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
$DIAS = LC_DIAS_PRUEBA;
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
<div class="clave"><b>Lo más importante:</b> tienes <b><?= $DIAS ?> días de prueba gratis</b>. Al crear tu cuenta te pedimos tu tarjeta en Stripe, pero <b>no se cobra nada hasta que termina la prueba</b>; si cancelas antes en <b>Plan → Cancelar suscripción</b>, no pagas nada. Si decides no poner la tarjeta, puedes usar tus días gratis igual, y al terminar <b>tu cuenta se cierra</b> hasta que pagues.</div>

<h2>1. Quiénes somos y aceptación</h2>
<p>LuniCitas (el “Servicio”) es una aplicación de reservas de citas que opera <?= e($QUIEN) ?> (“nosotros”), con sede en <?= e($LUGAR) ?>. Al crear una cuenta o usar el Servicio aceptas estos términos. Si los aceptas en nombre de un negocio, confirmas que tienes autoridad para obligarlo y que eres mayor de 18 años. Si no estás de acuerdo, no uses el Servicio.</p>

<h2>2. Prueba gratis y plan Pro</h2>
<ul>
  <li><b>Prueba gratis:</b> cada negocio nuevo recibe <b><?= $DIAS ?> días</b> con todas las funciones. Al registrarte te llevamos a poner tu tarjeta en Stripe; puedes hacerlo entonces o más tarde. La prueba es una sola vez por negocio.</li>
  <li><b>Al terminar la prueba:</b> si no has añadido tu tarjeta, <b>la aplicación se cierra para tu negocio</b>: solo podrás entrar a la pantalla de pago, tu página de reservas dejará de aceptar citas y no se enviarán recordatorios. Te avisamos dentro del portal durante la prueba. Tus datos se conservan según la Política de privacidad, y al pagar todo vuelve como estaba.</li>
  <li><b>Pro:</b> <?= e($PRECIO) ?> (USD), con todas las funciones.</li>
</ul>
<p>Podemos mejorar, cambiar o retirar funciones. Si un cambio te quita algo importante que ya pagaste, te avisaremos con antelación razonable.</p>

<h2>3. Pagos</h2>
<ul>
  <li>Los pagos los procesa <a href="https://stripe.com/es-us/legal/ssa" target="_blank" rel="noopener">Stripe</a>. Nosotros <b>no vemos ni guardamos</b> los datos de tu tarjeta; solo guardamos el identificador de cliente y de suscripción que nos da Stripe y el estado del pago.</li>
  <li>El plan Pro se cobra <b>por adelantado y se renueva solo cada mes</b> en la misma fecha, al método de pago que registraste, hasta que lo canceles.</li>
  <li>Si pones tu tarjeta durante la prueba, <b>el primer cobro se hace automáticamente el día en que termina la prueba</b>, salvo que canceles antes. Stripe te muestra esa fecha antes de que confirmes.</li>
  <li>Los precios no incluyen impuestos, que se añadirán cuando la ley lo exija.</li>
  <li>Si cambiamos el precio, te avisaremos por correo al menos 30 días antes; el nuevo precio se aplica a partir de la siguiente renovación. Si no estás de acuerdo, puedes cancelar antes.</li>
  <li>Si un cobro falla, Stripe lo reintentará durante unos días y te pediremos actualizar la tarjeta. Si no se logra cobrar, la cuenta se cierra igual que al terminar la prueba, hasta que pagues.</li>
</ul>

<h2>4. Cancelación y reembolsos</h2>
<ul>
  <li>Puedes cancelar <b>en cualquier momento, sin penalidad</b>, desde <b>Plan → Cancelar suscripción</b> en tu portal o desde “Administrar pago”. También puedes escribirnos a <?= $mail ?> y la cancelaremos por ti.</li>
  <li>Al cancelar, Stripe <b>deja de cobrarte</b>: no habrá más renovaciones. Conservas Pro hasta el final del periodo ya pagado y luego la cuenta se cierra.</li>
  <li>Puedes deshacer la cancelación antes de que termine el periodo con el botón “Seguir con Pro”.</li>
  <li>Los pagos no son reembolsables, tampoco de forma parcial, salvo que la ley aplicable lo exija. Si crees que hubo un error de cobro, escríbenos dentro de los 30 días siguientes.</li>
  <li>Si pides borrar tu cuenta, cancelaremos primero cualquier suscripción activa.</li>
</ul>

