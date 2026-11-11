<?php
// LuniCitas — instalador. Crea config.php, las tablas y el primer negocio. Licencia AGPL-3.0
require __DIR__ . '/lib.php';
header('X-Frame-Options: DENY');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$instalado = lc_ruta_config() !== null;
$error = ''; $listo = null;
$f = array_map(function ($x) { return trim((string)$x); }, $_POST) + ['host' => 'localhost', 'tipo' => 'barberia'];

if (!$instalado && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $slug = lc_slug(($f['slug'] ?? '') ?: ($f['negocio'] ?? ''));
  if (mb_strlen($f['negocio'] ?? '') < 2) $error = 'Escribe el nombre de tu negocio.';
  elseif (!preg_match('/^[a-z0-9-]{3,40}$/', $slug)) $error = 'El enlace debe tener de 3 a 40 letras, números o guiones.';
  elseif (mb_strlen($f['usuario'] ?? '') < 3) $error = 'El usuario debe tener al menos 3 caracteres.';
  elseif (strlen($_POST['clave'] ?? '') < 8) $error = 'La contraseña del portal debe tener al menos 8 caracteres.';
  else {
    try {
      new PDO("mysql:host={$f['host']};dbname={$f['bd']};charset=utf8mb4", $f['bd_usuario'], $_POST['bd_clave'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $ex) { $error = 'No pude conectar con la base de datos. Revisa el nombre, el usuario y la contraseña que creaste en tu hosting.'; }
  }
  if (!$error) {
    $config = "<?php\n// LuniCitas — datos secretos. No subas este archivo a GitHub.\n"
      . '$DB_HOST = ' . var_export($f['host'], true) . ";\n"
      . '$DB_NAME = ' . var_export($f['bd'], true) . ";\n"
      . '$DB_USER = ' . var_export($f['bd_usuario'], true) . ";\n"
      . '$DB_PASS = ' . var_export($_POST['bd_clave'] ?? '', true) . ";\n"
      . '$ADMIN_USER = ' . var_export($f['usuario'], true) . ";\n"
      . '$ADMIN_HASH = ' . var_export(password_hash($_POST['clave'], PASSWORD_DEFAULT), true) . ";\n"
      . "// Opcional, recordatorios automáticos por WhatsApp (ver README):\n// \$WA_TOKEN = '';\n// \$WA_PHONE_ID = '';\n";
    $fuera = dirname(__DIR__) . '/config.php';
    $ruta = (is_writable(dirname(__DIR__)) && @file_put_contents($fuera, $config) !== false) ? $fuera
          : (@file_put_contents(__DIR__ . '/config.php', $config) !== false ? __DIR__ . '/config.php' : null);
    if (!$ruta) {
      $error = 'No pude guardar config.php. Crea ese archivo a mano en la carpeta de arriba de public_html con este contenido:';
      $manual = $config;
    } else {
      @chmod($ruta, 0600);
      try {
        $db = lc_conectar();
        $q = $db->prepare('SELECT COUNT(*) FROM negocios WHERE slug = ? OR usuario = ?'); $q->execute([$slug, $f['usuario']]);
        if (!$q->fetchColumn()) lc_crear_negocio($db, $f['negocio'], isset(LC_TIPOS[$f['tipo']]) ? $f['tipo'] : 'barberia', $slug, $f['usuario'], $_POST['clave'], $f['persona'] ?? '');
        $listo = $slug;
      } catch (Exception $ex) { $error = 'Se guardó config.php, pero no se pudieron crear las tablas: ' . $ex->getMessage(); }
    }
  }
}
$base = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Instalar LuniCitas</title><link rel="icon" href="logo.svg?v=3">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;padding:18px}
.app{width:100%;max-width:520px}.app>img{height:38px;margin-bottom:16px}
.box{background:#fff;border-radius:18px;padding:18px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:12px}h1{font-size:22px;margin-bottom:6px}h3{font-size:15px;margin-bottom:4px}
p.s{color:#6b6980;font-size:14px;margin-bottom:12px}label{font-size:13px;color:#6b6980;display:block;margin-bottom:3px}
input,select{width:100%;padding:12px;border-radius:12px;border:2px solid #e4e2ee;font-size:15px;margin-bottom:10px;font-family:inherit}
.btn{display:block;width:100%;padding:15px;border:none;border-radius:16px;background:linear-gradient(90deg,#7b5cff,#1fd1c1);color:#fff;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;margin-top:8px}
.err{background:#fdecec;color:#c0262c;border-radius:12px;padding:12px;margin-bottom:12px;font-size:14px}pre{background:#1d1b2e;color:#e5e5f0;padding:12px;border-radius:12px;font-size:12px;overflow:auto;margin-top:8px}
.ok{text-align:center}.ok .c{font-size:56px}.enl{background:#f1edff;border-radius:12px;padding:10px;margin:10px 0;word-break:break-all}
</style></head><body><div class="app"><img src="logo.svg?v=3" alt="lunicitas">
<?php if ($listo): ?>
  <div class="box ok"><div class="c">🎉</div><h1>¡LuniCitas está lista!</h1>
    <p class="s">Este es el enlace de reservas de tu negocio:</p><div class="enl"><?= e($base . '/' . $listo) ?></div>
    <a class="btn" href="<?= e($base . '/' . $listo) ?>">Ver mi página</a><a class="btn" href="admin.php">Entrar al portal</a>
    <p class="s" style="margin-top:14px">Por seguridad, puedes borrar <b>install.php</b>; ya no hace falta.</p></div>
<?php elseif ($instalado): ?>
  <div class="box"><h1>Ya está instalada</h1><p class="s">LuniCitas ya tiene su archivo de configuración. Puedes borrar install.php.</p>
    <a class="btn" href="admin.php">Ir al portal</a></div>
<?php else: ?>
  <div class="box"><h1>Instalar LuniCitas</h1><p class="s">Tarda 2 minutos. Antes, crea una base de datos MySQL vacía en tu hosting (en Hostinger: hPanel → Bases de datos → Nueva base de datos MySQL) y ten a mano su nombre, usuario y contraseña.</p>
    <?php if ($error): ?><div class="err"><?= e($error) ?><?php if (!empty($manual)): ?><pre><?= e($manual) ?></pre><?php endif; ?></div><?php endif; ?>
    <form method="post">
      <h3>1. Base de datos</h3><p class="s">Los datos que te dio tu hosting.</p>
      <label>Servidor</label><input name="host" value="<?= e($f['host']) ?>" required>
      <label>Nombre de la base de datos</label><input name="bd" value="<?= e($f['bd'] ?? '') ?>" required>
      <label>Usuario de la base de datos</label><input name="bd_usuario" value="<?= e($f['bd_usuario'] ?? '') ?>" required autocomplete="off">
      <label>Contraseña de la base de datos</label><input name="bd_clave" type="password" autocomplete="new-password">
      <h3 style="margin-top:8px">2. Tu negocio</h3><p class="s">Luego podrás cambiar todo desde el portal.</p>
      <label>Nombre del negocio</label><input name="negocio" value="<?= e($f['negocio'] ?? '') ?>" placeholder="Barbería El Estilo" required>
      <label>Tipo</label><select name="tipo"><?php foreach (LC_TIPOS as $k => $n): ?><option value="<?= $k ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
      <label>Enlace (opcional)</label><input name="slug" value="<?= e($f['slug'] ?? '') ?>" placeholder="elestilo">
      <label>Tu nombre (primera persona del equipo)</label><input name="persona" value="<?= e($f['persona'] ?? '') ?>" placeholder="Carlos">
      <h3 style="margin-top:8px">3. Acceso al portal</h3><p class="s">Con esto entras a admin.php. Esta cuenta también puede crear otros negocios.</p>
      <label>Usuario</label><input name="usuario" value="<?= e($f['usuario'] ?? '') ?>" required autocomplete="off">
      <label>Contraseña (mínimo 8)</label><input name="clave" type="password" minlength="8" required autocomplete="new-password">
      <button class="btn">Instalar</button>
    </form></div>
<?php endif; ?>
</div></body></html>
