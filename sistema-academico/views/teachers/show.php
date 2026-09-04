<div class="pagina__cabecalho">
  <div style="display:flex;gap:.9rem;align-items:center">
    <span class="avatar" style="width:46px;height:46px;font-size:1rem"><?= e(iniciais($professor['name'])) ?></span>
    <div>
      <h1 style="margin-bottom:.15rem"><?= e($professor['name']) ?></h1>
      <p class="mudo mb-0 pequeno">
        <?= e($professor['email']) ?>
        <?= $professor['qualification'] ? ' · ' . e($professor['qualification']) : '' ?>
        · <span class="etiqueta etiqueta--<?= $professor['role'] === 'admin' ? 'roxo' : 'info' ?>"><?= e(rotulo('papel', $professor['role'])) ?></span>
        <span class="etiqueta etiqueta--<?= (int) $professor['is_active'] === 1 ? 'bom' : 'neutro' ?>">
          <?= (int) $professor['is_active'] === 1 ? 'Ativo' : 'Inativo' ?></span>
      </p>
    </div>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/professores') ?>">Voltar</a>
    <a class="botao" href="<?= url('/professores/' . $professor['id'] . '/editar') ?>">Editar</a>
  </div>
</div>

<div class="indicadores">
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Ofertas sob responsabilidade</div>
    <div class="indicador__valor"><?= count($ofertas) ?></div>
    <div class="indicador__nota">turma × disciplina</div>
  </div>
  <div class="indicador indicador--neutro">
    <div class="indicador__rotulo">Aulas / Avaliações</div>
    <div class="indicador__valor" style="font-size:1.35rem"><?= (int) $ensino['aulas'] ?> / <?= (int) $ensino['avaliacoes'] ?></div>
    <div class="indicador__nota">registradas por ele</div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($media) ?>">
    <div class="indicador__rotulo">Média das turmas dele</div>
    <div class="indicador__valor"><?= $media !== null ? pct($media) : '—' ?></div>
  </div>
  <div class="indicador indicador--<?= faixa_classe($acertos['pct_acertos']) ?>">
    <div class="indicador__rotulo">% de acertos</div>
    <div class="indicador__valor"><?= $acertos['pct_acertos'] !== null ? pct($acertos['pct_acertos']) : '—' ?></div>
    <div class="indicador__nota"><?= (int) $acertos['total'] ?> respostas</div>
  </div>
  <div class="indicador indicador--bom">
    <div class="indicador__rotulo">Alunos em evolução</div>
    <div class="indicador__valor"><?= (int) $classes['evolucao'] ?></div>
  </div>
  <div class="indicador indicador--ruim">
    <div class="indicador__rotulo">Precisam de atenção</div>
    <div class="indicador__valor"><?= (int) $classes['atencao'] ?></div>
    <div class="indicador__nota">nas turmas dele</div>
  </div>
</div>

