# Integración Fase 1 - admin-only

> Snippet de WPCode Lite para meter el rediseño en `archibrazo.org` con guardrail: solo lo ves vos (admin logueado), los visitantes públicos no notan nada. Cero riesgo para el flujo real.

## Qué hace este snippet

- Agrega **stepper visual** ("Ticket · Datos · Pagar · Comprobante · Listo") en cart, checkout, order-pay y thank-you.
- Cambia el **texto del botón final** del checkout a "Ya pagué, voy a subir el comprobante →".
- Inyecta un **banner crítico** en `/order-pay/` que dice "Pagar sin subir el comprobante NO genera el ticket".
- Cambia la **copy del thank-you** según el estado real de la orden (4 variantes: receipt-upload, receipt-approval, receipt-rejected, completed).
- Imprime una notice amarilla "Estás viendo el rediseño en modo preview" para que sepas que el guardrail está activo.

## Qué NO hace (Fase 1)

- **No agrega multi-step JS** (el checkout sigue siendo 1 página densa). Eso va en Fase 2.
- **No restilea** el form de WC ni de PeproDev. Solo agrega el stepper + banner + copy.
- No cambia precios, productos, gateways ni nada del backend.

## Instalación

1. WP admin → **Code Snippets** (o **WPCode**) → **Add Snippet** → **PHP Snippet**
2. Title: `Archi Funnel - Fase 1 (admin-only)`
3. Pegar el contenido completo de [`archi-funnel-fase-1.php`](./archi-funnel-fase-1.php)
4. Insertion: **Auto Insert** → **Run Everywhere**
5. **Save & Activate**

## Cómo probarlo

Estando logueado como admin (vos, Alex o Cyn con rol Administrator / Shop Manager):

1. Agregá un ticket al carrito desde `archibrazo.org/eventos/...`
2. Ir a `/carrito/` → tiene que verse el stepper arriba con el paso 1 activo.
3. Click en "Finalizar compra" → en `/finalizar-compra/` tiene que verse el stepper con paso 2 activo + el botón final dice "Ya pagué, voy a subir el comprobante →".
4. Completar datos, generar la orden → te lleva a `/order-pay/<id>/` → ahí ves stepper paso 4 + eyebrow "Último paso" + banner amarillo crítico.
5. Subir un comprobante de prueba → te lleva al thank-you → stepper paso 5 + copy "Recibimos tu comprobante...".
6. Cerrar sesión / abrir en incógnito → confirmar que el sitio se ve idéntico al actual (sin stepper, sin banner, sin cambio de copy).

## Si querés sumar a Alex/Cyn al preview

El snippet ya los incluye automáticamente si tienen rol Administrator o Shop Manager. No hace falta mandarles nada: cuando se loguean, ven el rediseño automáticamente.

## Si algo se rompe

WP Admin → Code Snippets → snippet "Archi Funnel - Fase 1 (admin-only)" → **Deactivate**. 5 segundos. Sitio vuelve al estado actual sin perder nada.

Si te equivocaste pegando el código y el snippet rompe el sitio entero (raro pero posible con PHP): entrar al WP admin vía `/wp-admin/`, ir a Code Snippets, deactivate. Si WPCode no carga, conectarte vía FTP a `/wp-content/plugins/wpcode-lite/` y renombrar la carpeta - eso desactiva el plugin entero y el sitio vuelve.

## Garantías de seguridad

- **Guardrail explícito** en cada hook: si el visitante no es admin Y no estamos en staging, ningún cambio se aplica.
- **Hooks de display únicamente** (`wp_head`, `woocommerce_before_*`). Ninguno modifica el estado de la orden ni los datos del customer.
- **Sin tocar plugins** (WooCommerce, FooEvents, PeproDev, snippet 37364, Make.com). Solo lectura.
- **Sin DB writes**. Ningún `update_post_meta`, ningún `wp_insert_post`.
- **Escape correcto**: todos los datos de la orden (email, status) se passan por `esc_html()` antes de renderear.

## Qué pasa después de Fase 1

Cuando estés conforme con cómo se ve el stepper + copy nueva:

- **Fase 2** = agregamos snippet adicional con CSS completo (paleta full del prototipo) + JS multi-step UI (oculta billing fields hasta apretar "Continuar"). Requiere staging clonado para test end-to-end con bot Telegram apuntando a grupo de prueba. Esa parte la coordinamos con Cyn.
- **Fase 3** = sacamos el guardrail `archi_funnel_should_apply()` (lo cambiamos para que retorne `true` siempre, o lo eliminamos). Queda visible para todos los visitantes.

## Archivos en este paquete

- `archi-funnel-fase-1.php` - el snippet para pegar en WPCode
- `README-fase-1.md` - este archivo