<h2>5. Tus responsabilidades</h2>
<ul>
  <li>Eres responsable de tu usuario y contraseña, y de todo lo que se haga con tu cuenta.</li>
  <li>Eres responsable de los datos de tus clientes que guardas en el Servicio, de tener permiso para usarlos y para contactarlos (por ejemplo, por WhatsApp), y de cumplir las leyes que te apliquen.</li>
  <li>Eres el único responsable de los servicios que prestas a tus clientes, de tus precios, horarios, cancelaciones y de la atención de tus citas. LuniCitas solo es una herramienta y no es parte de la relación entre tú y tus clientes.</li>
  <li>Eres responsable de pagar a tiempo para mantener tu cuenta abierta, de avisar a tus clientes si dejas de usar el Servicio o si tu cuenta se cierra, y de descargar tus datos (Excel o PDF) mientras tu cuenta esté abierta.</li>
  <li>No puedes usar el Servicio para actividades ilegales, para enviar mensajes no deseados (spam), para suplantar a otros, ni para intentar dañar, sobrecargar o acceder sin permiso al Servicio.</li>
</ul>

<h2>6. Servicio “tal cual”</h2>
<p>El Servicio se ofrece <b>“tal cual” y “según disponibilidad”</b>. En la medida que lo permita la ley, no damos garantías de ningún tipo, expresas o implícitas, incluidas las de comerciabilidad, idoneidad para un fin concreto y no infracción. No garantizamos que el Servicio funcione sin interrupciones ni errores, ni que los recordatorios o mensajes lleguen siempre. Te recomendamos descargar tus datos con regularidad (Excel o PDF).</p>

<h2>7. Personas que reservan una cita</h2>
<ul>
  <li>Si reservas en la página de un negocio, tu cita es un acuerdo entre tú y ese negocio. LuniCitas solo es la herramienta de reservas y <b>no es parte de ese acuerdo</b>: no presta el servicio, no fija los precios y no responde por la atención, los cambios, las cancelaciones o los cobros del negocio.</li>
  <li>El negocio puede cambiar o cancelar tu cita. Si tienes dudas, comunícate directamente con él.</li>
  <li>Al reservar aceptas estos términos y la Política de privacidad.</li>
</ul>

<h2>8. App instalada, archivos de calendario y tu dispositivo</h2>
<ul>
  <li>Puedes instalar LuniCitas, o la página de un negocio, en tu celular, tableta o computadora (Android, iPhone, iPad, Windows u otros), y guardar tus citas en tu calendario (Apple, Google, Outlook u otros). Hacerlo es <b>opcional y bajo tu propio riesgo</b>.</li>
  <li>La app instalada es la misma página web que abre tu navegador; la instalación la hacen tu navegador y tu sistema operativo, no nosotros. Se ofrece “tal cual”, sin garantía de que funcione en todos los dispositivos o versiones.</li>
  <li>En la máxima medida que permita la ley, <b>LuniCitas y <?= e($QUIEN) ?> no son responsables de ningún daño, fallo, pérdida de datos, mal funcionamiento, consumo de batería o datos, ni de ningún otro perjuicio a tu dispositivo, a sus programas o a tu información</b> que resulte de instalar, usar o desinstalar la app, de añadir citas a tu calendario o de las actualizaciones de tu navegador o sistema.</li>
  <li>Los datos que se guardan en tu calendario son una copia: si el negocio cambia la cita, tu calendario no se actualiza solo. Confirma siempre con el negocio.</li>
  <li>Puedes desinstalar la app o borrar el evento del calendario cuando quieras, como cualquier otra app o evento.</li>
</ul>

<h2>9. Interrupciones y fin del Servicio</h2>
<ul>
  <li><b>Interrupciones:</b> el Servicio puede dejar de funcionar de forma temporal, total o parcial, con o sin aviso, por mantenimiento, actualizaciones, fallos técnicos, ataques informáticos, o fallos del alojamiento, de Internet, de Stripe, de WhatsApp o de otros proveedores.</li>
  <li><b>Causas fuera de nuestro control:</b> no somos responsables de ningún retraso o fallo causado por huracanes, tormentas, terremotos, inundaciones, apagones, fallos de las telecomunicaciones, pandemias, actos de gobierno, guerras, huelgas u otras causas que no podemos controlar razonablemente.</li>
  <li><b>Fin del Servicio:</b> podemos cambiar, suspender o terminar el Servicio, en todo o en parte, en cualquier momento y por cualquier razón. Cuando sea posible, avisaremos con antelación por correo o en el portal para que descargues tus datos.</li>
  <li><b>Tus pagos si el Servicio termina:</b> cancelaremos todas las suscripciones para que <b>Stripe no te vuelva a cobrar</b>. No hay reembolsos por el tiempo restante del periodo ya pagado, salvo que la ley aplicable lo exija.</li>
  <li><b>Tus datos:</b> eres responsable de tener tu propia copia de tus citas y datos. Después del cierre del Servicio podemos borrar todos los datos sin más aviso.</li>
  <li>Como LuniCitas es de código abierto, puedes instalar tu propia copia en otro alojamiento si el Servicio deja de estar disponible.</li>
</ul>

