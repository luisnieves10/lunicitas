<?php
// LuniCitas — portal del dueño. Licencia AGPL-3.0
require __DIR__ . '/lib.php';
define('LC_ADMIN', true); // permite incluir los módulos de modulos/
lc_sesion();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function h12($t) { [$h, $m] = array_map('intval', explode(':', $t)); return (($h % 12) ?: 12) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' a. m.' : ' p. m.'); }
function csvSeguro($s) { $s = (string)$s; return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s; }
function js($x) { return json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); }
function cambio($a, $b) { if (!$b) return $a ? '↑ nuevo' : '—'; $p = round(($a - $b) * 100 / $b); return ($p >= 0 ? '↑ ' : '↓ ') . abs($p) . '%'; }
function p($k, $max = 300) { return mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max); }
function dinero($v) { global $neg; $v = (float)$v; return $neg['moneda'] . (floor($v) == $v ? number_format($v, 0) : number_format($v, 2)); }

$ESTADOS = ['pendiente' => 'Pendiente', 'asistio' => 'Asistió', 'no_asistio' => 'No asistió'];
$DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$DC = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$MC = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$ZONAS = ['America/Puerto_Rico','America/Santo_Domingo','America/New_York','America/Chicago','America/Denver','America/Los_Angeles',
  'America/Mexico_City','America/Guatemala','America/Panama','America/Bogota','America/Lima','America/Caracas','America/La_Paz',
  'America/Santiago','America/Argentina/Buenos_Aires','America/Sao_Paulo','Europe/Madrid','Europe/Lisbon'];
$RESERVADOS = ['admin','api','install','index','uploads','lib','config','recordatorios','logo','assets','docs','registro','stripe','entrar','legal','terminos','privacidad','terms','privacy','inicio','manifest','sw','offline','oauth','calendario','contacto','recuperar','modulos'];
$ip = lc_ip();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$super = !empty($_SESSION['super']);
$negId = (int)($_SESSION['neg'] ?? 0);

if (isset($_GET['salir'])) { session_destroy(); header('Location: admin.php'); exit; }

// ── Entrar ──────────────────────────────────────────────
$error = '';
if (!$negId && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $q = $db->prepare('SELECT COUNT(*) FROM intentos WHERE ip_hash = ? AND t > (NOW() - INTERVAL 15 MINUTE)');
  $q->execute([$ip]);
  $u = (string)($_POST['u'] ?? ''); $c = (string)($_POST['p'] ?? '');
  $esSuper = lc_cfg('ADMIN_USER') !== null && hash_equals((string)lc_cfg('ADMIN_USER'), $u)
    && (lc_cfg('ADMIN_HASH') ? password_verify($c, lc_cfg('ADMIN_HASH')) : hash_equals((string)lc_cfg('ADMIN_PASS'), $c));
  $q2 = $db->prepare('SELECT id, clave_hash FROM negocios WHERE usuario = ? AND activo = 1'); $q2->execute([$u]); $fila = $q2->fetch();
  if ($q->fetchColumn() >= 5) {
    $error = 'Demasiados intentos. Espera 15 minutos.';
  } elseif ($esSuper || ($fila && password_verify($c, $fila['clave_hash']))) {
    session_regenerate_id(true);
    if ($esSuper && !lc_cfg('URL_BASE') && !lc_meta($db, 'url_base')) lc_meta_set($db, 'url_base', lc_base()); // dirección oficial para los correos
    $_SESSION['super'] = $esSuper;
    $_SESSION['neg'] = $fila ? (int)$fila['id'] : (int)$db->query('SELECT MIN(id) FROM negocios')->fetchColumn();
    header('Location: admin.php'); exit;
  } else {
    $db->prepare('INSERT INTO intentos (ip_hash) VALUES (?)')->execute([$ip]);
    $error = 'Usuario o contraseña incorrectos';
  }
}

$neg = null;
if ($negId) {
  $q = $db->prepare('SELECT * FROM negocios WHERE id = ?'); $q->execute([$negId]); $neg = $q->fetch();
  if (!$neg) { session_destroy(); header('Location: admin.php'); exit; }
  $neg['horario'] = json_decode($neg['horario'], true) ?: lc_horario_defecto();
  date_default_timezone_set($neg['zona']);
}
$admin = (bool)$neg;
$PLAN = $neg ? lc_plan($neg) : ['clave' => 'pro', 'dias' => null];
$pro = $PLAN['clave'] !== 'cerrado';
// Prueba vencida sin pago: el dueño solo puede ver la pantalla de pago (el super admin entra igual).
$cerrado = $admin && !$super && $PLAN['clave'] === 'cerrado';
$PLAN_NOMBRE = ['pro' => 'Pro', 'prueba' => 'Prueba gratis', 'cerrado' => 'Cerrada'];
$PERSONAL = LC_PERSONAL[$neg['tipo'] ?? 'barberia'] ?? 'Barbero';

// ── Vista y fecha ───────────────────────────────────────
$VISTAS = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'reporte' => 'Análisis', 'servicios' => 'Servicios',
           'equipo' => 'Equipo', 'negocio' => 'Negocio', 'plan' => 'Plan'];
// Secciones nuevas: Clientes y Caja tras la agenda; Ayuda (negocio) o Soporte (administrador) al final.
$VISTAS = array_slice($VISTAS, 0, 4, true) + ['clientes' => 'Clientes', 'caja' => 'Caja'] + array_slice($VISTAS, 4, null, true);
if ($super) $VISTAS += ['negocios' => 'Negocios', 'soporte' => 'Soporte']; else $VISTAS['ayuda'] = 'Ayuda';
if ($cerrado) $VISTAS = ['plan' => 'Plan', 'ayuda' => 'Ayuda'];
// Mensajes sin leer (para el globito de la pestaña)
$SIN_LEER = 0;
if ($admin) {
  $q = $super ? $db->query("SELECT COUNT(*) FROM mensajes WHERE leido = 0 AND de IN ('negocio', 'visitante')")
              : $db->prepare("SELECT COUNT(*) FROM mensajes WHERE leido = 0 AND de = 'soporte' AND negocio_id = ?");
  if (!$super) $q->execute([$negId]);
  $SIN_LEER = (int)$q->fetchColumn();
}
$v = $_GET['v'] ?? 'dia';
if (!isset($VISTAS[$v])) $v = isset($VISTAS['dia']) ? 'dia' : 'plan';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$d = DateTime::createFromFormat('!Y-m-d', $fecha);
if (!$d || $d->format('Y-m-d') !== $fecha) { $fecha = date('Y-m-d'); $d = new DateTime($fecha); }
function url($v, $f = null) { global $fecha; return '?' . http_build_query(['v' => $v, 'fecha' => $f ?? $fecha]); }
function ir($v, $f = null, $msg = '') { if ($msg) $_SESSION['aviso'] = $msg; header('Location: admin.php' . url($v, $f)); exit; }

