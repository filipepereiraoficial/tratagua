<?php
$j = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= e($disciplina['name']) ?></h1>
    <p class="mudo mb-0">
      <?= $disciplina['teacher_name'] ? 'Prof. ' . e($disciplina['teacher_name']) : 'Sem professor definido' ?>
      <?= $disciplina['workload_hours'] ? ' · ' . (int) $disciplina['workload_hours'] . 'h' : '' ?>
      · <?= count($turmas) ?> turma(s)
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/questoes', ['disciplina' => $disciplina['id']]) ?>">Banco de questões</a>
    <a class="botao botao--secundario" href="<?= url('/relatorios', ['relatorio' => 'assunto', 'disciplina' => $disciplina['id']]) ?>">Relatório por assunto</a>
    <a class="botao" href="<?= url('/disciplinas/' . $disciplina['id'] . '/editar') ?>">Editar</a>
  </div>
</div>

<div class="indicadores">
  <div class="indicador indicador--<?= faixa_classe($media, $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">Média da disciplina</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos'], $faixas['dominio'], $faixas['intermediario']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $acertos['total'] ?> resposta(s)</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Assuntos mapeados</div>
    <div class="indicador__valor"><?= count($arvore) ?></div>
    <div class="indicador__nota">com tópicos vinculados</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Aulas / Avaliações</div>
    <div class="indicador__valor"><?= count($aulas) ?> / <?= count($avaliacoes) ?></div>
    <div class="indicador__nota">últimos registros</div>
  </div>
</div>

