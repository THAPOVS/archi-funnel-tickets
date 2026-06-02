# Plan de automatización de mails - Funnel de tickets El Archibrazo

Estado: listo para construir cuando Alex apruebe los diseños.
Arquitectura: nativo en WordPress (sin Make, sin dependencias externas).

---

## Resumen

6 mails. Dos mecanismos:

- **Disparados por evento**: el mail sale cuando la orden cambia de estado.
- **Disparados por tiempo (cron)**: una tarea automatica revisa que ordenes cumplen
  una condicion y manda. SIEMPRE re-verifica el estado de la orden antes de mandar.

Decisiones de diseno (2026-05-22):
- Se fusionaron "Recibimos tu reserva" + "Recordatorio" en UN solo mail: "Te falta el
  comprobante", condicional, que sale tras un buffer corto solo a quien no subio. No hay
  mail inmediato a todos. El momento de reservar lo cubre la pagina de gracias.
- "Recibimos tu comprobante" se mantiene como mail, pero CONDICIONAL: se programa al
  subir el comprobante y sale a los 5 minutos solo si en ese ratito todavia no salio
  "Tu entrada esta lista". Cubre el desfase entre subir el comprobante y recibir el QR
  (en ese hueco la gente pregunta "no me llego nada"). Si la aprobacion es instantanea,
  no se manda.
- La cancelacion por no subir comprobante pasa de 3 a 2 dias (feedback Alex: libera
  antes el lugar de quien solo prueba el funnel).

---

## Los 6 mails

| # | Mail | Tipo | Cuando / condicion | Plantilla (emails/) |
|---|---|---|---|---|
| 1 | Te falta el comprobante | tiempo | la orden sigue en `receipt-upload` unas horas despues de creada | email-reserva-creada.html |
| 2 | Reserva cancelada | tiempo | la orden sigue en `receipt-upload` 2 dias despues + se cancela la orden | email-reserva-cancelada.html |
| 3 | Recibimos tu comprobante | tiempo | 5 min despues de subir el comprobante, si todavia no salio "Tu entrada esta lista" | email-comprobante-recibido.html |
| 4 | Tu entrada esta lista | evento | la orden pasa a `completed` | email-entrada-lista.html |
| 5 | Comprobante rechazado | evento | la orden pasa a `receipt-rejected` | email-comprobante-rechazado.html |
| 6 | Post-evento | tiempo | lunes posterior, ordenes `completed` cuyo evento fue la semana pasada | email-post-evento.html |

(El mail 1 vive en email-reserva-creada.html: ese archivo trae los datos para
transferir. El diseno email-recordatorio-comprobante.html queda en la carpeta pero
NO se usa.)

---

## El recorrido

```
Una persona coloca su reserva
(la pagina de gracias le muestra los datos y el form para subir el comprobante)
│
├─ SUBE EL COMPROBANTE
│     ├─ a los 5 min, si todavia no salio el QR  → MAIL: Recibimos tu comprobante
│     │   (colchon: si la aprobacion es instantanea, no se manda)
│     └─ el equipo lo revisa
│           ├─ lo aprueba  → MAIL: Tu entrada esta lista (+ QR)  [cierra el camino feliz]
│           └─ lo rechaza  → MAIL: Comprobante rechazado (puede volver a subir uno valido)
│
└─ NO SUBE EL COMPROBANTE
      ├─ pasan unas horas        → MAIL: Te falta el comprobante
      └─ sigue sin subir, 2 dias → MAIL: Reserva cancelada (la orden se cancela)
         (si sube en cualquier momento, salta a la rama de arriba)

Asistio al evento → MAIL: Post-evento
```

**Camino feliz rapido = 1 mail** (Tu entrada esta lista). **Camino feliz con demora = 2**
(recibimos + entrada lista). **Se traba = hasta 2** (te falta + cancelada).
**Rechazado = 1** (rechazado) + lo que siga si re-sube.

---

## Regla dura de los mails de tiempo (1, 2 y 3)

Antes de mandar, el sistema RE-VERIFICA el estado de la orden. Si la orden ya cambio
de estado, no se manda. Por eso:
- Quien sube el comprobante a tiempo nunca recibe "Te falta el comprobante".
- Una reserva confirmada nunca recibe "Reserva cancelada".
- Quien recibe "Tu entrada esta lista" antes de los 5 min nunca recibe "Recibimos tu
  comprobante".

---

## Los mails con cron (1, 2, 3, 6) - detalle

- **Mail 1 (te falta):** cron que corre seguido. Busca ordenes en `receipt-upload`,
  creadas hace +N horas (buffer corto a definir, ~3-4 h), sin la marca
  `_archi_falta_enviado`. Manda y marca.
- **Mail 2 (cancelada):** cron diario. Busca ordenes en `receipt-upload`, creadas hace
  +2 dias. Manda el mail, pone la marca `_archi_cancelada_enviado` y cancela la orden.
- **Mail 3 (recibimos tu comprobante):** al subir el comprobante (orden pasa a
  `receipt-approval`) se programa un evento unico a +5 min para esa orden. A los 5 min
  se chequea: si la orden NO tiene la marca `_archi_entrada_enviado` (no salio el QR)
  y no esta rechazada ni cancelada, se manda y se marca `_archi_recibido_enviado`. Si
  el QR ya salio (aprobacion instantanea), no se manda nada. Esto tambien cubre el caso
  de que la automatizacion del QR falle: si el QR nunca salio, la marca nunca se pone
  y el colchon sale igual.
