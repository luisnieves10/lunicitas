<?php
// LuniCitas — vista Caja: cuentas de la semana o el mes, ventas y gastos, pago al equipo y cierres. Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
$per = ($_GET['p'] ?? '') === 'mes' ? 'mes' : 'semana';
$d0 = DateTime::createFromFormat('!Y-m-d', $fecha) ?: new DateTime('today');
if ($per === 'semana') { $desdeD = (clone $d0)->modify('monday this week'); $hastaD = (clone $desdeD)->modify('+6 days'); $paso = '7 days'; }
else { $desdeD = (clone $d0)->modify('first day of this month'); $hastaD = (clone $d0)->modify('last day of this month'); $paso = '1 month'; }
$desde = $desdeD->format('Y-m-d'); $hasta = $hastaD->format('Y-m-d');
$antF = (clone $desdeD)->modify('-' . $paso)->format('Y-m-d'); $sigF = (clone $desdeD)->modify('+' . $paso)->format('Y-m-d');
$C = lc_caja($db, $neg, $desde, $hasta);
$q = $db->prepare('SELECT * FROM cierres WHERE negocio_id = ? AND tipo = ? AND desde = ?'); $q->execute([$neg['id'], $per, $desde]); $cierre = $q->fetch();
$q = $db->prepare('SELECT * FROM movimientos WHERE negocio_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha DESC, id DESC'); $q->execute([$neg['id'], $desde, $hasta]); $movs = $q->fetchAll();
$q = $db->prepare('SELECT id, nombre, comision FROM equipo WHERE negocio_id = ? AND activo = 1 ORDER BY orden, id'); $q->execute([$neg['id']]); $eqTodos = $q->fetchAll();
$q = $db->prepare('SELECT * FROM cierres WHERE negocio_id = ? ORDER BY desde DESC LIMIT 12'); $q->execute([$neg['id']]); $cierres = $q->fetchAll();
$METODOS = ['efectivo' => '💵 Efectivo', 'tarjeta' => '💳 Tarjeta', 'ath' => '📱 ATH Móvil', 'transferencia' => '🏦 Transferencia'];
$titP = $per === 'semana' ? $desdeD->format('j') . ' ' . $MESES[$desdeD->format('n') - 1] . ' – ' . $hastaD->format('j') . ' ' . $MESES[$hastaD->format('n') - 1]
                          : ucfirst($MESES[$desdeD->format('n') - 1]) . ' ' . $desdeD->format('Y');
