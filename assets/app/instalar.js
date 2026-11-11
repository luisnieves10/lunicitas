// LuniCitas — instalar la app en el celular. Licencia AGPL-3.0
// Muestra los botones [data-instalar] solo cuando se puede instalar:
// Android y Chrome usan el aviso del navegador; en iPhone se explican los dos toques en Safari.
(function () {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function () {});
  var instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var botones = function () { return document.querySelectorAll('[data-instalar]'); };
  var mostrar = function () { botones().forEach(function (b) { b.hidden = false; }); };
  var ocultar = function () { botones().forEach(function (b) { b.hidden = true; }); };
  if (instalada) return ocultar();

  var ua = navigator.userAgent;
  var esIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var aviso = null;

  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); aviso = e; mostrar(); });
  window.addEventListener('appinstalled', ocultar);
  if (esIOS) document.addEventListener('DOMContentLoaded', mostrar);

  function instruccionesIOS() {
    var d = document.getElementById('lc-ios');
    if (!d) {
      d = document.createElement('dialog'); d.id = 'lc-ios';
      d.setAttribute('aria-labelledby', 'lc-ios-t');
      var tx = {
        es: ['Instala la app en tu iPhone', 'Abre esta página en <b>Safari</b>.', 'Toca el botón <b>Compartir</b> <span aria-hidden="true">(el cuadrado con la flecha hacia arriba)</span>.', 'Elige <b>Añadir a pantalla de inicio</b> y toca <b>Añadir</b>.', 'Entendido'],
        en: ['Install the app on your iPhone', 'Open this page in <b>Safari</b>.', 'Tap the <b>Share</b> button <span aria-hidden="true">(the square with the arrow pointing up)</span>.', 'Choose <b>Add to Home Screen</b> and tap <b>Add</b>.', 'Got it'],
        pt: ['Instale o app no seu iPhone', 'Abra esta página no <b>Safari</b>.', 'Toque no botão <b>Compartilhar</b> <span aria-hidden="true">(o quadrado com a seta para cima)</span>.', 'Escolha <b>Adicionar à Tela de Início</b> e toque em <b>Adicionar</b>.', 'Entendi'],
      }; tx = tx[(document.documentElement.lang || 'es').slice(0, 2)] || tx.es;
      d.innerHTML = '<h2 id="lc-ios-t">' + tx[0] + '</h2><ol><li>' + tx[1] + '</li><li>' + tx[2] + '</li><li>' + tx[3] + '</li></ol>' +
        '<button type="button">' + tx[4] + '</button>';
      d.querySelector('button').onclick = function () { d.close(); };
      var css = document.createElement('style');
      css.textContent = '#lc-ios{border:0;border-radius:20px;padding:24px;max-width:340px;width:calc(100% - 32px);margin:auto;font-family:inherit;color:#1d1b2e;box-shadow:0 30px 60px -20px rgba(29,27,46,.5)}' +
        '#lc-ios::backdrop{background:rgba(29,27,46,.45)}#lc-ios h2{font-size:19px;margin:0 0 12px}#lc-ios ol{padding-left:20px;margin:0 0 18px;line-height:1.6;font-size:15px}' +
        '#lc-ios li{margin-bottom:8px}#lc-ios button{width:100%;background:#7b5cff;color:#fff;border:0;border-radius:12px;padding:12px;font-family:inherit;font-size:16px;font-weight:600;cursor:pointer}';
      document.head.appendChild(css); document.body.appendChild(d);
    }
    d.showModal();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-instalar]'); if (!b) return;
    e.preventDefault();
    if (aviso) { aviso.prompt(); aviso.userChoice.then(function () { aviso = null; ocultar(); }); }
    else if (esIOS) instruccionesIOS();
  });
})();
