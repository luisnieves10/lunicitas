<?php
// LuniCitas — entrar con Google o Microsoft (OpenID Connect, flujo de código con PKCE). Licencia AGPL-3.0
// Inicio:  oauth.php?p=google&para=entrar|registro|conectar
// Regreso: oauth.php?code=…&state=…   (dirección de redirección que se registra en Google y Microsoft)
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
lc_sesion();

function b64url($b) { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function volver($destino, $msg = '') { if ($msg) $_SESSION['aviso'] = $msg; header('Location: ' . $destino); exit; }
function entrarComo($negocioId) {
  global $db, $o;
  // Si empezó en la app de Android, se entra en la app con un pase de un solo uso (2 minutos) en vez de en este navegador.
  if (!empty($o['app'])) {
    $pase = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM app_pases WHERE expira < NOW()');
    $db->prepare('INSERT INTO app_pases (hash, negocio_id, expira) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))')->execute([hash('sha256', $pase), (int)$negocioId]);
    $abrir = 'intent://' . parse_url(lc_url_publica($db), PHP_URL_HOST) . '/app-entrar.php?t=' . $pase . '#Intent;scheme=https;package=com.lunicitas.app;end';
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Volver a LuniCitas</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;min-height:90vh;align-items:center;justify-content:center;text-align:center;padding:24px}'
       . 'a{display:inline-block;margin-top:16px;background:#7b5cff;color:#fff;padding:15px 28px;border-radius:14px;text-decoration:none;font-weight:600;font-size:17px}p{color:#5e5c73}</style></head>'
       . '<body><div><h1>✅ Listo</h1><p>Ya entraste. Toca el botón para volver a la app.</p><a href="' . htmlspecialchars($abrir) . '">Abrir LuniCitas</a></div>'
       . '<script>location.href=' . json_encode($abrir) . ';</script></body></html>';
    exit;
  }
  session_regenerate_id(true);
  $_SESSION['neg'] = (int)$negocioId; $_SESSION['super'] = false;
  volver('admin.php');
}
$redirect = lc_base() . '/oauth.php';
$activos = lc_oauth_activos();

// ── 1. Ir al proveedor ────────────────────────────────────
if (isset($_GET['p'])) {
  $p = (string)$_GET['p'];
  $para = in_array($_GET['para'] ?? '', ['entrar', 'registro', 'conectar'], true) ? $_GET['para'] : 'entrar';
  if (!isset($activos[$p])) volver('admin.php', 'Esa forma de entrar no está disponible.');
  if ($para === 'conectar' && empty($_SESSION['neg'])) volver('admin.php', 'Entra primero para conectar tu cuenta.');
  $verificador = b64url(random_bytes(32));
  if (!empty($_GET['app']) && $para === 'conectar') volver('admin.php', 'Para conectar tu cuenta de Google o Microsoft, entra a lunicitas.com desde el navegador.');
  $_SESSION['oauth'] = ['p' => $p, 'para' => $para, 'state' => b64url(random_bytes(24)), 'nonce' => b64url(random_bytes(24)),
                        'verificador' => $verificador, 't' => time(), 'app' => !empty($_GET['app'])];
  $cfg = $activos[$p]['cfg'];
  header('Location: ' . $activos[$p]['auth'] . '?' . http_build_query([
    'response_type' => 'code', 'client_id' => lc_cfg($cfg . '_CLIENT_ID'), 'redirect_uri' => $redirect,
    'scope' => 'openid email profile', 'state' => $_SESSION['oauth']['state'], 'nonce' => $_SESSION['oauth']['nonce'],
    'code_challenge' => b64url(hash('sha256', $verificador, true)), 'code_challenge_method' => 'S256',
    'prompt' => 'select_account',
  ]));
  exit;
}

// ── 2. Regreso del proveedor ──────────────────────────────
$o = $_SESSION['oauth'] ?? null;
unset($_SESSION['oauth']);
if (!$o || !hash_equals($o['state'], (string)($_GET['state'] ?? '')) || time() - $o['t'] > 600 || !isset($activos[$o['p']])) {
  volver('admin.php', 'El inicio de sesión caducó. Inténtalo otra vez.');
}
$origen = $o['para'] === 'conectar' ? 'admin.php?v=negocio' : ($o['para'] === 'registro' ? 'registro.php' : 'admin.php');
if (isset($_GET['error']) || empty($_GET['code'])) volver($origen, 'No se completó el inicio de sesión.');

