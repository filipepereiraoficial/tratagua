<?php
/** @var int $pagina @var int $paginas @var array $filters */
if (($paginas ?? 1) <= 1) { return; }
$base = $filters ?? [];
unset($base['pagina']);
$linkPara = static fn (int $p) => url($caminho ?? '/alunos', array_merge($base, ['pagina' => $p]));
?>
<div class="paginacao">
  <?php if ($pagina > 1): ?>
    <a href="<?= e($linkPara($pagina - 1)) ?>">‹ Anterior</a>
  <?php endif; ?>

  <?php
  $inicio = max(1, $pagina - 2);
  $fim    = min($paginas, $pagina + 2);
  for ($p = $inicio; $p <= $fim; $p++):
  ?>
    <?php if ($p === $pagina): ?>
      <span class="atual"><?= $p ?></span>
    <?php else: ?>
      <a href="<?= e($linkPara($p)) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($pagina < $paginas): ?>
    <a href="<?= e($linkPara($pagina + 1)) ?>">Próxima ›</a>
  <?php endif; ?>
  <span class="mudo" style="border:0;background:none">Página <?= $pagina ?> de <?= $paginas ?></span>
</div>
