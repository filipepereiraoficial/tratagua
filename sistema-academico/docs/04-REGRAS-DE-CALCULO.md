# 4. Regras de Cálculo dos Indicadores

Todas as fórmulas abaixo estão implementadas em `src/Services/AnalyticsService.php`,
`RankingService.php` e `AlertService.php`. Os parâmetros entre `«»` são
configuráveis em **Configurações** (tabela `settings`).

## 4.1 Origem dos dados

Uma avaliação pode ser alimentada de duas formas — ambas produzem os mesmos
indicadores:

1. **Por questão** (preferencial): o professor marca, para cada aluno,
   `correta` / `incorreta` / `não respondida`. O sistema soma os pontos e grava
   em `grades` automaticamente. Só assim é possível analisar por assunto e por
   dificuldade.
2. **Nota direta**: o professor digita apenas a nota (`is_manual = 1`). Entra em
   médias, evolução e frequência, mas não alimenta a análise por assunto.

## 4.2 Nota e percentual

```
nota_bruta        = Σ pontos das questões corretas          (ou nota digitada)
percentual        = nota_bruta / valor_máximo × 100
acertos/erros/branco = contagem de student_answers por resultado
```

## 4.3 Médias

```
média_geral(aluno)          = Σ(percentual_i × peso_i) / Σ(peso_i)
média_por_disciplina(aluno) = idem, restrito às avaliações da disciplina
média_da_turma(avaliação)   = média aritmética dos percentuais dos alunos
```

O peso padrão de uma avaliação é `1.0`; simulados e provas podem receber peso
maior sem alterar nenhuma fórmula.

## 4.4 Aproveitamento por assunto/tópico (`topic mastery`)

Para cada par (aluno, tópico):

```
pontos_possíveis = Σ points das questões daquele tópico respondidas pelo aluno
pontos_obtidos   = Σ score_earned nessas questões
aproveitamento   = pontos_obtidos / pontos_possíveis × 100
```

Classificação (faixas configuráveis, padrão do escopo):

| Faixa | Classificação | Cor |
|---|---|---|
| «80» – 100% | **Domínio** | verde |
| «60» – «79»% | **Intermediário** | âmbar |
| 0 – «59»% | **Dificuldade** | vermelho |

Exigência mínima de amostra: `«min_questoes_assunto» = 3` questões respondidas.
Abaixo disso o tópico aparece como *"amostra insuficiente"* — evita rotular um
aluno com base em uma única questão.

O mesmo cálculo, agregando todos os alunos, produz o aproveitamento por assunto
da **turma**, da **disciplina** e da **avaliação**.

## 4.5 Frequência e participação

```
frequência(%)   = (presenças + atrasos×0,5) / aulas_registradas × 100
participação    = média das notas de participação (0 a 5) → normalizada 0–100
```
Faltas justificadas não reduzem a frequência, mas contam em `aulas_registradas`
apenas quando `«justificada_conta» = false` (padrão: não conta).

## 4.6 Evolução

Regressão linear simples sobre os percentuais ordenados por data:

```
n  = nº de avaliações                       (mínimo «min_avaliacoes_evolucao» = 3)
x  = índice cronológico (1..n)
b  = Σ(xᵢ−x̄)(yᵢ−ȳ) / Σ(xᵢ−x̄)²            ← pontos percentuais ganhos por avaliação
evolucao_total = b × (n − 1)                 ← ganho acumulado estimado
```

Também é calculada a **evolução recente**:

```
Δ_recente = média(últimas «janela_recente»=3) − média(anteriores)
```

Normalização para o índice (0–100):

```
score_evolucao = clamp(50 + b × «fator_evolucao»(=5), 0, 100)
```
Ou seja, aluno estável ⇒ 50; ganhando 10 p.p. por avaliação ⇒ 100; perdendo
10 p.p. ⇒ 0.

## 4.7 Consistência

```
score_consistencia = clamp(100 − desvio_padrão(percentuais) × «fator_consistencia»(=2), 0, 100)
```
Desvio de 0 p.p. ⇒ 100 (muito regular); desvio de 25 p.p. ⇒ 50; ≥50 p.p. ⇒ 0.
Com menos de 2 avaliações, usa-se 50 (neutro).

