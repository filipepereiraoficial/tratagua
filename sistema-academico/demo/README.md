# Demonstração navegável

Página única que reproduz a interface do Painel Pedagógico e roda os mesmos
cálculos do servidor no navegador, sobre o curso de exemplo. Serve para avaliar
o sistema sem instalar PHP.

**Link:** https://claude.ai/code/artifact/39660770-d62f-4923-9bd8-199264637663

## Troque de perfil no topo da página

O seletor no canto superior direito é o coração da demonstração: ele troca o
usuário e recorta a aplicação inteira, exatamente como o `Core\Scope` faz no
sistema real.

| Perfil | O que enxerga |
|---|---|
| **Filipe Pereira** — administrador | os 2 cursos, 2 turmas, 3 disciplinas e 8 alunos; painel do administrador, cadastro de professores e auditoria |
| **Marina Alencar** — professora | só INF01 — Informática: 5 alunos, 1 turma, 1 disciplina |
| **Ricardo Menezes** — professor | suas duas ofertas de Legislação Municipal (INF01 e AGT01): 8 alunos, 2 turmas |
| **Ana Beatriz Souza** — aluna | apenas a própria evolução, sem ranking nominal e sem os alertas internos |

O menu lateral, os números do topo, o ranking, os alertas e todos os relatórios
mudam junto. Uma faixa azul no alto de cada tela explica o recorte ativo.

## O que dá para fazer

- **Painel do professor** (`Meu painel`): quem mais evoluiu na disciplina, quem
  está com menor desenvolvimento, em quais avaliações a turma mais deixou pontos
  na mesa e o mapa de conteúdos dominados × em dificuldade.
- **Painel do administrador**: visão consolidada dos cursos, com o desempenho de
  cada turma, de cada disciplina e de cada professor — e o botão *ver como* para
  entrar no recorte de um professor com um clique.
- **Painel individual** de qualquer aluno, com o gráfico que decompõe o Índice de
  Desenvolvimento em suas quatro parcelas.
- **Acompanhamento pedagógico**: registrar uma intervenção a partir de um alerta
  e ver o efeito medido contra a linha de base congelada no momento do registro.
- **Lançar resultados**: em uma avaliação, clicar em `C` / `E` / `N` recalcula na
  hora a nota, o percentual, o mapa de conteúdos, o ranking e os alertas.
- **Alterar a frequência** na chamada e ver o índice se mover.
- **Mudar os critérios** em Configurações — faixas de classificação, pesos do
  índice e limites de alerta — e ver a turma inteira ser reclassificada.
- **Nove relatórios** com os mesmos recortes do sistema instalado, inclusive o de
  pontos perdidos por avaliação.
- **Auditoria**: cada ação feita na demonstração entra na trilha, como no sistema.

O que a demonstração não faz: gravar dados (tudo vive na memória da aba),
autenticar, exportar CSV e imprimir em PDF. Isso existe apenas no sistema
instalado.

## Como é construída

| Arquivo | Papel |
|---|---|
| `fonte/01-estilo.html` | tokens de cor e tipografia, layout, componentes |
| `fonte/02-casca-e-graficos.html` | casca, seletor de perfil, recálculo e os gráficos em SVG |
| `fonte/03-navegacao-e-paineis.html` | menu por perfil, peças reutilizáveis, painéis do professor e do administrador |
| `fonte/04-vistas-aluno-e-pessoas.html` | dashboard, alunos, aluno, professores, minha evolução |
| `fonte/05-vistas-registros.html` | turmas, disciplinas, aulas, chamada, avaliações, questões, acompanhamento |
| `fonte/06-analise-e-roteador.html` | relatórios, gráficos, configurações, auditoria e o roteador |
| `analytics.js` | motor analítico portado de `src/Services/*.php` |
| `dados.json` | dataset exportado do banco do próprio sistema |
| `referencia.json` | valores calculados pelo PHP, usados na verificação |
| `painel-demo.html` | resultado publicado (gerado, não editar à mão) |

```bash
php database/migrate.php --fresh --seed --demo   # gera o curso de exemplo
php demo/exportar.php                            # dados.json + referencia.json
node demo/verificar.js                           # JS × PHP: têm de bater
node demo/montar.js                              # gera painel-demo.html
```

## Por que a verificação existe

O motor analítico está escrito duas vezes: em PHP no servidor e em JavaScript
para esta página. Duas implementações divergem com o tempo — então
`verificar.js` compara as duas em cada valor que a interface mostra (ranking,
médias, frequência, evolução, consistência, índice, aproveitamento por assunto e
por dificuldade, distribuição, pontos perdidos) e no texto de cada mensagem de
alerta. E faz isso **três vezes**: como administrador e como cada um dos dois
professores, conferindo também quais ofertas cada professor alcança. Se
divergirem, o script falha.

Essa comparação já revelou dois defeitos reais no servidor:

1. O alerta de dificuldade persistente disparava para todos os alunos em todos
   os assuntos. No SQLite, o PDO envia todo parâmetro como texto e qualquer
   número é menor que qualquer texto, então `aproveitamento < :limite` era sempre
   verdadeiro. Corrigido em `AlertService::persistentDifficulties` com um
   `CAST(:limite AS REAL)`.
2. `AnalyticsService::studentAttendance` e `attendanceByClass` ignoravam o
   recorte de ofertas: um professor via a frequência do aluno somando aulas de
   disciplinas que não são dele (75% no JS × 70% no PHP para o mesmo aluno).
