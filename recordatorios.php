<?php
// LuniCitas — recordatorios automáticos por WhatsApp (opcional). Licencia AGPL-3.0
//
// Envía un mensaje a cada cliente con cita mañana que aún no fue recordado.
// Usa la API oficial de WhatsApp Cloud (Meta). Necesitas en config.php:
//   $WA_TOKEN    = 'token permanente de tu app de Meta';
//   $WA_PHONE_ID = 'ID del número de teléfono de WhatsApp Business';
//   $WA_PLANTILLA = 'recordatorio_cita';   // plantilla aprobada con 4 variables: nombre, negocio, fecha y hora, servicio
//   $WA_IDIOMA   = 'es';                    // idioma de la plantilla
// En Hostinger: hPanel → Avanzado → Cron Jobs → cada hora:
//   php /home/USUARIO/domains/TU-DOMINIO/public_html/recordatorios.php
// Sin token, el portal sigue teniendo el botón "Recordar por WhatsApp" de un toque.

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib.php';
$db = lc_conectar();
if (!$db) exit("LuniCitas no está instalada.\n");
$token = lc_cfg('WA_TOKEN'); $phone = lc_cfg('WA_PHONE_ID');
if (!$token || !$phone) exit("Faltan \$WA_TOKEN y \$WA_PHONE_ID en config.php.\n");
$plantilla = lc_cfg('WA_PLANTILLA', 'recordatorio_cita'); $idioma = lc_cfg('WA_IDIOMA', 'es');

$enviados = 0;
foreach ($db->query('SELECT * FROM negocios WHERE activo = 1')->fetchAll() as $neg) {
  $manana = lc_ahora($neg)->modify('+1 day')->format('Y-m-d');
  $q = $db->prepare('SELECT * FROM citas WHERE negocio_id = ? AND fecha = ? AND recordado = 0');
  $q->execute([$neg['id'], $manana]);
  foreach ($q->fetchAll() as $c) {
    [$h, $m] = array_map('intval', explode(':', $c['hora']));
    $cuando = date('j/n', strtotime($c['fecha'])) . ' ' . (($h % 12) ?: 12) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($h < 12 ? ' a. m.' : ' p. m.');
    $cuerpo = ['messaging_product' => 'whatsapp', 'to' => lc_wa_numero($c['telefono'], $neg['prefijo']), 'type' => 'template',
      'template' => ['name' => $plantilla, 'language' => ['code' => $idioma], 'components' => [['type' => 'body', 'parameters' => [
        ['type' => 'text', 'text' => explode(' ', trim($c['nombre']))[0]], ['type' => 'text', 'text' => $neg['nombre']],
        ['type' => 'text', 'text' => $cuando], ['type' => 'text', 'text' => $c['servicio']]]]]]];
    $ch = curl_init("https://graph.facebook.com/v21.0/$phone/messages");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
      CURLOPT_HTTPHEADER => ["Authorization: Bearer $token", 'Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($cuerpo)]);
    $resp = curl_exec($ch); $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($codigo >= 200 && $codigo < 300) {
      $db->prepare('UPDATE citas SET recordado = 1 WHERE id = ?')->execute([$c['id']]); $enviados++;
    } else {
      fwrite(STDERR, "No se pudo enviar la cita {$c['id']}: $resp\n");
    }
  }
}
echo "Recordatorios enviados: $enviados\n";