// Fotos: se validan, se reducen a 800 px y se guardan en uploads/ con un nombre al azar.
function subirFoto($campo) {
  $f = $_FILES[$campo] ?? null;
  if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 4 * 1024 * 1024) return false;
  $info = @getimagesize($f['tmp_name']);
  $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
  if (!$ext) return false;
  $dir = __DIR__ . '/uploads';
  if (!is_dir($dir)) @mkdir($dir, 0755);
  $nombre = 'uploads/' . bin2hex(random_bytes(10));
  if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring(file_get_contents($f['tmp_name'])))) {
    $w = imagesx($img); $h = imagesy($img); $k = min(1, 800 / max($w, $h));
    $nuevo = imagecreatetruecolor(max(1, (int)($w * $k)), max(1, (int)($h * $k)));
    imagecopyresampled($nuevo, $img, 0, 0, 0, 0, imagesx($nuevo), imagesy($nuevo), $w, $h);
    $ok = imagejpeg($nuevo, __DIR__ . "/$nombre.jpg", 85);
    return $ok ? "$nombre.jpg" : false;
  }
  return move_uploaded_file($f['tmp_name'], __DIR__ . "/$nombre.$ext") ? "$nombre.$ext" : false;
}
function borrarFoto($ruta) { if ($ruta && strpos($ruta, 'uploads/') === 0 && is_file(__DIR__ . '/uploads/' . basename($ruta))) @unlink(__DIR__ . '/uploads/' . basename($ruta)); }
function leerHorario() {
  $h = [];
  for ($i = 0; $i < 7; $i++) {
    $a = $_POST["abre$i"] ?? ''; $c = $_POST["cierra$i"] ?? '';
    if (empty($_POST["abierto$i"]) || lc_min($a) === null || lc_min($c) === null || lc_min($c) <= lc_min($a)) { $h[$i] = null; continue; }
    $di = $_POST["di$i"] ?? ''; $df = $_POST["df$i"] ?? '';
    if (lc_min($di) === null || lc_min($df) === null || lc_min($df) <= lc_min($di)) { $di = $df = ''; }
    $h[$i] = [$a, $c, $di, $df];
  }
  return $h;
}
function slugLibre($db, $slug, $id = 0) {
  global $RESERVADOS;
  if (!preg_match('/^[a-z0-9-]{3,40}$/', $slug) || in_array($slug, $RESERVADOS, true)) return false;
  $q = $db->prepare('SELECT COUNT(*) FROM negocios WHERE slug = ? AND id <> ?'); $q->execute([$slug, $id]);
  return !$q->fetchColumn();
}
// Abre el pago de Pro en Stripe Checkout. Si está en la prueba, guarda los días gratis: el primer cobro es al terminar.
function iniciarPago($neg, $PLAN, $volver) {
    $p = ['mode' => 'subscription', 'line_items' => [['price' => lc_cfg('STRIPE_PRICE'), 'quantity' => 1]],
        'client_reference_id' => $neg['id'], 'metadata' => ['negocio' => $neg['id']], 'subscription_data' => ['metadata' => ['negocio' => $neg['id']]],
        'allow_promotion_codes' => 'true', 'locale' => 'es', 'integration_identifier' => 'lunicitas-pro-qhzvtmbk',
        'custom_text' => ['submit' => ['message' => 'Se renueva cada mes hasta que canceles. Cancela cuando quieras en LuniCitas → Plan; no se te volverá a cobrar. '
          . 'Al pagar aceptas los Términos (' . lc_base() . '/terminos).']],
        'success_url' => lc_base() . '/admin.php?v=plan&pago=ok', 'cancel_url' => lc_base() . '/' . $volver];
  if ($neg['stripe_cliente']) $p['customer'] = $neg['stripe_cliente']; elseif ($neg['email']) $p['customer_email'] = $neg['email'];
  // Si está en la prueba gratis, conserva los días que le quedan: el primer cobro es al terminar la prueba.
  // (Stripe pide que la prueba termine al menos 2 días después de hoy.)
  if ($PLAN['clave'] === 'prueba' && $neg['prueba_hasta']) {
    $finPrueba = strtotime($neg['prueba_hasta'] . ' 23:59:59');
    if ($finPrueba > time() + 2 * 86400 + 3600) {
      $p['subscription_data']['trial_end'] = $finPrueba;
      $p['custom_text']['submit']['message'] = 'Tus días gratis siguen: no se te cobra nada hasta el ' . date('j/n/Y', $finPrueba)
        . '. Si cancelas antes en LuniCitas → Plan, no pagas nada. Luego se renueva cada mes hasta que canceles. Al continuar aceptas los Términos (' . lc_base() . '/terminos).';
    }
  }
  $r = lc_stripe('checkout/sessions', $p);
  if (!empty($r['url'])) { header('Location: ' . $r['url']); exit; }
  ir('plan', null, 'No se pudo abrir el pago: ' . ($r['error']['message'] ?? 'error desconocido'));
}
// Recién registrado: va directo a poner la tarjeta (con sus días gratis). Si sale del pago, sigue con su prueba.
if ($admin && !empty($_SESSION['empezar_pago'])) {
  unset($_SESSION['empezar_pago']);
  if (lc_stripe_activo() && $PLAN['clave'] === 'prueba' && !$neg['stripe_sub']) iniciarPago($neg, $PLAN, 'admin.php?v=negocio&sin_tarjeta=1');
}
// ── Acciones ────────────────────────────────────────────
if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) ir($v, null, 'La página caducó, inténtalo otra vez.');
  $nid = $neg['id']; $id = (int)($_POST['id'] ?? 0); $a = $_POST['accion'] ?? '';
  if ($cerrado && !in_array($a, ['pagar', 'portal', 'email', 'chat_enviar', 'sugerir'], true)) ir('plan', null, 'Tu prueba gratis terminó. Añade tu tarjeta para seguir usando LuniCitas.');
  require __DIR__ . '/modulos/acciones.php';

  // Citas
  if (isset($_POST['cancelar'])) {
    $db->prepare('DELETE FROM citas WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]); ir($v);
  }
  if (isset($ESTADOS[$_POST['estado'] ?? ''])) {
    $db->prepare('UPDATE citas SET estado = ? WHERE id = ? AND negocio_id = ?')->execute([$_POST['estado'], $id, $nid]); ir($v);
  }
  if ($a === 'recordar') {
    if (!$pro) ir('plan', null, 'Los recordatorios por WhatsApp son del plan Pro.');
    $q = $db->prepare('SELECT * FROM citas WHERE id = ? AND negocio_id = ?'); $q->execute([$id, $nid]);
    if ($c = $q->fetch()) {
      $db->prepare('UPDATE citas SET recordado = 1 WHERE id = ?')->execute([$id]);
      header('Location: https://wa.me/' . lc_wa_numero($c['telefono'], $neg['prefijo']) . '?text=' . rawurlencode(lc_mensaje_recordatorio($c, $neg)));
      exit;
    }
    ir($v);
  }

  // Servicios
  if ($a === 'svc_guardar') {
    $nombre = p('nombre', 60); $dur = max(5, min(600, (int)($_POST['duracion'] ?? 30)));
    $precio = max(0, round((float)str_replace(',', '.', $_POST['precio'] ?? '0'), 2)); $orden = (int)($_POST['orden'] ?? 0);
    if (mb_strlen($nombre) < 2) ir('servicios', null, 'Escribe el nombre del servicio.');
    $foto = subirFoto('foto');
    if ($foto === false) ir('servicios', null, 'La foto debe ser JPG, PNG o WEBP de menos de 4 MB.');
    if ($id) {
      $q = $db->prepare('SELECT foto FROM servicios WHERE id = ? AND negocio_id = ?'); $q->execute([$id, $nid]); $vieja = $q->fetchColumn();
      if ($vieja === false) ir('servicios');
      $nueva = $foto ?: (!empty($_POST['quitar_foto']) ? '' : $vieja);
      if ($nueva !== $vieja) borrarFoto($vieja);
      $db->prepare('UPDATE servicios SET nombre = ?, duracion = ?, precio = ?, orden = ?, foto = ? WHERE id = ? AND negocio_id = ?')
         ->execute([$nombre, $dur, $precio, $orden, $nueva, $id, $nid]);
    } else {
      $db->prepare('INSERT INTO servicios (negocio_id, nombre, duracion, precio, orden, foto) VALUES (?,?,?,?,?,?)')
         ->execute([$nid, $nombre, $dur, $precio, $orden, $foto ?: '']);
    }
    ir('servicios', null, 'Servicio guardado.');
  }
  if ($a === 'svc_borrar') {
    $db->prepare('UPDATE servicios SET activo = 0 WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]);
    ir('servicios', null, 'Servicio quitado. Las citas anteriores se conservan.');
  }

  // Equipo
  if ($a === 'eq_guardar') {
    $nombre = p('nombre', 40); $esp = p('especialidad', 60); $orden = (int)($_POST['orden'] ?? 0);
    if (mb_strlen($nombre) < 2) ir('equipo', null, 'Escribe el nombre.');
    $foto = subirFoto('foto');
    if ($foto === false) ir('equipo', null, 'La foto debe ser JPG, PNG o WEBP de menos de 4 MB.');
    if ($id) {
      $q = $db->prepare('SELECT foto FROM equipo WHERE id = ? AND negocio_id = ?'); $q->execute([$id, $nid]); $vieja = $q->fetchColumn();
      if ($vieja === false) ir('equipo');
      $nueva = $foto ?: (!empty($_POST['quitar_foto']) ? '' : $vieja);
      if ($nueva !== $vieja) borrarFoto($vieja);
      $db->prepare('UPDATE equipo SET nombre = ?, especialidad = ?, orden = ?, foto = ? WHERE id = ? AND negocio_id = ?')
         ->execute([$nombre, $esp, $orden, $nueva, $id, $nid]);
      $db->prepare('UPDATE citas SET barbero = ? WHERE equipo_id = ? AND negocio_id = ? AND fecha >= CURDATE()')->execute([$nombre, $id, $nid]);
    } else {
      $q = $db->prepare('SELECT COUNT(*) FROM equipo WHERE negocio_id = ? AND activo = 1'); $q->execute([$nid]);
      $db->prepare('INSERT INTO equipo (negocio_id, nombre, especialidad, orden, foto) VALUES (?,?,?,?,?)')
         ->execute([$nid, $nombre, $esp, $orden, $foto ?: '']);
    }
    ir('equipo', null, 'Guardado.');
  }
  if ($a === 'eq_borrar') {
    $db->prepare('UPDATE equipo SET activo = 0 WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]);
    ir('equipo', null, 'Quitado del equipo. Sus citas anteriores se conservan.');
  }

  // Negocio
  if ($a === 'neg_guardar') {
    $nombre = p('nombre', 80); $slug = lc_slug($_POST['slug'] ?? '');
    $tipo = isset(LC_TIPOS[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : $neg['tipo'];
    $zona = in_array($_POST['zona'] ?? '', DateTimeZone::listIdentifiers(), true) ? $_POST['zona'] : $neg['zona'];
    $int = in_array((int)($_POST['intervalo'] ?? 0), [10, 15, 20, 30, 45, 60], true) ? (int)$_POST['intervalo'] : 15;
    if (mb_strlen($nombre) < 2) ir('negocio', null, 'Escribe el nombre del negocio.');
    if (!slugLibre($db, $slug, $nid)) ir('negocio', null, 'Ese enlace no está disponible. Usa de 3 a 40 letras, números o guiones.');
    $foto = subirFoto('foto');
    if ($foto === false) ir('negocio', null, 'La foto debe ser JPG, PNG o WEBP de menos de 4 MB.');
    $nueva = $foto ?: (!empty($_POST['quitar_foto']) ? '' : $neg['foto']);
    if ($nueva !== $neg['foto']) borrarFoto($neg['foto']);
    $db->prepare('UPDATE negocios SET nombre=?, slug=?, tipo=?, direccion=?, telefono=?, prefijo=?, descripcion=?, zona=?, moneda=?, intervalo=?, horario=?, foto=? WHERE id=?')
       ->execute([$nombre, $slug, $tipo, p('direccion', 120), preg_replace('/[^0-9+ ()-]/', '', p('telefono', 20)),
                  preg_replace('/[^0-9]/', '', p('prefijo', 4)), p('descripcion', 300), $zona, p('moneda', 4) ?: '$', $int,
                  json_encode(leerHorario()), $nueva, $nid]);
    ir('negocio', null, 'Cambios guardados.');
  }
  if ($a === 'libre_add') {
    $f = $_POST['fecha_libre'] ?? ''; $hasta = ($_POST['hasta'] ?? '') ?: $f; $eq = (int)($_POST['equipo_id'] ?? 0) ?: null;
    $d1 = DateTime::createFromFormat('!Y-m-d', $f); $d2 = DateTime::createFromFormat('!Y-m-d', $hasta);
    if (!$d1 || !$d2 || $d2 < $d1 || $d1->diff($d2)->days > 90) ir('negocio', null, 'Revisa las fechas (máximo 90 días seguidos).');
    for ($x = clone $d1; $x <= $d2; $x->modify('+1 day'))
      $db->prepare('INSERT INTO dias_libres (negocio_id, fecha, equipo_id, motivo) VALUES (?,?,?,?)')->execute([$nid, $x->format('Y-m-d'), $eq, p('motivo', 80)]);
    ir('negocio', null, 'Día libre añadido.');
  }
  if ($a === 'libre_del') {
    $db->prepare('DELETE FROM dias_libres WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]); ir('negocio');
  }
  if ($a === 'clave') {
    // Quien se registró con Google o Microsoft no tiene contraseña: la crea sin pedir la actual.
    if ($neg['clave_hash'] !== '' && !password_verify((string)($_POST['actual'] ?? ''), $neg['clave_hash'])) ir('negocio', null, 'La contraseña actual no es correcta.');
    if (strlen($_POST['nueva'] ?? '') < 8) ir('negocio', null, 'La contraseña nueva debe tener al menos 8 caracteres.');
    $db->prepare('UPDATE negocios SET clave_hash = ? WHERE id = ?')->execute([password_hash($_POST['nueva'], PASSWORD_DEFAULT), $nid]);
    ir('negocio', null, $neg['clave_hash'] === '' ? 'Contraseña creada. Ya puedes entrar también con tu usuario: ' . $neg['usuario'] : 'Contraseña cambiada.');
  }
  if ($a === 'id_quitar') {
    $q = $db->prepare('SELECT COUNT(*) FROM identidades WHERE negocio_id = ?'); $q->execute([$nid]);
    if ($neg['clave_hash'] === '' && $q->fetchColumn() <= 1) ir('negocio', null, 'Crea una contraseña antes de quitar tu única forma de entrar.');
    $db->prepare('DELETE FROM identidades WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]);
    ir('negocio', null, 'Cuenta desconectada.');
  }

  // Pagos con Stripe
  if ($a === 'pagar' && lc_stripe_activo()) {
    // Ya tiene una suscripción viva: se administra en el portal, nunca se crea una segunda.
    if ($neg['stripe_sub'] && in_array($neg['stripe_estado'], ['active', 'trialing', 'past_due'], true)) $a = 'portal';
  }
  if ($a === 'pagar' && lc_stripe_activo()) iniciarPago($neg, $PLAN, 'admin.php?v=plan');
  // Cancelar desde LuniCitas, sin depender del portal: Stripe no vuelve a cobrar y el Pro dura hasta el fin del periodo pagado.
  if (in_array($a, ['cancelar', 'reactivar'], true) && lc_stripe_activo() && $neg['stripe_sub']) {
    $r = lc_stripe('subscriptions/' . rawurlencode($neg['stripe_sub']), ['cancel_at_period_end' => $a === 'cancelar' ? 'true' : 'false']);
    if (!empty($r['error'])) ir('plan', null, 'Stripe no respondió: ' . $r['error']['message'] . '. Escríbenos y lo cancelamos por ti.');
    $finSub = $r['cancel_at'] ?? ($r['items']['data'][0]['current_period_end'] ?? null);
    $db->prepare('UPDATE negocios SET stripe_cancela = ? WHERE id = ?')
       ->execute([$a === 'cancelar' && $finSub ? date('Y-m-d', (int)$finSub) : null, $nid]);
    ir('plan', null, $a === 'cancelar'
      ? 'Suscripción cancelada. No se te volverá a cobrar; tienes Pro hasta el ' . ($finSub ? date('j/n/Y', (int)$finSub) : 'fin del periodo pagado') . '.'
      : '¡Listo! Tu plan Pro sigue activo y se renovará normalmente.');
  }
  if ($a === 'portal' && lc_stripe_activo() && $neg['stripe_cliente']) {
    $r = lc_stripe('billing_portal/sessions', ['customer' => $neg['stripe_cliente'], 'return_url' => lc_base() . '/admin.php?v=plan', 'locale' => 'es']);
    if (!empty($r['url'])) { header('Location: ' . $r['url']); exit; }
    ir('plan', null, 'No se pudo abrir la facturación: ' . ($r['error']['message'] ?? 'error desconocido'));
  }
  if ($a === 'email') {
    $em = mb_strtolower(p('email', 120));
    if (!filter_var($em, FILTER_VALIDATE_EMAIL)) ir('plan', null, 'Escribe un correo válido.');
    $db->prepare('UPDATE negocios SET email = ? WHERE id = ?')->execute([$em, $nid]); ir('plan', null, 'Correo guardado.');
  }

  // Solo el dueño de la plataforma
  if ($super && $a === 'neg_plan' && in_array($_POST['plan'] ?? '', ['gratis', 'prueba', 'pro'], true)) {
    $pl = $_POST['plan'];
    $db->prepare('UPDATE negocios SET plan = ?, prueba_hasta = ?, pagado_hasta = NULL WHERE id = ?')
       ->execute([$pl, $pl === 'prueba' ? date('Y-m-d', strtotime('+' . LC_DIAS_PRUEBA . ' days')) : null, $id]);
    ir('negocios', null, 'Plan cambiado.');
  }
  if ($super && $a === 'alerta_ok') { lc_meta_set($db, 'stripe_alerta', ''); ir('plan', null, 'Aviso de Stripe borrado.'); }
  if ($super && $a === 'registro') { lc_meta_set($db, 'registro', lc_registro_abierto($db) ? '0' : '1'); ir('negocios', null, 'Registro público ' . (lc_registro_abierto($db) ? 'abierto.' : 'cerrado.')); }
  if ($super && $a === 'neg_entrar') { $_SESSION['neg'] = $id; ir('dia'); }
  if ($super && $a === 'neg_crear') {
    $nombre = p('nombre', 80); $slug = lc_slug(($_POST['slug'] ?? '') ?: $nombre); $usuario = p('usuario', 40);
    $tipo = isset(LC_TIPOS[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'barberia';
    $q = $db->prepare('SELECT COUNT(*) FROM negocios WHERE usuario = ?'); $q->execute([$usuario]);
    if (mb_strlen($nombre) < 2 || mb_strlen($usuario) < 3) ir('negocios', null, 'Faltan el nombre del negocio o el usuario.');
    if ($q->fetchColumn() || $usuario === lc_cfg('ADMIN_USER')) ir('negocios', null, 'Ese usuario ya existe.');
    if (strlen($_POST['clave'] ?? '') < 8) ir('negocios', null, 'La contraseña debe tener al menos 8 caracteres.');
    if (!slugLibre($db, $slug)) ir('negocios', null, 'Ese enlace no está disponible.');
    lc_crear_negocio($db, $nombre, $tipo, $slug, $usuario, $_POST['clave'], p('persona', 40));
    ir('negocios', null, "Negocio creado. Su enlace es /$slug");
  }
  ir($v);
}
$aviso = $_SESSION['aviso'] ?? ''; unset($_SESSION['aviso']);
if (isset($_GET['sin_tarjeta']) && $PLAN['clave'] === 'prueba') $aviso = 'Sigues con tus ' . LC_DIAS_PRUEBA . ' días gratis. Para que tu página no se cierre al terminar, añade tu tarjeta en Plan cuando quieras: no se cobra hasta el último día.';

$ini = (clone $d)->modify('first day of this month');
$fin = (clone $d)->modify('last day of this month');

// ── Exportar a Excel (CSV) ──────────────────────────────
if ($admin && ($_GET['export'] ?? '') === 'csv') {
  $q = $db->prepare("SELECT * FROM citas WHERE negocio_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha, STR_TO_DATE(hora, '%H:%i')");
  $q->execute([$neg['id'], $ini->format('Y-m-d'), $fin->format('Y-m-d')]);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="lunicitas-' . $neg['slug'] . '-' . $ini->format('Y-m') . '.csv"');
  $o = fopen('php://output', 'w');
  fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['Fecha', 'Hora', 'Cliente', 'Teléfono', 'Servicio', $PERSONAL, 'Duración (min)', 'Precio', 'Estado']);
  foreach ($q as $c) {
    fputcsv($o, [$c['fecha'], h12($c['hora']), csvSeguro($c['nombre']), csvSeguro($c['telefono']), csvSeguro($c['servicio']), csvSeguro($c['barbero']),
      $c['duracion'], $c['precio'], $ESTADOS[$c['estado']] ?? $c['estado']]);
  }
  exit;
}

if ($admin) require __DIR__ . '/modulos/get.php';

// ── Navegación ──────────────────────────────────────────
$hoy = date('Y-m-d'); $manana = date('Y-m-d', strtotime('+1 day'));
$ant = $sig = null; $titulo = $VISTAS[$v];
if ($v === 'dia') {
  $ant = (clone $d)->modify('-1 day'); $sig = (clone $d)->modify('+1 day');
  $titulo = ($fecha === $hoy ? 'Hoy, ' : ($fecha === $manana ? 'Mañana, ' : '')) . $DIAS[$d->format('w')] . ' ' . $d->format('j') . ' de ' . $MESES[$d->format('n') - 1];
} elseif ($v === 'semana') {
  $lun = (clone $d)->modify('monday this week'); $dom = (clone $lun)->modify('+6 days');
  $ant = (clone $d)->modify('-7 days'); $sig = (clone $d)->modify('+7 days');
  $titulo = $lun->format('j') . ' ' . $MESES[$lun->format('n') - 1] . ' – ' . $dom->format('j') . ' ' . $MESES[$dom->format('n') - 1];
} elseif ($v === 'mes' || $v === 'reporte') {
  $ant = (clone $ini)->modify('-1 month'); $sig = (clone $ini)->modify('+1 month');
  $titulo = ucfirst($MESES[$d->format('n') - 1]) . ' ' . $d->format('Y');
}

// ── Datos ───────────────────────────────────────────────
$citas = []; $porDia = []; $cnt = []; $r = null; $porRecordar = 0;
function resumen($filas) {
  $s = ['total' => count($filas), 'ingresos' => 0];
  foreach ($filas as $c) if ($c['estado'] === 'asistio') $s['ingresos'] += (float)$c['precio'];
  return $s;
}
if ($admin) {
  $nid = $neg['id'];
  $q = $db->prepare('SELECT * FROM servicios WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id'); $q->execute([$nid]); $servicios = $q->fetchAll();
  $q = $db->prepare('SELECT * FROM equipo WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id'); $q->execute([$nid]); $equipo = $q->fetchAll();
  $q = $db->prepare('SELECT COUNT(*) FROM citas WHERE negocio_id = ? AND fecha = ? AND recordado = 0'); $q->execute([$nid, $manana]);
  $porRecordar = (int)$q->fetchColumn();
  if ($v === 'dia') {
    $q = $db->prepare("SELECT * FROM citas WHERE negocio_id = ? AND fecha = ? ORDER BY STR_TO_DATE(hora, '%H:%i')");
    $q->execute([$nid, $fecha]); $citas = $q->fetchAll();
  } elseif ($v === 'semana') {
    $q = $db->prepare("SELECT * FROM citas WHERE negocio_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha, STR_TO_DATE(hora, '%H:%i')");
    $q->execute([$nid, $lun->format('Y-m-d'), $dom->format('Y-m-d')]);
    foreach ($q as $c) $porDia[$c['fecha']][] = $c;
  } elseif ($v === 'mes' || $v === 'reporte') {
    $q = $db->prepare('SELECT * FROM citas WHERE negocio_id = ? AND fecha BETWEEN ? AND ?');
    $q->execute([$nid, $ini->format('Y-m-d'), $fin->format('Y-m-d')]);
    $todas = $q->fetchAll();
    foreach ($todas as $c) $cnt[$c['fecha']] = ($cnt[$c['fecha']] ?? 0) + 1;
    if ($v === 'reporte') {
      $r = ['total' => count($todas), 'asistio' => 0, 'no_asistio' => 0, 'pendiente' => 0, 'ingresos' => 0,
            'barberos' => [], 'servicios' => [], 'ingServ' => [], 'horas' => [], 'dias' => array_fill(1, 7, 0)];
      foreach ($servicios as $s) { $r['servicios'][$s['nombre']] = 0; $r['ingServ'][$s['nombre']] = 0; }
      foreach ($todas as $c) {
        $est = $c['estado']; $pr = (float)$c['precio'];
        $r[$est] = ($r[$est] ?? 0) + 1;
        $r['barberos'][$c['barbero']] = ($r['barberos'][$c['barbero']] ?? 0) + 1;
        $r['servicios'][$c['servicio']] = ($r['servicios'][$c['servicio']] ?? 0) + 1;
        $r['ingServ'][$c['servicio']] = ($r['ingServ'][$c['servicio']] ?? 0);
        if ($est === 'asistio') { $r['ingresos'] += $pr; $r['ingServ'][$c['servicio']] += $pr; }
        $r['horas'][$c['hora']] = ($r['horas'][$c['hora']] ?? 0) + 1;
        $r['dias'][(int)date('N', strtotime($c['fecha']))]++;
      }
      arsort($r['horas']);
      $cerradas = $r['asistio'] + $r['no_asistio'];
      $r['tasa'] = $cerradas ? round($r['asistio'] * 100 / $cerradas) : 0;
      $pIni = (clone $ini)->modify('-1 month'); $pFin = (clone $pIni)->modify('last day of this month');
      $q = $db->prepare('SELECT precio, estado FROM citas WHERE negocio_id = ? AND fecha BETWEEN ? AND ?');
      $q->execute([$nid, $pIni->format('Y-m-d'), $pFin->format('Y-m-d')]);
      $r['prev'] = resumen($q->fetchAll());
      $t0 = (clone $ini)->modify('-5 months');
      $q = $db->prepare("SELECT DATE_FORMAT(fecha, '%Y-%m') m, servicio, COUNT(*) n FROM citas WHERE negocio_id = ? AND fecha BETWEEN ? AND ? GROUP BY m, servicio");
      $q->execute([$nid, $t0->format('Y-m-d'), $fin->format('Y-m-d')]);
      $r['tMeses'] = []; $claves = [];
      for ($i = 0; $i < 6; $i++) { $mm = (clone $t0)->modify("+$i months"); $claves[] = $mm->format('Y-m'); $r['tMeses'][] = $MC[$mm->format('n') - 1]; }
      $r['tend'] = [];
      foreach (array_keys($r['servicios']) as $sv) $r['tend'][$sv] = array_fill(0, 6, 0);
      foreach ($q as $row) { $k = array_search($row['m'], $claves, true); if ($k !== false && isset($r['tend'][$row['servicio']])) $r['tend'][$row['servicio']][$k] = (int)$row['n']; }
    }
  } elseif ($v === 'negocio') {
    $q = $db->prepare('SELECT l.*, e.nombre quien FROM dias_libres l LEFT JOIN equipo e ON e.id = l.equipo_id WHERE l.negocio_id = ? AND l.fecha >= ? ORDER BY l.fecha');
    $q->execute([$nid, $hoy]); $libres = $q->fetchAll();
  } elseif ($v === 'negocios') {
    $lista = $db->query('SELECT n.*, (SELECT COUNT(*) FROM citas c WHERE c.negocio_id = n.id) citas FROM negocios n ORDER BY n.id')->fetchAll();
  }
}
$base = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'lunicitas.com') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$csrf = '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';

function tarjeta($c, $v, $fecha) {
  global $ESTADOS, $csrf, $db, $neg;
  $cli = null;
  if (!empty($c['cliente_id'])) { $q = $db->prepare('SELECT * FROM clientes WHERE id = ? AND negocio_id = ?'); $q->execute([$c['cliente_id'], $neg['id']]); $cli = $q->fetch() ?: null; }
  [$sugPct, $sugMotivo] = lc_descuento_sugerido($db, $neg, $cli);
  $neto = (float)$c['precio'] - (float)$c['descuento']; ?>
  <div class="box cita <?= e($c['estado']) ?>">
    <div class="fila"><div class="hora"><?= e(h12($c['hora'])) ?></div>
      <div class="t"><b><?= e($c['nombre']) ?></b><?= $cli && $cli['fijo'] ? ' ⭐' : '' ?><small><?= e($c['servicio']) ?> · <?= e($c['barbero']) ?> · <?= (int)$c['duracion'] ?> min · <?= e(dinero($c['precio'])) ?><?php if ($c['estado'] === 'asistio'): ?> · cobrado <?= e(dinero($neto)) ?><?= $c['descuento'] > 0 ? ' (' . e($c['motivo'] ?: 'descuento') . ')' : '' ?><?= $c['propina'] > 0 ? ' + propina ' . e(dinero($c['propina'])) : '' ?><?php endif; ?></small>
        <?php if ($sugPct > 0 && $c['estado'] !== 'asistio'): ?><span class="sug">🎁 Le toca <?= e(rtrim(rtrim(number_format($sugPct, 2), '0'), '.')) ?>% · <?= e($sugMotivo) ?></span><?php endif; ?></div>
      <span class="tag"><?= e($ESTADOS[$c['estado']] ?? $c['estado']) ?></span></div>
      <?php if ($c['estado'] !== 'asistio'): ?>
      <form method="post" action="<?= e(url($v, $fecha)) ?>" class="noprint"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="accion" value="cobrar">
      <details class="cobro"><summary class="si" role="button">✓ Asistió y cobrar</summary>
        <div class="cf"><label>Descuento</label>
          <select name="pct"><option value="0">Sin descuento · <?= e(dinero($c['precio'])) ?></option>
            <?php if ($sugPct > 0): ?><option value="<?= e($sugPct) ?>" selected><?= e(rtrim(rtrim(number_format($sugPct, 2), '0'), '.')) ?>% · <?= e($sugMotivo) ?> · <?= e(dinero($c['precio'] * (1 - $sugPct / 100))) ?></option><?php endif; ?>
            <?php foreach ([5, 10, 15, 20, 25, 50, 100] as $pc): if (abs($pc - $sugPct) < 0.01) continue; ?><option value="<?= $pc ?>"><?= $pc ?>%<?= $pc == 100 ? ' (gratis)' : '' ?> · <?= e(dinero($c['precio'] * (1 - $pc / 100))) ?></option><?php endforeach; ?>
          </select>
          <label>Propina (opcional)</label><input name="propina" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0">
          <label>¿Cómo pagó?</label>
          <div class="mt"><button name="metodo" value="efectivo">💵 Efectivo</button><button name="metodo" value="ath">📱 ATH Móvil</button>
            <button name="metodo" value="tarjeta">💳 Tarjeta</button><button name="metodo" value="transferencia">🏦 Transferencia</button></div></div></details></form>
      <?php endif; ?>
    <form method="post" action="<?= e(url($v, $fecha)) ?>" class="acc noprint"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">

      <button class="no" name="estado" value="no_asistio">✗ No vino</button>
      <?php if ($GLOBALS['pro']): ?><button class="wa" name="accion" value="recordar" formtarget="_blank" onclick="setTimeout(()=>location.reload(),800)"><?= $c['recordado'] ? '✓ Recordado' : '🔔 Recordar por WhatsApp' ?></button>
      <?php else: ?><a class="wa" href="?v=plan">🔔 Recordar (Pro)</a><?php endif; ?>
      <button class="del" name="cancelar" value="1" onclick="return confirm('¿Cancelar esta cita?')">Cancelar</button>
    </form></div>
<?php }
function fotoPeq($f, $txt) { return $f ? '<img class="mini" src="' . e($f) . '" alt="">' : '<span class="mini av">' . e(mb_substr($txt, 0, 1)) . '</span>'; }
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>LuniCitas – Portal</title><link rel="icon" href="logo.svg?v=3"><meta name="theme-color" content="#7b5cff"><link rel="manifest" href="manifest.webmanifest"><link rel="apple-touch-icon" href="assets/app/apple-touch-icon.png"><meta name="apple-mobile-web-app-title" content="LuniCitas"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><script src="assets/app/instalar.js" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--m:#7b5cff;--g:linear-gradient(90deg,#7b5cff,#1fd1c1)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;min-height:100vh}
.app{width:100%;max-width:820px;padding:18px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px}.top img{height:34px}.top a{color:#8a88a0;font-size:14px;text-decoration:none}
.top .neg{flex:1;text-align:right;font-size:13px;color:#6b6980}.top .neg a{color:var(--m);font-weight:600}
.tabs{display:flex;gap:6px;margin-bottom:14px;overflow-x:auto;padding-bottom:4px}.tabs a{flex:1;white-space:nowrap;text-align:center;padding:10px 12px;border-radius:14px;background:#fff;color:#6b6980;text-decoration:none;font-weight:600;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.tabs a.on{background:var(--g);color:#fff}
.box{background:#fff;border-radius:18px;padding:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:10px}
.box h3{font-size:15px;margin-bottom:10px}
input,select,textarea{width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;font-size:15px;margin-bottom:10px;font-family:inherit;background:#fff}
textarea{min-height:70px}label{font-size:13px;color:#6b6980;display:block;margin-bottom:3px}
.btn{display:inline-block;padding:12px 16px;border:none;border-radius:14px;background:var(--g);color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;text-decoration:none;text-align:center}
.btn.gris{background:#ebe9f3;color:#6b6980}.btn.rojo{background:#fdecec;color:#e5484d}
.err{color:#e5484d;margin-bottom:10px;font-size:14px}.aviso{background:#e7f9ef;color:#128c4a;border-radius:14px;padding:12px;margin-bottom:12px;font-size:14px}
.alerta{background:#fff4e5;color:#8a4b00;border-radius:14px;padding:12px;margin-bottom:12px;font-size:14px}.alerta a{color:inherit;font-weight:700}
.nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.nav a{background:#fff;border-radius:12px;padding:8px 14px;text-decoration:none;color:var(--m);font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.06)}.nav h2{font-size:17px;text-align:center;flex:1}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px}.stat{background:var(--g);color:#fff;border-radius:16px;padding:12px}.stat b{font-size:22px;display:block}.stat small{opacity:.9;font-size:12px}
.chip{font-size:11px;display:inline-block;margin-top:4px;padding:2px 6px;border-radius:6px;background:rgba(255,255,255,.25)}
.fila{display:flex;gap:10px;align-items:center}.hora{min-width:82px;font-weight:700;color:var(--m)}.cita .t,.item .t{flex:1}.cita small,.item small{color:#8a88a0;display:block}
.tag{font-size:12px;padding:4px 8px;border-radius:8px;background:#ebe9f3;color:#6b6980;white-space:nowrap}
.cita.asistio .tag{background:#e7f9ef;color:#128c4a}.cita.no_asistio .tag{background:#fdecec;color:#e5484d}
.acc{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.acc a,.acc button{border:none;border-radius:10px;padding:7px 10px;font-size:13px;cursor:pointer;text-decoration:none;font-family:inherit}
.si{background:#e7f9ef;color:#128c4a}.no{background:#fff4e5;color:#b25e00}.wa{background:#e8f8ee;color:#128c4a}.del{background:#fdecec;color:#e5484d}
.vacio{text-align:center;color:#8a88a0;padding:20px 0}
.dia-h{font-weight:700;margin:16px 0 8px;color:#6b6980}.dia-h.hoy{color:var(--m)}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.cal .h{text-align:center;font-size:12px;color:#8a88a0;font-weight:600}
.cal a{background:#fff;border-radius:12px;min-height:64px;padding:6px;text-decoration:none;color:#1d1b2e;box-shadow:0 2px 8px rgba(0,0,0,.05);display:flex;flex-direction:column;justify-content:space-between}
.cal a.hoy{outline:2px solid var(--m)}.cal .n{font-size:12px;background:var(--g);color:#fff;border-radius:8px;padding:2px 6px;align-self:flex-end;font-weight:700}
.btns{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.g3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px}
@media(max-width:640px){.grid2{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
.rank{margin-bottom:10px}.rank .lin{display:flex;justify-content:space-between;font-size:14px;margin-bottom:4px}.rank .bar{height:10px;border-radius:6px;background:#ebe9f3;overflow:hidden}.rank .bar span{display:block;height:100%;border-radius:6px}
.chart{position:relative;height:240px}
.mini{width:48px;height:48px;border-radius:12px;object-fit:cover;flex-shrink:0}.mini.av{display:flex;align-items:center;justify-content:center;background:var(--g);color:#fff;font-weight:700}
details.item{background:#fff;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:10px}details.item summary{list-style:none;padding:12px;cursor:pointer}details.item summary::-webkit-details-marker{display:none}
details.item form{padding:0 12px 12px}.price{color:var(--m);font-weight:700}
.hor{display:grid;grid-template-columns:90px 1fr 1fr;gap:6px;align-items:center;margin-bottom:6px;font-size:14px}.hor input{margin:0;padding:8px}
.hor .desc{grid-column:2/4;display:grid;grid-template-columns:auto 1fr 1fr;gap:6px;align-items:center;font-size:12px;color:#8a88a0;margin-bottom:8px}
.enlace{background:#f1edff;border-radius:12px;padding:10px;font-size:14px;word-break:break-all;margin-bottom:10px}
table.lista{width:100%;border-collapse:collapse;font-size:14px}table.lista td,table.lista th{padding:8px 6px;border-bottom:1px solid #eee;text-align:left}
.volver{display:inline-block;font-size:14px;color:#5b45d6;text-decoration:none;font-weight:600;margin-bottom:8px}
.instalar{display:flex;align-items:center;gap:12px;width:100%;background:#fff;border:2px dashed #cfc6ff;border-radius:16px;padding:12px 14px;margin-bottom:12px;font:inherit;font-size:14px;color:#1d1b2e;text-align:left;cursor:pointer}.instalar img{width:40px;height:40px;border-radius:10px}.instalar b{display:block;font-size:15px}.instalar[hidden]{display:none}
.nb{display:inline-block;background:#e5484d;color:#fff;border-radius:999px;font-size:11px;font-weight:700;padding:0 6px;margin-left:2px}
.cobro summary{list-style:none;display:inline-block}.cobro summary::-webkit-details-marker{display:none}.cobro[open]{width:100%}.cobro .cf{background:#f7f7fb;border-radius:14px;padding:10px;margin-top:8px}
.cobro .cf select,.cobro .cf input{margin:0 0 8px}.cobro .mt{display:grid;grid-template-columns:1fr 1fr;gap:6px}.cobro .mt button{padding:10px;border:0;border-radius:12px;background:#fff;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.sug{font-size:12px;background:#e3faf7;color:#0b7a6e;border-radius:8px;padding:4px 8px;display:inline-block;margin-top:4px}
.plan-prueba{color:#b25e00!important}.plan-cerrado{color:#c0392b!important}
.ck{display:flex;gap:6px;align-items:center;font-size:14px;margin-bottom:10px}.ck input{width:auto;margin:0}
@media print{.noprint{display:none!important}body{background:#fff}.box,.stat{box-shadow:none;border:1px solid #ddd}}
<?= LC_OAUTH_CSS ?>
</style></head><body><div class="app">
<?php if (!$admin): ?>
  <a class="volver" href="./">← Volver al inicio</a>
  <div class="top"><a href="./"><img src="logo.svg?v=3" alt="lunicitas, ir al inicio"></a></div>
  <div class="box" style="max-width:420px;margin:0 auto"><h2 style="margin-bottom:14px">Portal del dueño</h2>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <?php if ($aviso): ?><p class="err" style="background:#efeaff;color:#3a2a99"><?= e($aviso) ?></p><?php endif; ?>
    <?php if ($b = lc_oauth_botones('entrar')): ?><?= $b ?><div class="o-sep">o con tu usuario</div><?php endif; ?>
    <form method="post"><input name="u" placeholder="Usuario" autocomplete="username" required>
    <input name="p" type="password" placeholder="Contraseña" autocomplete="current-password" required>
    <button class="btn" style="width:100%">Entrar</button></form>
    <p style="text-align:center;margin-top:12px;font-size:14px"><a href="recuperar.php" style="color:#5b45d6">¿Olvidaste tu contraseña?</a></p>
    <button type="button" class="instalar" data-instalar hidden style="margin-top:14px"><img src="assets/app/icono-192.png" alt=""><span><b>Instala la app en tu celular</b>Entra a tu agenda con un toque desde la pantalla de inicio.</span></button>
    <?php if (lc_registro_abierto($db)): ?><p style="text-align:center;margin-top:14px;font-size:14px;color:#6b6980">¿Tienes un negocio? <a href="registro.php" style="color:#7b5cff;font-weight:600">Crea tu cuenta gratis</a></p><?php endif; ?></div>
<?php else: ?>
  <div class="top"><img src="logo.svg?v=3" alt="lunicitas"><span class="neg"><b><?= e($neg['nombre']) ?></b> · <a href="<?= e($base . '/' . $neg['slug']) ?>" target="_blank">ver página</a> · <a href="?v=plan" class="plan-<?= $PLAN['clave'] ?>"><?= e($PLAN_NOMBRE[$PLAN['clave']]) ?><?= $PLAN['dias'] ? ': ' . $PLAN['dias'] . ' días' : '' ?></a></span><a class="noprint" href="?salir=1">Salir</a></div>
  <div class="tabs noprint">
    <?php foreach ($VISTAS as $k => $n): ?><a class="<?= $v === $k ? 'on' : '' ?>" href="<?= e(url($k)) ?>"><?= $n ?><?php if (in_array($k, ['ayuda', 'soporte'], true) && $SIN_LEER && $v !== $k): ?> <span class="nb" aria-label="mensajes sin leer"><?= $SIN_LEER ?></span><?php endif; ?></a><?php endforeach; ?>
  </div>
  <?php if ($aviso): ?><div class="aviso noprint"><?= e($aviso) ?></div><?php endif; ?>
  <?php if ($v === 'dia'): ?><button type="button" class="instalar noprint" data-instalar hidden><img src="assets/app/icono-192.png" alt=""><span><b>Instala LuniCitas en tu celular</b>Tu agenda a un toque, como cualquier otra app. Es gratis y no ocupa espacio.</span></button><?php endif; ?>
  <?php if ($PLAN['clave'] === 'prueba' && $v !== 'plan'): ?><div class="alerta noprint">⏳ Tu prueba gratis termina en <b><?= $PLAN['dias'] ?> <?= $PLAN['dias'] == 1 ? 'día' : 'días' ?></b>. Después tu cuenta se cierra y tu página deja de recibir reservas hasta que añadas tu tarjeta. <a href="?v=plan">Añadir tarjeta</a></div><?php endif; ?>
  <?php if ($porRecordar && !($v === 'dia' && $fecha === $manana) && in_array($v, ['dia', 'semana'], true)): ?>
    <div class="alerta noprint">🔔 Mañana hay <?= $porRecordar ?> cita<?= $porRecordar > 1 ? 's' : '' ?> sin recordar. <a href="<?= e(url('dia', $manana)) ?>">Enviar recordatorios</a></div>
  <?php endif; ?>
  <?php if ($ant): ?>
  <div class="nav"><a class="noprint" href="<?= e(url($v, $ant->format('Y-m-d'))) ?>">←</a><h2><?= e($titulo) ?></h2><a class="noprint" href="<?= e(url($v, $sig->format('Y-m-d'))) ?>">→</a></div>
  <?php endif; ?>

  <?php if ($v === 'dia'): $tot = 0; foreach ($citas as $c) $tot += (float)$c['precio']; ?>
    <div class="stats"><div class="stat"><b><?= count($citas) ?></b><small>Citas</small></div>
      <div class="stat"><b><?= count(array_filter($citas, function ($c) { return $c['estado'] === 'asistio'; })) ?></b><small>Asistieron</small></div>
      <div class="stat"><b><?= e(dinero($tot)) ?></b><small>Ingresos estimados</small></div></div>
    <?php if ($fecha === $manana && $porRecordar): ?><div class="alerta noprint">🔔 Toca <b>Recordar por WhatsApp</b> en cada cita: se abre WhatsApp con el mensaje ya escrito.</div><?php endif; ?>
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

  <?php elseif ($v === 'reporte' && !$pro): ?>
    <div class="box vacio"><h3>📊 El análisis del mes es del plan Pro</h3><p style="margin:8px 0 14px">Gráficas de ingresos, servicios, asistencia y días más ocupados, con descarga a Excel y PDF.</p><a class="btn" href="?v=plan">Ver planes</a></div>

  <?php elseif ($v === 'reporte'): $COL = ['#7b5cff', '#1fd1c1', '#ff7ab6', '#ffb547', '#4da3ff', '#9be15d']; $maxS = max(1, max($r['servicios'] ?: [0])); $iS = 0; ?>
    <div class="btns noprint"><a class="btn" href="<?= e(url('reporte')) ?>&export=csv">⬇ Descargar Excel</a>
      <button class="btn" onclick="window.print()">🖨 Imprimir / PDF</button></div>
    <div class="stats">
      <div class="stat"><b><?= $r['total'] ?></b><small>Citas del mes</small><br><span class="chip"><?= cambio($r['total'], $r['prev']['total']) ?> vs mes anterior</span></div>
      <div class="stat"><b><?= e(dinero($r['ingresos'])) ?></b><small>Ingresos (asistieron)</small><br><span class="chip"><?= cambio($r['ingresos'], $r['prev']['ingresos']) ?> vs mes anterior</span></div>
      <div class="stat"><b><?= $r['tasa'] ?>%</b><small>Tasa de asistencia</small></div>
    </div>
    <div class="box"><h3><?= $neg['tipo'] === 'barberia' ? '✂️ Tipos de corte del mes' : '✨ Servicios del mes' ?></h3>
      <?php foreach ($r['servicios'] as $sv => $n): $pct = $r['total'] ? round($n * 100 / $r['total']) : 0; $col = $COL[$iS++ % 6]; ?>
        <div class="rank"><div class="lin"><span><b><?= e($sv) ?></b></span><span><?= $n ?> · <?= $pct ?>% · <?= e(dinero($r['ingServ'][$sv] ?? 0)) ?></span></div>
          <div class="bar"><span style="width:<?= round($n * 100 / $maxS) ?>%;background:<?= $col ?>"></span></div></div>
      <?php endforeach; ?></div>
    <div class="grid2">
      <div class="box"><h3>Reparto de servicios</h3><div class="chart"><canvas id="cTipos"></canvas></div></div>
      <div class="box"><h3>Ingresos por servicio</h3><div class="chart"><canvas id="cIng"></canvas></div></div>
    </div>
    <div class="box"><h3>📈 Tendencia de los últimos 6 meses</h3><div class="chart" style="height:280px"><canvas id="cTend"></canvas></div></div>
    <div class="grid2">
      <div class="box"><h3>Asistencia</h3><div class="chart"><canvas id="cAsis"></canvas></div></div>
      <div class="box"><h3>Citas por <?= e(mb_strtolower($PERSONAL)) ?></h3><div class="chart"><canvas id="cBarb"></canvas></div></div>
    </div>
    <div class="box"><h3>📅 Días más ocupados</h3><div class="chart"><canvas id="cDias"></canvas></div>
      <?php if ($r['horas']): ?><p style="margin-top:10px;font-size:14px">⏰ Hora más pedida: <b><?= e(h12(array_key_first($r['horas']))) ?></b></p><?php endif; ?></div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    const COL=<?= js($COL) ?>;const M=<?= js($neg['moneda']) ?>;
    Chart.defaults.font.family='Poppins, sans-serif';Chart.defaults.color='#6b6980';Chart.defaults.maintainAspectRatio=false;
    const sv=<?= js(array_keys($r['servicios'])) ?>;
    new Chart(cTipos,{type:'doughnut',data:{labels:sv,datasets:[{data:<?= js(array_values($r['servicios'])) ?>,backgroundColor:COL,borderWidth:0}]},options:{cutout:'62%',plugins:{legend:{position:'bottom'}}}});
    new Chart(cIng,{type:'bar',data:{labels:sv,datasets:[{data:sv.map(s=>(<?= js($r['ingServ']) ?>)[s]||0),backgroundColor:COL,borderRadius:8}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>M+v}}}}});
    new Chart(cTend,{type:'bar',data:{labels:<?= js($r['tMeses']) ?>,datasets:sv.map((s,i)=>({label:s,data:<?= js($r['tend']) ?>[s],backgroundColor:COL[i%6],borderRadius:6}))},options:{plugins:{legend:{position:'bottom'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(cAsis,{type:'doughnut',data:{labels:['Asistió','No vino','Sin marcar'],datasets:[{data:[<?= (int)$r['asistio'] ?>,<?= (int)$r['no_asistio'] ?>,<?= (int)$r['pendiente'] ?>],backgroundColor:['#1fd1c1','#ff7ab6','#d9d6e6'],borderWidth:0}]},options:{cutout:'62%',plugins:{legend:{position:'bottom'}}}});
    new Chart(cBarb,{type:'bar',data:{labels:<?= js(array_keys($r['barberos'])) ?>,datasets:[{data:<?= js(array_values($r['barberos'])) ?>,backgroundColor:'#7b5cff',borderRadius:8}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(cDias,{type:'bar',data:{labels:<?= js($DC) ?>,datasets:[{data:<?= js(array_values($r['dias'])) ?>,backgroundColor:'#1fd1c1',borderRadius:8}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    </script>

  <?php elseif ($v === 'servicios'): ?>
    <p style="color:#6b6980;font-size:14px;margin-bottom:10px">Toca un servicio para editarlo. La duración decide cuánto tiempo se bloquea en la agenda.</p>
    <?php foreach ($servicios as $s): ?>
      <details class="item"><summary><div class="fila"><?= fotoPeq($s['foto'], $s['nombre']) ?><div class="t"><b><?= e($s['nombre']) ?></b><small><?= (int)$s['duracion'] ?> min</small></div><span class="price"><?= e(dinero($s['precio'])) ?></span></div></summary>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('servicios')) ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <label>Nombre</label><input name="nombre" maxlength="60" value="<?= e($s['nombre']) ?>" required>
          <div class="g3"><div><label>Precio</label><input name="precio" inputmode="decimal" value="<?= e((float)$s['precio']) ?>"></div>
            <div><label>Minutos</label><input name="duracion" type="number" min="5" max="600" step="5" value="<?= (int)$s['duracion'] ?>"></div>
            <div><label>Orden</label><input name="orden" type="number" value="<?= (int)$s['orden'] ?>"></div></div>
          <label>Foto (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
          <?php if ($s['foto']): ?><label class="ck"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label><?php endif; ?>
          <div class="btns"><button class="btn" name="accion" value="svc_guardar">Guardar</button>
            <button class="btn rojo" name="accion" value="svc_borrar" onclick="return confirm('¿Quitar este servicio?')">Quitar</button></div>
        </form></details>
    <?php endforeach; ?>
    <div class="box"><h3>➕ Añadir servicio</h3>
      <form method="post" enctype="multipart/form-data" action="<?= e(url('servicios')) ?>"><?= $csrf ?>
        <label>Nombre</label><input name="nombre" maxlength="60" placeholder="Ej. Corte infantil" required>
        <div class="g3"><div><label>Precio</label><input name="precio" inputmode="decimal" placeholder="10"></div>
          <div><label>Minutos</label><input name="duracion" type="number" min="5" max="600" step="5" value="30"></div>
          <div><label>Orden</label><input name="orden" type="number" value="<?= count($servicios) ?>"></div></div>
        <label>Foto (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        <button class="btn" name="accion" value="svc_guardar">Añadir</button></form></div>

  <?php elseif ($v === 'equipo'): ?>
    <p style="color:#6b6980;font-size:14px;margin-bottom:10px">Cada persona tiene su propia agenda. Si hay más de una, el cliente también puede elegir “Cualquiera”.</p>
    <?php if (!$equipo): ?><div class="alerta">Añade al menos una persona para que los clientes puedan reservar.</div><?php endif; ?>
    <?php foreach ($equipo as $m): ?>
      <details class="item"><summary><div class="fila"><?= fotoPeq($m['foto'], $m['nombre']) ?><div class="t"><b><?= e($m['nombre']) ?></b><small><?= e($m['especialidad']) ?></small></div></div></summary>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('equipo')) ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <label>Nombre</label><input name="nombre" maxlength="40" value="<?= e($m['nombre']) ?>" required>
          <label>Especialidad</label><input name="especialidad" maxlength="60" value="<?= e($m['especialidad']) ?>">
          <label>Orden</label><input name="orden" type="number" value="<?= (int)$m['orden'] ?>">
          <label>Foto (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
          <?php if ($m['foto']): ?><label class="ck"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label><?php endif; ?>
          <div class="btns"><button class="btn" name="accion" value="eq_guardar">Guardar</button>
            <button class="btn rojo" name="accion" value="eq_borrar" onclick="return confirm('¿Quitar a esta persona del equipo?')">Quitar</button></div>
        </form></details>
    <?php endforeach; ?>
    <?php if (true): ?>
    <div class="box"><h3>➕ Añadir <?= e(mb_strtolower($PERSONAL)) ?></h3>
      <form method="post" enctype="multipart/form-data" action="<?= e(url('equipo')) ?>"><?= $csrf ?>
        <label>Nombre</label><input name="nombre" maxlength="40" required>
        <label>Especialidad</label><input name="especialidad" maxlength="60" placeholder="Ej. Cortes modernos">
        <input type="hidden" name="orden" value="<?= count($equipo) ?>">
        <label>Foto (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        <button class="btn" name="accion" value="eq_guardar">Añadir</button></form></div>
    <?php endif; ?>

  <?php elseif ($v === 'negocio'): $NOMD = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']; ?>
    <div class="box"><h3>🔗 Tu enlace de reservas</h3><div class="enlace"><?= e($base . '/' . $neg['slug']) ?></div>
      <p style="font-size:13px;color:#8a88a0">Compártelo en Instagram, WhatsApp o Google. Tus clientes reservan desde ahí.</p></div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('negocio')) ?>"><?= $csrf ?>
    <div class="box"><h3>🏪 Datos del negocio</h3>
      <label>Nombre</label><input name="nombre" maxlength="80" value="<?= e($neg['nombre']) ?>" required>
      <div class="grid2"><div><label>Tipo de negocio</label><select name="tipo"><?php foreach (LC_TIPOS as $k => $n): ?><option value="<?= $k ?>" <?= $neg['tipo'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
        <div><label>Enlace (lo que va después de la barra)</label><input name="slug" maxlength="40" value="<?= e($neg['slug']) ?>" pattern="[a-z0-9\-]{3,40}"></div></div>
      <label>Dirección</label><input name="direccion" maxlength="120" value="<?= e($neg['direccion']) ?>">
      <div class="grid2"><div><label>Teléfono</label><input name="telefono" maxlength="20" value="<?= e($neg['telefono']) ?>"></div>
        <div><label>Código de país para WhatsApp (1 = PR/EE. UU.)</label><input name="prefijo" maxlength="4" value="<?= e($neg['prefijo']) ?>"></div></div>
      <label>Descripción</label><textarea name="descripcion" maxlength="300"><?= e($neg['descripcion']) ?></textarea>
      <div class="grid2"><div><label>Zona horaria</label><select name="zona"><?php foreach (array_unique(array_merge([$neg['zona']], $ZONAS)) as $z): ?><option <?= $neg['zona'] === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
        <div><label>Moneda (símbolo)</label><input name="moneda" maxlength="4" value="<?= e($neg['moneda']) ?>"></div></div>
      <label>Foto de portada (opcional)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
      <?php if ($neg['foto']): ?><label class="ck"><input type="checkbox" name="quitar_foto" value="1"> Quitar la foto actual</label><?php endif; ?>
    </div>
    <div class="box"><h3>🕒 Horario</h3>
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $i): $h = $neg['horario'][$i] ?? null; ?>
        <div class="hor"><label class="ck" style="margin:0"><input type="checkbox" name="abierto<?= $i ?>" value="1" <?= $h ? 'checked' : '' ?>> <?= $NOMD[$i] ?></label>
          <input type="time" name="abre<?= $i ?>" value="<?= e($h[0] ?? '09:00') ?>"><input type="time" name="cierra<?= $i ?>" value="<?= e($h[1] ?? '19:00') ?>">
          <div class="desc">Descanso <input type="time" name="di<?= $i ?>" value="<?= e($h[2] ?? '') ?>"><input type="time" name="df<?= $i ?>" value="<?= e($h[3] ?? '') ?>"></div></div>
      <?php endforeach; ?>
      <label>Cada cuántos minutos empieza una cita</label>
      <select name="intervalo"><?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?><option value="<?= $m ?>" <?= (int)$neg['intervalo'] === $m ? 'selected' : '' ?>><?= $m ?> minutos</option><?php endforeach; ?></select>
      <p style="font-size:13px;color:#8a88a0">Desmarca los días que cierras. El descanso (por ejemplo, el almuerzo) es opcional.</p>
    </div>
    <button class="btn" style="width:100%;margin-bottom:14px" name="accion" value="neg_guardar">Guardar cambios</button>
    </form>

    <div class="box"><h3>🌴 Días libres y vacaciones</h3>
      <?php if (!$libres): ?><p style="color:#8a88a0;font-size:14px;margin-bottom:10px">No hay días libres programados.</p><?php endif; ?>
      <?php foreach ($libres as $l): ?>
        <form method="post" action="<?= e(url('negocio')) ?>" class="fila" style="margin-bottom:8px"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <div class="t"><b><?= e(ucfirst($DIAS[date('w', strtotime($l['fecha']))]) . ' ' . date('j', strtotime($l['fecha'])) . ' de ' . $MESES[date('n', strtotime($l['fecha'])) - 1]) ?></b>
            <small><?= e($l['quien'] ? 'Solo ' . $l['quien'] : 'Todo el negocio') ?><?= $l['motivo'] ? ' · ' . e($l['motivo']) : '' ?></small></div>
          <button class="btn gris" name="accion" value="libre_del">Quitar</button></form>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('negocio')) ?>" style="margin-top:10px"><?= $csrf ?>
        <div class="grid2"><div><label>Desde</label><input type="date" name="fecha_libre" min="<?= $hoy ?>" required></div><div><label>Hasta (opcional)</label><input type="date" name="hasta" min="<?= $hoy ?>"></div></div>
        <div class="grid2"><div><label>¿Quién descansa?</label><select name="equipo_id"><option value="0">Todo el negocio (cerrado)</option>
          <?php foreach ($equipo as $m): ?><option value="<?= (int)$m['id'] ?>">Solo <?= e($m['nombre']) ?></option><?php endforeach; ?></select></div>
          <div><label>Motivo (opcional)</label><input name="motivo" maxlength="80" placeholder="Ej. Feriado"></div></div>
        <button class="btn" name="accion" value="libre_add">Añadir día libre</button></form></div>

    <?php $q = $db->prepare('SELECT id, proveedor, email FROM identidades WHERE negocio_id = ? ORDER BY id'); $q->execute([$neg['id']]); $ids = $q->fetchAll();
          $PROVN = ['google' => 'Google', 'microsoft' => 'Microsoft']; $sinClave = $neg['clave_hash'] === ''; ?>
    <?php if ($ids || lc_oauth_activos()): ?>
    <div class="box"><h3>🔑 Formas de entrar</h3>
      <p style="font-size:14px;color:#6b6980;margin-bottom:10px"><?= $sinClave ? 'Entras con tu cuenta conectada.' : 'Usuario: <b>' . e($neg['usuario']) . '</b> con tu contraseña.' ?> Conecta Google o Microsoft para entrar con un toque.</p>
      <?php foreach ($ids as $i): ?>
        <form method="post" action="<?= e(url('negocio')) ?>" class="fila" style="margin-bottom:8px"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
          <span style="flex:1;font-size:14px">✓ <b><?= e($PROVN[$i['proveedor']] ?? $i['proveedor']) ?></b> <?= e($i['email']) ?></span>
          <button class="btn gris" name="accion" value="id_quitar" style="padding:6px 12px;font-size:13px" onclick="return confirm('¿Desconectar esta cuenta?')">Quitar</button></form>
      <?php endforeach; ?>
      <?= lc_oauth_botones('conectar', 'Conectar') ?></div>
    <?php endif; ?>

    <div class="box"><h3>🔒 <?= $sinClave ? 'Crear una contraseña (opcional)' : 'Cambiar contraseña del portal' ?></h3>
      <?php if ($sinClave): ?><p style="font-size:14px;color:#6b6980;margin-bottom:10px">Así podrás entrar también con tu usuario <b><?= e($neg['usuario']) ?></b> si algún día no tienes acceso a tu cuenta conectada.</p><?php endif; ?>
      <form method="post" action="<?= e(url('negocio')) ?>"><?= $csrf ?>
        <div class="grid2"><?php if (!$sinClave): ?><div><label>Actual</label><input type="password" name="actual" autocomplete="current-password" required></div><?php endif; ?>
          <div><label>Nueva (mínimo 8)</label><input type="password" name="nueva" minlength="8" autocomplete="new-password" required></div></div>
        <button class="btn" name="accion" value="clave"><?= $sinClave ? 'Crear contraseña' : 'Cambiar' ?></button></form></div>

  <?php elseif ($v === 'plan'): $precio = lc_cfg('PLAN_PRECIO', '$20 al mes'); $pagado = $neg['plan'] === 'pro' && $neg['stripe_sub']; ?>
    <?php if ($neg['stripe_estado'] === 'past_due'): ?><div class="alerta">⚠️ No pudimos cobrar tu último pago. Stripe lo volverá a intentar; para no perder el plan Pro, toca <b>Administrar pago</b> y actualiza tu tarjeta.</div><?php endif; ?>
    <?php if ($super && ($al = lc_meta($db, 'stripe_alerta'))): ?><div class="alerta">Stripe: <?= e($al) ?>. Respóndelo en el Dashboard de Stripe (Pagos → Disputas o Radar). <form method="post" action="<?= e(url('plan')) ?>" style="display:inline"><?= $csrf ?><button class="btn gris" name="accion" value="alerta_ok" style="padding:4px 10px;font-size:12px">Ya lo revisé</button></form></div><?php endif; ?>
    <?php if (($_GET['pago'] ?? '') === 'ok'): ?><div class="aviso">¡Gracias! Stripe está confirmando tu pago. En unos segundos tu plan aparecerá como Pro; si no, recarga esta página.</div><?php endif; ?>
    <div class="box"><h3>Tu plan: <?= e($PLAN_NOMBRE[$PLAN['clave']]) ?></h3>
      <p style="font-size:14px;color:#6b6980">
      <?php if ($PLAN['clave'] === 'prueba'): ?>Te quedan <b><?= $PLAN['dias'] ?> <?= $PLAN['dias'] == 1 ? 'día' : 'días' ?></b> de prueba con todo incluido. Cuando termine, <b>tu cuenta se cierra</b> y tu página deja de recibir reservas hasta que añadas tu tarjeta. Tus datos no se pierden.
      <?php elseif ($PLAN['clave'] === 'cerrado'): ?><b style="color:#c0392b"><?= $neg['stripe_cliente'] ? 'Tu suscripción terminó' : 'Tu prueba gratis terminó' ?> y tu cuenta está cerrada.</b> Tu página no está recibiendo reservas. Añade tu tarjeta para activar Pro y todo vuelve a funcionar al instante, con tus citas, servicios y equipo como los dejaste.
      <?php elseif ($PLAN['clave'] === 'pro' && $pagado && $neg['stripe_cancela']): ?>Cancelaste tu suscripción: <b>no se te volverá a cobrar</b>. Sigues con Pro hasta el <b><?= e(date('j/n/Y', strtotime($neg['stripe_cancela']))) ?></b> y después pasas al plan gratis, sin perder tus citas.
      <?php elseif ($PLAN['clave'] === 'pro'): ?><?= $pagado ? 'Tu suscripción está activa' . ($neg['pagado_hasta'] ? ' y se renueva cerca del ' . e(date('j/n/Y', strtotime($neg['pagado_hasta']))) : '') . '. Puedes cancelarla cuando quieras.' : 'Tienes todas las funciones activas.' ?>
      <?php endif; ?></p></div>
    <div>
      <div class="box" style="outline:2px solid #7b5cff"><h3>Pro</h3><p class="price" style="font-size:22px"><?= e($precio) ?></p>
        <p style="font-size:14px;line-height:1.9">✓ Página de reservas con tu enlace<br>✓ Agenda día, semana y mes<br>✓ <b>Equipo sin límite</b><br>✓ Recordatorios por WhatsApp<br>✓ Análisis del mes, Excel y PDF<br>✓ Cancela cuando quieras, sin penalidad</p>
        <p style="font-size:12px;color:#8a88a0;margin:-4px 0 10px">Se cobra cada mes por adelantado con Stripe. <a href="terminos" target="_blank">Términos</a> · <a href="privacidad" target="_blank">Privacidad</a></p>
        <?php if (lc_stripe_activo()): ?>
          <form method="post" action="<?= e(url('plan')) ?>"><?= $csrf ?>
          <?php if ($pagado): ?><button class="btn gris" name="accion" value="portal" style="width:100%">Administrar pago y recibos</button>
            <?php if ($neg['stripe_cancela']): ?><button class="btn" name="accion" value="reactivar" style="width:100%;margin-top:8px">Seguir con Pro</button>
            <?php else: ?><button class="btn gris" name="accion" value="cancelar" style="width:100%;margin-top:8px;color:#c0392b" onclick="return confirm('¿Cancelar tu suscripción? No se te volverá a cobrar y tendrás Pro hasta el final del periodo que ya pagaste.')">Cancelar suscripción</button><?php endif; ?>
          <?php elseif ($PLAN['clave'] !== 'pro' || $neg['plan'] !== 'pro'): ?><button class="btn" name="accion" value="pagar" style="width:100%"><?= $PLAN['clave'] === 'cerrado' ? 'Añadir tarjeta y activar' : 'Añadir tarjeta ahora' ?></button>
            <?php if ($PLAN['clave'] === 'prueba'): ?><p style="font-size:12px;color:#8a88a0;margin-top:6px;text-align:center">No se te cobra hasta que termine tu prueba.</p><?php endif; ?><?php endif; ?>
          </form>
        <?php elseif ($PLAN['clave'] !== 'pro'): ?><p style="font-size:13px;color:#8a88a0">El pago en línea estará disponible muy pronto.</p><?php endif; ?>
      </div>
    </div>
    <div class="box"><h3>Correo para recibos</h3>
      <form method="post" action="<?= e(url('plan')) ?>" class="fila"><?= $csrf ?><input name="email" type="email" maxlength="120" value="<?= e($neg['email']) ?>" placeholder="tu@correo.com" style="margin:0;flex:1" required><button class="btn gris" name="accion" value="email">Guardar</button></form></div>
    <?php if ($super && !lc_stripe_activo()): ?>
    <div class="box"><h3>⚙️ Activar los pagos (solo tú ves esto)</h3>
      <ol style="font-size:14px;line-height:1.8;padding-left:18px">
        <li>Crea tu cuenta en <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">stripe.com</a>.</li>
        <li>En Stripe, crea un producto “LuniCitas Pro” con precio mensual y copia su ID de precio (<code>price_…</code>).</li>
        <li>En Desarrolladores → Claves de API, crea una <b>clave restringida</b> (<code>rk_…</code>) con permiso de escritura en <i>Checkout Sessions</i>, <i>Customer portal</i>, <i>Customers</i> y <i>Subscriptions</i>, y de lectura en <i>Charges</i>. Es más segura que la clave secreta.</li>
        <li>En Desarrolladores → Webhooks, añade el destino <code><?= e($base) ?>/stripe.php</code> con los eventos <code>checkout.session.completed</code>, <code>customer.subscription.created</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, <code>invoice.paid</code>, <code>invoice.payment_failed</code>, <code>charge.dispute.created</code> y <code>radar.early_fraud_warning.created</code>. Copia su secreto (<code>whsec_…</code>).</li>
        <li>Añade a <b>config.php</b> (fuera de public_html):<pre style="background:#1d1b2e;color:#e5e5f0;padding:10px;border-radius:10px;font-size:12px;overflow:auto;margin-top:6px">$STRIPE_SECRET = 'rk_test_…';
$STRIPE_PRICE = 'price_…';
$STRIPE_WEBHOOK_SECRET = 'whsec_…';
$PLAN_PRECIO = '$20 al mes';</pre></li>
        <li>Prueba con la tarjeta de prueba de Stripe y, cuando todo funcione, crea la misma clave restringida en modo real (<code>rk_live_…</code>) y un webhook real con su propio secreto.</li>
      </ol></div>
    <?php endif; ?>

  <?php elseif ($v === 'clientes'): include __DIR__ . '/modulos/v_clientes.php'; ?>
  <?php elseif ($v === 'caja'): include __DIR__ . '/modulos/v_caja.php'; ?>
  <?php elseif ($v === 'ayuda'): include __DIR__ . '/modulos/v_ayuda.php'; ?>
  <?php elseif ($v === 'soporte'): include __DIR__ . '/modulos/v_soporte.php'; ?>
  <?php elseif ($v === 'negocios'): ?>
    <?php if (lc_stripe_activo()) include __DIR__ . '/modulos/v_stripe.php'; else { ?><div class="alerta">💳 Stripe todavía <b>no está conectado</b>: faltan la clave y el precio en la configuración. Los negocios no pueden pagar hasta que se configuren.</div><?php } ?>
    <div class="box"><h3>🏪 Negocios en la plataforma</h3>
      <table class="lista"><tr><th>Negocio</th><th>Enlace</th><th>Citas</th><th>Plan</th><th></th></tr>
      <?php foreach ($lista as $n): ?>
        <tr><td><b><?= e($n['nombre']) ?></b><br><small style="color:#8a88a0"><?= e(LC_TIPOS[$n['tipo']] ?? $n['tipo']) ?> · <?= e($n['usuario']) ?></small></td>
          <td><a href="<?= e($base . '/' . $n['slug']) ?>" target="_blank">/<?= e($n['slug']) ?></a></td><td><?= (int)$n['citas'] ?></td>
          <td><form method="post" action="<?= e(url('negocios')) ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="accion" value="neg_plan">
            <?php $pn = lc_plan($n); ?><select name="plan" onchange="this.form.submit()" style="margin:0;padding:6px;font-size:13px"><?php foreach (['gratis' => 'Cerrada', 'prueba' => 'Prueba', 'pro' => 'Pro'] as $k => $t): ?><option value="<?= $k ?>" <?= $n['plan'] === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
            <small style="color:#8a88a0;display:block"><?= e($PLAN_NOMBRE[$pn['clave']]) ?><?= $pn['dias'] ? ' · ' . $pn['dias'] . ' d' : '' ?><?= $n['stripe_sub'] ? ' · Stripe' : '' ?></small></form></td>
          <td><?php if ((int)$n['id'] !== (int)$neg['id']): ?><form method="post" action="<?= e(url('negocios')) ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn gris" name="accion" value="neg_entrar">Administrar</button></form><?php else: ?><small>actual</small><?php endif; ?></td></tr>
      <?php endforeach; ?></table></div>
    <div class="box"><h3>📝 Registro público</h3>
      <form method="post" action="<?= e(url('negocios')) ?>" class="fila"><?= $csrf ?>
        <div class="t"><?php if (lc_registro_abierto($db)): ?>Abierto: cualquier negocio puede crear su cuenta en <a href="registro.php" target="_blank"><?= e($base) ?>/registro.php</a> con <?= LC_DIAS_PRUEBA ?> días de prueba.<?php else: ?>Cerrado: solo tú puedes crear negocios.<?php endif; ?></div>
        <button class="btn gris" name="accion" value="registro"><?= lc_registro_abierto($db) ? 'Cerrar' : 'Abrir' ?></button></form></div>
    <div class="box"><h3>➕ Crear negocio</h3>
      <p style="font-size:13px;color:#8a88a0;margin-bottom:10px">Se crea con servicios de ejemplo según el tipo. El dueño entra a este mismo portal con su usuario y contraseña.</p>
      <form method="post" action="<?= e(url('negocios')) ?>"><?= $csrf ?>
        <div class="grid2"><div><label>Nombre del negocio</label><input name="nombre" maxlength="80" required></div>
          <div><label>Tipo</label><select name="tipo"><?php foreach (LC_TIPOS as $k => $n): ?><option value="<?= $k ?>"><?= $n ?></option><?php endforeach; ?></select></div></div>
        <div class="grid2"><div><label>Enlace (opcional, se crea del nombre)</label><input name="slug" maxlength="40" placeholder="mi-salon"></div>
          <div><label>Primera persona del equipo</label><input name="persona" maxlength="40" placeholder="Ej. Ana"></div></div>
        <div class="grid2"><div><label>Usuario del dueño</label><input name="usuario" maxlength="40" required autocomplete="off"></div>
          <div><label>Contraseña (mínimo 8)</label><input name="clave" type="password" minlength="8" required autocomplete="new-password"></div></div>
        <button class="btn" name="accion" value="neg_crear">Crear negocio</button></form></div>
  <?php endif; ?>
<?php endif; ?>
<p style="text-align:center;font-size:13px;color:#6b6980;margin:20px 0" class="noprint"><a href="terminos" target="_blank" style="color:#7b5cff">Términos</a> · <a href="privacidad" target="_blank" style="color:#7b5cff">Privacidad</a></p>
</div></body></html>
