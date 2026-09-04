<div class="pagina__cabecalho">
  <div>
    <h1>Acompanhamento pedagógico</h1>
    <p class="mudo mb-0">
      O que foi <strong>feito</strong> com cada aluno sinalizado. Ao abrir um registro, a média e a
      frequência daquele momento ficam guardadas — e a coluna “efeito” mostra o que mudou desde então.
    </p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/acompanhamento/novo') ?>">+ Novo acompanhamento</a></div>
</div>

<div class="indicadores">
  <div class="indicador indicador--medio"><div class="indicador__rotulo">Abertos</div>
    <div class="indicador__valor"><?= (int) $contagem['aberta'] ?></div></div>
  <div class="indicador indicador--info"><div class="indicador__rotulo">Em andamento</div>
    <div class="indicador__valor"><?= (int) $contagem['em_andamento'] ?></div></div>
  <div class="indicador indicador--bom"><div class="indicador__rotulo">Concluídos</div>
    <div class="indicador__valor"><?= (int) $contagem['concluida'] ?></div></div>
  <div class="indicador indicador--ruim"><div class="indicador__rotulo">Prazo vencido</div>
    <div class="indicador__valor"><?= (int) $contagem['atrasadas'] ?></div>
    <div class="indicador__nota">abertos e fora do prazo</div></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/acompanhamento') ?>" data-auto-filtro>
      <div class="campo">
        <label for="aluno">Aluno</label>
        <select id="aluno" name="aluno">
          <option value="">Todos</option>
          <?php foreach ($alunos as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= ($filters['aluno'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <?php foreach ($situacoes as $chave => $texto): ?>
            <option value="<?= $chave ?>" <?= ($filters['status'] ?? '') === $chave ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
          <option value="">Todos</option>
          <?php foreach ($tipos as $chave => $texto): ?>
            <option value="<?= $chave ?>" <?= ($filters['tipo'] ?? '') === $chave ? 'selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/acompanhamento') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($registros === []): ?>
      <div class="vazio">
        <span class="vazio__icone">🤝</span>
        Nenhum acompanhamento registrado. Quando um alerta apontar um aluno, registre aqui a conversa,
        o reforço ou o contato feito — é assim que o sistema mede se a intervenção funcionou.
        <div class="mt-2"><a class="botao" href="<?= url('/acompanhamento/novo') ?>">Registrar o primeiro</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Aluno</th><th>Acompanhamento</th><th>Tipo</th><th>Prazo</th>
            <th>Situação</th><th class="num">Efeito na média</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($registros as $r): ?>
            <tr>
              <td>
                <a href="<?= url('/alunos/' . $r['student_id']) ?>"><?= e($r['student_name']) ?></a>
                <?php if ($r['class_code']): ?><div class="mudo pequeno"><?= e($r['class_code']) ?> · <?= e($r['subject_name']) ?></div><?php endif; ?>
              </td>
              <td>
                <strong><?= e($r['title']) ?></strong>
                <?php if ($r['description']): ?><div class="mudo pequeno"><?= e(mb_strimwidth($r['description'], 0, 90, '…')) ?></div><?php endif; ?>
                <div class="mudo pequeno">por <?= e($r['author_name'] ?? '—') ?> · <?= data_br($r['created_at']) ?></div>
              </td>
              <td class="pequeno"><?= e(\App\Models\Intervention::TYPES[$r['type']] ?? $r['type']) ?></td>
              <td class="pequeno nowrap">
                <?= $r['due_date'] ? data_br($r['due_date']) : '<span class="mudo">—</span>' ?>
                <?php if (!empty($r['atrasado'])): ?><br><span class="etiqueta etiqueta--ruim">vencido</span><?php endif; ?>
              </td>
              <td>
                <?php $cl = ['aberta' => 'medio', 'em_andamento' => 'info', 'concluida' => 'bom', 'cancelada' => 'neutro'][$r['status']]; ?>
                <span class="etiqueta etiqueta--<?= $cl ?>"><?= e(\App\Models\Intervention::STATUSES[$r['status']]) ?></span>
              </td>
              <td class="num">
                <?php if ($r['efeito']['media'] === null): ?>
                  <span class="mudo pequeno">sem linha de base</span>
                <?php else: ?>
                  <span class="etiqueta etiqueta--<?= $r['efeito']['media'] >= 0 ? 'bom' : 'ruim' ?>">
                    <?= $r['efeito']['media'] >= 0 ? '+' : '' ?><?= num($r['efeito']['media']) ?> p.p.</span>
                  <div class="mudo pequeno">de <?= pct($r['baseline_media'], 0) ?> para <?= pct($r['media_atual'], 0) ?></div>
                <?php endif; ?>
              </td>
              <td class="direita nowrap">
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/acompanhamento/' . $r['id'] . '/editar') ?>">Abrir</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
