<?php
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= e($avaliacao['name']) ?></h1>
    <p class="mudo mb-0">
      <?= e(rotulo('tipo_avaliacao', $avaliacao['type'])) ?> · <?= data_br($avaliacao['assessment_date']) ?>
      · <?= e($avaliacao['class_code']) ?> — <?= e($avaliacao['subject_name']) ?>
      · valor <?= num($avaliacao['max_score'], 2) ?> · peso <?= num($avaliacao['weight'], 1) ?>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/exportar') ?>">Exportar CSV</a>
    <a class="botao botao--secundario" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/editar') ?>">Editar</a>
    <a class="botao" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/resultados') ?>">Lançar resultados</a>
  </div>
</div>

<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($media, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média da avaliação</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div>
    <div class="indicador__nota"><?= count($notas) ?> de <?= count($alunos) ?> aluno(s) com resultado</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Menor / Maior</div>
    <div class="indicador__valor" style="font-size:1.3rem">
      <?= $minima !== null ? pct($minima, 0) : '—' ?> / <?= $maxima !== null ? pct($maxima, 0) : '—' ?>
    </div>
    <div class="indicador__nota">Desvio: <?= $desvio !== null ? num($desvio) . ' p.p.' : '—' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= $acertos['pct_branco'] !== null ? pct($acertos['pct_branco']) . ' em branco' : '' ?></div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Questões</div>
    <div class="indicador__valor"><?= count($questoes) ?></div>
    <div class="indicador__nota">somando <?= num($pontos_questoes, 2) ?> pontos</div>
  </div>
</div>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Índice de acerto por questão</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-questoes"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Aproveitamento por assunto</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-assuntos"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Aproveitamento por dificuldade</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-dificuldade"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Detalhe das questões</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($questoes === []): ?>
        <div class="vazio pequeno">Nenhuma questão cadastrada.</div>
      <?php else: ?>
        <div class="tabela-rolagem">
          <table class="tabela tabela--compacta">
            <thead><tr><th>#</th><th>Assunto</th><th>Dificuldade</th><th class="num">C / E / B</th><th>Índice de acerto</th></tr></thead>
            <tbody>
            <?php foreach ($questoes as $questao): ?>
              <tr>
                <td class="num"><?= $questao['number'] !== null ? (int) $questao['number'] : '·' ?></td>
                <td class="pequeno"><?= $questao['topic_name'] ? e($questao['topic_name']) : '<span class="mudo">sem assunto</span>' ?></td>
                <td>
                  <?php $classe = match ($questao['difficulty']) { 'facil' => 'bom', 'medio' => 'medio', default => 'ruim' }; ?>
                  <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('dificuldade', $questao['difficulty'])) ?></span>
                </td>
                <td class="num pequeno"><?= (int) $questao['correct'] ?> / <?= (int) $questao['wrong'] ?> / <?= (int) $questao['blank'] ?></td>
                <td style="min-width:140px">
                  <span class="progresso-linha">
                    <span class="progresso"><span class="progresso__barra progresso__barra--<?= faixa_classe($questao['indice_acerto'], $faixas['dominio'], $faixas['intermediario']) ?>" style="width:<?= (float) ($questao['indice_acerto'] ?? 0) ?>%"></span></span>
                    <span><?= $questao['indice_acerto'] !== null ? pct($questao['indice_acerto'], 0) : '—' ?></span>
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

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Resultados por aluno</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($alunos === []): ?>
        <div class="vazio pequeno">Nenhum aluno na turma.</div>
      <?php else: ?>
        <div class="tabela-rolagem">
          <table class="tabela tabela--compacta">
            <thead><tr><th>Aluno</th><th class="num">Nota</th><th class="num">%</th><th class="num">C/E/B</th></tr></thead>
            <tbody>
            <?php
            $ordenados = $alunos;
            usort($ordenados, static function ($a, $b) use ($notas) {
                $x = $notas[(int) $a['id']]['percentage'] ?? -1;
                $y = $notas[(int) $b['id']]['percentage'] ?? -1;
                return $y <=> $x;
            });
            foreach ($ordenados as $aluno):
                $nota = $notas[(int) $aluno['id']] ?? null;
            ?>
              <tr>
                <td><a href="<?= url('/alunos/' . $aluno['id']) ?>"><?= e($aluno['full_name']) ?></a></td>
                <td class="num"><?= $nota ? num($nota['score'], 2) : '—' ?></td>
                <td class="num">
                  <?php if ($nota): ?>
                    <span class="etiqueta etiqueta--<?= faixa_classe((float) $nota['percentage'], $faixas['dominio'], $faixas['intermediario']) ?>"><?= pct($nota['percentage'], 0) ?></span>
                  <?php else: ?><span class="mudo">—</span><?php endif; ?>
                </td>
                <td class="num pequeno"><?= $nota ? (int) $nota['correct_count'] . '/' . (int) $nota['wrong_count'] . '/' . (int) $nota['blank_count'] : '—' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($avaliacao['description']): ?>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Conteúdo abordado</h2></div>
    <div class="cartao__corpo"><p class="mb-0" style="white-space:pre-line"><?= e($avaliacao['description']) ?></p></div>
  </div>
<?php endif; ?>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  var q = <?= $j(array_map(static fn ($x) => ['nome' => 'Q' . ($x['number'] ?? $x['id']), 'valor' => $x['indice_acerto']], $questoes)) ?>;
  Painel.barras('g-questoes', q.map(function (i) { return i.nome; }),
    [{ nome: 'Índice de acerto (%)', dados: q.map(function (i) { return i.valor; }),
       cores: q.map(function (i) { return Painel.corPorFaixa(i.valor, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var a = <?= $j(array_map(static fn ($x) => ['nome' => $x['topic_name'], 'valor' => $x['aproveitamento']], $assuntos)) ?>;
  Painel.barras('g-assuntos', a.map(function (i) { return i.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: a.map(function (i) { return i.valor; }),
       cores: a.map(function (i) { return Painel.corPorFaixa(i.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var d = <?= $j(array_map(static fn ($x) => ['nome' => rotulo('dificuldade', $x['dificuldade']), 'valor' => $x['aproveitamento']], $dificuldade)) ?>;
  Painel.barras('g-dificuldade', d.map(function (i) { return i.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: d.map(function (i) { return i.valor; }),
       cores: [Painel.paleta.verde, Painel.paleta.ambar, Painel.paleta.vermelho] }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });
});
</script>
