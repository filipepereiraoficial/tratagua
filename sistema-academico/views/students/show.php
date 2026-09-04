<?php
/** Dashboard individual de aprendizagem. */
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$notas = $resumo['notas'];
$classeSituacao = match ($resumo['classificacao']) {
    'evolucao' => 'bom', 'intermediario' => 'medio', 'atencao' => 'ruim', default => 'neutro',
};
?>
<div class="pagina__cabecalho">
  <div style="display:flex;gap:.9rem;align-items:center">
    <span class="avatar" style="width:48px;height:48px;font-size:1rem"><?= e(iniciais($aluno['full_name'])) ?></span>
    <div>
      <h1 style="margin-bottom:.15rem"><?= e($aluno['full_name']) ?></h1>
      <p class="mudo mb-0 pequeno">
        <?= $aluno['class_code'] ? e($aluno['class_code']) . ' · ' . e($aluno['course_name']) : 'Sem turma vinculada' ?>
        · <span class="etiqueta etiqueta--<?= $classeSituacao ?>"><?= e(rotulo('classificacao', $resumo['classificacao'])) ?></span>
      </p>
    </div>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/relatorios', ['relatorio' => 'aluno', 'aluno' => $aluno['id']]) ?>">Relatório</a>
    <a class="botao botao--secundario" href="<?= url('/comparacao', ['modo' => 'aluno_turma', 'a' => $aluno['id']]) ?>">Comparar com a turma</a>
    <a class="botao" href="<?= url('/alunos/' . $aluno['id'] . '/editar') ?>">Editar</a>
  </div>
</div>

<?php if ($resumo['motivos']): ?>
  <div class="aviso aviso--<?= $resumo['classificacao'] === 'atencao' ? 'warning' : 'info' ?>">
    <span>ℹ</span>
    <div>
      <strong>Por que este aluno está classificado como “<?= e(rotulo('classificacao', $resumo['classificacao'])) ?>”:</strong>
      <?= e(implode(' ', $resumo['motivos'])) ?>
    </div>
  </div>
<?php endif; ?>

<!-- Filtros -->
<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/alunos/' . $aluno['id']) ?>" data-auto-filtro>
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
        <a class="botao botao--secundario" href="<?= url('/alunos/' . $aluno['id']) ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<!-- Indicadores -->
