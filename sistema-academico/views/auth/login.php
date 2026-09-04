<div class="auth__cabecalho">
  <div class="auth__marca">P</div>
  <h1 style="font-size:1.25rem">Painel Pedagógico</h1>
  <p class="mudo pequeno mb-0">Acompanhamento do desenvolvimento acadêmico</p>
</div>

<div class="auth__corpo">
  <?= \App\Core\View::partial('partials/flash', ['flash' => $flash ?? []]) ?>

  <form method="post" action="<?= url('/login') ?>">
    <?= csrf_field() ?>
    <div class="campo mb-2">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" required autofocus autocomplete="username"
             value="<?= e(old($old ?? [], 'email')) ?>">
    </div>
    <div class="campo mb-2">
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="botao botao--bloco">Entrar</button>
  </form>
</div>

<div class="auth__rodape">
  Acesso restrito a professores e administradores.
</div>
