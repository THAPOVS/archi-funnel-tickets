# Cómo bajar el prototipo a archibrazo.org (WordPress + WooCommerce + FooEvents)

> **Principio rector:** este rediseño es UI-only. NO toca el motor de checkout de WooCommerce, NO crea páginas custom, NO intercepta hooks que afecten el flujo de orden ni la creación de usuarios. El cliente sigue creando exactamente la misma orden que crea hoy. Lo único que cambia es cómo se ve durante el camino.

## Lo que YA hace hoy archibrazo.org (que NO podemos romper)

| Cosa | Quién la hace | Por qué no se toca |
|---|---|---|
| Crear cuenta WP automáticamente al comprar | WooCommerce (`Allow customers to create an account during checkout`) + filtro `woocommerce_checkout_create_customer` | El bot Telegram + FooEvents + mailings dependen del `user_id` asociado a la orden. |
| Validar nonce del form de checkout | WooCommerce core (`woocommerce_process_checkout_nonce`) | Es la única defensa CSRF del checkout. Tocarlo = vulnerabilidad. |
| Generar orden con estado custom `receipt-upload` | Gateway "Pago con Transferencia" (PeproDev / plugin asociado) | Es el trigger para que la página siguiente muestre el formulario de upload. |
| Inyectar formulario de comprobante en `/order-pay/` o `/order-received/` | PeproDev | El plugin tiene su propio nonce, su propia validación de archivo, su propio hook para cambiar estado a `wc-receipt-approval`. |
| Disparar webhook a Make.com cuando orden pasa a `receipt-approval` | Snippet WPCode 37364 (hook `woocommerce_order_status_changed`) | Es lo que dispara el aviso al grupo Telegram. Si cambio el estado o salteo el hook, el bot se calla. |
| Generar ticket FooEvents al pasar a `completed` | FooEvents (hook `woocommerce_order_status_completed`) | Es lo que manda el ticket PDF al mail del cliente. |
| Sanear comillas en producto/customer/email/phone | Snippet 37364 (post Bug 1) | Defensa contra inyección en el JSON del webhook. |

**Regla absoluta:** ningún cambio de UI puede modificar el estado de una orden, cambiar el flujo de redirects post-submit, ni reemplazar/duplicar los formularios que ya renderizan WooCommerce o PeproDev.

## Lo que SÍ podemos hacer (sin tocar el motor)

1. **Restylear con CSS** el form de checkout de WooCommerce, el form de upload de PeproDev, y la página thank-you.
2. **Agregar JS de UI-only** que muestre/oculte secciones del form de checkout en pasos visuales. **El submit final sigue siendo el submit estándar de WooCommerce**, con todos los campos válidos en el DOM al momento del POST.
3. **Inyectar un stepper visual** en cada una de las páginas del flujo (checkout, order-pay, thank-you) vía hooks de display (`woocommerce_before_checkout_form`, `woocommerce_before_pay_form`, `woocommerce_before_thankyou`). El stepper es markup HTML inerte, sin lógica.
4. **Cambiar textos** de WooCommerce y PeproDev vía filtros de display (`gettext`, `woocommerce_gateway_title`, options del plugin).

---

## Mapeo: 5 pasos del prototipo → páginas reales de WordPress

