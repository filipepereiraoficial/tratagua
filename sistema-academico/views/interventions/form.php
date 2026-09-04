<?php
$editando = $registro !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($registro[$campo] ?? $padrao) : $padrao));
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= $editando ? 'Acompanhamento' : 'Novo acompanhamento' ?></h1>
    <p class="mudo mb-0">
      <?= $editando
        ? 'Registre o que foi feito e o resultado observado.'
        : 'A média e a frequência atuais do aluno serão guardadas como linha de base para medir o efeito.' ?>
    </p>
  </div>
  <div class="pagina__acoes"><a class="botao botao--secundario" href="<?= url('/acompanhamento') ?>">Voltar</a></div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<?php if ($editando && isset($efeito)): ?>
  <div class="aviso aviso--info">
    <span>📊</span>
    <div>
      <strong>Efeito medido desde a abertura:</strong>
      <?php if ($efeito['media'] === null): ?>
        sem linha de base registrada.
      <?php else: ?>
        média <?= $efeito['media'] >= 0 ? '+' : '' ?><?= num($efeito['media']) ?> p.p.
        (de <?= pct($registro['baseline_media'], 1) ?> para <?= pct($resumo['media'], 1) ?>)<?php
        if ($efeito['frequencia'] !== null): ?>,
        frequência <?= $efeito['frequencia'] >= 0 ? '+' : '' ?><?= num($efeito['frequencia']) ?> p.p.<?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/acompanhamento/' . $registro['id'] : '/acompanhamento') ?>">
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo">
          <label for="student_id">Aluno *</label>
          <?php if ($editando): ?>
            <input type="text" value="<?= e($registro['student_name']) ?>" disabled>
          <?php else: ?>
            <select id="student_id" name="student_id" required>
              <option value="">Selecione…</option>
              <?php foreach ($alunos as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (string) ($aluno_pre ?? '') === (string) $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['full_name']) ?><?= $a['class_code'] ? ' · ' . e($a['class_code']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="campo">
          <label for="class_subject_id">Turma / disciplina</label>
          <select id="class_subject_id" name="class_subject_id">
            <option value="">— Geral (não é de uma disciplina) —</option>
            <?php foreach ($ofertas as $o): ?>
              <option value="<?= (int) $o['id'] ?>" <?= (string) ($editando ? $registro['class_subject_id'] : '') === (string) $o['id'] ? 'selected' : '' ?>>
                <?= e($o['class_code']) ?> — <?= e($o['subject_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="type">Tipo *</label>
          <select id="type" name="type" required>
            <?php foreach ($tipos as $chave => $texto): ?>
              <option value="<?= $chave ?>" <?= ($editando ? $registro['type'] : 'conversa') === $chave ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="status">Situação *</label>
          <select id="status" name="status" required>
            <?php foreach ($situacoes as $chave => $texto): ?>
              <option value="<?= $chave ?>" <?= ($editando ? $registro['status'] : 'aberta') === $chave ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="due_date">Prazo</label>
          <input type="date" id="due_date" name="due_date" value="<?= $valor('due_date') ?>">
          <span class="ajuda">Quando pretende reavaliar o efeito.</span>
        </div>
        <div class="campo campo--largo">
          <label for="title">Título *</label>
          <input type="text" id="title" name="title" required maxlength="200"
                 value="<?= $editando ? e($registro['title']) : e(old($old ?? [], 'title', $titulo_pre ?? '')) ?>">
        </div>
        <div class="campo campo--largo">
          <label for="description">Situação observada</label>
          <textarea id="description" name="description" rows="3"
            placeholder="O que motivou o acompanhamento"><?= $valor('description') ?></textarea>
        </div>
        <div class="campo campo--largo">
          <label for="action_taken">Ação combinada / realizada</label>
          <textarea id="action_taken" name="action_taken" rows="3"
            placeholder="O que foi feito ou será feito"><?= $valor('action_taken') ?></textarea>
        </div>
        <?php if ($editando): ?>
          <div class="campo campo--largo">
            <label for="result_note">Resultado observado</label>
            <textarea id="result_note" name="result_note" rows="3"
              placeholder="O que mudou depois da intervenção"><?= $valor('result_note') ?></textarea>
          </div>
        <?php endif; ?>
      </div>
      <input type="hidden" name="alert_key" value="<?= $editando ? e($registro['alert_key']) : e($alerta_pre ?? '') ?>">

      <div class="acoes-form">
        <button class="botao" type="submit"><?= $editando ? 'Salvar' : 'Registrar acompanhamento' ?></button>
        <a class="botao botao--secundario" href="<?= url('/acompanhamento') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<?php if ($editando): ?>
  <form method="post" action="<?= url('/acompanhamento/' . $registro['id'] . '/excluir') ?>"
        data-confirmar="Excluir este acompanhamento?">
    <?= csrf_field() ?>
    <button class="botao botao--perigo botao--pequeno" type="submit">Excluir acompanhamento</button>
  </form>
<?php endif; ?>