- **Mail 6 (post-evento):** cron los lunes. Busca ordenes `completed` cuyo evento
  (fecha de FooEvents) cayo en los ultimos 7 dias, sin la marca
  `_archi_postevento_enviado`. Manda y marca.

Las marcas (meta de la orden) evitan envios duplicados. Respeta el meta
`_bot_silent_close` (las ordenes de backlog cerradas en silencio no reciben nada).

El mail 4 "Tu entrada esta lista" pone la marca `_archi_entrada_enviado` al mandarse:
esa marca es la que apaga el colchon (mail 3).

---

## Que hay que apagar

Para que el cliente no reciba dos mails (el viejo + el nuevo):

- WooCommerce -> Correos: apagar "Uploaded Receipt to Customer" y "Rejected Receipt
  to Customer" de PeproDev (los reemplazan los mails 3 y 5).
- WooCommerce -> Correos: apagar "Procesando tu pedido" (el generico de WooCommerce,
  el que dice "Tu pedido esta en proceso"): lo reemplaza el mail 3.
- El mail actual de FooEvents "entrada lista": se reemplaza por el mail 4, o se
  restylea la plantilla de FooEvents. A definir en la implementacion por el QR del
  ticket (lo genera FooEvents).
- Revisar que no quede otro mail de WooCommerce que se pise.

---

## Personalizacion (variables por mail)

Todos: nombre del cliente, numero de pedido.

- Mail 1 Te falta el comprobante: + monto total a transferir, alias (fijo: ARCHICOOP),
  QR de transferencia (fijo, embebido).
- Mail 4 Tu entrada esta lista: + QR del ticket (unico por entrada, lo da FooEvents),
  datos del evento.
- Mail 6 Post-evento: + link de resena de Google (el directo del Google Business
  Profile, formato g.page/r/.../review).

Todos los mails llevan boleteria@archibrazo.org como contacto de ayuda. WhatsApp NO
por ahora (boleteria solo por email): se suma en otro momento.

---

## Checklist de go-live (cuando Alex apruebe los disenos)

1. Construir las 6 funciones de envio + las plantillas como archivos del theme.
2. Enganchar los hooks de WooCommerce (mails de evento: 4, 5).
3. Crear las tareas de cron (mails de tiempo: 1, 2, 6) + el evento unico a +5 min
   del mail 3.
4. Activar cron real del servidor (panel del hosting).
5. Apagar los mails viejos que se pisan.
6. Testear cada camino con ordenes de prueba (feliz rapido / feliz con demora /
   trabado / rechazado).
7. Funnel live: cambiar `ARCHI_FUNNEL_ADMIN_ONLY` a `false` en functions.php.
8. Monitorear el log de mails (WP Mail Logging) los primeros dias.

---

## Estado actual (2026-05-22, LIVE)

- **Funnel: LIVE.** `ARCHI_FUNNEL_ADMIN_ONLY=false` en functions.php. Todos los visitantes
  ven el funnel de 5 pasos.
- **Automatizacion de mails: LIVE.** `ARCHI_EMAILS_MODE='live'` en functions.php.
  Modulo: `inc/archi-emails.php` (200 LoC). Templates tokenizados en `emails/`.
- **PeproDev: las 2 customer mails apagadas** ("Uploaded Receipt to Customer" y
  "Rejected Receipt to Customer"). Las admin mails de PeproDev quedan como estaban.
- **Mail 4:** template override en `woocommerce/emails/customer-completed-order.php`.
  Mantiene los hooks estandar de WC (FooEvents sigue enganchando el ticket+QR),
  reemplaza la copia generica por hero branded "Tu entrada esta lista" + cierre
  "te esperamos / como llegar".
- Mode 'test' deja redirect a boleteria@ + prefijo [TEST] - util para testear cuando
  haga falta volver a probar algo sin afectar clientes.
- Trigger manual de prueba: `?archi_test=tefalta|cancelada|recibimos|rechazado|postevento|entradalista&order=ID`
  (admin only, fuerza redirect a boleteria@ aun en modo live).

### Verificado
- 5/5 mails tokenizados sale OK con el trigger manual (loggeados a boleteria@ con [TEST]).
- Funnel renderiza para anonimos (carrito 200, stepper visible).
- Sin sintaxis errors (php -l limpio en functions.php, archi-emails.php, customer-completed-order.php).

### Pendiente de verificar visualmente
- Mail 4 (template override): el trigger sintetico no logea cuando se llama
  programaticamente (quirk de WC al dispatchar sin transicion real). Para verificar:
  crear un pedido de prueba con tu mail, marcarlo como "Pedido completado" en wp-admin
  -> WC dispatchea el mail con el template overrideado + el ticket de FooEvents adjunto.
  Si algo no se ve bien, ajustar el template y redeployar.

### Si algo se rompe
- Para frenar TODO mail nuevo sin redeploy: editar `wp_options` en DB y NO conviene -
  hay que redeployar functions.php con `ARCHI_EMAILS_MODE='off'`. (En el zip queda
  preparado el cambio: 1 linea.)
- Para volver al estado pre-automatizacion: redeployar functions.php con
  `ARCHI_EMAILS_MODE='off'` + reactivar las 2 PeproDev customer mails en WC Settings.
