<div class="erro-pagina">
  <div style="max-width:760px">
    <div class="erro-pagina__codigo">500</div>
    <h1>Erro interno do servidor</h1>
    <p class="mudo">Registramos o ocorrido. Tente novamente; se persistir, avise o responsável técnico.</p>
    <?php if (!empty($message)): ?>
      <div class="aviso aviso--error" style="text-align:left"><span>✖</span><div><?= e($message) ?></div></div>
      <pre style="text-align:left;overflow:auto;background:var(--cinza-100);padding:.8rem;border-radius:6px;font-size:.75rem"><?= e($trace ?? '') ?></pre>
    <?php endif; ?>
    <a class="botao mt-2" href="<?= url('/') ?>">Voltar ao dashboard</a>
  </div>
</div>