$p = $o['p']; $prov = $activos[$p]; $clientId = (string)lc_cfg($prov['cfg'] . '_CLIENT_ID');
$ch = curl_init($prov['token']);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
  CURLOPT_POSTFIELDS => http_build_query([
    'grant_type' => 'authorization_code', 'code' => (string)$_GET['code'], 'redirect_uri' => $redirect,
    'client_id' => $clientId, 'client_secret' => (string)lc_cfg($prov['cfg'] . '_CLIENT_SECRET'), 'code_verifier' => $o['verificador'],
  ])]);
$tok = json_decode((string)curl_exec($ch), true); curl_close($ch);

// El id_token llega directo del proveedor por HTTPS (canal de servidor a servidor),
// así que se validan sus datos: emisor, destinatario, vigencia y nonce (OpenID Connect, sección 3.1.3.7).
$partes = explode('.', (string)($tok['id_token'] ?? ''));
$c = count($partes) === 3 ? json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true) : null;
$aud = (array)($c['aud'] ?? []);
$issOk = $p === 'google'
  ? in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
  : (!empty($c['tid']) && ($c['iss'] ?? '') === 'https://login.microsoftonline.com/' . $c['tid'] . '/v2.0');
if (!$c || !$issOk || !in_array($clientId, $aud, true) || (int)($c['exp'] ?? 0) < time() - 60
    || !hash_equals($o['nonce'], (string)($c['nonce'] ?? '')) || empty($c['sub'])) {
  volver($origen, 'No pudimos confirmar tu cuenta de ' . $prov['nombre'] . '. Inténtalo otra vez.');
}
$sub = (string)$c['sub'];
$email = mb_strtolower((string)($c['email'] ?? ($p === 'microsoft' ? ($c['preferred_username'] ?? '') : '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
$verificado = $p === 'google' && !empty($c['email_verified']) && $email !== '';
$nombre = mb_substr(trim((string)($c['given_name'] ?? $c['name'] ?? '')), 0, 40);

$q = $db->prepare('SELECT i.negocio_id, n.activo FROM identidades i JOIN negocios n ON n.id = i.negocio_id WHERE i.proveedor = ? AND i.sub = ?');
$q->execute([$p, $sub]); $ya = $q->fetch();

// Conectar a la cuenta abierta
if ($o['para'] === 'conectar') {
  $nid = (int)($_SESSION['neg'] ?? 0);
  if (!$nid) volver('admin.php', 'Entra primero para conectar tu cuenta.');
  if ($ya && (int)$ya['negocio_id'] !== $nid) volver($origen, 'Esa cuenta de ' . $prov['nombre'] . ' ya está conectada a otro negocio.');
  if (!$ya) $db->prepare('INSERT INTO identidades (negocio_id, proveedor, sub, email) VALUES (?,?,?,?)')->execute([$nid, $p, $sub, $email]);
  volver($origen, 'Listo: ya puedes entrar con ' . $prov['nombre'] . ($email ? ' (' . $email . ')' : '') . '.');
}

// Entrar o registrarse
if ($ya) {
  if (!(int)$ya['activo']) volver('admin.php', 'Esta cuenta está desactivada.');
  entrarComo($ya['negocio_id']);
}
// Google confirma que el correo es de esa persona: si un solo negocio tiene ese correo, se conecta solo.
// (Microsoft no lo garantiza para todas las cuentas, por eso ahí se conecta desde el portal.)
if ($verificado) {
  $q = $db->prepare('SELECT id FROM negocios WHERE LOWER(email) = ? AND activo = 1'); $q->execute([$email]); $ids = $q->fetchAll(PDO::FETCH_COLUMN);
  if (count($ids) === 1) {
    $db->prepare('INSERT INTO identidades (negocio_id, proveedor, sub, email) VALUES (?,?,?,?)')->execute([$ids[0], $p, $sub, $email]);
    entrarComo($ids[0]);
  }
}
if (lc_registro_abierto($db)) {
  $_SESSION['oauth_nuevo'] = ['p' => $p, 'sub' => $sub, 'email' => $email, 'nombre' => $nombre, 't' => time()];
  volver('registro.php', $o['para'] === 'entrar'
    ? 'No encontramos un negocio conectado a esa cuenta de ' . $prov['nombre'] . '. Crea tu cuenta aquí o, si ya tienes una, entra con tu usuario y conéctala en Negocio.'
    : '');
}
volver('admin.php', 'No hay ningún negocio conectado a esa cuenta de ' . $prov['nombre'] . '. Entra con tu usuario y contraseña y conéctala en la sección Negocio.');
