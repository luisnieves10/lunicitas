<?php
// LuniCitas — recibe los avisos (webhooks) de Stripe y activa o quita el plan Pro. Licencia AGPL-3.0
// En Stripe → Desarrolladores → Webhooks, destino: https://TU-DOMINIO/stripe.php
// Eventos: checkout.session.completed, customer.subscription.updated, customer.subscription.deleted
require __DIR__ . '/lib.php';
header('Content-Type: application/json');

function fin($codigo, $msg) { http_response_code($codigo); echo json_encode(['ok' => $codigo < 300, 'msg' => $msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fin(405, 'Solo POST');
$db = lc_conectar();
if (!$db) fin(503, 'No instalada');

$payload = file_get_contents('php://input');
if (!lc_stripe_firma_valida($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', (string)lc_cfg('STRIPE_WEBHOOK_SECRET'))) fin(400, 'Firma no válida');
$ev = json_decode($payload, true);
$obj = $ev['data']['object'] ?? [];
$tipo = $ev['type'] ?? '';

// Busca el negocio por la suscripción, el cliente o el número que guardamos en metadata
function negocioDe($db, $obj) {
  $id = (int)($obj['metadata']['negocio'] ?? $obj['client_reference_id'] ?? 0);
  if ($id) return $id;
  foreach (['stripe_sub' => $obj['id'] ?? '', 'stripe_cliente' => $obj['customer'] ?? ''] as $col => $val) {
    if (!$val) continue;
    $q = $db->prepare("SELECT id FROM negocios WHERE $col = ?"); $q->execute([$val]);
    if ($id = (int)$q->fetchColumn()) return $id;
  }
  return 0;
}
// Fecha hasta la que está pagado, con 3 días de gracia. Stripe la da en el objeto o, en versiones nuevas, en cada artículo.
function pagadoHasta($sub) {
  $t = $sub['current_period_end'] ?? ($sub['items']['data'][0]['current_period_end'] ?? null);
  return $t ? date('Y-m-d', (int)$t + 3 * 86400) : date('Y-m-d', strtotime('+35 days'));
}

if ($tipo === 'checkout.session.completed' && ($obj['mode'] ?? '') === 'subscription') {
  $id = negocioDe($db, $obj);
  if (!$id) fin(200, 'Negocio no encontrado');
  $db->prepare("UPDATE negocios SET plan = 'pro', stripe_cliente = ?, stripe_sub = ?, pagado_hasta = ? WHERE id = ?")
     ->execute([(string)($obj['customer'] ?? ''), (string)($obj['subscription'] ?? ''), date('Y-m-d', strtotime('+35 days')), $id]);
  fin(200, 'Pro activado');
}

if (in_array($tipo, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true)) {
  $id = negocioDe($db, $obj);
  if (!$id) fin(200, 'Negocio no encontrado');
  $activa = $tipo !== 'customer.subscription.deleted' && in_array($obj['status'] ?? '', ['active', 'trialing', 'past_due'], true);
  if ($activa) {
    $db->prepare("UPDATE negocios SET plan = 'pro', stripe_cliente = ?, stripe_sub = ?, pagado_hasta = ? WHERE id = ?")
       ->execute([(string)($obj['customer'] ?? ''), (string)($obj['id'] ?? ''), pagadoHasta($obj), $id]);
    fin(200, 'Pro al día');
  }
  $db->prepare("UPDATE negocios SET plan = 'gratis', stripe_sub = '', pagado_hasta = NULL WHERE id = ? AND (stripe_sub = ? OR stripe_sub = '')")
     ->execute([$id, (string)($obj['id'] ?? '')]);
  fin(200, 'Pasó a gratis');
}

fin(200, 'Evento ignorado');
