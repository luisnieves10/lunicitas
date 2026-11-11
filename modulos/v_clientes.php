<?php
// LuniCitas — vista Clientes: lista automática, fijos, club gratis, recomendaciones y descuentos. Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
$PR = lc_promos($neg);
$filtro = in_array($_GET['f'] ?? '', ['fijos', 'club', 'extranan', 'premio'], true) ? $_GET['f'] : '';
$busca = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
$sql = "SELECT c.*, COUNT(ci.id) visitas, MAX(ci.fecha) ultima, COALESCE(SUM(ci.precio - ci.descuento), 0) gastado,
          (SELECT COUNT(*) FROM clientes r WHERE r.referido_por = c.id) refs,
          (SELECT COUNT(*) FROM clientes r WHERE r.referido_por = c.id AND EXISTS (SELECT 1 FROM citas x WHERE x.cliente_id = r.id AND x.estado = 'asistio')) refs_ok,
          (SELECT nombre FROM clientes p WHERE p.id = c.referido_por) por
        FROM clientes c LEFT JOIN citas ci ON ci.cliente_id = c.id AND ci.estado = 'asistio'
        WHERE c.negocio_id = ?" . ($busca !== '' ? ' AND (c.nombre LIKE ? OR c.telefono LIKE ?)' : '') . "
        GROUP BY c.id ORDER BY (MAX(ci.fecha) IS NULL), MAX(ci.fecha) DESC, c.id DESC LIMIT 500";
$q = $db->prepare($sql);
$q->execute($busca !== '' ? [$neg['id'], '%' . $busca . '%', '%' . preg_replace('/[^0-9]/', '', $busca) . '%'] : [$neg['id']]);
$todos = $q->fetchAll();
$hace35 = date('Y-m-d', strtotime('-35 days'));
foreach ($todos as &$c) {
  $c['esFijo'] = $c['fijo'] || ($PR['fijo_visitas'] > 0 && $c['visitas'] >= $PR['fijo_visitas']);
  $c['premios'] = $PR['ref_meta'] > 0 ? intdiv((int)$c['refs_ok'], (int)$PR['ref_meta']) - (int)$c['premios_usados'] : 0;
  $c['extrana'] = $c['ultima'] && $c['ultima'] < $hace35;
} unset($c);
$cuenta = ['fijos' => 0, 'club' => 0, 'extranan' => 0, 'premio' => 0];
foreach ($todos as $c) { $cuenta['fijos'] += $c['esFijo']; $cuenta['club'] += (int)$c['club']; $cuenta['extranan'] += $c['extrana']; $cuenta['premio'] += $c['premios'] > 0; }
$lista = array_filter($todos, function ($c) use ($filtro) {
  return $filtro === '' || ($filtro === 'fijos' && $c['esFijo']) || ($filtro === 'club' && $c['club']) || ($filtro === 'extranan' && $c['extrana']) || ($filtro === 'premio' && $c['premios'] > 0);
});
$FILTROS = ['' => 'Todos (' . count($todos) . ')', 'fijos' => '⭐ Fijos (' . $cuenta['fijos'] . ')', 'club' => '🎟️ Club (' . $cuenta['club'] . ')',
            'premio' => '🎁 Con premio (' . $cuenta['premio'] . ')', 'extranan' => '💬 Te extrañan (' . $cuenta['extranan'] . ')'];
