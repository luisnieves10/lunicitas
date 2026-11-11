<?php
// LuniCitas — funciones compartidas por api.php, admin.php, install.php y recordatorios.php
// Licencia AGPL-3.0

const LC_VERSION_BD = 8;
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
  // Claves de Google y Microsoft pueden ir aparte, en config-claves.php (junto a config.php o en esta carpeta).
  foreach ([dirname($ruta) . '/config-claves.php', __DIR__ . '/config-claves.php'] as $extra) if (is_file($extra)) { require $extra; break; }
  // Claves de Stripe guardadas desde el portal (Negocios → Conectar Stripe). Nunca van al repositorio.
  if (is_file(lc_ruta_stripe_claves($ruta))) require lc_ruta_stripe_claves($ruta);
  $GLOBALS['LC_CONFIG'] = get_defined_vars();
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  lc_migrar($db);
  return $db;
}

function lc_ruta_stripe_claves($ruta = null) { return dirname($ruta ?: (lc_ruta_config() ?: __FILE__)) . '/stripe-claves.php'; }

// Guarda las claves de Stripe en un archivo privado del servidor (permisos 600), sin pasar por el chat ni por Git.
function lc_guardar_stripe_claves(array $v) {
  $txt = "<?php\n// LuniCitas — claves de Stripe (privado, generado desde el portal). No compartir.\n";
  foreach ($v as $k => $val) if (preg_match('/^[A-Z_]+$/', $k)) $txt .= '$' . $k . ' = ' . var_export((string)$val, true) . ";\n";
  $dest = lc_ruta_stripe_claves(); $tmp = $dest . '.' . bin2hex(random_bytes(6)) . '.tmp';
  if (@file_put_contents($tmp, $txt, LOCK_EX) === false) return false;
  @chmod($tmp, 0600);
  if (!@rename($tmp, $dest)) { @unlink($tmp); return false; }
  if (function_exists('opcache_invalidate')) @opcache_invalidate($dest, true);
  return true;
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
    usuario VARCHAR(120) NOT NULL UNIQUE, clave_hash VARCHAR(255) NOT NULL,
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
  // Versión 8: clientes y club, caja y cierres, chat de soporte, recuperar contraseña
  $db->exec("CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, nombre VARCHAR(80) NOT NULL, telefono VARCHAR(20) NOT NULL,
    email VARCHAR(120) NOT NULL DEFAULT '', codigo VARCHAR(10) NOT NULL, referido_por INT NULL, fijo TINYINT NOT NULL DEFAULT 0,
    club TINYINT NOT NULL DEFAULT 0, nota VARCHAR(200) NOT NULL DEFAULT '', premios_usados INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (negocio_id, telefono), UNIQUE KEY (negocio_id, codigo), KEY (referido_por)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, fecha DATE NOT NULL, tipo VARCHAR(6) NOT NULL,
    concepto VARCHAR(80) NOT NULL, monto DECIMAL(10,2) NOT NULL, metodo VARCHAR(12) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (negocio_id, fecha)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS cierres (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, tipo VARCHAR(6) NOT NULL, desde DATE NOT NULL, hasta DATE NOT NULL,
    datos TEXT NOT NULL, creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (negocio_id, tipo, desde)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NULL, de VARCHAR(12) NOT NULL, tipo VARCHAR(10) NOT NULL DEFAULT 'chat',
    nombre VARCHAR(80) NOT NULL DEFAULT '', email VARCHAR(120) NOT NULL DEFAULT '', texto TEXT NOT NULL,
    leido TINYINT NOT NULL DEFAULT 0, creada TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (negocio_id, id)) CHARACTER SET utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS recuperar (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, hash CHAR(64) NOT NULL UNIQUE, expira DATETIME NOT NULL,
    usado TINYINT NOT NULL DEFAULT 0, KEY (negocio_id)) CHARACTER SET utf8mb4");
  foreach (['cliente_id' => 'INT NULL', 'descuento' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'motivo' => "VARCHAR(40) NOT NULL DEFAULT ''",
            'propina' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'metodo' => "VARCHAR(12) NOT NULL DEFAULT ''"] as $c => $def)
    if (!lc_columna($db, 'citas', $c)) $db->exec("ALTER TABLE citas ADD $c $def");
  if (!lc_columna($db, 'equipo', 'comision')) $db->exec('ALTER TABLE equipo ADD comision DECIMAL(5,2) NOT NULL DEFAULT 0');
  if (!lc_columna($db, 'negocios', 'promos')) $db->exec("ALTER TABLE negocios ADD promos TEXT NULL");

  // Versión 7: el usuario puede ser un correo; cuentas de Google o Microsoft conectadas a cada negocio
  if ($ver >= 1 && $ver < 7) $db->exec('ALTER TABLE negocios MODIFY usuario VARCHAR(120) NOT NULL');
  $db->exec("CREATE TABLE IF NOT EXISTS identidades (
    id INT AUTO_INCREMENT PRIMARY KEY, negocio_id INT NOT NULL, proveedor VARCHAR(20) NOT NULL, sub VARCHAR(255) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '', creada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (proveedor, sub), KEY (negocio_id)) CHARACTER SET utf8mb4");

  // Versión 6: ya no hay plan gratis para siempre. Los negocios que estaban en gratis o con la prueba vencida
  // reciben 3 días de aviso antes de que su cuenta se cierre.
  if ($ver >= 3 && $ver < 6) {
    $db->prepare("UPDATE negocios SET plan = 'prueba', prueba_hasta = ? WHERE plan = 'gratis' OR (plan = 'prueba' AND (prueba_hasta IS NULL OR prueba_hasta < CURDATE()))")
       ->execute([date('Y-m-d', strtotime('+' . LC_DIAS_PRUEBA . ' days'))]);
  }
  // Versión 8: crea la ficha de cada cliente a partir de sus citas anteriores (por teléfono).
  if ($ver >= 1 && $ver < 8) {
    $filas = $db->query("SELECT id, negocio_id, nombre, telefono FROM citas WHERE cliente_id IS NULL ORDER BY id DESC")->fetchAll();
    foreach ($filas as $c) {
      $cli = lc_cliente($db, ['id' => $c['negocio_id']], $c['nombre'], $c['telefono']);
      if ($cli) $db->prepare('UPDATE citas SET cliente_id = ? WHERE id = ?')->execute([$cli['id'], $c['id']]);
    }
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
function lc_stripe($ruta, $params = [], $metodo = 'POST', $clave = null) {
  $url = 'https://api.stripe.com/v1/' . $ruta;
  if ($metodo === 'GET' && $params) $url .= '?' . http_build_query($params);
  $ch = curl_init($url);
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_USERPWD => ($clave ?? lc_cfg('STRIPE_SECRET')) . ':',
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

// ── Entrar con Google o Microsoft (OpenID Connect) ──────
// Se activa con $GOOGLE_CLIENT_ID + $GOOGLE_CLIENT_SECRET y/o $MICROSOFT_CLIENT_ID + $MICROSOFT_CLIENT_SECRET en config.php.
const LC_OAUTH = [
  'google' => ['nombre' => 'Google', 'cfg' => 'GOOGLE',
    'auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token'],
  'microsoft' => ['nombre' => 'Microsoft', 'cfg' => 'MICROSOFT',
    'auth' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize', 'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token'],
];
function lc_oauth_activos() {
  $r = [];
  foreach (LC_OAUTH as $k => $p) if (lc_cfg($p['cfg'] . '_CLIENT_ID') && lc_cfg($p['cfg'] . '_CLIENT_SECRET')) $r[$k] = $p;
  return $r;
}
// Botones "Continuar con …". $para: entrar, registro o conectar.
function lc_oauth_botones($para, $texto = 'Continuar con') {
  $ico = [
    'google' => '<svg viewBox="0 0 48 48" width="20" height="20" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>',
    'microsoft' => '<svg viewBox="0 0 21 21" width="18" height="18" aria-hidden="true"><path fill="#f25022" d="M1 1h9v9H1z"/><path fill="#00a4ef" d="M1 11h9v9H1z"/><path fill="#7fba00" d="M11 1h9v9h-9z"/><path fill="#ffb900" d="M11 11h9v9h-9z"/></svg>',
  ];
  $html = '';
  foreach (lc_oauth_activos() as $k => $p)
    $html .= '<a class="oauth" href="oauth.php?p=' . $k . '&amp;para=' . $para . '">' . $ico[$k] . '<span>' . htmlspecialchars($texto . ' ' . $p['nombre']) . '</span></a>';
  return $html;
}
const LC_OAUTH_CSS = '.oauth{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;background:#fff;color:#1d1b2e;font-weight:600;font-size:15px;text-decoration:none;margin-bottom:8px}.oauth:hover{border-color:#7b5cff}.oauth:focus-visible{outline:3px solid #7b5cff;outline-offset:2px}.o-sep{display:flex;align-items:center;gap:10px;color:#8a88a0;font-size:13px;margin:10px 0 12px}.o-sep::before,.o-sep::after{content:"";flex:1;height:1px;background:#e4e2ee}';

// ── Cita en el calendario del cliente (.ics firmado) ────
function lc_cal_clave($db) {
  $k = lc_meta($db, 'clave_cal');
  if (!$k) { $k = bin2hex(random_bytes(32)); lc_meta_set($db, 'clave_cal', $k); }
  return $k;
}
// Datos de la cita para Google Calendar, Outlook y el archivo .ics (Apple, Android, Outlook).
function lc_cal_cita($db, $neg, $servicio, $con, $fecha, $hora, $duracion) {
  $z = new DateTimeZone($neg['zona'] ?: 'America/Puerto_Rico');
  $ini = new DateTime($fecha . ' ' . $hora, $z); $fin = (clone $ini)->modify('+' . (int)$duracion . ' minutes');
  $utc = new DateTimeZone('UTC');
  $c = ['t' => $servicio . ' – ' . $neg['nombre'], 'i' => $ini->setTimezone($utc)->format('Ymd\THis\Z'), 'f' => $fin->setTimezone($utc)->format('Ymd\THis\Z'),
        'l' => (string)$neg['direccion'], 'n' => 'Con ' . $con . ($neg['telefono'] ? '. Tel. ' . $neg['telefono'] : '')];
  $d = rtrim(strtr(base64_encode(json_encode($c, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
  $c['ics'] = 'calendario.php?d=' . $d . '&s=' . substr(hash_hmac('sha256', $d, lc_cal_clave($db)), 0, 32);
  return $c;
}

// ── Correo ──────────────────────────────────────────────
// Con $SMTP_HOST, $SMTP_USUARIO y $SMTP_CLAVE (en config-claves.php) envía por SMTP seguro (puerto 465);
// si no, usa mail() del hosting. $CORREO_DE es el remitente (ej. no-responder@lunicitas.com).
function lc_correo($para, $asunto, $texto) {
  $para = trim((string)$para);
  if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $para)) return false;
  $asunto = str_replace(["\r", "\n"], ' ', (string)$asunto);
  $de = (string)lc_cfg('CORREO_DE', lc_cfg('SMTP_USUARIO', 'no-responder@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'lunicitas.com')));
  $asuntoCod = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
  $cab = "From: =?UTF-8?B?" . base64_encode('LuniCitas') . "?= <$de>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
       . "Content-Transfer-Encoding: base64\r\nX-Mailer: LuniCitas";
  $cuerpo = chunk_split(base64_encode($texto));
  $host = lc_cfg('SMTP_HOST');
  if (!$host) { $ok = @mail($para, $asuntoCod, $cuerpo, $cab, '-f' . $de); if (!$ok) error_log('LuniCitas: mail() falló para ' . $para); return $ok; }
  $s = @stream_socket_client('ssl://' . $host . ':' . (int)lc_cfg('SMTP_PUERTO', 465), $en, $es, 15);
  if (!$s) { error_log("LuniCitas SMTP: no conecta ($es)"); return false; }
  stream_set_timeout($s, 15);
  $lee = function () use ($s) { $r = ''; while (($l = fgets($s, 515)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
  $cmd = function ($c, $esperado) use ($s, $lee) { if ($c !== null) fwrite($s, $c . "\r\n"); $r = $lee(); if ((int)substr($r, 0, 3) !== $esperado) throw new Exception(trim($r)); };
  try {
    $cmd(null, 220); $cmd('EHLO ' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'lunicitas.com'), 250);
    $cmd('AUTH LOGIN', 334); $cmd(base64_encode((string)lc_cfg('SMTP_USUARIO')), 334); $cmd(base64_encode((string)lc_cfg('SMTP_CLAVE')), 235);
    $cmd("MAIL FROM:<$de>", 250); $cmd("RCPT TO:<$para>", 250); $cmd('DATA', 354);
    $msg = "To: <$para>\r\nSubject: $asuntoCod\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . '@' . substr(strrchr($de, '@'), 1) . ">\r\n$cab\r\n\r\n$cuerpo";
    $cmd(str_replace("\r\n.", "\r\n..", $msg) . "\r\n.", 250); $cmd('QUIT', 221);
    fclose($s); return true;
  } catch (Exception $e) { error_log('LuniCitas SMTP: ' . $e->getMessage()); @fclose($s); return false; }
}
// Correo donde llegan los avisos de soporte (nunca se muestra en la web).
function lc_correo_soporte() { return (string)lc_cfg('SOPORTE_EMAIL', ''); } // se configura en config-claves.php

// ── Clientes, club y referidos ──────────────────────────
function lc_tel($t) { return substr(preg_replace('/[^0-9]/', '', (string)$t), -15); }
function lc_promos($neg) {
  $p = json_decode((string)($neg['promos'] ?? ''), true) ?: [];
  return $p + ['club' => 1, 'fijo_pct' => 10, 'fijo_visitas' => 4, 'ref_meta' => 3, 'ref_pct' => 20, 'bienvenida_pct' => 10];
}
function lc_codigo_nuevo($db, $negId) {
  $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  do { $c = ''; for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, 31)];
       $q = $db->prepare('SELECT 1 FROM clientes WHERE negocio_id = ? AND codigo = ?'); $q->execute([$negId, $c]); } while ($q->fetch());
  return $c;
}
// Busca o crea el cliente por teléfono. $club: se unió al club (gratis). $ref: código de quien lo recomendó.
function lc_cliente($db, $neg, $nombre, $telefono, $email = '', $club = false, $ref = '') {
  $tel = lc_tel($telefono); if (strlen($tel) < 7) return null;
  $q = $db->prepare('SELECT * FROM clientes WHERE negocio_id = ? AND telefono = ?'); $q->execute([$neg['id'], $tel]); $c = $q->fetch();
  if (!$c) {
    $por = null;
    if ($ref !== '') { $q = $db->prepare('SELECT id FROM clientes WHERE negocio_id = ? AND codigo = ?'); $q->execute([$neg['id'], strtoupper($ref)]); $por = $q->fetchColumn() ?: null; }
    $db->prepare('INSERT INTO clientes (negocio_id, nombre, telefono, email, codigo, referido_por, club) VALUES (?,?,?,?,?,?,?)')
       ->execute([$neg['id'], mb_substr($nombre, 0, 80), $tel, $email, lc_codigo_nuevo($db, $neg['id']), $por, $club ? 1 : 0]);
    $q = $db->prepare('SELECT * FROM clientes WHERE id = ?'); $q->execute([$db->lastInsertId()]); return $q->fetch();
  }
  if ($club && !$c['club']) $db->prepare('UPDATE clientes SET club = 1, email = IF(email = "", ?, email) WHERE id = ?')->execute([$email, $c['id']]);
  elseif ($email !== '' && $c['email'] === '') $db->prepare('UPDATE clientes SET email = ? WHERE id = ?')->execute([$email, $c['id']]);
  return array_merge($c, ['club' => $club ? 1 : $c['club']]);
}
// Recomendaciones que cuentan: clientes que llegaron con su código y ya vinieron al menos una vez.
function lc_referidos($db, $clienteId) {
  $q = $db->prepare("SELECT COUNT(*) FROM clientes r WHERE r.referido_por = ? AND EXISTS (SELECT 1 FROM citas c WHERE c.cliente_id = r.id AND c.estado = 'asistio')");
  $q->execute([$clienteId]); return (int)$q->fetchColumn();
}
// Descuento que corresponde hoy a un cliente: [porcentaje, motivo, usaPremio]
function lc_descuento_sugerido($db, $neg, $cliente) {
  if (!$cliente) return [0, '', false];
  $p = lc_promos($neg); $op = [];
  if ($p['ref_meta'] > 0 && $p['ref_pct'] > 0) {
    $premios = intdiv(lc_referidos($db, $cliente['id']), (int)$p['ref_meta']) - (int)$cliente['premios_usados'];
    if ($premios > 0) $op[] = [(float)$p['ref_pct'], 'Premio por recomendar', true];
  }
  $q = $db->prepare("SELECT COUNT(*) FROM citas WHERE cliente_id = ? AND estado = 'asistio'"); $q->execute([$cliente['id']]); $visitas = (int)$q->fetchColumn();
  if ($p['fijo_pct'] > 0 && ($cliente['fijo'] || ($p['fijo_visitas'] > 0 && $visitas >= $p['fijo_visitas']))) $op[] = [(float)$p['fijo_pct'], 'Cliente fijo', false];
  if ($p['bienvenida_pct'] > 0 && $cliente['referido_por'] && $visitas === 0) $op[] = [(float)$p['bienvenida_pct'], 'Bienvenida por recomendación', false];
  if (!$op) return [0, '', false];
  usort($op, function ($a, $b) { return $b[0] <=> $a[0]; });
  return $op[0];
}

// ── Caja: cuentas de un periodo ─────────────────────────
// Ingresos = servicios cobrados (precio − descuento) + ventas extra. Ganancia = ingresos − gastos − comisiones del equipo.
// Las propinas se muestran aparte: son del equipo.
function lc_caja($db, $neg, $desde, $hasta) {
  $r = ['desde' => $desde, 'hasta' => $hasta, 'citas' => 0, 'bruto' => 0, 'descuentos' => 0, 'servicios' => 0, 'propinas' => 0,
        'ventas' => 0, 'gastos' => 0, 'comisiones' => 0, 'metodos' => [], 'equipo' => []];
  $q = $db->prepare("SELECT c.precio, c.descuento, c.propina, c.metodo, c.equipo_id, c.barbero, e.nombre en, e.comision
                     FROM citas c LEFT JOIN equipo e ON e.id = c.equipo_id AND e.negocio_id = c.negocio_id
                     WHERE c.negocio_id = ? AND c.estado = 'asistio' AND c.fecha BETWEEN ? AND ?");
  $q->execute([$neg['id'], $desde, $hasta]);
  foreach ($q->fetchAll() as $c) {
    $neto = (float)$c['precio'] - (float)$c['descuento']; $m = $c['metodo'] ?: 'efectivo';
    $r['citas']++; $r['bruto'] += (float)$c['precio']; $r['descuentos'] += (float)$c['descuento']; $r['servicios'] += $neto; $r['propinas'] += (float)$c['propina'];
    $r['metodos'][$m] = ($r['metodos'][$m] ?? 0) + $neto + (float)$c['propina'];
    $k = (string)($c['equipo_id'] ?: $c['barbero']);
    if (!isset($r['equipo'][$k])) $r['equipo'][$k] = ['nombre' => $c['en'] ?: $c['barbero'], 'citas' => 0, 'neto' => 0, 'propinas' => 0, 'pct' => (float)$c['comision'], 'comision' => 0];
    $r['equipo'][$k]['citas']++; $r['equipo'][$k]['neto'] += $neto; $r['equipo'][$k]['propinas'] += (float)$c['propina'];
  }
  foreach ($r['equipo'] as &$e) { $e['comision'] = round($e['neto'] * $e['pct'] / 100, 2); $r['comisiones'] += $e['comision']; } unset($e);
  $q = $db->prepare('SELECT tipo, monto, metodo FROM movimientos WHERE negocio_id = ? AND fecha BETWEEN ? AND ?'); $q->execute([$neg['id'], $desde, $hasta]);
  foreach ($q->fetchAll() as $m) {
    $mt = $m['metodo'] ?: 'efectivo';
    if ($m['tipo'] === 'venta') { $r['ventas'] += (float)$m['monto']; $r['metodos'][$mt] = ($r['metodos'][$mt] ?? 0) + (float)$m['monto']; }
    else { $r['gastos'] += (float)$m['monto']; $r['metodos'][$mt] = ($r['metodos'][$mt] ?? 0) - (float)$m['monto']; }
  }
  $r['ingresos'] = $r['servicios'] + $r['ventas'];
  $r['ganancia'] = $r['ingresos'] - $r['gastos'] - $r['comisiones'];
  $r['equipo'] = array_values($r['equipo']);
  foreach (['bruto', 'descuentos', 'servicios', 'propinas', 'ventas', 'gastos', 'comisiones', 'ingresos', 'ganancia'] as $k) $r[$k] = round($r[$k], 2);
  return $r;
}

// Dirección pública oficial para los enlaces de los correos. Nunca se arma con el dominio que manda el navegador
// (evita que alguien falsifique el enlace de cambiar contraseña). Usa $URL_BASE de la configuración;
// si no existe, la que se guardó la primera vez que entró el administrador.
function lc_url_publica($db = null) {
  $u = (string)lc_cfg('URL_BASE');
  if ($u === '' && $db) $u = (string)lc_meta($db, 'url_base', '');
  return rtrim($u !== '' ? $u : lc_base(), '/');
}