$hoyEnRango = $hoy >= $desde && $hoy <= $hasta ? $hoy : $desde;
$enlace = function ($f) use ($per) { return '?v=caja&amp;p=' . $per . '&amp;fecha=' . $f; };
?>
<style>
.per{display:flex;gap:6px;margin-bottom:10px}.per a{flex:1;text-align:center;padding:9px;border-radius:12px;background:#fff;color:#5e5c73;text-decoration:none;font-weight:600;font-size:14px}.per a.on{background:#1d1b2e;color:#fff}
.navp{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}.navp a{padding:8px 14px;border-radius:12px;background:#fff;text-decoration:none;color:#1d1b2e;font-weight:700}.navp b{font-size:16px}
.gan{background:#1d1b2e;color:#fff;border-radius:20px;padding:18px;margin-bottom:10px}.gan small{color:#c9c6dc;display:block;font-size:13px}.gan b{font-size:34px;letter-spacing:-.02em;display:block;line-height:1.1;margin:4px 0}
.gan.neg b{color:#ff8a8a}.cuentas td{padding:7px 4px;border-bottom:1px solid #f0eff5;font-size:14px}.cuentas td:last-child{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.cuentas tr.tot td{font-weight:700;border-bottom:2px solid #1d1b2e}.cuentas .menos{color:#b42318}.mini2{font-size:13px;color:#8a88a0}.tipo{display:flex;gap:6px;margin-bottom:8px}.tipo label{flex:1;margin:0}
.tipo input{display:none}.tipo span{display:block;text-align:center;padding:10px;border-radius:12px;border:2px solid #e4e2ee;font-weight:600;font-size:14px;cursor:pointer}.tipo input:checked+span{border-color:#7b5cff;background:#efeaff}
.mov{display:flex;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid #f0eff5;font-size:14px}.mov .t{flex:1}.mov .m{font-weight:700;font-variant-numeric:tabular-nums}.mov .m.g{color:#b42318}
.cerrado{background:#e7f9ef;color:#0f6b3a;border-radius:12px;padding:10px;font-size:14px;margin-bottom:10px}
</style>
<div class="per"><a class="<?= $per === 'semana' ? 'on' : '' ?>" href="?v=caja&amp;p=semana&amp;fecha=<?= e($fecha) ?>">Semana</a><a class="<?= $per === 'mes' ? 'on' : '' ?>" href="?v=caja&amp;p=mes&amp;fecha=<?= e($fecha) ?>">Mes</a></div>
<div class="navp"><a href="<?= $enlace($antF) ?>" aria-label="Periodo anterior">‹</a><b><?= e($titP) ?></b><a href="<?= $enlace($sigF) ?>" aria-label="Periodo siguiente">›</a></div>
<?php if ($cierre): ?><div class="cerrado">🔒 <?= $per === 'semana' ? 'Semana cerrada' : 'Mes cerrado' ?> el <?= e(date('j/n/Y g:i a', strtotime($cierre['creado']))) ?>. Si cambias algo, ciérralo otra vez para actualizarlo.</div><?php endif; ?>

<div class="gan <?= $C['ganancia'] < 0 ? 'neg' : '' ?>"><small>Ganancia de <?= $per === 'semana' ? 'la semana' : 'el mes' ?></small><b><?= e(dinero($C['ganancia'])) ?></b>
  <small><?= (int)$C['citas'] ?> citas cobradas · ingresos <?= e(dinero($C['ingresos'])) ?> · gastos <?= e(dinero($C['gastos'])) ?></small></div>

<div class="box"><h3>Cómo se calcula</h3>
  <table class="cuentas" style="width:100%"><tbody>
    <tr><td>Servicios (precio de lista)</td><td><?= e(dinero($C['bruto'])) ?></td></tr>
    <tr><td>Descuentos que diste</td><td class="menos">− <?= e(dinero($C['descuentos'])) ?></td></tr>
    <tr><td>Ventas extra (productos, sin cita…)</td><td>+ <?= e(dinero($C['ventas'])) ?></td></tr>
    <tr class="tot"><td>Ingresos</td><td><?= e(dinero($C['ingresos'])) ?></td></tr>
    <tr><td>Gastos</td><td class="menos">− <?= e(dinero($C['gastos'])) ?></td></tr>
    <tr><td>Comisiones del equipo</td><td class="menos">− <?= e(dinero($C['comisiones'])) ?></td></tr>
    <tr class="tot"><td>Ganancia</td><td><?= e(dinero($C['ganancia'])) ?></td></tr>
  </tbody></table>
  <p class="mini2" style="margin-top:8px">Propinas del periodo: <b><?= e(dinero($C['propinas'])) ?></b> (son del equipo; no cuentan como ganancia).</p></div>

<div class="box"><h3>Cuadre por forma de pago</h3><p class="mini2" style="margin-bottom:6px">Lo que debería haber en cada lugar (cobros + propinas + ventas − gastos).</p>
  <table class="cuentas" style="width:100%"><tbody>
  <?php if (!$C['metodos']): ?><tr><td colspan="2" class="mini2">Aún no hay cobros en este periodo.</td></tr><?php endif; ?>
  <?php foreach ($C['metodos'] as $m => $monto): ?><tr><td><?= e($METODOS[$m] ?? $m) ?></td><td><?= e(dinero($monto)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>

<div class="box"><h3>Equipo: lo que le toca a cada uno</h3>
  <table class="cuentas" style="width:100%"><tbody>
    <?php if (!$C['equipo']): ?><tr><td class="mini2">Aún no hay citas cobradas en este periodo.</td></tr><?php endif; ?>
    <?php foreach ($C['equipo'] as $p): ?>
      <tr><td><b><?= e($p['nombre']) ?></b><br><span class="mini2"><?= (int)$p['citas'] ?> citas · <?= e(dinero($p['neto'])) ?> en servicios · comisión <?= e(rtrim(rtrim(number_format($p['pct'], 2), '0'), '.')) ?>% + propinas <?= e(dinero($p['propinas'])) ?></span></td>
        <td><?= e(dinero($p['comision'] + $p['propinas'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <details style="margin-top:8px"><summary class="mini2" style="cursor:pointer">Cambiar el % de comisión de cada persona</summary>
    <form method="post" action="<?= $enlace($fecha) ?>" style="margin-top:8px"><?= $csrf ?>
      <?php foreach ($eqTodos as $p): ?><div class="fila" style="margin-bottom:6px"><span style="flex:1"><?= e($p['nombre']) ?></span><input name="com[<?= (int)$p['id'] ?>]" type="number" min="0" max="100" step="0.5" value="<?= e((float)$p['comision']) ?>" style="width:90px;margin:0"> %</div><?php endforeach; ?>
      <p class="mini2">Si trabajas solo o el equipo cobra sueldo fijo, déjalo en 0.</p>
      <button class="btn gris" name="accion" value="com_guardar">Guardar comisiones</button></form></details></div>

<div class="box"><h3>Añadir venta o gasto</h3>
  <form method="post" action="<?= $enlace($fecha) ?>"><?= $csrf ?>
    <div class="tipo"><label><input type="radio" name="tipo" value="venta" checked><span>➕ Venta</span></label><label><input type="radio" name="tipo" value="gasto"><span>➖ Gasto</span></label></div>
    <div class="g3"><input name="concepto" maxlength="80" placeholder="Ej. Pomada, alquiler, luz" required><input name="monto" type="number" min="0.01" step="0.01" placeholder="Monto" required><input name="dia" type="date" value="<?= e($hoyEnRango) ?>" required></div>
    <select name="metodo"><?php foreach ($METODOS as $k => $t): ?><option value="<?= $k ?>"><?= e($t) ?></option><?php endforeach; ?></select>
    <button class="btn" name="accion" value="mov_guardar">Añadir</button></form>
  <?php foreach ($movs as $m): ?>
    <form method="post" action="<?= $enlace($fecha) ?>" class="mov"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <span class="t"><?= e($m['concepto']) ?><br><span class="mini2"><?= e(date('j/n', strtotime($m['fecha']))) ?> · <?= e($METODOS[$m['metodo']] ?? $m['metodo']) ?></span></span>
      <span class="m <?= $m['tipo'] === 'gasto' ? 'g' : '' ?>"><?= $m['tipo'] === 'gasto' ? '−' : '+' ?><?= e(dinero($m['monto'])) ?></span>
      <button class="btn gris" name="accion" value="mov_borrar" style="padding:6px 10px" onclick="return confirm('¿Borrar este movimiento?')" aria-label="Borrar">✕</button></form>
  <?php endforeach; ?></div>

<form method="post" action="<?= $enlace($fecha) ?>" class="box"><?= $csrf ?><input type="hidden" name="tipo" value="<?= $per ?>"><input type="hidden" name="desde" value="<?= e($desde) ?>"><input type="hidden" name="hasta" value="<?= e($hasta) ?>">
  <h3>🔒 <?= $per === 'semana' ? 'Cierre de semana' : 'Cierre de mes' ?></h3>
  <p class="mini2" style="margin:6px 0 10px">Guarda una foto de estas cuentas para tu historial y para pagarle al equipo. Puedes descargarla en Excel.</p>
  <button class="btn" name="accion" value="cierre_guardar" style="width:100%"><?= $cierre ? 'Actualizar el cierre' : ($per === 'semana' ? 'Cerrar esta semana' : 'Cerrar este mes') ?></button></form>

<?php if ($cierres): ?><div class="box"><h3>Cierres anteriores</h3>
  <?php foreach ($cierres as $ci): $dd = json_decode($ci['datos'], true) ?: []; ?>
    <div class="mov"><span class="t"><?= $ci['tipo'] === 'semana' ? 'Semana' : 'Mes' ?> <?= e(date('j/n/Y', strtotime($ci['desde']))) ?> – <?= e(date('j/n/Y', strtotime($ci['hasta']))) ?><br>
      <span class="mini2">Ingresos <?= e(dinero($dd['ingresos'] ?? 0)) ?> · gastos <?= e(dinero($dd['gastos'] ?? 0)) ?></span></span>
      <span class="m"><?= e(dinero($dd['ganancia'] ?? 0)) ?></span><a class="btn gris" style="padding:6px 10px" href="?v=caja&amp;csv=cierre&amp;id=<?= (int)$ci['id'] ?>">Excel</a></div>
  <?php endforeach; ?></div><?php endif; ?>
