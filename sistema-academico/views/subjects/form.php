<?php
$editando = $disciplina !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($disciplina[$campo] ?? $padrao) : $padrao));
?>
<div class="pagina__cabecalho">
  <div><h1><?= $editando ? 'Editar disciplina' : 'Nova disciplina' ?></h1></div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url($editando ? '/disciplinas/' . $disciplina['id'] : '/disciplinas') ?>">Cancelar</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/disciplinas/' . $disciplina['id'] : '/disciplinas') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo campo--largo <?= isset($errors['name']) ? 'campo--erro' : '' ?>">
          <label for="name">Nome da disciplina *</label>
          <input type="text" id="name" name="name" required maxlength="150" placeholder="Informática" value="<?= $valor('name') ?>">
        </div>
        <div class="campo">
          <label for="teacher_user_id">Professor responsável</label>
          <select id="teacher_user_id" name="teacher_user_id">
            <option value="">— Não definido —</option>
            <?php foreach ($professores as $professor): ?>
              <option value="<?= (int) $professor['id'] ?>" <?= (string) ($editando ? $disciplina['teacher_user_id'] : '') === (string) $professor['id'] ? 'selected' : '' ?>>
                <?= e($professor['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="workload_hours">Carga horária (h)</label>
          <input type="number" id="workload_hours" name="workload_hours" min="0" value="<?= $valor('workload_hours') ?>">
        </div>
        <div class="campo">
          <label for="status">Situação *</label>
          <select id="status" name="status" required>
            <option value="ativa" <?= ($editando ? $disciplina['status'] : 'ativa') === 'ativa' ? 'selected' : '' ?>>Ativa</option>
            <option value="inativa" <?= ($editando ? $disciplina['status'] : '') === 'inativa' ? 'selected' : '' ?>>Inativa</option>
          </select>
        </div>
        <div class="campo campo--largo">
          <label for="description">Descrição / ementa</label>
          <textarea id="description" name="description" rows="4"><?= $valor('description') ?></textarea>
        </div>
      </div>
      <div class="acoes-form">
        <button class="botao" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar disciplina' ?></button>
        <a class="botao botao--secundario" href="<?= url('/disciplinas') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>
