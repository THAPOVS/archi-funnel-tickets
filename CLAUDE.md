# CLAUDE.md - Funnel de tickets El Archibrazo

> Fuente única de verdad del sistema de compra de tickets de archibrazo.org. Cubre arquitectura, flow, estados de orden, automatización de mails, bot Telegram + IA, lessons learned y la frontera entre lógica de negocio (reusable en cualquier stack) e implementación WordPress (descartable si se migra).
>
> **Audiencia:** cualquiera que herede o reescriba el sistema. Pensado en particular para una eventual migración a Cardano + MeshJS (TypeScript), donde el smart contract reemplaza el gateway de transferencia + PeproDev + parte del bot.

---

## 0. Resumen ejecutivo en 60 segundos

El Archi vende tickets a sus eventos por archibrazo.org. La gente:

1. Elige un evento, lo agrega al carrito.
2. Carga sus datos (login Google opcional, sino email + clave o guest).
3. Transfiere por QR a la cuenta Archicoop.
4. Sube el comprobante (foto o PDF) al sitio.
5. Recibe el ticket (con QR de FooEvents) en su mail cuando el comprobante se aprueba.

La aprobación del comprobante hoy es **híbrida**:

- Si la IA (Gemini 2.5 Flash) lee número de operación + monto exacto + no es duplicado, **auto-aprueba** sin tocar humanos.
- Si tiene dudas, manda el comprobante a un grupo de Telegram donde los coopes aprietan ✅ o ❌ desde el celular.
- En paralelo, 5 mails automáticos cubren los casos donde el flujo se traba: te-falta a recordatorio, cancelada a +2 días, recibimos a colchón, rechazado, post-evento.

El stack actual es WordPress + WooCommerce + FooEvents + PeproDev + child theme propio + 2 snippets WPCode + 2 escenarios Make.com + bot Telegram + Gemini API. Todo lo de negocio (estados, transiciones, lógica de mails, lógica del bot) es **reusable**. Todo lo de WP (templates, hooks, plugins) es **descartable** si se migra.

---

## 1. Stack actual

| Pieza | Qué hace | Reemplazable por |
|---|---|---|
| **WordPress + Astra (parent theme)** | CMS + theme base | Cualquier framework web (Next.js, SvelteKit) |
| **WooCommerce** | Carrito, checkout, gestor de órdenes, REST API | Cualquier ORM + state machine de órdenes |
| **FooEvents** | Convierte producto WC en producto-evento con fecha, ubicación, generación de ticket PDF con QR | Lib propia que genere QR firmado por evento |
| **PeproDev (Pepro Customer Bank Transfer / Receipt Uploader)** | Inyecta gateway "Pago con Transferencia" + form de subida de comprobante + 3 estados custom de orden (`receipt-upload`, `receipt-approval`, `receipt-rejected`) | Smart contract Cardano + storage del comprobante en IPFS o S3 |
| **Child theme `astra-child-archibrazo`** | Restyling completo del checkout, multi-step UI, stepper visual, fuentes Andralis ND + Manrope, paleta dark, copy custom, módulo de mails automáticos | Componentes React/Svelte equivalentes + librería de mails (Resend, Postmark) |
| **WPCode snippet 37364 ("Order receipt webhook")** | Engancha hook `woocommerce_order_status_changed`, filtra `new_status = receipt-approval`, hace POST con payload de orden a Make.com | Llamada a webhook desde la state machine al pasar al estado equivalente |
| **Make.com Escenario A (id 4997854)** | Webhook recibe orden → trae thumbnail del comprobante → analiza con Gemini → router (AUTO / HUMAN PDF / HUMAN Imagen / DUPLICADO) → sendPhoto o sendMessage al grupo Telegram | Edge function / worker que orqueste lo mismo |
| **Make.com Escenario B (id 4997855)** | Telegram callback_query → router (approve_X / reject_X) → PUT a WC REST API → nota privada + editMessageReplyMarkup + answerCallback | Edge function que reciba callback de Telegram y mute el estado de la orden |
| **Bot Telegram `@Archibrabot`** | UI de aprobación humana en el celular, callbacks con botones inline | Idéntico, Telegram es portable |
| **Grupo Telegram "Archi Tickets" (chat_id -5056494083)** | Cola humana | Idéntico |
| **Gemini 2.5 Flash (free tier, cuenta archibrazo@gmail.com)** | OCR + razonamiento sobre el comprobante: ¿es transferencia válida?, ¿el monto coincide con el esperado?, ¿número de operación?, ¿auto-aprobable? | Cualquier LLM con vision (GPT-4o, Claude Sonnet, Gemini) - el prompt importa, el proveedor no |

