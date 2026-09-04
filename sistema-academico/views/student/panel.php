<?php
/** Painel do aluno — a própria evolução, sem o ranking nominal da turma. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$labels = array_map(static fn ($n) => $n['assessment_name'], $resumo['notas']);
$mapaTurma = [];
foreach ($serie_turma as $item) { $mapaTurma[(int) $item['assessment_id']] = $item['media'] === null ? null : round((float) $item['media'], 2); }
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Minha evolução</h1>
    <p class="mudo mb-0">
      <?= e($aluno['full_name']) ?>
      <?= $aluno['class_code'] ? ' · turma ' . e($aluno['class_code']) . ' · ' . e($aluno['course_name']) : '' ?>
    </p>
  </div>
</div>

<div class="indicadores">
  <?php $delta = ($resumo['media'] !== null && $media_turma !== null) ? $resumo['media'] - $media_turma : null; ?>
  <div class="indicador indicador--<?= faixa_classe($resumo['media'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Minha média</div>
    <div class="indicador__valor"><?= pct($resumo['media']) ?></div>
    <div class="indicador__nota"><?= $delta === null ? '' :
      ($delta >= 0 ? 'acima' : 'abaixo') . ' da média da turma em ' . num(abs($delta)) . ' p.p.' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['acertos']['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Acertos</div>
    <div class="indicador__valor"><?= pct($resumo['acertos']['pct_acertos']) ?></div>
    <div class="indicador__nota"><?= (int) $resumo['acertos']['acertos'] ?> de <?= (int) $resumo['acertos']['total'] ?> questões</div>
  </div>
  <div class="indicador indicador--<?= $resumo['evolucao_recente'] === null ? 'neutro' : ($resumo['evolucao_recente'] >= 0 ? 'bom' : 'ruim') ?>">
    <div class="indicador__rotulo">Minha evolução</div>
    <div class="indicador__valor"><?= $resumo['evolucao_recente'] === null ? '—' : ($resumo['evolucao_recente'] >= 0 ? '+' : '') . num($resumo['evolucao_recente']) ?></div>
    <div class="indicador__nota">pontos percentuais nas últimas avaliações</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['frequencia'], 90, 75) ?>">
    <div class="indicador__rotulo">Frequência</div>
    <div class="indicador__valor"><?= pct($resumo['frequencia']) ?></div>
    <div class="indicador__nota"><?= (int) $resumo['presenca']['faltas'] ?> falta(s) em <?= (int) $resumo['presenca']['aulas'] ?> aula(s)</div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Conteúdos que domino</div>
    <div class="indicador__valor"><?= (int) $resumo['dominados'] ?></div>
    <div class="indicador__nota"><?= (int) $resumo['intermediarios'] ?> em construção</div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Para reforçar</div>
    <div class="indicador__valor"><?= (int) $resumo['dificuldades'] ?></div>
    <div class="indicador__nota">abaixo de <?= num($faixas['intermediario'], 0) ?>% de aproveitamento</div>
  </div>
</div>

<div class="colunas colunas--2">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Minhas notas ao longo do tempo</h2><span class="etiqueta etiqueta--info">eu × turma</span></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="me-ev"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Meu desempenho por assunto</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="me-as"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>O que reforçar</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php
      $dificeis = array_values(array_filter($resumo['assuntos'], static fn ($a) => $a['classificacao'] === 'dificuldade'));
      $bons = array_values(array_filter($resumo['assuntos'], static fn ($a) => $a['classificacao'] === 'dominio'));
      ?>
      <?php if ($dificeis === []): ?>
        <div class="vazio pequeno">Nenhum conteúdo abaixo da faixa de dificuldade. Bom trabalho.</div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach ($dificeis as $a): ?>
            <li>
              <span class="nome"><?= e($a['topic_name']) ?><br><small class="mudo"><?= e($a['subject_name']) ?></small></span>
              <span class="progresso-linha" style="flex:0 0 120px">
                <span class="progresso"><span class="progresso__barra progresso__barra--ruim" style="width:<?= (float) ($a['aproveitamento'] ?? 0) ?>%"></span></span>
                <span><?= pct($a['aproveitamento'], 0) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>O que já domino</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($bons === []): ?>
        <div class="vazio pequeno">Ainda não há conteúdo na faixa de domínio.</div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach ($bons as $a): ?>
            <li>
              <span class="nome"><?= e($a['topic_name']) ?><br><small class="mudo"><?= e($a['subject_name']) ?></small></span>
              <span class="progresso-linha" style="flex:0 0 120px">
                <span class="progresso"><span class="progresso__barra progresso__barra--bom" style="width:<?= (float) ($a['aproveitamento'] ?? 0) ?>%"></span></span>
                <span><?= pct($a['aproveitamento'], 0) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="cartao">
  <div class="cartao__cabecalho"><h2>Minhas avaliações</h2></div>
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($resumo['notas'] === []): ?>
      <div class="vazio pequeno">Nenhuma avaliação lançada ainda.</div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead><tr><th>Data</th><th>Avaliação</th><th>Disciplina</th><th class="num">Nota</th><th class="num">Aproveitamento</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($resumo['notas']) as $nota): ?>
            <tr>
              <td class="mono nowrap"><?= data_br($nota['assessment_date']) ?></td>
              <td><?= e($nota['assessment_name']) ?></td>
              <td class="mudo pequeno"><?= e($nota['subject_name']) ?></td>
              <td class="num mono"><?= num($nota['score'], 2) ?> / <?= num($nota['max_score'], 0) ?></td>
              <td class="num"><span class="etiqueta etiqueta--<?= faixa_classe((float) $nota['percentage'], $faixas['dominio'], $faixas['intermediario']) ?>"><?= pct($nota['percentage'], 0) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;
  Painel.linha('me-ev', <?= $j($labels) ?>, [
    { nome: 'Eu', dados: <?= $j($resumo['percentuais']) ?> },
    { nome: 'Média da turma', dados: <?= $j(array_map(static fn ($n) => $mapaTurma[(int) $n['assessment_id']] ?? null, $resumo['notas'])) ?>, cor: Painel.paleta.cinza }
  ], { scales: { y: { max: 100 } } });

  var ass = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], $resumo['assuntos'])) ?>;
  Painel.barras('me-as', ass.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.valor; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });
});
</script>
