<div class="pagina__cabecalho">
  <div>
    <h1>Banco de questões</h1>
    <p class="mudo mb-0"><?= count($questoes) ?> questão(ões) listada(s), com o índice de acerto real dos alunos.</p>
  </div>
  <div class="pagina__acoes"><a class="botao" href="<?= url('/questoes/nova', ['disciplina' => $filters['disciplina'] ?? null]) ?>">+ Nova questão</a></div>
</div>

<div class="cartao">
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/questoes') ?>">
      <div class="campo">
        <label for="disciplina">Disciplina</label>
        <select id="disciplina" name="disciplina" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach ($disciplinas as $disciplina): ?>
            <option value="<?= (int) $disciplina['id'] ?>" <?= ($filters['disciplina'] ?? '') == $disciplina['id'] ? 'selected' : '' ?>><?= e($disciplina['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="assunto">Assunto</label>
        <select id="assunto" name="assunto" <?= $assuntos === [] ? 'disabled' : '' ?>>
          <option value="">Todos</option>
          <?php foreach ($assuntos as $assunto): ?>
            <option value="<?= (int) $assunto['id'] ?>" <?= ($filters['assunto'] ?? '') == $assunto['id'] ? 'selected' : '' ?>><?= e($assunto['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($assuntos === []): ?><span class="ajuda">Selecione uma disciplina primeiro.</span><?php endif; ?>
      </div>
      <div class="campo">
        <label for="dificuldade">Dificuldade</label>
        <select id="dificuldade" name="dificuldade">
          <option value="">Todas</option>
          <?php foreach ($dificuldades as $nivel): ?>
            <option value="<?= $nivel ?>" <?= ($filters['dificuldade'] ?? '') === $nivel ? 'selected' : '' ?>><?= e(rotulo('dificuldade', $nivel)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="origem">Origem</label>
        <select id="origem" name="origem">
          <option value="">Todas</option>
          <option value="avaliacao" <?= ($filters['origem'] ?? '') === 'avaliacao' ? 'selected' : '' ?>>De avaliações</option>
          <option value="banco" <?= ($filters['origem'] ?? '') === 'banco' ? 'selected' : '' ?>>Somente do banco</option>
        </select>
      </div>
      <div class="campo">
        <label for="busca">Buscar no enunciado</label>
        <input type="search" id="busca" name="busca" value="<?= e($filters['busca'] ?? '') ?>">
      </div>
      <div class="campo acoes">
        <button class="botao" type="submit">Filtrar</button>
        <a class="botao botao--secundario" href="<?= url('/questoes') ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<div class="cartao">
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($questoes === []): ?>
      <div class="vazio"><span class="vazio__icone">❓</span>Nenhuma questão encontrada.</div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Questão</th><th>Disciplina / Assunto</th><th>Dificuldade</th><th class="num">Pontos</th><th>Origem</th><th>Índice de acerto</th><th class="direita">Ações</th></tr></thead>
          <tbody>
          <?php foreach ($questoes as $questao): ?>
            <tr>
              <td>
                <?php if ($questao['statement']): ?>
                  <?= e(mb_strimwidth($questao['statement'], 0, 110, '…')) ?>
                <?php else: ?>
                  <span class="mudo">Questão <?= $questao['number'] !== null ? (int) $questao['number'] : (int) $questao['id'] ?> (sem enunciado)</span>
                <?php endif; ?>
                <?php if ($questao['answer_key']): ?><br><small class="mudo">Gabarito: <?= e($questao['answer_key']) ?></small><?php endif; ?>
              </td>
              <td class="pequeno">
                <?= e($questao['subject_name']) ?><br>
                <span class="mudo"><?= $questao['topic_name'] ? e(($questao['parent_topic_name'] ? $questao['parent_topic_name'] . ' › ' : '') . $questao['topic_name']) : 'sem assunto' ?></span>
              </td>
              <td>
                <?php $classe = match ($questao['difficulty']) { 'facil' => 'bom', 'medio' => 'medio', default => 'ruim' }; ?>
                <span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('dificuldade', $questao['difficulty'])) ?></span>
              </td>
              <td class="num"><?= num($questao['points'], 2) ?></td>
              <td class="pequeno">
                <?php if ($questao['assessment_name']): ?>
                  <a href="<?= url('/avaliacoes/' . $questao['assessment_id']) ?>"><?= e($questao['assessment_name']) ?></a><br>
                  <span class="mudo"><?= data_br($questao['assessment_date']) ?></span>
                <?php else: ?>
                  <span class="etiqueta etiqueta--neutro">banco</span>
                <?php endif; ?>
              </td>
              <td style="min-width:150px">
                <?php if ($questao['indice_acerto'] === null): ?>
                  <span class="mudo pequeno">sem respostas</span>
                <?php else: ?>
                  <span class="progresso-linha">
                    <span class="progresso"><span class="progresso__barra progresso__barra--<?= faixa_classe($questao['indice_acerto']) ?>" style="width:<?= (float) $questao['indice_acerto'] ?>%"></span></span>
                    <span><?= pct($questao['indice_acerto'], 0) ?></span>
                  </span>
                  <small class="mudo"><?= (int) $questao['answers_count'] ?> resposta(s)</small>
                <?php endif; ?>
              </td>
              <td class="direita nowrap">
                <a class="botao botao--secundario botao--pequeno" href="<?= url('/questoes/' . $questao['id'] . '/editar') ?>">Editar</a>
                <form method="post" action="<?= url('/questoes/' . $questao['id'] . '/excluir') ?>"
                      data-confirmar="Excluir esta questão e suas respostas?" style="display:inline">
                  <?= csrf_field() ?>
                  <button class="botao botao--perigo botao--pequeno" type="submit">✕</button>
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
