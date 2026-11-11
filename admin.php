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
if (!$db->query("SHOW COLUMNS FROM citas LIKE 'estado'")->fetch()) {
  $db->exec("ALTER TABLE citas ADD estado VARCHAR(12) NOT NULL DEFAULT 'pendiente'");
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function h12($t) { [$h, $m] = array_map('intval', explode(':', $t)); return (($h % 12) ?: 12) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' a. m.' : ' p. m.'); }
function csvSeguro($s) { $s = (string)$s; return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s; }
$PRECIOS = ['Corte clásico' => 12, 'Corte + barba' => 18, 'Fade / degradado' => 15, 'Arreglo de barba' => 8];
$ESTADOS = ['pendiente' => 'Pendiente', 'asistio' => 'Asistió', 'no_asistio' => 'No asistió'];
$DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$DC = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$admin = !empty($_SESSION['admin']);

if (isset($_GET['salir'])) { session_destroy(); header('Location: admin.php'); exit; }

// Login
$error = '';
if (!$admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Vista y fecha
$v = $_GET['v'] ?? 'dia';
if (!in_array($v, ['dia','semana','mes','reporte'], true)) $v = 'dia';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) { $fecha = date('Y-m-d'); $d = new DateTime($fecha); }
$d->setTime(0, 0);
function ir($v, $f) { header('Location: admin.php?' . http_build_query(['v' => $v, 'fecha' => $f])); exit; }
function url($v, $f) { return '?' . http_build_query(['v' => $v, 'fecha' => $f]); }

// Acciones: marcar asistencia o cancelar
if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['cancelar'])) {
      $db->prepare('DELETE FROM citas WHERE id = ?')->execute([$id]);
    } elseif (isset($ESTADOS[$_POST['estado'] ?? ''])) {
      $db->prepare('UPDATE citas SET estado = ? WHERE id = ?')->execute([$_POST['estado'], $id]);
    }
  }
  ir($v, $fecha);
}

$ini = (clone $d)->modify('first day of this month');
$fin = (clone $d)->modify('last day of this month');

// Exportar mes a Excel (CSV)
if ($admin && ($_GET['export'] ?? '') === 'csv') {
  $q = $db->prepare("SELECT * FROM citas WHERE fecha BETWEEN ? AND ? ORDER BY fecha, STR_TO_DATE(hora, '%H:%i')");
  $q->execute([$ini->format('Y-m-d'), $fin->format('Y-m-d')]);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="lunicitas-' . $ini->format('Y-m') . '.csv"');
  $o = fopen('php://output', 'w');
  fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['Fecha', 'Hora', 'Cliente', 'Teléfono', 'Servicio', 'Barbero', 'Precio', 'Estado']);
  foreach ($q as $c) {
    fputcsv($o, [$c['fecha'], h12($c['hora']), csvSeguro($c['nombre']), csvSeguro($c['telefono']), $c['servicio'], $c['barbero'],
      $PRECIOS[$c['servicio']] ?? 0, $ESTADOS[$c['estado']] ?? $c['estado']]);
  }
  exit;
}

// Navegación y título
$hoy = date('Y-m-d');
if ($v === 'dia') {
  $ant = (clone $d)->modify('-1 day'); $sig = (clone $d)->modify('+1 day');
  $titulo = ($fecha === $hoy ? 'Hoy, ' : '') . $DIAS[$d->format('w')] . ' ' . $d->format('j') . ' de ' . $MESES[$d->format('n') - 1];
} elseif ($v === 'semana') {
  $lun = (clone $d)->modify('monday this week'); $dom = (clone $lun)->modify('+6 days');
  $ant = (clone $d)->modify('-7 days'); $sig = (clone $d)->modify('+7 days');
  $titulo = $lun->format('j') . ' ' . $MESES[$lun->format('n') - 1] . ' – ' . $dom->format('j') . ' ' . $MESES[$dom->format('n') - 1];
} else {
  $ant = (clone $ini)->modify('-1 month'); $sig = (clone $ini)->modify('+1 month');
  $titulo = ucfirst($MESES[$d->format('n') - 1]) . ' ' . $d->format('Y');
}

