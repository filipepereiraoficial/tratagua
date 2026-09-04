<?php
/** Painel individual do aluno, do ponto de vista da disciplina do professor. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$c = match ($resumo['classificacao']) {
    'evolucao' => ['bom', 'Em evolução'], 'intermediario' => ['medio', 'Intermediário'],
    'atencao' => ['ruim', 'Precisa de atenção'], default => ['neutro', 'Sem dados suficientes'],
};
$labels = array_map(static fn ($n) => $n['assessment_name'], $resumo['notas']);
$mapaTurma = [];
foreach ($serie_turma as $item) { $mapaTurma[(int) $item['assessment_id']] = $item['media'] === null ? null : round((float) $item['media'], 2); }
$perdaDele = array_values(array_filter($perda, static fn ($p) => (int) $p['student_id'] === (int) $aluno['id']));
?>
<div class="pagina__cabecalho">
  <div style="display:flex;gap:.9rem;align-items:center">
    <span class="avatar" style="width:46px;height:46px;font-size:1rem"><?= e(iniciais($aluno['full_name'])) ?></span>
    <div>
      <h1 style="margin-bottom:.15rem"><?= e($aluno['full_name']) ?></h1>
      <p class="mudo mb-0 pequeno">
        <button class="migalha" onclick="location.href='<?= e(url('/meu-painel')) ?>'" style="background:none;border:0;color:var(--azul-600);cursor:pointer;padding:0">Meu painel</button>
        · <?= e($aluno['class_code'] ?? 'sem turma') ?>
        · <span class="etiqueta etiqueta--<?= $c[0] ?>"><?= $c[1] ?></span>
        <?php if ($posicao['posicao']): ?> · posição <?= (int) $posicao['posicao'] ?> de <?= (int) $posicao['total'] ?><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario"
       href="<?= url('/acompanhamento/novo', ['aluno' => $aluno['id'], 'titulo' => 'Acompanhamento de ' . $aluno['full_name']]) ?>">Registrar acompanhamento</a>
  </div>
</div>

<?php if ($resumo['motivos']): ?>
  <div class="aviso aviso--<?= $resumo['classificacao'] === 'atencao' ? 'warning' : 'info' ?>">
    <span>ℹ</span><div><strong>Por que está classificado assim:</strong> <?= e(implode(' ', $resumo['motivos'])) ?></div>
  </div>
<?php endif; ?>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/meu-painel/aluno/' . $aluno['id']) ?>" data-auto-filtro>
      <div class="campo">
        <label for="f-disciplina">Minha disciplina</label>
        <select id="f-disciplina" name="disciplina">
          <option value="">Todas as minhas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>>
              <?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="f-inicio">De</label>
        <input type="date" id="f-inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>"></div>
      <div class="campo"><label for="f-fim">Até</label>
        <input type="date" id="f-fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>"></div>
      <div class="campo acoes">
        <button class="botao" type="submit">Aplicar</button>
        <a class="botao botao--secundario" href="<?= url('/meu-painel/aluno/' . $aluno['id']) ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="indicadores">
  <?php $delta = ($resumo['media'] !== null && $media_turma !== null) ? $resumo['media'] - $media_turma : null; ?>
  <div class="indicador indicador--<?= faixa_classe($resumo['media'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média na minha disciplina</div>
    <div class="indicador__valor"><?= pct($resumo['media']) ?></div>
    <div class="indicador__nota"><?= $delta === null ? 'sem comparativo' :
      ($delta >= 0 ? '▲' : '▼') . ' ' . num(abs($delta)) . ' p.p. vs. turma' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['acertos']['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= pct($resumo['acertos']['pct_acertos']) ?></div>
    <div class="indicador__nota"><?= (int) $resumo['acertos']['acertos'] ?> de <?= (int) $resumo['acertos']['total'] ?></div>
  </div>
  <div class="indicador indicador--<?= $resumo['evolucao_recente'] === null ? 'neutro' : ($resumo['evolucao_recente'] >= 0 ? 'bom' : 'ruim') ?>">
    <div class="indicador__rotulo">Evolução recente</div>
    <div class="indicador__valor"><?= $resumo['evolucao_recente'] === null ? '—' : num($resumo['evolucao_recente']) . ' p.p.' ?></div>
    <div class="indicador__nota"><?= (int) $resumo['avaliacoes'] ?> avaliação(ões)</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['frequencia'], 90, 75) ?>">
    <div class="indicador__rotulo">Frequência</div>
    <div class="indicador__valor"><?= pct($resumo['frequencia']) ?></div>
    <div class="indicador__nota"><?= (int) $resumo['presenca']['faltas'] ?> falta(s)</div>
  </div>
  <?php $perdidos = $perdaDele[0]['perdidos'] ?? null; ?>
  <div class="indicador indicador--medio">
    <div class="indicador__rotulo">Pontos deixados na prova</div>
    <div class="indicador__valor"><?= $perdidos === null ? '—' : num($perdidos, 1) ?></div>
    <div class="indicador__nota">pior em <?= e($perdaDele[0]['pior_avaliacao'] ?? '—') ?></div>
  </div>
  <div class="indicador indicador--roxo">
    <div class="indicador__rotulo">Índice de Desenvolvimento</div>
    <div class="indicador__valor"><?= num($resumo['indice']) ?></div>
    <div class="indicador__nota"><?= (int) $resumo['dominados'] ?> domínio · <?= (int) $resumo['dificuldades'] ?> dificuldade</div>
  </div>
</div>

<div class="colunas colunas--2">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Evolução</h2><span class="etiqueta etiqueta--info">aluno × turma</span></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="a-ev"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Desempenho por assunto</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="a-as"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Mapa de conteúdos na minha disciplina</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($resumo['assuntos'] === []): ?>
          <div class="vazio pequeno">Lance resultados por questão para mapear os assuntos deste aluno.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Assunto</th><th class="num">Questões</th><th class="num">Acertos</th>
                <th>Aproveitamento</th><th>Situação</th></tr></thead>
              <tbody>
              <?php foreach ($resumo['assuntos'] as $assunto):
                  $f = faixa_classe($assunto['amostra_suficiente'] ? $assunto['aproveitamento'] : null, $faixas['dominio'], $faixas['intermediario']);
                  $texto = !$assunto['amostra_suficiente'] ? 'Amostra insuficiente'
                      : match ($assunto['classificacao']) { 'dominio' => 'Domínio', 'intermediario' => 'Intermediário', default => 'Dificuldade' }; ?>
                <tr>
                  <td><?= e($assunto['topic_name']) ?></td>
                  <td class="num"><?= (int) $assunto['respondidas'] ?></td>
                  <td class="num"><?= (int) $assunto['acertos'] ?></td>
                  <td style="min-width:150px">
                    <span class="progresso-linha">
                      <span class="progresso"><span class="progresso__barra progresso__barra--<?= $f ?>"
                        style="width:<?= (float) ($assunto['aproveitamento'] ?? 0) ?>%"></span></span>
                      <span><?= pct($assunto['aproveitamento'], 0) ?></span>
                    </span>
                  </td>
                  <td><span class="etiqueta etiqueta--<?= $f ?>"><?= $texto ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Avaliações</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($resumo['notas'] === []): ?>
          <div class="vazio pequeno">Nenhuma avaliação lançada no recorte.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Data</th><th>Avaliação</th><th class="num">Nota</th><th class="num">%</th><th class="num">C/E/B</th></tr></thead>
              <tbody>
              <?php foreach (array_reverse($resumo['notas']) as $nota): ?>
                <tr class="linha-clicavel" onclick="location.href='<?= e(url('/avaliacoes/' . $nota['assessment_id'])) ?>'">
                  <td class="mono nowrap"><?= data_br($nota['assessment_date']) ?></td>
                  <td><?= e($nota['assessment_name']) ?><div class="mudo pequeno"><?= e($nota['subject_name']) ?></div></td>
                  <td class="num mono"><?= num($nota['score'], 2) ?> / <?= num($nota['max_score'], 0) ?></td>
                  <td class="num"><span class="etiqueta etiqueta--<?= faixa_classe((float) $nota['percentage'], $faixas['dominio'], $faixas['intermediario']) ?>"><?= pct($nota['percentage'], 0) ?></span></td>
                  <td class="num mono pequeno"><?= (int) $nota['correct_count'] ?>/<?= (int) $nota['wrong_count'] ?>/<?= (int) $nota['blank_count'] ?></td>
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
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Alertas</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($alertas === []): ?>
          <div class="vazio pequeno">Nenhum alerta ativo.</div>
        <?php else: foreach ($alertas as $alerta): ?>
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

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Acompanhamentos</h2>
        <a class="pequeno" href="<?= url('/acompanhamento/novo', ['aluno' => $aluno['id']]) ?>">+ novo</a></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($acompanhamentos === []): ?>
          <div class="vazio pequeno">Nenhum registro para este aluno.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($acompanhamentos as $item): ?>
              <li>
                <span class="nome"><a href="<?= url('/acompanhamento/' . $item['id'] . '/editar') ?>"><?= e($item['title']) ?></a><br>
                  <small class="mudo"><?= e(\App\Models\Intervention::TYPES[$item['type']]) ?> · <?= data_br($item['created_at']) ?></small></span>
                <span class="etiqueta etiqueta--<?= $item['status'] === 'concluida' ? 'bom' : ($item['status'] === 'cancelada' ? 'neutro' : 'medio') ?>">
                  <?= e(\App\Models\Intervention::STATUSES[$item['status']]) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Presença</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($presencas === []): ?>
          <div class="vazio pequeno">Sem chamadas registradas.</div>
        <?php else: ?>
          <div class="tabela-rolagem" style="max-height:280px;overflow-y:auto">
            <table class="tabela tabela--compacta">
              <tbody>
              <?php foreach ($presencas as $p): ?>
                <?php $cl = ['presente' => 'bom', 'atraso' => 'medio', 'falta' => 'ruim', 'falta_justificada' => 'neutro'][$p['status']]; ?>
                <tr><td class="mono pequeno"><?= data_br($p['lesson_date']) ?></td>
                  <td class="pequeno"><?= e($p['title']) ?></td>
                  <td><span class="etiqueta etiqueta--<?= $cl ?>"><?= e(rotulo('presenca', $p['status'])) ?></span></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;
  Painel.linha('a-ev', <?= $j($labels) ?>, [
    { nome: <?= $j(explode(' ', $aluno['full_name'])[0]) ?>, dados: <?= $j($resumo['percentuais']) ?> },
    { nome: 'Turma', dados: <?= $j(array_map(static fn ($n) => $mapaTurma[(int) $n['assessment_id']] ?? null, $resumo['notas'])) ?>, cor: Painel.paleta.cinza }
  ], { scales: { y: { max: 100 } } });

  var ass = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], $resumo['assuntos'])) ?>;
  Painel.barras('a-as', ass.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.valor; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });
});
</script>
