<?php
// LuniCitas — vista Soporte del administrador: chats con los negocios, mensajes del formulario y sugerencias. Licencia AGPL-3.0
if (!defined('LC_ADMIN') || !$super) exit;
$conv = $db->query("SELECT m.negocio_id, n.nombre, n.email, MAX(m.id) ultimo, SUM(m.de = 'negocio' AND m.leido = 0) sin_leer
                    FROM mensajes m JOIN negocios n ON n.id = m.negocio_id GROUP BY m.negocio_id, n.nombre, n.email ORDER BY SUM(m.de = 'negocio' AND m.leido = 0) > 0 DESC, MAX(m.id) DESC LIMIT 100")->fetchAll();
$sel = (int)($_GET['c'] ?? 0); $hilo = []; $selNeg = null;
if ($sel) {
  $q = $db->prepare('SELECT id, nombre, email, slug FROM negocios WHERE id = ?'); $q->execute([$sel]); $selNeg = $q->fetch();
  if ($selNeg) {
    $db->prepare("UPDATE mensajes SET leido = 1 WHERE negocio_id = ? AND de = 'negocio'")->execute([$sel]);
    $q = $db->prepare('SELECT * FROM mensajes WHERE negocio_id = ? ORDER BY id DESC LIMIT 100'); $q->execute([$sel]); $hilo = array_reverse($q->fetchAll());
  }
}
$visit = $db->query("SELECT * FROM mensajes WHERE negocio_id IS NULL AND de = 'visitante' ORDER BY leido, id DESC LIMIT 50")->fetchAll();
$sugs = $db->query("SELECT m.*, n.nombre neg FROM mensajes m LEFT JOIN negocios n ON n.id = m.negocio_id WHERE m.tipo = 'sugerencia' ORDER BY m.id DESC LIMIT 30")->fetchAll();
?>
<style>
.conv a{display:flex;justify-content:space-between;gap:8px;padding:10px;border-radius:12px;text-decoration:none;color:#1d1b2e;font-size:14px}.conv a.on{background:#efeaff}.conv a:hover{background:#f7f7fb}
.nb{background:#e5484d;color:#fff;border-radius:999px;font-size:11px;font-weight:700;padding:1px 7px;align-self:center}.chat{display:flex;flex-direction:column;gap:8px;max-height:55vh;overflow-y:auto;padding:4px 2px 8px}
.bur{max-width:82%;padding:10px 12px;border-radius:16px;font-size:14px;line-height:1.5;white-space:pre-wrap;word-wrap:break-word}.bur small{display:block;font-size:11px;opacity:.7;margin-top:4px}
.bur.yo{align-self:flex-end;background:#1d1b2e;color:#fff}.bur.ellos{align-self:flex-start;background:#f1f0f6}.bur.sug{background:#fff4d6}.vis{border-bottom:1px solid #f0eff5;padding:10px 0;font-size:14px}.vis.nuevo{border-left:4px solid #7b5cff;padding-left:10px}
.vis p{white-space:pre-wrap;margin:6px 0}.mini2{font-size:12px;color:#8a88a0}
</style>
<div class="grid2">
  <div class="box conv"><h3>💬 Chats con negocios</h3>
    <?php if (!$conv): ?><p class="mini2" style="margin-top:6px">Aún no hay mensajes.</p><?php endif; ?>
    <?php foreach ($conv as $c): ?><a class="<?= (int)$c['negocio_id'] === $sel ? 'on' : '' ?>" href="?v=soporte&amp;c=<?= (int)$c['negocio_id'] ?>"><span><b><?= e($c['nombre']) ?></b><br><span class="mini2"><?= e($c['email']) ?></span></span><?php if ($c['sin_leer'] > 0): ?><span class="nb"><?= (int)$c['sin_leer'] ?></span><?php endif; ?></a><?php endforeach; ?>
  </div>
  <div class="box"><?php if ($selNeg): ?>
    <h3><?= e($selNeg['nombre']) ?></h3><p class="mini2" style="margin-bottom:8px"><?= e($selNeg['email']) ?> · <a href="<?= e($base . '/' . $selNeg['slug']) ?>" target="_blank">ver página</a></p>
    <div class="chat" id="chat"><?php foreach ($hilo as $m): ?><div class="bur <?= $m['de'] === 'soporte' ? 'yo' : 'ellos' ?> <?= $m['tipo'] === 'sugerencia' ? 'sug' : '' ?>"><?= $m['tipo'] === 'sugerencia' ? '💡 ' : '' ?><?= e($m['texto']) ?><small><?= $m['de'] === 'soporte' ? 'Tú (soporte)' : e($selNeg['nombre']) ?> · <?= e(date('j/n g:i a', strtotime($m['creada']))) ?></small></div><?php endforeach; ?></div>
    <form method="post" action="?v=soporte&amp;c=<?= $sel ?>"><?= $csrf ?><input type="hidden" name="id" value="<?= $sel ?>"><textarea name="texto" maxlength="2000" placeholder="Tu respuesta (le llega también por correo)" required style="min-height:70px"></textarea>
      <button class="btn" name="accion" value="sop_responder">Responder</button></form>
    <script>var c = document.getElementById('chat'); c.scrollTop = c.scrollHeight;</script>
  <?php else: ?><h3>Elige un chat</h3><p class="mini2" style="margin-top:6px">Los negocios te escriben desde la pestaña Ayuda de su portal. Te llega un aviso por correo con cada mensaje.</p><?php endif; ?></div>
</div>

<div class="box"><h3>✉️ Formulario de contacto</h3><p class="mini2" style="margin:4px 0 8px">Mensajes de personas sin cuenta. Tu respuesta les llega por correo; tu dirección nunca se muestra en la web.</p>
  <?php if (!$visit): ?><p class="mini2">No hay mensajes.</p><?php endif; ?>
  <?php foreach ($visit as $m): ?><div class="vis <?= $m['leido'] ? '' : 'nuevo' ?>"><b><?= e($m['nombre']) ?></b> <span class="mini2">· <?= e($m['email']) ?> · <?= e(date('j/n/Y g:i a', strtotime($m['creada']))) ?></span><p><?= e($m['texto']) ?></p>
    <?php if (!$m['leido']): ?><details><summary class="mini2" style="cursor:pointer">Responder por correo</summary>
      <form method="post" action="?v=soporte"><?= $csrf ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><textarea name="texto" maxlength="3000" required style="min-height:70px;margin-top:6px"></textarea>
        <button class="btn" name="accion" value="sop_visitante">Enviar respuesta</button> <button class="btn gris" name="accion" value="sop_leido" formnovalidate>Marcar como atendido</button></form></details><?php else: ?><span class="mini2">✓ Atendido</span><?php endif; ?></div><?php endforeach; ?></div>

<div class="box"><h3>💡 Sugerencias recibidas</h3>
  <?php if (!$sugs): ?><p class="mini2" style="margin-top:6px">Aún no hay sugerencias.</p><?php endif; ?>
  <?php foreach ($sugs as $m): ?><div class="vis"><b><?= e($m['neg'] ?: $m['nombre']) ?></b> <span class="mini2">· <?= e(date('j/n/Y', strtotime($m['creada']))) ?></span><p><?= e($m['texto']) ?></p></div><?php endforeach; ?></div>
