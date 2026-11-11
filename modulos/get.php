<?php
// LuniCitas — respuestas que no son páginas: mensajes nuevos del chat (JSON) y cierres en Excel (CSV). Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
if (($_GET['json'] ?? '') === 'chat' && !$super) {
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  $q = $db->prepare('SELECT id, de, texto, creada FROM mensajes WHERE negocio_id = ? AND id > ? ORDER BY id LIMIT 50');
  $q->execute([$neg['id'], (int)($_GET['desde'] ?? 0)]);
  $out = array_map(function ($m) { return ['id' => (int)$m['id'], 'de' => $m['de'], 'texto' => $m['texto'], 'hora' => date('j/n g:i a', strtotime($m['creada']))]; }, $q->fetchAll());
  if ($out) $db->prepare("UPDATE mensajes SET leido = 1 WHERE negocio_id = ? AND de = 'soporte'")->execute([$neg['id']]);
  echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}
if (($_GET['csv'] ?? '') === 'cierre') {
  $q = $db->prepare('SELECT * FROM cierres WHERE id = ? AND negocio_id = ?'); $q->execute([(int)($_GET['id'] ?? 0), $neg['id']]); $ci = $q->fetch();
  if (!$ci) { http_response_code(404); exit('No encontrado'); }
  $d = json_decode($ci['datos'], true) ?: [];
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="cierre-' . $ci['tipo'] . '-' . $ci['desde'] . '.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['Cierre de ' . ($ci['tipo'] === 'semana' ? 'semana' : 'mes'), $ci['desde'] . ' a ' . $ci['hasta'], csvSeguro($neg['nombre'])]);
  foreach (['citas' => 'Citas cobradas', 'bruto' => 'Servicios (precio de lista)', 'descuentos' => 'Descuentos', 'ventas' => 'Ventas extra', 'ingresos' => 'Ingresos',
            'gastos' => 'Gastos', 'comisiones' => 'Comisiones', 'ganancia' => 'Ganancia', 'propinas' => 'Propinas (del equipo)'] as $k => $t) fputcsv($o, [$t, $d[$k] ?? 0]);
  fputcsv($o, []); fputcsv($o, ['Forma de pago', 'Monto']);
  foreach (($d['metodos'] ?? []) as $m => $v) fputcsv($o, [$m, $v]);
  fputcsv($o, []); fputcsv($o, ['Persona', 'Citas', 'Servicios', 'Comisión %', 'Comisión', 'Propinas', 'Total a pagar']);
  foreach (($d['equipo'] ?? []) as $p) fputcsv($o, [csvSeguro($p['nombre']), $p['citas'], $p['neto'], $p['pct'], $p['comision'], $p['propinas'], $p['comision'] + $p['propinas']]);
  exit;
}
