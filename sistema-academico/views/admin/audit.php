<div class="pagina__cabecalho">
  <div>
    <h1>Auditoria</h1>
    <p class="mudo mb-0">
      <?= (int) $total ?> alteração(ões) registrada(s). Só operações que mudam dados entram aqui —
      leitura não é registrada, para o log continuar legível.
    </p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/painel-admin') ?>">Painel do administrador</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/auditoria') ?>">
      <div class="campo">
        <label for="usuario">Usuário</label>
        <select id="usuario" name="usuario">
          <option value="">Todos</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= ($filters['usuario'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="entidade">Entidade</label>
        <select id="entidade" name="entidade">
          <option value="">Todas</option>
          <?php foreach ($entidades as $ent): ?>
            <option value="<?= e($ent) ?>" <?= ($filters['entidade'] ?? '') === $ent ? 'selected' : '' ?>><?= e($ent) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="acao">Ação</label>
        <select id="acao" name="acao">
          <option value="">Todas</option>
          <?php foreach ($acoes as $a): ?>
            <option value="<?= e($a) ?>" <?= ($filters['acao'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo"><label for="inicio">De</label>
        <input type="date" id="inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>"></div>
      <div class="campo"><label for="fim">Até</label>
        <input type="date" id="fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>"></div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/auditoria') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($registros === []): ?>
      <div class="vazio"><span class="vazio__icone">🔎</span>Nenhum registro para os filtros atuais.</div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead><tr><th>Quando</th><th>Quem</th><th>Perfil</th><th>Ação</th><th>Entidade</th><th>Detalhe</th></tr></thead>
          <tbody>
          <?php foreach ($registros as $log): ?>
            <tr>
              <td class="mono nowrap"><?= datahora_br($log['created_at']) ?></td>
              <td><?= e($log['user_name'] ?? 'sistema') ?></td>
              <td><?= $log['user_role'] ? '<span class="etiqueta etiqueta--neutro">' . e(rotulo('papel', $log['user_role'])) . '</span>' : '—' ?></td>
              <td><?= e($log['action']) ?></td>
              <td class="pequeno"><?= e($log['entity'] ?? '—') ?><?= $log['entity_id'] ? ' <span class="mudo mono">#' . (int) $log['entity_id'] . '</span>' : '' ?></td>
              <td class="pequeno mudo"><?= e($log['details'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
