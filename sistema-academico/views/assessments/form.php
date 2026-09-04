<?php
$editando = $avaliacao !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($avaliacao[$campo] ?? $padrao) : $padrao));
$ofertaAtual = $editando ? (int) $avaliacao['class_subject_id'] : (int) old($old ?? [], 'class_subject_id', 0);
?>
<div class="pagina__cabecalho">
  <div><h1><?= $editando ? 'Editar avaliação' : 'Nova avaliação' ?></h1></div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url($editando ? '/avaliacoes/' . $avaliacao['id'] : '/avaliacoes') ?>">Cancelar</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($ofertas === []): ?>
  <div class="aviso aviso--warning"><span>⚠</span><div>
    Nenhuma disciplina vinculada a uma turma. <a href="<?= url('/turmas') ?>">Vincule uma disciplina a uma turma</a> primeiro.
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/avaliacoes/' . $avaliacao['id'] : '/avaliacoes') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo campo--largo">
          <label for="name">Nome da avaliação *</label>
          <input type="text" id="name" name="name" required maxlength="200" placeholder="Simulado 1 — Informática" value="<?= $valor('name') ?>">
        </div>
        <div class="campo">
          <label for="class_subject_id">Turma / Disciplina *</label>
          <select id="class_subject_id" name="class_subject_id" required>
            <option value="">Selecione…</option>
            <?php foreach ($ofertas as $oferta): ?>
              <option value="<?= (int) $oferta['id'] ?>" <?= $ofertaAtual === (int) $oferta['id'] ? 'selected' : '' ?>>
                <?= e($oferta['class_code']) ?> — <?= e($oferta['subject_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="type">Tipo *</label>
          <select id="type" name="type" required>
            <?php foreach (\App\Models\Assessment::TYPES as $tipo): ?>
              <option value="<?= $tipo ?>" <?= ($editando ? $avaliacao['type'] : old($old ?? [], 'type', 'prova')) === $tipo ? 'selected' : '' ?>>
                <?= e(rotulo('tipo_avaliacao', $tipo)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="assessment_date">Data *</label>
          <input type="date" id="assessment_date" name="assessment_date" required value="<?= $valor('assessment_date', date('Y-m-d')) ?>">
        </div>
        <div class="campo">
          <label for="max_score">Valor máximo *</label>
          <input type="number" id="max_score" name="max_score" step="0.01" min="0.1" required value="<?= $valor('max_score', '10') ?>">
        </div>
        <div class="campo">
          <label for="weight">Peso na média</label>
          <input type="number" id="weight" name="weight" step="0.1" min="0.1" max="100" value="<?= $valor('weight', '1') ?>">
          <span class="ajuda">Simulados podem pesar mais que atividades.</span>
        </div>
        <div class="campo">
          <label for="status">Situação *</label>
          <select id="status" name="status" required>
            <?php foreach (['planejada', 'aplicada', 'corrigida'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editando ? $avaliacao['status'] : old($old ?? [], 'status', 'planejada')) === $s ? 'selected' : '' ?>>
                <?= e(rotulo('status_avaliacao', $s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!$editando): ?>
          <div class="campo">
            <label for="question_count">Quantidade de questões</label>
            <input type="number" id="question_count" name="question_count" min="0" max="200" placeholder="Ex.: 20">
            <span class="ajuda">Cria as questões numeradas automaticamente, dividindo o valor máximo.</span>
          </div>
        <?php endif; ?>
        <div class="campo campo--largo">
          <label for="description">Conteúdo abordado</label>
          <textarea id="description" name="description" rows="3" placeholder="Assuntos e tópicos cobrados nesta avaliação"><?= $valor('description') ?></textarea>
        </div>
      </div>

      <div class="acoes-form">
        <button class="botao" type="submit" <?= $ofertas === [] ? 'disabled' : '' ?>><?= $editando ? 'Salvar alterações' : 'Criar avaliação' ?></button>
        <a class="botao botao--secundario" href="<?= url('/avaliacoes') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<?php if ($editando): ?>
  <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/excluir') ?>"
        data-confirmar="Excluir esta avaliação, suas questões e todos os resultados?">
    <?= csrf_field() ?>
    <button class="botao botao--perigo botao--pequeno" type="submit">Excluir avaliação</button>
  </form>
<?php endif; ?>