// Datos
$citas = []; $porDia = []; $cnt = []; $r = null;
if ($admin) {
  if ($v === 'dia') {
    $q = $db->prepare("SELECT * FROM citas WHERE fecha = ? ORDER BY STR_TO_DATE(hora, '%H:%i')");
    $q->execute([$fecha]); $citas = $q->fetchAll(PDO::FETCH_ASSOC);
  } elseif ($v === 'semana') {
    $q = $db->prepare("SELECT * FROM citas WHERE fecha BETWEEN ? AND ? ORDER BY fecha, STR_TO_DATE(hora, '%H:%i')");
    $q->execute([$lun->format('Y-m-d'), $dom->format('Y-m-d')]);
    foreach ($q as $c) $porDia[$c['fecha']][] = $c;
  } else {
    $q = $db->prepare('SELECT * FROM citas WHERE fecha BETWEEN ? AND ?');
    $q->execute([$ini->format('Y-m-d'), $fin->format('Y-m-d')]);
    $todas = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($todas as $c) $cnt[$c['fecha']] = ($cnt[$c['fecha']] ?? 0) + 1;
    if ($v === 'reporte') {
      $r = ['total' => count($todas), 'asistio' => 0, 'no_asistio' => 0, 'pendiente' => 0, 'ingresos' => 0, 'barberos' => [], 'servicios' => [], 'horas' => []];
      foreach ($todas as $c) {
        $est = $c['estado']; $p = $PRECIOS[$c['servicio']] ?? 0;
        $r[$est] = ($r[$est] ?? 0) + 1;
        $b = &$r['barberos'][$c['barbero']];
        $b = $b ?? ['citas' => 0, 'asistio' => 0, 'ingresos' => 0];
        $b['citas']++;
        if ($est === 'asistio') { $b['asistio']++; $b['ingresos'] += $p; $r['ingresos'] += $p; }
        unset($b);
        $r['servicios'][$c['servicio']] = ($r['servicios'][$c['servicio']] ?? 0) + 1;
        $r['horas'][$c['hora']] = ($r['horas'][$c['hora']] ?? 0) + 1;
      }
      arsort($r['servicios']); arsort($r['horas']);
      $cerradas = $r['asistio'] + $r['no_asistio'];
      $r['tasa'] = $cerradas ? round($r['asistio'] * 100 / $cerradas) : 0;
    }
  }
}

