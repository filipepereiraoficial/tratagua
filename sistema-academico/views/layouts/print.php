<?php /** @var string $content */ ?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Relatório') ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
  body { background: #fff; }
  .folha { max-width: 1000px; margin: 0 auto; padding: 1.6rem; }
  .folha__cabecalho { border-bottom: 2px solid var(--azul-700); padding-bottom: .8rem; margin-bottom: 1.2rem; }
  .folha__rodape { margin-top: 1.6rem; padding-top: .6rem; border-top: 1px solid var(--cinza-300); font-size: .78rem; color: var(--cinza-600); }
</style>
</head>
<body>
<div class="folha"><?= $content ?></div>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
