<?php
/** Painel do professor — recortado nas ofertas sob responsabilidade dele. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ehAdmin = ($auth['role'] ?? '') === 'admin';
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Meu painel</h1>
    <p class="mudo mb-0">
      <?php if ($ehAdmin): ?>
        Como administrador, você vê todas as ofertas. O professor vê apenas as turmas e disciplinas dele.
      <?php else: ?>
        <?= count($ofertas) ?> turma(s)/disciplina(s) sob sua responsabilidade.
        Tudo nesta tela considera só os seus alunos.
      <?php endif; ?>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/minhas-turmas') ?>">Minhas turmas</a>
    <a class="botao botao--secundario" href="<?= url('/aulas/nova') ?>">+ Aula</a>
    <a class="botao" href="<?= url('/avaliacoes/nova') ?>">+ Avaliação</a>
  </div>
</div>

<?php if ($ofertas === []): ?>
  <div class="aviso aviso--warning">
    <span>⚠</span>
    <div>Você ainda não está vinculado a nenhuma turma/disciplina. Peça ao administrador para
      fazer o vínculo em <strong>Professores → sua ficha → Vincular a uma turma/disciplina</strong>.</div>
  </div>
<?php endif; ?>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/meu-painel') ?>" data-auto-filtro>
      <div class="campo">
        <label for="f-turma">Turma</label>
        <select id="f-turma" name="turma">
          <option value="">Todas as minhas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
              <?= e($turma['code']) ?> (<?= (int) $turma['year'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-disciplina">Disciplina</label>
        <select id="f-disciplina" name="disciplina">
          <option value="">Todas as minhas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>>
              <?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="f-tipo">Tipo de avaliação</label>
        <select id="f-tipo" name="tipo">
          <option value="">Todos</option>
          <?php foreach (\App\Models\Assessment::TYPES as $tipo): ?>
            <option value="<?= $tipo ?>" <?= ($filters['tipo'] ?? '') === $tipo ? 'selected' : '' ?>>
              <?= e(rotulo('tipo_avaliacao', $tipo)) ?></option>
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
        <a class="botao botao--secundario" href="<?= url('/meu-painel') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($media, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média dos meus alunos</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div>
    <div class="indicador__nota"><?= count($ranking) ?> aluno(s) no recorte</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $acertos['acertos'] ?> de <?= (int) $acertos['total'] ?> questões</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Aulas / Avaliações</div>
    <div class="indicador__valor" style="font-size:1.35rem"><?= (int) $ensino['aulas'] ?> / <?= (int) $ensino['avaliacoes'] ?></div>
    <div class="indicador__nota">nas minhas ofertas</div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Em evolução</div>
    <div class="indicador__valor"><?= (int) $classes['evolucao'] ?></div>
  </div>
  <div class="indicador indicador--medio">
    <div class="indicador__rotulo">Intermediários</div>
    <div class="indicador__valor"><?= (int) $classes['intermediario'] ?></div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Precisam de atenção</div>
    <div class="indicador__valor"><?= (int) $classes['atencao'] ?></div>
    <div class="indicador__nota"><?= (int) $classes['sem_dados'] ?> sem dados</div>
  </div>
</div>

<!-- As três perguntas do professor -->
<div class="colunas colunas--3">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Quem mais evoluiu</h2><span class="etiqueta etiqueta--bom">p.p. recentes</span></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($evoluindo === []): ?>
        <div class="vazio pequeno">Ainda não há avaliações suficientes para medir evolução.</div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach ($evoluindo as $aluno): ?>
            <li>
              <span class="nome">
                <a href="<?= url('/meu-painel/aluno/' . $aluno['id']) ?>"><?= e($aluno['full_name']) ?></a><br>
                <small class="mudo">média <?= pct($aluno['media'], 0) ?> · índice <?= num($aluno['indice']) ?></small>
              </span>
              <span class="etiqueta etiqueta--bom">+<?= num($aluno['evolucao_recente']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Quem precisa de atenção</h2><span class="etiqueta etiqueta--ruim"><?= count($atencao) ?></span></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($atencao === []): ?>
        <div class="vazio pequeno">Nenhum aluno na faixa de atenção. </div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach (array_slice($atencao, 0, 6) as $aluno): ?>
            <li>
              <span class="nome">
                <a href="<?= url('/meu-painel/aluno/' . $aluno['id']) ?>"><?= e($aluno['full_name']) ?></a><br>
                <small class="mudo"><?= e($aluno['motivos'][0] ?? '') ?></small>
              </span>
              <a class="botao botao--secundario botao--pequeno"
                 href="<?= url('/acompanhamento/novo', ['aluno' => $aluno['id'], 'titulo' => 'Acompanhamento de ' . $aluno['full_name']]) ?>">Acompanhar</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Quem mais deixou pontuação</h2><span class="etiqueta etiqueta--medio">pontos perdidos</span></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($perda_aluno === []): ?>
        <div class="vazio pequeno">Sem resultados por questão registrados.</div>
      <?php else: ?>
        <ul class="lista-conteudo">
          <?php foreach ($perda_aluno as $aluno): ?>
            <li>
              <span class="nome">
                <a href="<?= url('/meu-painel/aluno/' . $aluno['student_id']) ?>"><?= e($aluno['full_name']) ?></a><br>
                <small class="mudo">pior em <?= e($aluno['pior_avaliacao'] ?? '—') ?></small>
              </span>
              <span class="etiqueta etiqueta--medio"><?= num($aluno['perdidos'], 1) ?> pt</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Evolução dos meus alunos</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="p-ev"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Distribuição de desempenho</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="p-dist"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Assuntos da minha disciplina</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="p-as"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho">
      <h2>Onde a turma mais perdeu pontos</h2>
      <span class="etiqueta etiqueta--neutro">por avaliação</span>
    </div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="p-perda"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Meus alunos</h2>
        <input type="search" placeholder="Filtrar aluno…" data-filtra-tabela="#tab-meus" style="width:180px;margin-left:auto">
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($ranking === []): ?>
          <div class="vazio"><span class="vazio__icone">👥</span>Nenhum aluno nas suas turmas.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela" id="tab-meus">
              <thead><tr><th>#</th><th>Aluno</th><th class="num">Aval.</th><th class="num">Média</th>
                <th class="num">Freq.</th><th class="num">Evolução</th><th class="num">Índice</th>
                <th>Situação</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($ranking as $linha): ?>
                <?php $classe = match ($linha['classificacao']) {
                    'evolucao' => 'bom', 'intermediario' => 'medio', 'atencao' => 'ruim', default => 'neutro' }; ?>
                <tr>
                  <td class="num"><?= $linha['posicao'] ?? '—' ?></td>
                  <td><a href="<?= url('/meu-painel/aluno/' . $linha['id']) ?>"><?= e($linha['full_name']) ?></a>
                    <?php if ($linha['class_code']): ?><br><small class="mudo"><?= e($linha['class_code']) ?></small><?php endif; ?></td>
                  <td class="num"><?= (int) $linha['avaliacoes'] ?></td>
                  <td class="num"><?= pct($linha['media'], 0) ?></td>
                  <td class="num"><?= pct($linha['frequencia'], 0) ?></td>
                  <td class="num">
                    <?php if ($linha['evolucao_recente'] !== null): ?>
                      <span class="etiqueta etiqueta--<?= $linha['evolucao_recente'] >= 0 ? 'bom' : 'ruim' ?>">
                        <?= $linha['evolucao_recente'] >= 0 ? '+' : '' ?><?= num($linha['evolucao_recente']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td class="num mono"><strong><?= num($linha['indice']) ?></strong></td>
                  <td><span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('classificacao', $linha['classificacao'])) ?></span></td>
                  <td class="direita nowrap">
                    <a class="botao botao--secundario botao--pequeno" href="<?= url('/meu-painel/aluno/' . $linha['id']) ?>">Painel</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="pequeno mudo" style="padding:.6rem .9rem">Pesos do índice: <?= e($pesos) ?>.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Avaliações com maior perda de pontos</h2>
        <a class="pequeno" href="<?= url('/avaliacoes') ?>">todas</a>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($perda_avaliacao === []): ?>
          <div class="vazio pequeno">Sem resultados por questão registrados.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Avaliação</th><th>Turma</th><th class="num">Alunos</th>
                <th class="num">Pontos perdidos</th><th class="num">Erros</th><th>Aproveitamento</th></tr></thead>
              <tbody>
              <?php foreach ($perda_avaliacao as $item): ?>
                <tr class="linha-clicavel" onclick="location.href='<?= e(url('/avaliacoes/' . $item['assessment_id'])) ?>'">
                  <td><?= e($item['assessment_name']) ?><div class="mudo pequeno"><?= data_br($item['assessment_date']) ?></div></td>
                  <td class="pequeno"><?= e($item['class_code']) ?></td>
                  <td class="num"><?= (int) $item['alunos'] ?></td>
                  <td class="num mono"><strong><?= num($item['pontos_perdidos'], 1) ?></strong>
                    <div class="mudo pequeno">de <?= num($item['pontos_possiveis'], 1) ?></div></td>
                  <td class="num"><?= (int) $item['erros'] ?></td>
                  <td style="min-width:140px">
                    <span class="progresso-linha">
                      <span class="progresso"><span class="progresso__barra progresso__barra--<?= faixa_classe($item['aproveitamento'], $faixas['dominio'], $faixas['intermediario']) ?>"
                        style="width:<?= (float) ($item['aproveitamento'] ?? 0) ?>%"></span></span>
                      <span><?= pct($item['aproveitamento'], 0) ?></span>
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
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Alertas dos meus alunos</h2>
        <span class="etiqueta etiqueta--ruim"><?= count(array_filter($alertas, static fn ($a) => $a['severity'] === 'alta')) ?> alta</span></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($alertas === []): ?>
          <div class="vazio pequeno">Nenhum alerta ativo.</div>
        <?php else: foreach (array_slice($alertas, 0, 8) as $alerta): ?>
          <div class="alerta alerta--<?= e($alerta['severity']) ?>">
            <div class="alerta__marca"></div>
            <div class="alerta__corpo">
              <div class="alerta__titulo"><?= e($alerta['title']) ?></div>
              <div class="alerta__texto pequeno"><?= e($alerta['message']) ?></div>
              <?php if ($alerta['student_id']): ?>
                <a class="pequeno" href="<?= url('/acompanhamento/novo', ['aluno' => $alerta['student_id'], 'alerta' => $alerta['key'], 'titulo' => $alerta['title']]) ?>">
                  registrar acompanhamento →</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Acompanhamentos abertos</h2>
        <a class="pequeno" href="<?= url('/acompanhamento') ?>">todos</a></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($acompanhamentos === []): ?>
          <div class="vazio pequeno">Nenhum acompanhamento em aberto.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($acompanhamentos as $item): ?>
              <li>
                <span class="nome"><?= e($item['title']) ?><br>
                  <small class="mudo"><?= e($item['student_name']) ?>
                    <?= $item['due_date'] ? ' · prazo ' . data_br($item['due_date']) : '' ?></small></span>
                <span class="etiqueta etiqueta--<?= $item['status'] === 'aberta' ? 'medio' : 'info' ?>">
                  <?= e(\App\Models\Intervention::STATUSES[$item['status']]) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Minhas últimas aulas</h2>
        <a class="pequeno" href="<?= url('/aulas') ?>">todas</a></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($proximas_aulas === []): ?>
          <div class="vazio pequeno">Nenhuma aula registrada.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($proximas_aulas as $aula): ?>
              <li>
                <span class="nome"><a href="<?= url('/aulas/' . $aula['id'] . '/frequencia') ?>"><?= e($aula['title']) ?></a><br>
                  <small class="mudo"><?= data_br($aula['lesson_date']) ?> · <?= e($aula['class_code']) ?></small></span>
                <span class="etiqueta etiqueta--<?= (int) $aula['attendance_count'] === 0 ? 'medio' : 'bom' ?>">
                  <?= (int) $aula['attendance_count'] === 0 ? 'sem chamada' : (int) $aula['present_count'] . '/' . (int) $aula['attendance_count'] ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  Painel.linha('p-ev', <?= $j(array_map(static fn ($r) => $r['assessment_name'], $serie)) ?>,
    [{ nome: 'Média dos meus alunos (%)', dados: <?= $j(array_map(static fn ($r) => $r['media'] === null ? null : round((float) $r['media'], 2), $serie)) ?> }],
    { scales: { y: { max: 100 } } });

  Painel.rosca('p-dist', <?= $j(array_keys($distribuicao)) ?>, <?= $j(array_values($distribuicao)) ?>,
    [Painel.paleta.vermelho, Painel.paleta.ambar, Painel.paleta.azul, Painel.paleta.verde]);

  var ass = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], array_slice($assuntos, 0, 12))) ?>;
  Painel.barras('p-as', ass.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.valor; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var perda = <?= $j(array_map(static fn ($p) => ['nome' => $p['assessment_name'], 'valor' => $p['pontos_perdidos']], $perda_avaliacao)) ?>;
  Painel.barras('p-perda', perda.map(function (p) { return p.nome; }),
    [{ nome: 'Pontos perdidos', dados: perda.map(function (p) { return p.valor; }), cor: Painel.paleta.ambar }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });
});
</script>
