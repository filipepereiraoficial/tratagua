/* ===========================================================================
   Motor analítico — porte fiel de src/Services/AnalyticsService.php,
   RankingService.php e AlertService.php para o navegador.
   As fórmulas e os arredondamentos seguem docs/04-REGRAS-DE-CALCULO.md.
   =========================================================================== */

const PADROES = {
  faixa_dominio: 80, faixa_intermediario: 60,
  peso_desempenho: 0.40, peso_evolucao: 0.25, peso_frequencia: 0.15, peso_consistencia: 0.20,
  id_evolucao: 75, id_atencao: 55,
  min_questoes_assunto: 3, min_avaliacoes_evolucao: 3, min_avaliacoes_indice: 2,
  janela_recente: 3, fator_evolucao: 5, fator_consistencia: 2,
  frequencia_minima: 75, media_alerta: 60, queda_alerta: 10, evolucao_alerta: 10,
  limite_dificuldade: 60, ocorrencias_persistente: 3, justificada_conta: false,
};

const r2 = (v) => v === null ? null : Math.round(v * 100) / 100;
const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

function pesos(cfg) {
  const w = {
    desempenho: cfg.peso_desempenho, evolucao: cfg.peso_evolucao,
    frequencia: cfg.peso_frequencia, consistencia: cfg.peso_consistencia,
  };
  const soma = w.desempenho + w.evolucao + w.frequencia + w.consistencia;
  if (soma <= 0) return { desempenho: .4, evolucao: .25, frequencia: .15, consistencia: .2 };
  return {
    desempenho: w.desempenho / soma, evolucao: w.evolucao / soma,
    frequencia: w.frequencia / soma, consistencia: w.consistencia / soma,
  };
}

/** Média ponderada dos percentuais (peso = peso da avaliação). */
function mediaPonderada(notas) {
  let soma = 0, pesosTotal = 0;
  for (const n of notas) { soma += n.percentage * n.weight; pesosTotal += n.weight; }
  return pesosTotal > 0 ? r2(soma / pesosTotal) : null;
}

/** Coeficiente angular da reta de tendência: p.p. ganhos por avaliação. */
function tendencia(percentuais) {
  const n = percentuais.length;
  if (n < 2) return null;
  const mediaX = (n + 1) / 2;
  const mediaY = percentuais.reduce((a, b) => a + b, 0) / n;
  let num = 0, den = 0;
  percentuais.forEach((y, i) => { const dx = (i + 1) - mediaX; num += dx * (y - mediaY); den += dx * dx; });
  return den > 0 ? Math.round(num / den * 1000) / 1000 : null;
}

function desvioPadrao(valores) {
  const n = valores.length;
  if (n < 2) return null;
  const media = valores.reduce((a, b) => a + b, 0) / n;
  const variancia = valores.reduce((acc, v) => acc + (v - media) ** 2, 0) / n;
  return r2(Math.sqrt(variancia));
}

/** Média das últimas N avaliações menos a média das anteriores. */
function evolucaoRecente(percentuais, janela) {
  const n = percentuais.length;
  if (n < 2) return null;
  janela = Math.min(janela, Math.floor(n / 2)) || 1;
  const recentes = percentuais.slice(-janela);
  const anteriores = percentuais.slice(0, n - janela);
  if (anteriores.length === 0) return null;
  const m = (a) => a.reduce((x, y) => x + y, 0) / a.length;
  return r2(m(recentes) - m(anteriores));
}

function classificarConteudo(aproveitamento, cfg) {
  if (aproveitamento === null) return 'sem_dados';
  if (aproveitamento >= cfg.faixa_dominio) return 'dominio';
  if (aproveitamento >= cfg.faixa_intermediario) return 'intermediario';
  return 'dificuldade';
}

/* ------------------------------------------------------------------ base */

class Base {
  constructor(dados) {
    this.d = dados;
    this.topicoPorId = new Map(dados.topicos.map(t => [t.id, t]));
    this.questaoPorId = new Map(dados.questoes.map(q => [q.id, q]));
    this.avaliacaoPorId = new Map(dados.avaliacoes.map(a => [a.id, a]));
    this.alunoPorId = new Map(dados.alunos.map(a => [a.id, a]));
    this.aulaPorId = new Map(dados.aulas.map(l => [l.id, l]));
    // Avaliações em ordem cronológica — base de toda série temporal.
    this.avaliacoesOrdenadas = [...dados.avaliacoes].sort(
      (a, b) => a.assessment_date.localeCompare(b.assessment_date) || a.id - b.id
    );
  }