| Paso | Página real | Lo que ya pasa hoy | Cambio UI-only |
|---|---|---|---|
| **1. Tu ticket** | `/carrito/` (WooCommerce cart page) | Muestra tabla del carrito + totals + cupón + botón "Finalizar compra" | CSS para reorganizar como card. Stepper arriba. Botón "Continuar". |
| **2. Tus datos + 3. Pagar** | `/finalizar-compra/` (WooCommerce checkout page) | Form con billing fields + gateway QR con el QR embedded + checkbox términos + botón "Realizar pedido" | JS multi-step UI: muestra billing fields primero, después de "Continuar al pago" oculta los billing y muestra el QR + alias + instrucciones. Stepper arriba. **El form sigue siendo UNO solo**, los campos billing siguen en el DOM (ocultos visualmente con `display:none`), el submit sigue siendo el de WC. |
| **4. Subí tu comprobante** | `/order-pay/<order_id>/` o `/order-received/<order_id>/` (depende de config de PeproDev) | PeproDev renderiza su form de upload + checkbox términos + botón "Subir comprobante" | CSS para que se vea como el paso 4 del prototipo (drop zone visual, resumen del pedido). Stepper arriba. NO se reemplaza el form; se restylea con selectors específicos del plugin. |
| **5. Listo** | Thank-you page de WooCommerce (`/order-received/` después del upload) o página de confirmación de PeproDev | Muestra mensaje genérico "Su pedido está siendo procesado" | CSS + texto custom (vía filtro `woocommerce_thankyou_order_received_text`). Stepper marcando todos pasos como completed. |

---

## Plan de implementación

### 🔴 Bloque 1 - Multi-step UI sobre el checkout existente (medio día, Cyn)

**Approach:** sin plugin, vanilla JS + CSS en el child theme. El form de WooCommerce no se modifica estructuralmente.

1. **Enqueue de assets** condicionado a `is_checkout()` y `is_cart()`:

   ```php
   // functions.php (child theme)
   add_action('wp_enqueue_scripts', function() {
       if (is_checkout() || is_cart() || is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')) {
           wp_enqueue_style('archi-funnel', get_stylesheet_directory_uri() . '/assets/checkout-funnel.css', [], '1.0');
           wp_enqueue_script('archi-funnel', get_stylesheet_directory_uri() . '/assets/checkout-funnel.js', [], '1.0', true);
       }
   });
   ```

2. **Stepper inyectado** vía hooks de display (markup inerte, no toca form ni hooks de orden):

   ```php
   add_action('woocommerce_before_cart', 'archi_render_stepper_step_1');
   add_action('woocommerce_before_checkout_form', 'archi_render_stepper_step_2_3');
   add_action('woocommerce_before_pay_form', 'archi_render_stepper_step_4');
   // En thankyou ya hay un hook usable
   add_action('woocommerce_before_thankyou', 'archi_render_stepper_step_5');

   function archi_render_stepper_step_1() {
       echo archi_stepper_html(1); // misma <nav><ol> del prototipo con dot 1 activo
   }
   // etc.
   ```

3. **JS multi-step UI** sobre el form `<form class="checkout woocommerce-checkout">`:

   - Envuelve los bloques existentes en wrappers `<div data-archi-step="2">` (datos personales) y `<div data-archi-step="3">` (gateway con QR + términos + botón).
   - Hace `<div data-archi-step="3">` invisible al cargar (`display:none`) - pero los inputs siguen en el DOM, así que cuando el usuario llega al paso 3 y aprieta el submit nativo, WooCommerce recibe todos los campos.
   - Botón visual "Continuar al pago" hace solo `document.querySelector('[data-archi-step="2"]').style.display='none'; document.querySelector('[data-archi-step="3"]').style.display='block'; updateStepper(3);`. **No envía nada al server.** **IMPORTANTE:** el botón es `<button type="button">`, nunca `type="submit"`, para no disparar el form de WC.
   - El botón "Realizar pedido" estándar de WooCommerce (el real, el que dispara el submit) se restylea pero NO se reemplaza. La copy del botón se cambia vía filtro **scopeado al botón del checkout específicamente**:

     ```php
     // Cambio scopeado: solo afecta el botón final del checkout, NO mails ni admin
     add_filter('woocommerce_order_button_text', function() {
         return 'Ya pagué, voy a subir el comprobante →';
     });
     ```
     NO usar el filtro genérico `gettext` para esto - cambiaría también el texto en mails al admin y en otros lados donde aparece "Place order".

4. **Validación client-side antes de avanzar de paso 2 a 3:** chequear que los campos requeridos del billing estén llenos. Si no, marcar en rojo y NO avanzar. Esto es UX, no seguridad: WooCommerce sigue validando server-side al submit.

