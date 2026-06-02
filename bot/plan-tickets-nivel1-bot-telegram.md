# Plan Nivel 1: bot Telegram para aprobación de órdenes

> Versión 3 (post-setup WP del 2026-05-07). Reemplaza versiones anteriores.
> **Approach:** Joaco arma todo (bot, grupo, Make.com) y prueba solo end-to-end. Apenas funciona, suma a Alex/Cyn/Agus al grupo Telegram con pinned message instructivo. **WP no se toca** (sin cambios de configs/roles/plugins) para no confundir al equipo en su flujo habitual. El bot Telegram sí es herramienta nueva conocida por el equipo.

## Estado actual del setup en WP (lo que YA está hecho)

✅ **API key WC creada** (asignada al user joaquinpovina #113):
- Descripción: "Tickets bot interno"
- consumer_key + consumer_secret guardados en `~/Documents/Claude/Projects/Archibrazo/secrets/bot-tickets-credentials.md`

✅ **Snippet WPCode Lite creado** (snippet_id=37364):
- Título: "Order receipt webhook"
- Tipo PHP, Auto Insert "Run Everywhere", activo
- Engancha al hook `woocommerce_order_status_changed` filtrando `new_status = 'receipt-approval'`
- Tiene guardrail: NO dispara nada hasta que la URL del webhook se reemplace por la real de Make.com
- Edit: https://www.archibrazo.org/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=37364

✅ **Auditoría completa** del flujo, plugins, meta keys, estados custom. Documentada en `secrets/bot-tickets-credentials.md`.

## Lo que NO se tocó (decisión: piloto sigiloso)

❌ Configuración del PeproDev (acción 2: cambiar "En Comprobante Aprobado" a "Completado"). Se evalúa cuando el equipo esté informado.
❌ Roles de Cyn/Alex/Agus (siguen como están).
❌ Ningún plugin nuevo instalado.
❌ Ningún plugin existente modificado.

---

## Lo que falta (3 tareas externas a WP)

### Tarea 1. Crear bot Telegram + grupo de prueba (Joaco, 10 min)

A. Hablarle a `@BotFather` en Telegram → `/newbot` → nombre "Archi Tickets Bot" → username único (ej. `archi_tickets_bot`).

B. Anotar el `BOT_TOKEN` que devuelve (formato `123456:ABC-DEF...`). Pegarlo en `secrets/bot-tickets-credentials.md` en el campo `TELEGRAM_BOT_TOKEN`.

C. `@BotFather` → `/setprivacy` → seleccionar el bot → **Disable**. Esto permite que el bot reciba callbacks de botones desde grupos.

D. **Crear grupo Telegram "Archi - Aprobaciones tickets"** con vos solo de momento (vas a sumar al equipo cuando esté testeado). Agregar el bot al grupo. Promoverlo a **Admin del grupo**.

E. Mandar cualquier mensaje en el grupo (ej. `/start@archi_tickets_bot`).

F. En el navegador abrir `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates`. Buscar el `chat.id` del grupo (es un **número negativo**, formato `-100xxx`). Pegar en `TELEGRAM_CHAT_ID` del archivo de credenciales.

### Tarea 2. Crear cuenta Make.com y armar 2 escenarios (Joaco + yo, 1.5 h)

Cuenta gratis con `joaquinpovina@gmail.com` (separar de Pareto).

#### Escenario A. "Archi - Notificar comprobante pendiente"

A1. Módulo 1: **Webhooks → Custom webhook**. Generar URL. Pegar en `MAKE_WEBHOOK_URL` del archivo de credenciales.

A2. Editar el snippet 37364 → reemplazar `https://hook.<region>.make.com/<REPLACE_ME>_WHEN_MAKE_SCENARIO_READY` por la URL real. Guardar. (Esto es lo que activa todo).

A3. Hacer una orden de prueba con monto bajo en archibrazo.org y subir un comprobante. Make.com captura la estructura.

A4. Módulo 2: **HTTP → Make a request**.
- Method: GET
- URL: `https://www.archibrazo.org/wp-json/wp/v2/media/{{1.receipt_attachment_id}}`
- Auth: Basic (consumer_key:consumer_secret del archivo de credenciales)
- Parse JSON response.

A5. Módulo 3: **Telegram Bot → Send a Photo**.
- Connection: pegar `BOT_TOKEN`.
- Chat ID: el del grupo "Archi - Aprobaciones tickets" (negativo, formato `-100xxx`).
- Photo: `{{2.source_url}}`.
- Caption:
```
🎟️ Nueva orden pendiente #{{1.order_id}}

👤 {{1.customer}}
📧 {{1.email}}
💰 ${{1.total}}
🎭 {{1.items}}

🔗 {{1.admin_url}}
```
- Reply Markup (raw JSON):
```json
{
  "inline_keyboard": [[
    {"text": "✅ Aprobar", "callback_data": "approve_{{1.order_id}}"},
    {"text": "❌ Rechazar", "callback_data": "reject_{{1.order_id}}"}
  ]]
}
```

A6. Activar escenario. Test: hacer otra orden de prueba, ver que llega el mensaje en <30 segundos.

#### Escenario B. "Archi - Procesar aprobación"

B1. Módulo 1: **Telegram Bot → Watch Updates**, filter Update Type = `callback_query`.

B2. Módulo 2: **Tools → Set variable** `order_id` = `substring({{1.callback_query.data}}; indexOf({{1.callback_query.data}}; "_") + 1)`.

B3. Módulo 3: **Router** con 2 ramas:

**Rama Aprobar** (filter: `callback_query.data starts with "approve_"`):
- HTTP PUT a `https://www.archibrazo.org/wp-json/wc/v3/orders/{{order_id}}`, Basic Auth, Body `{"status": "completed"}`.
- HTTP POST a `https://www.archibrazo.org/wp-json/wc/v3/orders/{{order_id}}/notes`, Basic Auth, Body `{"note": "Aprobada vía bot Telegram por @{{1.callback_query.from.username}}", "customer_note": false}`. Deja constancia de quién aprobó.
- Telegram **Answer Callback Query** → "Aprobada".
- Telegram **Send Message** al grupo → "✅ Aprobada #{{order_id}} por @{{1.callback_query.from.username}}".

**Rama Rechazar** (filter: `callback_query.data starts with "reject_"`):
- HTTP PUT con body `{"status": "receipt-rejected"}`.
- HTTP POST nota: "Rechazada vía bot Telegram por @{{1.callback_query.from.username}}".
- Telegram Answer Callback + Send Message similar.

B4. Activar y test end-to-end con la orden de prueba.

### Tarea 3. Recordatorio órdenes vencidas (Joaco, 30 min, opcional)

Tercer escenario en Make.com:
- **Schedule** cada 2h, 9hs-23hs ART.
- HTTP GET a `/wp-json/wc/v3/orders?status=receipt-approval&per_page=50`, Basic Auth.
- Iterator + Filter (date_created entre 2h y 24h atrás).
- Aggregator + Telegram Send Message: "⚠️ {{count}} orden(es) pendientes hace +2h".

---

## Test end-to-end solo Joaco antes de invitar al equipo

1. Hacer orden de prueba de $100 en archibrazo.org con un evento real.
2. Subir un comprobante cualquiera.
3. Verificar que llega mensaje al grupo Telegram (todavía solo vos) con foto + botones, en <30 seg.
4. Apretar "Aprobar" desde el celular.
5. Verificar:
   - Orden en estado "completed" en WP admin.
   - Llega mail con tickets PDF de FooEvents al cliente.
   - Tickets aparecen en la app FooEvents Check-Ins.
   - La orden tiene nota privada "Aprobada vía bot Telegram por @joaco".
6. Repetir con otra orden y "Rechazar".

Si el flow pasa los 6 pasos, listo para sumar equipo.

---

## Sumar al equipo al grupo Telegram (cuando funcione el test)

1. **Pinear mensaje instructivo** en el grupo:

```
🎟️ Bot de aprobación de tickets del Archi

Cuando entra una orden con comprobante, el bot la postea acá con la foto del comprobante y dos botones.

✅ Aprobar = la orden pasa a "completada", FooEvents manda los tickets PDF al cliente y aparecen en la app de check-in.
❌ Rechazar = la orden queda en "Comprobante Rechazado", el cliente recibe mail.

Si tenés dudas con un comprobante, NO aprietes nada. Pedí en el grupo que alguien lo revise primero. Queda registro de quién apretó qué (en la nota de la orden).

Si una orden lleva +2h sin resolver, el bot manda recordatorio.
Si el bot no responde, igual se puede aprobar manualmente desde el WP admin como siempre.
```

2. **Invitar a Alex, Agus, Cyn** al grupo (y a quien más quieras sumar como aprobador).

3. **Hacer 1 orden de prueba en vivo** con todos en el grupo para que vean el flow una vez.

Eso es todo. No hace falta tocar nada del WP, ni roles, ni configs. El equipo solo gana una vía nueva (Telegram) además del admin de WP que ya conocen.

---

## Verificación de éxito (mes 1)

- Tiempo promedio orden→ticket: hoy 4-12 horas. Objetivo <30 min para 95%.
- % órdenes aprobadas vía bot Telegram vs admin de WP. Si Telegram capta el >70%, el sistema vale la pena.
- Distribución por aprobador: si entre 4 personas se reparte razonable, el bus factor está resuelto. Si Alex sigue >70%, hablarlo en asamblea.
- Quejas en puerta por "no me llegó el ticket": objetivo 0.

---

## Lo que NO resuelve este Nivel 1

- Si los 4 aprobadores duermen al mismo tiempo, las compras nocturnas se atrasan hasta la mañana.
- Cliente sigue pudiendo subir comprobantes falsos (poco común pero pasa).
- Sigue habiendo aprobación humana, aunque distribuida.

**Por eso el horizonte real es MODO Empresas con Credicoop** (cobro automático, cero aprobación humana, comisión <1.5%). Trámite en paralelo cuando estés listo. Confirmado 2026-05-07 que Credicoop está en MODO.
