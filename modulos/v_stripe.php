<?php
// LuniCitas — estado de la conexión con Stripe (solo el administrador). Licencia AGPL-3.0
if (!defined('LC_ADMIN') || !$super) exit;
$clave = (string)lc_cfg('STRIPE_SECRET');
$modo = preg_match('/^[rs]k_live_/', $clave) ? 'real' : (preg_match('/^[rs]k_test_/', $clave) ? 'prueba' : 'desconocido');
$tipo = strpos($clave, 'rk_') === 0 ? 'restringida (recomendada)' : (strpos($clave, 'sk_') === 0 ? 'secreta (mejor usa una restringida)' : 'no reconocida');
$precio = lc_stripe('prices/' . rawurlencode((string)lc_cfg('STRIPE_PRICE')), ['expand' => ['product']], 'GET');
$cuenta = lc_stripe('account', [], 'GET');
$ultimo = lc_meta($db, 'stripe_ultimo_aviso');
$ok = function ($b) { return $b ? '✅' : '⚠️'; };
?>
<div class="box"><h3>💳 Conexión con Stripe</h3>
  <table class="lista" style="margin-top:8px"><tbody>
    <tr><td>Clave</td><td><?= $ok($modo !== 'desconocido') ?> <?= e($tipo) ?> · modo <b><?= e($modo) ?></b><?= $modo === 'prueba' ? ' (los cobros no son reales)' : '' ?></td></tr>
    <tr><td>Cuenta</td><td><?php if (empty($cuenta['error'])): ?><?= $ok(!empty($cuenta['charges_enabled'])) ?> <?= e($cuenta['business_profile']['name'] ?? $cuenta['settings']['dashboard']['display_name'] ?? $cuenta['id'] ?? '') ?> · cobros <?= !empty($cuenta['charges_enabled']) ? 'activos' : 'pendientes' ?> · pagos a tu banco <?= !empty($cuenta['payouts_enabled']) ? 'activos' : 'pendientes' ?>
      <?php else: ?>ℹ️ <?= e(stripos($cuenta['error']['message'] ?? '', 'permission') !== false || ($cuenta['error']['type'] ?? '') === 'invalid_request_error' ? 'La clave no tiene permiso para leer la cuenta (no es necesario).' : ($cuenta['error']['message'] ?? 'Sin respuesta')) ?><?php endif; ?></td></tr>
    <tr><td>Precio de Pro</td><td><?php if (empty($precio['error'])): ?><?= $ok(!empty($precio['active']) && !empty($precio['recurring'])) ?> <?= e($precio['product']['name'] ?? '') ?> · <?= e(strtoupper($precio['currency'] ?? '')) ?> <?= e(number_format(($precio['unit_amount'] ?? 0) / 100, 2)) ?> cada <?= e(['month' => 'mes', 'year' => 'año', 'week' => 'semana', 'day' => 'día'][$precio['recurring']['interval'] ?? ''] ?? '—') ?><?= empty($precio['active']) ? ' (inactivo)' : '' ?>
      <?php else: ?>⚠️ <?= e($precio['error']['message'] ?? 'No se encontró el precio') ?><?php endif; ?></td></tr>
    <tr><td>Avisos (webhook)</td><td><?= $ok((bool)lc_cfg('STRIPE_WEBHOOK_SECRET')) ?> secreto <?= lc_cfg('STRIPE_WEBHOOK_SECRET') ? 'configurado' : 'falta' ?> · último aviso recibido: <b><?= $ultimo ? e(date('j/n/Y g:i a', strtotime($ultimo))) : 'ninguno todavía' ?></b></td></tr>
  </tbody></table>
  <p style="font-size:13px;color:#8a88a0;margin-top:8px">Si todo está en ✅ y el último aviso tiene fecha, LuniCitas está enlazada con tu cuenta de Stripe.</p></div>
