// LuniCitas — instalar la app en el dispositivo. Licencia AGPL-3.0
// Reconoce el teléfono y el navegador, y pregunta "¿Instalamos la app en tu dispositivo?":
// · Android: descarga la app de LuniCitas (LuniCitas.apk) y explica los 2 toques para instalarla; luego abre esta página en la app.
// · iPhone/iPad con Safari: descarga el perfil de Apple que pone el ícono de la app en la pantalla de inicio.
// · iPhone con otro navegador: pide abrir la página en Safari (o añadirla desde Compartir).
// · Windows, Mac y computadoras: el aviso de instalar del propio navegador (Chrome, Edge).
// · Navegadores dentro de Instagram, Facebook, WhatsApp…: no pueden instalar; se ofrece abrir la página en el navegador.
// Dentro de la app ya instalada no se muestra nada. La pregunta sale sola una vez por visita (y no vuelve en 14 días
// si dicen "Ahora no"); los botones [data-instalar] la abren siempre.
(function () {
  'use strict';
  var ua = navigator.userAgent;
  var enApp = /LuniCitasApp\//.test(ua);
  if ('serviceWorker' in navigator && !enApp) navigator.serviceWorker.register('sw.js').catch(function () {});
  var instalada = enApp || window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var botones = function () { return document.querySelectorAll('[data-instalar]'); };
  var mostrar = function () { botones().forEach(function (b) { b.hidden = false; }); };
  var ocultar = function () { botones().forEach(function (b) { b.hidden = true; }); };
  if (instalada) { document.addEventListener('DOMContentLoaded', ocultar); return; }

  var esIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var esAndroid = /Android/i.test(ua);
  var dentroDeApp = /FBAN|FBAV|Instagram|Line\/|WhatsApp|Snapchat|TikTok|LinkedInApp|GSA\/|; wv\)/.test(ua);
  var iosNav = /CriOS/.test(ua) ? 'chrome' : /FxiOS/.test(ua) ? 'firefox' : /EdgiOS/.test(ua) ? 'edge' : 'safari';
  var aviso = null;
  var APK = 'descargas/LuniCitas.apk';

  // Qué abre la app: la página de este negocio, la agenda del dueño o la bienvenida.
  var RESERVADAS = /^(app|app-entrar|entrar|registro|recuperar|contacto|terminos|privacidad|terms|privacy|inicio|admin|legal|calendario|offline|perfil-ios|descargas|android)$/;
  function destino() {
    var p = location.pathname.replace(/\/+$/, ''), ult = p.split('/').pop(), n = new URLSearchParams(location.search).get('n');
    if (/admin\.php$|\/entrar$/.test(p)) return { perfil: 'a=agenda', ruta: 'admin.php' };
    if (n && /^[a-z0-9-]{2,40}$/.test(n)) return { perfil: 'n=' + n, ruta: n };
    if (/^[a-z0-9-]{2,40}$/.test(ult) && !RESERVADAS.test(ult)) return { perfil: 'n=' + ult, ruta: ult };
    return { perfil: '', ruta: 'app' };
  }
  function urlApp() { return 'intent://' + location.host + '/' + destino().ruta + '#Intent;scheme=https;package=com.lunicitas.app;S.browser_fallback_url=' + encodeURIComponent(location.href) + ';end'; }

  var lang = function () { return ((document.documentElement.lang || navigator.language || 'es').slice(0, 2)); };
  var T = {
    es: { pregunta: '¿Instalamos la app en tu dispositivo?', sub: 'Abre tus citas con un toque desde la pantalla de inicio. Es gratis y casi no ocupa espacio.',
          si: 'Instalar', no: 'Ahora no', ok: 'Entendido', listo: 'Listo',
          andT: 'Termina de instalar la app', and: ['Abre <b>LuniCitas.apk</b> cuando termine de bajar (en la notificación o en <b>Descargas</b>).',
            'Si Android pregunta, toca <b>Configuración</b> → <b>Permitir de esta fuente</b> y vuelve atrás.', 'Toca <b>Instalar</b>.'],
          andNota: 'Es la app oficial de LuniCitas. Android pide permiso porque no viene de Google Play.',
          abrirApp: 'Ya la instalé: abrir en la app', sinDescargar: 'Prefiero añadirla sin descargar',
          iosT: 'Instala la app en tu iPhone', ios: ['Toca <b>Permitir</b> para bajar la app.', 'Abre <b>Ajustes</b> y toca <b>Perfil descargado</b> (arriba del todo).',
            'Toca <b>Instalar</b> arriba a la derecha y confirma. El ícono aparece en tu pantalla de inicio.'],
          iosNota: 'Apple puede mostrar “No verificado”: es normal. El perfil solo añade el ícono de la app y lo puedes borrar cuando quieras.',
          otraForma: 'Otra forma: desde el botón Compartir',
          safariT: 'Abre esta página en Safari', safari: 'En iPhone, la app se instala desde Safari. Copia el enlace y pégalo en Safari.',
          shareT: 'Añádela desde Compartir', share: { chrome: ['Toca <b>Compartir</b> <span class="lc-ic">⬆︎</span> arriba a la derecha.', 'Elige <b>Añadir a pantalla de inicio</b> y toca <b>Añadir</b>.'],
            safari: ['Toca <b>Compartir</b> <span class="lc-ic">⬆︎</span> abajo en la pantalla.', 'Elige <b>Añadir a pantalla de inicio</b> y toca <b>Añadir</b>.'],
            otro: ['Toca el menú <b>Compartir</b> <span class="lc-ic">⬆︎</span> de tu navegador.', 'Elige <b>Añadir a pantalla de inicio</b>.'] },
          appT: 'Abre esta página en tu navegador', app: 'Desde esta app no se puede instalar. Copia el enlace y ábrelo en Safari o Chrome.', copiar: 'Copiar enlace', copiado: '¡Copiado!',
          legal: 'Al instalar aceptas los <a href="terminos" target="_blank">Términos</a>.' },
    en: { pregunta: 'Install the app on your device?', sub: 'Open your appointments with one tap from your home screen. Free, and it takes almost no space.',
          si: 'Install', no: 'Not now', ok: 'Got it', listo: 'Done',
          andT: 'Finish installing the app', and: ['Open <b>LuniCitas.apk</b> when it finishes downloading (in the notification or in <b>Downloads</b>).',
            'If Android asks, tap <b>Settings</b> → <b>Allow from this source</b> and go back.', 'Tap <b>Install</b>.'],
          andNota: 'This is the official LuniCitas app. Android asks because it does not come from Google Play.',
          abrirApp: 'I installed it: open in the app', sinDescargar: 'Add it without downloading instead',
          iosT: 'Install the app on your iPhone', ios: ['Tap <b>Allow</b> to download the app.', 'Open <b>Settings</b> and tap <b>Profile Downloaded</b> (at the very top).',
            'Tap <b>Install</b> at the top right and confirm. The icon appears on your home screen.'],
          iosNota: 'Apple may show “Not Verified”: that is normal. The profile only adds the app icon and you can remove it any time.',
          otraForma: 'Another way: from the Share button',
          safariT: 'Open this page in Safari', safari: 'On iPhone, the app installs from Safari. Copy the link and paste it in Safari.',
          shareT: 'Add it from Share', share: { chrome: ['Tap <b>Share</b> <span class="lc-ic">⬆︎</span> at the top right.', 'Choose <b>Add to Home Screen</b> and tap <b>Add</b>.'],
            safari: ['Tap <b>Share</b> <span class="lc-ic">⬆︎</span> at the bottom of the screen.', 'Choose <b>Add to Home Screen</b> and tap <b>Add</b>.'],
            otro: ['Tap your browser\'s <b>Share</b> <span class="lc-ic">⬆︎</span> menu.', 'Choose <b>Add to Home Screen</b>.'] },
          appT: 'Open this page in your browser', app: 'This app can\'t install it. Copy the link and open it in Safari or Chrome.', copiar: 'Copy link', copiado: 'Copied!',
          legal: 'By installing you accept the <a href="terminos" target="_blank">Terms</a>.' },
    pt: { pregunta: 'Instalamos o app no seu dispositivo?', sub: 'Abra seus horários com um toque na tela inicial. É grátis e quase não ocupa espaço.',
          si: 'Instalar', no: 'Agora não', ok: 'Entendi', listo: 'Pronto',
          andT: 'Termine de instalar o app', and: ['Abra <b>LuniCitas.apk</b> quando terminar de baixar (na notificação ou em <b>Downloads</b>).',
            'Se o Android perguntar, toque em <b>Configurações</b> → <b>Permitir desta fonte</b> e volte.', 'Toque em <b>Instalar</b>.'],
          andNota: 'É o app oficial da LuniCitas. O Android pede permissão porque não vem do Google Play.',
          abrirApp: 'Já instalei: abrir no app', sinDescargar: 'Prefiro adicionar sem baixar',
          iosT: 'Instale o app no seu iPhone', ios: ['Toque em <b>Permitir</b> para baixar o app.', 'Abra <b>Ajustes</b> e toque em <b>Perfil Transferido</b> (no topo).',
            'Toque em <b>Instalar</b> no canto superior direito e confirme. O ícone aparece na tela inicial.'],
          iosNota: 'A Apple pode mostrar “Não Verificado”: é normal. O perfil só adiciona o ícone do app e você pode removê-lo quando quiser.',
          otraForma: 'Outra forma: pelo botão Compartilhar',
          safariT: 'Abra esta página no Safari', safari: 'No iPhone, o app é instalado pelo Safari. Copie o link e cole no Safari.',
          shareT: 'Adicione pelo Compartilhar', share: { chrome: ['Toque em <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> no canto superior direito.', 'Escolha <b>Adicionar à Tela de Início</b> e toque em <b>Adicionar</b>.'],
            safari: ['Toque em <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> embaixo na tela.', 'Escolha <b>Adicionar à Tela de Início</b> e toque em <b>Adicionar</b>.'],
            otro: ['Toque no menu <b>Compartilhar</b> <span class="lc-ic">⬆︎</span> do navegador.', 'Escolha <b>Adicionar à Tela de Início</b>.'] },
          appT: 'Abra esta página no navegador', app: 'Por este app não dá para instalar. Copie o link e abra no Safari ou Chrome.', copiar: 'Copiar link', copiado: 'Copiado!',
          legal: 'Ao instalar você aceita os <a href="terminos" target="_blank">Termos</a>.' }
  };
  var t = function () { return T[lang()] || T.es; };

  function css() {
    if (document.getElementById('lc-inst-css')) return;
    var c = document.createElement('style'); c.id = 'lc-inst-css';
    c.textContent = '.lc-d{border:0;border-radius:22px;padding:22px;max-width:380px;width:calc(100% - 32px);margin:auto;font-family:inherit;color:#1d1b2e;box-shadow:0 30px 60px -20px rgba(29,27,46,.5)}' +
      '.lc-d::backdrop{background:rgba(29,27,46,.45)}.lc-d img{width:56px;height:56px;border-radius:14px;display:block;margin-bottom:12px}' +
      '.lc-d h2{font-size:20px;margin:0 0 6px;line-height:1.25}.lc-d p{font-size:14px;color:#5e5c73;margin:0 0 16px;line-height:1.55}.lc-d ol{padding-left:20px;margin:0 0 12px;font-size:15px;line-height:1.6}.lc-d li{margin-bottom:8px}' +
      '.lc-d .lc-b{display:flex;gap:8px}.lc-d button{flex:1;border:0;border-radius:12px;padding:13px;font-family:inherit;font-size:16px;font-weight:600;cursor:pointer}' +
      '.lc-d .si{background:#7b5cff;color:#fff}.lc-d .no{background:#ebe9f3;color:#5e5c73}.lc-ic{display:inline-block;background:#efeaff;color:#5b45d6;border-radius:6px;padding:0 6px}' +
      '.lc-d .lc-l{display:block;width:100%;background:none;color:#5b45d6;font-size:14px;font-weight:600;padding:12px 0 0;text-decoration:underline}.lc-d small{display:block;font-size:12px;color:#8a88a0;margin-top:12px}.lc-d small a{color:#5b45d6}' +
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
  var lista = function (pasos) { return '<ol><li>' + pasos.join('</li><li>') + '</li></ol>'; };
  var ahoraNo = function (d) { try { localStorage.setItem('lc-inst-no', Date.now()); } catch (e) {} d.close(); };

  // La pregunta, igual en todos los dispositivos. "Instalar" sigue el camino de cada uno.
  function preguntar(alInstalar) {
    var x = t();
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.pregunta + '</h2><p>' + x.sub + '</p><div class="lc-b"><button class="no">' + x.no + '</button><button class="si">' + x.si + '</button></div><small>' + x.legal + '</small>');
    d.querySelector('.no').onclick = function () { ahoraNo(d); };
    d.querySelector('.si').onclick = function () { d.close(); alInstalar(); };
  }

  // Computadoras (Chrome, Edge): el aviso del navegador.
  function instalarNavegador() {
    if (navigator.install) { navigator.install().catch(function () {}); return; }
    if (aviso) { aviso.prompt(); aviso.userChoice.then(function (r) { aviso = null; if (r.outcome === 'accepted') ocultar(); }); }
  }

  // Android: baja LuniCitas.apk y explica cómo terminar.
  function instalarAndroid() {
    var x = t();
    var a = document.createElement('a'); a.href = APK; a.download = 'LuniCitas.apk'; document.body.appendChild(a); a.click(); a.remove();
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.andT + '</h2>' + lista(x.and) + '<p>' + x.andNota + '</p>' +
      '<div class="lc-b"><button class="si">' + x.abrirApp + '</button></div>' + (aviso || navigator.install ? '<button class="lc-l">' + x.sinDescargar + '</button>' : ''));
    d.querySelector('.si').onclick = function () { d.close(); location.href = urlApp(); };
    var l = d.querySelector('.lc-l'); if (l) l.onclick = function () { d.close(); instalarNavegador(); };
  }

  // iPhone con Safari: baja el perfil de Apple y explica cómo terminar en Ajustes.
  function instalarIOS() {
    var x = t(), q = destino().perfil;
    location.href = 'perfil-ios.php' + (q ? '?' + q : '');
    setTimeout(function () {
      var d = dialogo(icono + '<h2 id="lc-d-t">' + x.iosT + '</h2>' + lista(x.ios) + '<p>' + x.iosNota + '</p><div class="lc-b"><button class="si">' + x.listo + '</button></div><button class="lc-l">' + x.otraForma + '</button>');
      d.querySelector('.si').onclick = function () { d.close(); };
      d.querySelector('.lc-l').onclick = function () { d.close(); guiaCompartir(); };
    }, 600);
  }

  // iPhone: añadir desde el botón Compartir (la otra forma, o la única fuera de Safari).
  function guiaCompartir() {
    var x = t(), pasos = x.share[iosNav === 'safari' ? 'safari' : iosNav === 'chrome' ? 'chrome' : 'otro'];
    var f = document.createElement('div'); f.className = 'lc-flecha ' + (iosNav === 'chrome' ? 'arriba' : 'abajo'); f.textContent = iosNav === 'chrome' ? '⬆' : '⬇';
    document.body.appendChild(f);
    var d = dialogo(icono + '<h2 id="lc-d-t">' + x.shareT + '</h2>' + lista(pasos) + '<div class="lc-b"><button class="si">' + x.ok + '</button></div>',
      function () { setTimeout(function () { f.remove(); }, 6000); });
    d.querySelector('.si').onclick = function () { d.close(); };
  }

  function copiarEnlace(titulo, texto, extra) {
    var x = t();
    var d = dialogo(icono + '<h2 id="lc-d-t">' + titulo + '</h2><p>' + texto + '</p><div class="lc-b"><button class="no">' + x.no + '</button><button class="si">' + x.copiar + '</button></div>' + (extra ? '<button class="lc-l">' + x.otraForma + '</button>' : ''));
    d.querySelector('.no').onclick = function () { d.close(); };
    d.querySelector('.si').onclick = function (ev) {
      var b = ev.target; (navigator.clipboard ? navigator.clipboard.writeText(location.href) : Promise.reject()).then(function () { b.textContent = x.copiado; }, function () { b.textContent = location.href; });
    };
    var l = d.querySelector('.lc-l'); if (l) l.onclick = function () { d.close(); guiaCompartir(); };
  }

  function ofrecer() {
    var x = t();
    if (dentroDeApp) copiarEnlace(x.appT, x.app);
    else if (esAndroid) preguntar(instalarAndroid);
    else if (esIOS && iosNav === 'safari') preguntar(instalarIOS);
    else if (esIOS) copiarEnlace(x.safariT, x.safari, true);
    else if (aviso || navigator.install) preguntar(instalarNavegador);
  }
  window.lcInstalar = ofrecer;

  // Pregunta automática: una vez por visita, unos segundos después de abrir, si no dijeron "Ahora no" en los últimos 14 días.
  function puedePreguntarSolo() {
    try { var n = +localStorage.getItem('lc-inst-no') || 0; if (Date.now() - n < 14 * 864e5) return false; if (sessionStorage.getItem('lc-inst-visto')) return false; sessionStorage.setItem('lc-inst-visto', '1'); } catch (e) {}
    return !document.querySelector('dialog[open]');
  }
  var movil = (esAndroid || (esIOS && iosNav === 'safari')) && !dentroDeApp;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); aviso = e; mostrar();
    if (!movil) setTimeout(function () { if (aviso && puedePreguntarSolo()) ofrecer(); }, 4000);
  });
  window.addEventListener('appinstalled', ocultar);
  document.addEventListener('DOMContentLoaded', function () {
    if (esIOS || esAndroid || dentroDeApp || navigator.install) mostrar();
    if (movil) setTimeout(function () { if (puedePreguntarSolo()) ofrecer(); }, 4000);
  });
  document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-instalar]'); if (!b) return; e.preventDefault(); ofrecer(); });
})();