**Lo que NO se toca por contrato:** los hooks de WC que crean usuarios al comprar, el nonce CSRF del checkout, la generación de tickets de FooEvents, la validación de archivo de PeproDev. Cualquier reescritura tiene que reimplementar la lógica equivalente, no rodearla.

---

## 2. El flow visible al público (5 pasos)

Diseño rector: stepper visible arriba (`● ● ● ○ ○`), cada paso aterriza en una URL real de WP que ya existe. La UI multi-step es CSS + JS encima del checkout estándar, **no** un wizard custom.

| Paso | URL real | Estado de la orden al entrar | Acción del usuario | Estado al salir |
|---|---|---|---|---|
| 1 · Ticket | `/tickets/<slug-evento>/` y luego `/carrito/` | sin orden | Elige variante, agrega al cart, aprieta "Realizar compra" | sin orden |
| 2 · Datos | `/finalizar-compra/` (vista billing) | sin orden | Llena nombre, mail, teléfono (o login con Google) | sin orden |
| 3 · Pagar | `/finalizar-compra/` (vista order_review) | sin orden | Ve QR + alias `ARCHICOOP` + monto, transfiere por su app del banco, aprieta "Ya pagué, voy a subir el comprobante" | orden creada en `receipt-upload` |
| 4 · Comprobante | `/finalizar-compra/order-received/<id>/` | `receipt-upload` | Sube imagen o PDF del comprobante | `receipt-approval` (PeproDev) |
| 5 · Listo | misma URL después del upload | `receipt-approval` o `completed` | Lee mensaje de confirmación, espera mail | `completed` (cuando bot/coope aprueba) |

**Principio de honestidad de copy:** en el paso 5 nunca decimos "pago confirmado", decimos "recibimos tu comprobante" (cierto) y "te llega el mail cuando lo verifiquemos" (cierto). El usuario se entera por mail real cuando un humano o la IA aprobó.

**Stepper:** en pasos pasados va verde con check, en el actual gradiente rosa-naranja, en pendientes gris. En mobile colapsa a `Paso 3 de 5 - Pagar`.

---

## 3. Estados de la orden (state machine)

```
                       (cliente envía form de pago en /finalizar-compra/)
                                 │
                                 ▼
                         ┌─────────────────┐
                         │ receipt-upload  │ ◄── cliente todavía no subió el comprobante
                         └────────┬────────┘
                                  │ (cliente sube archivo en order-received)
                                  ▼
                         ┌─────────────────┐
                         │ receipt-approval│ ◄── pendiente de aprobación (humano o IA)
                         └──┬───────────┬──┘
                            │           │
                  bot/coope │           │ bot/coope
                  aprueba   │           │ rechaza
                            ▼           ▼
                    ┌──────────┐  ┌──────────────────┐
                    │completed │  │ receipt-rejected │
                    └────┬─────┘  └────────┬─────────┘
                         │                 │
                  FooEvents manda     mail "rechazado"
                  ticket con QR       (cliente vuelve a empezar)
```

Estados extra que pueden ocurrir:

- `cancelled`: orden auto-cancelada después de 2 días en `receipt-upload`. La dispara el cron horario (`archi_email_cron_hourly`) del módulo de mails.
- Estado nativo de WC `pending payment`: no lo usamos, PeproDev arranca directo en `receipt-upload`.

**Transiciones que NO existen:** nunca volvemos de `completed` a `receipt-approval`, nunca un usuario puede self-service mover el estado, nunca un mail puede mover el estado. Solo el form de PeproDev (sube comprobante) y el bot/coope (aprueba/rechaza) mutan estado.

**Para una reescritura en Cardano:** el smart contract reemplaza la mecánica de `receipt-upload → receipt-approval → completed`. La transferencia onchain ya es prueba de pago, no hay comprobante que verificar manualmente. El estado salta directo de "intent" a "paid" cuando el contrato confirma la tx. El paso 4 (subir comprobante) desaparece, el stepper colapsa de 5 a 4 pasos.

