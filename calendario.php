<?php
// LuniCitas — descarga la cita como archivo de calendario (.ics) para Apple, Android y Outlook. Licencia AGPL-3.0
// Solo acepta citas firmadas por api.php al reservar, así nadie puede fabricar eventos con esta dirección.
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
$db = lc_conectar();
$d = (string)($_GET['d'] ?? ''); $s = (string)($_GET['s'] ?? '');
if (!$db || !preg_match('/^[A-Za-z0-9_-]{10,3000}$/', $d) || !hash_equals(substr(hash_hmac('sha256', $d, lc_cal_clave($db)), 0, 32), $s)) {
  http_response_code(404); exit('Cita no encontrada');
}
$c = json_decode(base64_decode(strtr($d, '-_', '+/')), true);
if (!is_array($c) || !preg_match('/^\d{8}T\d{6}Z$/', $c['i'] ?? '') || !preg_match('/^\d{8}T\d{6}Z$/', $c['f'] ?? '')) { http_response_code(404); exit('Cita no encontrada'); }
function ics($t) { return str_replace(["\\", ';', ',', "\r", "\n"], ['\\\\', '\;', '\\,', '', '\\n'], (string)$t); }
$lineas = [
  'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LuniCitas//Reservas//ES', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
  'BEGIN:VEVENT', 'UID:' . substr($s, 0, 24) . '@lunicitas', 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
  'DTSTART:' . $c['i'], 'DTEND:' . $c['f'], 'SUMMARY:' . ics($c['t'] ?? 'Cita'),
  'LOCATION:' . ics($c['l'] ?? ''), 'DESCRIPTION:' . ics($c['n'] ?? ''),
  'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . ics($c['t'] ?? 'Cita'), 'TRIGGER:-PT1H', 'END:VALARM',
  'END:VEVENT', 'END:VCALENDAR',
];
// Las líneas largas se doblan a 75 bytes, como pide el formato iCalendar.
$salida = '';
foreach ($lineas as $l) { $out = ''; while (strlen($l) > 74) { $corte = 74; while ($corte > 0 && (ord($l[$corte]) & 0xC0) === 0x80) $corte--; $out .= substr($l, 0, $corte) . "\r\n "; $l = substr($l, $corte); } $salida .= $out . $l . "\r\n"; }
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="cita.ics"');
header('Cache-Control: private, max-age=3600');
echo $salida;
