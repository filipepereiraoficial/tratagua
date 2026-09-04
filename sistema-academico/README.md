# Painel Pedagógico — acompanhamento do desenvolvimento acadêmico

Sistema web para o professor acompanhar a evolução de cada aluno e o desempenho
de turmas, disciplinas e avaliações em um curso preparatório. Não é apenas um
cadastro de notas: os dados lançados alimentam indicadores, gráficos,
classificações e alertas pedagógicos com regras de cálculo explícitas.

Aplicação PHP independente, hospedada ao lado do site institucional em
`/sistema-academico`. Não depende do WordPress.

---

## Sumário da documentação

| Documento | Conteúdo |
|---|---|
| [docs/01-ARQUITETURA.md](docs/01-ARQUITETURA.md) | Arquitetura, camadas, diretórios, segurança e escalabilidade |
| [docs/02-MODELO-DE-DADOS.md](docs/02-MODELO-DE-DADOS.md) | Entidades, relacionamentos, tabelas e regras de integridade |
| [docs/03-TELAS-E-NAVEGACAO.md](docs/03-TELAS-E-NAVEGACAO.md) | Mapa de telas, layout e fluxo de navegação |
| [docs/04-REGRAS-DE-CALCULO.md](docs/04-REGRAS-DE-CALCULO.md) | Fórmulas dos indicadores, Índice de Desenvolvimento, alertas e dashboards |

---

## Instalação

### Requisitos
PHP 8.1 ou superior com as extensões `pdo`, `mbstring` e `json`, mais
`pdo_mysql` (produção) ou `pdo_sqlite` (uso imediato, sem configurar nada).

### Opção A — instalador web (recomendado)

1. Envie a pasta `sistema-academico/` para o servidor.
2. Dê permissão de escrita à pasta `storage/` (`chmod 775 storage`).
3. Acesse `https://seu-dominio/tratagua/sistema-academico/`.
   O sistema detecta que o banco ainda não existe e abre o instalador.
4. Confirme os dados do administrador e clique em **Instalar sistema**.

O instalador cria as tabelas, a conta de administrador e a carga inicial
(curso, disciplina, turma), e depois fica indisponível.

### Opção B — linha de comando

```bash
cd sistema-academico
php database/migrate.php --seed              # schema + carga inicial
php database/migrate.php --seed --demo       # + dados de demonstração
php database/migrate.php --fresh --seed      # recria do zero
```

### Banco MySQL em produção

```bash
cp config/config.local.example.php config/config.local.php
# edite host, database, username e password
php database/migrate.php --seed
```

`config/config.local.php` sobrescreve apenas o que você declarar e fica fora do
versionamento.

---

## Acesso inicial

| Campo | Valor |
|---|---|
| Nome | Filipe Pereira |
| E-mail | manowfilipe@gmail.com |
| Senha | `Fp$$1999` |
| Perfil | Administrador / Professor |

**Altere a senha após o primeiro acesso** em *Alterar senha* (menu lateral) ou
em *Configurações → Usuários*.

## Dados iniciais criados

- **Curso:** Preparatório para Guarda Municipal
- **Disciplina:** Informática — já com 6 assuntos e 18 tópicos
- **Turma:** INF01 · 2026 · Noturno
- A disciplina Informática já vem vinculada à turma INF01

O sistema inicia pronto para cadastrar alunos e registrar aulas e avaliações.

---

## Fluxo de uso

```
Curso → Turma → Disciplina → Vincular disciplina à turma → Alunos → Vincular à turma
      → Aulas + chamada → Avaliação → Questões (assunto/dificuldade) → Resultados
      → Cálculo automático → Dashboards, alertas e relatórios
```

Duas formas de lançar resultados, ambas alimentando os mesmos indicadores:

- **Por questão** (preferencial): marca-se `C` / `E` / `N` por item. Só assim o
  sistema consegue analisar por assunto e por nível de dificuldade.
- **Nota direta**: digita-se apenas a nota. Entra em médias, evolução e
  frequência, mas não na análise por conteúdo.

---

## Módulos

| Módulo | O que faz |
|---|---|
| **Dashboard** | 11 indicadores, 8 gráficos, alertas prioritários, ranking e conteúdos críticos |
| **Alunos** | Cadastro completo, vínculo/troca de turma com histórico, **dashboard individual de aprendizagem** |
| **Turmas** | Cursos, turmas, vínculo de alunos e disciplinas, painel de desempenho da turma |
| **Disciplinas** | Cadastro, árvore de assuntos e tópicos, desempenho por conteúdo e por turma |
| **Aulas** | Registro de conteúdo e tópicos, chamada com presença e participação |
| **Avaliações** | 6 tipos, questões em lote, lançamento por questão ou nota direta, análise item a item |
| **Questões** | Banco reaproveitável com índice de acerto real por questão |
| **Relatórios** | 8 relatórios filtráveis, exportação CSV e versão para impressão/PDF |
| **Gráficos** | Painel de gráficos + comparações (aluno×aluno, aluno×turma, turma×turma, disciplina×disciplina, avaliação×avaliação) |
| **Configurações** | Faixas de classificação, pesos do Índice de Desenvolvimento, limites dos alertas e usuários |

---

## Perguntas que o sistema responde

Qual aluno mais evoluiu · quem está com pior desempenho · quem está acima da
média da turma · quais alunos precisam de atenção · qual disciplina tem menor
desempenho · quais assuntos os alunos mais erram e mais acertam · se o
desempenho da turma está melhorando · qual avaliação foi mais difícil · qual
conteúdo precisa ser revisado.

---

## Como os números são calculados

Resumo — o detalhamento está em [docs/04](docs/04-REGRAS-DE-CALCULO.md).

- **Aproveitamento por assunto** = pontos obtidos ÷ pontos possíveis nas questões
  daquele assunto. Classificado em **domínio** (≥80%), **intermediário**
  (60–79%) e **dificuldade** (<60%) — faixas configuráveis, com exigência de
  amostra mínima de questões.
- **Índice de Desenvolvimento** = 40% desempenho + 25% evolução + 15% frequência
  + 20% consistência (pesos configuráveis, normalizados automaticamente).
  A classificação **não** olha só a maior nota: um aluno com média alta e
  evolução nula fica atrás de outro que vem crescendo.
- **Evolução** = coeficiente da reta de tendência sobre os percentuais em ordem
  cronológica, mais a diferença entre as avaliações recentes e as anteriores.
- **Consistência** = 100 − desvio-padrão dos percentuais × fator.
- **Frequência** = (presenças + atrasos×0,5) ÷ aulas, com faltas justificadas
  fora do denominador por padrão.

Todo indicador exibido tem origem em dados registrados; quando a amostra é
insuficiente, o sistema informa isso em vez de estimar.

---

## Segurança

Senhas com `password_hash`, sessão endurecida (`HttpOnly`, `SameSite`, `Secure`
em HTTPS, expiração por inatividade, regeneração de id no login), token CSRF em
todo POST, bloqueio progressivo após tentativas de login malsucedidas,
autorização por perfil em cada rota, 100% dos acessos ao banco via prepared
statements, escape obrigatório na saída e diretórios internos negados por
`.htaccess`.

## Perfil Aluno

A estrutura já contempla o perfil `aluno` (`users.role` + `users.student_id`) e
os serviços analíticos já recebem o aluno como parâmetro — habilitar o painel do
aluno é uma questão de expor as rotas, sem mudanças no modelo de dados.