---

## 4. Sistema de 6 mails

Los 6 mails que cubren todos los casos del funnel. **5 los maneja el módulo `astra-child-archibrazo/inc/archi-emails.php`. El 4 lo manda WooCommerce + FooEvents al pasar a `completed`.**

| # | Nombre | Cuándo dispara | Trigger | Template HTML |
|---|---|---|---|---|
| 1 | **te-falta** | +5 min de que la orden quedó en `receipt-upload` sin progresar | Cron `archi_5min` que escanea órdenes en ese estado | `emails/te-falta.html` |
| 2 | **cancelada** | +2 días en `receipt-upload`, además auto-cancela la orden | Mismo cron horario, mueve a `cancelled` | `emails/cancelada.html` |
| 3 | **recibimos** | +5 min después de pasar a `receipt-approval`, solo si la orden todavía sigue en ese estado (colchón anti-aprobación-rapidísima) | `wp_schedule_single_event` programado al cambio de estado | `emails/recibimos.html` |
| 4 | **tu entrada está lista** | Al pasar a `completed` | WooCommerce `customer_completed_order` mail con ticket FooEvents adjunto | Template restyleado en `woocommerce/emails/customer-completed-order.php` |
| 5 | **rechazado** | Al pasar a `receipt-rejected` | Hook `woocommerce_order_status_changed` | `emails/rechazado.html` |
| 6 | **post-evento** | Lunes posterior al evento, si el cliente compró ticket que se usó | Cron diario, lee `WooCommerceEventsDateTimeTimestamp` del producto FooEvents | `emails/post-evento.html` |

**Modo del módulo** (constante `ARCHI_EMAILS_MODE` en `functions.php`):
- `off`: ningún mail sale.
- `test`: los mails de evento se redirigen a `boleteria@archibrazo.org` con prefijo `[TEST]`. Los crons no escanean órdenes reales.
- `live`: producción normal.

**Marca go-live:** la primera corrida en modo live registra `archi_emails_golive` en wp_options. El cron solo procesa órdenes creadas **después** de esa marca, así no toca el backlog histórico.

**Guard de doble envío:** cada mail deja un meta key en la orden (`_archi_falta_enviado`, `_archi_cancelada_enviado`, `_archi_recibido_enviado`, `_archi_rechazado_enviado`, `_archi_postevento_enviado`). El cron chequea esos meta antes de mandar.

**Meta especial `_bot_silent_close`:** lo setea el bot cuando cierra una orden de forma silenciosa (ej. duplicado detectado por Gemini). Cualquier mail chequea este flag y se calla si está activo.

**Mail "cancelada" tiene sección amber "¿YA HABÍAS PAGADO?":** si el cliente transfirió pero no llegó a subir el comprobante a tiempo, el mail le explica que puede volver a reservar el mismo evento y subir la transferencia que ya hizo. Plata segura, problema solucionable.

---

## 5. Bot Telegram + IA: la cocina de la aprobación

**Stack:**

- WPCode snippet 37364 dispara webhook a Make.com cuando la orden pasa a `receipt-approval`.
- Make.com Escenario A:
  1. Webhook captura payload.
  2. HTTP GET a `/wp-json/wp/v2/media/{receipt_attachment_id}` con Basic Auth (WC API key) trae la URL pública del comprobante.
  3. Detección de duplicados: endpoint propio en WP que busca `numero_operacion` ya registrado.
  4. Llamada a Gemini 2.5 Flash con el comprobante como `inline_data` base64. Prompt pide JSON con `numero_operacion`, `monto_detectado`, `monto_esperado`, `confidence`, `motivo`, `auto_aprobable`.
  5. Router con 4 ramas mutuamente excluyentes (más abajo).
- Make.com Escenario B: recibe callbacks de Telegram, muta orden por REST API, edita el mensaje del grupo para sacar los botones, deja answerCallback al coope.

**Las 4 ramas del router (Escenario A) - filtros AND-exhaustivos para evitar doble disparo:**

