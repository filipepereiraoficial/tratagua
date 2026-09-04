<div class="auth__cabecalho">
  <div class="auth__marca">P</div>
  <h1 style="font-size:1.2rem">Instalação do sistema</h1>
  <p class="mudo pequeno mb-0">
    Banco em uso: <strong><?= e(strtoupper($driver ?? 'sqlite')) ?></strong>
  </p>
</div>

<div class="auth__corpo">
  <?= \App\Core\View::partial('partials/flash', ['flash' => $flash ?? []]) ?>

  <p class="pequeno mudo">
    Esta etapa cria as tabelas, a conta de administrador e a carga inicial
    (curso, disciplina e turma). Ela fica indisponível depois de concluída.
  </p>

  <form method="post" action="<?= url('/instalar') ?>">
    <?= csrf_field() ?>
    <div class="campo mb-2">
      <label for="name">Nome do administrador</label>
      <input type="text" id="name" name="name" required
             value="<?= e(old($old ?? [], 'name', 'Filipe Pereira')) ?>">
    </div>
    <div class="campo mb-2">
      <label for="email">E-mail de acesso</label>
      <input type="email" id="email" name="email" required
             value="<?= e(old($old ?? [], 'email', 'manowfilipe@gmail.com')) ?>">
    </div>
    <div class="campo mb-2">
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" required minlength="8">
      <span class="ajuda">Mínimo de 8 caracteres. Recomendamos alterá-la após o primeiro acesso.</span>
    </div>
    <div class="campo mb-2">
      <label for="password_confirmation">Confirme a senha</label>
      <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
    </div>
    <label class="checkbox mb-2">
      <input type="checkbox" name="demo" value="1">
      Incluir dados de demonstração (alunos, aulas e avaliações de exemplo)
    </label>
    <button type="submit" class="botao botao--bloco">Instalar sistema</button>
  </form>
</div>

<div class="auth__rodape">Curso, disciplina e turma iniciais são criados automaticamente.</div>
