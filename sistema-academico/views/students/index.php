<div class="pagina__cabecalho">
  <div>
    <h1>Alunos</h1>
    <p class="mudo mb-0"><?= (int) $total ?> aluno(s) no recorte atual.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/alunos/exportar', $filters) ?>">Exportar CSV</a>
    <a class="botao" href="<?= url('/alunos/novo') ?>">+ Novo aluno</a>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/alunos') ?>">
      <div class="campo">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" placeholder="Nome, e-mail ou CPF" value="<?= e($filters['busca'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="turma">Turma</label>
        <select id="turma" name="turma">
          <option value="">Todas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>><?= e($turma['code']) ?> (<?= (int) $turma['year'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="curso">Curso</label>
        <select id="curso" name="curso">
          <option value="">Todos</option>
          <?php foreach ($cursos as $curso): ?>
            <option value="<?= (int) $curso['id'] ?>" <?= ($filters['curso'] ?? '') == $curso['id'] ? 'selected' : '' ?>><?= e($curso['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <?php foreach (['ativo', 'inativo', 'concluido'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(rotulo('status_aluno', $s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="sem_turma">Vínculo</label>
        <select id="sem_turma" name="sem_turma">
          <option value="">Todos</option>
          <option value="1" <?= !empty($filters['sem_turma']) ? 'selected' : '' ?>>Somente sem turma</option>
        </select>
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/alunos') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($alunos === []): ?>
      <div class="vazio">
        <span class="vazio__icone">👤</span>
        Nenhum aluno encontrado com os filtros atuais.
        <div class="mt-2"><a class="botao" href="<?= url('/alunos/novo') ?>">Cadastrar o primeiro aluno</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead>
            <tr>
              <?php
              $ordenar = static function (string $chave, string $rotulo) use ($filters) {
                  $dir = (($filters['sort'] ?? '') === $chave && ($filters['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc';
                  $seta = ($filters['sort'] ?? '') === $chave ? (($filters['dir'] ?? 'asc') === 'asc' ? ' ▲' : ' ▼') : '';
                  $query = array_merge($filters, ['sort' => $chave, 'dir' => $dir]);
                  return '<a href="' . e(url('/alunos', $query)) . '">' . e($rotulo) . $seta . '</a>';
              };
              ?>
              <th><?= $ordenar('nome', 'Aluno') ?></th>
              <th>Contato</th>
              <th><?= $ordenar('turma', 'Turma') ?></th>
              <th><?= $ordenar('status', 'Situação') ?></th>
              <th class="direita">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($alunos as $aluno): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:.6rem">
                    <span class="avatar"><?= e(iniciais($aluno['full_name'])) ?></span>
                    <span>
                      <a href="<?= url('/alunos/' . $aluno['id']) ?>"><strong><?= e($aluno['full_name']) ?></strong></a>
                      <?php if ($aluno['document']): ?><br><small class="mudo"><?= e($aluno['document']) ?></small><?php endif; ?>
                    </span>
                  </div>
                </td>
                <td class="pequeno">
                  <?= $aluno['email'] ? e($aluno['email']) : '<span class="mudo">sem e-mail</span>' ?>
                  <?php if ($aluno['phone']): ?><br><span class="mudo"><?= e($aluno['phone']) ?></span><?php endif; ?>
                </td>
                <td>
                  <?php if ($aluno['class_code']): ?>
                    <a href="<?= url('/turmas/' . $aluno['class_id']) ?>"><?= e($aluno['class_code']) ?></a>
                    <br><small class="mudo"><?= e($aluno['course_name']) ?></small>
                  <?php else: ?>
                    <span class="etiqueta etiqueta--medio">Sem turma</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $classe = match ($aluno['status']) { 'ativo' => 'bom', 'concluido' => 'info', default => 'neutro' }; ?>
                  <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('status_aluno', $aluno['status'])) ?></span>
                </td>
                <td class="direita nowrap">
                  <a class="botao botao--secundario botao--pequeno" href="<?= url('/alunos/' . $aluno['id']) ?>">Dashboard</a>
                  <a class="botao botao--secundario botao--pequeno" href="<?= url('/alunos/' . $aluno['id'] . '/editar') ?>">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= \App\Core\View::partial('partials/pagination', ['pagina' => $pagina, 'paginas' => $paginas, 'filters' => $filters, 'caminho' => '/alunos']) ?>
    <?php endif; ?>
  </div>
</div>
