<div class="pagina__cabecalho">
  <div>
    <h1>Alterar senha</h1>
    <p class="mudo mb-0">
      <?= !empty($obrigado)
          ? 'Por segurança, defina uma nova senha antes de continuar usando o sistema.'
          : 'Use uma senha com pelo menos 8 caracteres, combinando letras, números e símbolos.' ?>
    </p>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error">
    <span>✖</span>
    <div>
      <?php foreach ($errors as $mensagens): foreach ($mensagens as $mensagem): ?>
        <div><?= e($mensagem) ?></div>
      <?php endforeach; endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="cartao" style="max-width:520px">
  <div class="cartao__corpo">
    <form method="post" action="<?= url('/senha') ?>">
      <?= csrf_field() ?>
      <div class="campo mb-2">
        <label for="current_password">Senha atual</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="campo mb-2">
        <label for="password">Nova senha</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="ajuda">Mínimo de 8 caracteres.</span>
      </div>
      <div class="campo mb-2">
        <label for="password_confirmation">Confirme a nova senha</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
      </div>
      <div class="acoes-form">
        <button type="submit" class="botao">Salvar nova senha</button>
        <?php if (empty($obrigado)): ?>
          <a class="botao botao--secundario" href="<?= url('/') ?>">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
