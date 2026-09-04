<div class="pagina__cabecalho">
  <div>
    <h1>Disciplinas</h1>
    <p class="mudo mb-0">Uma disciplina pode ser ofertada em várias turmas, com conteúdos e notas independentes.</p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/disciplinas/nova') ?>">+ Nova disciplina</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/disciplinas') ?>">
      <div class="campo">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" value="<?= e($filters['busca'] ?? '') ?>" placeholder="Nome da disciplina">
      </div>
      <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <option value="ativa" <?= ($filters['status'] ?? '') === 'ativa' ? 'selected' : '' ?>>Ativa</option>
          <option value="inativa" <?= ($filters['status'] ?? '') === 'inativa' ? 'selected' : '' ?>>Inativa</option>
        </select>
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/disciplinas') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($disciplinas === []): ?>
      <div class="vazio">
        <span class="vazio__icone">📚</span>Nenhuma disciplina cadastrada.
        <div class="mt-2"><a class="botao" href="<?= url('/disciplinas/nova') ?>">Cadastrar disciplina</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Disciplina</th><th>Professor</th><th class="num">Carga</th><th class="num">Turmas</th><th class="num">Assuntos</th><th class="num">Questões</th><th>Situação</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($disciplinas as $disciplina): ?>
            <tr>
              <td>
                <a href="<?= url('/disciplinas/' . $disciplina['id']) ?>"><strong><?= e($disciplina['name']) ?></strong></a>
                <?php if ($disciplina['description']): ?><br><small class="mudo"><?= e(mb_strimwidth($disciplina['description'], 0, 80, '…')) ?></small><?php endif; ?>
              </td>
              <td class="pequeno"><?= $disciplina['teacher_name'] ? e($disciplina['teacher_name']) : '<span class="mudo">—</span>' ?></td>
              <td class="num"><?= $disciplina['workload_hours'] ? (int) $disciplina['workload_hours'] . 'h' : '—' ?></td>
              <td class="num"><?= (int) $disciplina['classes_count'] ?></td>
              <td class="num"><?= (int) $disciplina['topics_count'] ?></td>
              <td class="num"><?= (int) $disciplina['questions_count'] ?></td>
              <td><span class="etiqueta etiqueta--<?= $disciplina['status'] === 'ativa' ? 'bom' : 'neutro' ?>"><?= e(ucfirst($disciplina['status'])) ?></span></td>
              <td class="direita nowrap">
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/disciplinas/' . $disciplina['id']) ?>">Abrir</a>
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/disciplinas/' . $disciplina['id'] . '/editar') ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
