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
     * Gera uma instituição de demonstração com dois cursos, duas disciplinas e
     * dois professores com responsabilidades diferentes — é o que permite
     * conferir o recorte por perfil:
     *
     *   Marina  → INF01 × Informática                      (5 alunos)
     *   Ricardo → INF01 × Legislação  +  AGT01 × Legislação (8 alunos)
     *   Admin   → tudo
     */
    function painel_seed_demo(int $classId, int $classSubjectId, int $subjectId, array $topicos): array
    {
        // ------------------------------------------------------- professores
        $marina = painel_seed_professor('Marina Alencar', 'professor@exemplo.com',
            'Licenciatura em Computação', '(81) 99999-0000');
        $ricardo = painel_seed_professor('Ricardo Menezes', 'ricardo@exemplo.com',
            'Bacharel em Direito', '(81) 98888-1111');

        Database::update('class_subjects', ['teacher_user_id' => $marina],
            'id = :id', ['id' => $classSubjectId]);

        // ------------------------------------- segunda disciplina e conteúdos
        $legislacaoId = painel_seed_find('subjects', 'name', 'Legislação Municipal');
        if ($legislacaoId === null) {
            $legislacaoId = Database::insert('subjects', [
                'name'            => 'Legislação Municipal',
                'description'     => 'Legislação aplicada à atuação da guarda municipal.',
                'teacher_user_id' => $ricardo,
                'workload_hours'  => 30,
                'status'          => 'ativa',
            ]);
        }
        $topicosLeg = painel_seed_conteudos($legislacaoId, [
            'Constituição Federal'   => ['Direitos fundamentais', 'Segurança pública'],
            'Estatuto da Guarda'     => ['Competências', 'Regime disciplinar'],
            'Direito Administrativo' => ['Princípios', 'Atos administrativos'],
            'Código de Trânsito'     => ['Infrações', 'Sinalização'],
        ]);

        // ------------------------------------- segundo curso e segunda turma
        $curso2 = painel_seed_find('courses', 'name', 'Preparatório para Agente de Trânsito');
        if ($curso2 === null) {
            $curso2 = Database::insert('courses', [
                'name'        => 'Preparatório para Agente de Trânsito',
                'description' => 'Curso preparatório para o concurso de agente de trânsito.',
                'status'      => 'ativo',
            ]);
        }
        $turma2 = painel_seed_find('classes', 'code', 'AGT01');
        if ($turma2 === null) {
            $turma2 = Database::insert('classes', [
                'code' => 'AGT01', 'name' => 'Turma Agentes 2026', 'course_id' => $curso2,
                'year' => 2026, 'period' => 'Matutino',
                'start_date' => '2026-03-02', 'end_date' => '2026-11-30', 'status' => 'em_andamento',
            ]);
        }

        // --------------------------------------------------- ofertas restantes
        $ofertaLegInf = painel_seed_oferta($classId, $legislacaoId, $ricardo);
        $ofertaLegAgt = painel_seed_oferta($turma2, $legislacaoId, $ricardo);

        // ------------------------------------------------------------- alunos
        $perfisInf = [
            ['nome' => 'Ana Beatriz Souza',    'base' => 58, 'passo' => 9,  'frequencia' => 0.95, 'forte' => 'Pacote Office',           'fraco' => 'Redes de Computadores'],
            ['nome' => 'Bruno Carvalho Lima',  'base' => 82, 'passo' => 1,  'frequencia' => 0.92, 'forte' => 'Segurança da Informação', 'fraco' => 'Hardware e Software'],
            ['nome' => 'Carla Mendes Rocha',   'base' => 76, 'passo' => -8, 'frequencia' => 0.88, 'forte' => 'Internet e Navegadores',  'fraco' => 'Redes de Computadores'],
            ['nome' => 'Diego Nunes Ferreira', 'base' => 47, 'passo' => 2,  'frequencia' => 0.58, 'forte' => 'Sistemas Operacionais',   'fraco' => 'Segurança da Informação'],
            ['nome' => 'Eduarda Prado Alves',  'base' => 66, 'passo' => 5,  'frequencia' => 0.97, 'forte' => 'Hardware e Software',     'fraco' => 'Pacote Office'],
        ];
        $perfisAgt = [
            ['nome' => 'Felipe Ramos Duarte',  'base' => 71, 'passo' => 6,  'frequencia' => 0.93, 'forte' => 'Código de Trânsito',      'fraco' => 'Direito Administrativo'],
            ['nome' => 'Gabriela Nunes Pires', 'base' => 54, 'passo' => -5, 'frequencia' => 0.72, 'forte' => 'Estatuto da Guarda',      'fraco' => 'Constituição Federal'],
            ['nome' => 'Henrique Alves Cruz',  'base' => 63, 'passo' => 4,  'frequencia' => 0.90, 'forte' => 'Constituição Federal',    'fraco' => 'Código de Trânsito'],
        ];

        $alunosInf = painel_seed_alunos($perfisInf, $classId, '2026-02-02');
        $alunosAgt = painel_seed_alunos($perfisAgt, $turma2, '2026-03-02');

        // ------------------------------------------------- aulas e avaliações
        $aulas = 0; $avaliacoes = 0;
        $r1 = painel_seed_oferta_conteudo($classSubjectId, $subjectId, $topicos, $alunosInf, 'Informática', '2026-02-09', [
            ['nome' => 'Avaliação diagnóstica', 'tipo' => 'diagnostica', 'data' => '2026-02-16'],
            ['nome' => 'Simulado 1',            'tipo' => 'simulado',    'data' => '2026-03-16'],
            ['nome' => 'Prova bimestral',       'tipo' => 'prova',       'data' => '2026-04-13'],
            ['nome' => 'Simulado 2',            'tipo' => 'simulado',    'data' => '2026-05-11'],
        ]);
        $r2 = painel_seed_oferta_conteudo($ofertaLegInf, $legislacaoId, $topicosLeg, $alunosInf, 'Legislação (INF01)', '2026-02-11', [
            ['nome' => 'Diagnóstica de Legislação', 'tipo' => 'diagnostica', 'data' => '2026-02-18'],
            ['nome' => 'Prova de Legislação 1',     'tipo' => 'prova',       'data' => '2026-03-25'],
            ['nome' => 'Simulado de Legislação',    'tipo' => 'simulado',    'data' => '2026-05-06'],
        ]);
        $r3 = painel_seed_oferta_conteudo($ofertaLegAgt, $legislacaoId, $topicosLeg, $alunosAgt, 'Legislação (AGT01)', '2026-03-09', [
            ['nome' => 'Diagnóstica AGT',       'tipo' => 'diagnostica', 'data' => '2026-03-17'],
            ['nome' => 'Prova de Trânsito 1',   'tipo' => 'prova',       'data' => '2026-04-21'],
            ['nome' => 'Simulado AGT',          'tipo' => 'simulado',    'data' => '2026-05-19'],
        ]);
        foreach ([$r1, $r2, $r3] as $r) { $aulas += $r['aulas']; $avaliacoes += $r['avaliacoes']; }

        // --------------------------------------- acompanhamentos pedagógicos
        painel_seed_acompanhamentos($alunosInf, $alunosAgt, $classSubjectId, $ofertaLegAgt, $marina, $ricardo);

        return [
            'alunos' => count($alunosInf) + count($alunosAgt),
            'aulas' => $aulas, 'avaliacoes' => $avaliacoes,
            'professor' => 'professor@exemplo.com',
        ];
    }

    function painel_seed_professor(string $nome, string $email, string $formacao, string $telefone): int
    {
        $id = painel_seed_find('users', 'email', $email);
        if ($id === null) {
            $id = User::create([
                'name' => $nome, 'email' => $email, 'password' => 'Professor@2026',
                'role' => 'professor', 'must_change_password' => 0,
            ]);
        }
        Database::update('users', ['qualification' => $formacao, 'phone' => $telefone],
            'id = :id', ['id' => $id]);
        return $id;
    }

    /** Cria a árvore assunto → tópicos e devolve o mapa usado pelas questões. */
    function painel_seed_conteudos(int $subjectId, array $arvore): array
    {
        $mapa = [];
        $ordem = 0;
        foreach ($arvore as $assunto => $filhos) {
            $assuntoId = painel_seed_find_topic($subjectId, $assunto, null);
            if ($assuntoId === null) {
                $assuntoId = Database::insert('topics', [
                    'subject_id' => $subjectId, 'parent_id' => null,
                    'name' => $assunto, 'sort_order' => $ordem++,
                ]);
            }
            $mapa[$assunto] = ['id' => $assuntoId, 'filhos' => []];
            foreach ($filhos as $i => $filho) {
                $filhoId = painel_seed_find_topic($subjectId, $filho, $assuntoId);
                if ($filhoId === null) {
                    $filhoId = Database::insert('topics', [
                        'subject_id' => $subjectId, 'parent_id' => $assuntoId,
                        'name' => $filho, 'sort_order' => $i,
                    ]);
                }
                $mapa[$assunto]['filhos'][] = $filhoId;
            }
        }
        return $mapa;
    }

    function painel_seed_oferta(int $classId, int $subjectId, ?int $teacherId): int
    {
        $id = Database::value('SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ?',
            [$classId, $subjectId]);
        if ($id !== null) {
            Database::update('class_subjects', ['teacher_user_id' => $teacherId], 'id = :id', ['id' => (int) $id]);
            return (int) $id;
        }
        return Database::insert('class_subjects', [
            'class_id' => $classId, 'subject_id' => $subjectId, 'teacher_user_id' => $teacherId,
        ]);
    }

    function painel_seed_alunos(array $perfis, int $classId, string $desde): array
    {
        $alunos = [];
        foreach ($perfis as $perfil) {
            $studentId = painel_seed_find('students', 'full_name', $perfil['nome']);
            if ($studentId === null) {
                $primeiro = mb_strtolower(explode(' ', $perfil['nome'])[0]);
                $studentId = Database::insert('students', [
                    'full_name'   => $perfil['nome'],
                    'email'       => $primeiro . '@exemplo.com',
                    'enrolled_at' => $desde,
                    'status'      => 'ativo',
                    'notes'       => 'Aluno de demonstração — pode ser excluído com segurança.',
                ]);
                Database::insert('enrollments', [
                    'student_id' => $studentId, 'class_id' => $classId,
                    'started_at' => $desde, 'is_current' => 1, 'status' => 'ativo',
                ]);
            }
            $alunos[] = ['id' => $studentId] + $perfil;
        }
        return $alunos;
    }

    /**
     * Preenche uma oferta: aulas com chamada, avaliações com questões
     * classificadas por assunto e as respostas de cada aluno.
     */
    function painel_seed_oferta_conteudo(int $classSubjectId, int $subjectId, array $topicos,
                                         array $alunos, string $rotulo, string $primeiraAula, array $avaliacoes): array
    {
        $nomesAssuntos = array_keys($topicos);
        $aulasCriadas = 0;
        $data = new DateTimeImmutable($primeiraAula);

        foreach ($nomesAssuntos as $indice => $assunto) {
            $titulo = $rotulo . ' — aula ' . ($indice + 1) . ': ' . $assunto;
            if (painel_seed_find('lessons', 'title', $titulo) !== null) {
                $data = $data->modify('+7 days');
                continue;
            }
            $lessonId = Database::insert('lessons', [
                'class_subject_id' => $classSubjectId,
                'title'            => $titulo,
                'lesson_date'      => $data->format('Y-m-d'),
                'content'          => 'Conteúdo trabalhado: ' . $assunto . '.',
                'duration_minutes' => 100,
            ]);
            Database::insert('lesson_topics', ['lesson_id' => $lessonId, 'topic_id' => $topicos[$assunto]['id']]);

            foreach ($alunos as $aluno) {
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
            $data = $data->modify('+7 days');
        }

        $criadas = 0;
        $porAssunto = 2;                       // 2 questões por assunto em cada prova
        $totalQuestoes = count($nomesAssuntos) * $porAssunto;

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

            $questoes = [];
            $numero = 1;
            foreach ($topicos as $assunto => $dados) {
                for ($rep = 0; $rep < $porAssunto; $rep++) {
                    $questoes[] = [
                        'id' => Database::insert('questions', [
                            'assessment_id' => $assessmentId,
                            'subject_id'    => $subjectId,
                            'topic_id'      => $dados['filhos'][$rep] ?? $dados['id'],
                            'number'        => $numero,
                            'statement'     => 'Questão ' . $numero . ' sobre ' . $assunto . '.',
                            'type'          => 'objetiva',
                            'difficulty'    => ['facil', 'medio', 'dificil'][($numero + $rodada) % 3],
                            'points'        => round(10 / $totalQuestoes, 2),
                            'answer_key'    => ['A', 'B', 'C', 'D'][$numero % 4],
                        ]),
                        'assunto' => $assunto,
                        'numero'  => $numero,
                        'pontos'  => round(10 / $totalQuestoes, 2),
                    ];
                    $numero++;
                }
            }

            foreach ($alunos as $aluno) {
                $alvo = $aluno['base'] + $aluno['passo'] * $rodada;
                foreach ($questoes as $questao) {
                    $chance = $alvo;
                    if ($questao['assunto'] === $aluno['forte']) { $chance += 18; }
                    if ($questao['assunto'] === $aluno['fraco'])  { $chance -= 28; }
                    $chance = max(5, min(97, $chance));

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
            $criadas++;
        }

        return ['aulas' => $aulasCriadas, 'avaliacoes' => $criadas];
    }

    /** Dois acompanhamentos abertos e um concluído, para a tela nascer com conteúdo. */
    function painel_seed_acompanhamentos(array $alunosInf, array $alunosAgt,
                                         int $ofertaInf, int $ofertaAgt, int $marina, int $ricardo): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM interventions', [], 0) > 0) {
            return;
        }
        $porNome = static function (array $lista, string $nome) {
            foreach ($lista as $aluno) { if ($aluno['nome'] === $nome) { return (int) $aluno['id']; } }
            return null;
        };
        $registros = [
            [$porNome($alunosInf, 'Diego Nunes Ferreira'), $ofertaInf, $marina, 'reforco', 'em_andamento',
             'Reforço em Segurança da Informação',
             'Média abaixo de 60% e frequência abaixo do mínimo.',
             'Monitoria às quintas e lista dirigida de exercícios.', '2026-06-30', null],
            [$porNome($alunosInf, 'Carla Mendes Rocha'), $ofertaInf, $marina, 'conversa', 'aberta',
             'Conversa sobre queda de desempenho',
             'Queda de 16,7 p.p. entre as primeiras e as últimas avaliações.',
             'Conversa individual agendada para entender o que mudou.', '2026-06-20', null],
            [$porNome($alunosAgt, 'Gabriela Nunes Pires'), $ofertaAgt, $ricardo, 'contato_responsavel', 'concluida',
             'Contato sobre frequência',
             'Frequência abaixo do mínimo configurado.',
             'Contato telefônico com o responsável.', '2026-05-30',
             'Responsável ciente; aluna retomou a presença nas duas aulas seguintes.'],
        ];
        foreach ($registros as [$aluno, $oferta, $autor, $tipo, $status, $titulo, $descricao, $acao, $prazo, $resultado]) {
            if ($aluno === null) { continue; }
            $resumo = \App\Services\AnalyticsService::studentSummary($aluno);
            Database::insert('interventions', [
                'student_id' => $aluno, 'class_subject_id' => $oferta, 'author_user_id' => $autor,
                'type' => $tipo, 'status' => $status, 'title' => $titulo,
                'description' => $descricao, 'action_taken' => $acao, 'due_date' => $prazo,
                'result_note' => $resultado,
                'baseline_media' => $resumo['media'], 'baseline_frequencia' => $resumo['frequencia'],
                'closed_at' => $status === 'concluida' ? date('Y-m-d H:i:s') : null,
            ]);
        }
    }

}
