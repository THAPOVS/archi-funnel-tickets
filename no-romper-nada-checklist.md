# Checklist de no-rotura - WordPress / WooCommerce / FooEvents / PeproDev / bot Telegram

> Auditoría línea por línea del plan de implementación contra los hooks, sesiones, AJAX, meta keys y dependencias del stack actual de archibrazo.org. Cada item documenta el riesgo, cómo el plan lo evita, y cómo verificarlo en staging.

## Resumen ejecutivo

El rediseño es **UI-only (CSS + JS de progressive disclosure)** y NO toca:

- Hooks que mutan estado de la orden
- El nonce CSRF de WC checkout
- Templates de WooCommerce
- Settings o templates de FooEvents
- El formulario, validación o nonce de PeproDev
- El snippet WPCode 37364 (bot)
- Los escenarios de Make.com
- Los estados custom `wc-receipt-upload` / `wc-receipt-approval` / `wc-receipt-rejected`
- Las meta keys `receipt_upload_status`, `receipt_uploaded_attachment_id`, `receipt_upload_date_uploaded`, `peprodev_uploadreceipt_action_run_once`
- La auto-creación de usuarios WP via WC

Lo único que cambia: **markup visual extra inyectado por hooks de display + restyling con CSS + JS de show/hide de bloques del form existente**.

---

## 1. WordPress core

| Riesgo | Mitigación en el plan | Cómo verificarlo |
|---|---|---|
| Edit a `functions.php` perdido en update del theme | Todo va en **child theme**. Si no existe child theme hoy, crearlo PRIMERO como pre-requisito del Bloque 1. | Cyn confirma estructura `wp-content/themes/<theme>-child/` antes de empezar. |
| Hooks de display no-existentes en versiones viejas de WP | `wp_enqueue_scripts`, `is_checkout()`, `is_cart()`, `is_wc_endpoint_url()` existen desde WP 3.x + WC 2.0. No hay riesgo. | Verificar versión WP en staging (esperable ≥6.0). |
| CSS/JS no se cargan por cache de página | `wp_enqueue_script` con versión bumped (`'1.0'`, `'1.1'`, etc) - el cache plugin re-cachea el HTML pero la URL del asset cambia. | Después de deploy, purgar cache manualmente y verificar `view-source:` que apunte a la versión nueva. |
| Conflicto con plugin de seguridad (Wordfence, iThemes) | El JS es vanilla, no usa eval ni innerHTML con datos del server. No dispara reglas WAF. | Test en staging con todos los plugins de seguridad activos. |

## 2. WooCommerce - Checkout (`/finalizar-compra/`)

| Riesgo | Mitigación en el plan | Cómo verificarlo |
|---|---|---|
| **Romper el AJAX `update_order_review`** que WC dispara al cambiar campos | El stepper se inyecta vía `woocommerce_before_checkout_form` (**fuera del `<form>` y fuera de `#order_review`**). Los wrappers `data-archi-step` envuelven bloques EXISTENTES del form, no reemplazan ni mueven inputs. | En staging: cambiar el país, ver que `#order_review` se refresca sin perder el stepper visual ni los pasos. |
| Botón "Continuar al pago" disparando submit del form por accidente | Botón con `type="button"` explícito (NUNCA `type="submit"`). JS captura el click con `addEventListener` + `e.preventDefault()` defensivo. | DevTools: inspeccionar el botón, confirmar `type="button"`. Hacer click sin completar campos: NO debe disparar POST a checkout. |
| Validación required se saltea | Los inputs siguen en el DOM con `display:none`. WC sigue validando server-side al submit final. Si hay campos required vacíos, WC los muestra como error en la respuesta AJAX y nuestro JS abre el paso 2 con el error visible. | Test: enviar form con campos vacíos. Debe rechazarlo, mostrar error, y el stepper debe volver al paso 2 con los errores resaltados. |
| FooEvents agrega campos custom al checkout y los oculta el multi-step | **Verificación previa obligatoria:** inspeccionar en DevTools si FooEvents inyecta attendee fields en el checkout. Si lo hace, ponerlos en el wrapper `data-archi-step="2"` (datos) o en uno nuevo entre 2 y 3. | Hacer una orden con un producto FooEvents tipo evento. Confirmar que TODOS los campos requeridos se ven en algún paso visible. |
| El gateway "Pago con Transferencia" se renderiza dentro de `#order_review` | El paso 3 (visualmente "Pagá por transferencia") apunta a `#order_review` que se mantiene en el DOM siempre. JS solo controla la visibilidad de los bloques de datos personales. | Test: cambiar de gateway entre opciones, ver que el contenido actualiza vía AJAX y el stepper sigue marcando paso 3. |
| Sesión de WC se pierde | NO se tocan cookies `wc_session_cookie_*` ni `wp_woocommerce_session_*`. JS no llama a `wc_cart` APIs. | Sesión persiste entre reloads (test obvio). |
| **Nonce CSRF del checkout** | NO se toca el campo `woocommerce-process-checkout-nonce`. WC lo regenera automáticamente vía AJAX `update_order_review`. | Test: rellenar checkout, esperar 30 min, submit. WC refresca nonce solo. |

