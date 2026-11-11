<?php
// LuniCitas — perfil de Apple (.mobileconfig) que instala la app en iPhone y iPad.
// Pone el ícono en la pantalla de inicio y abre a pantalla completa, sin la barra de Safari.
// No da ningún permiso sobre el teléfono: solo contiene un acceso directo (Web Clip) que la persona puede borrar cuando quiera.
//   perfil-ios.php?n=mi-barberia  → la página de reservas de ese negocio
//   perfil-ios.php?a=agenda       → la agenda del dueño
// Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
$db = lc_conectar();
if (!$db) { http_response_code(503); exit; }

$base = rtrim(lc_url_publica($db), '/');
$neg = isset($_GET['n']) ? lc_negocio($db, preg_replace('/[^a-z0-9-]/', '', (string)$_GET['n'])) : null;
$agenda = ($_GET['a'] ?? '') === 'agenda';
if ($agenda)  { $clave = 'agenda'; $nombre = 'LuniCitas'; $url = $base . '/admin.php'; $desc = 'Tu agenda de LuniCitas a un toque desde la pantalla de inicio.'; }
elseif ($neg) { $clave = $neg['slug']; $nombre = $neg['nombre']; $url = $base . '/' . $neg['slug']; $desc = 'Reserva tus citas en ' . $neg['nombre'] . ' con un toque desde la pantalla de inicio.'; }
else          { $clave = 'inicio'; $nombre = 'LuniCitas'; $url = $base . '/app'; $desc = 'LuniCitas a un toque desde la pantalla de inicio.'; }

$x = function ($s) { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
$uuid = function ($s) { $h = md5('lunicitas-perfil:' . $s); return strtoupper(substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12)); };
$id = 'com.lunicitas.app.' . preg_replace('/[^a-z0-9]/', '', $clave);
$etiqueta = mb_substr($nombre, 0, 24);
$icono = base64_encode((string)file_get_contents(__DIR__ . '/assets/app/apple-touch-icon.png'));

$plist = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>PayloadContent</key>
  <array>
    <dict>
      <key>FullScreen</key><true/>
      <key>Icon</key><data>' . $icono . '</data>
      <key>IsRemovable</key><true/>
      <key>Label</key><string>' . $x($etiqueta) . '</string>
      <key>Precomposed</key><true/>
      <key>URL</key><string>' . $x($url) . '</string>
      <key>PayloadDisplayName</key><string>' . $x($etiqueta) . '</string>
      <key>PayloadIdentifier</key><string>' . $x($id) . '.icono</string>
      <key>PayloadType</key><string>com.apple.webClip.managed</string>
      <key>PayloadUUID</key><string>' . $uuid($clave . ':icono') . '</string>
      <key>PayloadVersion</key><integer>1</integer>
    </dict>
  </array>
  <key>PayloadDisplayName</key><string>' . $x($etiqueta) . ' (app)</string>
  <key>PayloadDescription</key><string>' . $x($desc . ' Solo añade el ícono de la app; no cambia nada más en tu teléfono y lo puedes borrar cuando quieras en Ajustes > General > VPN y gestión de dispositivos.') . '</string>
  <key>PayloadOrganization</key><string>LuniCitas · Edukatenow Technology Center</string>
  <key>PayloadIdentifier</key><string>' . $x($id) . '</string>
  <key>PayloadRemovalDisallowed</key><false/>
  <key>PayloadType</key><string>Configuration</string>
  <key>PayloadUUID</key><string>' . $uuid($clave) . '</string>
  <key>PayloadVersion</key><integer>1</integer>
</dict>
</plist>
';

header('Content-Type: application/x-apple-aspen-config');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9-]/', '', $clave) . '.mobileconfig"');
header('Cache-Control: no-store');
echo $plist;
