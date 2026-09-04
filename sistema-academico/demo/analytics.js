/* ===========================================================================
   Motor analítico — porte fiel de src/Services/{Analytics,Ranking,Alert}Service.php
   e de Core\Scope. As fórmulas seguem docs/04-REGRAS-DE-CALCULO.md e o recorte
   por perfil segue docs/05-PERFIS-E-PAINEIS.md.

   Conferido no Node contra os valores do PHP para os três perfis (administrador,
   Marina e Ricardo): ranking, médias, frequência, aproveitamento por assunto e
   por dificuldade, perda de pontos e o texto de cada alerta.
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

const r2 = (v) => v === null || v === undefined ? null : Math.round(v * 100) / 100;
const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));
const soma = (a) => a.reduce((x, y) => x + y, 0);
const media = (a) => a.length ? soma(a) / a.length : 0;

/** Número no formato pt-BR — o mesmo que o PHP escreve nas mensagens. */
function fmt(valor, casas = 1) {
  return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
}

function pesos(cfg) {
  const w = {
    desempenho: cfg.peso_desempenho, evolucao: cfg.peso_evolucao,
    frequencia: cfg.peso_frequencia, consistencia: cfg.peso_consistencia,
  };
  const total = w.desempenho + w.evolucao + w.frequencia + w.consistencia;
  if (total <= 0) return { desempenho: .4, evolucao: .25, frequencia: .15, consistencia: .2 };
  return {
    desempenho: w.desempenho / total, evolucao: w.evolucao / total,
    frequencia: w.frequencia / total, consistencia: w.consistencia / total,
  };
}

/** Média ponderada dos percentuais (peso = peso da avaliação). */
function mediaPonderada(notas) {
  let acumulado = 0, pesosTotal = 0;
  for (const n of notas) { acumulado += n.percentage * n.weight; pesosTotal += n.weight; }
  return pesosTotal > 0 ? r2(acumulado / pesosTotal) : null;
}

/** Coeficiente angular da reta de tendência: p.p. por avaliação. */
function tendencia(percentuais) {
  const n = percentuais.length;
  if (n < 2) return null;
  const mediaX = (n + 1) / 2, mediaY = media(percentuais);
  let num = 0, den = 0;
  percentuais.forEach((y, i) => { const dx = (i + 1) - mediaX; num += dx * (y - mediaY); den += dx * dx; });
  return den > 0 ? Math.round(num / den * 1000) / 1000 : null;
}

function desvioPadrao(valores) {
  const n = valores.length;
  if (n < 2) return null;
  const m = media(valores);
  return r2(Math.sqrt(soma(valores.map(v => (v - m) ** 2)) / n));
}

/** Média das últimas N avaliações menos a das anteriores. */
function evolucaoRecente(percentuais, janela) {
  const n = percentuais.length;
  if (n < 2) return null;
  janela = Math.min(janela, Math.floor(n / 2)) || 1;
  const anteriores = percentuais.slice(0, n - janela);
  if (anteriores.length === 0) return null;
  return r2(media(percentuais.slice(-janela)) - media(anteriores));
}

function classificarConteudo(aproveitamento, cfg) {
  if (aproveitamento === null) return 'sem_dados';
  if (aproveitamento >= cfg.faixa_dominio) return 'dominio';
  if (aproveitamento >= cfg.faixa_intermediario) return 'intermediario';
  return 'dificuldade';
}

/* ==========================================================================
   Base — índices, recorte e consultas
   ========================================================================== */

class Base {
  constructor(dados) {
    this.d = dados;
    this.reindexar();
  }

