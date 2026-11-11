<p align="center"><img src="logo.svg" alt="LuniCitas" height="70"></p>

<p align="center"><b>Reservas de citas, de código abierto, para barberías, salones de belleza y centros de estética.</b><br>
Sencilla, bonita y pensada para el celular. En español, inglés y portugués.</p>

<p align="center">
  <a href="https://www.hostinger.com/deploy?template=luisnieves10/lunicitas"><img src="https://assets.hostinger.com/vps/deploy.svg" alt="Deploy on Hostinger"></a>
</p>

<p align="center"><a href="https://lunicitas.com">Ver demo en vivo</a> · <a href="#instalar-en-2-minutos">Instalar</a> · <a href="#english">English</a></p>

![Página de reservas en el celular](docs/reservas.png)

## ¿Qué hace?

**Para tus clientes**
- Reservan en menos de un minuto, sin crear cuenta: servicio, persona, día y hora.
- Solo ven horas realmente libres: la app tiene en cuenta la duración de cada servicio, el horario, el descanso del almuerzo y los días libres.
- Opción “Cualquiera”: la app asigna a quien esté libre.
- Español, inglés y portugués, con horario de 12 o 24 horas.

**Para el dueño** (portal en `admin.php`)
- Agenda por día, semana y mes. Marca quién asistió y quién no.
- 🔔 Recordatorio por WhatsApp en un toque, con el mensaje ya escrito. Opcional: recordatorios automáticos.
- Servicios editables: nombre, precio, duración y foto.
- Equipo editable: nombres, fotos y especialidades.
- Horario por día, descansos y días libres (de todo el negocio o de una sola persona).
- Análisis del mes con gráficas, comparación con el mes anterior y descarga a Excel o PDF.
- Tipo de negocio: barbería, salón de belleza o estética. Los textos se adaptan (barbero, estilista, especialista).

**Varios negocios en una sola instalación.** Cada uno con su enlace (`tudominio.com/mi-barberia`) y sus datos separados. Ideal para ofrecer LuniCitas como servicio.

![Portal del dueño](docs/portal.png)

## Instalar en 2 minutos

Necesitas un hosting con PHP 7.4 o superior y MySQL (cualquier plan de Hostinger sirve).

1. **Crea una base de datos.** En Hostinger: hPanel → Bases de datos → *Nueva base de datos MySQL*. Anota el nombre, el usuario y la contraseña.
2. **Sube los archivos.** Lo más fácil: hPanel → Avanzado → *Git* → repositorio `https://github.com/luisnieves10/lunicitas`, rama `main`, carpeta `public_html`. También puedes descargar el ZIP y subirlo con el Administrador de archivos.
3. **Abre tu dominio.** Te llevará solo a `install.php`. Escribe los datos de la base de datos, el nombre de tu negocio y tu usuario del portal.
4. ¡Listo! Comparte tu enlace de reservas y entra al portal en `tudominio.com/admin.php`.

El instalador guarda `config.php` fuera de `public_html` cuando puede, para que nadie lo vea desde el navegador.

### Actualizar

Si instalaste desde Git, cada actualización llega con un *pull* (o sola, si activaste el despliegue automático). La base de datos se actualiza sola la primera vez que se abre la app; tus citas no se tocan.

### Recordatorios automáticos por WhatsApp (opcional)

El botón **Recordar por WhatsApp** del portal funciona sin configurar nada. Si además quieres que salgan solos el día antes:

1. Crea una app en [Meta for Developers](https://developers.facebook.com/) con WhatsApp Cloud API y una plantilla aprobada llamada `recordatorio_cita` con 4 variables: nombre, negocio, fecha y hora, servicio.
2. Añade a `config.php`:
   ```php
   $WA_TOKEN = 'tu token';
   $WA_PHONE_ID = 'id de tu número';
   ```
3. En hPanel → Avanzado → *Cron Jobs*, programa cada hora:
   `php /home/USUARIO/domains/TU-DOMINIO/public_html/recordatorios.php`

## Archivos

| Archivo | Qué hace |
|---|---|
| `index.html` | Página de reservas de cada negocio |
| `api.php` | Horas libres y nuevas reservas |
| `admin.php` | Portal del dueño |
| `install.php` | Instalador |
| `lib.php` | Funciones compartidas y actualización de la base de datos |
| `recordatorios.php` | Recordatorios automáticos (opcional, por cron) |

![Editar servicios](docs/servicios.png)

## Contribuir

¡Las ideas y mejoras son bienvenidas! Abre un *issue* o un *pull request*. Próximas ideas: depósitos en línea, programa de lealtad y reseñas.

## Licencia

[AGPL-3.0](LICENSE). Puedes usarla, modificarla e instalarla gratis. Si ofreces una versión modificada como servicio en línea, debes publicar tus cambios.

---

### English

**LuniCitas** is an open-source booking app for barbershops, beauty salons and spas. Customers book in under a minute without an account (English, Spanish and Portuguese). Owners manage services, staff, hours, days off, attendance, WhatsApp reminders and monthly analytics. One install can host many businesses, each with its own link. Install: create a MySQL database, upload the files (or deploy from Git) and open your domain to run `install.php`. Licensed under AGPL-3.0.