  /** Assunto raiz de um tópico (o tópico filho é consolidado no assunto pai). */
  raiz(topicId) {
    const t = this.topicoPorId.get(topicId);
    if (!t) return null;
    return t.parent_id ? this.topicoPorId.get(t.parent_id) : t;
  }

  notasDoAluno(studentId, filtros = {}) {
    return this.d.notas
      .filter(n => n.student_id === studentId)
      .map(n => ({ ...n, aval: this.avaliacaoPorId.get(n.assessment_id) }))
      .filter(n => n.aval && (!filtros.tipo || n.aval.type === filtros.tipo))
      .sort((a, b) => a.aval.assessment_date.localeCompare(b.aval.assessment_date) || a.aval.id - b.aval.id)
      .map(n => ({ ...n, weight: n.aval.weight }));
  }

  respostasDoAluno(studentId) {
    return this.d.respostas.filter(r => r.student_id === studentId);
  }

  /** Presença: faltas justificadas saem do denominador por padrão. */
  frequencia(studentId, cfg) {
    const regs = this.d.presencas.filter(p => p.student_id === studentId);
    const conta = (s) => regs.filter(p => p.status === s).length;
    const presentes = conta('presente'), atrasos = conta('atraso');
    const faltas = conta('falta'), justificadas = conta('falta_justificada');
    const total = regs.length;
    const base = cfg.justificada_conta ? total : total - justificadas;
    const participacoes = regs.map(p => p.participation).filter(v => v !== null && v !== undefined);
    return {
      aulas: total, presentes, atrasos, faltas, justificadas,
      frequencia: base > 0 ? r2((presentes + atrasos * 0.5) / base * 100) : null,
      participacao: participacoes.length ? r2(participacoes.reduce((a, b) => a + b, 0) / participacoes.length) : null,
    };
  }

  /** Acertos / erros / em branco de um recorte de respostas. */
  totaisDeResposta(respostas) {
    const total = respostas.length;
    const conta = (s) => respostas.filter(r => r.result === s).length;
    const acertos = conta('correta'), erros = conta('incorreta'), branco = conta('nao_respondida');
    return {
      total, acertos, erros, branco,
      pct_acertos: total ? r2(acertos / total * 100) : null,
      pct_erros: total ? r2(erros / total * 100) : null,
      pct_branco: total ? r2(branco / total * 100) : null,
    };
  }

  /**
   * Aproveitamento por assunto: pontos obtidos ÷ pontos possíveis, consolidado
   * no assunto raiz e classificado nas faixas configuradas.
   */
  aproveitamentoPorAssunto(respostas, cfg) {
    const grupos = new Map();
    for (const resp of respostas) {
      const q = this.questaoPorId.get(resp.question_id);
      if (!q || !q.topic_id) continue;
      const raiz = this.raiz(q.topic_id);
      if (!raiz) continue;
      if (!grupos.has(raiz.id)) {
        grupos.set(raiz.id, {
          topic_id: raiz.id, topic_name: raiz.name,
          respondidas: 0, acertos: 0, erros: 0, branco: 0,
          pontos_obtidos: 0, pontos_possiveis: 0, alunos: new Set(),
        });
      }
      const g = grupos.get(raiz.id);
      g.respondidas++;
      g.alunos.add(resp.student_id);
      if (resp.result === 'correta') g.acertos++;
      else if (resp.result === 'incorreta') g.erros++;
      else g.branco++;
      g.pontos_obtidos += resp.score_earned;
      g.pontos_possiveis += q.points;
    }

    return [...grupos.values()].map(g => {
      const aprov = g.pontos_possiveis > 0 ? r2(g.pontos_obtidos / g.pontos_possiveis * 100) : null;
      const amostra = g.respondidas >= cfg.min_questoes_assunto;
      return {
        ...g, alunos: g.alunos.size, aproveitamento: aprov,
        amostra_suficiente: amostra,
        classificacao: amostra ? classificarConteudo(aprov, cfg) : 'sem_dados',
      };
    }).sort((a, b) => (a.aproveitamento ?? 999) - (b.aproveitamento ?? 999));
  }

