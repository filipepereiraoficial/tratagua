<div class="pagina__cabecalho">
  <div>
    <h1>Cursos</h1>
    <p class="mudo mb-0">O curso é o nível mais alto da hierarquia: Curso → Turmas → Alunos.</p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/turmas') ?>">Voltar às turmas</a></div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<div class="colunas colunas--2-1">
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Cursos cadastrados</h2></div>
    <div class="cartao__corpo cartao__corpo--liso">
      <?php if ($cursos === []): ?>
        <div class="vazio"><span class="vazio__icone">🎓</span>Nenhum curso cadastrado.</div>
      <?php else: ?>
        <div class="tabela-rolagem">
          <table class="tabela">
            <thead><tr><th>Curso</th><th class="num">Turmas</th><th class="num">Carga</th><th>Situação</th><th class="direita">Ações</th></tr></thead>
            <tbody>
            <?php foreach ($cursos as $curso): ?>
              <tr>
                <td>
                  <form method="post" action="<?= url('/cursos/' . $curso['id']) ?>" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
                    <?= csrf_field() ?>
                    <input type="text" name="name" value="<?= e($curso['name']) ?>" required maxlength="150" style="min-width:200px">
                    <input type="number" name="workload_hours" value="<?= e($curso['workload_hours']) ?>" placeholder="h" style="width:80px" min="0">
                    <select name="status" style="width:110px">
                      <option value="ativo" <?= $curso['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                      <option value="inativo" <?= $curso['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <input type="hidden" name="description" value="<?= e($curso['description']) ?>">
                    <button class="botao botao--pequeno botao--secundario" type="submit">Salvar</button>
                  </form>
                </td>
                <td class="num"><?= (int) $curso['classes_count'] ?></td>
                <td class="num"><?= $curso['workload_hours'] ? (int) $curso['workload_hours'] . 'h' : '—' ?></td>
                <td><span class="etiqueta etiqueta--<?= $curso['status'] === 'ativo' ? 'bom' : 'neutro' ?>"><?= e(ucfirst($curso['status'])) ?></span></td>
                <td class="direita">
                  <form method="post" action="<?= url('/cursos/' . $curso['id'] . '/excluir') ?>"
                        data-confirmar="Excluir este curso?" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="botao botao--perigo botao--pequeno" type="submit" <?= (int) $curso['classes_count'] > 0 ? 'disabled title="Existem turmas vinculadas"' : '' ?>>Excluir</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Novo curso</h2></div>
    <div class="cartao__corpo">
      <form method="post" action="<?= url('/cursos') ?>">
        <?= csrf_field() ?>
        <div class="campo mb-2">
          <label for="name">Nome do curso *</label>
          <input type="text" id="name" name="name" required maxlength="150" placeholder="Preparatório para Guarda Municipal">
        </div>
        <div class="campo mb-2">
          <label for="workload_hours">Carga horária (h)</label>
          <input type="number" id="workload_hours" name="workload_hours" min="0" placeholder="120">
        </div>
        <div class="campo mb-2">
          <label for="description">Descrição</label>
          <textarea id="description" name="description" rows="3"></textarea>
        </div>
        <input type="hidden" name="status" value="ativo">
        <button class="botao botao--bloco" type="submit">Cadastrar curso</button>
      </form>
    </div>
  </div>
</div>
