/**
 * Prova que o motor analítico em JavaScript (analytics.js, usado na
 * demonstração) reproduz exatamente o que o PHP calcula no servidor.
 *
 *   php database/migrate.php --fresh --seed --demo
 *   php demo/exportar.php
 *   node demo/verificar.js
 *
 * Compara ranking, médias, frequência, evolução, consistência, índice,
 * aproveitamento por assunto e por dificuldade, distribuição e o texto de
 * cada alerta. Sai com código 1 na primeira divergência.
 */
const { Base, gerarAlertas, PADROES } = require("./analytics.js");
const dados = require("./dados.json");
const ref = require("./referencia.json");

const base = new Base(dados);
const ranking = base.ranking(PADROES);
let falhas = 0;

const igual = (rotulo, js, php, tolerancia = 0.011) => {
  const ok = (js === null && php === null) ||
             (js !== null && php !== null && Math.abs(js - php) <= tolerancia);
  if (!ok) { console.log(`  ✗ ${rotulo}: js=${js} php=${php}`); falhas++; }
};

console.log("Ranking");
ref.ranking.forEach((php, i) => {
  const js = ranking[i];
  if (js.aluno.full_name !== php.nome) {
    console.log(`  ✗ ordem na posição ${i + 1}: js=${js.aluno.full_name} php=${php.nome}`); falhas++; return;
  }
  igual(`${php.nome}/média`, js.media, php.media);
  igual(`${php.nome}/frequência`, js.frequencia, php.freq);
  igual(`${php.nome}/evolução recente`, js.evolucao_recente, php.evolucao);
  igual(`${php.nome}/tendência`, js.evolucao_slope, php.slope);
  igual(`${php.nome}/desvio`, js.desvio, php.desvio);
  igual(`${php.nome}/score evolução`, js.score_evolucao, php.score_ev);
  igual(`${php.nome}/score consistência`, js.score_consistencia, php.score_cons);
  igual(`${php.nome}/índice`, js.indice, php.indice);
  igual(`${php.nome}/% acertos`, js.acertos.pct_acertos, php.pct_acertos);
  igual(`${php.nome}/% erros`, js.acertos.pct_erros, php.pct_erros);
  ["dominados", "intermediarios", "dificuldades"].forEach(k => igual(`${php.nome}/${k}`, js[k], php[k], 0));
  if (js.classificacao !== php.classe) { console.log(`  ✗ ${php.nome}/classificação: js=${js.classificacao} php=${php.classe}`); falhas++; }
  console.log(`  ${php.nome.padEnd(23)} pos ${js.posicao}  média ${js.media}  índice ${js.indice}  ${js.classificacao}`);
});

console.log("Agregados");
igual("média geral", base.mediaGeral(), ref.media_geral);
igual("% de acertos", base.totaisDeResposta(dados.respostas).pct_acertos, ref.acertos.pct_acertos);
const dist = base.distribuicao();
Object.keys(dist).forEach(k => igual(`distribuição/${k}`, dist[k], ref.distribuicao[k], 0));
base.mediaPorAvaliacao().forEach((a, i) => igual(`avaliação/${a.nome}`, a.media, ref.por_avaliacao[i].media));
base.aproveitamentoPorDificuldade(dados.respostas)
  .forEach((d, i) => igual(`dificuldade/${d.dificuldade}`, d.aproveitamento, ref.por_dificuldade[i].aproveitamento));

const assuntos = base.aproveitamentoPorAssunto(dados.respostas, PADROES).slice().reverse();
ref.assuntos.forEach((php, i) => {
  igual(`assunto/${php.nome}`, assuntos[i].aproveitamento, php.aprov);
  igual(`assunto/${php.nome}/respostas`, assuntos[i].respondidas, php.resp, 0);
  igual(`assunto/${php.nome}/acertos`, assuntos[i].acertos, php.acertos, 0);
  if (assuntos[i].classificacao !== php.classe) { console.log(`  ✗ assunto/${php.nome}/classificação`); falhas++; }
});

console.log("Alertas");
const alertas = gerarAlertas(base, ranking, PADROES);
const noJs = new Set(alertas.map(a => a.message));
const noPhp = new Set(ref.alertas.map(a => a.msg));
[...noPhp].filter(m => !noJs.has(m)).forEach(m => { console.log(`  ✗ só no PHP: ${m}`); falhas++; });
[...noJs].filter(m => !noPhp.has(m)).forEach(m => { console.log(`  ✗ só no JS: ${m}`); falhas++; });
console.log(`  ${alertas.length} mensagens, idênticas às do PHP`);

if (falhas) { console.log(`\n${falhas} divergência(s).`); process.exit(1); }
console.log("\nPorte verificado: o JavaScript reproduz o PHP em todos os valores e mensagens.");
