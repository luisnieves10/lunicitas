<?php
// LuniCitas — acciones de Clientes, Caja y Ayuda. Las incluye admin.php dentro del bloque POST (ya validó sesión y CSRF).
// Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
$METODOS = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'ath' => 'ATH Móvil', 'transferencia' => 'Transferencia'];
$num = function ($k, $min, $max) { return max($min, min($max, round((float)str_replace(',', '.', (string)($_POST[$k] ?? 0)), 2))); };

// ── Cobrar una cita (asistió) con descuento, propina y forma de pago ──
if ($a === 'cobrar') {
  $q = $db->prepare('SELECT * FROM citas WHERE id = ? AND negocio_id = ?'); $q->execute([$id, $nid]); $c = $q->fetch();
  if (!$c) ir($v);
  $cli = null;
  if ($c['cliente_id']) { $q = $db->prepare('SELECT * FROM clientes WHERE id = ? AND negocio_id = ?'); $q->execute([$c['cliente_id'], $nid]); $cli = $q->fetch() ?: null; }
  [$sugPct, $sugMotivo, $usaPremio] = lc_descuento_sugerido($db, $neg, $cli);
  $pct = $num('pct', 0, 100);
  $metodo = isset($METODOS[$_POST['metodo'] ?? '']) ? $_POST['metodo'] : 'efectivo';
  $motivo = $pct <= 0 ? '' : (abs($pct - $sugPct) < 0.01 ? $sugMotivo : 'Descuento');
  $db->prepare("UPDATE citas SET estado = 'asistio', descuento = ?, motivo = ?, propina = ?, metodo = ? WHERE id = ? AND negocio_id = ?")
     ->execute([round((float)$c['precio'] * $pct / 100, 2), $motivo, $num('propina', 0, 9999), $metodo, $id, $nid]);
  if ($cli && $usaPremio && $pct > 0 && abs($pct - $sugPct) < 0.01 && $c['estado'] !== 'asistio')
    $db->prepare('UPDATE clientes SET premios_usados = premios_usados + 1 WHERE id = ?')->execute([$cli['id']]);
  ir($v, null, 'Cobrado: ' . dinero((float)$c['precio'] * (1 - $pct / 100)) . ' con ' . $METODOS[$metodo] . '.');
}

// ── Ayuda: chat con soporte y sugerencias ─────────────
if (in_array($a, ['chat_enviar', 'sugerir'], true) && !$super) {
  $texto = mb_substr(trim(strip_tags((string)($_POST['texto'] ?? ''))), 0, 2000);
  $q = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE negocio_id = ? AND de = 'negocio' AND creada > (NOW() - INTERVAL 1 HOUR)"); $q->execute([$nid]);
  if (mb_strlen($texto) < 2) ir('ayuda');
  if ($q->fetchColumn() >= 30) ir('ayuda', null, 'Enviaste muchos mensajes seguidos. Espera un poco, por favor.');
  $tipo = $a === 'sugerir' ? 'sugerencia' : 'chat';
  $db->prepare("INSERT INTO mensajes (negocio_id, de, tipo, texto) VALUES (?, 'negocio', ?, ?)")->execute([$nid, $tipo, $texto]);
  lc_correo(lc_correo_soporte(), 'LuniCitas: ' . ($tipo === 'sugerencia' ? 'sugerencia' : 'mensaje') . ' de ' . $neg['nombre'],
    $neg['nombre'] . ' (' . $neg['email'] . ") escribió:\n\n$texto\n\nResponde en " . lc_url_publica($db) . '/admin.php?v=soporte&c=' . $nid);
  ir('ayuda', null, $tipo === 'sugerencia' ? '¡Gracias por tu idea! La revisaremos.' : '');
}
if ($a === 'sop_responder' && $super) {
  $texto = mb_substr(trim(strip_tags((string)($_POST['texto'] ?? ''))), 0, 2000);
  $q = $db->prepare('SELECT id, nombre, email FROM negocios WHERE id = ?'); $q->execute([$id]); $dest = $q->fetch();
  if (!$dest || mb_strlen($texto) < 2) ir('soporte');
  $db->prepare("INSERT INTO mensajes (negocio_id, de, tipo, texto) VALUES (?, 'soporte', 'chat', ?)")->execute([$id, $texto]);
  $db->prepare("UPDATE mensajes SET leido = 1 WHERE negocio_id = ? AND de = 'negocio'")->execute([$id]);
  lc_correo($dest['email'], 'LuniCitas te respondió', "Hola,\n\nSoporte de LuniCitas te respondió:\n\n$texto\n\nContesta desde tu portal: " . lc_url_publica($db) . '/admin.php?v=ayuda');
  header('Location: admin.php?v=soporte&c=' . $id); exit;
}
if ($a === 'sop_visitante' && $super) { // respuesta por correo a un mensaje del formulario de contacto
  $texto = mb_substr(trim(strip_tags((string)($_POST['texto'] ?? ''))), 0, 3000);
  $q = $db->prepare("SELECT * FROM mensajes WHERE id = ? AND negocio_id IS NULL AND de = 'visitante'"); $q->execute([$id]); $m = $q->fetch();
  if (!$m || mb_strlen($texto) < 2) ir('soporte');
  $ok = lc_correo($m['email'], 'Respuesta de LuniCitas', "Hola {$m['nombre']},\n\n$texto\n\n— Tu mensaje:\n{$m['texto']}\n\nLuniCitas");
  $db->prepare("INSERT INTO mensajes (negocio_id, de, tipo, nombre, email, texto) VALUES (NULL, 'soporte', 'respuesta', ?, ?, ?)")->execute([$m['nombre'], $m['email'], $texto]);
  $db->prepare('UPDATE mensajes SET leido = 1 WHERE id = ?')->execute([$id]);
  ir('soporte', null, $ok ? 'Respuesta enviada a ' . $m['email'] . '.' : 'No se pudo enviar el correo. Revisa la configuración de correo.');
}
if ($a === 'sop_leido' && $super) { $db->prepare("UPDATE mensajes SET leido = 1 WHERE id = ? AND negocio_id IS NULL")->execute([$id]); ir('soporte'); }