<div class="graficos">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Aproveitamento por assunto</h2></div>
    <div class="cartao__corpo"><div class="grafico grafico--alto"><canvas id="g-assuntos"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Desempenho por turma</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-turmas"></canvas></div></div>
  </div>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Aproveitamento por nível de dificuldade</h2></div>
    <div class="cartao__corpo"><div class="grafico"><canvas id="g-dificuldade"></canvas></div></div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <!-- Árvore de conteúdos -->
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Assuntos e tópicos</h2>
        <span class="etiqueta etiqueta--neutro">base da análise de dificuldades</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($arvore === []): ?>
          <div class="vazio"><span class="vazio__icone">🗂</span>
            Nenhum conteúdo cadastrado. Cadastre os assuntos ao lado — é o que permite
            identificar automaticamente em que o aluno tem dificuldade.
          </div>
        <?php else: ?>
          <?php foreach ($arvore as $assunto): ?>
            <div style="border-bottom:1px solid var(--cinza-200);padding:.7rem .9rem">
              <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
                <strong><?= e($assunto['name']) ?></strong>
                <span class="etiqueta etiqueta--info">assunto</span>
                <span style="margin-left:auto;display:flex;gap:.3rem">
                  <form method="post" action="<?= url('/assuntos/' . $assunto['id'] . '/excluir') ?>"
                        data-confirmar="Excluir este assunto e seus tópicos?">
                    <?= csrf_field() ?>
                    <button class="botao botao--secundario botao--pequeno" type="submit">✕</button>
                  </form>
                </span>
              </div>
              <?php if ($assunto['description']): ?>
                <div class="pequeno mudo"><?= e($assunto['description']) ?></div>
              <?php endif; ?>
              <?php if ($assunto['children']): ?>
                <ul style="margin:.4rem 0 0;padding-left:1.1rem" class="pequeno">
                  <?php foreach ($assunto['children'] as $topico): ?>
                    <li style="display:flex;align-items:center;gap:.4rem;padding:.15rem 0">
                      <span><?= e($topico['name']) ?></span>
                      <form method="post" action="<?= url('/assuntos/' . $topico['id'] . '/excluir') ?>"
                            data-confirmar="Excluir este tópico?" style="margin-left:auto">
                        <?= csrf_field() ?>
                        <button class="botao botao--secundario botao--pequeno" type="submit">✕</button>
                      </form>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Desempenho por assunto -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Aproveitamento medido</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($assuntos === []): ?>
          <div class="vazio pequeno">Sem respostas registradas para esta disciplina.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>Assunto</th><th class="num">Alunos</th><th class="num">Respostas</th><th class="num">Erros</th><th>Aproveitamento</th></tr></thead>
              <tbody>
              <?php foreach ($assuntos as $assunto): ?>
                <tr>
                  <td><?= e($assunto['topic_name']) ?></td>
                  <td class="num"><?= (int) $assunto['alunos'] ?></td>
                  <td class="num"><?= (int) $assunto['respondidas'] ?></td>
                  <td class="num"><?= (int) $assunto['erros'] ?></td>
                  <td style="min-width:150px">
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
    <!-- Novo conteúdo -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Cadastrar conteúdo</h2></div>
      <div class="cartao__corpo">
        <form method="post" action="<?= url('/disciplinas/' . $disciplina['id'] . '/assuntos') ?>">
          <?= csrf_field() ?>
          <div class="campo mb-2">
            <label for="name">Nome *</label>
            <input type="text" id="name" name="name" required maxlength="150" placeholder="Redes de Computadores">
          </div>
          <div class="campo mb-2">
            <label for="parent_id">Vincular a um assunto</label>
            <select id="parent_id" name="parent_id">
              <option value="">— É um assunto principal —</option>
              <?php foreach ($arvore as $assunto): ?>
                <option value="<?= (int) $assunto['id'] ?>"><?= e($assunto['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="ajuda">Deixe vazio para criar um assunto; selecione para criar um tópico dentro dele.</span>
          </div>
          <div class="campo mb-2">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="2"></textarea>
          </div>
          <button class="botao botao--bloco botao--pequeno" type="submit">Cadastrar</button>
        </form>
      </div>
    </div>

    <!-- Turmas -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Turmas que ofertam</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($turmas === []): ?>
          <div class="vazio pequeno">Nenhuma turma vinculada ainda.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($turmas as $t): ?>
              <li>
                <span class="nome">
                  <a href="<?= url('/turmas/' . $t['id']) ?>"><?= e($t['code']) ?></a><br>
                  <small class="mudo"><?= e($t['course_name']) ?> · <?= (int) $t['students_count'] ?> aluno(s)</small>
                </span>
                <span class="etiqueta etiqueta--neutro"><?= (int) $t['year'] ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Últimas aulas -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Últimas aulas</h2><a class="pequeno" href="<?= url('/aulas', ['disciplina' => $disciplina['id']]) ?>">ver todas</a></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($aulas === []): ?>
          <div class="vazio pequeno">Nenhuma aula registrada.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($aulas as $aula): ?>
              <li>
                <span class="nome"><?= e($aula['title']) ?><br><small class="mudo"><?= data_br($aula['lesson_date']) ?> · <?= e($aula['class_code']) ?></small></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- Últimas avaliações -->
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Últimas avaliações</h2><a class="pequeno" href="<?= url('/avaliacoes', ['disciplina' => $disciplina['id']]) ?>">ver todas</a></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($avaliacoes === []): ?>
          <div class="vazio pequeno">Nenhuma avaliação registrada.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach ($avaliacoes as $avaliacao): ?>
              <li>
                <span class="nome">
                  <a href="<?= url('/avaliacoes/' . $avaliacao['id']) ?>"><?= e($avaliacao['name']) ?></a><br>
                  <small class="mudo"><?= data_br($avaliacao['assessment_date']) ?> · <?= e($avaliacao['class_code']) ?></small>
                </span>
                <span class="etiqueta etiqueta--<?= faixa_classe($avaliacao['avg_percentage'] !== null ? (float) $avaliacao['avg_percentage'] : null, $faixas['dominio'], $faixas['intermediario']) ?>">
                  <?= $avaliacao['avg_percentage'] !== null ? pct($avaliacao['avg_percentage'], 0) : '—' ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($blockers === []): ?>
      <div class="cartao">
        <div class="cartao__cabecalho"><h2>Excluir disciplina</h2></div>
        <div class="cartao__corpo">
          <form method="post" action="<?= url('/disciplinas/' . $disciplina['id'] . '/excluir') ?>" data-confirmar="Excluir esta disciplina?">
            <?= csrf_field() ?>
            <button class="botao botao--perigo botao--pequeno" type="submit">Excluir disciplina</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
window.addEventListener('load', function () {
  var faixas = <?= $j($faixas) ?>;

  var ass = <?= $j(array_map(static fn ($a) => ['nome' => $a['topic_name'], 'valor' => $a['aproveitamento']], array_slice($assuntos, 0, 18))) ?>;
  Painel.barras('g-assuntos', ass.map(function (a) { return a.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: ass.map(function (a) { return a.valor; }),
       cores: ass.map(function (a) { return Painel.corPorFaixa(a.valor, faixas.dominio, faixas.intermediario); }) }],
    { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { max: 100 }, y: { ticks: { autoSkip: false, font: { size: 10 } } } } });

  var turmas = <?= $j(array_map(static fn ($r) => ['nome' => $r['class_code'], 'valor' => $r['media'] === null ? null : round((float) $r['media'], 2)], $por_turma)) ?>;
  Painel.barras('g-turmas', turmas.map(function (t) { return t.nome; }),
    [{ nome: 'Média (%)', dados: turmas.map(function (t) { return t.valor; }), cor: Painel.paleta.azul }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });

  var dif = <?= $j(array_map(static fn ($d) => ['nome' => rotulo('dificuldade', $d['dificuldade']), 'valor' => $d['aproveitamento']], $dificuldade)) ?>;
  Painel.barras('g-dificuldade', dif.map(function (d) { return d.nome; }),
    [{ nome: 'Aproveitamento (%)', dados: dif.map(function (d) { return d.valor; }),
       cores: [Painel.paleta.verde, Painel.paleta.ambar, Painel.paleta.vermelho] }],
    { plugins: { legend: { display: false } }, scales: { y: { max: 100 } } });
});
</script>
