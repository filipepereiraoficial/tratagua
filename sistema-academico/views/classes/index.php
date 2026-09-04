<div class="pagina__cabecalho">
  <div>
    <h1>Turmas</h1>
    <p class="mudo mb-0"><?= count($turmas) ?> turma(s) encontradas.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/cursos') ?>">Gerenciar cursos</a>
    <a class="botao" href="<?= url('/turmas/nova') ?>">+ Nova turma</a>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/turmas') ?>">
      <div class="campo">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" placeholder="Código ou nome" value="<?= e($filters['busca'] ?? '') ?>">
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
        <label for="ano">Ano</label>
        <select id="ano" name="ano">
          <option value="">Todos</option>
          <?php foreach ($anos as $ano): ?>
            <option value="<?= (int) $ano ?>" <?= ($filters['ano'] ?? '') == $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <?php foreach (['planejada', 'em_andamento', 'concluida', 'cancelada'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(rotulo('status_turma', $s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/turmas') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($turmas === []): ?>
      <div class="vazio">
        <span class="vazio__icone">🏫</span>Nenhuma turma cadastrada.
        <div class="mt-2"><a class="botao" href="<?= url('/turmas/nova') ?>">Criar turma</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Turma</th><th>Curso</th><th>Período</th><th class="num">Alunos</th><th class="num">Disciplinas</th><th>Situação</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($turmas as $turma): ?>
            <tr>
              <td>
                <a href="<?= url('/turmas/' . $turma['id']) ?>"><strong><?= e($turma['code']) ?></strong></a>
                <?php if ($turma['name']): ?><br><small class="mudo"><?= e($turma['name']) ?></small><?php endif; ?>
              </td>
              <td><?= e($turma['course_name']) ?><br><small class="mudo"><?= (int) $turma['year'] ?></small></td>
              <td class="pequeno">
                <?= e($turma['period'] ?: '—') ?><br>
                <span class="mudo"><?= data_br($turma['start_date'], '?') ?> a <?= data_br($turma['end_date'], '?') ?></span>
              </td>
              <td class="num"><?= (int) $turma['students_count'] ?></td>
              <td class="num"><?= (int) $turma['subjects_count'] ?></td>
              <td>
                <?php $classe = match ($turma['status']) {
                    'em_andamento' => 'bom', 'planejada' => 'info', 'concluida' => 'neutro', default => 'ruim',
                }; ?>
                <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('status_turma', $turma['status'])) ?></span>
              </td>
              <td class="direita nowrap">
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/turmas/' . $turma['id']) ?>">Painel</a>
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/turmas/' . $turma['id'] . '/editar') ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
