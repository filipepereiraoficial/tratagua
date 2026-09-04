/**
 * Prova que o motor analítico em JavaScript (analytics.js, usado na
 * demonstração) reproduz exatamente o que o PHP calcula no servidor —
 * inclusive o recorte por perfil.
 *
 *   php database/migrate.php --fresh --seed --demo
 *   php demo/exportar.php
 *   node demo/verificar.js
 *
 * Compara, para o administrador e para cada professor: ranking, médias,
 * frequência, evolução, consistência, índice, aproveitamento por assunto e por
 * dificuldade, distribuição, perda de pontos e o texto de cada alerta.
 * Sai com código 1 na primeira divergência.
 */
const { Base, gerarAlertas, PADROES } = require('./analytics.js');
const dados = require('./dados.json');
const referencia = require('./referencia.json');

const base = new Base(dados);
let falhas = 0;

const igual = (rotulo, js, php, tolerancia = 0.011) => {
  const ok = (js === null || js === undefined) && (php === null || php === undefined)
    || (js !== null && js !== undefined && php !== null && php !== undefined && Math.abs(js - php) <= tolerancia);
  if (!ok) { console.log(`  ✗ ${rotulo}: js=${js} php=${php}`); falhas++; }
};
const texto = (rotulo, js, php) => {
  if (js !== php) { console.log(`  ✗ ${rotulo}: js="${js}" php="${php}"`); falhas++; }
};

function conferir(nome, filtros, php) {
  console.log(`\n── ${nome} ──`);
  const ranking = base.ranking(PADROES, filtros);

  igual('média geral', base.mediaGeral(filtros), php.media_geral);
  igual('% de acertos', base.totaisDeResposta(base.respostas(filtros)).pct_acertos, php.acertos.pct_acertos);
  igual('% de erros', base.totaisDeResposta(base.respostas(filtros)).pct_erros, php.acertos.pct_erros);

  const dist = base.distribuicao(filtros);
  Object.keys(dist).forEach(k => igual(`distribuição/${k}`, dist[k], php.distribuicao[k], 0));

  igual('nº de alunos', ranking.length, php.ranking.length, 0);
  php.ranking.forEach((linha, i) => {
    const js = ranking[i];
    if (!js) { console.log(`  ✗ falta o aluno ${linha.nome}`); falhas++; return; }
    texto(`ordem #${i + 1}`, js.aluno.full_name, linha.nome);
    igual(`${linha.nome}/média`, js.media, linha.media);
    igual(`${linha.nome}/frequência`, js.frequencia, linha.freq);
    igual(`${linha.nome}/evolução`, js.evolucao_recente, linha.evolucao);
    igual(`${linha.nome}/tendência`, js.evolucao_slope, linha.slope);
    igual(`${linha.nome}/desvio`, js.desvio, linha.desvio);
    igual(`${linha.nome}/score evolução`, js.score_evolucao, linha.score_ev);
    igual(`${linha.nome}/score consistência`, js.score_consistencia, linha.score_cons);
    igual(`${linha.nome}/índice`, js.indice, linha.indice);
    igual(`${linha.nome}/% acertos`, js.acertos.pct_acertos, linha.pct_acertos);
    ['dominados', 'intermediarios', 'dificuldades'].forEach(k => igual(`${linha.nome}/${k}`, js[k], linha[k], 0));
    texto(`${linha.nome}/classificação`, js.classificacao, linha.classe);
  });

  base.mediaPorAvaliacao(filtros).forEach((a, i) => {
    texto(`avaliação #${i + 1}`, a.nome, php.por_avaliacao[i]?.nome);
    igual(`avaliação/${a.nome}`, a.media, php.por_avaliacao[i]?.media);
  });
  base.mediaPorDisciplina(filtros).forEach((d, i) => igual(`disciplina/${d.nome}`, d.media, php.por_disciplina[i]?.media));
  base.mediaPorTurma(filtros).forEach((t, i) => igual(`turma/${t.nome}`, t.media, php.por_turma[i]?.media));
  base.aproveitamentoPorDificuldade(base.respostas(filtros))
    .forEach((d, i) => igual(`dificuldade/${d.dificuldade}`, d.aproveitamento, php.por_dificuldade[i]?.aproveitamento));

  const assuntos = base.aproveitamentoPorAssunto(base.respostas(filtros), PADROES).slice().reverse();
  igual('nº de assuntos', assuntos.length, php.assuntos.length, 0);
  php.assuntos.forEach((a, i) => {
    igual(`assunto/${a.nome}`, assuntos[i]?.aproveitamento, a.aprov);
    igual(`assunto/${a.nome}/respostas`, assuntos[i]?.respondidas, a.resp, 0);
    texto(`assunto/${a.nome}/classe`, assuntos[i]?.classificacao, a.classe);
  });

  const perdaAval = base.perdaPorAvaliacao(filtros, 50);
  php.perda_avaliacao.forEach((p, i) => {
    texto(`perda/avaliação #${i + 1}`, perdaAval[i]?.assessment_name, p.nome);
    igual(`perda/${p.nome}`, perdaAval[i]?.pontos_perdidos, p.perdidos);
  });
  const perdaAluno = base.perdaPorAluno(filtros);
  php.perda_aluno.forEach((p, i) => {
    texto(`perda/aluno #${i + 1}`, perdaAluno[i]?.full_name, p.nome);
    igual(`perda/${p.nome}`, perdaAluno[i]?.perdidos, p.perdidos);
    texto(`perda/${p.nome}/pior`, perdaAluno[i]?.pior_avaliacao, p.pior);
  });

  const alertas = gerarAlertas(base, ranking, PADROES, filtros);
  const noJs = new Set(alertas.map(a => a.message));
  const noPhp = new Set(php.alertas.map(a => a.msg));
  [...noPhp].filter(m => !noJs.has(m)).forEach(m => { console.log(`  ✗ só no PHP: ${m}`); falhas++; });
  [...noJs].filter(m => !noPhp.has(m)).forEach(m => { console.log(`  ✗ só no JS: ${m}`); falhas++; });

  console.log(`  ${ranking.length} aluno(s), ${assuntos.length} assunto(s), ${alertas.length} alerta(s) — conferidos`);
}

conferir('Administrador (sem recorte)', {}, referencia.admin);
for (const [id, ofertas] of Object.entries(referencia._ofertas_por_professor)) {
  const bloco = referencia['professor_' + id];
  if (!bloco) continue;
  const professor = dados.professores.find(p => p.id === Number(id));
  conferir(`Professor ${professor.name} (ofertas ${ofertas.join(', ')})`, { ofertas }, bloco);
}

// O escopo calculado no JS bate com o do PHP?
for (const [id, ofertas] of Object.entries(referencia._ofertas_por_professor)) {
  const js = base.ofertasDoProfessor(Number(id)).sort();
  const php = [...ofertas].sort();
  if (JSON.stringify(js) !== JSON.stringify(php)) {
    console.log(`  ✗ escopo do usuário ${id}: js=[${js}] php=[${php}]`);
    falhas++;
  }
}

if (falhas) { console.log(`\n${falhas} divergência(s).`); process.exit(1); }
console.log('\nPorte verificado: o JavaScript reproduz o PHP em todos os perfis, valores e mensagens.');
