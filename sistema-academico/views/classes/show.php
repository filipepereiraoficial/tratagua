<?php
/** Painel da turma. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Turma <?= e($turma['code']) ?></h1>
    <p class="mudo mb-0">
      <?= e($turma['course_name']) ?> · <?= (int) $turma['year'] ?>
      <?= $turma['period'] ? ' · ' . e($turma['period']) : '' ?>
      · <span class="etiqueta etiqueta--info"><?= e(rotulo('status_turma', $turma['status'])) ?></span>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/relatorios', ['relatorio' => 'turma', 'turma' => $turma['id']]) ?>">Relatório</a>
    <a class="botao botao--secundario" href="<?= url('/alunos/novo', ['turma' => $turma['id']]) ?>">+ Aluno nesta turma</a>
    <a class="botao" href="<?= url('/turmas/' . $turma['id'] . '/editar') ?>">Editar turma</a>
  </div>
</div>

<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($media, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média da turma</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div>
    <div class="indicador__nota"><?= count($alunos) ?> aluno(s) vinculado(s)</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= $acertos['pct_erros'] !== null ? pct($acertos['pct_erros']) . ' de erros' : 'sem respostas' ?></div>
  </div>
  <?php $freqTurma = $frequencia[0]['frequencia'] ?? null; ?>
  <div class="indicador indicador--<?= faixa_classe($freqTurma !== null ? (float) $freqTurma : null, 90, 75) ?>">
    <div class="indicador__rotulo">Frequência média</div>
    <div class="indicador__valor"><?= $freqTurma !== null ? pct($freqTurma) : '—' ?></div>
    <div class="indicador__nota"><?= count($disciplinas) ?> disciplina(s)</div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Em evolução</div>
    <div class="indicador__valor"><?= (int) $classificacao['evolucao'] ?></div>
  </div>
  <div class="indicador indicador--medio">
    <div class="indicador__rotulo">Intermediários</div>
    <div class="indicador__valor"><?= (int) $classificacao['intermediario'] ?></div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Precisam de atenção</div>
    <div class="indicador__valor"><?= (int) $classificacao['atencao'] ?></div>
    <div class="indicador__nota"><?= (int) $classificacao['sem_dados'] ?> sem dados</div>
  </div>
</div>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Evolução da turma</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-evolucao"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Distribuição de desempenho</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-distribuicao"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Média por disciplina</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-disciplinas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Assuntos críticos</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="g-assuntos"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <!-- Ranking interno -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Alunos e Índice de Desenvolvimento</h2>
        <input type="search" placeholder="Filtrar aluno…" data-filtra-tabela="#tabela-ranking" style="width:180px;margin-left:auto">
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($ranking === []): ?>
          <div class="vazio"><span class="vazio__icone">👥</span>Nenhum aluno vinculado a esta turma.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela" id="tabela-ranking">
              <thead><tr><th>#</th><th>Aluno</th><th class="num">Aval.</th><th class="num">Média</th><th class="num">Freq.</th><th class="num">Evolução</th><th class="num">Índice</th><th>Situação</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($ranking as $linha): ?>
                <tr>
                  <td class="num"><?= $linha['posicao'] ?? '—' ?></td>
                  <td><a href="<?= url('/alunos/' . $linha['id']) ?>"><?= e($linha['full_name']) ?></a></td>
                  <td class="num"><?= (int) $linha['avaliacoes'] ?></td>
                  <td class="num"><?= $linha['media'] !== null ? pct($linha['media'], 0) : '—' ?></td>
                  <td class="num"><?= $linha['frequencia'] !== null ? pct($linha['frequencia'], 0) : '—' ?></td>
                  <td class="num">
                    <?php if ($linha['evolucao_recente'] !== null): ?>
                      <span class="etiqueta etiqueta--<?= $linha['evolucao_recente'] >= 0 ? 'bom' : 'ruim' ?>">
                        <?= $linha['evolucao_recente'] >= 0 ? '+' : '' ?><?= num($linha['evolucao_recente']) ?>
                      </span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td class="num"><strong><?= $linha['indice'] !== null ? num($linha['indice']) : '—' ?></strong></td>
                  <td>
                    <?php $classe = match ($linha['classificacao']) {
                        'evolucao' => 'bom', 'intermediario' => 'medio', 'atencao' => 'ruim', default => 'neutro',
                    }; ?>
                    <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('classificacao', $linha['classificacao'])) ?></span>
                  </td>
                  <td class="direita">
                    <form method="post" action="<?= url('/turmas/' . $turma['id'] . '/alunos/remover') ?>"
                          data-confirmar="Remover este aluno da turma? O histórico será preservado." style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="student_id" value="<?= (int) $linha['id'] ?>">
                      <button class="botao botao--secundario botao--pequeno" type="submit" title="Remover da turma">✕</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Assuntos -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Aproveitamento por assunto</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($assuntos === []): ?>
          <div class="vazio"><span class="vazio__icone">📚</span>Registre resultados por questão para mapear os assuntos.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Assunto</th><th>Disciplina</th><th class="num">Alunos</th><th class="num">Respostas</th><th>Aproveitamento</th></tr></thead>
              <tbody>
              <?php foreach ($assuntos as $assunto): ?>
                <tr>
                  <td><?= e($assunto['topic_name']) ?></td>
                  <td class="pequeno mudo"><?= e($assunto['subject_name']) ?></td>
                  <td class="num"><?= (int) $assunto['alunos'] ?></td>
                  <td class="num"><?= (int) $assunto['respondidas'] ?></td>
                  <td style="min-width:160px">
                    <span class="progresso-linha">
                      <span class="progresso"><span class="progresso__barra progresso__barra--<?= faixa_classe($assunto['aproveitamento'], $faixas['dominio'], $faixas['intermediario']) ?>" style="width:<?= (float) ($assunto['aproveitamento'] ?? 0) ?>%"></span></span>
                      <span><?= $assunto['aproveitamento'] !== null ? pct($assunto['aproveitamento'], 0) : '—' ?></span>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <!-- Disciplinas -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Disciplinas da turma</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($disciplinas === []): ?>
          <div class="vazio pequeno">Nenhuma disciplina vinculada.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($disciplinas as $disciplina): ?>
              <li>
                <span class="nome">
                  <a href="<?= url('/disciplinas/' . $disciplina['id']) ?>"><?= e($disciplina['name']) ?></a><br>
                  <small class="mudo">
                    <?= (int) $disciplina['lessons_count'] ?> aula(s) · <?= (int) $disciplina['assessments_count'] ?> avaliação(ões)
                    <?= $disciplina['teacher_name'] ? ' · ' . e($disciplina['teacher_name']) : '' ?>
                  </small>
                </span>
                <form method="post" action="<?= url('/turmas/' . $turma['id'] . '/disciplinas/remover') ?>"
                      data-confirmar="Desvincular esta disciplina da turma?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="class_subject_id" value="<?= (int) $disciplina['class_subject_id'] ?>">
                  <button class="botao botao--secundario botao--pequeno" type="submit">✕</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="cartao__corpo" style="border-top:1px solid var(--cinza-200)">
        <form method="post" action="<?= url('/turmas/' . $turma['id'] . '/disciplinas') ?>">
          <?= csrf_field() ?>
          <div class="campo mb-2">
            <label for="subject_id">Vincular disciplina</label>
            <select id="subject_id" name="subject_id" required>
              <option value="">Selecione…</option>
              <?php foreach ($disponiveis as $opcao): ?>
                <option value="<?= (int) $opcao['id'] ?>"><?= e($opcao['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo mb-2">
            <label for="teacher_user_id">Professor responsável</label>
            <select id="teacher_user_id" name="teacher_user_id">
              <option value="">— Usar o padrão da disciplina —</option>
              <?php foreach ($professores as $professor): ?>
                <option value="<?= (int) $professor['id'] ?>"><?= e($professor['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="botao botao--bloco botao--pequeno" type="submit">Vincular disciplina</button>
        </form>
      </div>
    </div>

    <!-- Vincular aluno -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Vincular aluno existente</h2></div>
      <div class="cartao__corpo">
        <?php if ($sem_turma === []): ?>
          <p class="pequeno mudo mb-0">Todos os alunos cadastrados já estão vinculados a alguma turma.</p>
        <?php else: ?>
          <form method="post" action="<?= url('/turmas/' . $turma['id'] . '/alunos') ?>">
            <?= csrf_field() ?>
            <div class="campo mb-2">
              <label for="student_id">Aluno sem turma</label>
              <select id="student_id" name="student_id" required>
                <option value="">Selecione…</option>
                <?php foreach ($sem_turma as $aluno): ?>
                  <option value="<?= (int) $aluno['id'] ?>"><?= e($aluno['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="botao botao--bloco botao--pequeno" type="submit">Vincular aluno</button>
          </form>
        <?php endif; ?>
        <a class="botao botao--secundario botao--bloco botao--pequeno mt-2" href="<?= url('/alunos/novo', ['turma' => $turma['id']]) ?>">+ Cadastrar novo aluno</a>
      </div>
    </div>

    <!-- Alertas -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Alertas da turma</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($alertas === []): ?>
          <div class="vazio pequeno">Nenhum alerta ativo.</div>
        <?php else: foreach (array_slice($alertas, 0, 10) as $alerta): ?>
          <div class="alerta alerta--<?= e($alerta['severity']) ?>">
            <div class="alerta__marca"></div>
            <div class="alerta__corpo">
              <div class="alerta__titulo"><?= e($alerta['title']) ?></div>
              <div class="alerta__texto pequeno"><?= e($alerta['message']) ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Excluir turma -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Excluir turma</h2></div>
      <div class="cartao__corpo">
        <?php if ($blockers !== []): ?>
          <p class="pequeno mudo mb-0">Não é possível excluir: <?= e(implode(', ', $blockers)) ?>.</p>
        <?php else: ?>
          <form method="post" action="<?= url('/turmas/' . $turma['id'] . '/excluir') ?>" data-confirmar="Excluir esta turma?">
            <?= csrf_field() ?>
            <button class="botao botao--perigo botao--pequeno" type="submit">Excluir turma</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  Painel.linha('g-evolucao',
    <?= $j(array_map(static fn ($r) => $r['assessment_name'], $serie)) ?>,
    [{ nome: 'Média da turma (%)', dados: <?= $j(array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $serie)) ?> }],
    { scales: { y: { max: 100 } } });

  Painel.rosca('g-distribuicao', <?= $j(array_keys($distribuicao)) ?>, <?= $j(array_values($distribuicao)) ?>,
    [Painel.paleta.vermelho, Painel.paleta.ambar, Painel.paleta.azul, Painel.paleta.verde]);

  var disc = <?= $j(array_map(static fn ($r) => ['nome' => $r['subject_name'], 'valor' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_disciplina)) ?>;
  Painel.barras('g-disciplinas', disc.map(function (d) { return d.nome; }),
    [{ nome: 'Média (%)', dados: disc.map(function (d) { return d.valor; }),
       cores: disc.map(function (d) { return Painel.corPorFaixa(d.valor, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var ass = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], array_slice($assuntos, 0, 15))) ?>;
  Painel.barras('g-assuntos', ass.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.valor; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });
});
</script>