  /** Refaz os índices — necessário depois de alterar dados na demonstração. */
  reindexar() {
    const d = this.d;
    this.cursoPorId     = new Map(d.cursos.map(x => [x.id, x]));
    this.turmaPorId     = new Map(d.turmas.map(x => [x.id, x]));
    this.disciplinaPorId= new Map(d.disciplinas.map(x => [x.id, x]));
    this.ofertaPorId    = new Map(d.ofertas.map(x => [x.id, x]));
    this.professorPorId = new Map(d.professores.map(x => [x.id, x]));
    this.topicoPorId    = new Map(d.topicos.map(x => [x.id, x]));
    this.questaoPorId   = new Map(d.questoes.map(x => [x.id, x]));
    this.avaliacaoPorId = new Map(d.avaliacoes.map(x => [x.id, x]));
    this.alunoPorId     = new Map(d.alunos.map(x => [x.id, x]));
    this.aulaPorId      = new Map(d.aulas.map(x => [x.id, x]));

    this.avaliacoesOrdenadas = [...d.avaliacoes].sort(
      (a, b) => a.assessment_date.localeCompare(b.assessment_date) || a.id - b.id);
    this.aulasOrdenadas = [...d.aulas].sort(
      (a, b) => a.lesson_date.localeCompare(b.lesson_date) || a.id - b.id);
  }

  /** Assunto raiz de um tópico (o filho é consolidado no assunto pai). */
  raiz(topicId) {
    const t = this.topicoPorId.get(topicId);
    if (!t) return null;
    return t.parent_id ? this.topicoPorId.get(t.parent_id) : t;
  }

  oferta(id) { return this.ofertaPorId.get(id) ?? null; }
  turmaDaOferta(id) { const o = this.oferta(id); return o ? this.turmaPorId.get(o.class_id) : null; }
  disciplinaDaOferta(id) { const o = this.oferta(id); return o ? this.disciplinaPorId.get(o.subject_id) : null; }

  /** Ofertas sob responsabilidade de um professor (espelha Core\\Scope). */
  ofertasDoProfessor(userId) {
    return this.d.ofertas
      .filter(o => o.teacher_user_id === userId ||
        (o.teacher_user_id === null && (this.disciplinaPorId.get(o.subject_id)?.teacher_user_id === userId)))
      .map(o => o.id);
  }

  /** A oferta passa pelo recorte pedido? */
  ofertaPassa(ofertaId, f) {
    const o = this.oferta(ofertaId);
    if (!o) return false;
    if (f.ofertas && !f.ofertas.includes(ofertaId)) return false;
    if (f.turma && o.class_id !== +f.turma) return false;
    if (f.disciplina && o.subject_id !== +f.disciplina) return false;
    if (f.curso) {
      const turma = this.turmaPorId.get(o.class_id);
      if (!turma || turma.course_id !== +f.curso) return false;
    }
    return true;
  }

  avaliacaoPassa(av, f) {
    if (!this.ofertaPassa(av.class_subject_id, f)) return false;
    if (f.tipo && av.type !== f.tipo) return false;
    if (f.avaliacao && av.id !== +f.avaliacao) return false;
    if (f.inicio && av.assessment_date < f.inicio) return false;
    if (f.fim && av.assessment_date > f.fim) return false;
    return true;
  }

  /** Avaliações do recorte, em ordem cronológica. */
  avaliacoes(f = {}) {
    return this.avaliacoesOrdenadas.filter(a => this.avaliacaoPassa(a, f));
  }

  aulas(f = {}) {
    return this.aulasOrdenadas.filter(l => {
      if (!this.ofertaPassa(l.class_subject_id, f)) return false;
      if (f.inicio && l.lesson_date < f.inicio) return false;
      if (f.fim && l.lesson_date > f.fim) return false;
      return true;
    });
  }

  /** Notas do recorte (com a avaliação anexada). */
  notas(f = {}) {
    const validas = new Map(this.avaliacoes(f).map(a => [a.id, a]));
    return this.d.notas
      .filter(n => validas.has(n.assessment_id) && (!f.aluno || n.student_id === +f.aluno))
      .map(n => ({ ...n, aval: validas.get(n.assessment_id), weight: validas.get(n.assessment_id).weight }))
      .sort((a, b) => a.aval.assessment_date.localeCompare(b.aval.assessment_date) || a.aval.id - b.aval.id);
  }

