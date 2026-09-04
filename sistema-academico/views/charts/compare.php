<?php
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$modos = [
    'aluno_aluno'           => ['Aluno × Aluno',            'alunos',      'alunos'],
    'aluno_turma'           => ['Aluno × Média da turma',   'alunos',      'turmas'],
    'aluno_disciplina'      => ['Aluno × Média da disciplina', 'alunos',   'disciplinas'],
    'turma_turma'           => ['Turma × Turma',            'turmas',      'turmas'],
    'disciplina_disciplina' => ['Disciplina × Disciplina',  'disciplinas', 'disciplinas'],
    'avaliacao_avaliacao'   => ['Avaliação × Avaliação',    'avaliacoes',  'avaliacoes'],
];
[$rotuloModo, $fonteA, $fonteB] = $modos[$modo] ?? $modos['aluno_aluno'];

/** Monta as <option> de cada fonte. */
$opcoes = static function (string $fonte) use ($alunos, $turmas, $disciplinas, $avaliacoes): array {
    return match ($fonte) {
        'alunos'      => array_map(static fn ($x) => ['id' => $x['id'], 'label' => $x['full_name']], $alunos),
        'turmas'      => array_map(static fn ($x) => ['id' => $x['id'], 'label' => $x['code'] . ' (' . $x['year'] . ')'], $turmas),
        'disciplinas' => array_map(static fn ($x) => ['id' => $x['id'], 'label' => $x['name']], $disciplinas),
        default       => array_map(static fn ($x) => ['id' => $x['id'], 'label' => $x['name'] . ' — ' . $x['class_code'] . ' (' . data_br($x['assessment_date']) . ')'], $avaliacoes),
    };
};
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Comparação de desempenho</h1>
    <p class="mudo mb-0"><?= e($rotuloModo) ?></p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/graficos') ?>">Gráficos gerais</a></div>
</div>

<div class="abas">
  <?php foreach ($modos as $chave => [$texto]): ?>
    <a class="<?= $modo === $chave ? 'ativo' : '' ?>" href="<?= url('/comparacao', ['modo' => $chave]) ?>"><?= e($texto) ?></a>
  <?php endforeach; ?>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/comparacao') ?>">
      <input type="hidden" name="modo" value="<?= e($modo) ?>">
      <div class="campo">
        <label for="a">Primeiro item</label>
        <select id="a" name="a" required>
          <option value="">Selecione…</option>
          <?php foreach ($opcoes($fonteA) as $opcao): ?>
            <option value="<?= (int) $opcao['id'] ?>" <?= $a === (int) $opcao['id'] ? 'selected' : '' ?>><?= e($opcao['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="b">Segundo item</label>
        <select id="b" name="b">
          <option value=""><?= $modo === 'aluno_turma' ? 'Turma atual do aluno' : 'Selecione…' ?></option>
          <?php foreach ($opcoes($fonteB) as $opcao): ?>
            <option value="<?= (int) $opcao['id'] ?>" <?= $b === (int) $opcao['id'] ? 'selected' : '' ?>><?= e($opcao['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="disciplina">Disciplina (recorte)</label>
        <select id="disciplina" name="disciplina">
          <option value="">Todas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>><?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="inicio">De</label>
        <input type="date" id="inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="fim">Até</label>
        <input type="date" id="fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>">
      </div>
      <div class="campo acoes"><button class="botao" type="submit">Comparar</button></div>
    </form>
  </div>
</div>

<?php if (!empty($resultado['aviso'])): ?>
  <div class="aviso aviso--info"><span>ℹ</span><div><?= e($resultado['aviso']) ?></div></div>
<?php else: ?>

  <div class="indicadores">
    <?php foreach ($resultado['series'] as $serie): ?>
      <div class="indicador indicador--<?= faixa_classe($serie['media']) ?>">
        <div class="indicador__rotulo"><?= e($serie['nome']) ?></div>
        <div class="indicador__valor"><?= $serie['media'] !== null ? pct($serie['media']) : '—' ?></div>
        <div class="indicador__nota">
          <?php foreach ($serie['extra'] as $chave => $valorExtra): ?>
            <?= e($chave) ?>: <strong><?= e((string) $valorExtra) ?></strong><br>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php
    $a1 = $resultado['series'][0]['media'] ?? null;
    $b1 = $resultado['series'][1]['media'] ?? null;
    if ($a1 !== null && $b1 !== null):
        $delta = $a1 - $b1;
    ?>
      <div class="indicador indicador--<?= $delta >= 0 ? 'bom' : 'ruim' ?>">
        <div class="indicador__rotulo">Diferença</div>
        <div class="indicador__valor"><?= $delta >= 0 ? '+' : '' ?><?= num($delta) ?> p.p.</div>
        <div class="indicador__nota">
          <?= e($resultado['series'][0]['nome']) ?> está
          <?= $delta >= 0 ? '<strong>acima</strong>' : '<strong>abaixo</strong>' ?>
          de <?= e($resultado['series'][1]['nome']) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Comparativo</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="c-comparacao"></canvas></div></div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Valores</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead>
            <tr>
              <th>Ponto</th>
              <?php foreach ($resultado['series'] as $serie): ?><th class="num"><?= e($serie['nome']) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php
          $todosLabels = [];
          foreach ($resultado['series'] as $serie) {
              foreach ($serie['labels'] as $label) {
                  if (!in_array($label, $todosLabels, true)) { $todosLabels[] = $label; }
              }
          }
          foreach ($todosLabels as $indice => $label):
          ?>
            <tr>
              <td><?= e($label) ?></td>
              <?php foreach ($resultado['series'] as $serie):
                  $posicao = array_search($label, $serie['labels'], true);
                  $valor = $posicao === false ? null : ($serie['dados'][$posicao] ?? null);
              ?>
                <td class="num">
                  <?= $valor === null ? '<span class="mudo">—</span>'
                      : '<span class="etiqueta etiqueta--' . faixa_classe((float) $valor) . '">' . pct($valor, 1) . '</span>' ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  window.addEventListener('load', function () {
    var series = <?= $j(array_map(static fn ($s) => ['nome' => $s['nome'], 'labels' => $s['labels'], 'dados' => $s['dados']], $resultado['series'])) ?>;

    // Une os rótulos das duas séries para alinhar os pontos no eixo X.
    var labels = [];
    series.forEach(function (s) {
      s.labels.forEach(function (l) { if (labels.indexOf(l) === -1) labels.push(l); });
    });

    var conjuntos = series.map(function (s) {
      return {
        nome: s.nome,
        dados: labels.map(function (l) {
          var i = s.labels.indexOf(l);
          return i === -1 ? null : s.dados[i];
        })
      };
    });

    <?php if (($resultado['formato'] ?? '') === 'barras'): ?>
      Painel.barras('c-comparacao', labels, conjuntos, { scales: { y: { max: 100 } } });
    <?php else: ?>
      Painel.linha('c-comparacao', labels, conjuntos, { scales: { y: { max: 100 } } });
    <?php endif; ?>
  });
  </script>
<?php endif; ?>
