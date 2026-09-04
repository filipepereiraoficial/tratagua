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
| [docs/05-PERFIS-E-PAINEIS.md](docs/05-PERFIS-E-PAINEIS.md) | Perfis, escopo de acesso, painel do professor, do administrador e do aluno |

## Experimentar sem instalar

Demonstração navegável, com a turma de exemplo e os mesmos cálculos do servidor
rodando no navegador:

**https://claude.ai/code/artifact/39660770-d62f-4923-9bd8-199264637663**

Dá para percorrer as dez telas, abrir o dashboard de um aluno, lançar resultados
por questão (`C` / `E` / `N`) e ver nota, ranking e alertas serem recalculados na
hora, e mudar as faixas e os pesos em Configurações para reclassificar a turma.
Ela não grava dados nem exporta arquivos — isso é do sistema instalado.
Detalhes e como reconstruí-la em [demo/README.md](demo/README.md).

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
php database/migrate.php --migrate           # atualiza instalação existente
```

### Atualizando uma instalação que já está no ar

Basta subir os arquivos: a aplicação detecta migrações pendentes no boot e as
aplica uma única vez, preservando os dados. Para fazer isso pela linha de
comando, use `php database/migrate.php --migrate`.

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

Com `--demo`, um professor de exemplo também é criado
(`professor@exemplo.com` / `Professor@2026`), já responsável pela turma INF01 —
serve para conferir o painel do professor e o recorte por responsabilidade.

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

## Perfis de acesso

| Perfil | Enxerga | Entra em |
|---|---|---|
| **Administrador** | a instituição inteira | `/painel-admin` |
| **Professor** | apenas as turmas × disciplinas sob sua responsabilidade | `/meu-painel` |
| **Aluno** | apenas a si mesmo | `/minha-evolucao` |

O professor é vinculado a uma **oferta** — o par turma + disciplina — e é esse
vínculo que recorta tudo: alunos, aulas, avaliações, questões, relatórios,
gráficos e alertas. O recorte vale nas listagens **e** no acesso direto por URL.
Detalhes em [docs/05](docs/05-PERFIS-E-PAINEIS.md).

## Módulos

| Módulo | O que faz |
|---|---|
| **Dashboard** | 11 indicadores, 8 gráficos, alertas prioritários, ranking e conteúdos críticos |
| **Painel do administrador** | Curso × curso, turma × turma, professor × professor e as pendências que travam a análise |
| **Painel do professor** | Quem mais evoluiu, quem precisa de atenção e quem mais deixou pontuação — só nas turmas dele |
| **Alunos** | Cadastro completo, vínculo/troca de turma com histórico, **dashboard individual de aprendizagem** |
| **Professores** | Cadastro com formação e contato, vínculo a turma + disciplina, carga de trabalho e redefinição de senha |
| **Turmas** | Cursos, turmas, vínculo de alunos e disciplinas, painel de desempenho da turma |
| **Disciplinas** | Cadastro, árvore de assuntos e tópicos, desempenho por conteúdo e por turma |
| **Aulas** | Registro de conteúdo e tópicos, chamada com presença e participação |
| **Avaliações** | 6 tipos, questões em lote, lançamento por questão ou nota direta, análise item a item |
| **Questões** | Banco reaproveitável com índice de acerto real por questão |
| **Acompanhamento pedagógico** | Registra o que foi feito com o aluno sinalizado e **mede o efeito** contra a linha de base |
| **Relatórios** | 8 relatórios filtráveis, exportação CSV e versão para impressão/PDF |
| **Gráficos** | Painel de gráficos + comparações (aluno×aluno, aluno×turma, turma×turma, disciplina×disciplina, avaliação×avaliação) |
| **Auditoria** | Quem alterou o quê, com filtros por usuário, entidade, ação e período |
| **Configurações** | Faixas de classificação, pesos do Índice de Desenvolvimento, limites dos alertas e usuários |
| **Minha evolução** | Painel do aluno: o próprio desempenho, sem ver os colegas |

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

## Fechando o ciclo

O sistema não para em apontar o problema:

```
alerta aponta o aluno → professor registra o acompanhamento
   → o sistema congela média e frequência daquele momento
   → ao reabrir, compara com os valores atuais
   → "efeito na média: +7,4 p.p. (de 49,0% para 56,4%)"
```

Sem linha de base, o efeito aparece como *"sem linha de base"* — o sistema não
inventa um "antes" que não foi medido.