  /** Respostas do recorte (aplica também assunto e dificuldade). */
  respostas(f = {}) {
    const validas = new Set(this.avaliacoes(f).map(a => a.id));
    return this.d.respostas.filter(r => {
      const q = this.questaoPorId.get(r.question_id);
      if (!q || !validas.has(q.assessment_id)) return false;
      if (f.aluno && r.student_id !== +f.aluno) return false;
      if (f.dificuldade && q.difficulty !== f.dificuldade) return false;
      if (f.assunto) {
        const raiz = q.topic_id ? this.raiz(q.topic_id) : null;
        if (!raiz || (raiz.id !== +f.assunto && q.topic_id !== +f.assunto)) return false;
      }
      return true;
    });
  }

  presencas(f = {}) {
    const aulasValidas = new Map(this.aulas(f).map(l => [l.id, l]));
    return this.d.presencas.filter(p =>
      aulasValidas.has(p.lesson_id) && (!f.aluno || p.student_id === +f.aluno));
  }

  /** Turmas alcançadas pelo recorte. */
  turmas(f = {}) {
    const ids = new Set(this.d.ofertas.filter(o => this.ofertaPassa(o.id, f)).map(o => o.class_id));
    return this.d.turmas.filter(t => ids.has(t.id));
  }

  disciplinas(f = {}) {
    const ids = new Set(this.d.ofertas.filter(o => this.ofertaPassa(o.id, f)).map(o => o.subject_id));
    return this.d.disciplinas.filter(s => ids.has(s.id));
  }

  /** Alunos alcançados pelo recorte (matriculados nas turmas das ofertas). */
  alunos(f = {}) {
    const turmas = new Set(this.turmas(f).map(t => t.id));
    return this.d.alunos
      .filter(a => a.status !== 'inativo' && a.class_id !== null && turmas.has(a.class_id))
      .filter(a => !f.aluno || a.id === +f.aluno);
  }

  /* ------------------------------------------------------------ indicadores */

  frequencia(studentId, cfg, f = {}) {
    const regs = this.presencas({ ...f, aluno: studentId });
    const conta = (s) => regs.filter(p => p.status === s).length;
    const presentes = conta('presente'), atrasos = conta('atraso');
    const justificadas = conta('falta_justificada');
    const total = regs.length;
    const base = cfg.justificada_conta ? total : total - justificadas;
    const participacoes = regs.map(p => p.participation).filter(v => v !== null && v !== undefined);
    return {
      aulas: total, presentes, atrasos, faltas: conta('falta'), justificadas,
      frequencia: base > 0 ? r2((presentes + atrasos * 0.5) / base * 100) : null,
      participacao: participacoes.length ? r2(media(participacoes)) : null,
    };
  }

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

