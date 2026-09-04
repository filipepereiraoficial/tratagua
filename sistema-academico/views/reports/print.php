<div class="folha__cabecalho">
  <h1 style="margin-bottom:.2rem"><?= e($relatorio['titulo']) ?></h1>
  <p class="mudo mb-0 pequeno">
    Painel Pedagógico · <?= e($filtros_descricao) ?> · Emitido em <?= date('d/m/Y H:i') ?>
  </p>
</div>

<?php if (!empty($relatorio['resumo'])): ?>
  <table class="tabela tabela--compacta mb-3" style="max-width:520px">
    <tbody>
    <?php foreach ($relatorio['resumo'] as $rotuloResumo => $valorResumo): ?>
      <tr><th style="width:60%"><?= e($rotuloResumo) ?></th><td class="num"><?= e((string) $valorResumo) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if (!empty($relatorio['aviso'])): ?>
  <p class="mudo"><?= e($relatorio['aviso']) ?></p>
<?php elseif ($relatorio['linhas'] === []): ?>
  <p class="mudo">Nenhum dado para os filtros selecionados.</p>
<?php else: ?>
  <table class="tabela tabela--compacta">
    <thead><tr><?php foreach ($relatorio['colunas'] as $coluna): ?><th><?= e($coluna) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($relatorio['linhas'] as $linha): ?>
      <tr>
        <?php foreach ($linha as $celula): ?>
          <td class="<?= is_numeric($celula) ? 'num' : '' ?>">
            <?= $celula === null || $celula === '' ? '—' : (is_float($celula) ? e(num($celula, 2)) : e((string) $celula)) ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<div class="folha__rodape">
  Documento gerado automaticamente pelo Painel Pedagógico. Os indicadores seguem as regras
  configuradas em Configurações (faixas de classificação e pesos do Índice de Desenvolvimento).
</div>
