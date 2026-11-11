<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/lib.php';

function responder($codigo, $datos) { http_response_code($codigo); echo json_encode($datos, JSON_UNESCAPED_UNICODE); exit; }

try { $db = lc_conectar(); } catch (Exception $e) { responder(500, ['error' => 'No se pudo conectar']); }
if (!$db) responder(503, ['error' => 'instalar']);

$entrada = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$neg = lc_negocio($db, preg_replace('/[^a-z0-9-]/', '', (string)($entrada['n'] ?? '')));
if (!$neg) responder(404, ['error' => 'Negocio no encontrado']);
date_default_timezone_set($neg['zona']);

function servicio($db, $neg, $id) {
  $q = $db->prepare('SELECT * FROM servicios WHERE id = ? AND negocio_id = ? AND activo = 1'); $q->execute([(int)$id, $neg['id']]);
  return $q->fetch() ?: null;
}
function equipoActivo($db, $neg, $lock = false) {
  $limite = lc_es_pro($neg) ? '' : ' LIMIT 1'; // plan gratis: una sola persona
  $q = $db->prepare('SELECT id, nombre FROM equipo WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id' . $limite . ($lock ? ' FOR UPDATE' : ''));
  $q->execute([$neg['id']]);
  return $q->fetchAll(PDO::FETCH_KEY_PAIR);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Horas libres: api.php?n=slug&fecha=2026-11-12&servicio=3&equipo=2 (0 = cualquiera)
  if (isset($_GET['fecha'])) {
    $s = servicio($db, $neg, $_GET['servicio'] ?? 0);
    $equipo = equipoActivo($db, $neg);
    $eid = (int)($_GET['equipo'] ?? 0);
    if (!$s || !lc_fecha_valida($neg, $_GET['fecha']) || ($eid && !isset($equipo[$eid]))) responder(400, ['error' => 'Datos no válidos']);
    $libres = lc_disponibles($db, $neg, $_GET['fecha'], (int)$s['duracion'], $eid ? [$eid] : array_keys($equipo));
    $todas = array_unique(array_merge([], ...array_values($libres)));
    sort($todas);
    responder(200, ['horas' => array_map('lc_hhmm', $todas)]);
  }

  // Información del negocio para pintar la página de reservas
  $q = $db->prepare('SELECT id, nombre, duracion, precio, foto FROM servicios WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id');
  $q->execute([$neg['id']]); $servicios = $q->fetchAll();
  foreach ($servicios as &$s) { $s['id'] = (int)$s['id']; $s['duracion'] = (int)$s['duracion']; $s['precio'] = (float)$s['precio']; } unset($s);
  $q = $db->prepare('SELECT id, nombre, especialidad, foto FROM equipo WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id' . (lc_es_pro($neg) ? '' : ' LIMIT 1'));
  $q->execute([$neg['id']]); $equipo = $q->fetchAll();
  foreach ($equipo as &$m) $m['id'] = (int)$m['id']; unset($m);
  $q = $db->prepare('SELECT fecha FROM dias_libres WHERE negocio_id = ? AND equipo_id IS NULL AND fecha >= CURDATE()');
  $q->execute([$neg['id']]);
  responder(200, [
    'negocio' => array_intersect_key($neg, array_flip(['slug', 'nombre', 'tipo', 'direccion', 'telefono', 'descripcion', 'foto', 'moneda', 'horario'])),
    'hoy' => lc_ahora($neg)->format('Y-m-d'),
    'servicios' => $servicios, 'equipo' => $equipo,
    'cerrado' => $q->fetchAll(PDO::FETCH_COLUMN),
    'registro' => lc_registro_abierto($db),
  ]);
}

// Nueva reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $s = servicio($db, $neg, $entrada['servicio'] ?? 0);
  $fecha = (string)($entrada['fecha'] ?? ''); $hora = lc_min($entrada['hora'] ?? '');
  $eid = (int)($entrada['equipo'] ?? 0);
  $nombre = trim(strip_tags((string)($entrada['nombre'] ?? '')));
  $telefono = preg_replace('/[^0-9+]/', '', (string)($entrada['telefono'] ?? ''));
  $idioma = in_array($entrada['idioma'] ?? '', ['es', 'en', 'pt'], true) ? $entrada['idioma'] : 'es';

  if (!$s || $hora === null || !lc_fecha_valida($neg, $fecha)
      || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80 || strlen($telefono) < 7 || strlen($telefono) > 20) {
    responder(400, ['error' => 'Revisa los datos de tu reserva']);
  }

  $ip = lc_ip();
  $q = $db->prepare('SELECT COUNT(*) FROM citas WHERE ip_hash = ? AND creada > (NOW() - INTERVAL 1 HOUR)');
  $q->execute([$ip]);
  if ($q->fetchColumn() >= 5) responder(429, ['error' => 'Demasiadas reservas, intenta más tarde']);

  try {
    $db->beginTransaction();
    $equipo = equipoActivo($db, $neg, true); // bloquea: dos reservas a la vez no pueden tomar la misma hora
    if ($eid && !isset($equipo[$eid])) { $db->rollBack(); responder(400, ['error' => 'Revisa los datos de tu reserva']); }
    $libres = lc_disponibles($db, $neg, $fecha, (int)$s['duracion'], $eid ? [$eid] : array_keys($equipo));
    $elegido = null;
    foreach ($libres as $id => $horas) if (in_array($hora, $horas, true)) { $elegido = $id; break; }
    if (!$elegido) { $db->rollBack(); responder(409, ['error' => 'Esa hora acaba de ocuparse, elige otra']); }
    $db->prepare('INSERT INTO citas (negocio_id, servicio, barbero, equipo_id, fecha, hora, duracion, precio, nombre, telefono, idioma, ip_hash)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$neg['id'], $s['nombre'], $equipo[$elegido], $elegido, $fecha, lc_hhmm($hora), (int)$s['duracion'], $s['precio'],
                  $nombre, $telefono, $idioma, $ip]);
    $db->commit();
  } catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    responder(500, ['error' => 'No se pudo guardar']);
  }
  responder(201, ['ok' => true, 'con' => $equipo[$elegido]]);
}

responder(405, ['error' => 'Método no permitido']);