## 3. WooCommerce - Cart (`/carrito/`)

| Riesgo | Mitigación |
|---|---|
| Botones de qty (-/+) del cart - WC tiene AJAX para `update_cart` | NO los tocamos. El restyling apunta a `.woocommerce-cart-form` con CSS, no reemplaza JS de WC. |
| Botón "Update cart" o "Apply coupon" | Idem. Solo CSS. |
| Cupones - logica server-side | Sin cambios. |

## 4. WooCommerce - Order pay (`/order-pay/<id>/`)

| Riesgo | Mitigación | Verificación |
|---|---|---|
| **El form de PeproDev tiene su propio nonce** | NO se reemplaza ni se replica el form. Solo se restylea via CSS apuntando a sus selectors. | DevTools: confirmar que el hidden `_wpnonce` o `pcb_nonce` (o como se llame en PeproDev) sigue presente. |
| Validación de archivo (MIME, tamaño) del lado de PeproDev | NO se toca. El `<input type="file">` se oculta visualmente pero el `<label for="...">` activa el input normalmente. | Test: subir archivo > 8MB. PeproDev debe rechazarlo con su mensaje habitual. |
| Cambio de estado `receipt-upload` → `receipt-approval` al upload | Hecho por PeproDev. No tocamos su action handler. | Después de upload exitoso, verificar en WP admin que la orden cambió a "Esperando Aprobación de los Comprobantes". |
| **Webhook al bot Telegram dispara correctamente** | El snippet 37364 engancha `woocommerce_order_status_changed` filtrando `new_status='receipt-approval'`. Como PeproDev sigue cambiando el estado, el hook sigue disparando. | Test: subir comprobante, ver que en <30s llega al grupo Telegram con foto + botones (escenario A de Make.com). |
| Aprobar/Rechazar desde Telegram cambia estado vía WC REST API | Sin cambios. Make.com escenario B sigue idéntico. | Test: aprobar desde Telegram, orden pasa a `completed`, FooEvents manda ticket por mail. |

## 5. FooEvents

| Riesgo | Mitigación | Verificación |
|---|---|---|
| Generación de ticket PDF al pasar a `completed` | FooEvents engancha `woocommerce_order_status_completed`. La orden sigue pasando a `completed` por el flujo normal (bot/admin). Sin cambios. | Verificar que llega mail con ticket PDF después de aprobación. |
| Attendee custom fields | Verificación previa obligatoria (ver tabla 2 arriba). | Test con producto FooEvents tipo evento con custom fields. |
| FooEvents Check-Ins app sincroniza con backend | App lee via WC REST API. Sin cambios en API. | Coope abre la app, confirma que el ticket aparece después de aprobación. |
| FooEvents emails (ticket attachment, attendee reminder) | Disparados por hooks de WC + propios. NO se tocan. | Mail con PDF llega. |
| Producto FooEvents con `_bot_silent_close=1` meta | Esa meta sigue silenciando emails. No la tocamos. | Sin cambios. |

## 6. PeproDev

| Riesgo | Mitigación | Verificación |
|---|---|---|
| **Selectors CSS del plugin pueden cambiar entre versiones** | Antes del Bloque 2: documentar versión exacta del plugin + capturar selectors en screenshot. Si el plugin se actualiza, volver a verificar antes de redeploy. | Capturar `View Page Source` de `/order-pay/` con orden de prueba activa. Documentar selectors en este checklist. |
| Form de upload se rompe por CSS conflictivo | CSS scopeado a `body.archi-funnel .pcb-upload-form` o similar (clase agregada por el JS al body). Nunca CSS global. | Test rápido: bajar nuestra CSS, verificar que el form vuelve al estado vanilla y funciona. |
| Meta keys (`receipt_upload_status`, etc) | NO se tocan. PeproDev las maneja. | Verificar en WP admin después de upload que las metas se setearon. |
| Estado `receipt-rejected` | Sin cambios. La UI del paso 5 muestra el estado correcto leyendo la order. | Test: rechazar manual desde admin, abrir thank-you page, ver mensaje de rechazo. |