  /** Aproveitamento por nível de dificuldade das questões. */
  aproveitamentoPorDificuldade(respostas) {
    const ordem = ['facil', 'medio', 'dificil'];
    const grupos = new Map(ordem.map(d => [d, { dificuldade: d, respondidas: 0, acertos: 0, obtidos: 0, possiveis: 0 }]));
    for (const resp of respostas) {
      const q = this.questaoPorId.get(resp.question_id);
      if (!q) continue;
      const g = grupos.get(q.difficulty);
      g.respondidas++;
      if (resp.result === 'correta') g.acertos++;
      g.obtidos += resp.score_earned;
      g.possiveis += q.points;
    }
    return ordem.map(d => grupos.get(d)).filter(g => g.respondidas > 0).map(g => ({
      dificuldade: g.dificuldade, respondidas: g.respondidas, acertos: g.acertos,
      aproveitamento: g.possiveis > 0 ? r2(g.obtidos / g.possiveis * 100) : null,
    }));
  }

  /** Painel completo de um aluno — fonte única do dashboard individual. */
  resumoDoAluno(studentId, cfg) {
    const notas = this.notasDoAluno(studentId);
    const percentuais = notas.map(n => n.percentage);
    const media = mediaPonderada(notas);
    const slope = tendencia(percentuais);
    const delta = evolucaoRecente(percentuais, cfg.janela_recente);
    const desvio = desvioPadrao(percentuais);
    const presenca = this.frequencia(studentId, cfg);
    const respostas = this.respostasDoAluno(studentId);
    const assuntos = this.aproveitamentoPorAssunto(respostas, cfg);

    const conta = (c) => assuntos.filter(a => a.classificacao === c).length;

    const scoreEvolucao = (slope !== null && notas.length >= cfg.min_avaliacoes_evolucao)
      ? clamp(50 + slope * cfg.fator_evolucao) : 50;
    const scoreConsistencia = desvio !== null
      ? clamp(100 - desvio * cfg.fator_consistencia) : 50;

    const w = pesos(cfg);
    const confiavel = notas.length >= cfg.min_avaliacoes_indice;
    const indice = confiavel ? r2(
      w.desempenho * media + w.evolucao * scoreEvolucao +
      w.frequencia * (presenca.frequencia ?? 50) + w.consistencia * scoreConsistencia
    ) : null;

    const { classificacao, motivos } = classificarAluno(indice, delta, presenca.frequencia, confiavel, cfg);

    return {
      id: studentId, aluno: this.alunoPorId.get(studentId),
      avaliacoes: notas.length, notas, percentuais, media,
      evolucao_slope: slope,
      evolucao_total: slope !== null ? r2(slope * (notas.length - 1)) : null,
      evolucao_recente: delta, desvio,
      score_evolucao: r2(scoreEvolucao), score_consistencia: r2(scoreConsistencia),
      frequencia: presenca.frequencia, participacao: presenca.participacao, presenca,
      acertos: this.totaisDeResposta(respostas),
      assuntos,
      dominados: conta('dominio'), intermediarios: conta('intermediario'), dificuldades: conta('dificuldade'),
      indice, indice_confiavel: confiavel, classificacao, motivos,
      // Contribuição de cada componente para o índice — usada no gráfico do perfil.
      componentes: [
        { nome: 'Desempenho',   valor: media ?? 0,                 peso: w.desempenho },
        { nome: 'Evolução',     valor: scoreEvolucao,              peso: w.evolucao },
        { nome: 'Frequência',   valor: presenca.frequencia ?? 50,  peso: w.frequencia },
        { nome: 'Consistência', valor: scoreConsistencia,          peso: w.consistencia },
      ],
    };
  }

  /** Ranking pelo Índice de Desenvolvimento (não pela maior nota). */
  ranking(cfg) {
    const linhas = this.d.alunos
      .filter(a => a.status !== 'inativo')
      .map(a => this.resumoDoAluno(a.id, cfg));

    linhas.sort((x, y) => {
      if (x.indice === null && y.indice === null) return x.aluno.full_name.localeCompare(y.aluno.full_name);
      if (x.indice === null) return 1;
      if (y.indice === null) return -1;
      return y.indice - x.indice;
    });

    let posicao = 0;
    linhas.forEach(l => { l.posicao = l.indice === null ? null : ++posicao; });
    return linhas;
  }

  /** Médias e séries agregadas do recorte inteiro. */
  mediaGeral() {
    if (!this.d.notas.length) return null;
    return r2(this.d.notas.reduce((a, n) => a + n.percentage, 0) / this.d.notas.length);
  }

  mediaPorAvaliacao() {
    return this.avaliacoesOrdenadas.map(av => {
      const notas = this.d.notas.filter(n => n.assessment_id === av.id);
      const p = notas.map(n => n.percentage);
      return {
        assessment_id: av.id, nome: av.name, data: av.assessment_date, tipo: av.type,
        alunos: notas.length,
        media: p.length ? r2(p.reduce((a, b) => a + b, 0) / p.length) : null,
        minima: p.length ? Math.min(...p) : null,
        maxima: p.length ? Math.max(...p) : null,
      };
    });
  }