  /** Aproveitamento por assunto: pontos obtidos ÷ possíveis, no assunto raiz. */
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
          subject_id: raiz.subject_id,
          subject_name: this.disciplinaPorId.get(raiz.subject_id)?.name ?? '',
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
      return { ...g, alunos: g.alunos.size, aproveitamento: aprov,
        amostra_suficiente: amostra,
        classificacao: amostra ? classificarConteudo(aprov, cfg) : 'sem_dados' };
    }).sort((a, b) => (a.aproveitamento ?? 999) - (b.aproveitamento ?? 999));
  }

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

  /** Onde mais se perde pontuação, por avaliação. */
  perdaPorAvaliacao(f = {}, limite = 20) {
    const respostas = this.respostas(f);
    const grupos = new Map();
    for (const r of respostas) {
      const q = this.questaoPorId.get(r.question_id);
      const av = this.avaliacaoPorId.get(q.assessment_id);
      if (!grupos.has(av.id)) {
        const turma = this.turmaDaOferta(av.class_subject_id);
        const disc = this.disciplinaDaOferta(av.class_subject_id);
        grupos.set(av.id, {
          assessment_id: av.id, assessment_name: av.name, assessment_date: av.assessment_date,
          type: av.type, class_code: turma?.code ?? '', subject_name: disc?.name ?? '',
          alunos: new Set(), respostas: 0, erros: 0, branco: 0,
          pontos_possiveis: 0, pontos_obtidos: 0,
        });
      }
      const g = grupos.get(av.id);
      g.alunos.add(r.student_id);
      g.respostas++;
      if (r.result === 'incorreta') g.erros++;
      if (r.result === 'nao_respondida') g.branco++;
      g.pontos_possiveis += q.points;
      g.pontos_obtidos += r.score_earned;
    }
    const linhas = [...grupos.values()].map(g => ({
      ...g, alunos: g.alunos.size,
      pontos_perdidos: r2(g.pontos_possiveis - g.pontos_obtidos),
      pct_perdido: g.pontos_possiveis > 0 ? r2((g.pontos_possiveis - g.pontos_obtidos) / g.pontos_possiveis * 100) : null,
      aproveitamento: g.pontos_possiveis > 0 ? r2(g.pontos_obtidos / g.pontos_possiveis * 100) : null,
    }));
    linhas.sort((a, b) => b.pontos_perdidos - a.pontos_perdidos);
    return linhas.slice(0, limite);
  }

  /** Quanto cada aluno deixou de pontuar, e em qual avaliação a perda foi maior. */
  perdaPorAluno(f = {}) {
    const respostas = this.respostas(f);
    const porAlunoAval = new Map();
    for (const r of respostas) {
      const q = this.questaoPorId.get(r.question_id);
      const chave = r.student_id + ':' + q.assessment_id;
      if (!porAlunoAval.has(chave)) {
        porAlunoAval.set(chave, { student_id: r.student_id, assessment_id: q.assessment_id, possiveis: 0, obtidos: 0 });
      }
      const g = porAlunoAval.get(chave);
      g.possiveis += q.points;
      g.obtidos += r.score_earned;
    }

    const porAluno = new Map();
    for (const g of porAlunoAval.values()) {
      const perdido = r2(g.possiveis - g.obtidos);
      if (!porAluno.has(g.student_id)) {
        porAluno.set(g.student_id, {
          student_id: g.student_id,
          full_name: this.alunoPorId.get(g.student_id)?.full_name ?? '',
          possiveis: 0, obtidos: 0, perdidos: 0, pior_avaliacao: null, pior_perda: 0,
        });
      }
      const a = porAluno.get(g.student_id);
      a.possiveis += g.possiveis;
      a.obtidos += g.obtidos;
      a.perdidos += perdido;
      if (perdido > a.pior_perda) {
        a.pior_perda = perdido;
        a.pior_avaliacao = this.avaliacaoPorId.get(g.assessment_id)?.name ?? null;
      }
    }

    const linhas = [...porAluno.values()].map(a => ({
      ...a, possiveis: r2(a.possiveis), obtidos: r2(a.obtidos), perdidos: r2(a.perdidos),
      aproveitamento: a.possiveis > 0 ? r2(a.obtidos / a.possiveis * 100) : null,
    }));
    linhas.sort((a, b) => b.perdidos - a.perdidos);
    return linhas;
  }

  /** Painel completo de um aluno — fonte única de todos os dashboards. */
  resumoDoAluno(studentId, cfg, f = {}) {
    const filtros = { ...f, aluno: studentId };
    const notas = this.notas(filtros);
    const percentuais = notas.map(n => n.percentage);
    const mediaGeral = mediaPonderada(notas);
    const slope = tendencia(percentuais);
    const delta = evolucaoRecente(percentuais, cfg.janela_recente);
    const desvio = desvioPadrao(percentuais);
    const presenca = this.frequencia(studentId, cfg, f);
    const respostas = this.respostas(filtros);
    const assuntos = this.aproveitamentoPorAssunto(respostas, cfg);
    const conta = (c) => assuntos.filter(a => a.classificacao === c).length;

    const scoreEvolucao = (slope !== null && notas.length >= cfg.min_avaliacoes_evolucao)
      ? clamp(50 + slope * cfg.fator_evolucao) : 50;
    const scoreConsistencia = desvio !== null ? clamp(100 - desvio * cfg.fator_consistencia) : 50;

    const w = pesos(cfg);
    const confiavel = notas.length >= cfg.min_avaliacoes_indice;
    const indice = confiavel ? r2(
      w.desempenho * mediaGeral + w.evolucao * scoreEvolucao +
      w.frequencia * (presenca.frequencia ?? 50) + w.consistencia * scoreConsistencia) : null;

    const { classificacao, motivos } = classificarAluno(indice, delta, presenca.frequencia, confiavel, cfg);
    const aluno = this.alunoPorId.get(studentId);

    return {
      id: studentId, aluno,
      turma: aluno && aluno.class_id ? this.turmaPorId.get(aluno.class_id) : null,
      avaliacoes: notas.length, notas, percentuais, media: mediaGeral,
      evolucao_slope: slope,
      evolucao_total: slope !== null ? r2(slope * (notas.length - 1)) : null,
      evolucao_recente: delta, desvio,
      score_evolucao: r2(scoreEvolucao), score_consistencia: r2(scoreConsistencia),
      frequencia: presenca.frequencia, participacao: presenca.participacao, presenca,
      acertos: this.totaisDeResposta(respostas),
      assuntos,
      dominados: conta('dominio'), intermediarios: conta('intermediario'), dificuldades: conta('dificuldade'),
      indice, indice_confiavel: confiavel, classificacao, motivos,
      componentes: [
        { nome: 'Desempenho',   valor: mediaGeral ?? 0,           peso: w.desempenho },
        { nome: 'Evolução',     valor: scoreEvolucao,             peso: w.evolucao },
        { nome: 'Frequência',   valor: presenca.frequencia ?? 50, peso: w.frequencia },
        { nome: 'Consistência', valor: scoreConsistencia,         peso: w.consistencia },
      ],
    };
  }

  /** Ranking pelo Índice de Desenvolvimento (não pela maior nota). */
  ranking(cfg, f = {}) {
    const linhas = this.alunos(f).map(a => this.resumoDoAluno(a.id, cfg, f));
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

  /* ------------------------------------------------------------ agregações */

  mediaGeral(f = {}) {
    const notas = this.notas(f);
    return notas.length ? r2(media(notas.map(n => n.percentage))) : null;
  }

  mediaPorAvaliacao(f = {}) {
    return this.avaliacoes(f).map(av => {
      const notas = this.d.notas.filter(n => n.assessment_id === av.id && (!f.aluno || n.student_id === +f.aluno));
      const p = notas.map(n => n.percentage);
      const turma = this.turmaDaOferta(av.class_subject_id);
      const disc = this.disciplinaDaOferta(av.class_subject_id);
      return {
        assessment_id: av.id, nome: av.name, data: av.assessment_date, tipo: av.type,
        class_code: turma?.code ?? '', subject_name: disc?.name ?? '',
        alunos: notas.length,
        media: p.length ? r2(media(p)) : null,
        minima: p.length ? Math.min(...p) : null,
        maxima: p.length ? Math.max(...p) : null,
      };
    });
  }

  mediaPorDisciplina(f = {}) {
    const grupos = new Map();
    for (const n of this.notas(f)) {
      const disc = this.disciplinaDaOferta(n.aval.class_subject_id);
      if (!disc) continue;
      if (!grupos.has(disc.id)) grupos.set(disc.id, { subject_id: disc.id, nome: disc.name, valores: [], alunos: new Set(), avaliacoes: new Set() });
      const g = grupos.get(disc.id);
      g.valores.push(n.percentage);
      g.alunos.add(n.student_id);
      g.avaliacoes.add(n.assessment_id);
    }
    return [...grupos.values()]
      .map(g => ({ subject_id: g.subject_id, nome: g.nome, alunos: g.alunos.size,
                   avaliacoes: g.avaliacoes.size, media: r2(media(g.valores)) }))
      .sort((a, b) => (b.media ?? -1) - (a.media ?? -1));
  }

  mediaPorTurma(f = {}) {
    const grupos = new Map();
    for (const n of this.notas(f)) {
      const turma = this.turmaDaOferta(n.aval.class_subject_id);
      if (!turma) continue;
      if (!grupos.has(turma.id)) grupos.set(turma.id, { class_id: turma.id, nome: turma.code, year: turma.year, valores: [], alunos: new Set() });
      const g = grupos.get(turma.id);
      g.valores.push(n.percentage);
      g.alunos.add(n.student_id);
    }
    return [...grupos.values()]
      .map(g => ({ class_id: g.class_id, nome: g.nome, year: g.year, alunos: g.alunos.size, media: r2(media(g.valores)) }))
      .sort((a, b) => (b.media ?? -1) - (a.media ?? -1));
  }

  mediaPorCurso(f = {}) {
    const grupos = new Map();
    for (const n of this.notas(f)) {
      const turma = this.turmaDaOferta(n.aval.class_subject_id);
      const curso = turma ? this.cursoPorId.get(turma.course_id) : null;
      if (!curso) continue;
      if (!grupos.has(curso.id)) grupos.set(curso.id, { course_id: curso.id, nome: curso.name, valores: [], alunos: new Set(), turmas: new Set() });
      const g = grupos.get(curso.id);
      g.valores.push(n.percentage);
      g.alunos.add(n.student_id);
      g.turmas.add(turma.id);
    }
    return [...grupos.values()]
      .map(g => ({ course_id: g.course_id, nome: g.nome, alunos: g.alunos.size,
                   turmas: g.turmas.size, media: r2(media(g.valores)) }))
      .sort((a, b) => (b.media ?? -1) - (a.media ?? -1));
  }

  distribuicao(f = {}) {
    const faixas = { '0 a 39%': 0, '40 a 59%': 0, '60 a 79%': 0, '80 a 100%': 0 };
    for (const aluno of this.alunos(f)) {
      const notas = this.notas({ ...f, aluno: aluno.id });
      if (!notas.length) continue;
      const m = media(notas.map(n => n.percentage));
      if (m < 40) faixas['0 a 39%']++;
      else if (m < 60) faixas['40 a 59%']++;
      else if (m < 80) faixas['60 a 79%']++;
      else faixas['80 a 100%']++;
    }
    return faixas;
  }

  /** Índice de acerto por questão de uma avaliação. */
  analiseDaAvaliacao(assessmentId, cfg) {
    return this.d.questoes
      .filter(q => q.assessment_id === assessmentId)
      .sort((a, b) => (a.number ?? a.id) - (b.number ?? b.id))
      .map(q => {
        const resp = this.d.respostas.filter(r => r.question_id === q.id);
        const acertos = resp.filter(r => r.result === 'correta').length;
        const indice = resp.length ? r2(acertos / resp.length * 100) : null;
        const raiz = q.topic_id ? this.raiz(q.topic_id) : null;
        return { ...q, assunto: raiz ? raiz.name : null,
          respondidas: resp.length, acertos,
          erros: resp.filter(r => r.result === 'incorreta').length,
          branco: resp.filter(r => r.result === 'nao_respondida').length,
          indice_acerto: indice, classificacao: classificarConteudo(indice, cfg) };
      });
  }
}