## 7. Snippet WPCode 37364 (bot Telegram)

| Riesgo | Mitigación |
|---|---|
| Se rompe la sanitización de comillas (Bug 1 documentado) | El snippet sigue intacto. NO se replica su lógica en el child theme. |
| Se rompe el webhook a Make.com | Sin cambios al snippet ni a la URL. |
| Se rompe el thumbnail PDF (Bug 5b) | Sin cambios. PeproDev sigue generando el attachment, WP/ImageMagick sigue generando el `-pdf.jpg`. |

## 8. Make.com (escenarios A y B)

Sin cambios. Los escenarios siguen escuchando el mismo webhook y haciendo los mismos calls al WC REST API. No los tocamos en absoluto.

## 9. Auto-creación de cuentas WP

| Riesgo | Mitigación |
|---|---|
| Romper el flujo "guest checkout creates account automatically" que WC tiene activado | NO se filtra `woocommerce_checkout_create_customer`, ni `woocommerce_checkout_customer_id`, ni `woocommerce_registration_is_user_logged_in`. El checkbox "Crear cuenta" del checkout sigue presente. |
| Customer asociado a la orden | WC sigue asociando `user_id` a la order vía su lógica nativa. El user_id es lo que el bot Telegram registra en la nota privada al aprobar. |
| Login/My Account | Páginas `/mi-cuenta/`, `/finalizar-compra/?login=true`, `/orders/` siguen funcionando. NO se les agrega CSS ni JS. |

## 10. Plugins de terceros que pueden colisionar

Lista de plugins comunes en sitios WC que requieren verificación específica en staging:

| Plugin | Posible conflicto | Cómo verificar |
|---|---|---|
| **TranslatePress / WPML / Polylang** | Filtros `gettext` interceptados. Como NO usamos `gettext`, no hay riesgo. | Test simple: cambiar idioma, ver que stepper y CTAs siguen funcionando. |
| **WP Rocket / W3 Total Cache** | Minify CSS/JS puede romper selectors. Cache de página puede no servir JS nuevo. | Después de deploy, purgar cache. Verificar que CSS minify excluye `checkout-funnel.css` si hace falta. |
| **Yoast / RankMath** | Schema.org en checkout - no afecta. | Sin verificación específica. |
| **Wordfence / iThemes Security** | Reglas WAF contra JS sospechoso. Nuestro JS es vanilla, no debería disparar reglas. | Logs de Wordfence después del primer test. |
| **Elementor / Divi / page builders** | Si el theme usa Elementor para el checkout, el markup puede ser muy distinto. | Verificar tema actual: si es Elementor-based, adaptar selectors. |
| **Cookie Notice / GDPR plugins** | Banner fixed en bottom puede tapar CTA. | Test visual mobile + desktop. |
| **WooCommerce Customizer / Storefront Powerpack** | Pueden filtrar `woocommerce_order_button_text` y pisar nuestro filtro. | Verificar orden de prioridad de filtros, ajustar prioridad si hace falta. |

## 11. Edge cases del flujo de cliente

| Caso | Comportamiento esperado | Cómo lo cubre el plan |
|---|---|---|
| Cliente cierra pestaña en paso 3, vuelve por link del mail | Ve el QR + stepper con paso 3 activo | El email "Tu pedido está pendiente" linkea a `/order-pay/<id>/` que es el paso 4 visual. Ajustar copy del email vía filtro `woocommerce_email_subject_customer_processing_order` para que diga "Subí el comprobante" en lugar de "pedido pendiente". |
| Cliente refresca el checkout antes de submit | Vuelve al paso 2 (estado limpio) | Aceptable. Opcional: persistir step en `sessionStorage`. |
| Cliente compra con cuenta existente (logueado) | Datos pre-cargados en paso 2 | WC ya hace esto. Solo verificar visualmente. |
| Cliente intenta saltearse el paso 4 cerrando pestaña | Orden queda en `receipt-upload`. NO recibe ticket. | Comportamiento actual, sin cambios. Idealmente: cron job que limpia órdenes en `receipt-upload` > 7 días con mail al cliente. Fuera de alcance acá. |
| Cliente sube comprobante falso | PeproDev lo acepta como archivo. Bot Telegram lo muestra al grupo. Coope rechaza. Cliente recibe mail. | Sin cambios. |
| Cliente sube archivo no-imagen (zip, docx) | PeproDev rechaza por MIME. | Sin cambios. |
| Cliente compra 2 tickets en distintos eventos (carrito mixto) | Una sola orden, un solo comprobante, FooEvents genera 2 tickets distintos | Sin cambios. El stepper muestra ambos en el "Tu pedido" del paso 1. |

