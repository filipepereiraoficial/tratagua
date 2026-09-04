<div class="pagina__cabecalho">
  <div>
    <h1>Avaliações</h1>
    <p class="mudo mb-0"><?= (int) $total ?> avaliação(ões) no recorte.</p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/avaliacoes/nova') ?>">+ Nova avaliação</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/avaliacoes') ?>">
      <div class="campo">
        <label for="turma">Turma</label>
        <select id="turma" name="turma">
          <option value="">Todas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>><?= e($turma['code']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="disciplina">Disciplina</label>
        <select id="disciplina" name="disciplina">
          <option value="">Todas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>><?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
          <option value="">Todos</option>
          <?php foreach ($tipos as $tipo): ?>
            <option value="<?= $tipo ?>" <?= ($filters['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= e(rotulo('tipo_avaliacao', $tipo)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <?php foreach (['planejada', 'aplicada', 'corrigida'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(rotulo('status_avaliacao', $s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="inicio">De</label>
        <input type="date" id="inicio" name="inicio" value="<?= e($filters['inicio'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="fim">Até</label>
        <input type="date" id="fim" name="fim" value="<?= e($filters['fim'] ?? '') ?>">
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/avaliacoes') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($avaliacoes === []): ?>
      <div class="vazio">
        <span class="vazio__icone">📝</span>Nenhuma avaliação neste recorte.
        <div class="mt-2"><a class="botao" href="<?= url('/avaliacoes/nova') ?>">Criar avaliação</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Data</th><th>Avaliação</th><th>Turma / Disciplina</th><th>Tipo</th><th class="num">Questões</th><th class="num">Lançados</th><th class="num">Média</th><th>Situação</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($avaliacoes as $avaliacao): ?>
            <tr>
              <td class="nowrap"><?= data_br($avaliacao['assessment_date']) ?></td>
              <td><a href="<?= url('/avaliacoes/' . $avaliacao['id']) ?>"><strong><?= e($avaliacao['name']) ?></strong></a></td>
              <td class="pequeno">
                <a href="<?= url('/turmas/' . $avaliacao['class_id']) ?>"><?= e($avaliacao['class_code']) ?></a><br>
                <span class="mudo"><?= e($avaliacao['subject_name']) ?></span>
              </td>
              <td class="pequeno"><?= e(rotulo('tipo_avaliacao', $avaliacao['type'])) ?></td>
              <td class="num"><?= (int) $avaliacao['questions_count'] ?></td>
              <td class="num"><?= (int) $avaliacao['graded_count'] ?></td>
              <td class="num">
                <?php if ($avaliacao['avg_percentage'] !== null): ?>
                  <span class="etiqueta etiqueta--<?= faixa_classe((float) $avaliacao['avg_percentage']) ?>"><?= pct($avaliacao['avg_percentage'], 0) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php $classe = match ($avaliacao['status']) { 'corrigida' => 'bom', 'aplicada' => 'medio', default => 'neutro' }; ?>
                <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('status_avaliacao', $avaliacao['status'])) ?></span>
              </td>
              <td class="direita nowrap">
                <a class="botao botao--pequeno" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/resultados') ?>">Resultados</a>
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes') ?>">Questões</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