function tarjeta($c, $v, $fecha) {
  global $ESTADOS;
  $tel = preg_replace('/[^0-9]/', '', $c['telefono']); ?>
  <div class="box cita <?= e($c['estado']) ?>">
    <div class="fila"><div class="hora"><?= e(h12($c['hora'])) ?></div>
      <div class="t"><b><?= e($c['nombre']) ?></b><small><?= e($c['servicio']) ?> · <?= e($c['barbero']) ?></small></div>
      <span class="tag"><?= e($ESTADOS[$c['estado']] ?? $c['estado']) ?></span></div>
    <form method="post" action="<?= e(url($v, $fecha)) ?>" class="acc noprint">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <button class="si" name="estado" value="asistio">✓ Asistió</button>
      <button class="no" name="estado" value="no_asistio">✗ No vino</button>
      <a class="wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($tel) ?>">WhatsApp</a>
      <button class="del" name="cancelar" value="1" onclick="return confirm('¿Cancelar esta cita?')">Cancelar</button>
    </form></div>
<?php }
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>LuniCitas – Portal</title><link rel="icon" href="logo.svg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--m:#7b5cff;--g:linear-gradient(90deg,#7b5cff,#1fd1c1)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;min-height:100vh}
.app{width:100%;max-width:760px;padding:18px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.top img{height:34px}.top a{color:#8a88a0;font-size:14px;text-decoration:none}
.tabs{display:flex;gap:6px;margin-bottom:14px;overflow-x:auto}.tabs a{flex:1;text-align:center;padding:10px;border-radius:14px;background:#fff;color:#6b6980;text-decoration:none;font-weight:600;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.tabs a.on{background:var(--g);color:#fff}
.box{background:#fff;border-radius:18px;padding:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:10px}
input{width:100%;padding:14px;border-radius:14px;border:2px solid #e4e2ee;font-size:16px;margin-bottom:10px;font-family:inherit}
.btn{display:inline-block;padding:13px 18px;border:none;border-radius:14px;background:var(--g);color:#fff;font-weight:700;font-size:15px;cursor:pointer;font-family:inherit;text-decoration:none;text-align:center}
.err{color:#e5484d;margin-bottom:10px;font-size:14px}
.nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.nav a{background:#fff;border-radius:12px;padding:8px 14px;text-decoration:none;color:var(--m);font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.06)}.nav h2{font-size:17px;text-align:center}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px}.stat{background:var(--g);color:#fff;border-radius:16px;padding:12px}.stat b{font-size:22px;display:block}.stat small{opacity:.9;font-size:12px}
.stat.w{background:#fff;color:#1d1b2e;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.fila{display:flex;gap:10px;align-items:center}.hora{min-width:82px;font-weight:700;color:var(--m)}.cita .t{flex:1}.cita small{color:#8a88a0;display:block}
.tag{font-size:12px;padding:4px 8px;border-radius:8px;background:#ebe9f3;color:#6b6980;white-space:nowrap}
.cita.asistio .tag{background:#e7f9ef;color:#128c4a}.cita.no_asistio .tag{background:#fdecec;color:#e5484d}
.acc{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.acc a,.acc button{border:none;border-radius:10px;padding:7px 10px;font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit}
.si{background:#e7f9ef;color:#128c4a}.no{background:#fff4e5;color:#b25e00}.wa{background:#eef4ff;color:#2a62d6}.del{background:#fdecec;color:#e5484d}
.vacio{text-align:center;color:#8a88a0;padding:20px 0}
.dia-h{font-weight:700;margin:16px 0 8px;color:#6b6980}.dia-h.hoy{color:var(--m)}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.cal .h{text-align:center;font-size:12px;color:#8a88a0;font-weight:600}
.cal a{background:#fff;border-radius:12px;min-height:64px;padding:6px;text-decoration:none;color:#1d1b2e;box-shadow:0 2px 8px rgba(0,0,0,.05);display:flex;flex-direction:column;justify-content:space-between}
.cal a.hoy{outline:2px solid var(--m)}.cal .n{font-size:12px;background:var(--g);color:#fff;border-radius:8px;padding:2px 6px;align-self:flex-end;font-weight:700}
table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #eee}th{color:#8a88a0;font-weight:600}
.btns{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 12px}
@media print{.noprint{display:none!important}body{background:#fff}.box,.stat{box-shadow:none;border:1px solid #ddd}}
</style></head><body><div class="app">
<?php if (!$admin): ?>
  <div class="top"><img src="logo.svg" alt="lunicitas"></div>
  <div class="box" style="max-width:420px;margin:0 auto"><h2 style="margin-bottom:14px">Portal del dueño</h2>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <form method="post"><input name="u" placeholder="Usuario" autocomplete="username" required>
    <input name="p" type="password" placeholder="Contraseña" autocomplete="current-password" required>
    <button class="btn" style="width:100%">Entrar</button></form></div>
<?php else: ?>
  <div class="top"><img src="logo.svg" alt="lunicitas"><a class="noprint" href="?salir=1">Salir</a></div>
  <div class="tabs noprint">
    <?php foreach (['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'reporte' => 'Reportes'] as $k => $n): ?>
      <a class="<?= $v === $k ? 'on' : '' ?>" href="<?= e(url($k, $fecha)) ?>"><?= $n ?></a>
    <?php endforeach; ?>
  </div>
  <div class="nav"><a class="noprint" href="<?= e(url($v, $ant->format('Y-m-d'))) ?>">←</a><h2><?= e($titulo) ?></h2><a class="noprint" href="<?= e(url($v, $sig->format('Y-m-d'))) ?>">→</a></div>

  <?php if ($v === 'dia'): $tot = 0; foreach ($citas as $c) $tot += $PRECIOS[$c['servicio']] ?? 0; ?>
    <div class="stats"><div class="stat"><b><?= count($citas) ?></b><small>Citas</small></div>
      <div class="stat"><b><?= count(array_filter($citas, function ($c) { return $c['estado'] === 'asistio'; })) ?></b><small>Asistieron</small></div>
      <div class="stat"><b>$<?= $tot ?></b><small>Ingresos estimados</small></div></div>
    <?php if (!$citas): ?><div class="box vacio">No hay citas este día</div><?php endif; ?>
    <?php foreach ($citas as $c) tarjeta($c, $v, $fecha); ?>

  <?php elseif ($v === 'semana'): ?>
    <?php for ($i = 0; $i < 7; $i++): $dd = (clone $lun)->modify("+$i days"); $f = $dd->format('Y-m-d'); ?>
      <div class="dia-h <?= $f === $hoy ? 'hoy' : '' ?>"><?= ucfirst($DIAS[$dd->format('w')]) ?> <?= $dd->format('j') ?> · <?= count($porDia[$f] ?? []) ?> citas</div>
      <?php if (empty($porDia[$f])): ?><div class="box vacio">Sin citas</div><?php endif; ?>
      <?php foreach ($porDia[$f] ?? [] as $c) tarjeta($c, $v, $fecha); ?>
    <?php endfor; ?>

  <?php elseif ($v === 'mes'): ?>
    <div class="cal">
      <?php foreach ($DC as $h): ?><div class="h"><?= $h ?></div><?php endforeach; ?>
      <?php for ($i = 1; $i < (int)$ini->format('N'); $i++): ?><div></div><?php endfor; ?>
      <?php for ($i = 1; $i <= (int)$fin->format('j'); $i++): $f = $ini->format('Y-m-') . str_pad($i, 2, '0', STR_PAD_LEFT); $n = $cnt[$f] ?? 0; ?>
        <a class="<?= $f === $hoy ? 'hoy' : '' ?>" href="<?= e(url('dia', $f)) ?>"><span><?= $i ?></span><?php if ($n): ?><span class="n"><?= $n ?></span><?php endif; ?></a>
      <?php endfor; ?>
    </div>
    <p style="color:#8a88a0;font-size:13px;margin-top:10px">Toca un día para ver sus citas. Total del mes: <?= array_sum($cnt) ?> citas.</p>

  <?php else: ?>
    <div class="btns noprint"><a class="btn" href="<?= e(url('reporte', $fecha)) ?>&export=csv">⬇ Descargar Excel</a>
      <button class="btn" onclick="window.print()">🖨 Imprimir / PDF</button></div>
    <div class="stats">
      <div class="stat"><b><?= $r['total'] ?></b><small>Citas del mes</small></div>
      <div class="stat"><b><?= $r['asistio'] ?></b><small>Asistieron</small></div>
      <div class="stat"><b><?= $r['no_asistio'] ?></b><small>No asistieron</small></div>
      <div class="stat w"><b><?= $r['pendiente'] ?></b><small>Sin marcar</small></div>
      <div class="stat w"><b><?= $r['tasa'] ?>%</b><small>Tasa de asistencia</small></div>
      <div class="stat w"><b>$<?= $r['ingresos'] ?></b><small>Ingresos (asistieron)</small></div>
    </div>
    <div class="box"><h3 style="margin-bottom:8px">Por barbero</h3>
      <?php if (!$r['barberos']): ?><p class="vacio">Sin datos este mes</p><?php else: ?>
      <table><tr><th>Barbero</th><th>Citas</th><th>Asistieron</th><th>Ingresos</th></tr>
      <?php foreach ($r['barberos'] as $n => $b): ?><tr><td><?= e($n) ?></td><td><?= $b['citas'] ?></td><td><?= $b['asistio'] ?></td><td>$<?= $b['ingresos'] ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?></div>
    <div class="box"><h3 style="margin-bottom:8px">Por servicio</h3>
      <?php if (!$r['servicios']): ?><p class="vacio">Sin datos este mes</p><?php else: ?>
      <table><tr><th>Servicio</th><th>Citas</th></tr>
      <?php foreach ($r['servicios'] as $n => $k): ?><tr><td><?= e($n) ?></td><td><?= $k ?></td></tr><?php endforeach; ?>
      </table><?php endif; ?></div>
    <?php if ($r['horas']): ?><div class="box">⏰ Hora más pedida: <b><?= e(h12(array_key_first($r['horas']))) ?></b></div><?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
