<?php
// LuniCitas — recibe los avisos (webhooks) de Stripe y activa o quita el plan Pro. Licencia AGPL-3.0
// En Stripe → Desarrolladores → Webhooks, destino: https://TU-DOMINIO/stripe.php
// Eventos: checkout.session.completed, customer.subscription.created, customer.subscription.updated,
//          customer.subscription.deleted, invoice.paid, invoice.payment_failed,
//          charge.dispute.created, radar.early_fraud_warning.created
//
// Los avisos pueden llegar dos veces o en otro orden. Por eso nunca se confía en el contenido del aviso:
// se vuelve a pedir la suscripción a Stripe y se guarda su estado actual.
require __DIR__ . '/lib.php';
header('Content-Type: application/json');

function fin($codigo, $msg) { http_response_code($codigo); echo json_encode(['ok' => $codigo < 300, 'msg' => $msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fin(405, 'Solo POST');
$db = lc_conectar();
if (!$db) fin(503, 'No instalada');

$payload = file_get_contents('php://input');
if (!lc_stripe_firma_valida($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', (string)lc_cfg('STRIPE_WEBHOOK_SECRET'))) fin(400, 'Firma no válida');
$ev = json_decode($payload, true);
lc_meta_set($db, 'stripe_ultimo_aviso', date('Y-m-d H:i:s')); // para el panel "Conexión con Stripe"
$obj = $ev['data']['object'] ?? [];
$tipo = $ev['type'] ?? '';

// Busca el negocio: primero por la suscripción o el cliente de Stripe que ya tenemos guardados;
// solo si no hay (el primer pago) usa el número que mandamos en client_reference_id / metadata.
function negocioDe($db, $subId, $clienteId, $obj = []) {
  foreach (['stripe_sub' => $subId, 'stripe_cliente' => $clienteId] as $col => $val) {
    if (!$val) continue;
    $q = $db->prepare("SELECT id FROM negocios WHERE $col = ?"); $q->execute([$val]);
    if ($id = (int)$q->fetchColumn()) return $id;
  }
  $id = (int)($obj['client_reference_id'] ?? $obj['metadata']['negocio'] ?? 0);
  if ($id) { $q = $db->prepare('SELECT id FROM negocios WHERE id = ?'); $q->execute([$id]); return (int)$q->fetchColumn(); }
  return 0;
}

// Pide la suscripción a Stripe y deja el negocio igual que ella. Devuelve [código HTTP, mensaje].
function sincronizar($db, $subId, $obj = []) {
  if (!$subId) return [200, 'Sin suscripción'];
  $sub = lc_stripe('subscriptions/' . rawurlencode($subId), [], 'GET');
  if (!empty($sub['error'])) // si no existe, no hay nada que reintentar; si es otro error, con 500 Stripe reintenta
    return [($sub['error']['code'] ?? '') === 'resource_missing' ? 200 : 500, 'Stripe: ' . $sub['error']['message']];
  $cliente = is_array($sub['customer'] ?? null) ? $sub['customer']['id'] : (string)($sub['customer'] ?? '');
  $id = negocioDe($db, $sub['id'], $cliente, $obj + ['metadata' => $sub['metadata'] ?? []]);
  if (!$id) return [200, 'Negocio no encontrado'];

  $estado = (string)($sub['status'] ?? '');
  // past_due: el cobro falló pero Stripe sigue reintentando; se mantiene Pro mientras tanto.
  if (in_array($estado, ['active', 'trialing', 'past_due'], true)) {
    $fin = $sub['items']['data'][0]['current_period_end'] ?? ($sub['current_period_end'] ?? null);
    $hasta = $fin ? date('Y-m-d', (int)$fin + 3 * 86400) : date('Y-m-d', strtotime('+35 days')); // 3 días de gracia
    // Cancelada pero aún pagada: Stripe ya no cobrará más y la termina en cancel_at.
    $cancelaEn = $sub['cancel_at'] ?? (!empty($sub['cancel_at_period_end']) ? $fin : null);
    $cancela = $cancelaEn ? date('Y-m-d', (int)$cancelaEn) : null;
    $db->prepare("UPDATE negocios SET plan = 'pro', stripe_cliente = ?, stripe_sub = ?, stripe_estado = ?, stripe_cancela = ?, pagado_hasta = ?, prueba_hasta = NULL WHERE id = ?")
       ->execute([$cliente, $sub['id'], $estado, $cancela, $hasta, $id]);
    return [200, "Pro ($estado)"];
  }
  // canceled, unpaid, incomplete_expired…: pasa a gratis, pero solo si es la suscripción que tenemos guardada
  // (así una suscripción vieja cancelada no quita el Pro de una nueva).
  $q = $db->prepare("UPDATE negocios SET plan = 'gratis', stripe_sub = '', stripe_estado = ?, stripe_cancela = NULL, pagado_hasta = NULL WHERE id = ? AND (stripe_sub = ? OR stripe_sub = '')");
  $q->execute([$estado, $id, $sub['id']]);
  return [200, $q->rowCount() ? "Pasó a gratis ($estado)" : 'Suscripción vieja, sin cambios'];
}

// La suscripción de una factura: en versiones nuevas de la API está en parent.subscription_details.
function subDeFactura($inv) {
  return (string)($inv['parent']['subscription_details']['subscription'] ?? $inv['subscription'] ?? '');
}

switch ($tipo) {
  case 'checkout.session.completed':
    if (($obj['mode'] ?? '') !== 'subscription') fin(200, 'Evento ignorado');
    if (($obj['payment_status'] ?? '') === 'unpaid') fin(200, 'Pago pendiente'); // llegará customer.subscription.updated
    fin(...sincronizar($db, (string)($obj['subscription'] ?? ''), $obj));

  case 'customer.subscription.created':
  case 'customer.subscription.updated':
  case 'customer.subscription.deleted':
    fin(...sincronizar($db, (string)($obj['id'] ?? ''), $obj));

  case 'invoice.paid':           // renovación cobrada: alarga pagado_hasta
  case 'invoice.payment_failed': // cobro fallido: queda past_due y el portal muestra el aviso
    fin(...sincronizar($db, subDeFactura($obj)));

  case 'charge.dispute.created':
  case 'radar.early_fraud_warning.created':
    // Hay que responder en el Dashboard de Stripe (Pagos → Disputas / Radar) cuanto antes.
    $cargo = lc_stripe('charges/' . rawurlencode((string)($obj['charge'] ?? '')), [], 'GET');
    $cliente = (string)($cargo['customer'] ?? '');
    $id = $cliente ? negocioDe($db, '', $cliente) : 0;
    error_log("LuniCitas Stripe: $tipo en el cargo " . ($obj['charge'] ?? '?') . " (negocio $id). Revisa el Dashboard de Stripe.");
    if ($id) lc_meta_set($db, 'stripe_alerta', date('Y-m-d H:i') . " · $tipo · negocio $id · " . ($obj['charge'] ?? ''));
    fin(200, 'Alerta registrada');
}

fin(200, 'Evento ignorado');
