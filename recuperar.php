<?php
// LuniCitas — recuperar la contraseña del portal con un enlace de un solo uso enviado por correo. Licencia AGPL-3.0
// Seguridad: el enlace vale 1 hora y una sola vez; solo se guarda su huella (SHA-256); la respuesta es la misma
// exista o no la cuenta (nadie puede averiguar qué correos están registrados); límite de intentos por conexión.
require __DIR__ . '/lib.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
lc_sesion();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas-recuperar');
$msg = ''; $error = ''; $paso = 'pedir';
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');

if ($token !== '') {
  $paso = 'nueva';
  $q = $db->prepare('SELECT r.id, r.negocio_id, n.usuario FROM recuperar r JOIN negocios n ON n.id = r.negocio_id
                     WHERE r.hash = ? AND r.usado = 0 AND r.expira > NOW() AND n.activo = 1');
  $q->execute([hash('sha256', $token)]); $fila = $q->fetch();
  if (!$fila) { $paso = 'pedir'; $error = 'Este enlace ya se usó o caducó. Pide uno nuevo.'; }
  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $c1 = (string)($_POST['clave'] ?? ''); $c2 = (string)($_POST['clave2'] ?? '');
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'La página caducó. Inténtalo otra vez.';
    elseif (strlen($c1) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif ($c1 !== $c2) $error = 'Las dos contraseñas no coinciden.';
    else {
      $db->beginTransaction();
      $db->prepare('UPDATE negocios SET clave_hash = ? WHERE id = ?')->execute([password_hash($c1, PASSWORD_DEFAULT), $fila['negocio_id']]);
      $db->prepare('UPDATE recuperar SET usado = 1 WHERE negocio_id = ?')->execute([$fila['negocio_id']]); // invalida todos sus enlaces
      $db->commit();
      session_regenerate_id(true);
      $_SESSION['aviso'] = 'Contraseña cambiada. Ya puedes entrar con tu usuario ' . $fila['usuario'] . ' y tu nueva contraseña.';
      header('Location: admin.php'); exit;
    }
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $dato = mb_strtolower(trim((string)($_POST['dato'] ?? '')));
  $q = $db->prepare('SELECT COUNT(*) FROM intentos WHERE ip_hash = ? AND t > (NOW() - INTERVAL 1 HOUR)'); $q->execute([$ip]);
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'La página caducó. Inténtalo otra vez.';
  elseif ($q->fetchColumn() >= 5) $error = 'Demasiados intentos. Espera una hora.';
  elseif ($dato === '') $error = 'Escribe tu correo o tu usuario.';
  else {
    $db->prepare('INSERT INTO intentos (ip_hash) VALUES (?)')->execute([$ip]);
    $q = $db->prepare('SELECT id, nombre, usuario, email FROM negocios WHERE activo = 1 AND (LOWER(usuario) = ? OR LOWER(email) = ?)');
    $q->execute([$dato, $dato]);
    foreach ($q->fetchAll() as $n) {
      if (!filter_var($n['email'], FILTER_VALIDATE_EMAIL)) continue;
      $t = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
      $db->prepare('INSERT INTO recuperar (negocio_id, hash, expira) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$n['id'], hash('sha256', $t)]);
      lc_correo($n['email'], 'Cambia tu contraseña de LuniCitas',
        "Hola,\n\nAlguien pidió cambiar la contraseña del portal de {$n['nombre']} (usuario: {$n['usuario']}).\n\n"
        . "Para elegir una nueva, abre este enlace (vale 1 hora y una sola vez):\n" . lc_url_publica($db) . "/recuperar.php?t=$t\n\n"
        . "Si no fuiste tú, ignora este correo: tu contraseña no cambia.\n\nLuniCitas");
    }
    $paso = 'enviado';
  }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Recuperar contraseña – LuniCitas</title><link rel="icon" href="logo.svg?v=3"><meta name="theme-color" content="#7b5cff">
<style>
@font-face{font-family:Poppins;font-weight:400;src:url(assets/fonts/poppins-latin-400-normal.woff2) format('woff2')}@font-face{font-family:Poppins;font-weight:600;src:url(assets/fonts/poppins-latin-600-normal.woff2) format('woff2')}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,system-ui,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;padding:18px}
.app{width:100%;max-width:420px}.volver{display:inline-block;font-size:14px;color:#5b45d6;text-decoration:none;font-weight:600;margin-bottom:10px}.top img{height:36px;margin-bottom:16px}
.box{background:#fff;border-radius:18px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06)}h1{font-size:22px;margin-bottom:8px}p{font-size:14px;color:#5e5c73;margin-bottom:14px;line-height:1.6}
label{font-size:13px;color:#5e5c73;display:block;margin-bottom:3px}input{width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;font:inherit;font-size:15px;margin-bottom:12px}input:focus{border-color:#7b5cff;outline:none}
.btn{width:100%;padding:13px;border:0;border-radius:12px;background:#7b5cff;color:#fff;font:600 16px Poppins,system-ui,sans-serif;cursor:pointer}.err{background:#fde8e8;color:#b42318;border-radius:10px;padding:10px;font-size:14px;margin-bottom:12px}
.ok{background:#e7f9ef;color:#0f6b3a;border-radius:10px;padding:12px;font-size:14px;line-height:1.6}.pie{text-align:center;font-size:13px;margin-top:16px}.pie a{color:#5b45d6}
</style></head><body><div class="app">
<a class="volver" href="./">← Volver al inicio</a>
<div class="top"><a href="./"><img src="logo.svg?v=3" alt="lunicitas, ir al inicio"></a></div>
<div class="box">
<?php if ($paso === 'enviado'): ?>
  <h1>Revisa tu correo</h1>
  <p class="ok">Si hay una cuenta con ese dato, te enviamos un enlace para cambiar tu contraseña. Vale 1 hora. Revisa también la carpeta de correo no deseado.</p>
<?php elseif ($paso === 'nueva'): ?>
  <h1>Elige tu nueva contraseña</h1><p>Para la cuenta <b><?= e($fila['usuario']) ?></b>.</p>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="t" value="<?= e($token) ?>">
    <label>Nueva contraseña (mínimo 8)</label><input name="clave" type="password" minlength="8" autocomplete="new-password" required>
    <label>Repítela</label><input name="clave2" type="password" minlength="8" autocomplete="new-password" required>
    <button class="btn">Guardar contraseña</button></form>
<?php else: ?>
  <h1>¿Olvidaste tu contraseña?</h1><p>Escribe tu correo o tu usuario y te enviaremos un enlace para elegir una nueva.</p>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <label>Correo o usuario</label><input name="dato" maxlength="120" autocomplete="username" required>
    <button class="btn">Enviarme el enlace</button></form>
<?php endif; ?>
</div>
<p class="pie"><a href="entrar">Volver a entrar</a> · <a href="contacto">¿Necesitas ayuda?</a></p>
</div></body></html>
