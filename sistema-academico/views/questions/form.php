<?php
$editando = $questao !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($questao[$campo] ?? $padrao) : $padrao));
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= $editando ? 'Editar questão' : 'Nova questão' ?></h1>
    <?php if ($editando && $questao['assessment_name']): ?>
      <p class="mudo mb-0">Pertence à avaliação <a href="<?= url('/avaliacoes/' . $questao['assessment_id']) ?>"><?= e($questao['assessment_name']) ?></a></p>
    <?php endif; ?>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/questoes') ?>">Cancelar</a></div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/questoes/' . $questao['id'] : '/questoes') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo">
          <label for="subject_id">Disciplina *</label>
          <select id="subject_id" name="subject_id" required data-carrega-assuntos="topic_id">
            <option value="">Selecione…</option>
            <?php foreach ($disciplinas as $disciplina): ?>
              <option value="<?= (int) $disciplina['id'] ?>" <?= (string) ($subject_id ?? '') === (string) $disciplina['id'] ? 'selected' : '' ?>>
                <?= e($disciplina['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="topic_id">Assunto / tópico</label>
          <select id="topic_id" name="topic_id" data-url-base="<?= url('/api/assuntos') ?>">
            <option value="">— Sem assunto —</option>
            <?php foreach ($assuntos ?? [] as $assunto): ?>
              <option value="<?= (int) $assunto['id'] ?>" <?= (string) ($editando ? $questao['topic_id'] : '') === (string) $assunto['id'] ? 'selected' : '' ?>>
                <?= e($assunto['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="ajuda">Classificar por assunto é o que habilita a análise de dificuldades.</span>
        </div>
        <div class="campo">
          <label for="difficulty">Dificuldade *</label>
          <select id="difficulty" name="difficulty" required>
            <?php foreach (['facil', 'medio', 'dificil'] as $nivel): ?>
              <option value="<?= $nivel ?>" <?= ($editando ? $questao['difficulty'] : old($old ?? [], 'difficulty', 'medio')) === $nivel ? 'selected' : '' ?>>
                <?= e(rotulo('dificuldade', $nivel)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="points">Valor da questão *</label>
          <input type="number" id="points" name="points" step="0.01" min="0.01" required value="<?= $valor('points', '1') ?>">
        </div>
        <div class="campo">
          <label for="type">Tipo *</label>
          <select id="type" name="type" required>
            <option value="objetiva" <?= ($editando ? $questao['type'] : 'objetiva') === 'objetiva' ? 'selected' : '' ?>>Objetiva</option>
            <option value="discursiva" <?= ($editando ? $questao['type'] : '') === 'discursiva' ? 'selected' : '' ?>>Discursiva</option>
          </select>
        </div>
        <div class="campo">
          <label for="number">Número</label>
          <input type="number" id="number" name="number" min="1" value="<?= $valor('number') ?>">
        </div>
        <div class="campo">
          <label for="answer_key">Gabarito</label>
          <input type="text" id="answer_key" name="answer_key" maxlength="10" value="<?= $valor('answer_key') ?>" placeholder="A">
        </div>
        <div class="campo campo--largo">
          <label for="statement">Enunciado</label>
          <textarea id="statement" name="statement" rows="4"><?= $valor('statement') ?></textarea>
        </div>
      </div>

      <?php if ($editando): ?>
        <fieldset class="mt-3">
          <legend>Alternativas</legend>
          <?php
          $existentes = [];
          foreach ($alternativas ?? [] as $alternativa) {
              $existentes[$alternativa['letter']] = $alternativa;
          }
          foreach (['A', 'B', 'C', 'D', 'E'] as $letra):
              $atual = $existentes[$letra] ?? null;
          ?>
            <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.4rem">
              <strong style="width:20px"><?= $letra ?></strong>
              <input type="text" name="options[<?= $letra ?>][content]" value="<?= e($atual['content'] ?? '') ?>" placeholder="Texto da alternativa <?= $letra ?>">
              <label class="checkbox nowrap">
                <input type="checkbox" name="options[<?= $letra ?>][is_correct]" value="1" <?= !empty($atual['is_correct']) ? 'checked' : '' ?>>
                correta
              </label>
            </div>
          <?php endforeach; ?>
          <span class="ajuda">Opcional — útil para montar provas dentro do sistema.</span>
        </fieldset>
      <?php endif; ?>

      <div class="acoes-form">
        <button class="botao" type="submit"><?= $editando ? 'Salvar questão' : 'Cadastrar questão' ?></button>
        <a class="botao botao--secundario" href="<?= url('/questoes') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>
