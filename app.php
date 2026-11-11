<?php
// LuniCitas — bienvenida de la app instalada (cuando todavía no hay un negocio o una agenda guardada). Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
if (!lc_registro_abierto($db) && ($n = lc_negocio($db, ''))) { header('Location: ' . $n['slug']); exit; } // un solo negocio: directo a reservar
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LuniCitas</title><meta name="robots" content="noindex"><meta name="theme-color" content="#7b5cff">
<link rel="icon" href="logo.svg?v=3"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/app/apple-touch-icon.png">
<style>
@font-face{font-family:Poppins;font-weight:400;font-display:swap;src:url(assets/fonts/poppins-latin-400-normal.woff2) format('woff2')}
@font-face{font-family:Poppins;font-weight:600;font-display:swap;src:url(assets/fonts/poppins-latin-600-normal.woff2) format('woff2')}
@font-face{font-family:Poppins;font-weight:800;font-display:swap;src:url(assets/fonts/poppins-latin-800-normal.woff2) format('woff2')}
*{box-sizing:border-box}body{margin:0;font-family:Poppins,system-ui,sans-serif;background:#f7f7fb;color:#1d1b2e;line-height:1.5}
main{max-width:440px;margin:0 auto;padding:28px 20px 40px}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:22px}.logo img{width:52px;height:52px;border-radius:14px}.logo b{font-size:24px;font-weight:800}
h1{font-size:22px;margin:0 0 4px}p{color:#5e5c73;margin:0 0 14px;font-size:15px}
section{background:#fff;border-radius:20px;padding:20px;margin-bottom:16px;box-shadow:0 1px 0 #e4e2ee}
h2{font-size:17px;margin:0 0 10px}
.neg{display:flex;align-items:center;gap:12px;padding:13px 14px;border:2px solid #e4e2ee;border-radius:14px;margin-bottom:8px;color:#1d1b2e;text-decoration:none;font-weight:600}
.neg:hover,.neg:focus-visible{border-color:#7b5cff;outline:none}.neg span{font-size:22px}
form{display:flex;gap:8px}input{flex:1;min-width:0;font:inherit;font-size:16px;padding:12px;border:2px solid #e4e2ee;border-radius:12px}input:focus{outline:none;border-color:#7b5cff}
.btn{display:block;text-align:center;background:#7b5cff;color:#fff;border:0;border-radius:14px;padding:14px;font:600 16px Poppins,system-ui,sans-serif;text-decoration:none;cursor:pointer}
form .btn{padding:12px 16px}.btn.gris{background:#efeaff;color:#5b45d6;margin-top:8px}
.err{color:#c0392b;font-size:14px;margin:8px 0 0}[hidden]{display:none}
</style></head>
<body><main>
  <div class="logo"><img src="assets/app/icono-192.png" alt=""><b>LuniCitas</b></div>

  <section id="cli">
    <h2>📅 Reservar una cita</h2>
    <div id="lista"></div>
    <p id="ayuda">Abre el enlace que te envió tu barbería, salón o centro de estética, o escribe aquí su nombre de enlace.</p>
    <form id="ir"><input id="slug" placeholder="ej. barberia-luis" autocapitalize="off" autocomplete="off" spellcheck="false" aria-label="Enlace de tu negocio"><button class="btn">Abrir</button></form>
    <p class="err" id="err" hidden>No encontramos ese negocio. Revisa el enlace que te enviaron.</p>
  </section>

  <section>
    <h2>✂️ ¿Tienes un negocio?</h2>
    <p>Mira tu agenda, tus clientes y tu caja.</p>
    <a class="btn" href="admin.php">Entrar a mi agenda</a>
    <a class="btn gris" href="registro.php">Crear mi cuenta</a>
  </section>
</main>
<script>
(function(){
  var esc=function(s){return String(s).replace(/[&<>"']/g,function(c){return'&#'+c.charCodeAt(0)+';'})};
  var r=[];try{r=JSON.parse(localStorage.getItem('lc-negocios')||'[]')}catch(e){}
  if(r.length){document.getElementById('lista').innerHTML=r.map(function(x){return'<a class="neg" href="'+encodeURIComponent(x.s)+'"><span>💈</span>'+esc(x.n)+'</a>'}).join('');document.getElementById('ayuda').textContent='¿Otro negocio? Escribe su nombre de enlace:'}
  document.getElementById('ir').onsubmit=async function(e){
    e.preventDefault();
    var v=document.getElementById('slug').value.trim().toLowerCase().replace(/^https?:\/\/[^\/]+\//,'').replace(/[?#].*$/,'').replace(/\/+$/,'');
    var err=document.getElementById('err');err.hidden=true;
    if(!/^[a-z0-9-]{2,40}$/.test(v)){err.hidden=false;return}
    try{var j=await (await fetch('api.php?n='+encodeURIComponent(v))).json();if(!j||!j.negocio)throw 0;location.href=v}catch(x){err.hidden=false}
  };
})();
</script>
</body></html>
