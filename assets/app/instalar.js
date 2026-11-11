// LuniCitas — instalar la app en el dispositivo. Licencia AGPL-3.0
// Reconoce el navegador y ofrece instalar con una pregunta sencilla:
// · Android, Windows, Chrome, Edge, Samsung: "¿Instalamos la app?" → un toque abre el aviso del propio navegador.
// · iPhone/iPad: Apple no deja que una página instale apps; se señala con una flecha el botón exacto que hay que tocar.
// · Navegadores dentro de Instagram, Facebook, WhatsApp…: no pueden instalar; se ofrece abrir la página en el navegador.
// La pregunta sale sola una vez (no vuelve a molestar en 14 días si dicen "Ahora no"); los botones [data-instalar] la abren siempre.
(function () {
  'use strict';
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function () {});
  var instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var botones = function () { return document.querySelectorAll('[data-instalar]'); };
  var mostrar = function () { botones().forEach(function (b) { b.hidden = false; }); };
  var ocultar = function () { botones().forEach(function (b) { b.hidden = true; }); };
  if (instalada) { document.addEventListener('DOMContentLoaded', ocultar); return; }

  var ua = navigator.userAgent;
  var esIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var dentroDeApp = /FBAN|FBAV|Instagram|Line\/|WhatsApp|Snapchat|TikTok|LinkedInApp|GSA\//.test(ua);
  var iosNav = /CriOS/.test(ua) ? 'chrome' : /FxiOS/.test(ua) ? 'firefox' : /EdgiOS/.test(ua) ? 'edge' : 'safari';
  var aviso = null;
  var lang = function () { return ((document.documentElement.lang || navigator.language || 'es').slice(0, 2)); };
  var T = {
    es: { pregunta: '¿Instalamos la app en tu dispositivo?', sub: 'Abre tus citas con un toque desde la pantalla de inicio. Es gratis y casi no ocupa espacio.',
          si: 'Instalar', no: 'Ahora no', iosT: 'Instala la app en 2 toques',
          ios: { safari: ['Toca <b>Compartir</b> <span class="lc-ic">⬆︎</span> abajo en la pantalla.', 'Elige <b>Añadir a pantalla de inicio</b> y toca <b>Añadir</b>.'],
                 chrome: ['Toca <b>Compartir</b> <span class="lc-ic">⬆︎</span> arriba a la derecha.', 'Elige <b>Añadir a pantalla de inicio</b> y toca <b>Añadir</b>.'],
                 otro: ['Toca el menú <b>Compartir</b> <span class="lc-ic">⬆︎</span> de tu navegador.', 'Elige <b>Añadir a pantalla de inicio</b>.'] },
          apple: 'Apple no permite que las páginas se instalen solas: este es el único paso.', ok: 'Entendido',
          appT: 'Abre esta página en tu navegador', app: 'Desde esta app no se puede instalar. Copia el enlace y ábrelo en Safari o Chrome.', copiar: 'Copiar enlace', copiado: '¡Copiado!' },
    en: { pregunta: 'Install the app on your device?', sub: 'Open your appointments with one tap from your home screen. Free, and it takes almost no space.',
          si: 'Install', no: 'Not now', iosT: 'Install the app in 2 taps',
          ios: { safari: ['Tap <b>Share</b> <span class="lc-ic">⬆︎</span> at the bottom of the screen.', 'Choose <b>Add to Home Screen</b> and tap <b>Add</b>.'],
                 chrome: ['Tap <b>Share</b> <span class="lc-ic">⬆︎</span> at the top right.', 'Choose <b>Add to Home Screen</b> and tap <b>Add</b>.'],
                 otro: ['Tap your browser\'s <b>Share</b> <span class="lc-ic">⬆︎</span> menu.', 'Choose <b>Add to Home Screen</b>.'] },
          apple: 'Apple does not let web pages install themselves: this is the only step.', ok: 'Got it',
          appT: 'Open this page in your browser', app: 'This app can\'t install it. Copy the link and open it in Safari or Chrome.', copiar: 'Copy link', copiado: 'Copied!' },
    pt: { pregunta: 'Instalamos o app no seu dispositivo?', sub: 'Abra seus horários com um toque na tela inicial. É grátis e quase não ocupa espaço.',
          si: 'Instalar', no: 'Agora não', iosT: 'Instale o app em 2 toques',
          ios: { safari: ['Toque em <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> embaixo na tela.', 'Escolha <b>Adicionar à Tela de Início</b> e toque em <b>Adicionar</b>.'],
                 chrome: ['Toque em <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> no canto superior direito.', 'Escolha <b>Adicionar à Tela de Início</b> e toque em <b>Adicionar</b>.'],
                 otro: ['Toque no menu <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> do navegador.', 'Escolha <b>Adicionar à Tela de Início</b>.'] },
          apple: 'A Apple não permite que páginas se instalem sozinhas: este é o único passo.', ok: 'Entendi',
          appT: 'Abra esta página no navegador', app: 'Por este app não dá para instalar. Copie o link e abra no Safari ou Chrome.', copiar: 'Copiar link', copiado: 'Copiado!' }
  };
  var t = function () { return T[lang()] || T.es; };

  function css() {
    if (document.getElementById('lc-inst-css')) return;
    var c = document.createElement('style'); c.id = 'lc-inst-css';
    c.textContent = '.lc-d{border:0;border-radius:22px;padding:22px;max-width:360px;width:calc(100% - 32px);margin:auto;font-family:inherit;color:#1d1b2e;box-shadow:0 30px 60px -20px rgba(29,27,46,.5)}' +
      '.lc-d::backdrop{background:rgba(29,27,46,.45)}.lc-d img{width:56px;height:56px;border-radius:14px;display:block;margin-bottom:12px}' +
      '.lc-d h2{font-size:20px;margin:0 0 6px;line-height:1.25}.lc-d p{font-size:14px;color:#5e5c73;margin:0 0 16px;line-height:1.55}.lc-d ol{padding-left:20px;margin:0 0 12px;font-size:15px;line-height:1.6}.lc-d li{margin-bottom:8px}' +
      '.lc-d .lc-b{display:flex;gap:8px}.lc-d button{flex:1;border:0;border-radius:12px;padding:13px;font-family:inherit;font-size:16px;font-weight:600;cursor:pointer}' +
      '.lc-d .si{background:#7b5cff;color:#fff}.lc-d .no{background:#ebe9f3;color:#5e5c73}.lc-ic{display:inline-block;background:#efeaff;color:#5b45d6;border-radius:6px;padding:0 6px}' +
      '.lc-flecha{position:fixed;z-index:2147483647;font-size:44px;color:#7b5cff;text-shadow:0 2px 8px rgba(0,0,0,.25);pointer-events:none;animation:lcb 1s ease-in-out infinite}' +
      '.lc-flecha.abajo{bottom:8px;left:50%;margin-left:-16px}.lc-flecha.arriba{top:6px;right:58px}' +
      '@keyframes lcb{50%{transform:translateY(8px)}}.lc-flecha.arriba{animation-name:lcb2}@keyframes lcb2{50%{transform:translateY(-8px)}}' +
      '@media (prefers-reduced-motion:reduce){.lc-flecha{animation:none}}';
    document.head.appendChild(c);
  }
  function dialogo(html, alCerrar) {
    css();
    var d = document.createElement('dialog'); d.className = 'lc-d'; d.setAttribute('aria-labelledby', 'lc-d-t'); d.innerHTML = html;
    document.body.appendChild(d);
    d.addEventListener('close', function () { if (alCerrar) alCerrar(); d.remove(); });
    d.showModal(); return d;
  }
  var icono = '<img src="assets/app/icono-192.png" alt="">';

  // Android, Windows y demás navegadores Chromium: pregunta y un toque.
  function preguntar() {
    var x = t();
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.pregunta + '</h2><p>' + x.sub + '</p><div class="lc-b"><button class="no" value="no">' + x.no + '</button><button class="si" value="si">' + x.si + '</button></div>');
    d.querySelector('.no').onclick = function () { try { localStorage.setItem('lc-inst-no', Date.now()); } catch (e) {} d.close(); };
    d.querySelector('.si').onclick = function () {
      d.close();
      if (navigator.install) { navigator.install().catch(function () {}); return; }
      if (aviso) { aviso.prompt(); aviso.userChoice.then(function (r) { aviso = null; if (r.outcome === 'accepted') ocultar(); }); }
    };
  }
  // iPhone: señala el botón exacto según el navegador.
  function guiaIOS() {
    var x = t(), pasos = x.ios[iosNav === 'safari' ? 'safari' : iosNav === 'chrome' ? 'chrome' : 'otro'];
    var f = document.createElement('div'); f.className = 'lc-flecha ' + (iosNav === 'chrome' ? 'arriba' : 'abajo'); f.textContent = iosNav === 'chrome' ? '⬆' : '⬇';
    document.body.appendChild(f);
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.iosT + '</h2><ol><li>' + pasos[0] + '</li><li>' + pasos[1] + '</li></ol><p>' + x.apple + '</p><div class="lc-b"><button class="si">' + x.ok + '</button></div>',
      function () { setTimeout(function () { f.remove(); }, 6000); });
    d.querySelector('.si').onclick = function () { d.close(); };
  }
  function abrirFuera() {
    var x = t();
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.appT + '</h2><p>' + x.app + '</p><div class="lc-b"><button class="no">' + x.no + '</button><button class="si">' + x.copiar + '</button></div>');
    d.querySelector('.no').onclick = function () { d.close(); };
    d.querySelector('.si').onclick = function (ev) {
      var b = ev.target; (navigator.clipboard ? navigator.clipboard.writeText(location.href) : Promise.reject()).then(function () { b.textContent = x.copiado; }, function () { b.textContent = location.href; });
    };
  }
  function ofrecer() { if (dentroDeApp) abrirFuera(); else if (aviso || navigator.install) preguntar(); else if (esIOS) guiaIOS(); }
  window.lcInstalar = ofrecer;

  // Pregunta automática: una sola vez, unos segundos después de abrir, si no dijeron "Ahora no" en los últimos 14 días.
  function puedePreguntarSolo() {
    try { var n = +localStorage.getItem('lc-inst-no') || 0; if (Date.now() - n < 14 * 864e5) return false; if (sessionStorage.getItem('lc-inst-visto')) return false; sessionStorage.setItem('lc-inst-visto', '1'); } catch (e) {}
    return !document.querySelector('dialog[open]');
  }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); aviso = e; mostrar();
    setTimeout(function () { if (aviso && puedePreguntarSolo()) preguntar(); }, 4000);
  });
  window.addEventListener('appinstalled', ocultar);
  document.addEventListener('DOMContentLoaded', function () { if (esIOS || dentroDeApp || navigator.install) mostrar(); });
  document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-instalar]'); if (!b) return; e.preventDefault(); ofrecer(); });
})();
