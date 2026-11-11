<?php
// LuniCitas — formulario "Apartar una cita" en la vista Día. Licencia AGPL-3.0
if (!defined('LC_ADMIN')) exit;
$pre = $_SESSION['nueva_cita'] ?? []; unset($_SESSION['nueva_cita']);
$preCli = (int)($_GET['cliente'] ?? ($pre['cliente_id'] ?? 0));
$q = $db->prepare('SELECT id, nombre, telefono, club FROM clientes WHERE negocio_id = ? ORDER BY nombre LIMIT 3000'); $q->execute([$neg['id']]);
$lista = $q->fetchAll();
$quien = $pre['quien'] ?? ($preCli || $lista ? 'lista' : 'nuevo');
if (!$lista) $quien = 'nuevo';
$abierta = $pre || isset($_GET['nueva']) || $preCli;
$diaPre = $pre['dia'] ?? ($fecha < date('Y-m-d') ? date('Y-m-d') : $fecha);
$confirmar = $_SESSION['confirmar_wa'] ?? ''; unset($_SESSION['confirmar_wa']);
$equipoForm = lc_es_pro($neg) ? $equipo : array_slice($equipo, 0, 1);
?>
<style>
.nc summary{list-style:none;cursor:pointer;font-weight:700;display:flex;align-items:center;gap:8px}.nc summary::-webkit-details-marker{display:none}
.nc summary .mas{margin-left:auto;font-size:13px;font-weight:600;color:#7b5cff}.nc[open] summary .mas{display:none}
.seg{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:12px 0 10px}.seg label{margin:0}.seg input{display:none}
.seg span{display:block;text-align:center;padding:10px 6px;border-radius:12px;border:2px solid #e4e2ee;font-size:14px;font-weight:600;cursor:pointer}
.seg input:checked+span{border-color:#7b5cff;background:#f1edff;color:#5b45d6}
.nc .g2{display:grid;grid-template-columns:1fr 1fr;gap:8px}@media(max-width:520px){.nc .g2{grid-template-columns:1fr}}
.nc .ayuda{font-size:12px;color:#8a88a0;margin:-6px 0 10px}.nc .oculto{display:none}
.confirmar{display:flex;gap:10px;align-items:center;background:#e7f9ef;color:#0f6b3a;border-radius:14px;padding:12px;margin-bottom:12px;font-size:14px}
.confirmar a{margin-left:auto;background:#25d366;color:#fff;border-radius:10px;padding:8px 12px;text-decoration:none;font-weight:700;white-space:nowrap}
</style>
<?php if ($confirmar): ?>
  <div class="confirmar noprint">💬 ¿Le avisas por WhatsApp que su cita quedó apartada?<a href="<?= e($confirmar) ?>" target="_blank" rel="noopener">Enviar confirmación</a></div>
<?php endif; ?>
<details class="box nc noprint" id="nueva" <?= $abierta ? 'open' : '' ?>>
  <summary>➕ Apartar una cita <span class="mas">para un cliente o alguien nuevo</span></summary>
  <?php if (!$servicios || !$equipoForm): ?>
    <p style="margin-top:10px;font-size:14px;color:#6b6980">Primero añade al menos un servicio y una persona en <a href="?v=servicios">Servicios</a> y <a href="?v=equipo">Equipo</a>.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('dia')) ?>" id="ncForm"><?= $csrf ?>
    <div class="seg">
      <label><input type="radio" name="quien" value="lista" <?= $quien === 'lista' ? 'checked' : '' ?> <?= $lista ? '' : 'disabled' ?>><span>👤 De mi lista de clientes</span></label>
      <label><input type="radio" name="quien" value="nuevo" <?= $quien === 'nuevo' ? 'checked' : '' ?>><span>✍️ Alguien nuevo o sin inscribir</span></label>
    </div>
    <div data-quien="lista" class="<?= $quien === 'lista' ? '' : 'oculto' ?>">
      <input type="search" id="ncBusca" placeholder="Buscar por nombre o teléfono" autocomplete="off">
      <select name="cliente_id" id="ncCliente" size="5" style="height:auto">
        <?php foreach ($lista as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $preCli === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?> · <?= e($c['telefono']) ?><?= $c['club'] ? ' · 🎟️ Club' : '' ?></option><?php endforeach; ?>
      </select>
    </div>
    <div data-quien="nuevo" class="<?= $quien === 'nuevo' ? '' : 'oculto' ?>">
      <div class="g2"><div><label>Nombre</label><input name="nombre" maxlength="80" value="<?= e($pre['nombre'] ?? '') ?>" placeholder="Ej. José Rivera"></div>
        <div><label>Teléfono (opcional)</label><input name="telefono" type="tel" maxlength="20" value="<?= e($pre['telefono'] ?? '') ?>" placeholder="787 555 1234"></div></div>
      <label class="ck"><input type="checkbox" name="guardar" value="1" <?= !$pre || !empty($pre['guardar']) ? 'checked' : '' ?>> Guardarlo en mi lista de clientes (si tiene teléfono)</label>
    </div>
    <div class="g2">
      <div><label>Servicio</label><select name="servicio" id="ncSvc"><?php foreach ($servicios as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($pre['servicio'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?> · <?= (int)$s['duracion'] ?> min · <?= e(dinero($s['precio'])) ?></option><?php endforeach; ?></select></div>
      <div><label>Con</label><select name="equipo" id="ncEq"><?php if (count($equipoForm) > 1): ?><option value="0">Cualquiera libre</option><?php endif; ?><?php foreach ($equipoForm as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($pre['equipo'] ?? -1) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="g2">
      <div><label>Día</label><input type="date" name="dia" id="ncDia" value="<?= e($diaPre) ?>" min="<?= e(date('Y-m-d')) ?>"></div>
      <div><label>Hora libre</label><select name="hora" id="ncHora"><option value="">Elige el día…</option></select></div>
    </div>
    <label class="ck"><input type="checkbox" name="forzar" value="1" id="ncForzar" <?= !empty($pre['forzar']) ? 'checked' : '' ?>> Apartar aunque esté ocupada o fuera de horario</label>
    <div id="ncManual" class="<?= !empty($pre['forzar']) ? '' : 'oculto' ?>"><label>Hora exacta</label><input type="time" name="hora_manual" value="<?= e($pre['hora_manual'] ?? '') ?>">
      <p class="ayuda">Úsalo para un cliente que llegó sin cita, para quedarte un rato más tarde o para meter a alguien entre citas.</p></div>
    <button class="btn" name="accion" value="cita_nueva" style="width:100%">Apartar cita</button>
  </form>
  <script>
  (function(){
    const f=document.getElementById('ncForm'); if(!f) return;
    const $=id=>document.getElementById(id), pre=<?= js($pre['hora'] ?? '') ?>;
    f.querySelectorAll('input[name=quien]').forEach(r=>r.addEventListener('change',()=>f.querySelectorAll('[data-quien]').forEach(d=>d.classList.toggle('oculto',d.dataset.quien!==f.quien.value))));
    $('ncBusca') && $('ncBusca').addEventListener('input',e=>{const t=e.target.value.toLowerCase().replace(/[^a-z0-9áéíóúñü ]/g,'');let first=null;
      for(const o of $('ncCliente').options){const ok=!t||o.text.toLowerCase().replace(/[^a-z0-9áéíóúñü ]/g,'').includes(t);o.hidden=!ok;if(ok&&!first)first=o}
      if(first&&($('ncCliente').selectedOptions[0]||{}).hidden!==false)first.selected=true});
    $('ncForzar').addEventListener('change',e=>$('ncManual').classList.toggle('oculto',!e.target.checked));
    const fmt=x=>{let[h,m]=x.split(':').map(Number);return (h%12||12)+':'+String(m).padStart(2,'0')+(h<12?' a. m.':' p. m.')};
    let n=0;
    async function horas(){const id=++n,s=$('ncHora');s.innerHTML='<option value="">Buscando horas…</option>';
      try{const r=await fetch('api.php?'+new URLSearchParams({n:<?= js($neg['slug']) ?>,fecha:$('ncDia').value,servicio:$('ncSvc').value,equipo:$('ncEq').value}));const j=await r.json();if(id!==n)return;
        const hs=j.horas||[];s.innerHTML=hs.length?hs.map(x=>`<option value="${x}" ${x===pre?'selected':''}>${fmt(x)}</option>`).join(''):'<option value="">No quedan horas libres ese día</option>';}
      catch(e){s.innerHTML='<option value="">No se pudieron cargar las horas</option>'}}
    ['ncDia','ncSvc','ncEq'].forEach(i=>$(i).addEventListener('change',horas));
    horas();
    <?php if ($abierta): ?>document.getElementById('nueva').scrollIntoView({block:'start'});<?php endif; ?>
  })();
  </script>
  <?php endif; ?>
</details>
