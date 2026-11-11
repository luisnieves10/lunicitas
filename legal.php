<?php
// LuniCitas — Términos y condiciones (legal.php?p=terminos) y Política de privacidad (legal.php?p=privacidad).
// Licencia AGPL-3.0. Si instalas LuniCitas para ofrecerla a otros, pon TUS datos en config.php:
//   $LEGAL_NOMBRE = 'Mi Empresa LLC'; $LEGAL_EMAIL = 'legal@miempresa.com'; $LEGAL_LUGAR = 'Puerto Rico';
// Este texto es una base general, no asesoría legal: revísalo con un abogado de tu jurisdicción.
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

lc_conectar(); // carga config.php si existe
$QUIEN = lc_cfg('LEGAL_NOMBRE', 'Edukatenow Technology Center');
$CORREO = lc_cfg('LEGAL_EMAIL', 'luis.nieves.ramos@icloud.com');
$LUGAR = lc_cfg('LEGAL_LUGAR', 'Puerto Rico');
$PRECIO = lc_cfg('PLAN_PRECIO', '$20 al mes');
$VIGENTE = '11 de noviembre de 2026';
$p = ($_GET['p'] ?? '') === 'privacidad' ? 'privacidad' : 'terminos';
$titulo = $p === 'privacidad' ? 'Política de privacidad' : 'Términos y condiciones';
$mail = '<a href="mailto:' . e($CORREO) . '">' . e($CORREO) . '</a>';
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<base href="<?= e(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\')) ?>/"><title><?= e($titulo) ?> – LuniCitas</title><link rel="icon" href="logo.svg?v=3"><meta name="robots" content="index,follow">
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;justify-content:center;padding:18px;line-height:1.65}
.app{width:100%;max-width:760px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap}.top img{height:38px}
.tabs a{color:#7b5cff;font-size:14px;font-weight:600;text-decoration:none;margin-left:14px}.tabs a.on{color:#1d1b2e;border-bottom:2px solid #7b5cff}
.box{background:#fff;border-radius:18px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
h1{font-size:26px;line-height:1.25}.vig{color:#8a88a0;font-size:13px;margin:4px 0 18px}
h2{font-size:17px;margin:22px 0 6px}p,li{font-size:15px;color:#3a3850}p{margin-bottom:8px}ul{margin:0 0 8px 20px}li{margin-bottom:4px}
a{color:#7b5cff}.clave{background:#f1edff;border-radius:12px;padding:12px 14px;margin:10px 0}
.pie{text-align:center;font-size:13px;color:#6b6980;margin-top:20px}
</style></head><body><div class="app">
<div class="top"><a href="./"><img src="logo.svg?v=3" alt="lunicitas"></a>
  <span class="tabs"><a href="terminos" class="<?= $p === 'terminos' ? 'on' : '' ?>">Términos</a><a href="privacidad" class="<?= $p === 'privacidad' ? 'on' : '' ?>">Privacidad</a></span></div>
<div class="box">
<h1><?= e($titulo) ?></h1><p class="vig">Vigente desde el <?= e($VIGENTE) ?></p>

<?php if ($p === 'terminos'): ?>
<div class="clave"><b>Lo más importante:</b> puedes cancelar el plan Pro cuando quieras desde tu portal, en <b>Plan → Cancelar suscripción</b>. Al cancelar no se te vuelve a cobrar, y conservas Pro hasta el final del periodo que ya pagaste.</div>

<h2>1. Quiénes somos y aceptación</h2>
<p>LuniCitas (el “Servicio”) es una aplicación de reservas de citas que opera <?= e($QUIEN) ?> (“nosotros”), con sede en <?= e($LUGAR) ?>. Al crear una cuenta o usar el Servicio aceptas estos términos. Si los aceptas en nombre de un negocio, confirmas que tienes autoridad para obligarlo y que eres mayor de 18 años. Si no estás de acuerdo, no uses el Servicio.</p>

<h2>2. El Servicio y los planes</h2>
<ul>
  <li><b>Gratis:</b> página de reservas, agenda y 1 persona en el equipo, sin costo.</li>
  <li><b>Prueba:</b> las cuentas nuevas reciben 14 días con todas las funciones, sin pedir tarjeta. Al terminar, la cuenta pasa sola al plan Gratis; no se cobra nada si no eliges Pro.</li>
  <li><b>Pro:</b> <?= e($PRECIO) ?> (USD), con equipo sin límite, recordatorios y análisis.</li>
</ul>
<p>Podemos mejorar, cambiar o retirar funciones. Si un cambio te quita algo importante que ya pagaste, te avisaremos con antelación razonable.</p>

<h2>3. Pagos</h2>
<ul>
  <li>Los pagos los procesa <a href="https://stripe.com/es-us/legal/ssa" target="_blank" rel="noopener">Stripe</a>. Nosotros <b>no vemos ni guardamos</b> los datos de tu tarjeta; solo guardamos el identificador de cliente y de suscripción que nos da Stripe y el estado del pago.</li>
  <li>El plan Pro se cobra <b>por adelantado y se renueva solo cada mes</b> en la misma fecha, al método de pago que registraste, hasta que lo canceles.</li>
  <li>Si pasas a Pro durante la prueba, el primer cobro se hace el día en que termina la prueba.</li>
  <li>Los precios no incluyen impuestos, que se añadirán cuando la ley lo exija.</li>
  <li>Si cambiamos el precio, te avisaremos por correo al menos 30 días antes; el nuevo precio se aplica a partir de la siguiente renovación. Si no estás de acuerdo, puedes cancelar antes.</li>
  <li>Si un cobro falla, Stripe lo reintentará durante unos días y te pediremos actualizar la tarjeta. Si no se logra cobrar, la cuenta pasa al plan Gratis; tus datos y citas se conservan.</li>
</ul>

<h2>4. Cancelación y reembolsos</h2>
<ul>
  <li>Puedes cancelar <b>en cualquier momento, sin penalidad</b>, desde <b>Plan → Cancelar suscripción</b> en tu portal o desde “Administrar pago”. También puedes escribirnos a <?= $mail ?> y la cancelaremos por ti.</li>
  <li>Al cancelar, Stripe <b>deja de cobrarte</b>: no habrá más renovaciones. Conservas Pro hasta el final del periodo ya pagado y luego la cuenta pasa al plan Gratis.</li>
  <li>Puedes deshacer la cancelación antes de que termine el periodo con el botón “Seguir con Pro”.</li>
  <li>Los periodos ya pagados no se reembolsan de forma parcial, salvo que la ley aplicable lo exija o que hayamos cometido un error de cobro. Si crees que hubo un cobro indebido, escríbenos dentro de los 30 días siguientes y lo revisaremos.</li>
  <li>Si pides borrar tu cuenta, cancelaremos primero cualquier suscripción activa.</li>
</ul>

<h2>5. Tus responsabilidades</h2>
<ul>
  <li>Eres responsable de tu usuario y contraseña, y de todo lo que se haga con tu cuenta.</li>
  <li>Eres responsable de los datos de tus clientes que guardas en el Servicio, de tener permiso para usarlos y para contactarlos (por ejemplo, por WhatsApp), y de cumplir las leyes que te apliquen.</li>
  <li>Eres el único responsable de los servicios que prestas a tus clientes, de tus precios, horarios, cancelaciones y de la atención de tus citas. Nosotros solo damos la herramienta.</li>
  <li>No puedes usar el Servicio para actividades ilegales, para enviar mensajes no deseados (spam), para suplantar a otros, ni para intentar dañar, sobrecargar o acceder sin permiso al Servicio.</li>
</ul>

<h2>6. Servicio “tal cual”</h2>
<p>El Servicio se ofrece <b>“tal cual” y “según disponibilidad”</b>. En la medida que lo permita la ley, no damos garantías de ningún tipo, expresas o implícitas, incluidas las de comerciabilidad, idoneidad para un fin concreto y no infracción. No garantizamos que el Servicio funcione sin interrupciones ni errores, ni que los recordatorios o mensajes lleguen siempre. Te recomendamos descargar tus datos con regularidad (Excel o PDF).</p>

<h2>7. Limitación de responsabilidad</h2>
<p>En la máxima medida que permita la ley, ni <?= e($QUIEN) ?> ni sus dueños, empleados o colaboradores serán responsables de daños indirectos, incidentales, especiales, consecuentes o punitivos, ni de pérdida de ganancias, ingresos, clientes, citas o datos, aunque se haya advertido de su posibilidad. Nuestra responsabilidad total por cualquier reclamación relacionada con el Servicio no superará <b>lo que nos hayas pagado en los 12 meses anteriores</b> a la reclamación, o <b>50 USD</b> si usas el plan Gratis. Algunas jurisdicciones no permiten ciertas limitaciones; en ese caso, se aplican solo en la medida permitida.</p>

<h2>8. Indemnización</h2>
<p>Aceptas defender e indemnizar a <?= e($QUIEN) ?> frente a reclamaciones de terceros (incluidos tus clientes) que surjan de tu uso del Servicio, de los datos que subas o de que incumplas estos términos o la ley.</p>

<h2>9. Servicios de terceros</h2>
<p>El Servicio usa proveedores como Stripe (pagos), nuestro proveedor de alojamiento web y, si los activas, WhatsApp de Meta (recordatorios). Su funcionamiento se rige por sus propios términos y no somos responsables de sus fallos.</p>

<h2>10. Suspensión y cierre</h2>
<p>Podemos suspender o cerrar una cuenta que incumpla estos términos, que ponga en riesgo el Servicio o a otros usuarios, o cuando la ley nos lo exija. Si cerramos una cuenta Pro sin que hayas incumplido nada, te devolveremos la parte no usada del periodo pagado. Tú puedes dejar de usar el Servicio cuando quieras.</p>

<h2>11. Código abierto</h2>
<p>El programa de LuniCitas es de código abierto bajo la licencia <a href="https://github.com/luisnieves10/lunicitas/blob/main/LICENSE" target="_blank" rel="noopener">AGPL-3.0</a>. Estos términos regulan el uso del Servicio en línea que nosotros operamos, no la licencia del código.</p>

<h2>12. Cambios a estos términos</h2>
<p>Podemos actualizar estos términos. Si el cambio es importante, te avisaremos por correo o en tu portal al menos 15 días antes. Si sigues usando el Servicio después de esa fecha, aceptas los nuevos términos.</p>

<h2>13. Ley aplicable</h2>
<p>Estos términos se rigen por las leyes de <?= e($LUGAR) ?> y, cuando apliquen, por las leyes federales de los Estados Unidos. Cualquier disputa se presentará ante los tribunales con competencia en <?= e($LUGAR) ?>, salvo que la ley de protección al consumidor de tu lugar de residencia disponga otra cosa. Si alguna parte de estos términos no fuera válida, el resto sigue vigente.</p>

<h2>14. Contacto</h2>
<p><?= e($QUIEN) ?> · <?= e($LUGAR) ?> · <?= $mail ?></p>

<?php else: ?>
<div class="clave"><b>En resumen:</b> solo guardamos lo necesario para que funcionen tus reservas, no vendemos datos, no usamos publicidad ni rastreo, y los datos de tarjeta los maneja Stripe, no nosotros.</div>

<h2>1. Quién es responsable</h2>
<p><?= e($QUIEN) ?> (<?= e($LUGAR) ?>, <?= $mail ?>) opera LuniCitas. Hay dos tipos de personas:</p>
<ul>
  <li><b>Negocios</b> (barberías, salones, centros de estética) que tienen una cuenta. Para sus datos, nosotros somos los responsables.</li>
  <li><b>Clientes</b> que reservan una cita en la página de un negocio. Cada negocio decide cómo usa los datos de sus clientes y es el responsable de ellos; nosotros los guardamos y procesamos por cuenta del negocio.</li>
</ul>

<h2>2. Qué datos recogemos</h2>
<ul>
  <li><b>De los negocios:</b> nombre del negocio y de la persona, correo, usuario, contraseña (guardada cifrada, nunca en texto), dirección, teléfono, horario, servicios, precios, nombres y fotos del equipo.</li>
  <li><b>De los clientes que reservan:</b> nombre, teléfono, servicio, persona elegida, fecha y hora de la cita e idioma. No pedimos cuenta ni contraseña.</li>
  <li><b>De pagos:</b> el identificador de cliente y de suscripción de Stripe y el estado del pago. Los datos de la tarjeta los recibe y guarda solo Stripe; nunca pasan por nuestros servidores.</li>
  <li><b>Técnicos:</b> una cookie de sesión necesaria para mantenerte conectado en el portal, y una huella cifrada de la dirección IP para frenar abusos (intentos de acceso y registros repetidos). No usamos cookies de publicidad ni herramientas de rastreo.</li>
</ul>

<h2>3. Para qué los usamos</h2>
<ul>
  <li>Mostrar las horas libres, registrar citas y que el negocio las gestione.</li>
  <li>Enviar recordatorios de citas, si el negocio los activa.</li>
  <li>Cobrar el plan Pro y mostrar el estado de la suscripción.</li>
  <li>Proteger el Servicio, prevenir fraude y cumplir la ley.</li>
  <li>Avisar a los negocios de cambios importantes en el Servicio, los precios o estas políticas.</li>
</ul>
<p>No vendemos ni alquilamos datos personales, y no los usamos para publicidad.</p>

<h2>4. Con quién los compartimos</h2>
<ul>
  <li><b>El negocio</b> donde reservas ve tus datos de la cita.</li>
  <li><b>Stripe</b>, para procesar los pagos del plan Pro (<a href="https://stripe.com/es-us/privacy" target="_blank" rel="noopener">su política de privacidad</a>).</li>
  <li><b>Nuestro proveedor de alojamiento web</b>, que guarda la base de datos.</li>
  <li><b>WhatsApp (Meta)</b>, solo si el negocio activa los recordatorios automáticos: se envían el nombre, la fecha, la hora y el servicio de la cita al teléfono del cliente.</li>
  <li><b>Autoridades</b>, solo cuando la ley nos obligue.</li>
</ul>
<p>Los datos pueden guardarse en servidores fuera de tu país, con las protecciones que exige la ley.</p>

<h2>5. Cuánto tiempo los guardamos</h2>
<p>Mientras la cuenta del negocio esté activa. Si un negocio pide borrar su cuenta, borramos sus datos y los de sus citas en un máximo de 30 días, salvo lo que la ley nos obligue a conservar (por ejemplo, registros de pagos). Las copias de seguridad se eliminan en su ciclo normal.</p>

<h2>6. Tus derechos</h2>
<p>Puedes pedir ver, corregir, descargar o borrar tus datos, o oponerte a algún uso. Los negocios pueden hacer casi todo desde su portal. Si reservaste una cita, pídeselo primero al negocio; si no te responde, escríbenos a <?= $mail ?> y te ayudaremos. Respondemos en un máximo de 30 días.</p>

<h2>7. Seguridad</h2>
<p>Usamos conexión cifrada (HTTPS), contraseñas cifradas, límites de intentos de acceso y permisos separados para cada negocio. Ningún sistema es 100 % seguro; si ocurre un incidente que afecte tus datos, te avisaremos como exige la ley.</p>

<h2>8. Menores de edad</h2>
<p>Las cuentas de negocio son solo para mayores de 18 años. El Servicio no está dirigido a menores de 13 años y no recogemos sus datos a sabiendas. Si un menor reserva una cita, debe hacerlo con permiso de su madre, padre o tutor.</p>

<h2>9. Cambios a esta política</h2>
<p>Si hacemos un cambio importante, lo publicaremos aquí con la nueva fecha y avisaremos a los negocios por correo o en su portal.</p>

<h2>10. Contacto</h2>
<p><?= e($QUIEN) ?> · <?= e($LUGAR) ?> · <?= $mail ?></p>
<?php endif; ?>
</div>
<p class="pie">LuniCitas · <a href="terminos">Términos</a> · <a href="privacidad">Privacidad</a></p>
</div></body></html>
