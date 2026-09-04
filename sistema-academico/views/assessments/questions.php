<div class="pagina__cabecalho">
  <div>
    <h1>Questões</h1>
    <p class="mudo mb-0">
      <strong><?= e($avaliacao['name']) ?></strong> · <?= e($avaliacao['class_code']) ?> — <?= e($avaliacao['subject_name']) ?>
      · <?= data_br($avaliacao['assessment_date']) ?>
    </p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/avaliacoes/' . $avaliacao['id']) ?>">Análise</a>
    <a class="botao" href="<?= url('/avaliacoes/' . $avaliacao['id'] . '/resultados') ?>">Lançar resultados →</a>
  </div>
</div>

<?php
$diferenca = abs($pontos - (float) $avaliacao['max_score']);
if (count($questoes) > 0 && $diferenca > 0.01):
?>
  <div class="aviso aviso--warning">
    <span>⚠</span>
    <div>
      A soma dos pontos das questões é <strong><?= num($pontos, 2) ?></strong>, diferente do valor máximo da
      avaliação (<strong><?= num($avaliacao['max_score'], 2) ?></strong>). O sistema converte proporcionalmente,
      mas ajustar os pontos deixa o lançamento mais transparente.
    </div>
  </div>
<?php endif; ?>

<?php if (empty($topicos)): ?>
  <div class="aviso aviso--info">
    <span>ℹ</span>
    <div>
      A disciplina <strong><?= e($avaliacao['subject_name']) ?></strong> ainda não tem assuntos cadastrados.
      <a href="<?= url('/disciplinas/' . $avaliacao['subject_id']) ?>">Cadastre os assuntos</a> para que o sistema
      identifique automaticamente as dificuldades por conteúdo.
    </div>
  </div>
<?php endif; ?>

<div class="colunas colunas--2-1">
  <div>
    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2><?= count($questoes) ?> questão(ões)</h2>
        <span class="etiqueta etiqueta--neutro">total: <?= num($pontos, 2) ?> pontos</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($questoes === []): ?>
          <div class="vazio"><span class="vazio__icone">❓</span>Nenhuma questão cadastrada. Use o painel ao lado para criar em lote.</div>
        <?php else: ?>
          <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes/salvar') ?>">
            <?= csrf_field() ?>
            <div class="tabela-rolagem">
              <table class="tabela tabela--compacta">
                <thead>
                  <tr>
                    <th style="width:44px">#</th>
                    <th style="min-width:200px">Assunto / tópico</th>
                    <th style="width:110px">Dificuldade</th>
                    <th style="width:82px">Pontos</th>
                    <th style="width:80px">Gabarito</th>
                    <th>Enunciado (opcional)</th>
                    <th style="width:60px">Excluir</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($questoes as $questao): $id = (int) $questao['id']; ?>
                  <tr>
                    <td class="num"><?= $questao['number'] !== null ? (int) $questao['number'] : '—' ?></td>
                    <td>
                      <select name="topic_id[<?= $id ?>]">
                        <option value="">— Sem assunto —</option>
                        <?php foreach ($topicos as $topico): ?>
                          <option value="<?= (int) $topico['id'] ?>" <?= (string) $questao['topic_id'] === (string) $topico['id'] ? 'selected' : '' ?>>
                            <?= e($topico['label']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <select name="difficulty[<?= $id ?>]">
                        <?php foreach (['facil', 'medio', 'dificil'] as $nivel): ?>
                          <option value="<?= $nivel ?>" <?= $questao['difficulty'] === $nivel ? 'selected' : '' ?>><?= e(rotulo('dificuldade', $nivel)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td><input type="number" name="points[<?= $id ?>]" step="0.01" min="0.01" value="<?= e($questao['points']) ?>"></td>
                    <td><input type="text" name="answer_key[<?= $id ?>]" maxlength="10" value="<?= e($questao['answer_key']) ?>" placeholder="A"></td>
                    <td><input type="text" name="statement[<?= $id ?>]" value="<?= e($questao['statement']) ?>" placeholder="Resumo da questão"></td>
                    <td class="centro"><input type="checkbox" name="delete[]" value="<?= $id ?>"></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="cartao__corpo" style="border-top:1px solid var(--cinza-200)">
              <button class="botao" type="submit">Salvar questões</button>
              <span class="pequeno mudo" style="margin-left:.6rem">As notas já lançadas são recalculadas automaticamente.</span>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Criar questões em lote</h2></div>
      <div class="cartao__corpo">
        <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes/lote') ?>">
          <?= csrf_field() ?>
          <div class="campo mb-2">
            <label for="quantity">Quantidade *</label>
            <input type="number" id="quantity" name="quantity" min="1" max="200" value="10" required>
          </div>
          <div class="campo mb-2">
            <label for="points-lote">Pontos por questão</label>
            <input type="number" id="points-lote" name="points" step="0.01" min="0.01" value="1">
          </div>
          <div class="campo mb-2">
            <label for="difficulty-lote">Dificuldade</label>
            <select id="difficulty-lote" name="difficulty">
              <option value="facil">Fácil</option>
              <option value="medio" selected>Médio</option>
              <option value="dificil">Difícil</option>
            </select>
          </div>
          <div class="campo mb-2">
            <label for="topic-lote">Assunto (opcional)</label>
            <select id="topic-lote" name="topic_id">
              <option value="">— Definir depois —</option>
              <?php foreach ($topicos as $topico): ?>
                <option value="<?= (int) $topico['id'] ?>"><?= e($topico['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="botao botao--bloco" type="submit">Criar questões</button>
        </form>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Adicionar uma questão</h2></div>
      <div class="cartao__corpo">
        <form method="post" action="<?= url('/avaliacoes/' . $avaliacao['id'] . '/questoes') ?>">
          <?= csrf_field() ?>
          <div class="campo mb-2">
            <label for="number">Número</label>
            <input type="number" id="number" name="number" min="1" value="<?= count($questoes) + 1 ?>">
          </div>
          <div class="campo mb-2">
            <label for="topic_id">Assunto</label>
            <select id="topic_id" name="topic_id">
              <option value="">— Sem assunto —</option>
              <?php foreach ($topicos as $topico): ?>
                <option value="<?= (int) $topico['id'] ?>"><?= e($topico['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo mb-2">
            <label for="difficulty">Dificuldade *</label>
            <select id="difficulty" name="difficulty" required>
              <option value="facil">Fácil</option>
              <option value="medio" selected>Médio</option>
              <option value="dificil">Difícil</option>
            </select>
          </div>
          <div class="campo mb-2">
            <label for="points">Pontos *</label>
            <input type="number" id="points" name="points" step="0.01" min="0.01" value="1" required>
          </div>
          <div class="campo mb-2">
            <label for="type">Tipo</label>
            <select id="type" name="type">
              <option value="objetiva">Objetiva</option>
              <option value="discursiva">Discursiva</option>
            </select>
          </div>
          <div class="campo mb-2">
            <label for="answer_key">Gabarito</label>
            <input type="text" id="answer_key" name="answer_key" maxlength="10" placeholder="A">
          </div>
          <div class="campo mb-2">
            <label for="statement">Enunciado</label>
            <textarea id="statement" name="statement" rows="3"></textarea>
          </div>
          <button class="botao botao--bloco botao--secundario" type="submit">Adicionar questão</button>
        </form>
      </div>
    </div>
  </div>
</div>
