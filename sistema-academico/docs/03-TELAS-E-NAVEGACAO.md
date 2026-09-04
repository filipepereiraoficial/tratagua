# 3. Mapa de Telas e Fluxo de Navegação

## 3.1 Mapa de telas

| # | Tela | Rota | Perfil | Conteúdo |
|---|---|---|---|---|
| 0 | Login | `GET/POST /login` | público | e-mail, senha, aviso de bloqueio |
| 0b | Trocar senha | `GET/POST /senha` | autenticado | obrigatória no 1º acesso |
| 1 | Dashboard geral | `/` | admin/prof | 11 indicadores, 8 gráficos, alertas, ranking |
| 2 | Alunos — lista | `/alunos` | admin/prof | busca, filtros (turma/status), ordenação, paginação |
| 2a | Aluno — novo/editar | `/alunos/novo`, `/alunos/{id}/editar` | admin/prof | dados + turma (vínculo direto opcional) |
| 2b | **Aluno — dashboard individual** | `/alunos/{id}` | admin/prof | indicadores, 8 gráficos, domínio/dificuldade, alertas, histórico |
| 2c | Aluno — transferir turma | `POST /alunos/{id}/turma` | admin/prof | encerra vínculo e abre novo |
| 3 | Turmas — lista | `/turmas` | admin/prof | filtros curso/ano/status |
| 3a | Turma — novo/editar | `/turmas/novo`, `/turmas/{id}/editar` | admin/prof | |
| 3b | **Turma — painel** | `/turmas/{id}` | admin/prof | desempenho geral, alunos vinculados, disciplinas, ranking interno, assuntos críticos |
| 4 | Disciplinas — lista | `/disciplinas` | admin/prof | |
| 4a | Disciplina — novo/editar | `/disciplinas/novo`, `/disciplinas/{id}/editar` | admin/prof | |
| 4b | Disciplina — detalhe | `/disciplinas/{id}` | admin/prof | assuntos/tópicos (árvore), turmas, aulas, avaliações, desempenho |
| 5 | Aulas — lista | `/aulas` | admin/prof | filtros turma/disciplina/período/aluno |
| 5a | Aula — novo/editar | `/aulas/nova`, `/aulas/{id}/editar` | admin/prof | conteúdo, tópicos, materiais |
| 5b | **Aula — chamada** | `/aulas/{id}/frequencia` | admin/prof | presença + participação de todos os alunos |
| 6 | Avaliações — lista | `/avaliacoes` | admin/prof | filtros turma/disciplina/tipo/período |
| 6a | Avaliação — novo/editar | `/avaliacoes/nova`, `/avaliacoes/{id}/editar` | admin/prof | |
| 6b | Avaliação — questões | `/avaliacoes/{id}/questoes` | admin/prof | montar prova, tópico e dificuldade por questão |
| 6c | **Avaliação — resultados** | `/avaliacoes/{id}/resultados` | admin/prof | grade aluno × questão (C/E/N) **ou** nota direta; cálculo automático |
| 6d | Avaliação — análise | `/avaliacoes/{id}` | admin/prof | índice de acerto por questão/tópico/dificuldade |
| 7 | Banco de questões | `/questoes` | admin/prof | filtros disciplina/assunto/dificuldade; estatística de acerto |
| 8 | Relatórios | `/relatorios` + 8 sub-relatórios | admin/prof | filtros + exportação CSV e impressão/PDF |
| 9 | Gráficos & Comparações | `/graficos` | admin/prof | comparar aluno×aluno, aluno×turma, turma×turma, disciplina×disciplina, avaliação×avaliação |
| 10 | Configurações | `/configuracoes` | admin | faixas, pesos, limites de alerta, frequência mínima |
| 10a | Usuários | `/configuracoes/usuarios` | admin | criar/editar/desativar usuários |
| — | API JSON | `/api/*` | autenticado | séries dos gráficos |

## 3.2 Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ ☰  Painel Pedagógico            🔔 alertas   Filipe Pereira ▾  │ topbar
├───────────────┬─────────────────────────────────────────────────┤
│ ▸ Dashboard   │  Título da página                 [ações]       │
│ ▸ Alunos      │  ┌────────┐┌────────┐┌────────┐┌────────┐       │
│ ▸ Turmas      │  │ card   ││ card   ││ card   ││ card   │       │ indicadores
│ ▸ Disciplinas │  └────────┘└────────┘└────────┘└────────┘       │
│ ▸ Aulas       │  ┌───────────────────────┐┌──────────────────┐  │
│ ▸ Avaliações  │  │  gráfico              ││  gráfico         │  │
│ ▸ Questões    │  └───────────────────────┘└──────────────────┘  │
│ ▸ Relatórios  │  ┌─────────────────────────────────────────┐    │
│ ▸ Gráficos    │  │  tabela / ranking / alertas             │    │
│ ▸ Configurações│ └─────────────────────────────────────────┘    │
└───────────────┴─────────────────────────────────────────────────┘
```

Responsivo: em telas < 1024px o menu lateral vira gaveta (☰); cards e gráficos
reorganizam-se em coluna única; tabelas largas ganham rolagem horizontal
própria.

## 3.3 Fluxo de navegação principal

```
Login
  └─► Dashboard geral
        ├─► [1] Curso ──► [2] Turma ──► [3] Disciplina ──► [4] Vincular disciplina à turma
        │                                   │
        │                                   └─► [5] Cadastrar alunos ─► [6] Vincular à turma
        │
        ├─► [7] Aulas + conteúdos ─► chamada (frequência/participação)
        │
        ├─► [8] Avaliação ─► [9] Questões (assunto, tópico, dificuldade)
        │                        └─► Resultados por aluno (C / E / N)
        │
        ├─► [10] Cálculo automático (médias, %, domínio, índice, alertas)
        │
        ├─► [11] Dashboards: geral · turma · disciplina · aluno
        │
        └─► [12] Alertas + Ranking ─► identificar quem precisa de atenção
                                     └─► Relatórios / Exportação
```

Atalhos previstos para reduzir cliques do professor:
- botão **"Novo aluno"** dentro do painel da turma já pré-seleciona a turma;
- **"Lançar resultados"** aparece direto na lista de avaliações;
- **"Fazer chamada"** aparece direto na lista de aulas;
- cada aluno listado leva ao seu dashboard individual com um clique.
