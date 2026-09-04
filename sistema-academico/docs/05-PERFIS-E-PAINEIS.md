# 5. Perfis, Escopo de Acesso e Painéis

## 5.1 Os três perfis

| Perfil | Enxerga | Cadastra | Painel de entrada |
|---|---|---|---|
| **Administrador** | a instituição inteira: todos os cursos, turmas, disciplinas, professores e alunos | cursos, turmas, disciplinas, professores, alunos, aulas, avaliações, questões | `/painel-admin` |
| **Professor** | **apenas as ofertas sob sua responsabilidade** (turma × disciplina) e os alunos dessas turmas | aulas, avaliações, questões, resultados, notas, frequência e acompanhamentos das suas ofertas | `/meu-painel` |
| **Aluno** | apenas a si mesmo | nada | `/minha-evolucao` |

## 5.2 Como o escopo é decidido

O vínculo do professor não é com a turma nem com a disciplina isoladamente — é
com a **oferta**, o par turma × disciplina (`class_subjects`). É esse par que
define tudo o que ele enxerga:

```
class_subjects.teacher_user_id = professor          ← vínculo direto da oferta
   ou (oferta sem professor próprio)
subjects.teacher_user_id       = professor          ← responsável da disciplina
        ↓
  App\Core\Scope::classSubjectIds()  →  [3, 7, 12]
        ↓
  turmas acessíveis   = turmas dessas ofertas
  disciplinas         = disciplinas dessas ofertas
  alunos acessíveis   = matriculados nessas turmas
  questões            = as das disciplinas dessas ofertas
```

A restrição é resolvida **uma vez**, em `Scope`, e viaja pelos filtros como
`ofertas`. Os serviços analíticos (`AnalyticsService`, `RankingService`,
`AlertService`) e os modelos de listagem sabem respeitá-la. Nenhuma consulta
repete a regra — e, por isso, nenhuma esquece dela.

Para o administrador, `classSubjectIds()` devolve `null`, que significa "sem
restrição": os mesmos serviços rodam sem cláusula adicional.

## 5.3 Duas camadas de proteção

O escopo é aplicado em dois pontos, porque filtrar a listagem não basta:

1. **Listagens e filtros** — o professor só vê nos selects e nas tabelas as
   turmas, disciplinas e alunos que lhe pertencem.
2. **Acesso direto por URL** — `Scope::canAccessClassSubject()` e
   `canAccessStudent()` barram com **403** quem digitar o id de um registro
   fora do escopo, e a validação dos formulários rejeita uma oferta alheia
   enviada por POST.

## 5.4 Painel do professor (`/meu-painel`)

Responde às três perguntas que o professor faz sobre a própria disciplina:

| Bloco | O que mostra |
|---|---|
| **Quem mais evoluiu** | maiores ganhos em pontos percentuais nas avaliações recentes |
| **Quem precisa de atenção** | classificação `atenção` com o motivo, e um botão que abre o acompanhamento já preenchido |
| **Quem mais deixou pontuação** | soma de pontos perdidos por aluno e em qual avaliação a perda foi maior |
| Indicadores | média dos alunos dele, % de acertos, aulas e avaliações registradas, distribuição por classificação |
| Gráficos | evolução, distribuição, aproveitamento por assunto, **pontos perdidos por avaliação** |
| Tabelas | todos os seus alunos com índice e situação; avaliações ordenadas por perda de pontos |
| Laterais | alertas dos seus alunos, acompanhamentos abertos, últimas aulas com estado da chamada |

Filtros: turma, disciplina, tipo de avaliação e período — todos limitados às
ofertas dele.

### Perda de pontuação

```
pontos_possíveis = Σ points das questões respondidas
pontos_obtidos   = Σ score_earned
pontos_perdidos  = pontos_possíveis − pontos_obtidos
```

Agregado por avaliação responde "em que prova a turma mais deixou pontos na
mesa"; agregado por aluno responde "quem mais deixou". A ordenação é por
**pontos perdidos**, não por média — uma prova longa com 60% de acerto tira
mais pontuação do que uma curta com 40%.

### Painel individual (`/meu-painel/aluno/{id}`)

O mesmo dashboard de aprendizagem do aluno, recortado nas disciplinas do
professor: média na disciplina dele, comparação com a turma, evolução, mapa de
conteúdos, pontos deixados na prova, alertas, acompanhamentos e presença.

## 5.5 Painel do administrador (`/painel-admin`)

O dashboard geral responde *como está indo*; este responde *onde está o
problema*:

- **Comparações**: curso × curso, turma × turma, disciplina × disciplina e
  professor × professor (média das turmas atendidas por cada um).
- **Pendências operacionais** — o que está impedindo o sistema de produzir
  análise, cada item com o link para resolver:
  oferta sem professor responsável · aluno ativo sem turma · disciplina sem
  assuntos cadastrados · avaliação aplicada sem resultados · aula sem chamada.
- **Alertas** do recorte e **últimas alterações** (trilha de auditoria).

A leitura da tabela de professores traz uma ressalva explícita na tela: a média
é a das turmas atendidas, e deve ser lida como contexto da turma, não como
avaliação do professor.

## 5.6 Acompanhamento pedagógico (`/acompanhamento`)

Fecha o ciclo que faltava: o sistema já dizia quem precisa de atenção, mas não
guardava o que foi feito nem permitia saber se funcionou.

```
alerta aponta o aluno
   → professor registra o acompanhamento (conversa, reforço, contato…)
   → o sistema CONGELA a média e a frequência daquele momento (linha de base)
   → ao reabrir o registro, compara com os valores atuais
   → "efeito na média: +7,4 p.p. (de 49,0% para 56,4%)"
```

Tipos: conversa individual · reforço/monitoria · material de apoio · contato
com responsável · encaminhamento · outro.
Situações: aberta · em andamento · concluída · cancelada — com prazo opcional,
e destaque para os que passaram do prazo.

Sem linha de base, o efeito aparece como *"sem linha de base"* — o sistema não
inventa um "antes".

## 5.7 Auditoria (`/auditoria`)

Somente administrador. Registra apenas operações que **alteram estado** (criar,
atualizar, excluir, vincular, lançar notas, registrar chamada) — leitura não
entra, para o log continuar legível. Filtros por usuário, entidade, ação e
período.

A gravação nunca derruba a operação que observa: uma falha ao registrar o log é
silenciada.

## 5.8 Painel do aluno (`/minha-evolucao`)

Leitura da própria evolução: média, comparação com a média da turma, evolução
recente, frequência, conteúdos dominados e a reforçar, e o histórico de
avaliações.

**Não** expõe o ranking nominal da turma nem os alertas internos — o aluno vê a
si mesmo, não os colegas. O vínculo é feito em *Configurações → Usuários*,
associando a conta (`role = aluno`) a um cadastro de aluno.

## 5.9 Migrações

Instalações existentes são atualizadas sem perder dados:

```bash
php database/migrate.php --migrate     # aplica o que estiver pendente
```

Cada migração é um arquivo por dialeto em `database/migrations/`
(`NNN_nome.{sqlite,mysql}.sql`), e o que já rodou fica registrado na tabela
`migrations`. Instalações novas aplicam o schema completo — que já contém tudo —
e as migrações entram diretamente como aplicadas. A aplicação também verifica
pendências no boot e as aplica uma única vez, então subir a versão nova pelo FTP
já basta.
