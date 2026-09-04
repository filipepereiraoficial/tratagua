<?php
/** Painel do administrador — a instituição inteira ou um curso por vez. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Painel do administrador</h1>
    <p class="mudo mb-0">
      <?= $curso ? 'Curso: <strong>' . e($curso['name']) . '</strong>' : 'Todos os cursos' ?>.
      O dashboard geral mostra como está indo; esta tela mostra <em>onde está o problema</em>.
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/professores') ?>">Professores</a>
    <a class="botao botao--secundario" href="<?= url('/auditoria') ?>">Auditoria</a>
    <a class="botao" href="<?= url('/') ?>">Dashboard geral</a>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/painel-admin') ?>" data-auto-filtro>
      <div class="campo">
        <label for="f-curso">Curso</label>
        <select id="f-curso" name="curso">
          <option value="">Todos os cursos</option>
          <?php foreach ($cursos as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($filters['curso'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="f-inicio">De</label>
        <input type="date" id="f-inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>"></div>
      <div class="campo"><label for="f-fim">Até</label>
        <input type="date" id="f-fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>"></div>
      <div class="campo acoes">
        <button class="botao" type="submit">Aplicar</button>
        <a class="botao botao--secundario" href="<?= url('/painel-admin') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="indicadores">
  <div class="indicador"><div class="indicador__rotulo">Cursos</div>
    <div class="indicador__valor"><?= (int) $contadores['cursos'] ?></div>
    <div class="indicador__nota"><?= (int) $contadores['turmas'] ?> turma(s)</div></div>
  <div class="indicador"><div class="indicador__rotulo">Alunos</div>
    <div class="indicador__valor"><?= (int) $contadores['alunos'] ?></div>
    <div class="indicador__nota"><?= (int) $contadores['disciplinas'] ?> disciplina(s)</div></div>
  <div class="indicador"><div class="indicador__rotulo">Professores</div>
    <div class="indicador__valor"><?= count($por_professor) ?></div>
    <div class="indicador__nota">com turma atribuída</div></div>
  <div class="indicador indicador--<?= faixa_classe($media, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média do recorte</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div></div>
  <div class="indicador indicador--bom"><div class="indicador__rotulo">Em evolução</div>
    <div class="indicador__valor"><?= (int) $classes['evolucao'] ?></div></div>
  <div class="indicador indicador--ruim"><div class="indicador__rotulo">Precisam de atenção</div>
    <div class="indicador__valor"><?= (int) $classes['atencao'] ?></div></div>
  <div class="indicador indicador--roxo"><div class="indicador__rotulo">Acompanhamentos abertos</div>
    <div class="indicador__valor"><?= (int) ($acompanhamentos['aberta'] + $acompanhamentos['em_andamento']) ?></div>
    <div class="indicador__nota"><?= (int) $acompanhamentos['atrasadas'] ?> com prazo vencido</div></div>
  <div class="indicador indicador--medio"><div class="indicador__rotulo">Pendências operacionais</div>
    <div class="indicador__valor"><?= count($pendencias) ?></div>
    <div class="indicador__nota">itens bloqueando a análise</div></div>
</div>

<?php if ($pendencias !== []): ?>
  <div class="cartao">
    <div class="cartao__cabecalho">
      <h2>O que está travando a análise</h2>
      <span class="etiqueta etiqueta--medio"><?= count($pendencias) ?> pendência(s)</span>
    </div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php foreach ($pendencias as $item): ?>
        <div class="alerta alerta--media">
          <div class="alerta__marca"></div>
          <div class="alerta__corpo">
            <div class="alerta__titulo"><?= e($item['tipo']) ?></div>
            <div class="alerta__texto"><?= e($item['texto']) ?></div>
          </div>
          <div class="alerta__acoes"><a class="botao botao--secundario botao--pequeno" href="<?= url($item['link']) ?>">Resolver</a></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Comparação entre cursos</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="ad-cursos"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Comparação entre turmas</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="ad-turmas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Média por disciplina</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="ad-disc"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Assuntos mais críticos da instituição</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="ad-assuntos"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Cursos</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead><tr><th>Curso</th><th class="num">Turmas</th><th class="num">Alunos</th><th class="num">Média</th>
            <th class="num">Evolução</th><th class="num">Atenção</th></tr></thead>
          <tbody>
          <?php foreach ($por_curso as $linha): ?>
            <tr class="linha-clicavel" onclick="location.href='<?= e(url('/painel-admin', ['curso' => $linha['id']])) ?>'">
              <td><strong><?= e($linha['nome']) ?></strong></td>
              <td class="num"><?= (int) $linha['turmas'] ?></td>
              <td class="num"><?= (int) $linha['alunos'] ?></td>
              <td class="num"><span class="etiqueta etiqueta--<?= faixa_classe($linha['media'], $faixas['dominio'], $faixas['intermediario']) ?>"><?= pct($linha['media'], 0) ?></span></td>
              <td class="num"><?= (int) $linha['evolucao'] ?></td>
              <td class="num"><?= (int) $linha['atencao'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Professores</h2>
      <a class="pequeno" href="<?= url('/professores') ?>">gerenciar</a></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($por_professor === []): ?>
        <div class="vazio pequeno">Nenhum professor com turma atribuída.
          <div class="mt-2"><a class="botao botao--pequeno" href="<?= url('/professores/novo') ?>">Cadastrar professor</a></div>
        </div>
      <?php else: ?>
        <div class="tabela-rolagem">
          <table class="tabela tabela--compacta">
            <thead><tr><th>Professor</th><th class="num">Ofertas</th><th class="num">Alunos</th>
              <th class="num">Aulas</th><th class="num">Aval.</th><th class="num">Média</th><th class="num">Atenção</th></tr></thead>
            <tbody>
            <?php foreach ($por_professor as $linha): ?>
              <tr class="linha-clicavel" onclick="location.href='<?= e(url('/professores/' . $linha['id'])) ?>'">
                <td><?= e($linha['nome']) ?></td>
                <td class="num"><?= (int) $linha['ofertas'] ?></td>
                <td class="num"><?= (int) $linha['alunos'] ?></td>
                <td class="num"><?= (int) $linha['aulas'] ?></td>
                <td class="num"><?= (int) $linha['avaliacoes'] ?></td>
                <td class="num"><span class="etiqueta etiqueta--<?= faixa_classe($linha['media'], $faixas['dominio'], $faixas['intermediario']) ?>"><?= pct($linha['media'], 0) ?></span></td>
                <td class="num"><?= (int) $linha['atencao'] ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pequeno mudo" style="padding:.6rem .9rem">
          A média é a das turmas atendidas por cada um — leia como contexto da turma, não como avaliação do professor.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Alertas do recorte</h2>
      <span class="etiqueta etiqueta--ruim"><?= count(array_filter($alertas, static fn ($a) => $a['severity'] === 'alta')) ?> alta</span></div>
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
          <?php if ($alerta['student_id']): ?>
            <div class="alerta__acoes">
              <a class="botao botao--secundario botao--pequeno" href="<?= url('/alunos/' . $alerta['student_id']) ?>">Aluno</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Últimas alterações</h2>
      <a class="pequeno" href="<?= url('/auditoria') ?>">ver tudo</a></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($atividade === []): ?>
        <div class="vazio pequeno">Nenhuma alteração registrada ainda.</div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach ($atividade as $log): ?>
            <li>
              <span class="nome">
                <strong><?= e($log['user_name'] ?? 'sistema') ?></strong> <?= e($log['action']) ?>
                <?= $log['entity'] ? '<span class="mudo">' . e($log['entity']) . '</span>' : '' ?><br>
                <small class="mudo"><?= e($log['details'] ?? '') ?> · <?= datahora_br($log['created_at']) ?></small>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;
  var cursos = <?= $j(array_map(static fn ($c) => ['n' => $c['nome'], 'v' => $c['media']], $por_curso)) ?>;
  Painel.barras('ad-cursos', cursos.map(function (c) { return c.n; }),
    [{ nome: 'Média (%)', dados: cursos.map(function (c) { return c.v; }),
       cores: cursos.map(function (c) { return Painel.corPorFaixa(c.v, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var turmas = <?= $j(array_map(static fn ($t) => ['n' => $t['class_code'], 'v' => $t['media'] === null ? null : round((float) $t['media'], 2)], $por_turma)) ?>;
  Painel.barras('ad-turmas', turmas.map(function (t) { return t.n; }),
    [{ nome: 'Média (%)', dados: turmas.map(function (t) { return t.v; }), cor: Painel.paleta.azulEscuro }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var disc = <?= $j(array_map(static fn ($d) => ['n' => $d['subject_name'], 'v' => $d['media'] === null ? null : round((float) $d['media'], 2)], $por_disciplina)) ?>;
  Painel.barras('ad-disc', disc.map(function (d) { return d.n; }),
    [{ nome: 'Média (%)', dados: disc.map(function (d) { return d.v; }),
       cores: disc.map(function (d) { return Painel.corPorFaixa(d.v, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var ass = <?= $j(array_map(static fn ($a) => ['n' => $a['topic_name'], 'v' => $a['aproveitamento']], array_slice($assuntos, 0, 12))) ?>;
  Painel.barras('ad-assuntos', ass.map(function (a) { return a.n; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.v; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.v, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });
});
</script>
