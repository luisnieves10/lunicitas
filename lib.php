<?php
// LuniCitas — funciones compartidas por api.php, admin.php, install.php y recordatorios.php
// Licencia AGPL-3.0

const LC_VERSION_BD = 6;
const LC_STRIPE_VERSION = '2026-10-28.endive'; // versión fija de la API de Stripe
const LC_DIAS_PRUEBA = 3; // días de prueba gratis; después la cuenta se cierra hasta que pague
const LC_TIPOS = ['barberia' => 'Barbería', 'salon' => 'Salón de belleza', 'estetica' => 'Estética / spa'];
const LC_PERSONAL = ['barberia' => 'Barbero', 'salon' => 'Estilista', 'estetica' => 'Especialista'];

// Busca config.php: primero fuera de public_html (más seguro), luego junto a la app.
function lc_ruta_config() {
  foreach ([dirname(__DIR__) . '/config.php', __DIR__ . '/config.php'] as $r) if (is_file($r)) return $r;
  return null;
}

function lc_conectar() {
  $ruta = lc_ruta_config();
  if (!$ruta) return null;
  require $ruta;
  $GLOBALS['LC_CONFIG'] = get_defined_vars();
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  lc_migrar($db);
  return $db;
}

function lc_cfg($clave, $defecto = null) { return $GLOBALS['LC_CONFIG'][$clave] ?? $defecto; }

function lc_columna($db, $tabla, $col) {
  return (bool)$db->query("SHOW COLUMNS FROM `$tabla` LIKE " . $db->quote($col))->fetch();
}

function lc_horario_defecto() {
  $h = [];
  for ($d = 0; $d < 7; $d++) $h[$d] = $d === 0 ? null : ['09:00', '19:00', '', ''];
  return $h;
}

function lc_servicios_defecto($tipo) {
  return [
    'barberia' => [['Corte clásico', 30, 12], ['Corte + barba', 45, 18], ['Fade / degradado', 40, 15], ['Arreglo de barba', 20, 8]],
    'salon'    => [['Corte y peinado', 60, 35], ['Tinte', 120, 70], ['Manicura', 45, 20], ['Pedicura', 60, 25]],
    'estetica' => [['Limpieza facial', 60, 45], ['Depilación', 30, 20], ['Masaje relajante', 60, 50], ['Diseño de cejas', 30, 15]],
  ][$tipo] ?? [];
}

