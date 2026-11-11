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

// ── Conectar Stripe (solo el administrador): guarda las claves en un archivo privado del servidor ──
if ($a === 'stripe_claves' && $super) {
  $sec = trim((string)($_POST['stripe_secret'] ?? '')); $wh = trim((string)($_POST['stripe_whsec'] ?? ''));
  $pr = trim((string)($_POST['stripe_price'] ?? '')) ?: (string)lc_cfg('STRIPE_PRICE');
  $sec = $sec !== '' ? $sec : (string)lc_cfg('STRIPE_SECRET'); $wh = $wh !== '' ? $wh : (string)lc_cfg('STRIPE_WEBHOOK_SECRET');
  if (!preg_match('/^[rs]k_(live|test)_[A-Za-z0-9]{20,250}$/', $sec)) ir('negocios', null, 'La clave de Stripe no tiene el formato correcto (empieza con rk_live_…).');
  if ($wh !== '' && !preg_match('/^whsec_[A-Za-z0-9]{20,200}$/', $wh)) ir('negocios', null, 'El secreto del webhook no tiene el formato correcto (empieza con whsec_…).');
  if (!preg_match('/^price_[A-Za-z0-9]{8,80}$/', $pr)) ir('negocios', null, 'El precio debe empezar con price_….');
  $prueba = lc_stripe('prices/' . rawurlencode($pr), [], 'GET', $sec);
  if (!empty($prueba['error'])) ir('negocios', null, 'Stripe rechazó la clave o el precio: ' . mb_substr((string)($prueba['error']['message'] ?? ''), 0, 160));
  $ok = lc_guardar_stripe_claves(['STRIPE_SECRET' => $sec, 'STRIPE_PRICE' => $pr, 'STRIPE_WEBHOOK_SECRET' => $wh,
    'PLAN_PRECIO' => '$' . rtrim(rtrim(number_format(($prueba['unit_amount'] ?? 2000) / 100, 2), '0'), '.') . ' al mes']);
  ir('negocios', null, $ok ? '✅ Claves de Stripe guardadas en el servidor.' : 'No se pudo escribir el archivo de claves en el servidor.');
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

// ── Apartar una cita desde el portal (cliente de la lista o persona sin inscribir) ──
if ($a === 'cita_nueva') {
  $volver = function ($msg, $f = null) use ($fecha) { $_SESSION['nueva_cita'] = $_POST; ir('dia', $f ?? $fecha, $msg); };
  $q = $db->prepare('SELECT * FROM servicios WHERE id = ? AND negocio_id = ? AND activo = 1'); $q->execute([(int)($_POST['servicio'] ?? 0), $nid]); $s = $q->fetch();
  $f = (string)($_POST['dia'] ?? ''); $d = DateTime::createFromFormat('!Y-m-d', $f);
  $forzar = !empty($_POST['forzar']);
  $hora = lc_min($forzar && ($_POST['hora_manual'] ?? '') !== '' ? $_POST['hora_manual'] : ($_POST['hora'] ?? ''));
  $eid = (int)($_POST['equipo'] ?? 0);
  if (!$s) $volver('Elige el servicio.');
  if (!$d || $d->format('Y-m-d') !== $f || $f < date('Y-m-d', strtotime('-1 day')) || $f > date('Y-m-d', strtotime('+365 days'))) $volver('Revisa la fecha.');
  if ($hora === null || $hora >= 24 * 60) $volver('Elige la hora.');

  // ¿Quién viene? Un cliente de la lista, o alguien nuevo (con o sin teléfono)
  $cli = null; $nombre = ''; $tel = '';
  if (($_POST['quien'] ?? '') === 'lista') {
    $q = $db->prepare('SELECT * FROM clientes WHERE id = ? AND negocio_id = ?'); $q->execute([(int)($_POST['cliente_id'] ?? 0), $nid]); $cli = $q->fetch() ?: null;
    if (!$cli) $volver('Elige el cliente de tu lista.');
    $nombre = $cli['nombre']; $tel = $cli['telefono'];
  } else {
    $nombre = p('nombre', 80); $tel = preg_replace('/[^0-9+]/', '', p('telefono', 20));
    if (mb_strlen($nombre) < 2) $volver('Escribe el nombre de la persona.');
    if ($tel !== '' && strlen($tel) < 7) $volver('El teléfono parece incompleto. Puedes dejarlo vacío.');
  }

  $limite = lc_es_pro($neg) ? '' : ' LIMIT 1';
  try {
    $db->beginTransaction();
    $q = $db->prepare('SELECT id, nombre FROM equipo WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id' . $limite . ' FOR UPDATE'); $q->execute([$nid]);
    $equipoAct = $q->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$equipoAct || ($eid && !isset($equipoAct[$eid]))) { $db->rollBack(); $volver('Elige quién atiende.'); }
    $elegido = null;
    if ($forzar) {
      $elegido = $eid ?: array_key_first($equipoAct); // fuera de horario o encima de otra cita: lo decide el dueño
    } else {
      foreach (lc_disponibles($db, $neg, $f, (int)$s['duracion'], $eid ? [$eid] : array_keys($equipoAct)) as $id2 => $horas)
        if (in_array($hora, $horas, true)) { $elegido = $id2; break; }
      if (!$elegido) { $db->rollBack(); $volver('Esa hora ya no está libre. Elige otra o marca “Apartar aunque esté ocupada o fuera de horario”.'); }
    }
    $db->prepare('INSERT INTO citas (negocio_id, servicio, barbero, equipo_id, fecha, hora, duracion, precio, nombre, telefono, idioma, ip_hash, cliente_id)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$nid, $s['nombre'], $equipoAct[$elegido], $elegido, $f, lc_hhmm($hora), (int)$s['duracion'], $s['precio'],
                  $nombre, $tel, 'es', hash('sha256', 'portal'), $cli['id'] ?? null]);
    $citaId = (int)$db->lastInsertId();
    $db->commit();
  } catch (PDOException $ex) {
    if ($db->inTransaction()) $db->rollBack();
    $volver('No se pudo guardar la cita. Inténtalo otra vez.');
  }
  // Persona nueva con teléfono: se guarda en Clientes si el dueño lo pidió
  if (!$cli && $tel !== '' && !empty($_POST['guardar'])) {
    try { $cli = lc_cliente($db, $neg, $nombre, $tel); if ($cli) $db->prepare('UPDATE citas SET cliente_id = ? WHERE id = ?')->execute([$cli['id'], $citaId]); }
    catch (Exception $ex) { error_log('LuniCitas cliente: ' . $ex->getMessage()); }
  }
  unset($_SESSION['nueva_cita']);
  if ($tel !== '' && ($wa = lc_wa_numero($tel, $neg['prefijo']))) {
    $c = ['nombre' => $nombre, 'fecha' => $f, 'hora' => lc_hhmm($hora), 'servicio' => $s['nombre'], 'barbero' => $equipoAct[$elegido]];
    $_SESSION['confirmar_wa'] = 'https://wa.me/' . $wa . '?text=' . rawurlencode(lc_mensaje_confirmacion($c, $neg));
  }
  ir('dia', $f, 'Cita apartada para ' . $nombre . ' a las ' . h12(lc_hhmm($hora)) . ' con ' . $equipoAct[$elegido] . '.');
}

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
