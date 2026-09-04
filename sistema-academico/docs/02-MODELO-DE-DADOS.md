# 2. Estrutura do Banco de Dados — Entidades e Relacionamentos

## 2.1 Diagrama de relacionamentos

```
                         ┌────────────┐
                         │   users    │  role: admin | professor | aluno
                         └─────┬──────┘
             leciona           │ 0..1  (perfil do aluno)
        ┌──────────────────────┤
        │                      ▼
        │              ┌──────────────┐        ┌──────────────┐
        │              │   students   │───────►│ enrollments  │ (histórico)
        │              └──────┬───────┘  1   * └──────┬───────┘
        │                     │                       │ *
        │                     │                       ▼ 1
┌───────▼──────┐      ┌───────┴──────┐        ┌──────────────┐      ┌──────────┐
│   subjects   │      │              │        │   classes    │─────►│ courses  │
│ (disciplina) │      │              │        │   (turma)    │ *  1 │ (curso)  │
└──┬───────┬───┘      │              │        └──────┬───────┘      └──────────┘
   │       │          │              │               │
   │ 1     │ 1        │              │               │ 1
   │ *     │ *        │              │               │ *
   ▼       ▼          │              │        ┌──────▼────────┐
┌────────┐ ┌────────────────┐        └───────►│ class_subjects│  (turma × disciplina)
│ topics │ │                │                 └──┬─────────┬──┘
│assunto/│ │                │                    │ 1       │ 1
│ tópico │ │                │                    │ *       │ *
└───┬────┘ │                │              ┌─────▼───┐ ┌───▼──────────┐
    │      │                │              │ lessons │ │ assessments  │
    │      │                │              │ (aulas) │ │ (avaliações) │
    │      │                │              └────┬────┘ └───────┬──────┘
    │      │                │                   │ 1            │ 1
    │      │                │                   │ *            │ *
    │      │           ┌────▼──────────┐  ┌─────▼───────┐ ┌────▼──────────┐
    ├─────►│ lesson_topics │◄──────────┘  │ attendances │ │   questions   │
    │      └───────────────┘              │ (frequência)│ │  (questões)   │
    │                                     └─────────────┘ └───┬───────┬───┘
    │                                                         │ 1     │ 1
    └─────────────────────────────────────────────────────────┘ *     │ *
                                                    ┌──────────────┐  │
                                                    │question_     │◄─┘
                                                    │options       │
                                                    │(alternativas)│
                                                    └──────────────┘
                          ┌────────────────┐   ┌────────┐
   students ─────────────►│ student_answers│   │ grades │◄──── assessments
                          │   (respostas)  │   │(notas/ │◄──── students
                          └────────────────┘   │result.)│
                                               └────────┘
   settings (parâmetros)      alert_dismissals (alertas tratados)
```

Cadeia principal exigida no escopo:

`Curso → Turmas → Alunos` · `Turma → Disciplinas` · `Disciplina → Aulas` ·
`Disciplina → Avaliações` · `Avaliação → Questões` · `Aluno → Respostas` ·
`Respostas → Resultados`.

## 2.2 Tabelas

### `users` — usuários do sistema
| Campo | Tipo | Notas |
|---|---|---|
| id | PK | |
| name | varchar(150) | |
| email | varchar(150) UNIQUE | login |
| password_hash | varchar(255) | `password_hash()` |
| role | enum(admin, professor, aluno) | controle de acesso |
| student_id | FK students NULL | preenchido quando `role='aluno'` |
| is_active | bool | |
| must_change_password | bool | força troca no 1º acesso |
| document, phone, qualification, notes | | perfil do professor sobre a mesma identidade de acesso — evita o clássico "cadastro de professor sem login" |
| last_login_at, created_at, updated_at | datetime | |

### `courses` — cursos
`id, name, description, workload_hours, status(ativo|inativo), created_at`

### `classes` — turmas
`id, code UNIQUE, name, course_id→courses, year, period, start_date, end_date,`
`status(planejada|em_andamento|concluida|cancelada), created_at`

### `students` — alunos
`id, full_name, document(CPF/identificador, opcional, UNIQUE quando preenchido),`
`email, phone, birth_date, enrolled_at, status(ativo|inativo|concluido),`
`notes(observações pedagógicas), created_at, updated_at`

### `enrollments` — vínculo aluno × turma (com histórico)
`id, student_id→students, class_id→classes, started_at, ended_at NULL,`
`is_current(bool), status(ativo|transferido|concluido|trancado)`
Restrição: no máximo **um** vínculo com `is_current = 1` por aluno.
Trocar de turma encerra o vínculo anterior (`ended_at`, `status='transferido'`)
e cria um novo — o histórico nunca é apagado.

### `subjects` — disciplinas
`id, name, description, teacher_user_id→users NULL, workload_hours, status`

