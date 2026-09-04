/**
 * Monta demo/painel-demo.html a partir dos fragmentos de fonte/, do motor
 * analítico (analytics.js) e do dataset (dados.json).
 *
 *   node demo/montar.js
 *
 * A página final é autossuficiente: um único arquivo, sem dependências externas
 * além das fontes do Google Fonts.
 */
const fs = require("fs");
const path = require("path");

const raiz = __dirname;
const fragmentos = fs.readdirSync(path.join(raiz, "fonte")).sort();
let html = fragmentos.map(f => fs.readFileSync(path.join(raiz, "fonte", f), "utf8")).join("\n");

const dados = JSON.stringify(JSON.parse(fs.readFileSync(path.join(raiz, "dados.json"), "utf8")))
  .replace(/<\//g, "<\\/");
html = html.replace("__DADOS__", dados);

let analytics = fs.readFileSync(path.join(raiz, "analytics.js"), "utf8")
  .split("if (typeof module !== 'undefined')")[0]
  .replace(/^\/\* =+[\s\S]*?=+ \*\/\n/, "")
  .trimEnd();
html = html.replace("__ANALYTICS__", analytics);

if (html.includes("__DADOS__") || html.includes("__ANALYTICS__")) {
  console.error("Marcadores não substituídos — verifique fonte/02-casca-e-graficos.html");
  process.exit(1);
}

fs.writeFileSync(path.join(raiz, "painel-demo.html"), html);
console.log(`painel-demo.html gerado (${(html.length / 1024).toFixed(1)} KB, ${fragmentos.length} fragmentos)`);