// Crea o actualiza las tablas. Es seguro ejecutarla siempre: solo hace cambios si faltan.
function lc_migrar($db) {
  $db->exec('CREATE TABLE IF NOT EXISTS lc_meta (k VARCHAR(30) PRIMARY KEY, v TEXT) CHARACTER SET utf8mb4');
  $ver = (int)($db->query("SELECT v FROM lc_meta WHERE k = 'version'")->fetchColumn() ?: 0);
  if ($ver >= LC_VERSION_BD) return;
  // Si ya existía la tabla de citas, es la instalación original: se conserva como primer negocio.
  $legado = (bool)$db->query("SHOW TABLES LIKE 'citas'")->fetch();

  $db->exec("CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY, servicio VARCHAR(60) NOT NULL, barbero VARCHAR(40) NOT NULL,
    fecha DATE NOT NULL, hora VARCHAR(5) NOT NULL, nombre VARCHAR(80) NOT NULL, telefono VARCHAR(20) NOT NULL,
    ip_hash CHAR(64) NOT NULL, creada TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4");
  $db->exec('CREATE TABLE IF NOT EXISTS intentos (ip_hash CHAR(64) NOT NULL, t TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
  $db->exec("CREATE TABLE IF NOT EXISTS negocios (
    id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(40) NOT NULL UNIQUE, nombre VARCHAR(80) NOT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'barberia', direccion VARCHAR(120) NOT NULL DEFAULT '',
    telefono VARCHAR(20) NOT NULL DEFAULT '', prefijo VARCHAR(4) NOT NULL DEFAULT '1',
    descripcion VARCHAR(300) NOT NULL DEFAULT '', foto VARCHAR(255) NOT NULL DEFAULT '',
    usuario VARCHAR(40) NOT NULL UNIQUE, clave_hash VARCHAR(255) NOT NULL,
    zona VARCHAR(40) NOT NULL DEFAULT 'America/Puerto_Rico', moneda VARCHAR(4) NOT NULL DEFAULT '$',
    intervalo INT NOT NULL DEFAULT 15, horario TEXT NOT NULL, activo TINYINT NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS servicios (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, nombre VARCHAR(60) NOT NULL,
    duracion INT NOT NULL DEFAULT 30, precio DECIMAL(8,2) NOT NULL DEFAULT 0, foto VARCHAR(255) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0, activo TINYINT NOT NULL DEFAULT 1, KEY (negocio_id)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS equipo (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, nombre VARCHAR(40) NOT NULL,
    especialidad VARCHAR(60) NOT NULL DEFAULT '', foto VARCHAR(255) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0, activo TINYINT NOT NULL DEFAULT 1, KEY (negocio_id)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS dias_libres (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, fecha DATE NOT NULL, equipo_id INT NULL,
    motivo VARCHAR(80) NOT NULL DEFAULT '', KEY (negocio_id, fecha)) CHARACTER SET utf8mb4");

  // Planes y pagos (versión 3). Los negocios que ya existían quedan en Pro sin vencimiento.
  $planes = [
    'plan' => "VARCHAR(10) NOT NULL DEFAULT 'pro'", 'prueba_hasta' => 'DATE NULL', 'pagado_hasta' => 'DATE NULL',
    'email' => "VARCHAR(120) NOT NULL DEFAULT ''", 'stripe_cliente' => "VARCHAR(60) NOT NULL DEFAULT ''",
    'stripe_sub' => "VARCHAR(60) NOT NULL DEFAULT ''",
    'stripe_estado' => "VARCHAR(20) NOT NULL DEFAULT ''", // versión 4: active, trialing, past_due…
    'stripe_cancela' => 'DATE NULL',            // versión 5: día en que termina una suscripción cancelada
    'terminos_aceptados' => 'DATETIME NULL',    // versión 5: cuándo aceptó los términos y la privacidad
  ];
  foreach ($planes as $c => $def) if (!lc_columna($db, 'negocios', $c)) $db->exec("ALTER TABLE negocios ADD $c $def");

  // Columnas nuevas en citas
  $nuevas = [
    'estado' => "VARCHAR(12) NOT NULL DEFAULT 'pendiente'", 'negocio_id' => 'INT NOT NULL DEFAULT 1',
    'equipo_id' => 'INT NULL', 'duracion' => 'INT NOT NULL DEFAULT 30', 'precio' => 'DECIMAL(8,2) NOT NULL DEFAULT 0',
    'recordado' => 'TINYINT NOT NULL DEFAULT 0', 'idioma' => "VARCHAR(2) NOT NULL DEFAULT 'es'",
  ];
  foreach ($nuevas as $c => $def) if (!lc_columna($db, 'citas', $c)) $db->exec("ALTER TABLE citas ADD $c $def");
  // La regla vieja (una cita por barbero y hora) chocaba entre negocios; ahora se revisa por duración.
  if ($db->query("SHOW INDEX FROM citas WHERE Key_name = 'una_cita'")->fetch()) $db->exec('ALTER TABLE citas DROP INDEX una_cita');
  if (!$db->query("SHOW INDEX FROM citas WHERE Key_name = 'neg_fecha'")->fetch()) $db->exec('ALTER TABLE citas ADD INDEX neg_fecha (negocio_id, fecha)');

  // Primer negocio: conserva la barbería que ya estaba funcionando y su usuario del portal.
  if ($legado && !$db->query('SELECT COUNT(*) FROM negocios')->fetchColumn()) {
    $usuario = lc_cfg('ADMIN_USER', 'admin');
    $hash = lc_cfg('ADMIN_HASH') ?: password_hash((string)lc_cfg('ADMIN_PASS', bin2hex(random_bytes(8))), PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO negocios (id, slug, nombre, tipo, direccion, telefono, descripcion, usuario, clave_hash, horario)
                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute(['elestilo', 'Barbería El Estilo', 'barberia', 'Calle Principal 123', '',
                  'Cortes clásicos y modernos, barba y más. Reserva en menos de un minuto, sin crear cuenta.',
                  $usuario, $hash, json_encode(lc_horario_defecto())]);
    $precios = [];
    foreach (lc_servicios_defecto('barberia') as $i => [$n, $d, $p]) {
      $db->prepare('INSERT INTO servicios (negocio_id, nombre, duracion, precio, orden) VALUES (1, ?, ?, ?, ?)')->execute([$n, $d, $p, $i]);
      $precios[$n] = [$d, $p];
    }
    $equipo = [['Carlos', 'Especialista en fades', 'https://i.pravatar.cc/200?img=12'],
               ['Miguel', 'Cortes clásicos', 'https://i.pravatar.cc/200?img=33'],
               ['Andrés', 'Barbas y diseños', 'https://i.pravatar.cc/200?img=59']];
    foreach ($equipo as $i => [$n, $e, $f]) {
      $db->prepare('INSERT INTO equipo (negocio_id, nombre, especialidad, foto, orden) VALUES (1, ?, ?, ?, ?)')->execute([$n, $e, $f, $i]);
      $db->prepare('UPDATE citas SET equipo_id = ? WHERE barbero = ? AND equipo_id IS NULL')->execute([$db->lastInsertId(), $n]);
    }
    foreach ($precios as $n => [$d, $p]) {
      $db->prepare('UPDATE citas SET duracion = ?, precio = ? WHERE servicio = ? AND precio = 0')->execute([$d, $p, $n]);
    }
  }
  // Versión 6: ya no hay plan gratis para siempre. Los negocios que estaban en gratis o con la prueba vencida
  // reciben 3 días de aviso antes de que su cuenta se cierre.
  if ($ver >= 3 && $ver < 6) {
    $db->prepare("UPDATE negocios SET plan = 'prueba', prueba_hasta = ? WHERE plan = 'gratis' OR (plan = 'prueba' AND (prueba_hasta IS NULL OR prueba_hasta < CURDATE()))")
       ->execute([date('Y-m-d', strtotime('+' . LC_DIAS_PRUEBA . ' days'))]);
  }
  $db->prepare("REPLACE INTO lc_meta (k, v) VALUES ('version', ?)")->execute([LC_VERSION_BD]);
}

function lc_negocio($db, $slug = '') {
  if ($slug !== '') {
    $q = $db->prepare('SELECT * FROM negocios WHERE slug = ? AND activo = 1'); $q->execute([$slug]);
  } else {
    $q = $db->query('SELECT * FROM negocios WHERE activo = 1 ORDER BY id LIMIT 1');
  }
  $n = $q->fetch();
  if ($n) $n['horario'] = json_decode($n['horario'], true) ?: lc_horario_defecto();
  return $n ?: null;
}

function lc_min($h) { if (!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$h, $m)) return null; return (int)$m[1] * 60 + (int)$m[2]; }
function lc_hhmm($m) { return intdiv($m, 60) . ':' . str_pad($m % 60, 2, '0', STR_PAD_LEFT); }

function lc_slug($s) {
  $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s);
  return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

function lc_ahora($neg) { return new DateTime('now', new DateTimeZone($neg['zona'] ?: 'America/Puerto_Rico')); }

function lc_fecha_valida($neg, $f) {
  $d = DateTime::createFromFormat('!Y-m-d', (string)$f, new DateTimeZone($neg['zona']));
  if (!$d || $d->format('Y-m-d') !== $f) return false;
  $hoy = lc_ahora($neg)->format('Y-m-d');
  $max = lc_ahora($neg)->modify('+60 days')->format('Y-m-d');
  return $f >= $hoy && $f <= $max;
}

// Horas libres para un servicio en una fecha. Devuelve [equipo_id => [minutos de inicio]].
function lc_disponibles($db, $neg, $fecha, $duracion, $equipoIds) {
  $dia = (int)date('w', strtotime($fecha));
  $h = $neg['horario'][$dia] ?? null;
  $res = array_fill_keys($equipoIds, []);
  if (!$h || lc_min($h[0]) === null || lc_min($h[1]) === null) return $res;
  $q = $db->prepare('SELECT equipo_id FROM dias_libres WHERE negocio_id = ? AND fecha = ?');
  $q->execute([$neg['id'], $fecha]);
  $libres = $q->fetchAll(PDO::FETCH_COLUMN);
  if (in_array(null, $libres, true)) return $res;

  $abre = lc_min($h[0]); $cierra = lc_min($h[1]);
  $di = lc_min($h[2] ?? ''); $df = lc_min($h[3] ?? '');
  $paso = max(5, (int)$neg['intervalo']);
  $ahora = lc_ahora($neg);
  $limite = $ahora->format('Y-m-d') === $fecha ? (int)$ahora->format('G') * 60 + (int)$ahora->format('i') : -1;

  $q = $db->prepare('SELECT equipo_id, hora, duracion FROM citas WHERE negocio_id = ? AND fecha = ? AND equipo_id IS NOT NULL');
  $q->execute([$neg['id'], $fecha]);
  $ocupado = [];
  foreach ($q as $c) $ocupado[$c['equipo_id']][] = [lc_min($c['hora']), lc_min($c['hora']) + (int)$c['duracion']];

  foreach ($equipoIds as $eid) {
    if (in_array($eid, $libres)) continue;
    for ($t = $abre; $t + $duracion <= $cierra; $t += $paso) {
      if ($t <= $limite) continue;
      if ($di !== null && $df !== null && $t < $df && $t + $duracion > $di) continue;
      foreach ($ocupado[$eid] ?? [] as [$a, $b]) if ($t < $b && $t + $duracion > $a) continue 2;
      $res[$eid][] = $t;
    }
  }
  return $res;
}

function lc_ip() { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas'); }

// Número para WhatsApp con código de país
function lc_wa_numero($tel, $prefijo) {
  $d = preg_replace('/[^0-9]/', '', $tel);
  $p = preg_replace('/[^0-9]/', '', (string)$prefijo);
  if ($p !== '' && strpos($tel, '+') !== 0 && strlen($d) <= 10 && strpos($d, $p) !== 0) $d = $p . $d;
  return $d;
}

function lc_mensaje_recordatorio($c, $neg) {
  $DIAS = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
  [$h, $m] = array_map('intval', explode(':', $c['hora']));
  $hora = (($h % 12) ?: 12) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' a. m.' : ' p. m.');
  $dia = $DIAS[(int)date('w', strtotime($c['fecha']))] . ' ' . (int)date('j', strtotime($c['fecha']));
  $nombre = explode(' ', trim($c['nombre']))[0];
  return "Hola $nombre, te recordamos tu cita en {$neg['nombre']} el $dia a las $hora ({$c['servicio']} con {$c['barbero']}). ¡Te esperamos! Si no puedes venir, avísanos por aquí.";
}

function lc_crear_negocio($db, $nombre, $tipo, $slug, $usuario, $clave, $persona) {
  $db->prepare('INSERT INTO negocios (slug, nombre, tipo, usuario, clave_hash, horario) VALUES (?,?,?,?,?,?)')
     ->execute([$slug, $nombre, $tipo, $usuario, password_hash($clave, PASSWORD_DEFAULT), json_encode(lc_horario_defecto())]);
  $id = (int)$db->lastInsertId();
  foreach (lc_servicios_defecto($tipo) as $i => [$n, $du, $pr])
    $db->prepare('INSERT INTO servicios (negocio_id, nombre, duracion, precio, orden) VALUES (?,?,?,?,?)')->execute([$id, $n, $du, $pr, $i]);
  if ($persona !== '') $db->prepare('INSERT INTO equipo (negocio_id, nombre) VALUES (?, ?)')->execute([$id, $persona]);
  return $id;
}

// ── Planes ──────────────────────────────────────────────
// prueba (LC_DIAS_PRUEBA días) y pro: todo. Si no, la cuenta está cerrada: el dueño solo ve la pantalla de pago
// y la página de reservas deja de aceptar citas. En la base de datos, plan = 'gratis' significa cerrada.
function lc_plan($neg) {
  $hoy = date('Y-m-d');
  if (($neg['plan'] ?? 'pro') === 'pro' && (empty($neg['pagado_hasta']) || $neg['pagado_hasta'] >= $hoy)) return ['clave' => 'pro', 'dias' => null];
  if (($neg['plan'] ?? '') === 'prueba' && !empty($neg['prueba_hasta']) && $neg['prueba_hasta'] >= $hoy)
    return ['clave' => 'prueba', 'dias' => max(1, (int)round((strtotime($neg['prueba_hasta']) - strtotime($hoy)) / 86400))];
  return ['clave' => 'cerrado', 'dias' => null];
}
function lc_es_pro($neg) { return lc_plan($neg)['clave'] !== 'cerrado'; } // cuenta abierta (prueba o pro)

function lc_meta($db, $k, $defecto = null) {
  $q = $db->prepare('SELECT v FROM lc_meta WHERE k = ?'); $q->execute([$k]); $v = $q->fetchColumn();
  return $v === false ? $defecto : $v;
}
function lc_meta_set($db, $k, $v) { $db->prepare('REPLACE INTO lc_meta (k, v) VALUES (?, ?)')->execute([$k, (string)$v]); }
function lc_registro_abierto($db) { return lc_meta($db, 'registro', '1') === '1'; }

function lc_sesion() {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
  session_start();
}

function lc_base() {
  return (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
}

// ── Stripe (sin librerías). Se activa con $STRIPE_SECRET y $STRIPE_PRICE en config.php ──
function lc_stripe_activo() { return (bool)lc_cfg('STRIPE_SECRET') && (bool)lc_cfg('STRIPE_PRICE'); }

// $STRIPE_SECRET puede ser una clave restringida (rk_…, recomendada) o secreta (sk_…).
function lc_stripe($ruta, $params = [], $metodo = 'POST') {
  $url = 'https://api.stripe.com/v1/' . $ruta;
  if ($metodo === 'GET' && $params) $url .= '?' . http_build_query($params);
  $ch = curl_init($url);
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERPWD => lc_cfg('STRIPE_SECRET') . ':',
    CURLOPT_HTTPHEADER => ['Stripe-Version: ' . LC_STRIPE_VERSION]];
  if ($metodo === 'POST') $op += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($params)];
  curl_setopt_array($ch, $op);
  $r = json_decode((string)curl_exec($ch), true); curl_close($ch);
  return is_array($r) ? $r : ['error' => ['message' => 'Sin respuesta de Stripe']];
}

// Comprueba la firma Stripe-Signature (t=...,v1=...) de un aviso (webhook).
function lc_stripe_firma_valida($payload, $cabecera, $secreto, $tolerancia = 300) {
  if (!$secreto || !$cabecera) return false;
  $t = null; $firmas = [];
  foreach (explode(',', $cabecera) as $parte) {
    [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
    if ($k === 't') $t = $v; elseif ($k === 'v1') $firmas[] = $v;
  }
  if (!$t || !ctype_digit($t) || abs(time() - (int)$t) > $tolerancia) return false;
  $esperada = hash_hmac('sha256', $t . '.' . $payload, $secreto);
  foreach ($firmas as $f) if (hash_equals($esperada, $f)) return true;
  return false;
}
