<?php
// LuniCitas — registro de negocios nuevos (prueba de LC_DIAS_PRUEBA días). Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
lc_sesion();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$abierto = lc_registro_abierto($db);
$f = array_map(function ($x) { return is_string($x) ? trim($x) : ''; }, $_POST) + ['tipo' => $_GET['tipo'] ?? 'barberia'];
$error = '';
$aviso = $_SESSION['aviso'] ?? ''; unset($_SESSION['aviso']);
// Viene de "Continuar con Google/Microsoft" sin cuenta: se crea el negocio conectado a esa cuenta, sin usuario ni contraseña.
if (isset($_GET['cancelar'])) unset($_SESSION['oauth_nuevo']);
$nuevo = $_SESSION['oauth_nuevo'] ?? null;
if ($nuevo && time() - $nuevo['t'] > 1800) { unset($_SESSION['oauth_nuevo']); $nuevo = null; }
$PROV = ['google' => 'Google', 'microsoft' => 'Microsoft'];
if ($nuevo && $_SERVER['REQUEST_METHOD'] !== 'POST') $f += ['persona' => $nuevo['nombre'], 'email' => $nuevo['email']];

if ($abierto && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $ipReg = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas-registro');
  $q = $db->prepare('SELECT COUNT(*) FROM intentos WHERE ip_hash = ? AND t > (NOW() - INTERVAL 1 HOUR)'); $q->execute([$ipReg]);
  $nombre = mb_substr(strip_tags($f['negocio'] ?? ''), 0, 80);
  $persona = mb_substr(strip_tags($f['persona'] ?? ''), 0, 40);
  $email = mb_strtolower(($nuevo && $nuevo['email']) ? $nuevo['email'] : ($f['email'] ?? ''));
  $usuario = $f['usuario'] ?? '';
  if ($nuevo) { // usuario interno a partir del correo; la contraseña no se usa (entra con su cuenta)
    $baseU = substr(preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: 'negocio')), 0, 32);
    if (strlen($baseU) < 3) $baseU .= '-lc';
    $usuario = $baseU; $k = 2; $chkU = $db->prepare('SELECT COUNT(*) FROM negocios WHERE usuario = ?');
    while (true) { $chkU->execute([$usuario]); if (!$chkU->fetchColumn() && $usuario !== lc_cfg('ADMIN_USER')) break; $usuario = $baseU . $k++; }
    $_POST['clave'] = bin2hex(random_bytes(24));
  }
  $tipo = isset(LC_TIPOS[$f['tipo']]) ? $f['tipo'] : 'barberia';
  $existe = $db->prepare('SELECT COUNT(*) FROM negocios WHERE usuario = ?'); $existe->execute([$usuario]);

  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'La página caducó. Inténtalo otra vez.';
  elseif (($f['web'] ?? '') !== '') $error = 'No se pudo completar el registro.'; // campo trampa para robots
  elseif ($q->fetchColumn() >= 3) $error = 'Demasiados registros desde esta conexión. Espera una hora.';
  elseif (mb_strlen($nombre) < 2) $error = 'Escribe el nombre de tu negocio.';
  elseif (mb_strlen($persona) < 2) $error = 'Escribe tu nombre.';
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Escribe un correo válido.';
  elseif (!preg_match('/^[A-Za-z0-9._@+-]{3,120}$/', $usuario)) $error = 'El usuario puede ser tu correo o un nombre de 3 letras o más, sin espacios.';
  elseif ($existe->fetchColumn() || $usuario === lc_cfg('ADMIN_USER')) $error = 'Ese usuario ya existe. Elige otro.';
  elseif (strlen($_POST['clave'] ?? '') < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
  elseif (empty($f['acepto'])) $error = 'Debes aceptar las condiciones para continuar.';
  else {
    // Enlace único a partir del nombre: mi-barberia, mi-barberia-2…
    $base = substr(lc_slug($nombre), 0, 34) ?: 'negocio';
    if (strlen($base) < 3) $base .= '-lc';
    $slug = $base; $n = 2;
    $chk = $db->prepare('SELECT COUNT(*) FROM negocios WHERE slug = ?');
    while (true) { $chk->execute([$slug]); if (!$chk->fetchColumn() && !in_array($slug, ['admin','api','install','index','uploads','lib','config','recordatorios','logo','assets','docs','registro','stripe','entrar','legal','terminos','privacidad','terms','privacy','inicio','manifest','sw','offline','oauth','calendario','contacto','recuperar','modulos'], true)) break; $slug = $base . '-' . $n++; }

    $id = lc_crear_negocio($db, $nombre, $tipo, $slug, $usuario, $_POST['clave'], $persona);
    $db->prepare("UPDATE negocios SET plan = 'prueba', prueba_hasta = ?, email = ?, terminos_aceptados = NOW() WHERE id = ?")
       ->execute([date('Y-m-d', strtotime('+' . LC_DIAS_PRUEBA . ' days')), $email, $id]);
    $db->prepare('INSERT INTO intentos (ip_hash) VALUES (?)')->execute([$ipReg]);
    if ($nuevo) {
      $db->prepare("UPDATE negocios SET clave_hash = '' WHERE id = ?")->execute([$id]);
      $db->prepare('INSERT INTO identidades (negocio_id, proveedor, sub, email) VALUES (?,?,?,?)')->execute([$id, $nuevo['p'], $nuevo['sub'], $nuevo['email']]);
      unset($_SESSION['oauth_nuevo']);
    }

    session_regenerate_id(true);
    $_SESSION['neg'] = $id; $_SESSION['super'] = false;
    $_SESSION['empezar_pago'] = true; // el portal lo lleva directo a poner la tarjeta, con sus días gratis
    // Correo de bienvenida con lo necesario para empezar (nunca incluye la contraseña).
    $web = lc_url_publica($db);
    lc_correo($email, '¡Bienvenido a LuniCitas, ' . $nombre . '!',
      "Hola $persona,\n\nTu cuenta de LuniCitas para $nombre está lista.\n\n"
      . "• Tu página de reservas (compártela con tus clientes): $web/$slug\n"
      . "• Tu portal: $web/entrar\n"
      . ($nuevo ? "• Para entrar: toca «Continuar con " . $PROV[$nuevo['p']] . "».\n" : "• Tu usuario: $usuario\n")
      . "• Prueba gratis hasta el " . date('j/n/Y', strtotime('+' . LC_DIAS_PRUEBA . ' days')) . ". Puedes cancelar cuando quieras en Plan.\n\n"
      . "¿Olvidaste tu contraseña? $web/recuperar.php\n¿Necesitas ayuda? Usa el chat de Ayuda en tu portal.\n\n"
      . "Términos: $web/terminos · Privacidad: $web/privacidad\n\n¡Éxitos con tu negocio!\nLuniCitas");
    $_SESSION['aviso'] = '¡Bienvenido a LuniCitas! Tienes ' . LC_DIAS_PRUEBA . ' días de prueba con todo incluido; después añade tu tarjeta en Plan para seguir. Revisa tu horario y servicios, y comparte tu enlace. Para volver a entrar usa ' . ($_SERVER['HTTP_HOST'] ?? '') . '/entrar' . ($nuevo ? ' y toca Continuar con ' . $PROV[$nuevo['p']] . '.' : '.');
    header('Location: admin.php?v=negocio'); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crea tu cuenta – LuniCitas</title><link rel="icon" href="logo.svg?v=3"><meta name="theme-color" content="#7b5cff"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/app/apple-touch-icon.png"><meta name="apple-mobile-web-app-title" content="LuniCitas"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="assets/app/instalar.js" defer></script>
<meta name="description" content="Reservas en línea para barberías, salones y centros de estética. Prueba gratis <?= LC_DIAS_PRUEBA ?> días.">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;padding:18px}
.app{width:100%;max-width:480px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.top img{height:38px}.top a{color:#7b5cff;font-size:14px;font-weight:600;text-decoration:none}
.box{background:#fff;border-radius:18px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:12px}
h1{font-size:24px;line-height:1.25;margin-bottom:6px}p.s{color:#6b6980;font-size:14px;margin-bottom:14px}
.ventajas{list-style:none;margin:0 0 6px;font-size:14px;color:#3a3850}.ventajas li{margin-bottom:6px}
label{font-size:13px;color:#6b6980;display:block;margin-bottom:3px}
input,select{width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;font-size:15px;margin-bottom:10px;font-family:inherit;background:#fff}
input:focus,select:focus{border-color:#7b5cff;outline:none}
.tipos{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}.tipos label{margin:0;cursor:pointer}
.tipos input{display:none}.tipos span{display:block;text-align:center;border:2px solid #e4e2ee;border-radius:14px;padding:10px 4px;font-size:13px;color:#1d1b2e}
.tipos span b{display:block;font-size:22px}.tipos input:checked+span{border-color:#7b5cff;background:#f1edff}
.ck{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#6b6980;margin:4px 0 6px}.ck input{width:auto;margin:3px 0 0}
.btn{display:block;width:100%;padding:15px;border:none;border-radius:16px;background:linear-gradient(90deg,#7b5cff,#1fd1c1);color:#fff;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;margin-top:8px;box-shadow:0 8px 20px rgba(123,92,255,.3)}
.err{background:#fdecec;color:#c0262c;border-radius:12px;padding:12px;margin-bottom:12px;font-size:14px}
.trampa{position:absolute;left:-9999px}.pie a{color:#5b45d6}
.pie{text-align:center;font-size:13px;color:#8a88a0}.pie a{color:#7b5cff}
<?= LC_OAUTH_CSS ?>.volver{display:inline-block;font-size:14px;color:#5b45d6;text-decoration:none;font-weight:600;margin-bottom:10px}.conectado{background:#e3faf7;border-radius:12px;padding:12px;font-size:14px;margin-bottom:12px}.conectado a{color:#5b45d6}
</style></head><body><div class="app">
<a class="volver" href="./">← Volver al inicio</a>
<div class="top"><a href="./"><img src="logo.svg?v=3" alt="lunicitas, ir al inicio"></a><a href="entrar">Ya tengo cuenta · Entrar</a></div>
<?php if (!$abierto): ?>
  <div class="box"><h1>Registro cerrado</h1><p class="s">Por ahora no se aceptan negocios nuevos en esta instalación.</p></div>
<?php else: ?>
  <div class="box">
    <h1>Tu agenda en línea, lista en 2 minutos</h1>
    <p class="s">Tus clientes reservan desde el celular, sin llamadas ni mensajes.</p>
    <ul class="ventajas">
      <li>✅ <b><?= LC_DIAS_PRUEBA ?> días gratis</b> con todo incluido. Pones tu tarjeta al empezar y <b>no se cobra nada hasta el día <?= LC_DIAS_PRUEBA ?></b></li>
      <li>✅ Tu propio enlace para Instagram y WhatsApp</li>
      <li>✅ Recordatorios por WhatsApp para que no falten</li>
      <li>✅ Después, <?= e(lc_cfg('PLAN_PRECIO', '$20 al mes')) ?>. Cancela antes de que termine la prueba en Plan y <b>no pagas nada</b></li>
    </ul>
  </div>
  <div class="box">
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($aviso): ?><div class="err" style="background:#efeaff;color:#3a2a99"><?= e($aviso) ?></div><?php endif; ?>
    <?php if ($nuevo): ?>
      <p class="conectado">✓ Conectado con <b><?= e($PROV[$nuevo['p']]) ?></b><?= $nuevo['email'] ? ' como ' . e($nuevo['email']) : '' ?>. Solo faltan los datos de tu negocio. <a href="registro.php?cancelar=1">Usar otra forma</a></p>
    <?php elseif ($b = lc_oauth_botones('registro', 'Crear cuenta con')): ?>
      <?= $b ?><div class="o-sep">o con tu correo</div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <div class="trampa" aria-hidden="true"><label>No llenes esto</label><input name="web" tabindex="-1" autocomplete="off"></div>
      <label>Tipo de negocio</label>
      <div class="tipos">
        <?php foreach (['barberia' => ['✂️', 'Barbería'], 'salon' => ['💇', 'Salón'], 'estetica' => ['✨', 'Estética']] as $k => [$ico, $n]): ?>
          <label><input type="radio" name="tipo" value="<?= $k ?>" <?= $f['tipo'] === $k ? 'checked' : '' ?>><span><b><?= $ico ?></b><?= $n ?></span></label>
        <?php endforeach; ?>
      </div>
      <label>Nombre del negocio</label><input name="negocio" maxlength="80" value="<?= e($f['negocio'] ?? '') ?>" placeholder="Barbería El Estilo" required>
      <label>Tu nombre</label><input name="persona" maxlength="40" value="<?= e($f['persona'] ?? '') ?>" placeholder="Carlos" autocomplete="given-name" required>
      <?php if (!$nuevo || !$nuevo['email']): ?><label>Correo</label><input name="email" type="email" maxlength="120" value="<?= e($f['email'] ?? '') ?>" autocomplete="email" required><?php endif; ?>
      <?php if (!$nuevo): ?>
      <label>Usuario para entrar (puede ser tu correo)</label><input name="usuario" maxlength="120" value="<?= e($f['usuario'] ?? '') ?>" autocomplete="username" pattern="[A-Za-z0-9._@+\-]{3,120}" title="Tu correo o un nombre sin espacios" required>
      <label>Contraseña (mínimo 8)</label><input name="clave" type="password" minlength="8" autocomplete="new-password" required>
      <?php endif; ?>
      <label class="ck"><input type="checkbox" name="acepto" value="1" <?= !empty($f['acepto']) ? 'checked' : '' ?> required> <span>Acepto los <a href="terminos" target="_blank">Términos y condiciones</a> y la <a href="privacidad" target="_blank">Política de privacidad</a>, y me comprometo a cuidar los datos de mis clientes.</span></label>
      <button class="btn">Crear mi cuenta gratis</button>
    </form>
  </div>
  <p class="pie">LuniCitas es <a href="https://github.com/luisnieves10/lunicitas">código abierto</a> · <a href="terminos">Términos</a> · <a href="privacidad">Privacidad</a></p>
<?php endif; ?>
</div></body></html>