// ── Clientes y promociones ─────────────────────────────
if ($a === 'cli_guardar') {
  $db->prepare('UPDATE clientes SET nombre = ?, email = ?, nota = ?, fijo = ? WHERE id = ? AND negocio_id = ?')
     ->execute([p('nombre', 80) ?: 'Cliente', filter_var(p('email', 120), FILTER_VALIDATE_EMAIL) ?: '', p('nota', 200), empty($_POST['fijo']) ? 0 : 1, $id, $nid]);
  header('Location: admin.php?v=clientes&f=' . urlencode($_GET['f'] ?? '') . '#c' . $id); exit;
}
if ($a === 'cli_fijo') {
  $db->prepare('UPDATE clientes SET fijo = 1 - fijo WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]);
  header('Location: admin.php?v=clientes&f=' . urlencode($_GET['f'] ?? '') . '#c' . $id); exit;
}
if ($a === 'promos_guardar') {
  $pr = ['club' => empty($_POST['club']) ? 0 : 1, 'fijo_pct' => $num('fijo_pct', 0, 100), 'fijo_visitas' => (int)$num('fijo_visitas', 0, 100),
         'ref_meta' => (int)$num('ref_meta', 0, 100), 'ref_pct' => $num('ref_pct', 0, 100), 'bienvenida_pct' => $num('bienvenida_pct', 0, 100)];
  $db->prepare('UPDATE negocios SET promos = ? WHERE id = ?')->execute([json_encode($pr), $nid]);
  ir('clientes', null, 'Descuentos guardados.');
}

// ── Caja: ventas, gastos, comisiones y cierres ─────────
if ($a === 'mov_guardar') {
  $f = (string)($_POST['dia'] ?? ''); $tipo = ($_POST['tipo'] ?? '') === 'gasto' ? 'gasto' : 'venta';
  $monto = $num('monto', 0, 999999);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || $monto <= 0 || p('concepto', 80) === '') ir('caja', null, 'Escribe el concepto y el monto.');
  $db->prepare('INSERT INTO movimientos (negocio_id, fecha, tipo, concepto, monto, metodo) VALUES (?,?,?,?,?,?)')
     ->execute([$nid, $f, $tipo, p('concepto', 80), $monto, isset($METODOS[$_POST['metodo'] ?? '']) ? $_POST['metodo'] : 'efectivo']);
  header('Location: admin.php?v=caja&p=' . urlencode($_GET['p'] ?? 'semana') . '&fecha=' . urlencode($fecha)); exit;
}
if ($a === 'mov_borrar') {
  $db->prepare('DELETE FROM movimientos WHERE id = ? AND negocio_id = ?')->execute([$id, $nid]);
  header('Location: admin.php?v=caja&p=' . urlencode($_GET['p'] ?? 'semana') . '&fecha=' . urlencode($fecha)); exit;
}
if ($a === 'com_guardar') {
  foreach ((array)($_POST['com'] ?? []) as $eid => $pct)
    $db->prepare('UPDATE equipo SET comision = ? WHERE id = ? AND negocio_id = ?')->execute([max(0, min(100, (float)$pct)), (int)$eid, $nid]);
  header('Location: admin.php?v=caja&p=' . urlencode($_GET['p'] ?? 'semana') . '&fecha=' . urlencode($fecha)); exit;
}
if ($a === 'cierre_guardar') {
  $tipo = ($_POST['tipo'] ?? '') === 'mes' ? 'mes' : 'semana';
  $desde = (string)($_POST['desde'] ?? ''); $hasta = (string)($_POST['hasta'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) ir('caja');
  $datos = lc_caja($db, $neg, $desde, $hasta);
  $db->prepare('REPLACE INTO cierres (negocio_id, tipo, desde, hasta, datos) VALUES (?,?,?,?,?)')->execute([$nid, $tipo, $desde, $hasta, json_encode($datos)]);
  header('Location: admin.php?v=caja&p=' . $tipo . '&fecha=' . urlencode($desde) . '&cerrado=1'); exit;
}
