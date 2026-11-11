<?php
date_default_timezone_set('America/Puerto_Rico');
session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict']);
session_start();
require __DIR__ . '/../config.php';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec('CREATE TABLE IF NOT EXISTS intentos (ip_hash CHAR(64) NOT NULL, t TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function h12($t) { [$h, $m] = array_map('intval', explode(':', $t)); return (($h % 12) ?: 12) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' a. m.' : ' p. m.'); }
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

if (isset($_GET['salir'])) { session_destroy(); header('Location: admin.php'); exit; }

$error = '';
if (empty($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $q = $db->prepare('SELECT COUNT(*) FROM intentos WHERE ip_hash = ? AND t > (NOW() - INTERVAL 15 MINUTE)');
  $q->execute([$ip]);
  if ($q->fetchColumn() >= 5) {
    $error = 'Demasiados intentos. Espera 15 minutos.';
  } elseif (hash_equals($ADMIN_USER, (string)($_POST['u'] ?? '')) && hash_equals($ADMIN_PASS, (string)($_POST['p'] ?? ''))) {
    session_regenerate_id(true); $_SESSION['admin'] = true; header('Location: admin.php'); exit;
  } else {
    $db->prepare('INSERT INTO intentos (ip_hash) VALUES (?)')->execute([$ip]);
    $error = 'Usuario o contraseña incorrectos';
  }
}

if (!empty($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
  if (hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    $db->prepare('DELETE FROM citas WHERE id = ?')->execute([(int)$_POST['cancelar']]);
  }
  header('Location: admin.php?fecha=' . urlencode($_POST['fecha'] ?? '')); exit;
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) { $fecha = date('Y-m-d'); $d = new DateTime($fecha); }
$ant = (clone $d)->modify('-1 day')->format('Y-m-d');
$sig = (clone $d)->modify('+1 day')->format('Y-m-d');
$dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$titulo = ($fecha === date('Y-m-d') ? 'Hoy, ' : '') . $dias[$d->format('w')] . ' ' . $d->format('j') . ' de ' . $meses[$d->format('n') - 1];
$PRECIOS = ['Corte clásico' => 12, 'Corte + barba' => 18, 'Fade / degradado' => 15, 'Arreglo de barba' => 8];

$citas = [];
if (!empty($_SESSION['admin'])) {
  $q = $db->prepare("SELECT * FROM citas WHERE fecha = ? ORDER BY STR_TO_DATE(hora, '%H:%i')");
  $q->execute([$fecha]);
  $citas = $q->fetchAll(PDO::FETCH_ASSOC);
}
$total = 0; foreach ($citas as $c) $total += $PRECIOS[$c['servicio']] ?? 0;
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>LuniCitas – Panel</title><link rel="icon" href="logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--m:#7b5cff;--g:linear-gradient(90deg,#7b5cff,#1fd1c1)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;min-height:100vh}
.app{width:100%;max-width:520px;padding:18px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.top img{height:36px}.top a{color:#8a88a0;font-size:14px;text-decoration:none}
.box{background:#fff;border-radius:20px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:12px}
input{width:100%;padding:14px;border-radius:14px;border:2px solid #e4e2ee;font-size:16px;margin-bottom:10px;font-family:inherit}
.btn{width:100%;padding:15px;border:none;border-radius:16px;background:var(--g);color:#fff;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit}
.err{color:#e5484d;margin-bottom:10px;font-size:14px}
.nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.nav a{background:#fff;border-radius:12px;padding:8px 14px;text-decoration:none;color:var(--m);font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.06)}.nav h2{font-size:17px;text-align:center}
.stats{display:flex;gap:10px;margin-bottom:12px}.stat{flex:1;background:var(--g);color:#fff;border-radius:18px;padding:14px}.stat b{font-size:24px;display:block}.stat small{opacity:.85}
.cita{display:flex;gap:12px;align-items:center}.hora{min-width:86px;font-weight:700;color:var(--m)}.cita .t{flex:1}.cita small{color:#8a88a0;display:block}
.acc{display:flex;gap:6px}.acc a,.acc button{border:none;border-radius:10px;padding:8px 10px;font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit}
.wa{background:#e7f9ef;color:#128c4a}.del{background:#fdecec;color:#e5484d}.vacio{text-align:center;color:#8a88a0;padding:30px 0}
</style></head><body><div class="app">
<?php if (empty($_SESSION['admin'])): ?>
  <div class="top"><img src="logo.svg" alt="lunicitas"></div>
  <div class="box"><h2 style="margin-bottom:14px">Panel del dueño</h2>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <form method="post"><input name="u" placeholder="Usuario" autocomplete="username" required>
    <input name="p" type="password" placeholder="Contraseña" autocomplete="current-password" required>
    <button class="btn">Entrar</button></form></div>
<?php else: ?>
  <div class="top"><img src="logo.svg" alt="lunicitas"><a href="?salir=1">Salir</a></div>
  <div class="nav"><a href="?fecha=<?= e($ant) ?>">←</a><h2><?= e($titulo) ?></h2><a href="?fecha=<?= e($sig) ?>">→</a></div>
  <div class="stats"><div class="stat"><b><?= count($citas) ?></b><small>Citas</small></div><div class="stat"><b>$<?= $total ?></b><small>Ingresos estimados</small></div></div>
  <?php if (!$citas): ?><div class="box vacio">No hay citas este día</div><?php endif; ?>
  <?php foreach ($citas as $c): $tel = preg_replace('/[^0-9]/', '', $c['telefono']); ?>
  <div class="box cita"><div class="hora"><?= e(h12($c['hora'])) ?></div>
    <div class="t"><b><?= e($c['nombre']) ?></b><small><?= e($c['servicio']) ?> · <?= e($c['barbero']) ?></small></div>
    <div class="acc"><a class="wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($tel) ?>">WhatsApp</a>
    <form method="post" onsubmit="return confirm('¿Cancelar esta cita?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="fecha" value="<?= e($fecha) ?>"><button class="del" name="cancelar" value="<?= (int)$c['id'] ?>">Cancelar</button></form></div></div>
  <?php endforeach; ?>
<?php endif; ?>
</div></body></html>
