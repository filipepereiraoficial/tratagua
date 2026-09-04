<?php
/** @var string $route @var array|null $auth */
$itens = [
    ['Dashboard',     '/',              '▦', ['/']],
    ['Alunos',        '/alunos',        '👤', ['/alunos']],
    ['Turmas',        '/turmas',        '🏫', ['/turmas', '/cursos']],
    ['Disciplinas',   '/disciplinas',   '📚', ['/disciplinas', '/assuntos']],
    ['Aulas',         '/aulas',         '🗓', ['/aulas']],
    ['Avaliações',    '/avaliacoes',    '📝', ['/avaliacoes']],
    ['Questões',      '/questoes',      '❓', ['/questoes']],
];
$analise = [
    ['Relatórios',    '/relatorios',    '📄', ['/relatorios']],
    ['Gráficos',      '/graficos',      '📈', ['/graficos']],
    ['Comparação',    '/comparacao',    '⇄',  ['/comparacao']],
];
$ativo = static function (array $prefixos) use ($route): bool {
    foreach ($prefixos as $prefixo) {
        if ($prefixo === '/' ? $route === '/' : str_starts_with($route, $prefixo)) {
            return true;
        }
    }
    return false;
};
?>
<nav class="menu" aria-label="Menu principal">
  <a class="menu__marca" href="<?= url('/') ?>">
    <span class="menu__logo">P</span>
    <span>Painel Pedagógico<small>Acompanhamento de alunos</small></span>
  </a>

  <div class="menu__grupo">Cadastros e registros</div>
  <ul class="menu__lista">
    <?php foreach ($itens as [$rotulo, $href, $icone, $prefixos]): ?>
      <li>
        <a class="menu__link <?= $ativo($prefixos) ? 'menu__link--ativo' : '' ?>" href="<?= url($href) ?>">
          <span class="menu__icone" aria-hidden="true"><?= $icone ?></span><?= e($rotulo) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="menu__grupo">Análise</div>
  <ul class="menu__lista">
    <?php foreach ($analise as [$rotulo, $href, $icone, $prefixos]): ?>
      <li>
        <a class="menu__link <?= $ativo($prefixos) ? 'menu__link--ativo' : '' ?>" href="<?= url($href) ?>">
          <span class="menu__icone" aria-hidden="true"><?= $icone ?></span><?= e($rotulo) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if (($auth['role'] ?? '') === 'admin'): ?>
    <div class="menu__grupo">Sistema</div>
    <ul class="menu__lista">
      <li>
        <a class="menu__link <?= $ativo(['/configuracoes']) ? 'menu__link--ativo' : '' ?>" href="<?= url('/configuracoes') ?>">
          <span class="menu__icone" aria-hidden="true">⚙</span>Configurações
        </a>
      </li>
    </ul>
  <?php endif; ?>

  <div class="menu__rodape">
    <a class="menu__link" href="<?= url('/senha') ?>" style="padding:.35rem .65rem">
      <span class="menu__icone" aria-hidden="true">🔒</span>Alterar senha
    </a>
  </div>
</nav>