### 🔴 Bloque 2 - Restylear el formulario de PeproDev (3 horas, Cyn)

PeproDev renderiza su form en `/order-pay/<id>/` o en la thank-you (depende de cómo lo configuró Alex). Inspeccionar en DevTools para confirmar:

- ¿Qué selector tiene el contenedor? (probablemente `.pcb-upload-form` o `.pepro-receipt-upload`)
- ¿Qué selectors tiene el `<input type="file">`, el botón submit, los labels?

Una vez identificados, **agregar CSS al mismo `checkout-funnel.css`** que apunte a esos selectors específicos:

```css
.pcb-upload-form { /* card layout del prototipo */ }
.pcb-upload-form input[type="file"] { display: none; } /* hide nativo */
.pcb-upload-form label[for="pcb-receipt"] {
  /* convertir el label en la drop zone visual del prototipo */
}
.pcb-upload-form button[type="submit"] {
  /* mismo estilo que .btn-primary del prototipo */
}
```

**Sin tocar el HTML del plugin.** Solo CSS. Si el plugin tiene un classes-mode pero los selectors cambian en una update, el form vuelve a verse "vanilla", no se rompe la funcionalidad.

**Sumar el resumen del pedido y el banner "último paso"** vía hook de display ANTES del form:

```php
add_action('woocommerce_before_pay_form', function() {
    echo '<div class="archi-paso-4-intro">';
    echo '<p class="eyebrow">Último paso</p>';
    echo '<h1>Subí tu comprobante</h1>';
    echo '<p class="sub">Sin este paso <strong>no se genera el ticket</strong>...</p>';
    echo '</div>';
}, 5);
```

### 🟠 Bloque 3 - Copy y CTAs (2 horas, Cyn)

Cambios vía filtros, no tocan el motor:

| Donde | Cómo | Resultado |
|---|---|---|
| Botón final del checkout | Filtro **`woocommerce_order_button_text`** (NO `gettext`, que afectaría también mails y admin) | `Ya pagué, voy a subir el comprobante →` |
| Título del gateway "Pago con Transferencia" | Filtro `woocommerce_gateway_title` o config del gateway | `Transferencia con QR (subís el comprobante después)` |
| Texto thank-you genérico | Filtro `woocommerce_thankyou_order_received_text` | `Recibimos tu comprobante. En cuanto lo verifiquemos te llega el ticket por mail.` (variante: si la orden está en `receipt-upload`, mostrar la copy de "subí el comprobante"; si está en `receipt-approval`, mostrar la copy del paso 5) |
| Botón submit de PeproDev | Plugin settings o filtro del plugin | `Enviar comprobante y finalizar →` |
| Banner amarillo de warning en `/order-pay/` | Hook `woocommerce_before_pay_form` con markup propio | Copy directa: "Pagar sin subir el comprobante NO genera el ticket. Te lleva 30 segundos más." |

### 🟢 Bloque 4 - Mobile + edge cases (3 horas, Cyn)

1. **Media queries** del prototipo (`@media (max-width: 640px)`) aplicadas a los mismos selectors WC + PeproDev. Stepper colapsa a "Paso N de 5 - <label>".
2. **Estado de "ya logueado":** el flujo actual permite crear cuenta o checkout-as-guest. Si el usuario ya está logueado, el paso 2 (datos) se ve precargado y se puede saltear con un botón "Estos son mis datos, continuar".
3. **Estado de "orden ya pagada/aprobada":** si el cliente vuelve a `/order-received/<id>/` después de que la orden ya fue aprobada por el bot, el stepper muestra los 5 pasos verdes y el contenido cambia a "Tu ticket ya está confirmado, revisá el mail".
4. **Estado de "orden rechazada":** si la orden está en `receipt-rejected`, mostrar mensaje claro con instrucciones de qué hacer (escribir a archibrazo@gmail.com con el comprobante real). Stepper muestra paso 4 en rojo en lugar de verde.

---

## Garantías de seguridad

