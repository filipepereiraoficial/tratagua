# Demonstração navegável

Página única que reproduz a interface do Painel Pedagógico e roda os mesmos
cálculos do servidor no navegador, sobre a turma de exemplo. Serve para avaliar
o sistema sem instalar PHP.

**Link:** https://claude.ai/code/artifact/39660770-d62f-4923-9bd8-199264637663

## O que dá para fazer

- Percorrer as dez telas do menu: dashboard, alunos, turmas, disciplinas, aulas,
  avaliações, questões, relatórios, gráficos e configurações.
- Abrir o dashboard individual de qualquer aluno, com o gráfico que decompõe o
  Índice de Desenvolvimento em suas quatro parcelas.
- **Lançar resultados**: em uma avaliação, clicar em `C` / `E` / `N` recalcula
  na hora a nota, o percentual, o mapa de conteúdos, o ranking e os alertas.
- **Alterar a frequência** na chamada e ver o índice se mover.
- **Mudar os critérios** em Configurações — faixas de classificação, pesos do
  índice e limites de alerta — e ver a turma inteira ser reclassificada.

O que a demonstração não faz: gravar dados (tudo vive na memória da aba),
autenticar, exportar CSV e imprimir em PDF. Isso existe apenas no sistema
instalado.

## Como é construída

| Arquivo | Papel |
|---|---|
| `fonte/01-estilo.html` | tokens de cor e tipografia, layout, componentes |
| `fonte/02-casca-e-graficos.html` | casca da aplicação, utilitários e os gráficos em SVG |
| `fonte/03-…`, `04-…`, `05-…` | as vistas |
| `analytics.js` | motor analítico portado de `src/Services/*.php` |
| `dados.json` | dataset exportado do banco do próprio sistema |
| `referencia.json` | valores calculados pelo PHP, usados na verificação |
| `painel-demo.html` | resultado publicado (gerado, não editar à mão) |

```bash
php database/migrate.php --fresh --seed --demo   # gera a turma de exemplo
php demo/exportar.php                            # dados.json + referencia.json
node demo/verificar.js                           # JS × PHP: têm de bater
node demo/montar.js                              # gera painel-demo.html
```

## Por que a verificação existe

O motor analítico está escrito duas vezes: em PHP no servidor e em JavaScript
para esta página. Duas implementações divergem com o tempo — então
`verificar.js` compara as duas em cada valor que a interface mostra (ranking,
médias, frequência, evolução, consistência, índice, aproveitamento por assunto e
por dificuldade, distribuição) e no texto de cada uma das 21 mensagens de alerta.
Se divergirem, o script falha.

Foi essa comparação que revelou um defeito real no servidor: o alerta de
dificuldade persistente disparava para todos os alunos em todos os assuntos.
No SQLite, o PDO envia todo parâmetro como texto e qualquer número é menor que
qualquer texto, então `aproveitamento < :limite` era sempre verdadeiro. Corrigido
em `AlertService::persistentDifficulties` com um `CAST(:limite AS REAL)`.