| Rama | Filtro | Acción | Resultado |
|---|---|---|---|
| **DUPLICADO** | `dup == SI` | PUT order status `receipt-rejected` + nota privada del bot + sendMessage al grupo "Comprobante duplicado, orden rechazada automáticamente" | Sin botones. Cierre silencioso. |
| **AUTO** | `dup != SI` AND `auto_aprobable == SI` | PUT order status `completed` + nota privada "🤖 Auto-aprobada por bot (Gemini X%): {motivo}" + sendMessage al grupo "✅ Auto-aprobada #{order_id} por Gemini" | Sin botones. FooEvents manda el ticket. |
| **HUMAN PDF** | `dup != SI` AND `auto_aprobable != SI` AND `receipt_url endswith .pdf` | sendPhoto (thumbnail `-pdf.jpg` de PeproDev) + sendMessage con info de la orden + reply_markup con botones ✅ Aprobar / ❌ Rechazar | Cola humana. |
| **HUMAN Imagen** | `dup != SI` AND `auto_aprobable != SI` AND `receipt_url NOT endswith .pdf` | sendPhoto (URL directa de la imagen) + sendMessage + botones | Cola humana. |

**Threshold de auto-aprobación:** Gemini solo marca `auto_aprobable = SI` cuando:
- `numero_operacion` extraído con confianza alta.
- `monto_detectado` exactamente igual a `monto_esperado` (sobrepagos van a humano, decisión consciente).
- `confidence` >= 85.
- Banco emisor identificado.

**Prompt de Gemini:** vive en el módulo HTTP del Escenario A. Pide JSON estricto, da ejemplos de comprobantes válidos vs ambiguos, lista bancos argentinos comunes (Galicia, Santander, BBVA, Macro, Mercado Pago, Brubank, Ualá, Naranja X, etc.), explica que tiene que ser cuenta-a-cuenta (no escaneos de QR de Mercado Pago Pay, no recibos de tarjeta).

**Detección de duplicados:** endpoint custom en WP (snippet aparte) busca por `numero_operacion` en notas privadas de órdenes existentes. Si encuentra, marca `dup: SI` y agrega referencia a la orden previa que ya cobró ese comprobante.

**Lessons learned críticas (Bug 7 + Bug 8):**

- El `BasicRouter` de Make manda el bundle a todas las ramas cuyo filtro matchea, no solo la primera. Por eso los filtros son AND-exhaustivos, no fall-through.
- Gemini para PDF analiza el thumbnail `-pdf.jpg` que genera PeproDev (la primera página). Si necesitás el PDF completo, hay que usar `file_uri` con la URL del PDF crudo.
- Cuentas Google Workspace bloquean Gemini free tier. Usar cuenta `@gmail.com` (acá: `archibrazo@gmail.com`).
- Rate limit Gemini Flash free: alcanza para <100 órdenes/semana sin problema. Por encima, ir a pago o cachear resultados.
- Telegram `sendPhoto` rechaza PDFs silenciosamente. Hay que mandar la thumbnail JPG de PeproDev, no el PDF.

**Para una reescritura en Cardano:** todo este aparato (Gemini OCR + duplicado + router) reemplaza el problema de "¿esta transferencia bancaria fue real?". En Cardano la tx onchain es la prueba. El bot Telegram **sigue siendo útil** como UI de notificación al equipo ("entró una compra"), pero ya no como gate de aprobación. La auto-aprobación pasa a ser el default y la rama humana solo se mantiene para edge cases (ej. el smart contract no liquidó, pago multi-step, retry).

---

## 6. Decisiones de diseño que NO son obvias en el código

Estas son las decisiones que más cuestan después si se las cambia sin contexto.

### 6.1 UI-only sobre WC, no fork del checkout

El multi-step UI es CSS + JS encima del form único de WooCommerce. Los inputs del billing **siguen en el DOM** cuando estás en el paso 3 (ocultos con `display:none`), así WC recibe todos los campos al submit. El botón "Continuar al pago" es `<button type="button">` con un listener JS, **nunca** `type="submit"`. Si lo hacés submit, disparás el form de WC sin querer.

### 6.2 Stepper inyectado por hooks de display, no por templates override

`woocommerce_before_cart`, `woocommerce_before_checkout_form`, `woocommerce_before_pay_form`, `woocommerce_before_thankyou`. Markup inerte que sobrevive updates de WC.

### 6.3 Filtro `woocommerce_order_button_text` scopeado, NO `gettext` genérico

