<?php
$editando = $aluno !== null;
$valor = static fn (string $campo, $padrao = '') => e(old($old ?? [], $campo, $editando ? ($aluno[$campo] ?? $padrao) : $padrao));
$turmaAtual = $editando ? ($aluno['class_id'] ?? null) : ($turma_pre ?? null);
?>
<div class="pagina__cabecalho">
  <div>
    <h1><?= $editando ? 'Editar aluno' : 'Novo aluno' ?></h1>
    <p class="mudo mb-0">Você pode vincular o aluno a uma turma agora ou depois.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url($editando ? '/alunos/' . $aluno['id'] : '/alunos') ?>">Cancelar</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $mensagens): foreach ($mensagens as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url($editando ? '/alunos/' . $aluno['id'] : '/alunos') ?>">
  <?= csrf_field() ?>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Dados pessoais</h2></div>
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo campo--largo <?= isset($errors['full_name']) ? 'campo--erro' : '' ?>">
          <label for="full_name">Nome completo *</label>
          <input type="text" id="full_name" name="full_name" required maxlength="150" value="<?= $valor('full_name') ?>">
          <?php if (isset($errors['full_name'])): ?><span class="erro-campo"><?= e($errors['full_name'][0]) ?></span><?php endif; ?>
        </div>
        <div class="campo <?= isset($errors['document']) ? 'campo--erro' : '' ?>">
          <label for="document">CPF ou identificador</label>
          <input type="text" id="document" name="document" maxlength="32" value="<?= $valor('document') ?>">
          <span class="ajuda">Opcional, mas não pode repetir.</span>
        </div>
        <div class="campo">
          <label for="birth_date">Data de nascimento</label>
          <input type="date" id="birth_date" name="birth_date" value="<?= $valor('birth_date') ?>">
        </div>
        <div class="campo <?= isset($errors['email']) ? 'campo--erro' : '' ?>">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" maxlength="150" value="<?= $valor('email') ?>">
        </div>
        <div class="campo">
          <label for="phone">Telefone</label>
          <input type="tel" id="phone" name="phone" maxlength="32" value="<?= $valor('phone') ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Situação e vínculo</h2></div>
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo">
          <label for="enrolled_at">Data de cadastro</label>
          <input type="date" id="enrolled_at" name="enrolled_at" value="<?= $valor('enrolled_at', date('Y-m-d')) ?>">
        </div>
        <div class="campo">
          <label for="status">Situação *</label>
          <select id="status" name="status" required>
            <?php foreach (['ativo', 'inativo', 'concluido'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editando ? $aluno['status'] : old($old ?? [], 'status', 'ativo')) === $s ? 'selected' : '' ?>>
                <?= e(rotulo('status_aluno', $s)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="class_id">Turma</label>
          <select id="class_id" name="class_id">
            <option value="0">— Sem turma —</option>
            <?php foreach ($turmas as $turma): ?>
              <option value="<?= (int) $turma['id'] ?>" <?= (string) $turmaAtual === (string) $turma['id'] ? 'selected' : '' ?>>
                <?= e($turma['code']) ?> · <?= e($turma['course_name']) ?> (<?= (int) $turma['year'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($editando): ?>
            <span class="ajuda">Trocar a turma encerra o vínculo atual e preserva o histórico.</span>
          <?php endif; ?>
        </div>
        <div class="campo campo--largo">
          <label for="notes">Observações pedagógicas</label>
          <textarea id="notes" name="notes" rows="4" placeholder="Contexto do aluno, acompanhamento, combinados…"><?= $valor('notes') ?></textarea>
        </div>
      </div>

      <div class="acoes-form">
        <button class="botao" type="submit"><?= $editando ? 'Salvar alterações' : 'Cadastrar aluno' ?></button>
        <a class="botao botao--secundario" href="<?= url($editando ? '/alunos/' . $aluno['id'] : '/alunos') ?>">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<?php if ($editando): ?>
  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Excluir aluno</h2></div>
    <div class="cartao__corpo">
      <p class="pequeno mudo">
        A exclusão remove notas, respostas e frequência do aluno. Para manter o histórico,
        prefira alterar a situação para <strong>Inativo</strong>.
      </p>
      <form method="post" action="<?= url('/alunos/' . $aluno['id'] . '/excluir') ?>"
            data-confirmar="Excluir definitivamente este aluno e todo o seu histórico?">
        <?= csrf_field() ?>
        <label class="checkbox mb-2">
          <input type="checkbox" name="confirmar_historico" value="1">
          Confirmo que desejo excluir também os resultados já registrados
        </label>
        <button class="botao botao--perigo botao--pequeno" type="submit">Excluir aluno</button>
      </form>
    </div>
  </div>
<?php endif; ?>
