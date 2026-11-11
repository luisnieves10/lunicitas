<?php
// LuniCitas — la app de Android canjea aquí el pase de un solo uso que recibe después de entrar con Google o Microsoft
// en el navegador. El pase dura 2 minutos y solo sirve una vez. Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$db = lc_conectar();
if (!$db) { header('Location: install.php'); exit; }
lc_sesion();

$pase = (string)($_GET['t'] ?? '');
$nid = 0;
if (preg_match('/^[a-f0-9]{64}$/', $pase)) {
  $h = hash('sha256', $pase);
  $q = $db->prepare('SELECT negocio_id FROM app_pases WHERE hash = ? AND expira > NOW()'); $q->execute([$h]);
  $nid = (int)$q->fetchColumn();
  $db->prepare('DELETE FROM app_pases WHERE hash = ?')->execute([$h]); // un solo uso, sirva o no
}
if ($nid) {
  $q = $db->prepare('SELECT activo FROM negocios WHERE id = ?'); $q->execute([$nid]);
  if ((int)$q->fetchColumn() === 1) {
    session_regenerate_id(true);
    $_SESSION['neg'] = $nid; $_SESSION['super'] = false;
    header('Location: admin.php'); exit;
  }
}
$_SESSION['aviso'] = 'El enlace para entrar caducó. Inténtalo otra vez.';
header('Location: admin.php');
