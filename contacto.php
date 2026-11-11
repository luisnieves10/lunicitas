<?php
// LuniCitas — formulario de contacto público. Los mensajes llegan a la bandeja de Soporte del administrador. Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
lc_sesion();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$TIPOS = ['ayuda' => 'Necesito ayuda', 'sugerencia' => 'Quiero sugerir una mejora', 'privacidad' => 'Mis datos o privacidad', 'pagos' => 'Pagos o cancelación', 'otro' => 'Otro tema'];
$f = ['nombre' => '', 'email' => '', 'tipo' => $_GET['tema'] ?? 'ayuda', 'texto' => ''];
$error = ''; $enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach (['nombre' => 80, 'email' => 120, 'tipo' => 12, 'texto' => 3000] as $k => $max) $f[$k] = mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);
  $f['email'] = mb_strtolower($f['email']);
  $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas-contacto');
  $q = $db->prepare('SELECT COUNT(*) FROM intentos WHERE ip_hash = ? AND t > (NOW() - INTERVAL 1 HOUR)'); $q->execute([$ip]);
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $error = 'La página caducó. Inténtalo otra vez.';
  elseif (($_POST['web'] ?? '') !== '') $error = 'No se pudo enviar.'; // campo trampa para robots
  elseif ($q->fetchColumn() >= 5) $error = 'Ya enviaste varios mensajes. Espera una hora, por favor.';
  elseif (mb_strlen($f['nombre']) < 2) $error = 'Escribe tu nombre.';
  elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = 'Escribe un correo válido para poder responderte.';
  elseif (mb_strlen($f['texto']) < 10) $error = 'Cuéntanos un poco más (al menos 10 letras).';
  else {
    $tipo = isset($TIPOS[$f['tipo']]) ? $f['tipo'] : 'otro';
    $db->prepare('INSERT INTO intentos (ip_hash) VALUES (?)')->execute([$ip]);
    $db->prepare("INSERT INTO mensajes (negocio_id, de, tipo, nombre, email, texto) VALUES (NULL, 'visitante', ?, ?, ?, ?)")
       ->execute([$tipo === 'sugerencia' ? 'sugerencia' : 'contacto', $f['nombre'], $f['email'], '[' . $TIPOS[$tipo] . '] ' . $f['texto']]);
    lc_correo(lc_correo_soporte(), 'LuniCitas: nuevo mensaje de contacto', "De: {$f['nombre']} <{$f['email']}>\nTema: {$TIPOS[$tipo]}\n\n{$f['texto']}\n\nRespóndelo desde el portal → Soporte.");
    $enviado = true;
  }
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contacto – LuniCitas</title><link rel="icon" href="logo.svg?v=3"><meta name="theme-color" content="#7b5cff">
<style>
@font-face{font-family:Poppins;font-weight:400;src:url(assets/fonts/poppins-latin-400-normal.woff2) format('woff2')}@font-face{font-family:Poppins;font-weight:600;src:url(assets/fonts/poppins-latin-600-normal.woff2) format('woff2')}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,system-ui,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;padding:18px}
.app{width:100%;max-width:520px}.volver{display:inline-block;font-size:14px;color:#5b45d6;text-decoration:none;font-weight:600;margin-bottom:10px}.top img{height:36px;margin-bottom:16px}
.box{background:#fff;border-radius:18px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06)}h1{font-size:22px;margin-bottom:8px}p{font-size:14px;color:#5e5c73;margin-bottom:14px;line-height:1.6}
label{font-size:13px;color:#5e5c73;display:block;margin-bottom:3px}input,select,textarea{width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;font:inherit;font-size:15px;margin-bottom:12px;background:#fff}
textarea{min-height:130px;resize:vertical}input:focus,select:focus,textarea:focus{border-color:#7b5cff;outline:none}.trampa{position:absolute;left:-9999px}
.btn{width:100%;padding:13px;border:0;border-radius:12px;background:#7b5cff;color:#fff;font:600 16px Poppins,system-ui,sans-serif;cursor:pointer}.err{background:#fde8e8;color:#b42318;border-radius:10px;padding:10px;font-size:14px;margin-bottom:12px}
.ok{background:#e7f9ef;color:#0f6b3a;border-radius:10px;padding:12px;font-size:14px;line-height:1.6}.tip{background:#efeaff;border-radius:10px;padding:10px 12px;font-size:13px;color:#3a2a99;margin-bottom:14px}.tip a{color:#5b45d6}
.pie{text-align:center;font-size:13px;margin-top:16px;color:#5e5c73}.pie a{color:#5b45d6}
</style></head><body><div class="app">
<a class="volver" href="./">← Volver al inicio</a>
<div class="top"><a href="./"><img src="logo.svg?v=3" alt="lunicitas, ir al inicio"></a></div>
<div class="box">
<?php if ($enviado): ?>
  <h1>¡Mensaje recibido!</h1><p class="ok">Gracias, <?= e($f['nombre']) ?>. Te responderemos a <?= e($f['email']) ?> lo antes posible.</p>
<?php else: ?>
  <h1>Contáctanos</h1><p>¿Tienes una duda, un problema o una idea para mejorar LuniCitas? Escríbenos y te responderemos por correo.</p>
  <p class="tip">¿Ya tienes cuenta? Entra a tu portal y usa el chat de <b>Ayuda</b>: te respondemos ahí mismo. <a href="entrar">Entrar</a></p>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <div class="trampa" aria-hidden="true"><input name="web" tabindex="-1" autocomplete="off"></div>
    <label>Tu nombre</label><input name="nombre" maxlength="80" value="<?= e($f['nombre']) ?>" autocomplete="name" required>
    <label>Tu correo</label><input name="email" type="email" maxlength="120" value="<?= e($f['email']) ?>" autocomplete="email" required>
    <label>Tema</label><select name="tipo"><?php foreach ($TIPOS as $k => $t): ?><option value="<?= $k ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select>
    <label>Mensaje</label><textarea name="texto" maxlength="3000" required><?= e($f['texto']) ?></textarea>
    <button class="btn">Enviar mensaje</button></form>
<?php endif; ?>
</div>
<p class="pie"><a href="terminos">Términos</a> · <a href="privacidad">Privacidad</a></p>
</div></body></html>