| Riesgo | Cómo lo evitamos |
|---|---|
| CSRF en el checkout | NO se toca el nonce de WC. El submit sigue siendo el form nativo. |
| XSS por copy custom | Todos los textos custom se passan por `esc_html()` / `wp_kses_post()` al renderear en PHP. JS no usa `innerHTML` con datos del server. |
| Saltearse el upload del comprobante | NO se cambia el estado de orden por JS. WooCommerce + PeproDev siguen siendo dueños del estado. Si alguien sabotea el JS y "salta" al paso 5, su orden sigue en `receipt-upload` en el backend, no recibe ticket. |
| Romper hooks del bot Telegram | El estado `receipt-approval` lo sigue setando PeproDev al recibir el upload. El snippet 37364 sigue disparando al webhook. Sin cambios. |
| Romper la creación de usuarios | El form de checkout sigue con sus checkboxes nativos ("Crear cuenta"). NO se filtra `woocommerce_checkout_create_customer`. Si quisiéramos forzar "guest checkout" tendríamos que hablarlo aparte. |
| File upload malicioso | PeproDev ya valida MIME, tamaño, extensión. No se reemplaza esa validación. El restyling del input no permite saltear validación server-side. |
| SQL injection via meta | No agregamos meta nueva. No tocamos `wpdb` directamente. Solo CSS y JS de UI. |
| Plugins incompatibles | Antes de deploy a producción, listar plugins activos y verificar que ninguno engancha hooks de display que reseteen el stepper. Probable: TranslatePress, Loco Translate, Yoast - todos OK. |
| WordPress / WooCommerce update | El CSS apunta a selectors estables de WC (`.woocommerce-checkout`, `#order_review`, etc). El JS no monkey-patcha APIs internas. Una update de WC podría cambiar markup, pero el form sigue funcionando "vanilla". Verificar después de cada update mayor. |

---

## Archivos que se tocan (todo en child theme)

```
wp-content/themes/<theme-child>/
├── functions.php                   # hooks de enqueue + render del stepper + filtros gettext
├── assets/
│   ├── checkout-funnel.css         # estilos UI-only del flujo
│   └── checkout-funnel.js          # multi-step UI del checkout (vanilla, ~150 líneas)
└── inc/
    └── archi-stepper.php           # función helper que genera el HTML del stepper
```

**Archivos que NO se tocan:**
- Templates de WooCommerce (`woocommerce/checkout/*.php`)
- Templates de FooEvents
- Settings de plugins
- Cualquier archivo de `wp-content/plugins/`
- `.htaccess`, `wp-config.php`
- DB (sin migrations, sin custom tables)

---

## Verificación end-to-end (post-deploy a staging WP)

**Pre-requisito:** clonar archibrazo.org producción a `staging.archibrazo.org` (Cyn con apoyo del hosting). El staging debe replicar EXACTO: misma versión de WP, WooCommerce, FooEvents, PeproDev, mismos plugins activos, misma config del gateway QR.

**Plan de test (Joaco + Alex desde 2 celulares + 1 desktop):**

1. **Guest checkout - flujo feliz JPG:** orden de prueba sin loguearse, comprobante JPG, aprobación manual desde wp-admin de Alex.
   - ✓ Stepper visible en cart, checkout, order-pay, thank-you.
   - ✓ Usuario creado en WP automáticamente (verificar en Usuarios).
   - ✓ Bot Telegram recibe la orden con foto + botones.
   - ✓ Al aprobar, mail con ticket FooEvents llega.

2. **Cliente ya logueado - flujo feliz PDF:** desde cuenta existente, comprobante PDF.
   - ✓ Datos pre-cargados en paso 2, opción "estos son mis datos" funciona.
   - ✓ Bot recibe thumbnail PDF (Bug 5b ya resuelto).
   - ✓ Auto-aprobación por Gemini (si está activa) o aprobación humana.

3. **Cliente que abandona en paso 3 y vuelve:** dejar el QR a la vista, cerrar pestaña, volver vía link del mail "Tu pedido está pendiente".
   - ✓ Vuelve a la página de pago con el stepper marcando paso 3 actual.
   - ✓ Puede continuar al paso 4 (upload).

