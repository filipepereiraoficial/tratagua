<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Input;

/**
 * Acompanhamento pedagógico: o registro do que foi FEITO a partir de um alerta.
 *
 * Fecha o ciclo que faltava no sistema — ele já dizia quem precisa de atenção,
 * mas não guardava a intervenção nem permitia saber se ela funcionou. Ao abrir
 * o registro, a média e a frequência do aluno são congeladas como linha de
 * base; ao concluir, a comparação com os valores atuais mostra o efeito.
 */
class Intervention
{
    public const TYPES = [
        'conversa'            => 'Conversa individual',
        'reforco'             => 'Reforço / monitoria',
        'material'            => 'Material de apoio',
        'contato_responsavel' => 'Contato com responsável',
        'encaminhamento'      => 'Encaminhamento',
        'outro'               => 'Outro',
    ];
    public const STATUSES = [
        'aberta'      => 'Aberta',
        'em_andamento'=> 'Em andamento',
        'concluida'   => 'Concluída',
        'cancelada'   => 'Cancelada',
    ];

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT i.*, s.full_name AS student_name, u.name AS author_name,
                    c.code AS class_code, sj.name AS subject_name
               FROM interventions i
               JOIN students s ON s.id = i.student_id
               LEFT JOIN users u ON u.id = i.author_user_id
               LEFT JOIN class_subjects cs ON cs.id = i.class_subject_id
               LEFT JOIN classes c ON c.id = cs.class_id
               LEFT JOIN subjects sj ON sj.id = cs.subject_id
              WHERE i.id = ?',
            [$id]
        );
    }

    public static function search(array $filters = [], int $limit = 200): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filters['aluno']))  { $where[] = 'i.student_id = :aluno';  $params['aluno'] = (int) $filters['aluno']; }
        if (!empty($filters['status'])) { $where[] = 'i.status = :status';     $params['status'] = $filters['status']; }
        if (!empty($filters['tipo']))   { $where[] = 'i.type = :tipo';         $params['tipo'] = $filters['tipo']; }
        if (!empty($filters['autor']))  { $where[] = 'i.author_user_id = :autor'; $params['autor'] = (int) $filters['autor']; }
        if (!empty($filters['abertas'])) { $where[] = "i.status IN ('aberta','em_andamento')"; }

        // Escopo: o professor vê os acompanhamentos das suas ofertas e os
        // gerais (sem oferta) dos alunos das turmas em que leciona.
        if (isset($filters['ofertas'])) {
            $ids = array_filter(array_map('intval', (array) $filters['ofertas']));
            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $lista = implode(',', $ids);
                $where[] = "(i.class_subject_id IN ({$lista})
                             OR (i.class_subject_id IS NULL AND i.student_id IN (
                                  SELECT e.student_id FROM enrollments e
                                    JOIN class_subjects cs2 ON cs2.class_id = e.class_id
                                   WHERE e.is_current = 1 AND cs2.id IN ({$lista}))))";
            }
        }

        return Database::all(
            'SELECT i.*, s.full_name AS student_name, u.name AS author_name,
                    c.code AS class_code, sj.name AS subject_name
               FROM interventions i
               JOIN students s ON s.id = i.student_id
               LEFT JOIN users u ON u.id = i.author_user_id
               LEFT JOIN class_subjects cs ON cs.id = i.class_subject_id
               LEFT JOIN classes c ON c.id = cs.class_id
               LEFT JOIN subjects sj ON sj.id = cs.subject_id
              WHERE ' . implode(' AND ', $where) . "
              ORDER BY CASE i.status WHEN 'aberta' THEN 0 WHEN 'em_andamento' THEN 1 ELSE 2 END,
                       i.due_date IS NULL, i.due_date, i.id DESC
              LIMIT " . (int) $limit,
            $params
        );
    }

    public static function forStudent(int $studentId): array
    {
        return self::search(['aluno' => $studentId]);
    }

    public static function create(array $data): int
    {
        return Database::insert('interventions', [
            'student_id'          => (int) $data['student_id'],
            'class_subject_id'    => Input::id($data, 'class_subject_id'),
            'author_user_id'      => $data['author_user_id'] ?? null,
            'alert_key'           => Input::text($data, 'alert_key'),
            'type'                => isset(self::TYPES[$data['type'] ?? '']) ? $data['type'] : 'conversa',
            'title'               => $data['title'],
            'description'         => Input::text($data, 'description'),
            'action_taken'        => Input::text($data, 'action_taken'),
            'due_date'            => Input::text($data, 'due_date'),
            'status'              => isset(self::STATUSES[$data['status'] ?? '']) ? $data['status'] : 'aberta',
            'baseline_media'      => $data['baseline_media'] ?? null,
            'baseline_frequencia' => $data['baseline_frequencia'] ?? null,
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $status = isset(self::STATUSES[$data['status'] ?? '']) ? $data['status'] : 'aberta';
        $campos = [
            'class_subject_id' => Input::id($data, 'class_subject_id'),
            'type'             => isset(self::TYPES[$data['type'] ?? '']) ? $data['type'] : 'conversa',
            'title'            => $data['title'],
            'description'      => Input::text($data, 'description'),
            'action_taken'     => Input::text($data, 'action_taken'),
            'due_date'         => Input::text($data, 'due_date'),
            'status'           => $status,
            'result_note'      => Input::text($data, 'result_note'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        // Encerrar carimba a data; reabrir a limpa, para o histórico não mentir.
        $campos['closed_at'] = in_array($status, ['concluida', 'cancelada'], true) ? date('Y-m-d H:i:s') : null;

        Database::update('interventions', $campos, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('interventions', 'id = ?', [$id]);
    }

    /** Já existe acompanhamento aberto para este alerta? Evita duplicar. */
    public static function openForAlert(string $alertKey): ?array
    {
        return Database::first(
            "SELECT * FROM interventions
              WHERE alert_key = ? AND status IN ('aberta','em_andamento')
              ORDER BY id DESC",
            [$alertKey]
        );
    }

    /** @return array<string,int> contagem por situação */
    public static function countByStatus(array $filters = []): array
    {
        $linhas = self::search($filters, 1000);
        $contagem = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($linhas as $linha) {
            $contagem[$linha['status']]++;
        }
        $contagem['atrasadas'] = count(array_filter($linhas, static fn ($i) =>
            in_array($i['status'], ['aberta', 'em_andamento'], true)
            && $i['due_date'] !== null && $i['due_date'] < date('Y-m-d')));
        return $contagem;
    }

    /**
     * Efeito medido: diferença entre a média/frequência de agora e a linha de
     * base congelada na abertura. Sem linha de base, devolve null — o sistema
     * não inventa um "antes".
     */
    public static function effect(array $intervencao, ?float $mediaAtual, ?float $frequenciaAtual): array
    {
        $base = $intervencao['baseline_media'] === null ? null : (float) $intervencao['baseline_media'];
        $baseFreq = $intervencao['baseline_frequencia'] === null ? null : (float) $intervencao['baseline_frequencia'];
        return [
            'media'      => ($base !== null && $mediaAtual !== null) ? round($mediaAtual - $base, 2) : null,
            'frequencia' => ($baseFreq !== null && $frequenciaAtual !== null) ? round($frequenciaAtual - $baseFreq, 2) : null,
        ];
    }
}
