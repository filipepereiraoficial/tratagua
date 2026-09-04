<?php
/** @var string $content @var array|null $auth @var string $route @var string $title */
$auth  = $auth ?? null;
$route = $route ?? '/';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Painel Pedagógico') ?> · Painel Pedagógico</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231a6d9e'/><text x='16' y='23' font-size='18' text-anchor='middle' fill='white' font-family='sans-serif'>P</text></svg>">
</head>
<body>
<div class="app">
  <?= \App\Core\View::partial('partials/sidebar', ['route' => $route, 'auth' => $auth]) ?>

  <div class="conteudo">
    <header class="topo">
      <button class="menu-botao" type="button" aria-label="Abrir menu">☰</button>
      <div class="topo__titulo"><?= e($title ?? 'Painel Pedagógico') ?></div>
      <div class="topo__acoes">
        <?php if ($auth): ?>
          <div class="topo__usuario">
            <div class="avatar"><?= e(iniciais($auth['name'])) ?></div>
            <span>
              <strong><?= e($auth['name']) ?></strong><br>
              <small class="mudo"><?= e(rotulo('papel', $auth['role'])) ?></small>
            </span>
          </div>
          <form method="post" action="<?= url('/logout') ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="botao botao--secundario botao--pequeno" type="submit">Sair</button>
          </form>
        <?php endif; ?>
      </div>
    </header>

    <main class="pagina">
      <?= \App\Core\View::partial('partials/flash', ['flash' => $flash ?? []]) ?>
      <?= $content ?>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<script src="<?= asset('js/charts.js') ?>" defer></script>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= $scripts ?? '' ?>
</body>
</html>