  distribuicao() {
    const faixas = { '0 a 39%': 0, '40 a 59%': 0, '60 a 79%': 0, '80 a 100%': 0 };
    for (const aluno of this.d.alunos) {
      const notas = this.d.notas.filter(n => n.student_id === aluno.id);
      if (!notas.length) continue;
      const m = notas.reduce((a, n) => a + n.percentage, 0) / notas.length;
      if (m < 40) faixas['0 a 39%']++;
      else if (m < 60) faixas['40 a 59%']++;
      else if (m < 80) faixas['60 a 79%']++;
      else faixas['80 a 100%']++;
    }
    return faixas;
  }

  /** Índice de acerto por questão de uma avaliação. */
  analiseDaAvaliacao(assessmentId, cfg) {
    const questoes = this.d.questoes
      .filter(q => q.assessment_id === assessmentId)
      .sort((a, b) => (a.number ?? a.id) - (b.number ?? b.id));

    return questoes.map(q => {
      const resp = this.d.respostas.filter(r => r.question_id === q.id);
      const acertos = resp.filter(r => r.result === 'correta').length;
      const indice = resp.length ? r2(acertos / resp.length * 100) : null;
      const raiz = q.topic_id ? this.raiz(q.topic_id) : null;
      return {
        ...q, assunto: raiz ? raiz.name : null,
        respondidas: resp.length, acertos,
        erros: resp.filter(r => r.result === 'incorreta').length,
        branco: resp.filter(r => r.result === 'nao_respondida').length,
        indice_acerto: indice, classificacao: classificarConteudo(indice, cfg),
      };
    });
  }
}

/** Classificação do aluno com a justificativa que o professor lê na tela. */
function classificarAluno(indice, delta, frequencia, confiavel, cfg) {
  if (!confiavel || indice === null) {
    return { classificacao: 'sem_dados', motivos: [`Menos de ${cfg.min_avaliacoes_indice} avaliação(ões) registrada(s).`] };
  }
  const motivos = [];
  let atencao = false;

  if (delta !== null && delta <= -cfg.queda_alerta) {
    atencao = true;
    motivos.push(`Queda de ${fmt(Math.abs(delta), 1)} p.p. no desempenho recente.`);
  }
  if (frequencia !== null && frequencia < cfg.frequencia_minima) {
    atencao = true;
    motivos.push(`Frequência de ${fmt(frequencia, 1)}%, abaixo do mínimo de ${fmt(cfg.frequencia_minima, 0)}%.`);
  }

  if (atencao || indice < cfg.id_atencao) {
    if (indice < cfg.id_atencao) motivos.push(`Índice de Desenvolvimento em ${fmt(indice, 1)}.`);
    return { classificacao: 'atencao', motivos };
  }
  if (indice >= cfg.id_evolucao) {
    motivos.push(`Índice de Desenvolvimento em ${fmt(indice, 1)}.`);
    if (delta !== null && delta > 0) motivos.push(`Ganho de ${fmt(delta, 1)} p.p. nas avaliações recentes.`);
    return { classificacao: 'evolucao', motivos };
  }
  motivos.push(`Índice de Desenvolvimento em ${fmt(indice, 1)}.`);
  return { classificacao: 'intermediario', motivos };
}

/** Número no formato pt-BR (vírgula decimal), como o PHP escreve nas mensagens. */
function fmt(valor, casas = 1) {
  return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
}

/* ---------------------------------------------------------------- alertas */