?>
<style>
.chips{display:flex;gap:6px;overflow-x:auto;margin-bottom:10px;padding-bottom:2px}.chips a{white-space:nowrap;padding:8px 12px;border-radius:999px;background:#fff;color:#5e5c73;text-decoration:none;font-size:13px;font-weight:600;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.chips a.on{background:#1d1b2e;color:#fff}.cli summary{list-style:none;cursor:pointer;display:flex;gap:10px;align-items:center}.cli summary::-webkit-details-marker{display:none}
.cli .t{flex:1}.cli small{color:#8a88a0;display:block}.badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 7px;border-radius:999px;background:#efeaff;color:#5b45d6;margin-left:4px;vertical-align:middle}
.badge.oro{background:#fff4d6;color:#8a5a00}.badge.verde{background:#e3faf7;color:#0b7a6e}.cli .det{padding-top:12px;font-size:14px}.cli .det p{margin-bottom:8px}.enlace{word-break:break-all;background:#f7f7fb;border-radius:10px;padding:8px;font-size:12px}
.acc2{display:flex;gap:6px;flex-wrap:wrap;margin:8px 0}.acc2 a,.acc2 button{font-size:13px;padding:8px 10px;border-radius:10px;border:0;background:#ebe9f3;color:#1d1b2e;text-decoration:none;font-weight:600;cursor:pointer;font-family:inherit}
.acc2 .wa{background:#e7f9ef;color:#0f6b3a}.promo label{font-size:13px}.promo .g{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}.ayudita{font-size:13px;color:#8a88a0;margin:-2px 0 10px}
</style>

<details class="box promo">
  <summary style="cursor:pointer;font-weight:700">🎁 Descuentos y recomendaciones <span style="font-weight:400;color:#8a88a0;font-size:13px">· toca para cambiarlos</span></summary>
  <form method="post" action="<?= e(url('clientes')) ?>" style="margin-top:12px"><?= $csrf ?>
    <label class="ck"><input type="checkbox" name="club" value="1" <?= $PR['club'] ? 'checked' : '' ?>> Ofrecer el <b>club de clientes</b> gratis en tu página de reservas</label>
    <p class="ayudita">Tus clientes pueden unirse al reservar. Reciben su propio enlace para recomendarte.</p>
    <div class="g">
      <div><label>⭐ Descuento a clientes fijos (%)</label><input name="fijo_pct" type="number" min="0" max="100" step="1" value="<?= e($PR['fijo_pct']) ?>"></div>
      <div><label>Es fijo desde la visita número</label><input name="fijo_visitas" type="number" min="0" max="100" value="<?= e($PR['fijo_visitas']) ?>"></div>
      <div><label>🎁 Premio cada (clientes recomendados)</label><input name="ref_meta" type="number" min="0" max="100" value="<?= e($PR['ref_meta']) ?>"></div>
      <div><label>Premio por recomendar (%)</label><input name="ref_pct" type="number" min="0" max="100" step="1" value="<?= e($PR['ref_pct']) ?>"></div>
      <div><label>👋 Bienvenida al recomendado (%)</label><input name="bienvenida_pct" type="number" min="0" max="100" step="1" value="<?= e($PR['bienvenida_pct']) ?>"></div>
    </div>
    <p class="ayudita">Pon 0 para no usar un descuento. Un recomendado cuenta cuando ya vino a su primera cita. Al cobrar, LuniCitas te sugiere el mejor descuento que le toca a cada cliente; tú decides si lo aplicas.</p>
    <button class="btn" name="accion" value="promos_guardar">Guardar descuentos</button>
  </form>
</details>

<form method="get" class="fila" style="margin-bottom:10px"><input type="hidden" name="v" value="clientes"><input type="hidden" name="f" value="<?= e($filtro) ?>">
  <input name="q" value="<?= e($busca) ?>" placeholder="Buscar por nombre o teléfono" style="margin:0;flex:1"><button class="btn gris">Buscar</button></form>
<div class="chips"><?php foreach ($FILTROS as $k => $t): ?><a class="<?= $filtro === $k ? 'on' : '' ?>" href="?v=clientes&amp;f=<?= e($k) ?><?= $busca !== '' ? '&amp;q=' . e(urlencode($busca)) : '' ?>"><?= e($t) ?></a><?php endforeach; ?></div>
<?php if ($filtro === 'extranan'): ?><p class="ayudita">Clientes que no vienen hace más de 35 días. Escríbeles por WhatsApp con un toque para invitarlos a volver.</p><?php endif; ?>

<?php if (!$lista): ?>
  <div class="box vacio"><h3>Aún no hay clientes aquí</h3><p style="margin-top:6px;font-size:14px;color:#6b6980">Cada persona que reserva en tu página aparece sola en esta lista, con sus visitas y lo que ha gastado.</p></div>
<?php endif; ?>
<?php foreach ($lista as $c):
  $wa = lc_wa_numero($c['telefono'], $neg['prefijo']);
  $enlace = $base . '/' . $neg['slug'] . '?ref=' . $c['codigo'];
  $nom1 = explode(' ', trim($c['nombre']))[0];
  $msgVuelve = "¡Hola $nom1! Te extrañamos en {$neg['nombre']}. ¿Te reservo una cita? Escoge tu hora aquí: $base/{$neg['slug']}";
  $msgEnlace = "¡Hola $nom1! Este es tu enlace de {$neg['nombre']} para recomendarnos: $enlace" . ($PR['ref_meta'] > 0 && $PR['ref_pct'] > 0 ? " Por cada {$PR['ref_meta']} amigos que vengan con tu enlace, ganas {$PR['ref_pct']}% de descuento." : '');
?>
  <details class="box cli" id="c<?= (int)$c['id'] ?>">
    <summary><?= fotoPeq('', $c['nombre']) ?><div class="t"><b><?= e($c['nombre']) ?></b>
      <?php if ($c['esFijo']): ?><span class="badge oro">⭐ Fijo</span><?php endif; ?><?php if ($c['club']): ?><span class="badge">🎟️ Club</span><?php endif; ?><?php if ($c['premios'] > 0): ?><span class="badge verde">🎁 Premio</span><?php endif; ?>
      <small><?= (int)$c['visitas'] ?> <?= $c['visitas'] == 1 ? 'visita' : 'visitas' ?><?= $c['ultima'] ? ' · última ' . e(date('j/n/Y', strtotime($c['ultima']))) : '' ?> · <?= e(dinero($c['gastado'])) ?></small></div></summary>
    <div class="det">
      <div class="acc2">
        <?php if ($wa): ?><a class="wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">💬 WhatsApp</a><?php endif; ?>
        <?php if ($wa && $c['extrana']): ?><a class="wa" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($msgVuelve)) ?>" target="_blank" rel="noopener">👋 Invitar a volver</a><?php endif; ?>
        <?php if ($wa): ?><a class="wa" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($msgEnlace)) ?>" target="_blank" rel="noopener">🎁 Enviarle su enlace</a><?php endif; ?>
      </div>
      <p>📞 <?= e($c['telefono']) ?><?= $c['email'] ? ' · ✉️ ' . e($c['email']) : '' ?><?= $c['por'] ? ' · Lo recomendó <b>' . e($c['por']) . '</b>' : '' ?></p>
      <p>🎁 Recomendó a <b><?= (int)$c['refs'] ?></b> (<?= (int)$c['refs_ok'] ?> ya vinieron)<?= $PR['ref_meta'] > 0 ? ' · premios ganados ' . intdiv((int)$c['refs_ok'], (int)$PR['ref_meta']) . ', usados ' . (int)$c['premios_usados'] : '' ?></p>
      <p class="enlace">Enlace para recomendar: <?= e($enlace) ?></p>
      <form method="post" action="?v=clientes&amp;f=<?= e($filtro) ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="grid2"><div><label>Nombre</label><input name="nombre" maxlength="80" value="<?= e($c['nombre']) ?>"></div>
          <div><label>Correo (opcional)</label><input name="email" type="email" maxlength="120" value="<?= e($c['email']) ?>"></div></div>
        <label>Nota (gustos, alergias, cómo le gusta el corte…)</label><input name="nota" maxlength="200" value="<?= e($c['nota']) ?>">
        <label class="ck"><input type="checkbox" name="fijo" value="1" <?= $c['fijo'] ? 'checked' : '' ?>> ⭐ Marcar como cliente fijo</label>
        <button class="btn" name="accion" value="cli_guardar">Guardar</button></form>
    </div>
  </details>
<?php endforeach; ?>
