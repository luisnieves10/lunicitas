<?php
// LuniCitas — manifiesto de la app de cada negocio, para que sus clientes instalen su página de reservas. Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$db = lc_conectar();
$neg = $db ? lc_negocio($db, preg_replace('/[^a-z0-9-]/', '', (string)($_GET['n'] ?? ''))) : null;
$nombre = $neg ? $neg['nombre'] : 'LuniCitas';
$inicio = $neg ? './' . $neg['slug'] : './';
echo json_encode([
  'id' => $inicio, 'name' => $nombre, 'short_name' => mb_substr($nombre, 0, 12),
  'description' => 'Reserva tu cita en ' . $nombre, 'lang' => 'es',
  'start_url' => $inicio, 'scope' => './', 'display' => 'standalone',
  'background_color' => '#f7f7fb', 'theme_color' => '#7b5cff',
  'icons' => [
    ['src' => 'assets/app/icono-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
    ['src' => 'assets/app/icono-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ['src' => 'assets/app/icono-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
