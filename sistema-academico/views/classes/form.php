<?php
$editando = $turma !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($turma[$campo] ?? $padrao) : $padrao));
?>
<div class="pagina__cabecalho">
  <div><h1><?= $editando ? 'Editar turma' : 'Nova turma' ?></h1></div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url($editando ? '/turmas/' . $turma['id'] : '/turmas') ?>">Cancelar</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($cursos === []): ?>
  <div class="aviso aviso--warning"><span>⚠</span><div>
    Nenhum curso cadastrado. <a href="<?= url('/cursos') ?>">Cadastre um curso</a> antes de criar a turma.
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/turmas/' . $turma['id'] : '/turmas') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo <?= isset($errors['code']) ? 'campo--erro' : '' ?>">
          <label for="code">Código da turma *</label>
          <input type="text" id="code" name="code" required maxlength="32" placeholder="INF01" value="<?= $valor('code') ?>">
          <?php if (isset($errors['code'])): ?><span class="erro-campo"><?= e($errors['code'][0]) ?></span><?php endif; ?>
        </div>
        <div class="campo">
          <label for="name">Nome descritivo</label>
          <input type="text" id="name" name="name" maxlength="150" placeholder="Turma noturno 2026" value="<?= $valor('name') ?>">
        </div>
        <div class="campo">
          <label for="course_id">Curso *</label>
          <select id="course_id" name="course_id" required>
            <option value="">Selecione…</option>
            <?php foreach ($cursos as $curso): ?>
              <option value="<?= (int) $curso['id'] ?>" <?= (string) ($editando ? $turma['course_id'] : old($old ?? [], 'course_id')) === (string) $curso['id'] ? 'selected' : '' ?>>
                <?= e($curso['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="year">Ano *</label>
          <input type="number" id="year" name="year" required min="2000" max="2100" value="<?= $valor('year', date('Y')) ?>">
        </div>
        <div class="campo">
          <label for="period">Período</label>
          <input type="text" id="period" name="period" maxlength="40" placeholder="Noturno / 1º semestre" value="<?= $valor('period') ?>">
        </div>
        <div class="campo">
          <label for="status">Situação *</label>
          <select id="status" name="status" required>
            <?php foreach (['planejada', 'em_andamento', 'concluida', 'cancelada'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editando ? $turma['status'] : old($old ?? [], 'status', 'em_andamento')) === $s ? 'selected' : '' ?>>
                <?= e(rotulo('status_turma', $s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="start_date">Data de início</label>
          <input type="date" id="start_date" name="start_date" value="<?= $valor('start_date') ?>">
        </div>
        <div class="campo">
          <label for="end_date">Data de término</label>
          <input type="date" id="end_date" name="end_date" value="<?= $valor('end_date') ?>">
        </div>
      </div>

      <div class="acoes-form">
        <button class="botao" type="submit" <?= $cursos === [] ? 'disabled' : '' ?>><?= $editando ? 'Salvar alterações' : 'Criar turma' ?></button>
        <a class="botao botao--secundario" href="<?= url('/turmas') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>
