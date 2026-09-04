/* Camada fina sobre o Chart.js: paleta única, rótulos em pt-BR e degradação
   suave quando a biblioteca não carrega (rede bloqueada, offline). */
(function (global) {
  'use strict';

  var PALETA = {
    azul: '#2489c4',
    azulEscuro: '#14557d',
    verde: '#147d5b',
    ambar: '#c07800',
    vermelho: '#b32424',
    roxo: '#6b3fa0',
    cinza: '#7d8b9a'
  };
  var SEQUENCIA = [PALETA.azul, PALETA.verde, PALETA.roxo, PALETA.ambar, PALETA.vermelho, PALETA.azulEscuro, PALETA.cinza];

  function disponivel() { return typeof global.Chart !== 'undefined'; }

  function semDados(canvas, mensagem) {
    var alvo = canvas.parentNode;
    alvo.innerHTML = '<div class="grafico__vazio">' + (mensagem || 'Sem dados suficientes para este gráfico.') + '</div>';
  }

  function corPorFaixa(valor, dominio, intermediario) {
    if (valor === null || valor === undefined) return PALETA.cinza;
    if (valor >= (dominio || 80)) return PALETA.verde;
    if (valor >= (intermediario || 60)) return PALETA.ambar;
    return PALETA.vermelho;
  }

  var baseOpcoes = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { labels: { boxWidth: 12, font: { size: 11 } } },
      tooltip: {
        callbacks: {
          label: function (ctx) {
            var v = ctx.parsed.y !== undefined && ctx.parsed.y !== null ? ctx.parsed.y : ctx.parsed;
            if (v === null || v === undefined || isNaN(v)) return ctx.dataset.label + ': sem dado';
            return ctx.dataset.label + ': ' + Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
          }
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { font: { size: 11 }, callback: function (v) { return v; } },
        grid: { color: 'rgba(0,0,0,.06)' }
      },
      x: { ticks: { font: { size: 11 }, autoSkip: true, maxRotation: 40 }, grid: { display: false } }
    }
  };

  function mesclar(destino, origem) {
    Object.keys(origem || {}).forEach(function (chave) {
      if (origem[chave] && typeof origem[chave] === 'object' && !Array.isArray(origem[chave])) {
        destino[chave] = mesclar(Object.assign({}, destino[chave]), origem[chave]);
      } else {
        destino[chave] = origem[chave];
      }
    });
    return destino;
  }

  function criar(id, tipo, dados, opcoes) {
    var canvas = document.getElementById(id);
    if (!canvas) return null;
    if (!disponivel()) { semDados(canvas, 'Biblioteca de gráficos indisponível. Os dados continuam nas tabelas.'); return null; }

    var temDado = (dados.datasets || []).some(function (ds) {
      return (ds.data || []).some(function (v) { return v !== null && v !== undefined && !isNaN(v); });
    });
    if (!temDado) { semDados(canvas); return null; }

    return new global.Chart(canvas, {
      type: tipo,
      data: dados,
      options: mesclar(JSON.parse(JSON.stringify(baseOpcoes)), opcoes || {})
    });
  }

  global.Painel = {
    paleta: PALETA,
    sequencia: SEQUENCIA,
    corPorFaixa: corPorFaixa,

    /** Linha simples ou múltiplas séries. */
    linha: function (id, labels, series, opcoes) {
      return criar(id, 'line', {
        labels: labels,
        datasets: series.map(function (s, i) {
          return {
            label: s.nome,
            data: s.dados,
            borderColor: s.cor || SEQUENCIA[i % SEQUENCIA.length],
            backgroundColor: (s.cor || SEQUENCIA[i % SEQUENCIA.length]) + '22',
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: .28,
            fill: series.length === 1,
            spanGaps: true
          };
        })
      }, opcoes);
    },

    /** Barras verticais ou horizontais (indexAxis: 'y'). */
    barras: function (id, labels, series, opcoes) {
      return criar(id, 'bar', {
        labels: labels,
        datasets: series.map(function (s, i) {
          return {
            label: s.nome,
            data: s.dados,
            backgroundColor: s.cores || s.cor || SEQUENCIA[i % SEQUENCIA.length],
            borderRadius: 4,
            maxBarThickness: 46
          };
        })
      }, opcoes);
    },

    /** Rosca para distribuições e composições. */
    rosca: function (id, labels, valores, cores, opcoes) {
      return criar(id, 'doughnut', {
        labels: labels,
        datasets: [{ data: valores, backgroundColor: cores || SEQUENCIA, borderWidth: 2, borderColor: '#fff' }]
      }, mesclar({ scales: { x: { display: false }, y: { display: false } }, cutout: '58%' }, opcoes || {}));
    },

    /** Radar para comparar perfis (desempenho por dimensão). */
    radar: function (id, labels, series, opcoes) {
      return criar(id, 'radar', {
        labels: labels,
        datasets: series.map(function (s, i) {
          var cor = s.cor || SEQUENCIA[i % SEQUENCIA.length];
          return {
            label: s.nome, data: s.dados,
            borderColor: cor, backgroundColor: cor + '33', borderWidth: 2, pointRadius: 3
          };
        })
      }, mesclar({ scales: { x: { display: false }, y: { display: false }, r: { beginAtZero: true, max: 100, ticks: { font: { size: 10 } } } } }, opcoes || {}));
    }
  };
})(window);
