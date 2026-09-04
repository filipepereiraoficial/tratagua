<div class="pagina__cabecalho">
  <div>
    <h1>Aulas</h1>
    <p class="mudo mb-0"><?= (int) $total ?> aula(s) no recorte. Histórico por turma, disciplina, período ou aluno.</p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/aulas/nova') ?>">+ Nova aula</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/aulas') ?>">
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
        <label for="aluno">Aluno</label>
        <select id="aluno" name="aluno">
          <option value="">Todos</option>
          <?php foreach ($alunos as $aluno): ?>
            <option value="<?= (int) $aluno['id'] ?>" <?= ($filters['aluno'] ?? '') == $aluno['id'] ? 'selected' : '' ?>><?= e($aluno['full_name']) ?></option>
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
        <a class="botao botao--secundario" href="<?= url('/aulas') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($aulas === []): ?>
      <div class="vazio">
        <span class="vazio__icone">🗓</span>Nenhuma aula registrada neste recorte.
        <div class="mt-2"><a class="botao" href="<?= url('/aulas/nova') ?>">Registrar aula</a></div>
      </div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Data</th><th>Aula</th><th>Turma / Disciplina</th><th class="num">Duração</th><th>Chamada</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($aulas as $aula): ?>
            <tr>
              <td class="nowrap"><?= data_br($aula['lesson_date']) ?></td>
              <td>
                <strong><?= e($aula['title']) ?></strong>
                <?php if ($aula['content']): ?><br><small class="mudo"><?= e(mb_strimwidth($aula['content'], 0, 90, '…')) ?></small><?php endif; ?>
              </td>
              <td class="pequeno">
                <a href="<?= url('/turmas/' . $aula['class_id']) ?>"><?= e($aula['class_code']) ?></a><br>
                <span class="mudo"><?= e($aula['subject_name']) ?></span>
              </td>
              <td class="num"><?= $aula['duration_minutes'] ? (int) $aula['duration_minutes'] . ' min' : '—' ?></td>
              <td>
                <?php if ((int) $aula['attendance_count'] === 0): ?>
                  <span class="etiqueta etiqueta--medio">pendente</span>
                <?php else: ?>
                  <span class="etiqueta etiqueta--bom"><?= (int) $aula['present_count'] ?>/<?= (int) $aula['attendance_count'] ?> presentes</span>
                <?php endif; ?>
              </td>
              <td class="direita nowrap">
                <a class="botao botao--pequeno" href="<?= url('/aulas/' . $aula['id'] . '/frequencia') ?>">Chamada</a>
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/aulas/' . $aula['id'] . '/editar') ?>">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