## 12. Rollback inmediato si algo se rompe

```php
// functions.php (child theme)
// COMENTAR las siguientes 4 líneas y purgar cache para volver al estado pre-funnel
// add_action('wp_enqueue_scripts', 'archi_funnel_enqueue');
// add_action('woocommerce_before_checkout_form', 'archi_render_stepper_step_2_3');
// add_action('woocommerce_before_pay_form', 'archi_render_stepper_step_4');
// add_filter('woocommerce_order_button_text', 'archi_order_button_text');
```

Tiempo total de rollback: <5 minutos. NO hay migration de DB que revertir. NO hay órdenes que reparar. NO hay usuarios afectados.

## 13. Pre-requisitos antes de tocar staging

- [ ] **Child theme existe** (si no, crearlo)
- [ ] **Staging clonado de producción** con todos los plugins activos
- [ ] **Versión documentada** de WordPress, WooCommerce, FooEvents, PeproDev, theme actual
- [ ] **Backup completo** de staging antes del primer commit
- [ ] **Acceso a wp-admin de staging** para Cyn + Joaco
- [ ] **Bot Telegram apuntando a grupo de staging** (no al de producción) para no contaminar las alertas reales
- [ ] **Make.com con escenario duplicado** apuntando al WC REST de staging (no producción)

## 14. Plan de test en staging - secuencia mínima exigida

Antes de aprobar deploy a producción, los 7 escenarios de `aplicacion-wordpress.md` (sección "Verificación end-to-end") deben pasar **sin errores en console del browser ni en error_log de PHP**. Adicionalmente:

- [ ] `tail -f wp-content/debug.log` durante 1 orden de prueba completa - no debe aparecer ningún warning ni notice nuevo introducido por nuestro código
- [ ] Console del browser durante todo el flujo - no debe haber errores JS rojos
- [ ] Network tab - el endpoint `?wc-ajax=update_order_review` sigue respondiendo 200 con HTML válido
- [ ] WP admin → Pedidos: la orden de prueba aparece con todos los datos correctos
- [ ] WP admin → Usuarios: el customer fue creado correctamente
- [ ] Mail recibido tiene el ticket PDF como adjunto
- [ ] El ticket en la app FooEvents Check-Ins aparece para los coopes

## 15. Incertidumbres restantes (a resolver con Cyn/Alex en staging)

- Theme actual de archibrazo.org: ¿cuál es? ¿tiene child? (no hay info en docs locales)
- Versión exacta de PeproDev y sus selectors CSS
- Si FooEvents tiene attendee fields configurados en checkout o solo en order-received
- Si hay plugins de cache activos en producción
- Si el sitio tiene WPML/Polylang (parece que NO, todo está en español únicamente)
- Si el botón "Realizar pedido" del checkout actual ya está customizado por algún snippet existente que pueda colisionar con `woocommerce_order_button_text`

Todas estas incertidumbres NO son bloqueantes para escribir el plan; son verificaciones del primer día de trabajo en staging.

---

## Veredicto

Implementado tal cual está documentado, el rediseño UI-only del funnel **NO toca ningún punto crítico** del stack WordPress + WooCommerce + FooEvents + PeproDev + bot Telegram. El peor caso (CSS/JS roto) deja el sitio funcionando "vanilla" pero feo, sin pérdida de datos ni de órdenes. El rollback es comentar 4 líneas en `functions.php`.

Las únicas operaciones que cambian comportamiento son:
1. CSS visual (zero riesgo funcional)
2. JS de show/hide de bloques (zero riesgo si está bien escrito: type="button", preventDefault, sin tocar APIs de WC)
3. Markup inerte inyectado vía hooks de display (zero riesgo)
4. Un único filtro PHP (`woocommerce_order_button_text`) scopeado al botón del checkout (zero riesgo de propagación)

Todo lo demás del stack sigue funcionando exactamente como hoy.
