<div class="pagina__cabecalho">
  <div>
    <h1>Relatórios</h1>
    <p class="mudo mb-0"><?= e($filtros_descricao) ?></p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/relatorios/exportar', array_merge($filters, ['relatorio' => $tipo])) ?>">Exportar CSV</a>
    <a class="botao" href="<?= url('/relatorios/imprimir', array_merge($filters, ['relatorio' => $tipo])) ?>" target="_blank" rel="noopener">Imprimir / PDF</a>
  </div>
</div>

<div class="abas">
  <?php foreach ($tipos as $chave => $rotuloTipo): ?>
    <a class="<?= $tipo === $chave ? 'ativo' : '' ?>" href="<?= url('/relatorios', array_merge($filters, ['relatorio' => $chave])) ?>">
      <?= e($rotuloTipo) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="cartao">
  <div class="cartao__cabecalho"><h2>Filtros</h2></div>
  <div class="cartao__corpo">
    <form class="filtros" method="get" action="<?= url('/relatorios') ?>">
      <input type="hidden" name="relatorio" value="<?= e($tipo) ?>">
      <div class="campo">
        <label for="curso">Curso</label>
        <select id="curso" name="curso">
          <option value="">Todos</option>
          <?php foreach ($cursos as $curso): ?>
            <option value="<?= (int) $curso['id'] ?>" <?= ($filters['curso'] ?? '') == $curso['id'] ? 'selected' : '' ?>><?= e($curso['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="turma">Turma</label>
        <select id="turma" name="turma">
          <option value="">Todas</option>
          <?php foreach ($turmas as $turma): ?>
            <option value="<?= (int) $turma['id'] ?>" <?= ($filters['turma'] ?? '') == $turma['id'] ? 'selected' : '' ?>><?= e($turma['code']) ?> (<?= (int) $turma['year'] ?>)</option>
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
        <label for="assunto">Assunto</label>
        <select id="assunto" name="assunto" <?= $assuntos === [] ? 'disabled' : '' ?>>
          <option value="">Todos</option>
          <?php foreach ($assuntos as $assunto): ?>
            <option value="<?= (int) $assunto['id'] ?>" <?= ($filters['assunto'] ?? '') == $assunto['id'] ? 'selected' : '' ?>><?= e($assunto['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="tipo_aval">Tipo de avaliação</label>
        <select id="tipo_aval" name="tipo">
          <option value="">Todos</option>
          <?php foreach ($tipos_aval as $t): ?>
            <option value="<?= $t ?>" <?= ($filters['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= e(rotulo('tipo_avaliacao', $t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="dificuldade">Dificuldade</label>
        <select id="dificuldade" name="dificuldade">
          <option value="">Todas</option>
          <?php foreach ($dificuldades as $d): ?>
            <option value="<?= $d ?>" <?= ($filters['dificuldade'] ?? '') === $d ? 'selected' : '' ?>><?= e(rotulo('dificuldade', $d)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="status_aluno">Situação do aluno</label>
        <select id="status_aluno" name="status_aluno">
          <option value="">Todas</option>
          <?php foreach (['ativo', 'inativo', 'concluido'] as $s): ?>
            <option value="<?= $s ?>" <?= ($filters['status_aluno'] ?? '') === $s ? 'selected' : '' ?>><?= e(rotulo('status_aluno', $s)) ?></option>
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
        <button class="botao" type="submit">Gerar</button>
        <a class="botao botao--secundario" href="<?= url('/relatorios', ['relatorio' => $tipo]) ?>">Limpar</a>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($relatorio['resumo'])): ?>
  <div class="indicadores">
    <?php foreach ($relatorio['resumo'] as $rotuloResumo => $valorResumo): ?>
      <div class="indicador indicador--neutro">
        <div class="indicador__rotulo"><?= e($rotuloResumo) ?></div>
        <div class="indicador__valor" style="font-size:1.25rem"><?= e((string) $valorResumo) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="cartao">
  <div class="cartao__cabecalho">
    <h2><?= e($relatorio['titulo']) ?></h2>
    <span class="etiqueta etiqueta--neutro"><?= count($relatorio['linhas']) ?> linha(s)</span>
  </div>
  <div class="cartao__corpo cartao__corpo--liso">
    <?php if (!empty($relatorio['aviso'])): ?>
      <div class="vazio"><span class="vazio__icone">ℹ</span><?= e($relatorio['aviso']) ?></div>
    <?php elseif ($relatorio['linhas'] === []): ?>
      <div class="vazio"><span class="vazio__icone">📄</span>Nenhum dado para os filtros selecionados.</div>
    <?php else: ?>
      <div class="tabela-rolagem">
        <table class="tabela tabela--compacta">
          <thead>
            <tr><?php foreach ($relatorio['colunas'] as $coluna): ?><th><?= e($coluna) ?></th><?php endforeach; ?></tr>
          </thead>
          <tbody>
          <?php foreach ($relatorio['linhas'] as $linha): ?>
            <tr>
              <?php foreach ($linha as $coluna => $celula): ?>
                <td class="<?= is_numeric($celula) ? 'num' : '' ?>">
                  <?php
                  if ($celula === null || $celula === '') {
                      echo '<span class="mudo">—</span>';
                  } elseif (str_contains($coluna, '%') || in_array($coluna, ['Média', 'Aproveitamento', 'Frequência', 'Consistência'], true)) {
                      echo '<span class="etiqueta etiqueta--' . faixa_classe((float) $celula) . '">' . pct($celula, 1) . '</span>';
                  } elseif (is_float($celula)) {
                      echo e(num($celula, 2));
                  } else {
                      echo e((string) $celula);
                  }
                  ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
