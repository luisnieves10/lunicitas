<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/../config.php';

function responder($codigo, $datos) { http_response_code($codigo); echo json_encode($datos); exit; }

try {
  $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $db->exec("CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio VARCHAR(60) NOT NULL,
    barbero VARCHAR(40) NOT NULL,
    fecha DATE NOT NULL,
    hora VARCHAR(5) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    creada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY una_cita (barbero, fecha, hora)
  ) CHARACTER SET utf8mb4");
} catch (Exception $e) { responder(500, ['error' => 'No se pudo conectar']); }

$SERVICIOS = ['Corte clásico','Corte + barba','Fade / degradado','Arreglo de barba'];
$BARBEROS  = ['Carlos','Miguel','Andrés','Cualquiera'];
$HORAS     = ['9:00','9:45','10:30','11:15','12:00','14:00','14:45','15:30','16:15','17:00','17:45','18:30'];

function fechaValida($f) {
  $d = DateTime::createFromFormat('Y-m-d', $f);
  if (!$d || $d->format('Y-m-d') !== $f) return false;
  $hoy = new DateTime('today'); $max = (new DateTime('today'))->modify('+60 days');
  return $d >= $hoy && $d <= $max;
}

// Horas ocupadas: api.php?fecha=2026-11-12&barbero=Carlos
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $fecha = $_GET['fecha'] ?? ''; $barbero = $_GET['barbero'] ?? '';
  if (!fechaValida($fecha) || !in_array($barbero, $BARBEROS, true)) responder(400, ['error' => 'Datos no válidos']);
  $q = $db->prepare('SELECT hora FROM citas WHERE fecha = ? AND barbero = ?');
  $q->execute([$fecha, $barbero]);
  responder(200, ['ocupadas' => $q->fetchAll(PDO::FETCH_COLUMN)]);
}

// Nueva reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) responder(400, ['error' => 'Datos no válidos']);
  $servicio = $in['servicio'] ?? ''; $barbero = $in['barbero'] ?? '';
  $fecha = $in['fecha'] ?? ''; $hora = $in['hora'] ?? '';
  $nombre = trim(strip_tags($in['nombre'] ?? ''));
  $telefono = preg_replace('/[^0-9+]/', '', $in['telefono'] ?? '');

  if (!in_array($servicio, $SERVICIOS, true) || !in_array($barbero, $BARBEROS, true)
      || !in_array($hora, $HORAS, true) || !fechaValida($fecha)
      || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80
      || strlen($telefono) < 7 || strlen($telefono) > 20) {
    responder(400, ['error' => 'Revisa los datos de tu reserva']);
  }

  $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'lunicitas');
  $q = $db->prepare('SELECT COUNT(*) FROM citas WHERE ip_hash = ? AND creada > (NOW() - INTERVAL 1 HOUR)');
  $q->execute([$ip]);
  if ($q->fetchColumn() >= 5) responder(429, ['error' => 'Demasiadas reservas, intenta más tarde']);

  try {
    $q = $db->prepare('INSERT INTO citas (servicio, barbero, fecha, hora, nombre, telefono, ip_hash) VALUES (?,?,?,?,?,?,?)');
    $q->execute([$servicio, $barbero, $fecha, $hora, $nombre, $telefono, $ip]);
  } catch (PDOException $e) {
    if ($e->getCode() == 23000) responder(409, ['error' => 'Esa hora acaba de ocuparse, elige otra']);
    responder(500, ['error' => 'No se pudo guardar']);
  }
  responder(201, ['ok' => true]);
}

responder(405, ['error' => 'Método no permitido']);
