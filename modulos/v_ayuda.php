<?php
// LuniCitas — vista Ayuda del negocio: chat con soporte y sugerencias. Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
$db->prepare("UPDATE mensajes SET leido = 1 WHERE negocio_id = ? AND de = 'soporte'")->execute([$neg['id']]);
$q = $db->prepare("SELECT id, de, tipo, texto, creada FROM mensajes WHERE negocio_id = ? ORDER BY id DESC LIMIT 100"); $q->execute([$neg['id']]);
$msgs = array_reverse($q->fetchAll()); $ultimo = $msgs ? (int)end($msgs)['id'] : 0;
?>
<style>
.chat{display:flex;flex-direction:column;gap:8px;max-height:55vh;overflow-y:auto;padding:4px 2px 8px}.bur{max-width:82%;padding:10px 12px;border-radius:16px;font-size:14px;line-height:1.5;white-space:pre-wrap;word-wrap:break-word}
.bur small{display:block;font-size:11px;opacity:.7;margin-top:4px}.bur.yo{align-self:flex-end;background:#7b5cff;color:#fff;border-bottom-right-radius:4px}.bur.ellos{align-self:flex-start;background:#f1f0f6;color:#1d1b2e;border-bottom-left-radius:4px}
.bur.sug{background:#fff4d6;color:#5c3d00}.chatf{display:flex;gap:8px;margin-top:8px}.chatf textarea{flex:1;margin:0;min-height:46px;max-height:140px;resize:vertical}.vacio2{color:#8a88a0;font-size:14px;text-align:center;padding:18px}
</style>
<div class="box"><h3>💬 Chat con soporte de LuniCitas</h3><p style="font-size:13px;color:#8a88a0;margin:4px 0 10px">Escríbenos tu duda o problema. Te respondemos aquí mismo y te avisamos por correo.</p>
  <div class="chat" id="chat" data-ultimo="<?= $ultimo ?>">
    <?php if (!$msgs): ?><p class="vacio2" id="chatVacio">Aún no hay mensajes. ¡Escríbenos!</p><?php endif; ?>
    <?php foreach ($msgs as $m): ?><div class="bur <?= $m['de'] === 'negocio' ? 'yo' : 'ellos' ?> <?= $m['tipo'] === 'sugerencia' ? 'sug' : '' ?>"><?= $m['tipo'] === 'sugerencia' ? '💡 ' : '' ?><?= e($m['texto']) ?><small><?= $m['de'] === 'negocio' ? 'Tú' : 'Soporte' ?> · <?= e(date('j/n g:i a', strtotime($m['creada']))) ?></small></div><?php endforeach; ?>
  </div>
  <form method="post" action="?v=ayuda" class="chatf"><?= $csrf ?><textarea name="texto" maxlength="2000" placeholder="Escribe tu mensaje…" required></textarea><button class="btn" name="accion" value="chat_enviar">Enviar</button></form>
</div>
<div class="box"><h3>💡 Sugiere una mejora</h3><p style="font-size:13px;color:#8a88a0;margin:4px 0 10px">¿Qué te gustaría que LuniCitas hiciera mejor? Leemos todas las ideas.</p>
  <form method="post" action="?v=ayuda"><?= $csrf ?><textarea name="texto" maxlength="2000" placeholder="Me gustaría que…" required style="min-height:90px"></textarea>
    <button class="btn" name="accion" value="sugerir">Enviar idea</button></form></div>
<p style="font-size:13px;color:#8a88a0;text-align:center">También tienes la <a href="terminos" target="_blank">página de Términos</a>, la de <a href="privacidad" target="_blank">Privacidad</a> y el <a href="contacto" target="_blank">formulario de contacto</a>.</p>
<script>
// Trae mensajes nuevos cada 15 segundos (solo mientras esta pestaña está a la vista)
(function () {
  var c = document.getElementById('chat'); c.scrollTop = c.scrollHeight;
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  setInterval(function () {
    if (document.hidden) return;
    fetch('admin.php?v=ayuda&json=chat&desde=' + c.dataset.ultimo, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : []; }).then(function (ms) {
      ms.forEach(function (m) {
        var v = document.getElementById('chatVacio'); if (v) v.remove();
        var b = document.createElement('div'); b.className = 'bur ' + (m.de === 'negocio' ? 'yo' : 'ellos');
        b.innerHTML = esc(m.texto) + '<small>' + (m.de === 'negocio' ? 'Tú' : 'Soporte') + ' · ' + esc(m.hora) + '</small>';
        c.appendChild(b); c.dataset.ultimo = m.id; c.scrollTop = c.scrollHeight;
      });
    }).catch(function () {});
  }, 15000);
})();
</script>
