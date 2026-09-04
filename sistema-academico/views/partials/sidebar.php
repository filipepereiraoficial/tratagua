<?php
/**
 * Menu lateral. O que aparece depende do perfil: o professor entra no painel
 * dele, o administrador na visão institucional, e o aluno só vê a si mesmo.
 *
 * @var string $route @var array|null $auth
 */
$papel = $auth['role'] ?? 'aluno';
$ehAdmin = $papel === 'admin';

if ($papel === 'aluno') {
    $grupos = [
        'Minha área' => [
            ['Minha evolução', '/minha-evolucao', '📈', ['/minha-evolucao']],
        ],
    ];
} else {
    $grupos = [
        'Acompanhamento' => array_values(array_filter([
            $ehAdmin ? ['Dashboard geral', '/', '▦', ['/']] : null,
            $ehAdmin ? ['Painel do administrador', '/painel-admin', '🏛', ['/painel-admin']] : null,
            ['Meu painel', '/meu-painel', '🎯', ['/meu-painel']],
            ['Minhas turmas', '/minhas-turmas', '🧭', ['/minhas-turmas']],
            ['Acompanhamento', '/acompanhamento', '🤝', ['/acompanhamento']],
        ])),
        'Cadastros e registros' => array_values(array_filter([
            ['Alunos', '/alunos', '👤', ['/alunos']],
            $ehAdmin ? ['Professores', '/professores', '🧑‍🏫', ['/professores']] : null,
            ['Turmas', '/turmas', '🏫', ['/turmas', '/cursos']],
            ['Disciplinas', '/disciplinas', '📚', ['/disciplinas', '/assuntos']],
            ['Aulas', '/aulas', '🗓', ['/aulas']],
            ['Avaliações', '/avaliacoes', '📝', ['/avaliacoes']],
            ['Questões', '/questoes', '❓', ['/questoes']],
        ])),
        'Análise' => [
            ['Relatórios', '/relatorios', '📄', ['/relatorios']],
            ['Gráficos', '/graficos', '📈', ['/graficos']],
            ['Comparação', '/comparacao', '⇄', ['/comparacao']],
        ],
    ];
    if ($ehAdmin) {
        $grupos['Sistema'] = [
            ['Configurações', '/configuracoes', '⚙', ['/configuracoes']],
            ['Auditoria', '/auditoria', '🔎', ['/auditoria']],
        ];
    }
}

$ativo = static function (array $prefixos) use ($route): bool {
    foreach ($prefixos as $prefixo) {
        if ($prefixo === '/' ? $route === '/' : str_starts_with($route, $prefixo)) {
            return true;
        }
    }
    return false;
};
$papelTexto = ['admin' => 'Administração', 'professor' => 'Professor', 'aluno' => 'Aluno'][$papel] ?? '';
?>
<nav class="menu" aria-label="Menu principal">
  <a class="menu__marca" href="<?= url($papel === 'aluno' ? '/minha-evolucao' : '/meu-painel') ?>">
    <span class="menu__logo">P</span>
    <span>Painel Pedagógico<small><?= e($papelTexto) ?></small></span>
  </a>

  <?php foreach ($grupos as $titulo => $itens): ?>
    <?php if ($itens === []) { continue; } ?>
    <div class="menu__grupo"><?= e($titulo) ?></div>
    <ul class="menu__lista">
      <?php foreach ($itens as [$rotulo, $href, $icone, $prefixos]): ?>
        <li>
          <a class="menu__link <?= $ativo($prefixos) ? 'menu__link--ativo' : '' ?>" href="<?= url($href) ?>">
            <span class="menu__icone" aria-hidden="true"><?= $icone ?></span><?= e($rotulo) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>

  <div class="menu__rodape">
    <a class="menu__link" href="<?= url('/senha') ?>" style="padding:.35rem .65rem">
      <span class="menu__icone" aria-hidden="true">🔒</span>Alterar senha
    </a>
  </div>
</nav>