`gettext` cambiaría "Place order" en mails al admin, en backend, en widgets, en todos lados. El filtro scopeado solo toca el botón del checkout. Hay un mapa enorme de `gettext` aparte (`archi-funnel-traducciones.php`) que traduce strings del plugin PeproDev del inglés al castellano, ahí sí se justifica.

### 6.4 Copy de la thank-you cambia según el estado de la orden

El mismo URL `/order-received/` puede mostrar 4 mensajes distintos según el estado:
- `receipt-upload` → "tu pedido está creado pero falta el comprobante"
- `receipt-approval` → "recibimos tu comprobante, te llega el ticket en minutos"
- `receipt-rejected` → "hubo un problema, escribinos a archibrazo@gmail.com"
- `completed` → "ya está confirmado y te llegó por mail"

Implementado en filtro `woocommerce_thankyou_order_received_text`.

### 6.5 Tipografías

- Display: **Andralis ND** (Juan Andralis, 1966). OTF servido localmente desde el child theme en `/fonts/`. Sin Andralis no hay identidad Archi. Si te migrás, llevátela.
- Body: **Manrope** (Google Fonts). Reemplazable.
- En el deck de presentación al equipo usamos Manrope + Fraunces porque Andralis era para producción.

### 6.6 Paleta dark con acentos cálidos

- Background: `#0a0a0a` (casi negro, lecho del Archi)
- Surface: `#161616`
- Texto: `#f5f1ea` (off-white cálido)
- Acento primario (CTA gradient): `#ff3d7f → #ff8a3d` (rosa a naranja)
- Warning (banner amarillo): `#fbbf24`
- Éxito (check verde stepper): `#34d399`

No usar Tailwind defaults. Esta paleta tiene la calidez que el Archi necesita.

### 6.7 Boletería = `boleteria@archibrazo.org`

Único email de contacto que aparece en mails y copy. Nunca publicar `archibrazo@gmail.com` (es el operativo interno). Nunca decir "escribinos por WhatsApp" (no usamos WhatsApp para boletería).

### 6.8 Nombre comercial de la cocina

Antes "Archibraza". **Ya no se usa.** La cocina del Archi sí funciona y vende comida artesanal. **NO vendemos cerveza artesanal.** En cualquier copy: ok comida artesanal, nunca cerveza artesanal.

### 6.9 Auto-cancelación a +2 días es decisión de negocio

Liberar el lugar después de 2 días sin comprobante es la regla. El mail "cancelada" le ofrece al cliente volver a empezar (ver sección amber "¿YA HABÍAS PAGADO?"). No es punitivo, es de inventario.

### 6.10 Cargo del servicio 10%

Se suma al subtotal del carrito. Aparece como "Coste del Servicio" en la tabla del pedido. Cubre comisiones (Argentores 10% de borderós, costos del bot, hosting, etc.). En el deck va explicado como "10% para sostener el sistema de tickets".

---

## 7. Archivos críticos del filesystem (mapa)

```
funnel-tickets-staging/
├── propuesta-funnel.md             # spec UX/UI con los 5 pasos
├── aplicacion-wordpress.md         # cómo bajar el spec a WP sin romper nada
├── analisis-flujo-actual.md        # auditoría del flow pre-rediseño
├── no-romper-nada-checklist.md     # verificaciones pre-deploy
├── PLAN-automatizacion-mails.md    # plan original del módulo de mails
├── README-para-alex.md             # handoff al equipo
│
├── child-theme/astra-child-archibrazo/
│   ├── functions.php               # 2776 líneas, todo el rediseño UI vive acá
│   ├── style.css                   # 1774 líneas, CSS scopeado al checkout
│   ├── inc/archi-emails.php        # 435 líneas, módulo de 5 mails automáticos
│   ├── fonts/                      # Andralis ND OTFs (Regular, Italic, Bold)
│   ├── emails/
│   │   ├── te-falta.html
│   │   ├── recibimos.html
│   │   ├── cancelada.html
│   │   ├── rechazado.html
│   │   └── post-evento.html
│   └── woocommerce/
│       ├── cart/cart.php
│       ├── cart/cart-totals.php
│       ├── checkout/thankyou.php
│       └── emails/customer-completed-order.php
│
├── integracion-fase-1/
│   └── archi-funnel-fase-1.php     # snippet WPCode: stepper + banner + copy thank-you
├── integracion-fase-2/
│   └── archi-funnel-fase-2.php     # snippet WPCode: multi-step UI sobre checkout
├── integracion-fase-3-traducciones/
│   └── archi-funnel-traducciones.php  # snippet WPCode: gettext map EN→ES de PeproDev
│
└── screenshots-playwright/         # capturas de cada paso para el deck del equipo

../ (Archibrazo/)
├── plan-tickets-2026-05.md         # plan macro del proyecto
├── plan-tickets-nivel1-bot-telegram.md  # spec del bot + Make.com escenarios A y B
├── bot-tickets-troubleshooting.md  # 8 bugs documentados con causa raíz + fix
├── scenario-A-blueprint-with-gemini.json    # blueprint export Make.com Escenario A
├── scenario-B-blueprint-revoke-2step.json   # blueprint export Make.com Escenario B
├── snippet-order-receipt-webhook.php        # versión standalone del snippet 37364
└── secrets/bot-tickets-credentials.md       # tokens, IDs, chat_id (NO commitear nunca)
```