### `class_subjects` — disciplina ofertada em uma turma
`id, class_id→classes, subject_id→subjects, teacher_user_id NULL`
UNIQUE(class_id, subject_id). É a **unidade de oferta**: aulas, avaliações e
frequência penduram aqui, o que permite a mesma disciplina em várias turmas com
conteúdos e notas independentes.

### `topics` — assuntos e tópicos
`id, subject_id→subjects, parent_id→topics NULL, name, description, sort_order`
`parent_id IS NULL` ⇒ **assunto**; `parent_id` preenchido ⇒ **tópico** do assunto.
Uma única árvore atende "conteúdos / assuntos / tópicos" do escopo.

### `lessons` — aulas
`id, class_subject_id→class_subjects, title, lesson_date, content(conteúdo`
`ministrado), duration_minutes, materials, notes, created_at`

### `lesson_topics` — tópicos abordados na aula
`lesson_id→lessons, topic_id→topics` (PK composta)

### `attendances` — frequência e participação
`id, lesson_id→lessons, student_id→students,`
`status(presente|falta|falta_justificada|atraso), participation(0..5), notes`
UNIQUE(lesson_id, student_id)

### `assessments` — avaliações
`id, class_subject_id→class_subjects, name, type(prova|simulado|atividade|`
`exercicio|diagnostica|revisao), assessment_date, max_score, weight,`
`description(conteúdo abordado), status(planejada|aplicada|corrigida)`

### `questions` — questões
`id, assessment_id→assessments NULL, subject_id→subjects, topic_id→topics NULL,`
`number, statement, difficulty(facil|medio|dificil), points, answer_key,`
`type(objetiva|discursiva)`
`assessment_id` nulo ⇒ questão no **banco de questões**, reaproveitável.

### `question_options` — alternativas
`id, question_id→questions, letter, content, is_correct`

### `student_answers` — respostas dos alunos
`id, question_id→questions, student_id→students, given_answer,`
`result(correta|incorreta|nao_respondida), score_earned, answered_at`
UNIQUE(question_id, student_id)

### `grades` — notas / resultados consolidados
`id, assessment_id→assessments, student_id→students, score, percentage,`
`correct_count, wrong_count, blank_count, is_manual, notes, updated_at`
UNIQUE(assessment_id, student_id).
Quando existem respostas registradas, a nota é **recalculada automaticamente**
a partir delas; `is_manual = 1` protege lançamentos feitos direto pelo professor.

### `interventions` — acompanhamento pedagógico
`id, student_id→students, class_subject_id→class_subjects NULL,`
`author_user_id→users, alert_key NULL, type(conversa|reforco|material|`
`contato_responsavel|encaminhamento|outro), title, description, action_taken,`
`due_date, status(aberta|em_andamento|concluida|cancelada), result_note,`
`baseline_media, baseline_frequencia, created_at, updated_at, closed_at`

Fecha o ciclo do sistema: o alerta aponta o aluno, aqui se registra o que foi
feito. `baseline_media` e `baseline_frequencia` são **congelados na abertura** —
é contra eles que o efeito é medido depois. `alert_key` liga o registro ao
alerta que o originou.

### `migrations` — controle de versão do schema
`migration UNIQUE, applied_at` — o que já rodou de `database/migrations/`.

### `settings` — parâmetros configuráveis
`key(PK), value` — faixas de classificação, pesos do Índice de Desenvolvimento,
limites dos alertas, frequência mínima.

### `alert_dismissals` — alertas já tratados
`id, alert_key UNIQUE, dismissed_by→users, dismissed_at`

### `login_attempts` — proteção contra força bruta
`id, email, ip, attempted_at, success`

### `activity_log` — trilha de auditoria
`id, user_id, action, entity, entity_id, details, created_at`

## 2.3 Integridade e regras estruturais

1. Aluno pertence a **uma** turma corrente (`enrollments.is_current`), com
   histórico completo de turmas anteriores.
2. Troca de turma preserva notas, respostas e frequência — elas pertencem ao
   aluno e à avaliação/aula, não ao vínculo.
3. `ON DELETE RESTRICT` em cursos, turmas e disciplinas que possuem dependentes:
   o sistema **impede** apagar algo que deixaria registros órfãos e explica o
   motivo na interface.
4. `ON DELETE CASCADE` apenas em dependências fracas (alternativas de questão,
   tópicos de aula, vínculos turma-disciplina sem aulas).
5. Nota nunca excede `assessments.max_score`; percentual é sempre derivado.
6. Datas de avaliação/aula devem cair dentro do intervalo da turma (aviso, não
   bloqueio, para permitir reposições).
7. Índices em todas as FKs e nas colunas de data usadas em filtros por período.
8. O professor responde por **ofertas** (`class_subjects`), não por turmas ou
   disciplinas soltas — é esse vínculo que recorta tudo o que ele enxerga.
   Desativar ou excluir um professor exige transferir as ofertas antes, para
   nenhuma aula ficar sem responsável.