<div class="colunas colunas--2-1">
  <div>
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Turmas e disciplinas</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($ofertas === []): ?>
          <div class="vazio">
            <span class="vazio__icone">🧭</span>
            Este professor ainda não responde por nenhuma turma. Vincule uma oferta ao lado —
            sem isso, o painel dele fica vazio.
          </div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela">
              <thead><tr><th>Turma</th><th>Disciplina</th><th>Curso</th><th class="num">Alunos</th>
                <th class="num">Aulas</th><th class="num">Avaliações</th><th class="direita">Ação</th></tr></thead>
              <tbody>
              <?php foreach ($ofertas as $oferta): ?>
                <tr>
                  <td><a href="<?= url('/turmas/' . $oferta['class_id']) ?>"><strong><?= e($oferta['class_code']) ?></strong></a>
                    <div class="mudo pequeno"><?= (int) $oferta['year'] ?></div></td>
                  <td><a href="<?= url('/disciplinas/' . $oferta['subject_id']) ?>"><?= e($oferta['subject_name']) ?></a></td>
                  <td class="pequeno mudo"><?= e($oferta['course_name']) ?></td>
                  <td class="num"><?= (int) $oferta['alunos'] ?></td>
                  <td class="num"><?= (int) $oferta['aulas'] ?></td>
                  <td class="num"><?= (int) $oferta['avaliacoes'] ?></td>
                  <td class="direita">
                    <form method="post" action="<?= url('/professores/' . $professor['id'] . '/desvincular') ?>"
                          data-confirmar="Remover este vínculo? A oferta ficará sem professor responsável.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="class_subject_id" value="<?= (int) $oferta['id'] ?>">
                      <button class="botao botao--secundario botao--pequeno" type="submit">✕</button>
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

    <div class="cartao">
      <div class="cartao__cabecalho">
        <h2>Alunos atendidos por este professor</h2>
        <span class="etiqueta etiqueta--neutro"><?= count($ranking) ?> aluno(s)</span>
      </div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($ranking === []): ?>
          <div class="vazio pequeno">Nenhum aluno nas turmas deste professor.</div>
        <?php else: ?>
          <div class="tabela-rolagem">
            <table class="tabela tabela--compacta">
              <thead><tr><th>#</th><th>Aluno</th><th class="num">Média</th><th class="num">Freq.</th>
                <th class="num">Índice</th><th>Situação</th></tr></thead>
              <tbody>
              <?php foreach ($ranking as $linha): ?>
                <?php $classe = match ($linha['classificacao']) {
                    'evolucao' => 'bom', 'intermediario' => 'medio', 'atencao' => 'ruim', default => 'neutro' }; ?>
                <tr>
                  <td class="num"><?= $linha['posicao'] ?? '—' ?></td>
                  <td><a href="<?= url('/alunos/' . $linha['id']) ?>"><?= e($linha['full_name']) ?></a></td>
                  <td class="num"><?= pct($linha['media'], 0) ?></td>
                  <td class="num"><?= pct($linha['frequencia'], 0) ?></td>
                  <td class="num"><strong><?= num($linha['indice']) ?></strong></td>
                  <td><span class="etiqueta etiqueta--<?= $classe ?>"><?= e(rotulo('classificacao', $linha['classificacao'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Vincular a uma turma/disciplina</h2></div>
      <div class="cartao__corpo">
        <?php if ($disponiveis === []): ?>
          <p class="pequeno mudo mb-0">Não há outras ofertas para vincular. Crie a turma e vincule a disciplina primeiro.</p>
        <?php else: ?>
          <form method="post" action="<?= url('/professores/' . $professor['id'] . '/vincular') ?>">
            <?= csrf_field() ?>
            <div class="campo mb-2">
              <label for="class_subject_id">Turma — Disciplina</label>
              <select id="class_subject_id" name="class_subject_id" required>
                <option value="">Selecione…</option>
                <?php foreach ($disponiveis as $opcao): ?>
                  <option value="<?= (int) $opcao['id'] ?>">
                    <?= e($opcao['class_code']) ?> — <?= e($opcao['subject_name']) ?>
                    <?= $opcao['teacher_name'] ? '(hoje com ' . e($opcao['teacher_name']) . ')' : '(sem professor)' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="ajuda">Vincular transfere a responsabilidade se a oferta já tiver outro professor.</span>
            </div>
            <button class="botao botao--bloco botao--pequeno" type="submit">Vincular</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Assuntos das turmas dele</h2></div>
      <div class="cartao__corpo cartao__corpo--liso">
        <?php if ($assuntos === []): ?>
          <div class="vazio pequeno">Sem respostas registradas ainda.</div>
        <?php else: ?>
          <ul class="lista-conteudo">
            <?php foreach (array_slice($assuntos, 0, 8) as $assunto): ?>
              <li>
                <span class="nome"><?= e($assunto['topic_name']) ?><br>
                  <small class="mudo"><?= e($assunto['subject_name']) ?></small></span>
                <span class="progresso-linha" style="flex:0 0 120px">
                  <span class="progresso"><span class="progresso__barra progresso__barra--<?= faixa_classe($assunto['aproveitamento']) ?>"
                    style="width:<?= (float) ($assunto['aproveitamento'] ?? 0) ?>%"></span></span>
                  <span><?= pct($assunto['aproveitamento'], 0) ?></span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Acesso</h2></div>
      <div class="cartao__corpo">
        <p class="pequeno mudo">Último acesso: <?= datahora_br($professor['last_login_at'], 'nunca') ?></p>
        <form method="post" action="<?= url('/professores/' . $professor['id'] . '/senha') ?>"
              style="display:flex;gap:.4rem;align-items:flex-end">
          <?= csrf_field() ?>
          <div class="campo" style="flex:1">
            <label for="password">Redefinir senha</label>
            <input type="password" id="password" name="password" minlength="8" placeholder="nova senha">
          </div>
          <button class="botao botao--secundario botao--pequeno" type="submit">Redefinir</button>
        </form>
      </div>
    </div>

    <div class="cartao">
      <div class="cartao__cabecalho"><h2>Excluir professor</h2></div>
      <div class="cartao__corpo">
        <?php if ($blockers !== []): ?>
          <p class="pequeno mudo mb-0">Não é possível excluir: <?= e(implode(', ', $blockers)) ?>. Transfira os vínculos antes.</p>
        <?php else: ?>
          <form method="post" action="<?= url('/professores/' . $professor['id'] . '/excluir') ?>"
                data-confirmar="Excluir este professor?">
            <?= csrf_field() ?>
            <button class="botao botao--perigo botao--pequeno" type="submit">Excluir professor</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