<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($resumo['media'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média geral</div>
    <div class="indicador__valor"><?= $resumo['media'] !== null ? pct($resumo['media']) : '—' ?></div>
    <div class="indicador__nota">
      <?php if ($media_turma !== null && $resumo['media'] !== null): ?>
        <?php $delta = $resumo['media'] - $media_turma; ?>
        <?= $delta >= 0 ? '▲' : '▼' ?> <?= num(abs($delta)) ?> p.p. vs. turma (<?= pct($media_turma, 0) ?>)
      <?php else: ?>Sem comparativo de turma<?php endif; ?>
    </div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['acertos']['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $resumo['acertos']['pct_acertos'] !== null ? pct($resumo['acertos']['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $resumo['acertos']['acertos'] ?> de <?= (int) $resumo['acertos']['total'] ?> questões</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">% de erros</div>
    <div class="indicador__valor"><?= $resumo['acertos']['pct_erros'] !== null ? pct($resumo['acertos']['pct_erros']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $resumo['acertos']['branco'] ?> em branco</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Avaliações realizadas</div>
    <div class="indicador__valor"><?= (int) $resumo['avaliacoes'] ?></div>
    <div class="indicador__nota">Desvio: <?= $resumo['desvio'] !== null ? num($resumo['desvio']) . ' p.p.' : '—' ?></div>
  </div>
  <div class="indicador indicador--<?= $resumo['evolucao_recente'] === null ? 'neutro' : ($resumo['evolucao_recente'] >= 0 ? 'bom' : 'ruim') ?>">
    <div class="indicador__rotulo">Evolução recente</div>
    <div class="indicador__valor"><?= $resumo['evolucao_recente'] !== null ? num($resumo['evolucao_recente']) . ' p.p.' : '—' ?></div>
    <div class="indicador__nota">Tendência: <?= $resumo['evolucao_slope'] !== null ? num($resumo['evolucao_slope'], 2) . ' p.p./aval.' : '—' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($resumo['frequencia'], 90, 75) ?>">
    <div class="indicador__rotulo">Frequência</div>
    <div class="indicador__valor"><?= $resumo['frequencia'] !== null ? pct($resumo['frequencia']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $resumo['presenca']['faltas'] ?> falta(s) em <?= (int) $resumo['presenca']['aulas'] ?> aula(s)</div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Conteúdos dominados</div>
    <div class="indicador__valor"><?= (int) $resumo['dominados'] ?></div>
    <div class="indicador__nota"><?= (int) $resumo['intermediarios'] ?> intermediário(s)</div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Conteúdos com dificuldade</div>
    <div class="indicador__valor"><?= (int) $resumo['dificuldades'] ?></div>
    <div class="indicador__nota">Abaixo de <?= num($faixas['intermediario'], 0) ?>% de aproveitamento</div>
  </div>
  <div class="indicador indicador--roxo">
    <div class="indicador__rotulo">Índice de Desenvolvimento</div>
    <div class="indicador__valor"><?= $resumo['indice'] !== null ? num($resumo['indice']) : '—' ?></div>
    <div class="indicador__nota">
      <?= $posicao['posicao'] ? 'Posição ' . (int) $posicao['posicao'] . ' de ' . (int) $posicao['total'] . ' na turma' : 'Sem ranking' ?>
    </div>
  </div>
</div>

<!-- Gráficos -->
<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Evolução das notas</h2><span class="etiqueta etiqueta--info">aluno × turma</span></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-evolucao"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Acertos e erros por avaliação</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-acertos"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Desempenho por disciplina</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-disciplinas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Desempenho por nível de dificuldade</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-dificuldade"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Desempenho por assunto</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="g-assuntos"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Frequência ao longo do tempo</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-frequencia"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Perfil de desenvolvimento</h2><span class="etiqueta etiqueta--roxo">componentes do índice</span></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-perfil"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <!-- Conteúdos -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Mapa de conteúdos</h2>
        <span class="etiqueta etiqueta--bom"><?= count($dominados) ?> domínio</span>
        <span class="etiqueta etiqueta--medio"><?= count($intermediarios) ?> intermediário</span>
        <span class="etiqueta etiqueta--ruim"><?= count($dificuldades) ?> dificuldade</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($resumo['assuntos'] === []): ?>
          <div class="vazio"><span class="vazio__icone">📚</span>
            Nenhum resultado por questão registrado ainda. Lance os resultados de uma avaliação
            marcando acerto/erro por questão para habilitar esta análise.
          </div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Assunto</th><th>Disciplina</th><th class="num">Questões</th><th>Aproveitamento</th><th>Situação</th></tr></thead>
              <tbody>
              <?php foreach ($resumo['assuntos'] as $assunto): ?>
                <tr>
                  <td><?= e($assunto['topic_name']) ?></td>
                  <td class="pequeno mudo"><?= e($assunto['subject_name']) ?></td>
                  <td class="num"><?= (int) $assunto['respondidas'] ?></td>
                  <td style="min-width:150px">
                    <span class="progresso-linha">
                      <span class="progresso">
                        <span class="progresso__barra progresso__barra--<?= faixa_classe($assunto['aproveitamento'], $faixas['dominio'], $faixas['intermediario']) ?>"
                              style="width:<?= (float) ($assunto['aproveitamento'] ?? 0) ?>%"></span>
                      </span>
                      <span><?= $assunto['aproveitamento'] !== null ? pct($assunto['aproveitamento'], 0) : '—' ?></span>
                    </span>
                  </td>
                  <td>
                    <?php
                    [$classe, $texto] = match ($assunto['classificacao']) {
                        'dominio'       => ['bom', 'Domínio'],
                        'intermediario' => ['medio', 'Intermediário'],
                        'dificuldade'   => ['ruim', 'Dificuldade'],
                        default         => ['neutro', 'Amostra insuficiente'],
                    };
                    ?>
                    <span class="etiqueta etiqueta--<?= $classe ?>"><?= $texto ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Histórico de avaliações -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Histórico de avaliações</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($notas === []): ?>
          <div class="vazio"><span class="vazio__icone">📝</span>Nenhuma avaliação registrada neste recorte.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Data</th><th>Avaliação</th><th>Disciplina</th><th>Tipo</th><th class="num">Nota</th><th class="num">%</th><th class="num">A/E/B</th></tr></thead>
              <tbody>
              <?php foreach (array_reverse($notas) as $nota): ?>
                <tr>
                  <td class="nowrap"><?= data_br($nota['assessment_date']) ?></td>
                  <td><a href="<?= url('/avaliacoes/' . $nota['assessment_id']) ?>"><?= e($nota['assessment_name']) ?></a></td>
                  <td class="pequeno mudo"><?= e($nota['subject_name']) ?></td>
                  <td class="pequeno"><?= e(rotulo('tipo_avaliacao', $nota['type'])) ?></td>
                  <td class="num"><?= num($nota['score'], 2) ?> / <?= num($nota['max_score'], 1) ?></td>
                  <td class="num">
                    <span class="etiqueta etiqueta--<?= faixa_classe((float) $nota['percentage'], $faixas['dominio'], $faixas['intermediario']) ?>">
                      <?= pct($nota['percentage'], 0) ?>
                    </span>
                  </td>
                  <td class="num pequeno"><?= (int) $nota['correct_count'] ?>/<?= (int) $nota['wrong_count'] ?>/<?= (int) $nota['blank_count'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Frequência -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Registro de presença</h2>
        <span class="etiqueta etiqueta--neutro"><?= (int) $resumo['presenca']['presentes'] ?> presença(s)</span>
        <span class="etiqueta etiqueta--medio"><?= (int) $resumo['presenca']['atrasos'] ?> atraso(s)</span>
        <span class="etiqueta etiqueta--ruim"><?= (int) $resumo['presenca']['faltas'] ?> falta(s)</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($presencas === []): ?>
          <div class="vazio"><span class="vazio__icone">🗓</span>Nenhuma chamada registrada.</div>
        <?php else: ?>
          <div class="tabela-rolagem" style="max-height:340px;overflow-y:auto">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Data</th><th>Aula</th><th>Disciplina</th><th>Situação</th><th class="num">Participação</th></tr></thead>
              <tbody>
              <?php foreach ($presencas as $presenca): ?>
                <tr>
                  <td class="nowrap"><?= data_br($presenca['lesson_date']) ?></td>
                  <td><?= e($presenca['title']) ?></td>
                  <td class="pequeno mudo"><?= e($presenca['subject_name']) ?></td>
                  <td>
                    <?php $classe = match ($presenca['status']) {
                        'presente' => 'bom', 'atraso' => 'medio', 'falta' => 'ruim', default => 'neutro',
                    }; ?>
                    <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('presenca', $presenca['status'])) ?></span>
                  </td>
                  <td class="num"><?= $presenca['participation'] !== null ? (int) $presenca['participation'] . '/5' : '—' ?></td>
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
    <!-- Alertas do aluno -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Alertas</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($alertas === []): ?>
          <div class="vazio"><span class="vazio__icone">✓</span>Nenhum alerta ativo.</div>
        <?php else: foreach ($alertas as $alerta): ?>
          <div class="alerta alerta--<?= e($alerta['severity']) ?>">
            <div class="alerta__marca"></div>
            <div class="alerta__corpo">
              <div class="alerta__titulo"><?= e($alerta['title']) ?></div>
              <div class="alerta__texto"><?= e($alerta['message']) ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Vínculo com turma -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Turma</h2></div>
      <div class="cartao__corpo">
        <form method="post" action="<?= url('/alunos/' . $aluno['id'] . '/turma') ?>">
          <?= csrf_field() ?>
          <div class="campo mb-2">
            <label for="class_id">Turma atual</label>
            <select id="class_id" name="class_id">
              <option value="0">— Sem turma —</option>
              <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= (string) ($aluno['class_id'] ?? '') === (string) $turma['id'] ? 'selected' : '' ?>>
                  <?= e($turma['code']) ?> (<?= (int) $turma['year'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="botao botao--pequeno" type="submit">Atualizar vínculo</button>
        </form>

        <?php if (count($vinculos) > 0): ?>
          <h3 class="mt-3" style="font-size:.85rem">Histórico de turmas</h3>
          <ul class="lista-conteudo">
            <?php foreach ($vinculos as $vinculo): ?>
              <li>
                <span class="nome">
                  <?= e($vinculo['code']) ?> · <small class="mudo"><?= e($vinculo['course_name']) ?></small><br>
                  <small class="mudo"><?= data_br($vinculo['started_at']) ?> — <?= $vinculo['ended_at'] ? data_br($vinculo['ended_at']) : 'atual' ?></small>
                </span>
                <span class="etiqueta etiqueta--<?= $vinculo['is_current'] ? 'bom' : 'neutro' ?>"><?= e(rotulo('vinculo', $vinculo['status'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Dados cadastrais -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Dados cadastrais</h2></div>
      <div class="cartao__corpo pequeno">
        <p class="mb-1"><strong>CPF/ID:</strong> <?= $aluno['document'] ? e($aluno['document']) : '—' ?></p>
        <p class="mb-1"><strong>E-mail:</strong> <?= $aluno['email'] ? e($aluno['email']) : '—' ?></p>
        <p class="mb-1"><strong>Telefone:</strong> <?= $aluno['phone'] ? e($aluno['phone']) : '—' ?></p>
        <p class="mb-1"><strong>Nascimento:</strong> <?= data_br($aluno['birth_date']) ?></p>
        <p class="mb-1"><strong>Cadastro:</strong> <?= data_br($aluno['enrolled_at']) ?></p>
        <p class="mb-1"><strong>Situação:</strong> <?= e(rotulo('status_aluno', $aluno['status'])) ?></p>
        <?php if ($aluno['notes']): ?>
          <hr style="border:0;border-top:1px solid var(--cinza-200);margin:.7rem 0">
          <strong>Observações pedagógicas</strong>
          <p class="mudo" style="white-space:pre-line"><?= e($aluno['notes']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$labels     = array_map(static fn ($n) => $n['assessment_name'], $notas);
$percentuais = array_map(static fn ($n) => (float) $n['percentage'], $notas);
$serieTurmaMap = [];
foreach ($serie_turma as $item) {
    $serieTurmaMap[(int) $item['assessment_id']] = $item['media'] === null ? null : round((float) $item['media'], 2);
}
$turmaAlinhada = array_map(static fn ($n) => $serieTurmaMap[(int) $n['assessment_id']] ?? null, $notas);
$acertosSerie = array_map(static function ($n) {
    $total = (int) $n['correct_count'] + (int) $n['wrong_count'] + (int) $n['blank_count'];
    return $total > 0 ? round((int) $n['correct_count'] / $total * 100, 2) : null;
}, $notas);
$errosSerie = array_map(static function ($n) {
    $total = (int) $n['correct_count'] + (int) $n['wrong_count'] + (int) $n['blank_count'];
    return $total > 0 ? round((int) $n['wrong_count'] / $total * 100, 2) : null;
}, $notas);

// Frequência acumulada ao longo do tempo (ordem cronológica).
$freqLabels = [];
$freqValores = [];
$acumPresenca = 0; $acumTotal = 0;
foreach (array_reverse($presencas) as $registro) {
    if ($registro['status'] === 'falta_justificada') { continue; }
    $acumTotal++;
    $acumPresenca += $registro['status'] === 'presente' ? 1 : ($registro['status'] === 'atraso' ? 0.5 : 0);
    $freqLabels[]  = data_br($registro['lesson_date']);
    $freqValores[] = round($acumPresenca / $acumTotal * 100, 2);
}
?>
<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  Painel.linha('g-evolucao', <?= $j($labels) ?>, [
    { nome: <?= $j($aluno['full_name']) ?>, dados: <?= $j($percentuais) ?> },
    { nome: 'Média da turma', dados: <?= $j($turmaAlinhada) ?>, cor: Painel.paleta.cinza }
  ], { scales: { y: { max: 100 } } });

  Painel.barras('g-acertos', <?= $j($labels) ?>, [
    { nome: '% de acertos', dados: <?= $j($acertosSerie) ?>, cor: Painel.paleta.verde },
    { nome: '% de erros', dados: <?= $j($errosSerie) ?>, cor: Painel.paleta.vermelho }
  ], { scales: { y: { max: 100 } } });

  var disc = <?= $j(array_map(static fn ($d) => ['nome' => $d['subject_name'], 'valor' => $d['media'] === null ? null : round((float) $d['media'], 2)], $por_disciplina)) ?>;
  Painel.barras('g-disciplinas', disc.map(function (d) { return d.nome; }),
    [{ nome: 'Média (%)', dados: disc.map(function (d) { return d.valor; }),
       cores: disc.map(function (d) { return Painel.corPorFaixa(d.valor, faixas.dominio, faixas.intermediario); }) }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var dif = <?= $j(array_map(static fn ($d) => ['nome' => rotulo('dificuldade', $d['dificuldade']), 'valor' => $d['aproveitamento']], $por_dificuldade)) ?>;
  Painel.barras('g-dificuldade', dif.map(function (d) { return d.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: dif.map(function (d) { return d.valor; }),
       cores: [Painel.paleta.verde, Painel.paleta.ambar, Painel.paleta.vermelho] }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var assuntos = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], array_slice($resumo['assuntos'], 0, 18))) ?>;
  Painel.barras('g-assuntos', assuntos.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: assuntos.map(function (a) { return a.valor; }),
       cores: assuntos.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  Painel.linha('g-frequencia', <?= $j($freqLabels) ?>,
    [{ nome: 'Frequência acumulada (%)', dados: <?= $j($freqValores) ?>, cor: Painel.paleta.azulEscuro }],
    { scales: { y: { max: 100 } } });

  Painel.radar('g-perfil', ['Desempenho', 'Evolução', 'Frequência', 'Consistência'], [{
    nome: <?= $j($aluno['full_name']) ?>,
    dados: [
      <?= $j($resumo['media'] ?? 0) ?>,
      <?= $j($resumo['score_evolucao']) ?>,
      <?= $j($resumo['frequencia'] ?? 0) ?>,
      <?= $j($resumo['score_consistencia']) ?>
    ]
  }]);
});
</script>
