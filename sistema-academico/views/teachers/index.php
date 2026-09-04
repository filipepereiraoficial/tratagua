<div class="pagina__cabecalho">
  <div>
    <h1>Professores</h1>
    <p class="mudo mb-0">
      <?= count($professores) ?> professor(es). O vínculo é sempre com uma <strong>turma + disciplina</strong>:
      é ele que define o que cada professor enxerga no próprio painel.
    </p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/professores/novo') ?>">+ Novo professor</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/professores') ?>">
      <div class="campo">
        <label for="busca">Buscar</label>
        <input type="search" id="busca" name="busca" placeholder="Nome ou e-mail" value="<?= e($filters['busca'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="ativo">Situação</label>
        <select id="ativo" name="ativo">
          <option value="">Todas</option>
          <option value="1" <?= ($filters['ativo'] ?? '') === '1' ? 'selected' : '' ?>>Ativos</option>
          <option value="0" <?= ($filters['ativo'] ?? '') === '0' ? 'selected' : '' ?>>Inativos</option>
        </select>
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/professores') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($professores === []): ?>
      <div class="vazio"><span class="vazio__icone">🧑‍🏫</span>Nenhum professor cadastrado.
        <div class="mt-2"><a class="botao" href="<?= url('/professores/novo') ?>">Cadastrar professor</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Professor</th><th>Contato</th><th>Perfil</th><th class="num">Turmas</th>
            <th class="num">Disciplinas</th><th class="num">Aulas</th><th class="num">Avaliações</th>
            <th>Situação</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($professores as $professor): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:.6rem">
                  <span class="avatar"><?= e(iniciais($professor['name'])) ?></span>
                  <span>
                    <a href="<?= url('/professores/' . $professor['id']) ?>"><strong><?= e($professor['name']) ?></strong></a>
                    <?php if ($professor['qualification']): ?><br><small class="mudo"><?= e($professor['qualification']) ?></small><?php endif; ?>
                  </span>
                </div>
              </td>
              <td class="pequeno">
                <?= e($professor['email']) ?>
                <?php if ($professor['phone']): ?><br><span class="mudo"><?= e($professor['phone']) ?></span><?php endif; ?>
              </td>
              <td><span class="etiqueta etiqueta--<?= $professor['role'] === 'admin' ? 'roxo' : 'info' ?>"><?= e(rotulo('papel', $professor['role'])) ?></span></td>
              <td class="num"><?= (int) $professor['turmas'] ?></td>
              <td class="num"><?= (int) $professor['disciplinas'] ?></td>
              <td class="num"><?= (int) $professor['aulas'] ?></td>
              <td class="num"><?= (int) $professor['avaliacoes'] ?></td>
              <td><span class="etiqueta etiqueta--<?= (int) $professor['is_active'] === 1 ? 'bom' : 'neutro' ?>">
                <?= (int) $professor['is_active'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
              <td class="direita nowrap">
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/professores/' . $professor['id']) ?>">Ficha</a>
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/professores/' . $professor['id'] . '/editar') ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
