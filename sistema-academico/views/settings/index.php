<?php
$v = static fn (string $chave) => e($valores[$chave] ?? \App\Models\Setting::DEFAULTS[$chave] ?? '');
$padrao = static fn (string $chave) => e($padroes[$chave] ?? '');
?>
<div class="pagina__cabecalho">
  <div>
    <h1>Configurações</h1>
    <p class="mudo mb-0">Os critérios abaixo alimentam todos os indicadores, classificações e alertas do sistema.</p>
  </div>
  <div class="pagina__acoes">
    <a class="botao botao--secundario" href="<?= url('/configuracoes/usuarios') ?>">Usuários</a>
  </div>
</div>

<?php if (!empty($errors)): ?>
  <div class="aviso aviso--error"><span>✖</span><div>
    <?php foreach ($errors as $ms): foreach ($ms as $m): ?><div><?= e($m) ?></div><?php endforeach; endforeach; ?>
  </div></div>
<?php endif; ?>

<form method="post" action="<?= url('/configuracoes') ?>">
  <?= csrf_field() ?>

  <div class="cartao">
    <div class="cartao__cabecalho">
      <h2>Faixas de classificação por aproveitamento</h2>
      <span class="etiqueta etiqueta--neutro">padrão: 80 / 60</span>
    </div>
    <div class="cartao__corpo">
      <p class="pequeno mudo">
        Definem quando um conteúdo é considerado <strong>domínio</strong>, <strong>intermediário</strong> ou
        <strong>dificuldade</strong> para cada aluno, turma e disciplina.
      </p>
      <div class="form-grade">
        <div class="campo">
          <label for="faixa_dominio">Domínio a partir de (%)</label>
          <input type="number" id="faixa_dominio" name="faixa_dominio" step="1" min="1" max="100" value="<?= $v('faixa_dominio') ?>">
          <span class="ajuda">Padrão: <?= $padrao('faixa_dominio') ?>%</span>
        </div>
        <div class="campo">
          <label for="faixa_intermediario">Intermediário a partir de (%)</label>
          <input type="number" id="faixa_intermediario" name="faixa_intermediario" step="1" min="0" max="99" value="<?= $v('faixa_intermediario') ?>">
          <span class="ajuda">Abaixo disso: dificuldade. Padrão: <?= $padrao('faixa_intermediario') ?>%</span>
        </div>
        <div class="campo">
          <label for="min_questoes_assunto">Mínimo de questões por assunto</label>
          <input type="number" id="min_questoes_assunto" name="min_questoes_assunto" step="1" min="1" max="100" value="<?= $v('min_questoes_assunto') ?>">
          <span class="ajuda">Abaixo disso o assunto aparece como "amostra insuficiente".</span>
        </div>
      </div>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho">
      <h2>Índice de Desenvolvimento</h2>
      <span class="etiqueta etiqueta--roxo">
        em uso: <?= round($pesos['desempenho'] * 100) ?>/<?= round($pesos['evolucao'] * 100) ?>/<?= round($pesos['frequencia'] * 100) ?>/<?= round($pesos['consistencia'] * 100) ?>
      </span>
    </div>
    <div class="cartao__corpo">
      <p class="pequeno mudo">
        ID = peso<sub>desempenho</sub> × média + peso<sub>evolução</sub> × score de evolução +
        peso<sub>frequência</sub> × frequência + peso<sub>consistência</sub> × score de consistência.
        Se a soma dos pesos não for 1, o sistema normaliza automaticamente.
      </p>
      <div class="form-grade">
        <div class="campo">
          <label for="peso_desempenho">Peso do desempenho</label>
          <input type="number" id="peso_desempenho" name="peso_desempenho" step="0.05" min="0" max="1" value="<?= $v('peso_desempenho') ?>">
        </div>
        <div class="campo">
          <label for="peso_evolucao">Peso da evolução</label>
          <input type="number" id="peso_evolucao" name="peso_evolucao" step="0.05" min="0" max="1" value="<?= $v('peso_evolucao') ?>">
        </div>
        <div class="campo">
          <label for="peso_frequencia">Peso da frequência</label>
          <input type="number" id="peso_frequencia" name="peso_frequencia" step="0.05" min="0" max="1" value="<?= $v('peso_frequencia') ?>">
        </div>
        <div class="campo">
          <label for="peso_consistencia">Peso da consistência</label>
          <input type="number" id="peso_consistencia" name="peso_consistencia" step="0.05" min="0" max="1" value="<?= $v('peso_consistencia') ?>">
        </div>
        <div class="campo">
          <label for="id_evolucao">Corte "em evolução" (ID ≥)</label>
          <input type="number" id="id_evolucao" name="id_evolucao" step="1" min="1" max="100" value="<?= $v('id_evolucao') ?>">
        </div>
        <div class="campo">
          <label for="id_atencao">Corte "precisa de atenção" (ID &lt;)</label>
          <input type="number" id="id_atencao" name="id_atencao" step="1" min="0" max="99" value="<?= $v('id_atencao') ?>">
        </div>
        <div class="campo">
          <label for="fator_evolucao">Fator de normalização da evolução</label>
          <input type="number" id="fator_evolucao" name="fator_evolucao" step="0.5" min="0.1" max="50" value="<?= $v('fator_evolucao') ?>">
          <span class="ajuda">score = 50 + tendência × fator (limitado a 0–100).</span>
        </div>
        <div class="campo">
          <label for="fator_consistencia">Fator de normalização da consistência</label>
          <input type="number" id="fator_consistencia" name="fator_consistencia" step="0.5" min="0.1" max="20" value="<?= $v('fator_consistencia') ?>">
          <span class="ajuda">score = 100 − desvio × fator (limitado a 0–100).</span>
        </div>
        <div class="campo">
          <label for="min_avaliacoes_indice">Mínimo de avaliações para o índice</label>
          <input type="number" id="min_avaliacoes_indice" name="min_avaliacoes_indice" step="1" min="1" max="50" value="<?= $v('min_avaliacoes_indice') ?>">
        </div>
        <div class="campo">
          <label for="min_avaliacoes_evolucao">Mínimo de avaliações para a tendência</label>
          <input type="number" id="min_avaliacoes_evolucao" name="min_avaliacoes_evolucao" step="1" min="2" max="50" value="<?= $v('min_avaliacoes_evolucao') ?>">
        </div>
        <div class="campo">
          <label for="janela_recente">Janela de avaliações recentes</label>
          <input type="number" id="janela_recente" name="janela_recente" step="1" min="1" max="20" value="<?= $v('janela_recente') ?>">
          <span class="ajuda">Quantas avaliações contam como "recentes" na evolução.</span>
        </div>
      </div>
    </div>
  </div>

  <div class="cartao">
    <div class="cartao__cabecalho"><h2>Alertas pedagógicos e frequência</h2></div>
    <div class="cartao__corpo">
      <div class="form-grade">
        <div class="campo">
          <label for="frequencia_minima">Frequência mínima (%)</label>
          <input type="number" id="frequencia_minima" name="frequencia_minima" step="1" min="0" max="100" value="<?= $v('frequencia_minima') ?>">
        </div>
        <div class="campo">
          <label for="media_alerta">Alerta de média abaixo de (%)</label>
          <input type="number" id="media_alerta" name="media_alerta" step="1" min="0" max="100" value="<?= $v('media_alerta') ?>">
        </div>
        <div class="campo">
          <label for="queda_alerta">Alerta de queda (p.p.)</label>
          <input type="number" id="queda_alerta" name="queda_alerta" step="1" min="1" max="100" value="<?= $v('queda_alerta') ?>">
        </div>
        <div class="campo">
          <label for="evolucao_alerta">Destaque de evolução (p.p.)</label>
          <input type="number" id="evolucao_alerta" name="evolucao_alerta" step="1" min="1" max="100" value="<?= $v('evolucao_alerta') ?>">
        </div>
        <div class="campo">
          <label for="limite_dificuldade">Limite de dificuldade (%)</label>
          <input type="number" id="limite_dificuldade" name="limite_dificuldade" step="1" min="0" max="100" value="<?= $v('limite_dificuldade') ?>">
          <span class="ajuda">Usado no relatório de dificuldades e no alerta de conteúdo crítico.</span>
        </div>
        <div class="campo">
          <label for="ocorrencias_persistente">Ocorrências para "dificuldade persistente"</label>
          <input type="number" id="ocorrencias_persistente" name="ocorrencias_persistente" step="1" min="2" max="20" value="<?= $v('ocorrencias_persistente') ?>">
        </div>
        <div class="campo campo--largo">
          <label class="checkbox">
            <input type="checkbox" name="justificada_conta" value="1" <?= ($valores['justificada_conta'] ?? '0') === '1' ? 'checked' : '' ?>>
            Faltas justificadas reduzem a frequência
          </label>
          <span class="ajuda">Desmarcado (padrão): faltas justificadas saem do cálculo.</span>
        </div>
      </div>

      <div class="acoes-form">
        <button class="botao" type="submit">Salvar configurações</button>
      </div>
    </div>
  </div>
</form>

<div class="cartao">
  <div class="cartao__cabecalho"><h2>Restaurar padrões</h2></div>
  <div class="cartao__corpo">
    <p class="pequeno mudo">Volta todos os parâmetros aos valores originais do projeto.</p>
    <form method="post" action="<?= url('/configuracoes/restaurar') ?>" data-confirmar="Restaurar todos os parâmetros para os valores padrão?">
      <?= csrf_field() ?>
      <button class="botao botao--secundario botao--pequeno" type="submit">Restaurar padrões</button>
    </form>
  </div>
</div>
