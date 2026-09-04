<?php
$editando = $aula !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($aula[$campo] ?? $padrao) : $padrao));
$ofertaAtual = $editando ? (int) $aula['class_subject_id'] : (int) old($old ?? [], 'class_subject_id', 0);
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= $editando ? 'Editar aula' : 'Nova aula' ?></h1>
    <p class="mudo mb-0">Marque os tópicos abordados: é isso que conecta a aula à análise de conteúdos.</p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/aulas') ?>">Cancelar</a></div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($ofertas === []): ?>
  <div class="aviso aviso--warning"><span>⚠</span><div>
    Nenhuma disciplina está vinculada a uma turma. <a href="<?= url('/turmas') ?>">Vincule uma disciplina a uma turma</a> primeiro.
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/aulas/' . $aula['id'] : '/aulas') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo">
          <label for="class_subject_id">Turma / Disciplina *</label>
          <select id="class_subject_id" name="class_subject_id" required>
            <option value="">Selecione…</option>
            <?php foreach ($ofertas as $oferta): ?>
              <option value="<?= (int) $oferta['id'] ?>" <?= $ofertaAtual === (int) $oferta['id'] ? 'selected' : '' ?>>
                <?= e($oferta['class_code']) ?> — <?= e($oferta['subject_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="lesson_date">Data *</label>
          <input type="date" id="lesson_date" name="lesson_date" required value="<?= $valor('lesson_date', date('Y-m-d')) ?>">
        </div>
        <div class="campo">
          <label for="duration_minutes">Duração (min)</label>
          <input type="number" id="duration_minutes" name="duration_minutes" min="0" max="1440" value="<?= $valor('duration_minutes') ?>">
        </div>
        <div class="campo campo--largo">
          <label for="title">Título da aula *</label>
          <input type="text" id="title" name="title" required maxlength="200" value="<?= $valor('title') ?>">
        </div>
        <div class="campo campo--largo">
          <label for="content">Conteúdo ministrado</label>
          <textarea id="content" name="content" rows="4"><?= $valor('content') ?></textarea>
        </div>
        <div class="campo campo--largo">
          <label for="materials">Materiais de apoio</label>
          <textarea id="materials" name="materials" rows="2" placeholder="Links, apostilas, slides…"><?= $valor('materials') ?></textarea>
        </div>
        <div class="campo campo--largo">
          <label for="notes">Observações</label>
          <textarea id="notes" name="notes" rows="2"><?= $valor('notes') ?></textarea>
        </div>
      </div>

      <fieldset class="mt-3">
        <legend>Tópicos abordados</legend>
        <?php if ($topicos === []): ?>
          <p class="pequeno mudo mb-0">Nenhum conteúdo cadastrado. Cadastre assuntos nas disciplinas.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.3rem;max-height:260px;overflow-y:auto">
            <?php
            $disciplinaAtual = null;
            foreach ($topicos as $topico):
              if ($disciplinaAtual !== $topico['subject_name']):
                  $disciplinaAtual = $topico['subject_name'];
            ?>
              <div style="grid-column:1/-1;font-size:.76rem;font-weight:650;color:var(--cinza-600);text-transform:uppercase;margin-top:.4rem"><?= e($disciplinaAtual) ?></div>
            <?php endif; ?>
              <label class="checkbox">
                <input type="checkbox" name="topics[]" value="<?= (int) $topico['id'] ?>"
                       <?= in_array((int) $topico['id'], $selecionados, true) ? 'checked' : '' ?>>
                <?= $topico['parent_id'] ? '— ' : '' ?><?= e($topico['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </fieldset>

      <div class="acoes-form">
        <button class="botao" type="submit" <?= $ofertas === [] ? 'disabled' : '' ?>><?= $editando ? 'Salvar aula' : 'Registrar aula e fazer chamada' ?></button>
        <a class="botao botao--secundario" href="<?= url('/aulas') ?>">Cancelar</a>
        <?php if ($editando): ?>
          <span style="margin-left:auto"></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<?php if ($editando): ?>
  <form method="post" action="<?= url('/aulas/' . $aula['id'] . '/excluir') ?>" data-confirmar="Excluir esta aula e a chamada correspondente?">
    <?= csrf_field() ?>
    <button class="botao botao--perigo botao--pequeno" type="submit">Excluir aula</button>
  </form>
<?php endif; ?>
