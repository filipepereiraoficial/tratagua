<?php
$editando = $professor !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($professor[$campo] ?? $padrao) : $padrao));
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= $editando ? 'Editar professor' : 'Novo professor' ?></h1>
    <p class="mudo mb-0">O professor recebe acesso ao sistema e definirá a própria senha no primeiro login.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url($editando ? '/professores/' . $professor['id'] : '/professores') ?>">Cancelar</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/professores/' . $professor['id'] : '/professores') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Dados do professor</h2></div>
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo campo--largo">
          <label for="name">Nome completo *</label>
          <input type="text" id="name" name="name" required maxlength="150" value="<?= $valor('name') ?>">
        </div>
        <div class="campo">
          <label for="email">E-mail de acesso *</label>
          <input type="email" id="email" name="email" required maxlength="150" value="<?= $valor('email') ?>">
        </div>
        <div class="campo">
          <label for="document">CPF</label>
          <input type="text" id="document" name="document" maxlength="32" value="<?= $valor('document') ?>">
        </div>
        <div class="campo">
          <label for="phone">Telefone</label>
          <input type="tel" id="phone" name="phone" maxlength="32" value="<?= $valor('phone') ?>">
        </div>
        <div class="campo">
          <label for="qualification">Formação / titulação</label>
          <input type="text" id="qualification" name="qualification" maxlength="150"
                 placeholder="Licenciatura em Computação" value="<?= $valor('qualification') ?>">
        </div>
        <div class="campo">
          <label for="role">Perfil de acesso *</label>
          <select id="role" name="role" required>
            <option value="professor" <?= ($editando ? $professor['role'] : 'professor') === 'professor' ? 'selected' : '' ?>>
              Professor — vê apenas as turmas e disciplinas dele</option>
            <option value="admin" <?= ($editando ? $professor['role'] : '') === 'admin' ? 'selected' : '' ?>>
              Administrador — vê a instituição inteira</option>
          </select>
        </div>
        <?php if (!$editando): ?>
          <div class="campo">
            <label for="password">Senha inicial *</label>
            <input type="password" id="password" name="password" required minlength="8">
            <span class="ajuda">Mínimo de 8 caracteres. Será trocada no primeiro acesso.</span>
          </div>
        <?php else: ?>
          <div class="campo">
            <label class="checkbox">
              <input type="checkbox" name="is_active" value="1" <?= (int) $professor['is_active'] === 1 ? 'checked' : '' ?>>
              Professor ativo
            </label>
            <span class="ajuda">Inativar exige que os vínculos sejam transferidos antes.</span>
          </div>
        <?php endif; ?>
        <div class="campo campo--largo">
          <label for="notes">Observações</label>
          <textarea id="notes" name="notes" rows="3"><?= $valor('notes') ?></textarea>
        </div>
      </div>

      <div class="acoes-form">
        <button class="botao" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar professor' ?></button>
        <a class="botao botao--secundario" href="<?= url('/professores') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>