<h2>10. Limitación de responsabilidad</h2>
<p>En la máxima medida que permita la ley, ni LuniCitas ni <?= e($QUIEN) ?>, ni sus dueños, empleados o colaboradores, serán responsables de daños directos o indirectos, incidentales, especiales, consecuentes o punitivos, ni de pérdida de ganancias, ingresos, clientes, citas o datos, incluidos los que resulten de <b>el cierre de tu cuenta al terminar la prueba, por falta de pago o por cancelación</b>, de interrupciones, fallos o la terminación del Servicio, de Stripe, del alojamiento o de WhatsApp, o de citas que tus clientes no puedan hacer, aunque se haya advertido de su posibilidad. Si aun así la ley nos hiciera responsables, nuestra responsabilidad total no superará <b>lo que nos hayas pagado en los 3 meses anteriores</b> a la reclamación, o <b>0 USD</b> si no has pagado nada. Algunas jurisdicciones no permiten ciertas limitaciones; en ese caso, se aplican solo en la medida permitida.</p>

<h2>11. Indemnización</h2>
<p>Aceptas defender, indemnizar y mantener libre de responsabilidad a LuniCitas y a <?= e($QUIEN) ?> frente a reclamaciones, daños, multas y gastos (incluidos honorarios de abogados) de terceros, incluidos tus clientes, que surjan de tu negocio, de tu uso del Servicio, del cierre de tu cuenta, de los datos que subas o de que incumplas estos términos o la ley.</p>

<h2>12. Servicios de terceros</h2>
<p>El Servicio usa proveedores como Stripe (pagos), nuestro proveedor de alojamiento web y, si los activas, WhatsApp de Meta (recordatorios). Su funcionamiento se rige por sus propios términos y no somos responsables de sus fallos.</p>

<h2>13. Suspensión y cierre</h2>
<p>La cuenta se cierra sola al terminar la prueba sin tarjeta, cuando no se logra cobrar o al terminar una suscripción cancelada. También podemos suspender o cerrar una cuenta, con o sin aviso, si incumple estos términos, pone en riesgo el Servicio o a otros usuarios, o cuando la ley lo exija. Tú puedes dejar de usar el Servicio cuando quieras.</p>

<h2>14. Código abierto</h2>
<p>El programa de LuniCitas es de código abierto bajo la licencia <a href="https://github.com/luisnieves10/lunicitas/blob/main/LICENSE" target="_blank" rel="noopener">AGPL-3.0</a>. Estos términos regulan el uso del Servicio en línea que nosotros operamos, no la licencia del código.</p>

<h2>15. Cambios a estos términos</h2>
<p>Podemos actualizar estos términos. Si el cambio es importante, te avisaremos por correo o en tu portal al menos 15 días antes. Si sigues usando el Servicio después de esa fecha, aceptas los nuevos términos.</p>

<h2>16. Ley aplicable</h2>
<p>Estos términos se rigen por las leyes de <?= e($LUGAR) ?> y, cuando apliquen, por las leyes federales de los Estados Unidos. Cualquier disputa se presentará ante los tribunales con competencia en <?= e($LUGAR) ?>, salvo que la ley de protección al consumidor de tu lugar de residencia disponga otra cosa. Si alguna parte de estos términos no fuera válida, el resto sigue vigente.</p>

<h2>17. Contacto</h2>
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
  <li><b>Si entras con Google o Microsoft:</b> recibimos de ellos tu nombre, tu correo y un identificador de tu cuenta, solo para reconocerte cuando entras. No recibimos tu contraseña ni accedemos a tu correo, tus contactos ni tus archivos.</li>
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
  <li><b>Google y Microsoft</b>, solo si eliges entrar con ellos: confirman quién eres según sus propias políticas de privacidad.</li>
  <li><b>Stripe</b>, para procesar los pagos del plan Pro (<a href="https://stripe.com/es-us/privacy" target="_blank" rel="noopener">su política de privacidad</a>).</li>
  <li><b>Nuestro proveedor de alojamiento web</b>, que guarda la base de datos.</li>
  <li><b>WhatsApp (Meta)</b>, solo si el negocio activa los recordatorios automáticos: se envían el nombre, la fecha, la hora y el servicio de la cita al teléfono del cliente.</li>
  <li><b>Autoridades</b>, solo cuando la ley nos obligue.</li>
</ul>
<p>Los datos pueden guardarse en servidores fuera de tu país, con las protecciones que exige la ley.</p>

<h2>5. Cuánto tiempo los guardamos</h2>
<p>Mientras la cuenta del negocio exista. Si la cuenta se cierra (al terminar la prueba, por falta de pago o por cancelación), guardamos los datos hasta 90 días para que el negocio pueda volver; después podemos borrarlos sin más aviso. Si un negocio pide borrar su cuenta, borramos sus datos y los de sus citas en un máximo de 30 días, salvo lo que la ley nos obligue a conservar (por ejemplo, registros de pagos). Las copias de seguridad se eliminan en su ciclo normal.</p>

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