function gerarAlertas(base, ranking, cfg) {
  const alertas = [];
  const novo = (chave, sev, titulo, alunoId, msg) =>
    alertas.push({ key: chave, severity: sev, title: titulo, student_id: alunoId, message: msg });

  for (const a of ranking) {
    const nome = a.aluno.full_name;

    if (a.evolucao_recente !== null && a.evolucao_recente <= -cfg.queda_alerta) {
      const janela = Math.min(cfg.janela_recente, Math.max(1, Math.floor(a.percentuais.length / 2)));
      const recentes = a.percentuais.slice(-janela);
      const anteriores = a.percentuais.slice(0, a.percentuais.length - janela);
      const m = (arr) => arr.length ? arr.reduce((x, y) => x + y, 0) / arr.length : 0;
      novo(`queda:${a.id}`, 'alta', 'Queda de desempenho', a.id,
        `${nome} teve queda de ${fmt(Math.abs(a.evolucao_recente))} pontos percentuais nas avaliações mais recentes (de ${fmt(m(anteriores))}% para ${fmt(m(recentes))}%).`);
    }

    if (a.frequencia !== null && a.frequencia < cfg.frequencia_minima && a.presenca.aulas > 0) {
      novo(`frequencia:${a.id}`, 'alta', 'Frequência baixa', a.id,
        `${nome} está com ${fmt(a.frequencia)}% de frequência (mínimo configurado: ${fmt(cfg.frequencia_minima, 0)}%), com ${a.presenca.faltas} falta(s) em ${a.presenca.aulas} aula(s).`);
    }

    if (a.media !== null && a.media < cfg.media_alerta && a.avaliacoes > 0) {
      novo(`media:${a.id}`, 'alta', 'Baixo aproveitamento', a.id,
        `${nome} está com média de ${fmt(a.media)}% em ${a.avaliacoes} avaliação(ões), abaixo do mínimo de ${fmt(cfg.media_alerta, 0)}%.`);
    }

    if (a.evolucao_recente !== null && a.evolucao_recente >= cfg.evolucao_alerta) {
      novo(`evolucao:${a.id}`, 'positiva', 'Evolução significativa', a.id,
        `${nome} evoluiu ${fmt(a.evolucao_recente)} pontos percentuais nas avaliações mais recentes. Vale registrar o reconhecimento.`);
    }

    if (a.avaliacoes === 0) {
      novo(`sem_avaliacao:${a.id}`, 'media', 'Aluno sem resultados', a.id,
        `${nome} está vinculado a uma turma mas ainda não possui nenhum resultado registrado.`);
    }
  }

  // Dificuldade persistente: mesmo assunto abaixo do limite em N avaliações.
  for (const a of ranking) {
    const porAssuntoAvaliacao = new Map();
    for (const resp of base.respostasDoAluno(a.id)) {
      const q = base.questaoPorId.get(resp.question_id);
      if (!q || !q.topic_id) continue;
      const raiz = base.raiz(q.topic_id);
      if (!raiz) continue;
      const chave = `${raiz.id}|${q.assessment_id}`;
      if (!porAssuntoAvaliacao.has(chave)) {
        porAssuntoAvaliacao.set(chave, { topico: raiz, obtidos: 0, possiveis: 0 });
      }
      const g = porAssuntoAvaliacao.get(chave);
      g.obtidos += resp.score_earned;
      g.possiveis += q.points;
    }
    const ocorrencias = new Map();
    for (const [chave, g] of porAssuntoAvaliacao) {
      if (g.possiveis <= 0) continue;
      const aprov = g.obtidos / g.possiveis * 100;
      if (aprov >= cfg.limite_dificuldade) continue;
      const id = chave.split('|')[0];
      if (!ocorrencias.has(id)) ocorrencias.set(id, { topico: g.topico, n: 0 });
      ocorrencias.get(id).n++;
    }
    for (const [id, o] of ocorrencias) {
      if (o.n < cfg.ocorrencias_persistente) continue;
      novo(`persistente:${a.id}:${id}`, 'alta', 'Dificuldade persistente', a.id,
        `Atenção: ${a.aluno.full_name} apresentou aproveitamento inferior a ${fmt(cfg.limite_dificuldade, 0)}% em ${o.topico.name} nas últimas ${o.n} avaliações.`);
    }
  }

  // Conteúdo crítico da turma.
  const assuntosTurma = base.aproveitamentoPorAssunto(base.d.respostas, cfg);
  for (const t of assuntosTurma) {
    if (t.aproveitamento === null || t.aproveitamento >= cfg.limite_dificuldade || t.respondidas < 5) continue;
    novo(`turma_conteudo:${t.topic_id}`, 'media', 'Conteúdo crítico da turma', null,
      `A turma ${base.d.turma.code} obteve apenas ${fmt(t.aproveitamento)}% de aproveitamento em ${t.topic_name} (${t.respondidas} respostas de ${t.alunos} aluno(s)). Conteúdo candidato a revisão.`);
  }

  const ordem = { alta: 0, media: 1, positiva: 2 };
  alertas.sort((x, y) => (ordem[x.severity] ?? 3) - (ordem[y.severity] ?? 3));
  return alertas;
}

if (typeof module !== 'undefined') module.exports = { Base, gerarAlertas, PADROES, classificarConteudo, fmt, r2 };