**Snippets WPCode en producción (todos editables desde wp-admin):**

- **37364** "Order receipt webhook" - dispara webhook a Make.com al `receipt-approval`.
- "Archi Funnel - Fase 1" - stepper + copy del checkout (legacy, lo absorbió el child theme).
- "Archi Funnel - Fase 2" - multi-step UI (legacy, lo absorbió el child theme).
- "Archi Funnel - Traducciones upload comprobante" - mapa gettext EN→ES de PeproDev.

**Cuando el child theme está activo, los snippets de Fase 1 y Fase 2 pueden desactivarse.** El snippet 37364 sigue siendo necesario porque maneja el webhook al bot.

---

## 8. Verificación end-to-end (post-deploy)

Plan de test minimal que cubre los 7 caminos:

1. **Guest checkout JPG** - sin loguearse, comprobante JPG, aprobación manual desde Telegram.
2. **Cliente logueado PDF** - cuenta existente, comprobante PDF, auto-aprobación por Gemini.
3. **Abandono en paso 3 y vuelta** - dejar QR, cerrar, volver vía mail "te falta".
4. **Archivo inválido** - subir >8MB o formato no permitido, ver mensaje de error en español.
5. **Rechazo por bot** - comprobante falso, coope aprieta ❌, cliente recibe mail rechazado.
6. **Mobile real** - iOS Safari + Android Chrome, upload desde cámara.
7. **Regresión** - login/mi-cuenta/mis-pedidos/check-ins app de coopes siguen funcionando.

**Métricas que el rediseño busca mover:**
- % órdenes que efectivamente suben comprobante (objetivo: >95%, pre-rediseño estimado: 70-85%).
- Tiempo cart → comprobante subido (objetivo: <2 min, pre: 3-7 min).
- Tasa de abandono entre paso 3 (pagar) y paso 4 (subir comprobante). **Este es el número que más importa.**

---

## 9. Si algo se rompe en producción: rollback en 5 minutos

1. Volver al theme padre Astra (sin child).
2. Desactivar snippet 37364 (corta el bot, las órdenes siguen funcionando manualmente desde wp-admin).
3. Desactivar el escenario A en Make.com.
4. Limpiar cache (W3 Total Cache / WP Rocket → Purge All).

El sitio vuelve al estado pre-funnel sin perder ni una orden ni un usuario, porque ningún cambio tocó el motor de WooCommerce ni la DB.

---

## 10. Notas para una reescritura en Cardano + MeshJS

**Lo que sigue siendo válido (lógica de negocio):**

- El stepper de 5 pasos colapsa a 4 si el pago es onchain (no hay comprobante que subir).
- La copy de cada paso, los principios de honestidad, los textos de mails: portables.
- El módulo de mails (5 templates + cron + go-live marker + meta key guards): la lógica es portable a Resend/Postmark + un cron worker (Vercel Cron, Cloudflare Workers).
- Los estados de la orden cambian a algo como: `pending → tx_submitted → tx_confirmed → completed` (con `rejected` si la tx falla). La state machine es la misma idea.
- Bot Telegram como notificación al equipo: portable 1:1, el Telegram Bot API es agnóstico al stack.
- Detección de "ya cobré esta tx" (anti-duplicado): trivial en blockchain, la tx hash es único onchain.
- Auto-aprobación: pasa a ser el default. Confirmaciones del bloque = prueba criptográfica de pago.
- Sistema de fonts + paleta + identidad visual: portable, son archivos estáticos.

