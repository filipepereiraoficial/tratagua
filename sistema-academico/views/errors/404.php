<div class="erro-pagina">
  <div>
    <div class="erro-pagina__codigo">404</div>
    <h1>Não encontramos esta página</h1>
    <p class="mudo"><?= e($message ?? 'O endereço acessado não existe ou o registro foi removido.') ?></p>
    <a class="botao mt-2" href="<?= url('/') ?>">Voltar ao dashboard</a>
  </div>
</div>
