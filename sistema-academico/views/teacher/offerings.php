<div class="pagina__cabecalho">
  <div>
    <h1>Minhas turmas e disciplinas</h1>
    <p class="mudo mb-0">Cada linha é uma oferta: uma disciplina dentro de uma turma. Os atalhos levam direto ao trabalho do dia.</p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/meu-painel') ?>">Meu painel</a></div>
</div>

<?php if ($ofertas === []): ?>
  <div class="cartao"><div class="cartao__corpo">
    <div class="vazio"><span class="vazio__icone">🧭</span>
      Você ainda não responde por nenhuma turma. Peça o vínculo ao administrador.
    </div>
  </div></div>
<?php else: ?>
  <div class="colunas colunas--2">
    <?php foreach ($ofertas as $oferta): $d = $desempenho[(int) $oferta['id']] ?? ['media' => null, 'acertos' => null]; ?>
      <div class="cartao">
        <div class="cartao__cabecalho">
          <h2><?= e($oferta['class_code']) ?> — <?= e($oferta['subject_name']) ?></h2>
          <span class="etiqueta etiqueta--<?= faixa_classe($d['media'], $faixas['dominio'], $faixas['intermediario']) ?>">
            média <?= pct($d['media'], 0) ?></span>
        </div>
        <div class="cartao__corpo">
          <p class="pequeno mudo"><?= e($oferta['course_name']) ?> · <?= (int) $oferta['year'] ?></p>
          <div class="indicadores" style="margin-bottom:.8rem">
            <div class="indicador indicador--neutro" style="border-left-width:3px">
              <div class="indicador__rotulo">Alunos</div><div class="indicador__valor" style="font-size:1.3rem"><?= (int) $oferta['students_count'] ?></div>
            </div>
            <div class="indicador indicador--neutro" style="border-left-width:3px">
              <div class="indicador__rotulo">Aulas</div><div class="indicador__valor" style="font-size:1.3rem"><?= (int) $oferta['lessons_count'] ?></div>
            </div>
            <div class="indicador indicador--neutro" style="border-left-width:3px">
              <div class="indicador__rotulo">Avaliações</div><div class="indicador__valor" style="font-size:1.3rem"><?= (int) $oferta['assessments_count'] ?></div>
            </div>
            <div class="indicador indicador--neutro" style="border-left-width:3px">
              <div class="indicador__rotulo">% acertos</div><div class="indicador__valor" style="font-size:1.3rem"><?= pct($d['acertos'], 0) ?></div>
            </div>
          </div>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a class="botao botao--pequeno" href="<?= url('/aulas/nova') ?>">+ Aula</a>
            <a class="botao botao--pequeno" href="<?= url('/avaliacoes/nova') ?>">+ Avaliação</a>
            <a class="botao botao--secundario botao--pequeno" href="<?= url('/meu-painel', ['turma' => $oferta['class_id'], 'disciplina' => $oferta['subject_id']]) ?>">Painel desta oferta</a>
            <a class="botao botao--secundario botao--pequeno" href="<?= url('/aulas', ['turma' => $oferta['class_id'], 'disciplina' => $oferta['subject_id']]) ?>">Aulas</a>
            <a class="botao botao--secundario botao--pequeno" href="<?= url('/avaliacoes', ['turma' => $oferta['class_id'], 'disciplina' => $oferta['subject_id']]) ?>">Avaliações</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