**Lo que desaparece:**

- PeproDev entero (gateway de transferencia + form de upload + 3 estados custom).
- Gemini OCR + análisis de comprobantes (la tx ya está validada onchain).
- El paso 4 del flow.
- 2 de los 5 mails (te-falta y cancelada) si la UX se rediseña: si el cliente no completa la tx, no se le manda nada, la orden expira sola. Quedan: recibimos (al confirmar tx), entrada lista (ticket), rechazado (si la tx no cuajó), post-evento.

**Lo que aparece:**

- Wallet connection (Lace, Nami, Eternl, Yoroi, Flint vía MeshJS).
- Smart contract de venta de ticket: recibe ADA, emite un NFT que ES el ticket (con el QR/datos del evento metidos en el metadata). El comprador queda con el NFT en su wallet = no hace falta el mail con el QR, el NFT es el ticket.
- Verificación en la puerta: scan del NFT desde la wallet vs lista de tickets emitidos por el contrato del evento. Reemplaza FooEvents Check-Ins.
- Reembolso onchain en caso de cancelación del evento.

**Trade-offs honestos a discutir con Pablo antes de invertir:**

1. **Onboarding del cliente.** El público del Archi compra entradas desde el celular en 90 segundos con su app del banco. Instalar una wallet, entender qué es ADA, conectarla a un sitio: friction grande. Hoy no es su mundo. Probablemente se mantenga ambos rails un buen tiempo (transferencia tradicional + cripto opcional).
2. **Precio en pesos vs ADA.** El precio del evento se fija en ARS, pero el contrato cobra en ADA. La volatilidad ADA/ARS pega fuerte. Soluciones: precio convertido en el momento del checkout, o usar stablecoin (DJED, IUSD).
3. **Custodia + gas fees.** Cardano fees son bajos pero existen. Para una entrada de $8.000 ARS (~$8 USD), un fee de 0.17 ADA (~$0.10) es 1.2% del valor. Razonable, pero hay que comunicarlo.
4. **Refund onchain.** Si se cancela un evento, el contrato tiene que poder devolver. Esto cambia el modelo del contrato (no es solo "recibí ADA, emití NFT", es "recibí ADA en escrow hasta la fecha del evento, después transfiero al Archi y emito el NFT").
5. **Identidad del comprador.** Hoy WC guarda nombre + mail + teléfono. Onchain una wallet es anónima. Si el Archi quiere mantener la relación con el público (mails post-evento, agenda, retargeting), hace falta o un step adicional para capturar mail, o aceptar que el comprador es una wallet anónima.

**Recomendación operativa para el experimento de Pablo:**

Antes de migrar nada, hacer un piloto en paralelo: el flow actual (WP + transferencia) sigue intacto, y se agrega un **gateway adicional "Pagar con ADA / Cardano"** que cuando el cliente lo elige, lo manda a una página separada con conexión de wallet + interacción con el smart contract + emisión de NFT-ticket. Si esa rama funciona y la usa gente, después se evalúa migrar todo. Si no la usa nadie, queda como rail opcional sin haber roto el sistema productivo.

Esto reduce el riesgo a cero: el funnel actual sigue dándole de comer al Archi, el experimento Cardano corre por afuera y aprende sin presión.

---

## 11. Contactos

- **Joaco** (yo, Síndico Titular, project lead del rediseño): joaquinpovina@gmail.com
- **Pablo Ifantidis** (Presidente del Consejo, dueño del inmueble): explorando integración con Cardano + MeshJS
- **Alex** (Tesorero, web + boletería + presencial): contacto operativo del sitio
- **Cyn** (diseño gráfico + web): ejecutó el rediseño del checkout
- **Boletería** (único email público): boleteria@archibrazo.org
- **Mail operativo interno** (no publicar): archibrazo@gmail.com

---

_Última actualización: 2026-06-02. Si tomás este archivo y reescribís el sistema, dejá un changelog acá abajo y subí los nuevos paths del filesystem._
