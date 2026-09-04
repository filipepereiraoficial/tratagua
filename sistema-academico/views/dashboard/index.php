<?php
/** Dashboard geral — indicadores, gráficos, alertas e ranking. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Dashboard geral</h1>
    <p class="mudo mb-0">Visão consolidada do desenvolvimento acadêmico. Todos os números vêm dos registros lançados no sistema.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/relatorios', $filters) ?>">Relatórios</a>
    <a class="botao" href="<?= url('/graficos', $filters) ?>">Ver todos os gráficos</a>
  </div>
</div>

<!-- Filtros -->
<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/') ?>" data-auto-filtro>
      <div class="campo">
        <label for="f-curso">Curso</label>
        <select id="f-curso" name="curso">
          <option value="">Todos</option>
          <?php foreach ($cursos as $curso): ?>
            <option value="<?= (int) $curso['id'] ?>" <?= ($filters['curso'] ?? '') == $curso['id'] ? 'selected' : '' ?>><?= e($curso['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-turma">Turma</label>
        <select id="f-turma" name="turma">
          <option value="">Todas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
              <?= e($turma['code']) ?> · <?= e($turma['course_name']) ?> (<?= (int) $turma['year'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-disciplina">Disciplina</label>
        <select id="f-disciplina" name="disciplina">
          <option value="">Todas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>><?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-inicio">De</label>
        <input type="date" id="f-inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="f-fim">Até</label>
        <input type="date" id="f-fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>">
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Aplicar</button>
        <a class="botao botao--secundario" href="<?= url('/') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<!-- Indicadores de volume -->
<div class="indicadores">
  <div class="indicador">
    <div class="indicador__rotulo">Alunos</div>
    <div class="indicador__valor"><?= (int) $counters['alunos'] ?></div>
    <div class="indicador__nota"><a href="<?= url('/alunos') ?>">Ver lista</a></div>
  </div>
  <div class="indicador">
    <div class="indicador__rotulo">Turmas</div>
    <div class="indicador__valor"><?= (int) $counters['turmas'] ?></div>
    <div class="indicador__nota"><?= (int) $counters['cursos'] ?> curso(s)</div>
  </div>
  <div class="indicador">
    <div class="indicador__rotulo">Disciplinas</div>
    <div class="indicador__valor"><?= (int) $counters['disciplinas'] ?></div>
    <div class="indicador__nota"><?= (int) $counters['questoes'] ?> questão(ões)</div>
  </div>
  <div class="indicador">
    <div class="indicador__rotulo">Aulas</div>
    <div class="indicador__valor"><?= (int) $counters['aulas'] ?></div>
    <div class="indicador__nota"><a href="<?= url('/aulas') ?>">Histórico</a></div>
  </div>
  <div class="indicador">
    <div class="indicador__rotulo">Avaliações</div>
    <div class="indicador__valor"><?= (int) $counters['avaliacoes'] ?></div>
    <div class="indicador__nota"><a href="<?= url('/avaliacoes') ?>">Ver avaliações</a></div>
  </div>
</div>

<!-- Indicadores de desempenho -->
<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($media_geral, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média geral</div>
    <div class="indicador__valor"><?= $media_geral !== null ? pct($media_geral) : '—' ?></div>
    <div class="indicador__nota">Média dos aproveitamentos lançados</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% médio de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= number_format($acertos['acertos'], 0, ',', '.') ?> de <?= number_format($acertos['total'], 0, ',', '.') ?> questões</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">% médio de erros</div>
    <div class="indicador__valor"><?= $acertos['pct_erros'] !== null ? pct($acertos['pct_erros']) : '—' ?></div>
    <div class="indicador__nota"><?= $acertos['pct_branco'] !== null ? pct($acertos['pct_branco']) . ' em branco' : 'Sem respostas registradas' ?></div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Alunos em evolução</div>
    <div class="indicador__valor"><?= (int) $classificacao['evolucao'] ?></div>
    <div class="indicador__nota">Índice ≥ configurado para destaque</div>
  </div>
  <div class="indicador indicador--medio">
    <div class="indicador__rotulo">Intermediários</div>
    <div class="indicador__valor"><?= (int) $classificacao['intermediario'] ?></div>
    <div class="indicador__nota">Desempenho moderado</div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Precisam de atenção</div>
    <div class="indicador__valor"><?= (int) $classificacao['atencao'] ?></div>
    <div class="indicador__nota"><?= (int) $classificacao['sem_dados'] ?> sem dados suficientes</div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <!-- Gráficos -->
    <div class="graficos">
      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Evolução geral</h2><span class="etiqueta etiqueta--info">média por avaliação</span></div>
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
        <div class="cartao__cabecalho"><h2>Comparação entre turmas</h2></div>
        <div class="cartao__corpo"><div class="grafico"><canvas id="g-turmas"></canvas></div></div>
      </div>

      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Desempenho por assunto</h2><span class="etiqueta etiqueta--neutro">15 menores</span></div>
        <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="g-assuntos"></canvas></div></div>
      </div>

      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Frequência por turma</h2></div>
        <div class="cartao__corpo"><div class="grafico"><canvas id="g-frequencia"></canvas></div></div>
      </div>

      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Evolução dos alunos</h2><span class="etiqueta etiqueta--neutro">p.p. recentes</span></div>
        <div class="cartao__corpo"><div class="grafico"><canvas id="g-movers"></canvas></div></div>
      </div>

      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Média por avaliação</h2></div>
        <div class="cartao__corpo"><div class="grafico"><canvas id="g-avaliacoes"></canvas></div></div>
      </div>
    </div>
  </div>

  <div>
    <!-- Alertas -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Alertas pedagógicos</h2>
        <span class="etiqueta etiqueta--ruim"><?= (int) $alertas_sev['alta'] ?> alta</span>
        <span class="etiqueta etiqueta--medio"><?= (int) $alertas_sev['media'] ?> média</span>
        <span class="etiqueta etiqueta--bom"><?= (int) $alertas_sev['positiva'] ?> positiva</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($alertas === []): ?>
          <div class="vazio"><span class="vazio__icone">✓</span>Nenhum alerta ativo neste recorte.</div>
        <?php else: ?>
          <?php foreach ($alertas as $alerta): ?>
            <div class="alerta alerta--<?= e($alerta['severity']) ?>">
              <div class="alerta__marca"></div>
              <div class="alerta__corpo">
                <div class="alerta__titulo"><?= e($alerta['title']) ?></div>
                <div class="alerta__texto"><?= e($alerta['message']) ?></div>
                <?php if ($alerta['student_id']): ?>
                  <a class="pequeno" href="<?= url('/alunos/' . $alerta['student_id']) ?>">Ver dashboard do aluno →</a>
                <?php endif; ?>
              </div>
              <form class="alerta__acoes" method="post" action="<?= url('/alertas/tratar') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="alert_key" value="<?= e($alerta['key']) ?>">
                <button class="botao botao--secundario botao--pequeno" type="submit" title="Marcar como tratado">✓</button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if ($alertas_total > count($alertas)): ?>
            <div class="pequeno mudo" style="padding:.6rem .9rem">
              Exibindo <?= count($alertas) ?> de <?= (int) $alertas_total ?> alertas.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ranking -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Índice de Desenvolvimento</h2>
        <a class="pequeno" href="<?= url('/relatorios', array_merge($filters, ['relatorio' => 'turma'])) ?>">Ver todos</a>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($ranking === []): ?>
          <div class="vazio"><span class="vazio__icone">📊</span>Sem alunos avaliados neste recorte.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>#</th><th>Aluno</th><th class="num">Média</th><th class="num">Índice</th><th>Situação</th></tr></thead>
              <tbody>
              <?php foreach ($ranking as $linha): ?>
                <tr>
                  <td class="num"><?= $linha['posicao'] ?? '—' ?></td>
                  <td>
                    <a href="<?= url('/alunos/' . $linha['id']) ?>"><?= e($linha['full_name']) ?></a>
                    <?php if ($linha['class_code']): ?><br><small class="mudo"><?= e($linha['class_code']) ?></small><?php endif; ?>
                  </td>
                  <td class="num"><?= $linha['media'] !== null ? pct($linha['media'], 0) : '—' ?></td>
                  <td class="num"><strong><?= $linha['indice'] !== null ? num($linha['indice']) : '—' ?></strong></td>
                  <td>
                    <?php
                    $classe = match ($linha['classificacao']) {
                        'evolucao' => 'bom', 'intermediario' => 'medio', 'atencao' => 'ruim', default => 'neutro',
                    };
                    ?>
                    <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('classificacao', $linha['classificacao'])) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pequeno mudo" style="padding:.6rem .9rem">
            Pesos em uso: <?= e($pesos) ?>. <a href="<?= url('/configuracoes') ?>">Ajustar</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Assuntos críticos -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Conteúdos que precisam de revisão</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($assuntos === []): ?>
          <div class="vazio"><span class="vazio__icone">📚</span>Registre resultados por questão para mapear os assuntos.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($assuntos as $assunto): ?>
              <li>
                <span class="nome">
                  <?= e($assunto['topic_name']) ?><br>
                  <small class="mudo"><?= e($assunto['subject_name']) ?> · <?= (int) $assunto['respondidas'] ?> resposta(s)</small>
                </span>
                <span class="progresso-linha" style="flex:0 0 130px">
                  <span class="progresso">
                    <span class="progresso__barra progresso__barra--<?= faixa_classe($assunto['aproveitamento'], $faixas['dominio'], $faixas['intermediario']) ?>"
                          style="width:<?= (float) ($assunto['aproveitamento'] ?? 0) ?>%"></span>
                  </span>
                  <span><?= $assunto['aproveitamento'] !== null ? pct($assunto['aproveitamento'], 0) : '—' ?></span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$labelsAval = array_map(static fn ($r) => $r['assessment_name'], $serie);
$mediasAval = array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $serie);
$movimento  = array_merge($movers['subiram'], array_reverse($movers['cairam']));
?>
<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  Painel.linha('g-evolucao', <?= $j($labelsAval) ?>, [{ nome: 'Média (%)', dados: <?= $j($mediasAval) ?> }], { scales: { y: { max: 100 } } });
  Painel.linha('g-avaliacoes', <?= $j($labelsAval) ?>, [{ nome: 'Média (%)', dados: <?= $j($mediasAval) ?>, cor: Painel.paleta.roxo }], { scales: { y: { max: 100 } } });

  Painel.rosca('g-distribuicao',
    <?= $j(array_keys($distribuicao)) ?>,
    <?= $j(array_values($distribuicao)) ?>,
    [Painel.paleta.vermelho, Painel.paleta.ambar, Painel.paleta.azul, Painel.paleta.verde]);

  var disc = <?= $j(array_map(static fn ($r) => ['nome' => $r['subject_name'], 'media' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_disciplina)) ?>;
  Painel.barras('g-disciplinas', disc.map(function (d) { return d.nome; }),
    [{ nome: 'Média (%)', dados: disc.map(function (d) { return d.media; }),
       cores: disc.map(function (d) { return Painel.corPorFaixa(d.media, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var turmas = <?= $j(array_map(static fn ($r) => ['nome' => $r['class_code'], 'media' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_turma)) ?>;
  Painel.barras('g-turmas', turmas.map(function (t) { return t.nome; }),
    [{ nome: 'Média (%)', dados: turmas.map(function (t) { return t.media; }), cor: Painel.paleta.azulEscuro }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var assuntos = <?= $j(array_map(static fn ($r) => ['nome' => $r['topic_name'], 'valor' => $r['aproveitamento']], array_slice($assuntos, 0, 15))) ?>;
  Painel.barras('g-assuntos', assuntos.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: assuntos.map(function (a) { return a.valor; }),
       cores: assuntos.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var freq = <?= $j(array_map(static fn ($r) => ['nome' => $r['class_code'], 'valor' => $r['frequencia'] === null ? null : round((float) $r['frequencia'], 2)], $frequencia)) ?>;
  Painel.barras('g-frequencia', freq.map(function (f) { return f.nome; }),
    [{ nome: 'Frequência (%)', dados: freq.map(function (f) { return f.valor; }), cor: Painel.paleta.verde }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var mov = <?= $j(array_map(static fn ($r) => ['nome' => $r['full_name'], 'valor' => $r['evolucao_recente']], $movimento)) ?>;
  Painel.barras('g-movers', mov.map(function (m) { return m.nome; }),
    [{ nome: 'Evolução recente (p.p.)', dados: mov.map(function (m) { return m.valor; }),
       cores: mov.map(function (m) { return m.valor >= 0 ? Painel.paleta.verde : Painel.paleta.vermelho; }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { y: { ticks: { autoSkip: false, font: { size: 10 } } }, x: { beginAtZero: true } } });
});
</script>
