<div class="pagina__cabecalho">
  <div>
    <h1>Chamada</h1>
    <p class="mudo mb-0">
      <strong><?= e($aula['title']) ?></strong> · <?= data_br($aula['lesson_date']) ?>
      · <?= e($aula['class_code']) ?> — <?= e($aula['subject_name']) ?>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/aulas/' . $aula['id'] . '/editar') ?>">Editar aula</a>
    <a class="botao botao--secundario" href="<?= url('/aulas') ?>">Voltar</a>
  </div>
</div>

<?php if ($topicos): ?>
  <div class="aviso aviso--info">
    <span>📚</span>
    <div><strong>Tópicos desta aula:</strong> <?= e(implode(' · ', array_column($topicos, 'name'))) ?></div>
  </div>
<?php endif; ?>

<?php if ($alunos === []): ?>
  <div class="cartao"><div class="cartao__corpo">
    <div class="vazio"><span class="vazio__icone">👥</span>
      Nenhum aluno vinculado a esta turma.
      <div class="mt-2"><a class="botao" href="<?= url('/turmas/' . $aula['class_id']) ?>">Vincular alunos</a></div>
    </div>
  </div></div>
<?php else: ?>
<form method="post" action="<?= url('/aulas/' . $aula['id'] . '/frequencia') ?>" data-escopo>
  <?= csrf_field() ?>
  <div class="cartao">
    <div class="cartao__cabecalho">
      <h2><?= count($alunos) ?> aluno(s)</h2>
      <div class="cartao__acoes">
        <button type="button" class="botao botao--secundario botao--pequeno" data-marcar-todos="status" data-valor="presente">Marcar todos presentes</button>
        <button type="button" class="botao botao--secundario botao--pequeno" data-marcar-todos="status" data-valor="falta">Marcar todos ausentes</button>
      </div>
    </div>
    <div class="cartao__corpo cartao__corpo--liso">
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead>
            <tr>
              <th>Aluno</th>
              <th style="width:340px">Presença</th>
              <th style="width:120px">Participação</th>
              <th>Observação</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($alunos as $aluno):
              $id = (int) $aluno['id'];
              $registro = $registros[$id] ?? null;
              $statusAtual = $registro['status'] ?? 'presente';
          ?>
            <tr>
              <td>
                <a href="<?= url('/alunos/' . $id) ?>"><?= e($aluno['full_name']) ?></a>
              </td>
              <td>
                <div style="display:flex;gap:.8rem;flex-wrap:wrap">
                  <?php foreach (['presente' => 'Presente', 'atraso' => 'Atraso', 'falta' => 'Falta', 'falta_justificada' => 'Justificada'] as $chave => $texto): ?>
                    <label class="checkbox">
                      <input type="radio" name="status[<?= $id ?>]" value="<?= $chave ?>" <?= $statusAtual === $chave ? 'checked' : '' ?>>
                      <?= $texto ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <select name="participation[<?= $id ?>]">
                  <option value="">—</option>
                  <?php for ($n = 0; $n <= 5; $n++): ?>
                    <option value="<?= $n ?>" <?= (string) ($registro['participation'] ?? '') === (string) $n ? 'selected' : '' ?>><?= $n ?></option>
                  <?php endfor; ?>
                </select>
              </td>
              <td>
                <input type="text" name="notes[<?= $id ?>]" maxlength="255" value="<?= e($registro['notes'] ?? '') ?>" placeholder="opcional">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="cartao__corpo" style="border-top:1px solid var(--cinza-200)">
      <button class="botao" type="submit">Salvar chamada</button>
      <span class="pequeno mudo" style="margin-left:.6rem">
        Participação de 0 a 5. Faltas justificadas não reduzem a frequência (configurável).
      </span>
    </div>
  </div>
</form>
<?php endif; ?>