/** Classificação do aluno com a justificativa que aparece na tela. */
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

/* ==========================================================================
   Alertas pedagógicos
   ========================================================================== */

function gerarAlertas(base, ranking, cfg, f = {}) {
  const alertas = [];
  const novo = (key, severity, title, student_id, message) =>
    alertas.push({ key, severity, title, student_id, message });

  for (const a of ranking) {
    const nome = a.aluno.full_name;

    if (a.evolucao_recente !== null && a.evolucao_recente <= -cfg.queda_alerta) {
      const janela = Math.min(cfg.janela_recente, Math.max(1, Math.floor(a.percentuais.length / 2)));
      const recentes = a.percentuais.slice(-janela);
      const anteriores = a.percentuais.slice(0, a.percentuais.length - janela);
      novo(`queda:${a.id}`, 'alta', 'Queda de desempenho', a.id,
        `${nome} teve queda de ${fmt(Math.abs(a.evolucao_recente))} pontos percentuais nas avaliações mais recentes (de ${fmt(media(anteriores))}% para ${fmt(media(recentes))}%).`);
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
    if (a.avaliacoes === 0 && a.aluno.class_id !== null) {
      novo(`sem_avaliacao:${a.id}`, 'media', 'Aluno sem resultados', a.id,
        `${nome} está vinculado a uma turma mas ainda não possui nenhum resultado registrado.`);
    }
  }

  // Dificuldade persistente: mesmo assunto abaixo do limite em N avaliações.
  for (const a of ranking) {
    const porAssuntoAval = new Map();
    for (const resp of base.respostas({ ...f, aluno: a.id })) {
      const q = base.questaoPorId.get(resp.question_id);
      if (!q || !q.topic_id) continue;
      const raiz = base.raiz(q.topic_id);
      if (!raiz) continue;
      const chave = `${raiz.id}|${q.assessment_id}`;
      if (!porAssuntoAval.has(chave)) porAssuntoAval.set(chave, { topico: raiz, obtidos: 0, possiveis: 0 });
      const g = porAssuntoAval.get(chave);
      g.obtidos += resp.score_earned;
      g.possiveis += q.points;
    }
    const ocorrencias = new Map();
    for (const [chave, g] of porAssuntoAval) {
      if (g.possiveis <= 0) continue;
      if (g.obtidos / g.possiveis * 100 >= cfg.limite_dificuldade) continue;
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

  // Conteúdo crítico, por turma.
  const porTurmaTopico = new Map();
  for (const resp of base.respostas(f)) {
    const q = base.questaoPorId.get(resp.question_id);
    if (!q || !q.topic_id) continue;
    const raiz = base.raiz(q.topic_id);
    const av = base.avaliacaoPorId.get(q.assessment_id);
    const turma = base.turmaDaOferta(av.class_subject_id);
    if (!raiz || !turma) continue;
    const chave = `${turma.id}|${raiz.id}`;
    if (!porTurmaTopico.has(chave)) {
      porTurmaTopico.set(chave, { turma, topico: raiz, respostas: 0, alunos: new Set(), obtidos: 0, possiveis: 0 });
    }
    const g = porTurmaTopico.get(chave);
    g.respostas++;
    g.alunos.add(resp.student_id);
    g.obtidos += resp.score_earned;
    g.possiveis += q.points;
  }
  for (const g of porTurmaTopico.values()) {
    if (g.respostas < 5 || g.possiveis <= 0) continue;
    const aprov = g.obtidos / g.possiveis * 100;
    if (aprov >= cfg.limite_dificuldade) continue;
    novo(`turma_conteudo:${g.turma.id}:${g.topico.id}`, 'media', 'Conteúdo crítico da turma', null,
      `A turma ${g.turma.code} obteve apenas ${fmt(aprov)}% de aproveitamento em ${g.topico.name} (${g.respostas} respostas de ${g.alunos.size} aluno(s)). Conteúdo candidato a revisão.`);
  }

  const ordem = { alta: 0, media: 1, positiva: 2 };
  alertas.sort((x, y) => (ordem[x.severity] ?? 3) - (ordem[y.severity] ?? 3));
  return alertas;
}

if (typeof module !== 'undefined') {
  module.exports = { Base, gerarAlertas, PADROES, classificarConteudo, classificarAluno, fmt, r2, desvioPadrao };
}
