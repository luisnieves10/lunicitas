<?php
// LuniCitas — página principal. Licencia AGPL-3.0
// Con el registro abierto (LuniCitas como servicio) presenta la app y lleva al registro.
// Con el registro cerrado (un solo negocio) muestra directamente la página de reservas.
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
if (!lc_registro_abierto($db)) { header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__ . '/index.html'); exit; }

$precio = lc_cfg('PLAN_PRECIO', '$20 al mes');
$dias = LC_DIAS_PRUEBA;
$demo = lc_negocio($db, '');
$demoUrl = $demo ? $demo['slug'] : '';
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LuniCitas: agenda tus clientes en línea · barberías, salones y estética</title>
<meta name="description" content="Tu agenda en línea: tus clientes reservan solos desde el celular, a cualquier hora. Prueba <?= $dias ?> días gratis.">
<meta property="og:title" content="LuniCitas: tus clientes reservan solos">
<meta property="og:description" content="Agenda en línea para barberías, salones y centros de estética. Prueba <?= $dias ?> días gratis.">
<meta property="og:image" content="docs/reservas.png"><meta name="theme-color" content="#7b5cff"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/app/apple-touch-icon.png"><meta name="apple-mobile-web-app-title" content="LuniCitas"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="assets/app/instalar.js" defer></script>
<link rel="icon" href="logo.svg?v=3"><link rel="preload" href="assets/fonts/poppins-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:Poppins;font-weight:400;font-display:swap;src:url(assets/fonts/poppins-latin-400-normal.woff2) format('woff2')}
@font-face{font-family:Poppins;font-weight:600;font-display:swap;src:url(assets/fonts/poppins-latin-600-normal.woff2) format('woff2')}
@font-face{font-family:Poppins;font-weight:800;font-display:swap;src:url(assets/fonts/poppins-latin-800-normal.woff2) format('woff2')}
:root{--tinta:#1d1b2e;--gris:#5e5c73;--violeta:#7b5cff;--violeta-osc:#5b45d6;--menta:#1fd1c1;--papel:#f7f7fb;--lila:#efeaff;--linea:#e4e2ee}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:Poppins,'Segoe UI',system-ui,sans-serif;background:var(--papel);color:var(--tinta);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--violeta-osc)}
:focus-visible{outline:3px solid var(--violeta);outline-offset:3px;border-radius:6px}
.envoltura{max-width:1080px;margin:0 auto;padding:0 22px}

/* Barra superior: fija, con Suscribirme siempre a la vista */
.cabecera{position:sticky;top:0;z-index:10;background:rgba(247,247,251,.88);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid transparent}
.cabecera.baja{border-bottom-color:var(--linea)}
.barra{display:flex;justify-content:space-between;align-items:center;padding:14px 22px;gap:12px}
.barra img{height:34px;display:block}
.barra nav{display:flex;align-items:center;gap:8px}
.barra a.entrar{font-weight:600;font-size:15px;text-decoration:none;padding:8px 10px}
.barra a.suscribir{background:var(--violeta);color:#fff;font-weight:600;font-size:15px;text-decoration:none;padding:9px 18px;border-radius:12px}
.barra a.suscribir:hover{background:var(--violeta-osc)}
.icono{display:block;width:76px;height:76px;margin-bottom:22px}

/* Portada */
.portada{display:grid;grid-template-columns:1.05fr .95fr;gap:56px;align-items:center;padding:40px 0 72px}
h1{font-weight:800;letter-spacing:-.035em;line-height:1.02}
.h1a{display:block;font-size:clamp(2.8rem,7vw,4.8rem);background:linear-gradient(90deg,#7b5cff,#1fd1c1);-webkit-background-clip:text;background-clip:text;color:transparent;padding-bottom:.06em}
.h1b{display:block;font-size:clamp(1.5rem,3.2vw,2.2rem);color:var(--tinta);letter-spacing:-.02em;line-height:1.15;margin-top:10px;max-width:18ch}
.bajada{font-size:clamp(1.05rem,1.6vw,1.2rem);color:var(--gris);margin:22px 0 30px;max-width:36ch}
.acciones{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.boton{display:inline-block;background:var(--violeta);color:#fff;font-weight:600;font-size:17px;text-decoration:none;padding:15px 26px;border-radius:14px;box-shadow:0 8px 24px -10px rgba(123,92,255,.8)}
.boton:hover{background:var(--violeta-osc)}
.boton.claro{background:transparent;color:var(--tinta);box-shadow:inset 0 0 0 2px var(--linea)}
.boton.claro:hover{box-shadow:inset 0 0 0 2px var(--violeta);background:transparent}
.nota{font-size:14px;color:var(--gris);margin-top:14px}
.instalar{display:inline-flex;align-items:center;gap:10px;margin-top:22px;background:none;border:0;padding:0;font:600 15px Poppins,system-ui,sans-serif;color:var(--violeta-osc);cursor:pointer}
.instalar img{width:34px;height:34px;border-radius:9px}
.instalar[hidden]{display:none}

/* La agenda que se llena: el momento memorable */
.agenda{background:#fff;border-radius:28px;padding:22px 22px 92px;box-shadow:0 30px 60px -30px rgba(29,27,46,.35),0 0 0 1px var(--linea);position:relative;max-width:420px;justify-self:end;width:100%}
.agenda header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px}
.agenda header b{font-size:20px;letter-spacing:-.02em}
.agenda header span{font-size:14px;color:var(--gris)}
.fila{display:grid;grid-template-columns:62px 1fr;gap:12px;align-items:stretch;min-height:52px}
.fila time{font-size:13px;color:var(--gris);padding-top:6px;font-variant-numeric:tabular-nums}
.hueco{border-top:1px dashed var(--linea);position:relative}
.cita{position:absolute;inset:5px 0 5px 0;border-radius:12px;background:var(--lila);border-left:4px solid var(--violeta);padding:6px 12px;display:flex;flex-direction:column;justify-content:center;line-height:1.25}
.cita.menta{background:#e3faf7;border-left-color:var(--menta)}
.cita b{font-size:14px}.cita small{font-size:12.5px;color:var(--gris)}
.js .cita{opacity:0;transform:translateX(10px)}
.js .cita.entra{opacity:1;transform:none;transition:opacity .45s ease,transform .45s ease}
.aviso{position:absolute;left:-28px;bottom:16px;background:var(--tinta);color:#fff;border-radius:16px;padding:12px 16px;font-size:14px;line-height:1.35;box-shadow:0 18px 40px -16px rgba(29,27,46,.6);max-width:250px}
.aviso b{color:var(--menta)}
.js .aviso{opacity:0;transform:translateY(8px)}
.js .aviso.entra{opacity:1;transform:none;transition:opacity .5s ease,transform .5s ease}

/* Secciones */
section.bloque{padding:84px 0;border-top:1px solid var(--linea)}
h2{font-size:clamp(1.7rem,3.2vw,2.4rem);line-height:1.15;font-weight:800;letter-spacing:-.025em;max-width:22ch;margin-bottom:14px}
.intro{color:var(--gris);max-width:52ch;margin-bottom:40px}

/* Cómo funciona: es una secuencia, por eso va numerada */
.pasos{list-style:none;display:grid;grid-template-columns:repeat(3,1fr);gap:40px;counter-reset:paso}
.pasos li{counter-increment:paso}
.pasos li::before{content:counter(paso);display:grid;place-items:center;width:44px;height:44px;border-radius:50%;background:var(--tinta);color:#fff;font-weight:800;margin-bottom:16px}
.pasos h3{font-size:19px;margin-bottom:6px;letter-spacing:-.01em}
.pasos p{color:var(--gris);font-size:16px}

/* Ventajas: lista sencilla en dos columnas */
.ventajas{display:grid;grid-template-columns:1fr 1fr;gap:28px 56px;list-style:none}
.ventajas li{padding-left:36px;position:relative}
.ventajas li::before{content:"";position:absolute;left:0;top:5px;width:20px;height:20px;border-radius:6px;background:var(--menta) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10.5l3 3 7-7' fill='none' stroke='%231d1b2e' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/16px no-repeat}
.ventajas b{display:block;font-size:17px}
.ventajas span{color:var(--gris);font-size:15.5px}
.para{margin-top:56px;font-size:clamp(1.2rem,2.4vw,1.6rem);font-weight:600;letter-spacing:-.015em;max-width:30ch}

/* Precio */
.precio{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.tarifa{background:var(--tinta);color:#fff;border-radius:28px;padding:36px}
.tarifa .monto{font-size:clamp(2.6rem,5vw,3.4rem);font-weight:800;letter-spacing:-.03em;line-height:1}
.tarifa ul{list-style:none;margin:22px 0 28px;color:#d9d7e6;font-size:16px}
.tarifa li{padding:7px 0;border-top:1px solid rgba(255,255,255,.12)}
.tarifa .boton{width:100%;text-align:center}
.claves p{margin-bottom:14px;color:var(--gris)}
.claves p b{color:var(--tinta)}

/* Preguntas */
.preguntas{max-width:760px}
details{border-top:1px solid var(--linea);padding:18px 0}
details:last-child{border-bottom:1px solid var(--linea)}
summary{font-weight:600;cursor:pointer;list-style:none;display:flex;justify-content:space-between;gap:16px}
summary::-webkit-details-marker{display:none}
summary::after{content:"+";font-size:24px;line-height:1;color:var(--violeta)}
details[open] summary::after{content:"–"}
details p{color:var(--gris);margin-top:10px;max-width:62ch}

/* Cierre */
.cierre{text-align:center;padding:96px 0}
.cierre h2{margin:0 auto 14px}
.cierre .intro{margin:0 auto 30px}
footer{border-top:1px solid var(--linea);padding:28px 0 40px;font-size:14px;color:var(--gris)}
footer .envoltura{display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px}
footer nav a{margin-left:18px}

@media (max-width:420px){
  .barra a.entrar{padding:8px 6px}
  .barra a.suscribir{padding:9px 14px}
  .barra img{height:30px}
}
@media (max-width:860px){
  .portada{grid-template-columns:1fr;gap:56px;padding:20px 0 64px}
  .agenda{justify-self:start;max-width:none;padding-bottom:112px}
  .aviso{left:14px;right:14px;max-width:none}
  .pasos,.ventajas,.precio{grid-template-columns:1fr}
  .pasos{gap:32px}
  section.bloque{padding:64px 0}
  footer nav a{margin:0 18px 0 0}
}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  .js .cita,.js .aviso{opacity:1;transform:none;transition:none}
}
</style>
<script>document.documentElement.classList.add('js')</script>
</head><body>

<header class="cabecera" id="cabecera"><div class="envoltura barra">
  <a href="./" aria-label="LuniCitas, inicio"><img src="logo.svg?v=3" alt="lunicitas"></a>
  <nav aria-label="Cuenta"><a class="entrar" href="entrar">Entrar</a><a class="suscribir" href="registro">Suscribirme</a></nav>
</div></header>

<div class="envoltura">

  <main>
  <div class="portada">
    <div>
      <svg class="icono" viewBox="0 0 76 76" role="img" aria-label="Calendario con una cita nueva">
        <rect x="6" y="12" width="56" height="54" rx="12" fill="#fff" stroke="#1d1b2e" stroke-width="3"/>
        <path d="M6 24a12 12 0 0 1 12-12h32a12 12 0 0 1 12 12v4H6z" fill="#7b5cff" stroke="#1d1b2e" stroke-width="3" stroke-linejoin="round"/>
        <rect x="19" y="5" width="5" height="13" rx="2.5" fill="#1d1b2e"/><rect x="44" y="5" width="5" height="13" rx="2.5" fill="#1d1b2e"/>
        <rect x="15" y="36" width="9" height="8" rx="2.5" fill="#e4e2ee"/><rect x="29" y="36" width="9" height="8" rx="2.5" fill="#efeaff" stroke="#7b5cff" stroke-width="2"/><rect x="43" y="36" width="9" height="8" rx="2.5" fill="#e4e2ee"/>
        <rect x="15" y="49" width="9" height="8" rx="2.5" fill="#e4e2ee"/><rect x="29" y="49" width="9" height="8" rx="2.5" fill="#e4e2ee"/>
        <circle cx="56" cy="56" r="16" fill="#1fd1c1" stroke="#1d1b2e" stroke-width="3"/>
        <path d="M56 48v16M48 56h16" stroke="#1d1b2e" stroke-width="3.4" stroke-linecap="round"/>
      </svg>
      <h1><span class="h1a">Agenda tus clientes</span><span class="h1b">Ellos reservan solos, a cualquier hora.</span></h1>
      <p class="bajada">LuniCitas es la agenda en línea para barberías, salones de belleza y centros de estética. Compartes tu enlace, tus clientes eligen servicio, persona y hora, y la cita aparece en tu agenda. Sin llamadas, sin mensajes.</p>
      <div class="acciones">
        <a class="boton" href="registro">Crear mi cuenta gratis</a>
        <?php if ($demoUrl): ?><a class="boton claro" href="<?= e($demoUrl) ?>">Ver un ejemplo</a><?php endif; ?>
      </div>
      <p class="nota"><?= $dias ?> días gratis: no se cobra nada hasta el día <?= $dias ?>. Lista en 2 minutos.</p>
      <button type="button" class="instalar" data-instalar hidden><img src="assets/app/icono-192.png" alt="">Instalar la app en mi celular</button>
    </div>

    <div class="agenda" aria-label="Ejemplo de agenda del día que se llena con reservas">
      <header><b>Hoy</b><span>Barbería El Estilo</span></header>
      <div class="fila"><time>9:00 am</time><div class="hueco"><div class="cita"><b>Carlos</b><small>Corte clásico con Luis</small></div></div></div>
      <div class="fila"><time>10:00 am</time><div class="hueco"><div class="cita menta"><b>Javier</b><small>Corte y barba con Luis</small></div></div></div>
      <div class="fila"><time>11:00 am</time><div class="hueco"></div></div>
      <div class="fila"><time>12:00 pm</time><div class="hueco"><div class="cita"><b>Andrés</b><small>Diseño de barba con Pedro</small></div></div></div>
      <div class="fila"><time>1:00 pm</time><div class="hueco"><div class="cita menta"><b>Miguel</b><small>Corte para niño con Pedro</small></div></div></div>
      <div class="fila"><time>2:30 pm</time><div class="hueco"><div class="cita"><b>Ana</b><small>Corte y lavado con Luis</small></div></div></div>
      <p class="aviso" role="status"><b>Nueva cita</b><br>Ana reservó Corte y lavado para hoy a las 2:30 pm</p>
    </div>
  </div>

  <section class="bloque" id="como">
    <h2>Así funciona</h2>
    <p class="intro">No tienes que instalar nada, ni tú ni tus clientes. Todo funciona desde el navegador del celular.</p>
    <ol class="pasos">
      <li><h3>Crea tu cuenta</h3><p>Escribe el nombre de tu negocio, tus servicios con su precio y duración, tu horario y tu equipo.</p></li>
      <li><h3>Comparte tu enlace</h3><p>Recibes tu propia dirección, como lunicitas.com/tu-barberia. Ponla en Instagram, en WhatsApp o en Google.</p></li>
      <li><h3>Recibe las citas</h3><p>Tus clientes reservan en menos de un minuto y tú las ves en tu agenda del día, la semana y el mes.</p></li>
    </ol>
  </section>

  <section class="bloque" id="ventajas">
    <h2>Menos tiempo en el teléfono, más tiempo con tus clientes</h2>
    <p class="intro">Todo lo que necesitas para llenar tu agenda sin contestar mensajes a las 11 de la noche.</p>
    <ul class="ventajas">
      <li><b>Solo horas libres de verdad</b><span>La app tiene en cuenta cuánto dura cada servicio, tu horario, el almuerzo y los días libres. Nunca te reservan dos citas a la misma hora.</span></li>
      <li><b>Sin cuentas ni contraseñas para tus clientes</b><span>Eligen servicio, persona, día y hora, y ponen su nombre y teléfono. Listo.</span></li>
      <li><b>Recordatorio por WhatsApp</b><span>Un toque y le llega a tu cliente un mensaje ya escrito con su cita, para que no se le olvide.</span></li>
      <li><b>Todo tu equipo</b><span>Cada persona con su foto, su especialidad y sus días libres. O deja que el cliente elija “cualquiera”.</span></li>
      <li><b>Cuentas claras cada mes</b><span>Ingresos, servicios más pedidos, asistencia y días más ocupados, con descarga a Excel y PDF.</span></li>
      <li><b>App para iPhone y Android</b><span>Instálala desde el navegador con un toque y abre tu agenda desde la pantalla de inicio, sin pasar por tiendas.</span></li>
      <li><b>En español, inglés y portugués</b><span>Tus clientes ven la página en su idioma, con horario de 12 o 24 horas.</span></li>
    </ul>
    <p class="para">Hecho para barberías, salones de belleza y centros de estética.</p>
  </section>

  <section class="bloque" id="precio">
    <div class="precio">
      <div>
        <h2>Un solo plan, todo incluido</h2>
        <div class="claves">
          <p><b>Pruébalo <?= $dias ?> días gratis</b>, con todas las funciones. Al crear tu cuenta pones tu tarjeta en Stripe y no se cobra nada hasta el día <?= $dias ?>.</p>
          <p><b>Cancela antes del día <?= $dias ?> y no pagas nada.</b> Si prefieres no poner la tarjeta todavía, puedes usar tus días gratis igual; al terminar, la cuenta se cierra hasta que la pongas.</p>
          <p><b>Cancela cuando quieras</b> desde tu portal, sin llamadas ni penalidades.</p>
        </div>
      </div>
      <div class="tarifa">
        <div class="monto"><?= e($precio) ?></div>
        <ul>
          <li>Página de reservas con tu enlace</li>
          <li>Agenda del día, la semana y el mes</li>
          <li>Equipo sin límite</li>
          <li>Recordatorios por WhatsApp</li>
          <li>Análisis del mes, Excel y PDF</li>
        </ul>
        <a class="boton" href="registro">Empezar mi prueba gratis</a>
      </div>
    </div>
  </section>

  <section class="bloque" id="preguntas">
    <h2>Preguntas frecuentes</h2>
    <div class="preguntas">
      <details><summary>¿Mis clientes tienen que descargar una app?</summary><p>No. Reservan desde el navegador de su celular o computadora, con tu enlace. No necesitan crear cuenta.</p></details>
      <details><summary>¿Qué pasa cuando termina la prueba de <?= $dias ?> días?</summary><p>Si pusiste tu tarjeta, empieza tu plan y se cobra el primer mes. Si cancelas antes en Plan, no se te cobra nada. Si no pusiste tarjeta, tu cuenta se cierra: tu página deja de recibir reservas hasta que la pongas, y tus datos siguen ahí por un tiempo (lee la Política de privacidad).</p></details>
      <details><summary>¿Cómo cancelo?</summary><p>En tu portal, entra a Plan y toca Cancelar suscripción. No se te vuelve a cobrar y conservas todo hasta el final del mes que ya pagaste.</p></details>
      <details><summary>¿Es seguro pagar?</summary><p>Los pagos los procesa Stripe, una de las plataformas de pago más usadas del mundo. LuniCitas nunca ve ni guarda los datos de tu tarjeta.</p></details>
      <details><summary>¿Puedo usarla con todo mi equipo?</summary><p>Sí. Añade a todas las personas que quieras, cada una con su foto, especialidad y días libres.</p></details>
    </div>
  </section>

  <section class="cierre">
    <h2>Tu agenda en línea, lista hoy</h2>
    <p class="intro">Crea tu cuenta en 2 minutos y comparte tu enlace esta misma tarde.</p>
    <a class="boton" href="registro">Crear mi cuenta gratis</a>
  </section>
  </main>
</div>

<footer><div class="envoltura"><span>LuniCitas es de <a href="https://github.com/luisnieves10/lunicitas">código abierto</a>.</span>
  <nav aria-label="Legal"><a href="terminos">Términos y condiciones</a><a href="privacidad">Política de privacidad</a></nav></div></footer>

<script>
(function () { var c = document.getElementById('cabecera'); function f() { c.classList.toggle('baja', window.scrollY > 8); } f(); window.addEventListener('scroll', f, { passive: true }); })();
// Un solo momento animado: las citas entran una a una en la agenda y llega el aviso de la nueva cita.
(function () {
  var citas = document.querySelectorAll('.agenda .cita'), aviso = document.querySelector('.agenda .aviso');
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { citas.forEach(function (c) { c.classList.add('entra'); }); aviso.classList.add('entra'); return; }
  citas.forEach(function (c, i) { setTimeout(function () { c.classList.add('entra'); }, 350 + i * 420); });
  setTimeout(function () { aviso.classList.add('entra'); }, 350 + citas.length * 420 + 250);
})();
</script>
</body></html>
