<?php /** @var array $flash */ ?>
<?php foreach (($flash ?? []) as $mensagem): ?>
  <div class="aviso aviso--<?= e($mensagem['type']) ?>">
    <span aria-hidden="true"><?php
      echo match ($mensagem['type']) {
        'success' => '✔',
        'error'   => '✖',
        'warning' => '⚠',
        default   => 'ℹ',
      };
    ?></span>
    <div><?= e($mensagem['message']) ?></div>
  </div>
<?php endforeach; ?>