## 4.8 Índice de Desenvolvimento (ID)

```
ID = «p_desempenho»(0,40) × média_geral
   + «p_evolucao»  (0,25) × score_evolucao
   + «p_frequencia»(0,15) × frequência
   + «p_consistencia»(0,20) × score_consistencia
```

Pesos configuráveis; o sistema normaliza automaticamente caso a soma ≠ 1.

**Confiabilidade do índice**: com menos de «min_avaliacoes_indice» = 2
avaliações, o ID é exibido com a marca *"dados insuficientes"* e o aluno não
entra no ranking — evita classificar quem ainda não foi avaliado.

### Classificação do aluno

| ID | Classificação |
|---|---|
| ≥ «id_evolucao» (75) | **Em evolução / destaque** |
| «id_atencao» (55) – 74,9 | **Intermediário** |
| < «id_atencao» (55) | **Precisa de atenção** |

Um aluno também é rotulado **"precisa de atenção"**, independentemente do ID,
quando a evolução recente é ≤ −«queda_alerta»(10) p.p. ou a frequência é
< «frequencia_minima»(75)%. A justificativa aparece ao lado da classificação:
o professor sempre vê *por que* o aluno caiu naquele grupo.

## 4.9 Alertas pedagógicos

| Alerta | Condição | Severidade |
|---|---|---|
| Queda de desempenho | Δ_recente ≤ −«queda_alerta»(10) p.p. | alta |
| Frequência baixa | frequência < «frequencia_minima»(75)% | alta |
| Baixo aproveitamento | média_geral < «media_alerta»(60)% | alta |
| Dificuldade persistente | mesmo assunto < «limite_dificuldade»(60)% em ≥ «ocorrencias_persistente»(3) avaliações | alta |
| Evolução significativa | Δ_recente ≥ «evolucao_alerta»(10) p.p. | positiva |
| Turma com conteúdo crítico | aproveitamento da turma no assunto < «limite_dificuldade» com ≥5 respostas | média |
| Aluno sem avaliações | matriculado há >30 dias sem nenhum resultado | média |

Cada alerta traz texto pronto para o professor, por exemplo:
> **Atenção:** João apresentou aproveitamento inferior a 50% em *Redes de
> Computadores* nas últimas 3 avaliações.

Alertas resolvidos podem ser marcados como tratados (`alert_dismissals`) e
reaparecem se a condição voltar a ocorrer com dados novos.

## 4.10 Estrutura dos dashboards

### Dashboard geral (`/`)
**Indicadores:** total de alunos · turmas · disciplinas · aulas · avaliações ·
média geral · % médio de acertos · % médio de erros · alunos em evolução ·
intermediários · que precisam de atenção.
**Gráficos:** evolução geral (linha, média por avaliação no tempo) ·
distribuição de desempenho (barras por faixa) · média por disciplina (barras) ·
média por avaliação (linha) · desempenho por assunto (barras horizontais) ·
comparação entre turmas (barras) · frequência (barras) · evolução dos alunos
(top ganhos e perdas).
**Blocos:** alertas prioritários · ranking do Índice de Desenvolvimento ·
assuntos mais críticos.

### Dashboard individual (`/alunos/{id}`)
**Indicadores:** média geral · média por disciplina · % acertos · % erros ·
avaliações realizadas · evolução (p.p.) · frequência · conteúdos dominados ·
conteúdos com dificuldade · Índice de Desenvolvimento · posição no ranking da
turma.
**Gráficos:** evolução das notas · % acertos por avaliação · % erros por
avaliação · desempenho por disciplina · desempenho por assunto · desempenho por
dificuldade (fácil/médio/difícil) · frequência ao longo do tempo · aluno × média
da turma.
**Blocos:** assuntos dominados / intermediários / com dificuldade · alertas do
aluno · histórico de avaliações · histórico de presença · observações.

### Painel da turma (`/turmas/{id}`)
Média da turma · frequência média · alunos por classificação · evolução da turma
· média por disciplina · assuntos críticos · ranking interno · lista de alunos
com ID e situação.
