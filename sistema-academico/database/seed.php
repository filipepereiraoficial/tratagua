<?php
/**
 * Carga inicial do sistema (itens 21 e 22 do escopo):
 *   · conta administradora
 *   · curso "Preparatório para Guarda Municipal"
 *   · disciplina "Informática" (com assuntos e tópicos)
 *   · turma INF01 / 2026, com a disciplina já vinculada
 *
 * Opcionalmente gera dados de demonstração para o professor ver os
 * dashboards funcionando antes de cadastrar a turma real.
 */

use App\Core\Database;
use App\Models\Setting;
use App\Models\User;

if (!function_exists('painel_seed')) {
    /**
     * @param array{name:string,email:string,password:string} $admin
     * @return array{message:string}
     */
    function painel_seed(array $admin, bool $comDemo = false): array
    {
        $mensagens = [];

        // ------------------------------------------------------- administrador
        $existente = User::findByEmail($admin['email']);
        if ($existente) {
            $adminId = (int) $existente['id'];
            $mensagens[] = 'Administrador já existia.';
        } else {
            $adminId = User::create([
                'name'                 => $admin['name'],
                'email'                => $admin['email'],
                'password'             => $admin['password'],
                'role'                 => 'admin',
                'must_change_password' => 0,
            ]);
            $mensagens[] = 'Administrador criado.';
        }

        // -------------------------------------------------------------- curso
        $courseId = painel_seed_find('courses', 'name', 'Preparatório para Guarda Municipal');
        if ($courseId === null) {
            $courseId = Database::insert('courses', [
                'name'        => 'Preparatório para Guarda Municipal',
                'description' => 'Curso preparatório para o concurso da Guarda Municipal.',
                'status'      => 'ativo',
            ]);
        }

        // --------------------------------------------------------- disciplina
        $subjectId = painel_seed_find('subjects', 'name', 'Informática');
        if ($subjectId === null) {
            $subjectId = Database::insert('subjects', [
                'name'            => 'Informática',
                'description'     => 'Noções de informática para concursos públicos.',
                'teacher_user_id' => $adminId,
                'workload_hours'  => 40,
                'status'          => 'ativa',
            ]);
        }

        // --------------------------------------- assuntos e tópicos iniciais
        $conteudos = [
            'Hardware e Software' => ['Componentes do computador', 'Periféricos', 'Sistemas operacionais'],
            'Sistemas Operacionais' => ['Windows', 'Linux', 'Gerenciamento de arquivos'],
            'Pacote Office' => ['Word', 'Excel', 'PowerPoint'],
            'Internet e Navegadores' => ['Protocolos', 'Navegadores', 'Correio eletrônico'],
            'Redes de Computadores' => ['Topologias', 'Endereçamento IP', 'Equipamentos de rede'],
            'Segurança da Informação' => ['Malwares', 'Backup', 'Criptografia'],
        ];
        $topicos = [];
        $ordem = 0;
        foreach ($conteudos as $assunto => $filhos) {
            $assuntoId = painel_seed_find_topic($subjectId, $assunto, null);
            if ($assuntoId === null) {
                $assuntoId = Database::insert('topics', [
                    'subject_id' => $subjectId,
                    'parent_id'  => null,
                    'name'       => $assunto,
                    'sort_order' => $ordem++,
                ]);
            }
            $topicos[$assunto] = ['id' => $assuntoId, 'filhos' => []];
            foreach ($filhos as $indice => $filho) {
                $filhoId = painel_seed_find_topic($subjectId, $filho, $assuntoId);
                if ($filhoId === null) {
                    $filhoId = Database::insert('topics', [
                        'subject_id' => $subjectId,
                        'parent_id'  => $assuntoId,
                        'name'       => $filho,
                        'sort_order' => $indice,
                    ]);
                }
                $topicos[$assunto]['filhos'][] = $filhoId;
            }
        }

        // --------------------------------------------------------------- turma
        $classId = painel_seed_find('classes', 'code', 'INF01');
        if ($classId === null) {
            $classId = Database::insert('classes', [
                'code'       => 'INF01',
                'name'       => 'Turma Informática 2026',
                'course_id'  => $courseId,
                'year'       => 2026,
                'period'     => 'Noturno',
                'start_date' => '2026-02-02',
                'end_date'   => '2026-11-30',
                'status'     => 'em_andamento',
            ]);
        }

        // ------------------------------------------ vínculo turma × disciplina
        $classSubjectId = (int) (Database::value(
            'SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?',
            [$classId, $subjectId]
        ) ?? 0);
        if ($classSubjectId === 0) {
            $classSubjectId = Database::insert('class_subjects', [
                'class_id'        => $classId,
                'subject_id'      => $subjectId,
                'teacher_user_id' => $adminId,
            ]);
        }

        Setting::resetToDefaults();
        $mensagens[] = 'Curso, disciplina Informática e turma INF01 criados e vinculados.';

        if ($comDemo) {
            $criados = painel_seed_demo($classId, $classSubjectId, $subjectId, $topicos);
            $mensagens[] = "Dados de demonstração: {$criados['alunos']} aluno(s), {$criados['aulas']} aula(s) e {$criados['avaliacoes']} avaliação(ões).";
            $mensagens[] = "Professor de exemplo: {$criados['professor']} / Professor@2026.";
        }

        return ['message' => implode(' ', $mensagens)];
    }

    function painel_seed_find(string $table, string $column, string $value): ?int
    {
        $id = Database::value("SELECT id FROM {$table} WHERE {$column} = ?", [$value]);
        return $id === null ? null : (int) $id;
    }

    function painel_seed_find_topic(int $subjectId, string $name, ?int $parentId): ?int
    {
        $sql = 'SELECT id FROM topics WHERE subject_id = ? AND name = ? AND parent_id ' . ($parentId === null ? 'IS NULL' : '= ?');
        $params = $parentId === null ? [$subjectId, $name] : [$subjectId, $name, $parentId];
        $id = Database::value($sql, $params);
        return $id === null ? null : (int) $id;
    }

    /**
     * Gera uma turma de demonstração com perfis de aprendizagem distintos —
     * um aluno em evolução, um estável, um em queda e um faltoso — para que os
     * dashboards, o ranking e os alertas mostrem casos reais desde o início.
     */
    function painel_seed_demo(int $classId, int $classSubjectId, int $subjectId, array $topicos): array
    {
        // Um professor de verdade para a oferta: sem ele não dá para conferir o
        // painel do professor nem o recorte por responsabilidade.
        $professorId = painel_seed_find('users', 'email', 'professor@exemplo.com');
        if ($professorId === null) {
            $professorId = User::create([
                'name'                 => 'Marina Alencar',
                'email'                => 'professor@exemplo.com',
                'password'             => 'Professor@2026',
                'role'                 => 'professor',
                'must_change_password' => 0,
            ]);
            Database::update('users', [
                'qualification' => 'Licenciatura em Computação',
                'phone'         => '(81) 99999-0000',
            ], 'id = :id', ['id' => $professorId]);
        }
        Database::update('class_subjects', ['teacher_user_id' => $professorId],
            'id = :id', ['id' => $classSubjectId]);

        $perfis = [
            ['nome' => 'Ana Beatriz Souza',    'base' => 58, 'passo' => 9,  'frequencia' => 0.95, 'forte' => 'Pacote Office',        'fraco' => 'Redes de Computadores'],
            ['nome' => 'Bruno Carvalho Lima',  'base' => 82, 'passo' => 1,  'frequencia' => 0.92, 'forte' => 'Segurança da Informação', 'fraco' => 'Hardware e Software'],
            ['nome' => 'Carla Mendes Rocha',   'base' => 76, 'passo' => -8, 'frequencia' => 0.88, 'forte' => 'Internet e Navegadores', 'fraco' => 'Redes de Computadores'],
            ['nome' => 'Diego Nunes Ferreira', 'base' => 47, 'passo' => 2,  'frequencia' => 0.58, 'forte' => 'Sistemas Operacionais', 'fraco' => 'Segurança da Informação'],
            ['nome' => 'Eduarda Prado Alves',  'base' => 66, 'passo' => 5,  'frequencia' => 0.97, 'forte' => 'Hardware e Software',   'fraco' => 'Pacote Office'],
        ];

        $alunos = [];
        foreach ($perfis as $perfil) {
            $studentId = painel_seed_find('students', 'full_name', $perfil['nome']);
            if ($studentId === null) {
                $studentId = Database::insert('students', [
                    'full_name'   => $perfil['nome'],
                    'email'       => strtolower(str_replace(' ', '.', explode(' ', $perfil['nome'])[0])) . '@exemplo.com',
                    'enrolled_at' => '2026-02-02',
                    'status'      => 'ativo',
                    'notes'       => 'Aluno de demonstração — pode ser excluído com segurança.',
                ]);
                Database::insert('enrollments', [
                    'student_id' => $studentId,
                    'class_id'   => $classId,
                    'started_at' => '2026-02-02',
                    'is_current' => 1,
                    'status'     => 'ativo',
                ]);
            }
            $alunos[] = ['id' => $studentId] + $perfil;
        }

        // ------------------------------------------------------------- aulas
        $nomesAssuntos = array_keys($topicos);
        $aulasCriadas = 0;
        $dataAula = new DateTimeImmutable('2026-02-09');
        foreach ($nomesAssuntos as $indice => $assunto) {
            $titulo = 'Aula ' . ($indice + 1) . ' — ' . $assunto;
            if (painel_seed_find('lessons', 'title', $titulo) !== null) {
                $dataAula = $dataAula->modify('+7 days');
                continue;
            }
            $lessonId = Database::insert('lessons', [
                'class_subject_id' => $classSubjectId,
                'title'            => $titulo,
                'lesson_date'      => $dataAula->format('Y-m-d'),
                'content'          => 'Conteúdo trabalhado: ' . $assunto . '.',
                'duration_minutes' => 100,
            ]);
            Database::insert('lesson_topics', ['lesson_id' => $lessonId, 'topic_id' => $topicos[$assunto]['id']]);

            foreach ($alunos as $aluno) {
                // Frequência determinística por perfil (mesma carga, mesmos números),
                // espalhada o suficiente para gerar faltas de verdade.
                $sorteio = (($indice * 37 + $aluno['id'] * 61) % 100) / 100;
                $presente = $sorteio < $aluno['frequencia'];
                Database::insert('attendances', [
                    'lesson_id'     => $lessonId,
                    'student_id'    => $aluno['id'],
                    'status'        => $presente ? 'presente' : 'falta',
                    'participation' => $presente ? min(5, max(1, (int) round($aluno['base'] / 20))) : null,
                ]);
            }
            $aulasCriadas++;
            $dataAula = $dataAula->modify('+7 days');
        }

        // -------------------------------------------------------- avaliações
        $avaliacoes = [
            ['nome' => 'Avaliação diagnóstica', 'tipo' => 'diagnostica', 'data' => '2026-02-16'],
            ['nome' => 'Simulado 1',            'tipo' => 'simulado',    'data' => '2026-03-16'],
            ['nome' => 'Prova bimestral',       'tipo' => 'prova',       'data' => '2026-04-13'],
            ['nome' => 'Simulado 2',            'tipo' => 'simulado',    'data' => '2026-05-11'],
        ];

        $avaliacoesCriadas = 0;
        foreach ($avaliacoes as $rodada => $config) {
            if (painel_seed_find('assessments', 'name', $config['nome']) !== null) {
                continue;
            }
            $assessmentId = Database::insert('assessments', [
                'class_subject_id' => $classSubjectId,
                'name'             => $config['nome'],
                'type'             => $config['tipo'],
                'assessment_date'  => $config['data'],
                'max_score'        => 10,
                'weight'           => $config['tipo'] === 'simulado' ? 1.5 : 1,
                'description'      => 'Avaliação de demonstração cobrindo os assuntos da disciplina.',
                'status'           => 'corrigida',
            ]);

            // 12 questões: 2 por assunto, dificuldades alternadas.
            $questoes = [];
            $numero = 1;
            foreach ($topicos as $assunto => $dados) {
                foreach ([0, 1] as $repeticao) {
                    $questoes[] = [
                        'id' => Database::insert('questions', [
                            'assessment_id' => $assessmentId,
                            'subject_id'    => $subjectId,
                            'topic_id'      => $dados['filhos'][$repeticao] ?? $dados['id'],
                            'number'        => $numero,
                            'statement'     => 'Questão ' . $numero . ' sobre ' . $assunto . '.',
                            'type'          => 'objetiva',
                            'difficulty'    => ['facil', 'medio', 'dificil'][($numero + $rodada) % 3],
                            'points'        => round(10 / 12, 2),
                            'answer_key'    => ['A', 'B', 'C', 'D'][$numero % 4],
                        ]),
                        'assunto' => $assunto,
                        'numero'  => $numero,
                        'pontos'  => round(10 / 12, 2),
                    ];
                    $numero++;
                }
            }

            foreach ($alunos as $aluno) {
                // Probabilidade de acerto: base do aluno + evolução + ajuste por assunto.
                $alvo = $aluno['base'] + $aluno['passo'] * $rodada;
                foreach ($questoes as $questao) {
                    $chance = $alvo;
                    if ($questao['assunto'] === $aluno['forte']) { $chance += 18; }
                    if ($questao['assunto'] === $aluno['fraco'])  { $chance -= 28; }
                    $chance = max(5, min(97, $chance));

                    // Sorteio determinístico (mesma carga gera sempre os mesmos números).
                    $semente = ($aluno['id'] * 7919 + $questao['numero'] * 104729 + $rodada * 1299709) % 100;
                    $resultado = $semente < $chance ? 'correta' : ($semente % 7 === 0 ? 'nao_respondida' : 'incorreta');

                    Database::insert('student_answers', [
                        'question_id'  => $questao['id'],
                        'student_id'   => $aluno['id'],
                        'result'       => $resultado,
                        'score_earned' => $resultado === 'correta' ? $questao['pontos'] : 0,
                        'given_answer' => $resultado === 'nao_respondida' ? null : ['A', 'B', 'C', 'D'][$semente % 4],
                    ]);
                }
                \App\Models\Grade::recalculate($assessmentId, (int) $aluno['id']);
            }
            $avaliacoesCriadas++;
        }

        return ['alunos' => count($alunos), 'aulas' => $aulasCriadas,
                'avaliacoes' => $avaliacoesCriadas, 'professor' => 'professor@exemplo.com'];
    }
}