4. **Cliente que sube un archivo > 8MB / formato inválido:**
   - ✓ Validación de PeproDev sigue funcionando (mensaje de error en español).
   - ✓ Stepper NO avanza, cliente sigue en paso 4.

5. **Orden rechazada por el bot:** comprobante falso, coope aprieta Rechazar en Telegram.
   - ✓ Cliente recibe mail de rechazo con instrucciones.
   - ✓ Si vuelve a la web, ve el stepper en estado "paso 4 rojo".

6. **Mobile real (iOS Safari + Android Chrome):** repetir flujo feliz desde celular.
   - ✓ Sticky CTA en bottom no tapa contenido.
   - ✓ Upload desde fotos del rollo funciona.
   - ✓ Stepper colapsado legible.

7. **Test de regresión** sobre el resto del sitio:
   - ✓ Eventos, talleres, todas las páginas del menú siguen funcionando.
   - ✓ Login / mi-cuenta / mis-pedidos siguen funcionando (no se rompe la cuenta WP).
   - ✓ FooEvents Check-Ins en la app de los coopes sigue mostrando tickets correctamente.

**Métricas pre/post deploy:**
- % de órdenes que llegan al paso `receipt-upload` (today probablemente ~100%)
- % de órdenes que efectivamente suben comprobante (estimado: ~70-85%, objetivo: >95%)
- Tiempo promedio cart → comprobante subido (estimado: ~3-7 min, objetivo: <2 min)
- Tasa de abandono entre paso 3 (pagar) y paso 4 (subir comprobante) - este es el número clave que el rediseño busca mover.

---

## Orden sugerido de ejecución

1. **Aprobación del prototipo en asamblea de coopes** (Joaco + Cyn + Alex + Pablo, 30 min). Mirar screenshots + clickear el prototipo local. Validar copy.
2. **Cyn levanta staging.archibrazo.org** clonando producción (1h + tiempo del hosting).
3. **Bloque 1 + Bloque 2 + Bloque 3** en staging: 1-2 días de Cyn.
4. **Verificación end-to-end** con los 7 escenarios de arriba: 1 día (Joaco + Alex).
5. **Si hay rechazos: ajustar CSS, repetir.** No alterar lógica de plugins.
6. **Deploy a producción** en franja de bajo tráfico (martes 10-12hs). Backup de child theme antes del push.
7. **Monitoreo activo del bot Telegram durante 48hs** post-deploy. Si entran 5-6 órdenes y todas pasan por el flujo nuevo sin perderse en el camino, declarar éxito.

---

## Lo que este plan explícitamente NO hace

- **NO migra a MODO Empresas.** Ortogonal. Cuando MODO esté disponible, aparece como gateway adicional al lado del QR. Si el cliente elige MODO, los pasos 3 y 4 colapsan en uno (no hay comprobante que subir).
- **NO toca el bot Telegram ni los escenarios de Make.com.** Siguen funcionando idéntico.
- **NO cambia FooEvents Check-Ins** ni la lógica de tickets.
- **NO afecta cuentas WP existentes**, customers, órdenes históricas.
- **NO toca SEO** de las páginas del checkout (que de todas formas tienen `noindex` por default en WC).
- **NO migra de plugin de upload (PeproDev).** Si en algún momento se reemplaza, este plan se rehace con los selectors del plugin nuevo.

---

## Si algo se rompe en producción: rollback en 5 minutos

1. Comentar la línea de `wp_enqueue_style` y `wp_enqueue_script` en `functions.php`.
2. Comentar los `add_action` de los `archi_render_stepper_*`.
3. Comentar los filtros de `gettext` y `woocommerce_thankyou_order_received_text`.
4. Limpiar cache (W3 Total Cache / WP Rocket → Purge All).

El sitio vuelve al estado pre-funnel sin perder ni una orden ni un usuario, porque ningún cambio tocó el motor.
