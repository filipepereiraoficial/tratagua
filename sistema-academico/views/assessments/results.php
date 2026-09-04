<div class="pagina__cabecalho">
  <div>
    <h1>Lançar resultados</h1>
    <p class="mudo mb-0">
      <strong><?= e($avaliacao['name']) ?></strong> · <?= e($avaliacao['class_code']) ?> — <?= e($avaliacao['subject_name']) ?>
      · valor máximo <?= num($avaliacao['max_score'], 2) ?>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes') ?>">Questões</a>
    <a class="botao botao--secundario" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/exportar') ?>">Exportar CSV</a>
    <a class="botao" href="<?= url('/avaliacoes/' . $avaliacao['id']) ?>">Ver análise</a>
  </div>
</div>

<?php if ($alunos === []): ?>
  <div class="cartao"><div class="cartao__corpo">
    <div class="vazio"><span class="vazio__icone">👥</span>Nenhum aluno vinculado à turma desta avaliação.
      <div class="mt-2"><a class="botao" href="<?= url('/turmas/' . $avaliacao['class_id']) ?>">Vincular alunos</a></div>
    </div>
  </div></div>
<?php else: ?>

<!-- Lançamento por questão -->
<div class="cartao">
  <div class="cartao__cabecalho">
    <h2>Por questão</h2>
    <span class="etiqueta etiqueta--info">habilita a análise por assunto e dificuldade</span>
  </div>
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if ($questoes === []): ?>
      <div class="vazio">
        <span class="vazio__icone">❓</span>
        Esta avaliação ainda não tem questões. Cadastre as questões para lançar acerto/erro por item —
        ou use o lançamento de nota direta abaixo.
        <div class="mt-2"><a class="botao" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes') ?>">Cadastrar questões</a></div>
      </div>
    <?php else: ?>
      <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/resultados') ?>">
        <?= csrf_field() ?>
        <div class="grade-resultados">
          <table>
            <thead>
              <tr>
                <th>Aluno</th>
                <?php foreach ($questoes as $questao): ?>
                  <th class="centro" title="<?= e($questao['topic_name'] ?? 'sem assunto') ?> · <?= e(rotulo('dificuldade', $questao['difficulty'])) ?> · <?= num($questao['points'], 2) ?> pt">
                    <?= $questao['number'] !== null ? (int) $questao['number'] : '·' ?>
                  </th>
                <?php endforeach; ?>
                <th class="centro">Nota</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($alunos as $aluno):
                $studentId = (int) $aluno['id'];
                $respostas = $matriz[$studentId] ?? [];
                $nota = $notas[$studentId] ?? null;
            ?>
              <tr data-escopo>
                <th>
                  <div style="display:flex;align-items:center;gap:.35rem;justify-content:space-between">
                    <a href="<?= url('/alunos/' . $studentId) ?>"><?= e($aluno['full_name']) ?></a>
                    <span style="display:flex;gap:.15rem">
                      <button type="button" class="botao botao--secundario botao--pequeno" title="Marcar todas como corretas"
                              data-marcar-todos="result[<?= $studentId ?>]" data-valor="correta">C</button>
                      <button type="button" class="botao botao--secundario botao--pequeno" title="Marcar todas como não respondidas"
                              data-marcar-todos="result[<?= $studentId ?>]" data-valor="nao_respondida">N</button>
                    </span>
                  </div>
                </th>
                <?php foreach ($questoes as $questao):
                    $qid = (int) $questao['id'];
                    $atual = $respostas[$qid]['result'] ?? '';
                ?>
                  <td class="centro">
                    <div class="marcador">
                      <span class="c">
                        <input type="radio" id="r<?= $studentId ?>-<?= $qid ?>-c" name="result[<?= $studentId ?>][<?= $qid ?>]" value="correta" <?= $atual === 'correta' ? 'checked' : '' ?>>
                        <label for="r<?= $studentId ?>-<?= $qid ?>-c" title="Acertou">C</label>
                      </span>
                      <span class="e">
                        <input type="radio" id="r<?= $studentId ?>-<?= $qid ?>-e" name="result[<?= $studentId ?>][<?= $qid ?>]" value="incorreta" <?= $atual === 'incorreta' ? 'checked' : '' ?>>
                        <label for="r<?= $studentId ?>-<?= $qid ?>-e" title="Errou">E</label>
                      </span>
                      <span class="n">
                        <input type="radio" id="r<?= $studentId ?>-<?= $qid ?>-n" name="result[<?= $studentId ?>][<?= $qid ?>]" value="nao_respondida" <?= $atual === 'nao_respondida' ? 'checked' : '' ?>>
                        <label for="r<?= $studentId ?>-<?= $qid ?>-n" title="Não respondeu">N</label>
                      </span>
                    </div>
                  </td>
                <?php endforeach; ?>
                <td class="centro nowrap">
                  <?php if ($nota): ?>
                    <strong><?= num($nota['score'], 2) ?></strong><br>
                    <span class="etiqueta etiqueta--<?= faixa_classe((float) $nota['percentage']) ?>"><?= pct($nota['percentage'], 0) ?></span>
                  <?php else: ?><span class="mudo">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="cartao__corpo" style="border-top:1px solid var(--cinza-200)">
          <button class="botao" type="submit">Salvar resultados e recalcular</button>
          <span class="pequeno mudo" style="margin-left:.6rem">
            C = acertou · E = errou · N = não respondeu. A nota, o percentual e os gráficos são recalculados na hora.
          </span>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Lançamento por nota direta -->
<div class="cartao">
  <div class="cartao__cabecalho">
    <h2>Nota direta</h2>
    <span class="etiqueta etiqueta--neutro">sem detalhamento por questão</span>
  </div>
  <div class="cartao__corpo cartao__corpo--liso">
    <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/notas') ?>">
      <?= csrf_field() ?>
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead><tr><th>Aluno</th><th style="width:130px">Nota (0 a <?= num($avaliacao['max_score'], 1) ?>)</th><th class="num">Aproveitamento</th><th>Origem</th></tr></thead>
          <tbody>
          <?php foreach ($alunos as $aluno):
              $studentId = (int) $aluno['id'];
              $nota = $notas[$studentId] ?? null;
              $manual = $nota && (int) $nota['is_manual'] === 1;
          ?>
            <tr>
              <td><?= e($aluno['full_name']) ?></td>
              <td>
                <input type="number" name="score[<?= $studentId ?>]" step="0.01" min="0" max="<?= e($avaliacao['max_score']) ?>"
                       value="<?= $manual ? e($nota['score']) : '' ?>"
                       <?= $nota && !$manual ? 'placeholder="' . e(num($nota['score'], 2)) . ' (por questão)" disabled' : '' ?>>
              </td>
              <td class="num"><?= $nota ? pct($nota['percentage'], 1) : '—' ?></td>
              <td class="pequeno">
                <?php if (!$nota): ?><span class="mudo">sem lançamento</span>
                <?php elseif ($manual): ?><span class="etiqueta etiqueta--neutro">nota direta</span>
                <?php else: ?><span class="etiqueta etiqueta--info">por questão</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="cartao__corpo" style="border-top:1px solid var(--cinza-200)">
        <button class="botao botao--secundario" type="submit">Salvar notas diretas</button>
        <span class="pequeno mudo" style="margin-left:.6rem">
          Alunos com resultado por questão têm o campo bloqueado — a nota deles vem do detalhamento.
        </span>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>
