/* Comportamentos da interface. Sem dependências. */
(function () {
  'use strict';

  // ------------------------------------------------------------- menu móvel
  var menu = document.querySelector('.menu');
  var botao = document.querySelector('.menu-botao');
  var fundo = null;

  function fecharMenu() {
    if (!menu) return;
    menu.classList.remove('aberto');
    if (fundo) { fundo.remove(); fundo = null; }
  }

  if (botao && menu) {
    botao.addEventListener('click', function () {
      var aberto = menu.classList.toggle('aberto');
      if (aberto) {
        fundo = document.createElement('div');
        fundo.className = 'fundo-menu';
        fundo.addEventListener('click', fecharMenu);
        document.body.appendChild(fundo);
      } else {
        fecharMenu();
      }
    });
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fecharMenu(); });

  // ------------------------------------------- confirmação antes de excluir
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var mensagem = form.getAttribute('data-confirmar');
    if (mensagem && !window.confirm(mensagem)) {
      e.preventDefault();
    }
  });

  // ------------------------------- formulários de filtro: aplicar ao mudar
  document.querySelectorAll('[data-auto-filtro] select, [data-auto-filtro] input[type=date]').forEach(function (campo) {
    campo.addEventListener('change', function () { campo.form.submit(); });
  });

  // ---------------------------- selects dependentes: disciplina -> assuntos
  document.querySelectorAll('[data-carrega-assuntos]').forEach(function (origem) {
    var destinoId = origem.getAttribute('data-carrega-assuntos');
    var destino = document.getElementById(destinoId);
    if (!destino) return;

    origem.addEventListener('change', function () {
      var id = origem.value;
      destino.innerHTML = '<option value="">Carregando…</option>';
      if (!id) { destino.innerHTML = '<option value="">— Sem assunto —</option>'; return; }

      fetch(destino.getAttribute('data-url-base') + '/' + id, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (itens) {
          var html = '<option value="">— Sem assunto —</option>';
          itens.forEach(function (item) {
            html += '<option value="' + item.id + '">' + item.label.replace(/</g, '&lt;') + '</option>';
          });
          destino.innerHTML = html;
        })
        .catch(function () { destino.innerHTML = '<option value="">Erro ao carregar</option>'; });
    });
  });

  // ------------------- marcação em massa na grade de resultados e na chamada
  document.querySelectorAll('[data-marcar-todos]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var alvo = botao.getAttribute('data-marcar-todos');   // ex.: "result[12]" ou "status"
      var valor = botao.getAttribute('data-valor');
      var escopo = botao.closest('[data-escopo]') || document;
      escopo.querySelectorAll('input[type=radio]').forEach(function (radio) {
        if (radio.name.indexOf(alvo) === 0 && radio.value === valor) {
          radio.checked = true;
        }
      });
    });
  });

  // ------------------------------------------------ busca instantânea local
  document.querySelectorAll('[data-filtra-tabela]').forEach(function (campo) {
    var tabela = document.querySelector(campo.getAttribute('data-filtra-tabela'));
    if (!tabela) return;
    campo.addEventListener('input', function () {
      var termo = campo.value.toLowerCase().trim();
      tabela.querySelectorAll('tbody tr').forEach(function (linha) {
        linha.style.display = !termo || linha.textContent.toLowerCase().indexOf(termo) !== -1 ? '' : 'none';
      });
    });
  });

  // ------------------------------------------------------------- impressão
  document.querySelectorAll('[data-imprimir]').forEach(function (botao) {
    botao.addEventListener('click', function (e) { e.preventDefault(); window.print(); });
  });
})();
