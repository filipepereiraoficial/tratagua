<?php
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Gráficos</h1>
    <p class="mudo mb-0">Todos os gráficos respeitam os filtros abaixo e são alimentados pelos registros reais.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/comparacao') ?>">Comparar desempenho</a>
    <a class="botao botao--secundario" href="#" data-imprimir>Imprimir</a>
  </div>
</div>

<div class="cartao nao-imprimir">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/graficos') ?>" data-auto-filtro>
      <div class="campo">
        <label for="curso">Curso</label>
        <select id="curso" name="curso">
          <option value="">Todos</option>
          <?php foreach ($cursos as $curso): ?>
            <option value="<?= (int) $curso['id'] ?>" <?= ($filters['curso'] ?? '') == $curso['id'] ? 'selected' : '' ?>><?= e($curso['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="turma">Turma</label>
        <select id="turma" name="turma">
          <option value="">Todas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>><?= e($turma['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="disciplina">Disciplina</label>
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
      <div class="campo acoes">
        <button class="botao" type="submit">Aplicar</button>
        <a class="botao botao--secundario" href="<?= url('/graficos') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Evolução geral</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-evolucao"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Distribuição de desempenho</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-distribuicao"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Média por disciplina</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-disciplinas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Comparação entre turmas</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-turmas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Melhores assuntos</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="c-melhores"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Assuntos mais críticos</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="c-piores"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Aproveitamento por dificuldade</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-dificuldade"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Frequência por turma</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="c-frequencia"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Quem mais evoluiu e quem mais caiu</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="c-movers"></canvas></div></div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  Painel.linha('c-evolucao',
    <?= $j(array_column($serie, 'assessment_name')) ?>,
    [{ nome: 'Média (%)', dados: <?= $j(array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $serie)) ?> }],
    { scales: { y: { max: 100 } } });

  Painel.rosca('c-distribuicao', <?= $j(array_keys($distribuicao)) ?>, <?= $j(array_values($distribuicao)) ?>,
    [Painel.paleta.vermelho, Painel.paleta.ambar, Painel.paleta.azul, Painel.paleta.verde]);

  var disc = <?= $j(array_map(static fn ($r) => ['n' => $r['subject_name'], 'v' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_disciplina)) ?>;
  Painel.barras('c-disciplinas', disc.map(function (d) { return d.n; }),
    [{ nome: 'Média (%)', dados: disc.map(function (d) { return d.v; }),
       cores: disc.map(function (d) { return Painel.corPorFaixa(d.v); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var turmas = <?= $j(array_map(static fn ($r) => ['n' => $r['class_code'], 'v' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_turma)) ?>;
  Painel.barras('c-turmas', turmas.map(function (t) { return t.n; }),
    [{ nome: 'Média (%)', dados: turmas.map(function (t) { return t.v; }), cor: Painel.paleta.azulEscuro }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var melhores = <?= $j(array_map(static fn ($a) => ['n' => $a['topic_name'], 'v' => $a['aproveitamento']], array_slice($assuntos, 0, 12))) ?>;
  Painel.barras('c-melhores', melhores.map(function (a) { return a.n; }),
    [{ nome: 'Aproveitamento (%)', dados: melhores.map(function (a) { return a.v; }), cor: Painel.paleta.verde }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var piores = <?= $j(array_map(static fn ($a) => ['n' => $a['topic_name'], 'v' => $a['aproveitamento']], array_slice(array_reverse($assuntos), 0, 12))) ?>;
  Painel.barras('c-piores', piores.map(function (a) { return a.n; }),
    [{ nome: 'Aproveitamento (%)', dados: piores.map(function (a) { return a.v; }), cor: Painel.paleta.vermelho }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var dif = <?= $j(array_map(static fn ($d) => ['n' => rotulo('dificuldade', $d['dificuldade']), 'v' => $d['aproveitamento']], $dificuldade)) ?>;
  Painel.barras('c-dificuldade', dif.map(function (d) { return d.n; }),
    [{ nome: 'Aproveitamento (%)', dados: dif.map(function (d) { return d.v; }),
       cores: [Painel.paleta.verde, Painel.paleta.ambar, Painel.paleta.vermelho] }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var freq = <?= $j(array_map(static fn ($r) => ['n' => $r['class_code'], 'v' => $r['frequencia'] === null ? null : round((float) $r['frequencia'], 2)], $frequencia)) ?>;
  Painel.barras('c-frequencia', freq.map(function (f) { return f.n; }),
    [{ nome: 'Frequência (%)', dados: freq.map(function (f) { return f.v; }), cor: Painel.paleta.roxo }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var mov = <?= $j(array_map(static fn ($r) => ['n' => $r['full_name'], 'v' => $r['evolucao_recente']], array_merge($movers['subiram'], array_reverse($movers['cairam'])))) ?>;
  Painel.barras('c-movers', mov.map(function (m) { return m.n; }),
    [{ nome: 'Evolução recente (p.p.)', dados: mov.map(function (m) { return m.v; }),
       cores: mov.map(function (m) { return m.v >= 0 ? Painel.paleta.verde : Painel.paleta.vermelho; }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { y: { ticks: { autoSkip: false, font: { size: 10 } } }, x: { beginAtZero: true } } });
});
</script>
