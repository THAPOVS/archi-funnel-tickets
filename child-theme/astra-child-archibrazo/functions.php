<?php
/**
 * Astra Child Archibrazo - functions.php
 *
 * Hereda todo del padre Astra y agrega:
 *   1. Enqueue del CSS del padre + child
 *   2. Tipografías (Andralis ND local + Manrope de Google) en páginas del flujo
 *   3. Stepper visual de 5 pasos en cart/checkout/order-pay/thank-you
 *   4. JS multi-step UI para el checkout (oculta order_review hasta apretar Continuar)
 *   5. Copy custom del botón final del checkout
 *   6. Copy custom de la thank-you según estado de la orden
 *   7. Banner crítico en order-pay
 *
 * Cuando este child theme esté activo, se pueden DESACTIVAR los snippets
 * de WPCode "Archi Funnel - Fase 1" y "Archi Funnel - Fase 2" - todo
 * lo que hacían vive acá ahora.
 */

if (!defined('ABSPATH')) return;

// =====================================================================
// CONFIG: admin-only vs live
// =====================================================================
// true  = el rediseño SOLO se ve para vos como admin logueado (más seguro)
// false = el rediseño se ve para TODOS los visitantes (deploy live)
//
// Por default está en true para que cuando actives el theme no afecte
// a visitantes públicos. Cuando confirmes que todo está OK, cambiá
// esta línea a false y el rediseño pasa a live.
if (!defined('ARCHI_FUNNEL_ADMIN_ONLY')) {
    define('ARCHI_FUNNEL_ADMIN_ONLY', false);
}

// Automatización de mails del funnel: 'off' | 'test' | 'live'
//   'test' = los mails de evento van SOLO a la casilla boleteria@ (no a clientes)
//            y los crons de scan no corren. Probar con ?archi_test=...&order=ID
//   'live' = manda a los clientes y los crons escanean órdenes reales.
// Cambiar a 'live' recién cuando esté todo testeado.
if (!defined('ARCHI_EMAILS_MODE')) {
    define('ARCHI_EMAILS_MODE', 'live');
}

// Cargar el módulo de automatización de mails (inc/archi-emails.php)
$archi_emails_module = get_stylesheet_directory() . '/inc/archi-emails.php';
if (file_exists($archi_emails_module)) {
    require_once $archi_emails_module;
}
unset($archi_emails_module);

if (!function_exists('archi_funnel_should_apply')) {
    function archi_funnel_should_apply() {
        // Si está en modo live (constante false), aplicar a todos
        if (defined('ARCHI_FUNNEL_ADMIN_ONLY') && ARCHI_FUNNEL_ADMIN_ONLY === false) {
            return true;
        }
        // Modo admin-only: solo aplica si el visitante es admin / shop manager
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        if (strpos($host, 'staging.') === 0) {
            return true;
        }
        if (function_exists('current_user_can')) {
            if (current_user_can('manage_options') || current_user_can('manage_woocommerce')) {
                return true;
            }
        }
        return false;
    }
}

// =====================================================================
// 1. Enqueue del CSS padre + child (priority 999 para cargar DESPUÉS de plugins)
// =====================================================================
add_action('wp_enqueue_scripts', function () {
    // El CSS del padre siempre se carga (sino el sitio se rompe)
    $parent_style = 'astra-theme-css';
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');

    // El CSS del child SOLO se carga si aplica el rediseño
    if (archi_funnel_should_apply()) {
        wp_enqueue_style(
            'astra-child-archibrazo',
            get_stylesheet_directory_uri() . '/style.css',
            array($parent_style),
            // Cache-bust por filemtime: el header Version queda fijo en 2.0.0, así
            // que sin esto el navegador sirve el style.css cacheado tras cada deploy.
            filemtime(get_stylesheet_directory() . '/style.css')
        );
    }
}, 999); // Priority alta para que cargue después de Royal Addons / Astra / etc

// =====================================================================
// 1b. CSS inline en wp_head con priority 9999 para pisar plugins terceros
// =====================================================================
add_action('wp_head', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_woocommerce')) return;
    $is_gracias_comprobante = is_singular('page') && get_the_ID() === 22158;
    if (!(is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay') || $is_gracias_comprobante)) return;
    ?>
    <style id="archi-funnel-override-third-party">
    /* Pisar Royal Elementor Addons (wpr-shop-table) que pinta thead con bg claro */
    body.woocommerce-cart table.wpr-shop-table thead th,
    body.woocommerce-cart table.shop_table thead th,
    body.woocommerce-cart table.cart thead th,
    body.woocommerce-cart .woocommerce table thead th {
        background: #1f1f1f !important;
        background-color: #1f1f1f !important;
        background-image: none !important;
        color: #f5f1ea !important;
        text-shadow: none !important;
    }
    body.woocommerce-cart table.wpr-shop-table thead,
    body.woocommerce-cart table.wpr-shop-table thead tr,
    body.woocommerce-cart table.shop_table thead,
    body.woocommerce-cart table.shop_table thead tr {
        background: #1f1f1f !important;
        background-color: #1f1f1f !important;
        background-image: none !important;
    }
    /* El thead de la tabla del carrito (Royal wpr-shop-table) se rompe: la grilla
       de Royal espera 5 columnas pero la tabla tiene 6 th, así que "Subtotal" cae
       a una 2da fila y el encabezado queda gigante. El carrito es card-style y no
       necesita fila de encabezado: la ocultamos. */
    body.woocommerce-cart table.wpr-shop-table thead,
    body.woocommerce-cart table.shop_table thead,
    body.woocommerce-cart table.cart thead,
    body.woocommerce-cart .woocommerce-cart-form table thead {
        display: none !important;
    }

    /* "Tu pedido" en checkout step 3 - sin recuadro/border blanco extra */
    body.woocommerce-checkout #order_review_heading,
    body.woocommerce-checkout h3#order_review_heading,
    body.woocommerce-checkout form.checkout #order_review_heading,
    body.woocommerce-checkout form.checkout.archi-funnel-step-3 #order_review_heading,
    html body.woocommerce-checkout #order_review_heading {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
        border: 0 none transparent !important;
        border-top: 0 none transparent !important;
        border-right: 0 none transparent !important;
        border-bottom: 0 none transparent !important;
        border-left: 0 none transparent !important;
        border-width: 0 !important;
        border-style: none !important;
        border-color: transparent !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
    }

    /* "Dirección de facturación" en thank-you / order-pay - mismo bug que "Tu pedido":
       fondo blanco con texto crema = ilegible. Neutralizamos y damos color crema sólido. */
    html body h2.woocommerce-column__title,
    html body section.woocommerce-customer-details h2.woocommerce-column__title,
    html body .woocommerce-order-details__title,
    html body h2.woocommerce-order-details__title {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
        border: 0 none transparent !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
        color: #f5f1ea !important;
        text-shadow: none !important;
    }
    /* Wrapper de Dirección de facturación: que herede dark del resto */
    html body section.woocommerce-customer-details,
    html body .woocommerce-customer-details {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
    }
    html body section.woocommerce-customer-details address,
    html body .woocommerce-customer-details address {
        background: transparent !important;
        background-color: transparent !important;
        color: #f5f1ea !important;
        border-color: rgba(245, 241, 234, 0.14) !important;
    }

    /* PEPRODEV: ocultar la fila "Comprobante cargado: Esperando archivo".
       Antes de subir nada, esa fila es ruido visual (preview vacío + placeholder).
       Solo necesitamos la fila "Cargá tu comprobante" con el input + botón. */
    html body table.woocommerce-table--upload-receipt.upload_receipt {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
    }
    html body table.upload_receipt tbody tr:first-child,
    html body table.woocommerce-table--upload-receipt tbody tr:has(td.receipt-img-preview),
    html body td.receipt-img-preview,
    html body td.receipt-img-preview img.receipt-preview.upload,
    html body p.receipt-status.upload {
        display: none !important;
    }
    /* Fila del input upload: full width, sin la th sticky "Cargá tu comprobante" (ya tenemos el title del wrapper) */
    html body table.upload_receipt tbody tr:last-child th,
    html body table.woocommerce-table--upload-receipt tbody tr:last-child th {
        display: none !important;
    }
    html body table.upload_receipt tbody tr:last-child td,
    html body table.woocommerce-table--upload-receipt tbody tr:last-child td {
        display: block !important;
        width: 100% !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
    }

    /* Banner del CUPÓN - position relative para que la X quede al extremo derecho */
    body.woocommerce-checkout .woocommerce-form-coupon-toggle,
    body.woocommerce-cart .woocommerce-form-coupon-toggle,
    body.woocommerce-checkout .woocommerce-form-coupon-toggle .woocommerce-info,
    body.woocommerce-cart .woocommerce-form-coupon-toggle .woocommerce-info,
    body.woocommerce-checkout .woocommerce-info,
    body.woocommerce-cart .woocommerce-info {
        position: relative !important;
        padding-right: 64px !important;
    }

    /* ============================================================
       REDISEÑO DARK de los banners de WooCommerce (info / error / message).
       Los banners blancos default rompen la estética dark del funnel.
       (El banner "carrito vacío" .cart-empty tiene su propio estilo, lo excluimos.)
       ============================================================ */
    html body.woocommerce-checkout .woocommerce-info:not(.cart-empty),
    html body.woocommerce-cart .woocommerce-info:not(.cart-empty),
    html body.woocommerce-checkout .woocommerce-error,
    html body.woocommerce-cart .woocommerce-error,
    html body.woocommerce-checkout .woocommerce-message,
    html body.woocommerce-cart .woocommerce-message,
    html body.woocommerce-checkout .woocommerce-form-coupon-toggle .woocommerce-info,
    html body.woocommerce-cart .woocommerce-form-coupon-toggle .woocommerce-info {
        background: linear-gradient(180deg, #1c1a1f, #15131a) !important;
        background-color: #1c1a1f !important;
        background-image: linear-gradient(180deg, #1c1a1f, #15131a) !important;
        color: #f5f1ea !important;
        border: 1px solid rgba(245, 241, 234, 0.14) !important;
        border-radius: 14px !important;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.3) !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        line-height: 1.55 !important;
        text-shadow: none !important;
        padding: 16px 64px 16px 22px !important;
        width: 100% !important;
        max-width: 920px !important;
        margin-left: auto !important;
        margin-right: auto !important;
        box-sizing: border-box !important;
    }
    /* Acento de color por tipo: naranja info, rojo error, verde message */
    html body.woocommerce-checkout .woocommerce-info:not(.cart-empty),
    html body.woocommerce-cart .woocommerce-info:not(.cart-empty),
    html body.woocommerce-checkout .woocommerce-form-coupon-toggle .woocommerce-info,
    html body.woocommerce-cart .woocommerce-form-coupon-toggle .woocommerce-info {
        border-left: 4px solid #fb923c !important;
    }
    html body.woocommerce-checkout .woocommerce-error,
    html body.woocommerce-cart .woocommerce-error {
        border-left: 4px solid #f87171 !important;
    }
    html body.woocommerce-checkout .woocommerce-message,
    html body.woocommerce-cart .woocommerce-message {
        border-left: 4px solid #34d399 !important;
    }
    /* Links dentro de los banners - naranja, legibles sobre dark */
    html body .woocommerce-info:not(.cart-empty) a,
    html body .woocommerce-error a,
    html body .woocommerce-message a,
    html body .woocommerce-form-coupon-toggle .woocommerce-info a {
        color: #fb923c !important;
        text-decoration: underline !important;
        font-weight: 600 !important;
    }
    /* Icono ::before de WooCommerce - se ve mal/cortado con el theme dark, lo ocultamos */
    html body .woocommerce-info:not(.cart-empty)::before,
    html body .woocommerce-error::before,
    html body .woocommerce-message::before,
    html body .woocommerce-form-coupon-toggle .woocommerce-info::before {
        display: none !important;
    }
    /* El wrapper .woocommerce-form-coupon-toggle es flex y desalinea el banner a la izquierda.
       Lo forzamos a block + sin padding para que el .woocommerce-info (max-width 920 + margin auto)
       quede centrado y alineado con los campos de datos. */
    html body.woocommerce-checkout .woocommerce-form-coupon-toggle,
    html body.woocommerce-cart .woocommerce-form-coupon-toggle {
        display: block !important;
        padding: 0 !important;
        text-align: left !important;
    }

    /* PASO 1 (carrito): retiramos el bloque de cupón. El checkout ya tiene su
       propio campo de cupón, repetirlo en el carrito es redundante (feedback de
       Alex, 2026-05-20). El carrito lo renderea Royal/WC core (no nuestro
       cart.php), por eso se saca por CSS y no desde el template. */
    html body.woocommerce-cart td.actions .coupon,
    html body.woocommerce-cart .woocommerce-cart-form .coupon {
        display: none !important;
    }

    /* Cupón en el checkout: solo en el paso "Pagar" (step 3), no en "Datos" (step 2).
       El toggle de WC se renderea ANTES del form.checkout (es hermano, no descendiente),
       así que detectamos el paso con body:has(form en archi-funnel-step-2). */
    html body.woocommerce-checkout:has(form.checkout.archi-funnel-step-2) .woocommerce-form-coupon-toggle,
    html body.woocommerce-checkout:has(form.checkout.archi-funnel-step-2) .checkout_coupon {
        display: none !important;
    }

    /* CONSISTENCIA DE GRADIENTE: Royal/Astra pisaban los botones del carrito con
       un naranja sólido (#ff6c00). Forzamos el gradiente de marca en todos los
       botones del funnel (incluido "Actualizar carrito") para que se vean iguales
       (feedback de Alex, 2026-05-20). */
    html body.woocommerce-cart .archi-totals__action .checkout-button,
    html body.woocommerce-cart a.checkout-button,
    html body.woocommerce-cart .wc-proceed-to-checkout .checkout-button,
    html body.woocommerce-cart button[name="update_cart"],
    html body.woocommerce-cart input[name="update_cart"],
    html body.woocommerce-checkout #place_order,
    html body .archi-funnel-continue {
        background: var(--archi-gradient) !important;
        background-image: var(--archi-gradient) !important;
        background-color: #ff3d7f !important;
        color: #ffffff !important;
        border: none !important;
    }

    /* Botones dentro de banners / "Seguir comprando" (wc-backward): texto blanco.
       Sobre el fondo naranja del botón, el texto oscuro no contrasta. */
    html body .woocommerce-message .button,
    html body .woocommerce-info .button,
    html body .woocommerce-error .button,
    html body .woocommerce a.button.wc-backward,
    html body .woocommerce a.button.wc-forward,
    html body a.button.wc-backward,
    html body a.button.wc-forward {
        color: #ffffff !important;
    }
    /* X SIEMPRE en el extremo derecho del banner blanco */
    html body .archi-notice-dismiss,
    body.woocommerce-checkout .archi-notice-dismiss,
    body.woocommerce-cart .archi-notice-dismiss {
        position: absolute !important;
        top: 50% !important;
        right: 16px !important;
        left: auto !important;
        transform: translateY(-50%) !important;
        width: 32px !important;
        height: 32px !important;
        border-radius: 999px !important;
        background: rgba(0, 0, 0, 0.6) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 18px !important;
        line-height: 1 !important;
        padding: 0 !important;
        margin: 0 !important;
        z-index: 10 !important;
        transition: background 180ms ease !important;
    }
    html body .archi-notice-dismiss:hover {
        background: rgba(0, 0, 0, 0.8) !important;
    }

    /* Banner "Tu carrito está vacío" - dark coherente, sin X (lo skipea el JS) */
    body.woocommerce-cart .cart-empty.woocommerce-info,
    body.woocommerce-cart .wc-empty-cart-message,
    body.woocommerce-cart div.cart-empty,
    html body.woocommerce-cart .cart-empty,
    html body.woocommerce-cart .wc-empty-cart-message {
        background: linear-gradient(180deg, #161616, #1f1f1f) !important;
        background-color: #161616 !important;
        background-image: linear-gradient(180deg, #161616, #1f1f1f) !important;
        border: 1px solid rgba(245, 241, 234, 0.14) !important;
        border-left: 4px solid #fbbf24 !important;
        border-radius: 14px !important;
        color: #f5f1ea !important;
        padding: 20px 24px !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 15px !important;
        text-shadow: none !important;
    }
    body.woocommerce-cart .cart-empty.woocommerce-info::before,
    body.woocommerce-cart .wc-empty-cart-message::before {
        color: #fbbf24 !important;
    }
    /* El icono ::before puede solaparse al texto y "comerse" la primera palabra.
       Forzar gap visual usando position absolute + padding-left. */
    body.woocommerce-cart .cart-empty.woocommerce-info,
    body.woocommerce-cart .wc-empty-cart-message {
        position: relative !important;
        padding-left: 56px !important;
    }
    body.woocommerce-cart .cart-empty.woocommerce-info::before,
    body.woocommerce-cart .wc-empty-cart-message::before {
        position: absolute !important;
        left: 20px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    /* ============================================================
       BANK CTA - reforzado con !important para ganar specificity vs Astra
       ============================================================ */
    html body .archi-bank-cta {
        max-width: 920px !important;
        margin: 0 auto 24px !important;
        background: linear-gradient(180deg, #1c1a1f, #15131a) !important;
        background-color: #1c1a1f !important;
        border: 1px solid rgba(244, 114, 182, 0.28) !important;
        border-radius: 20px !important;
        padding: 24px !important;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35) !important;
        color: #f5f1ea !important;
        box-sizing: border-box !important;
    }
    html body .archi-bank-cta__alias-row {
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        padding: 14px 18px !important;
        background: linear-gradient(135deg, rgba(244, 114, 182, 0.22), rgba(251, 146, 60, 0.22)) !important;
        border: 1px solid rgba(244, 114, 182, 0.45) !important;
        border-radius: 14px !important;
        margin-bottom: 18px !important;
        flex-wrap: wrap !important;
    }
    html body .archi-bank-cta__alias-label {
        font-family: 'Manrope', sans-serif !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 0.14em !important;
        text-transform: uppercase !important;
        color: rgba(245, 241, 234, 0.7) !important;
    }
    html body .archi-bank-cta__alias-value {
        font-family: 'JetBrains Mono', 'SF Mono', Menlo, monospace !important;
        font-size: 22px !important;
        font-weight: 700 !important;
        letter-spacing: 0.04em !important;
        color: #ffffff !important;
        flex: 1 1 auto !important;
        word-break: break-all !important;
        min-width: 0 !important;
    }
    html body .archi-bank-cta__amount {
        display: flex !important;
        align-items: baseline !important;
        justify-content: space-between !important;
        gap: 10px 16px !important;
        padding: 14px 18px !important;
        background: rgba(245, 241, 234, 0.05) !important;
        border: 1px solid rgba(245, 241, 234, 0.12) !important;
        border-radius: 14px !important;
        margin-bottom: 18px !important;
        flex-wrap: wrap !important;
    }
    html body .archi-bank-cta__amount-label {
        font-family: 'Manrope', sans-serif !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 0.14em !important;
        text-transform: uppercase !important;
        color: rgba(245, 241, 234, 0.7) !important;
    }
    html body .archi-bank-cta__amount-value {
        font-family: 'Manrope', sans-serif !important;
        font-size: 30px !important;
        font-weight: 800 !important;
        color: #ffffff !important;
        line-height: 1 !important;
    }
    html body .archi-bank-cta__copy {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        padding: 10px 20px !important;
        background: rgba(255, 255, 255, 0.10) !important;
        border: 1px solid rgba(255, 255, 255, 0.22) !important;
        border-radius: 999px !important;
        color: #f5f1ea !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        line-height: 1 !important;
        white-space: nowrap !important;
        flex: 0 0 auto !important;
        flex-shrink: 0 !important;
        min-width: 110px !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        text-overflow: clip !important;
        justify-content: center !important;
    }
    html body .archi-bank-cta__copy span {
        white-space: nowrap !important;
    }
    html body .archi-bank-cta__copy svg {
        flex: 0 0 auto !important;
        width: 16px !important;
        height: 16px !important;
    }
    html body .archi-bank-cta__copy:hover {
        background: rgba(255, 255, 255, 0.18) !important;
        border-color: rgba(255, 255, 255, 0.35) !important;
    }
    html body .archi-bank-cta__copy.archi-bank-cta__copy--ok {
        background: rgba(34, 197, 94, 0.22) !important;
        border-color: rgba(34, 197, 94, 0.55) !important;
        color: #86efac !important;
    }
    html body .archi-bank-cta__copy--inline {
        padding: 6px 10px !important;
        font-size: 12px !important;
        margin-left: 10px !important;
    }
    /* Botón copy redondo, solo ícono - usado para el CBU. Tamaño fijo, centrado, no se corta. */
    html body .archi-bank-cta__copy--icon {
        width: 36px !important;
        height: 36px !important;
        min-width: 36px !important;
        min-height: 36px !important;
        padding: 0 !important;
        border-radius: 999px !important;
        flex: 0 0 36px !important;
        justify-content: center !important;
    }
    html body .archi-bank-cta__copy--icon svg {
        width: 16px !important;
        height: 16px !important;
        flex: 0 0 16px !important;
    }
    /* Card del CBU: label arriba + row (CBU value + botón copy) abajo */
    html body .archi-bank-cta__detail--with-copy {
        gap: 6px !important;
    }
    html body .archi-bank-cta__detail-row {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        justify-content: space-between !important;
        min-width: 0 !important;
    }
    html body .archi-bank-cta__detail-row .archi-bank-cta__detail-value {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        word-break: break-all !important;
    }
    html body .archi-bank-cta__details {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 12px 16px !important;
    }
    html body .archi-bank-cta__detail {
        display: flex !important;
        flex-direction: column !important;
        gap: 4px !important;
        padding: 12px 14px !important;
        background: rgba(245, 241, 234, 0.04) !important;
        border: 1px solid rgba(245, 241, 234, 0.10) !important;
        border-radius: 10px !important;
    }
    html body .archi-bank-cta__detail--wide {
        grid-column: 1 / -1 !important;
    }

    /* QR + cartel "ACREDITÁ TU ABONO": dejar imagen completa, sin crop.
       Tamaño 360px para que el cartel sea legible. */
    html body.woocommerce-checkout .woocommerce-checkout-review-order > img,
    html body.woocommerce-checkout .woocommerce-checkout-review-order img[src*="QR-ARCHICOOP"],
    html body .archi-payment-layout__right img {
        display: block !important;
        max-width: 360px !important;
        width: 100% !important;
        height: auto !important;
        aspect-ratio: auto !important;
        object-fit: unset !important;
        margin: 16px auto !important;
        border-radius: 16px !important;
        background: transparent !important;
    }

    /* Layout 2-col en desktop: a la IZQUIERDA "¿Cómo abona?" + descripción, a la DERECHA la imagen del QR.
       El JS envuelve los elementos en .archi-payment-layout__{left,right}. */
    html body .archi-payment-layout {
        display: block;
        margin: 24px 0;
    }
    html body .archi-payment-layout > h3,
    html body .archi-payment-layout > p {
        /* fallback si JS no envolvió */
    }
    @media (min-width: 768px) {
        html body .archi-payment-layout {
            display: flex !important;
            gap: 32px !important;
            align-items: center !important;
            justify-content: space-between !important;
            margin: 28px 0 !important;
        }
        html body .archi-payment-layout__left {
            flex: 1 1 0 !important;
            min-width: 0 !important;
        }
        html body .archi-payment-layout__right {
            flex: 0 0 auto !important;
            max-width: 360px !important;
            width: 360px !important;
        }
        html body .archi-payment-layout__right img {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        /* En desktop, h3 "¿Cómo abona?" queda alineado al QR y ya no necesita estar centrado */
        html body .archi-payment-layout__left h3 {
            margin-top: 0 !important;
        }
        html body .archi-payment-layout__left p {
            margin-bottom: 0 !important;
        }
    }
    html body .archi-bank-cta__detail-label {
        font-family: 'Manrope', sans-serif !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        letter-spacing: 0.14em !important;
        text-transform: uppercase !important;
        color: rgba(245, 241, 234, 0.55) !important;
    }
    html body .archi-bank-cta__detail-value {
        font-family: 'Manrope', sans-serif !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        color: #f5f1ea !important;
        display: inline-flex !important;
        align-items: center !important;
        flex-wrap: wrap !important;
    }
    html body .archi-bank-cta__detail-value.archi-mono {
        font-family: 'JetBrains Mono', 'SF Mono', Menlo, monospace !important;
        letter-spacing: 0.02em !important;
        word-break: break-all !important;
    }
    html body .archi-bank-cta__hint {
        margin: 16px 0 0 !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 13px !important;
        color: rgba(245, 241, 234, 0.7) !important;
        text-align: center !important;
        line-height: 1.5 !important;
    }

    /* Paso 3: las 4 fichas + el QR lado a lado, ocupando el ancho de la tarjeta. */
    html body .archi-bank-cta__main {
        display: flex !important;
        gap: 16px !important;
        align-items: stretch !important;
        margin: 14px 0 0 !important;
    }
    html body .archi-bank-cta__main .archi-bank-cta__details {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }
    html body .archi-bank-cta__qr {
        flex: 0 0 200px !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        margin: 0 !important;
        padding: 14px !important;
        background: rgba(245, 241, 234, 0.04) !important;
        border: 1px solid rgba(245, 241, 234, 0.10) !important;
        border-radius: 10px !important;
    }
    html body .archi-bank-cta__qr img {
        display: block !important;
        width: 100% !important;
        max-width: 150px !important;
        height: auto !important;
        border-radius: 8px !important;
    }
    html body .archi-bank-cta__qr-cap {
        font-family: 'Manrope', sans-serif !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 0.12em !important;
        text-transform: uppercase !important;
        color: rgba(245, 241, 234, 0.55) !important;
    }

    /* El bloque del método de pago (label "QR o Pago con Transferencia" + la
       descripción con el QR viejo y los banners amarillos) es redundante: la
       tarjeta de arriba ya tiene alias, CBU y el QR. Lo ocultamos. El radio del
       método sigue en el DOM y marcado, así que la orden se procesa igual. */
    html body.woocommerce-checkout ul.wc_payment_methods,
    html body.woocommerce-checkout .archi-payment-layout {
        display: none !important;
    }
    @media (max-width: 640px) {
        html body .archi-bank-cta { padding: 18px !important; }
        html body .archi-bank-cta__alias-value { font-size: 18px !important; }
        html body .archi-bank-cta__details { grid-template-columns: 1fr !important; }
        html body .archi-bank-cta__main { flex-direction: column !important; }
        html body .archi-bank-cta__qr { flex: none !important; }
    }

    /* "Tu pedido" en step 3 - OCULTAR completo (no solo desnudar bordes).
       Pedido del usuario: "podemos sacar esto?" */
    html body.woocommerce-checkout form.checkout.archi-funnel-step-3 #order_review_heading,
    html body.woocommerce-checkout form.checkout.archi-funnel-step-3 h3#order_review_heading {
        display: none !important;
    }

    /* Pill destacando ARCHICOOP en el texto "Escaneá el código QR o transfiere al alias: ARCHICOOP..."
       del método de pago. La pill la inyecta JS envolviendo el match.
       Es un <button> clickeable que copia "ARCHICOOP" al clipboard. */
    html body .archi-alias-pill {
        display: inline-block !important;
        padding: 3px 12px !important;
        margin: 0 2px !important;
        background: linear-gradient(135deg, rgba(244, 114, 182, 0.28), rgba(251, 146, 60, 0.28)) !important;
        border: 1px solid rgba(244, 114, 182, 0.5) !important;
        border-radius: 999px !important;
        font-family: 'JetBrains Mono', 'SF Mono', Menlo, monospace !important;
        font-weight: 700 !important;
        font-size: inherit !important;
        letter-spacing: 0.04em !important;
        color: #fff !important;
        cursor: pointer !important;
        line-height: 1.4 !important;
        transition: background 180ms ease, border-color 180ms ease, transform 100ms ease !important;
    }
    html body .archi-alias-pill:hover {
        background: linear-gradient(135deg, rgba(244, 114, 182, 0.45), rgba(251, 146, 60, 0.45)) !important;
        border-color: rgba(244, 114, 182, 0.8) !important;
    }
    html body .archi-alias-pill:active {
        transform: scale(0.96) !important;
    }
    html body .archi-alias-pill.archi-alias-pill--ok {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.4), rgba(34, 197, 94, 0.5)) !important;
        border-color: rgba(34, 197, 94, 0.8) !important;
        color: #fff !important;
    }

    /* THANK-YOU: tabla "Detalles del pedido" más compacta. Antes era enorme con padding 16px+24px,
       font 18px. Ajustamos a algo más legible y proporcionado al resto de los bloques. */
    html body.woocommerce-order-received section.woocommerce-order-details {
        max-width: 720px !important;
        margin: 24px auto !important;
    }
    html body.woocommerce-order-received .woocommerce-order-details__title {
        font-size: 20px !important;
        margin-bottom: 12px !important;
    }
    html body.woocommerce-order-received table.woocommerce-table--order-details,
    html body.woocommerce-order-received table.shop_table.order_details {
        font-size: 14px !important;
        border-collapse: collapse !important;
    }
    html body.woocommerce-order-received table.woocommerce-table--order-details th,
    html body.woocommerce-order-received table.woocommerce-table--order-details td,
    html body.woocommerce-order-received table.shop_table.order_details th,
    html body.woocommerce-order-received table.shop_table.order_details td {
        padding: 10px 14px !important;
        font-size: 14px !important;
        line-height: 1.4 !important;
    }
    /* "Volver a pagar" en la última fila: chico también */
    html body.woocommerce-order-received table.woocommerce-table--order-details a.button,
    html body.woocommerce-order-received table.shop_table.order_details a.button {
        font-size: 13px !important;
        padding: 8px 16px !important;
    }

    /* THANK-YOU: el form de PeproDev se renderea adentro de section.woocommerce-order-details
       pero queremos verlo arriba (en .archi-thankyou__upload). Para evitar FOUC ocultamos el
       order-details hasta que el JS lo mueva. */
    html body.woocommerce-order-received .archi-thankyou .woocommerce-order-details:not(.archi-ready) {
        visibility: hidden !important;
    }
    html body.woocommerce-order-received .archi-thankyou__upload[data-archi-upload-target]:empty {
        min-height: 80px;
    }

    /* THANK-YOU: control custom de archivo (el input nativo "Choose file" pasa desapercibido).
       JS reemplaza el input con un wrapper .archi-file-control que tiene un botón visible
       "Elegir archivo" + un texto con el nombre del archivo elegido. */
    html body.woocommerce-order-received .archi-file-control {
        display: inline-flex !important;
        align-items: center !important;
        gap: 16px !important;
        margin: 8px 0 !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    html body.woocommerce-order-received .archi-file-control__btn {
        display: inline-block !important;
        padding: 12px 26px !important;
        background: var(--archi-gradient) !important;
        color: #ffffff !important;
        border: 0 !important;
        border-radius: 999px !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        letter-spacing: 0.02em !important;
        cursor: pointer !important;
        transition: transform 100ms ease, box-shadow 180ms ease !important;
        box-shadow: 0 4px 18px rgba(255, 61, 127, 0.35) !important;
        line-height: 1 !important;
    }
    html body.woocommerce-order-received .archi-file-control__btn:hover {
        box-shadow: 0 6px 22px rgba(255, 61, 127, 0.5) !important;
        transform: translateY(-1px) !important;
    }
    html body.woocommerce-order-received .archi-file-control__btn:active {
        transform: translateY(0) scale(0.98) !important;
    }
    html body.woocommerce-order-received .archi-file-control__name {
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        color: rgba(245, 241, 234, 0.55) !important;
    }
    html body.woocommerce-order-received .archi-file-control__name--selected {
        color: rgba(245, 241, 234, 0.95) !important;
        font-weight: 600 !important;
    }

    /* FORM DE UPLOAD: una sola card. El wrapper interno .peprodev_woocommerce_receipt_uploader
       tenía su propio background + border, generando un efecto "card dentro de card" feo
       (sobre todo en mobile). Lo hacemos transparente: la única card es .archi-thankyou__upload. */
    html body.woocommerce-order-received .archi-thankyou__upload .peprodev_woocommerce_receipt_uploader {
        background: transparent !important;
        background-image: none !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    /* Centrar todo el contenido del form de upload (título, botones, label) */
    html body.woocommerce-order-received .archi-thankyou__upload {
        text-align: center !important;
    }
    html body.woocommerce-order-received .archi-thankyou__upload .receipt-img-upload,
    html body.woocommerce-order-received .archi-thankyou__upload form#uploadreceiptfileimage,
    html body.woocommerce-order-received .archi-thankyou__upload form#uploadreceiptfileimage > div {
        text-align: center !important;
        display: block !important;
        width: 100% !important;
    }
    /* Form de subir comprobante: un solo botón a la vez (feedback de Joaco, 2026-05-20).
       Sin archivo: se ve "Elegir archivo", oculto "Enviar comprobante".
       Con archivo (.archi-upload-has-file, lo togglea el JS): aparece "Enviar
       comprobante" y "Elegir archivo" pasa a un link chico "Cambiar archivo". */
    html body.woocommerce-order-received .archi-thankyou__upload button.start-upload {
        display: none !important;
        margin: 6px auto 0 !important;
        float: none !important;
    }
    html body.woocommerce-order-received .archi-thankyou__upload.archi-upload-has-file button.start-upload {
        display: block !important;
    }
    /* El h2 "Subí tu comprobante" se veía como un botón (pill con gradiente).
       Lo dejamos como título plano de sección. */
    html body.woocommerce-order-received .archi-thankyou__upload h2.upload_receipt,
    html body.woocommerce-order-received .archi-thankyou__upload h2.woocommerce-order-details__title.upload_receipt {
        background: transparent !important;
        background-image: none !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 0 20px !important;
        color: #f5f1ea !important;
        font-family: 'AndralisND', serif !important;
        font-size: 22px !important;
        font-weight: 600 !important;
        text-align: center !important;
    }
    /* Con archivo elegido, "Elegir archivo" deja de ser botón y pasa a link discreto. */
    html body.woocommerce-order-received .archi-thankyou__upload.archi-upload-has-file .archi-file-control__btn {
        background: transparent !important;
        background-image: none !important;
        box-shadow: none !important;
        padding: 2px 6px !important;
        font-size: 13px !important;
        color: rgba(245, 241, 234, 0.6) !important;
        text-decoration: underline !important;
    }
    /* Ocultar el input nativo sin display:none (eso puede romper el click programático
       en algunos browsers). Usamos clip-path para sacarlo del flow visual. */
    html body.woocommerce-order-received input.archi-file-control__input-hidden,
    html body.woocommerce-order-received input[type="file"]#receipt-file.archi-file-control__input-hidden {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0,0,0,0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
    }
    /* Label .archi-file-control__btn: aparte del style heredado, asegurar cursor pointer */
    html body.woocommerce-order-received label.archi-file-control__btn {
        cursor: pointer !important;
        user-select: none !important;
    }

    /* Fallback (cuando el JS no se ejecuta): igual estilamos el input nativo como botón */
    html body.woocommerce-order-received input[type="file"]#receipt-file,
    html body.woocommerce-order-received .archi-thankyou__upload input[type="file"] {
        width: auto !important;
        padding: 0 !important;
        background: transparent !important;
        border: 0 !important;
        color: rgba(245, 241, 234, 0.7) !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        cursor: pointer !important;
    }
    html body.woocommerce-order-received input[type="file"]#receipt-file::file-selector-button,
    html body.woocommerce-order-received .archi-thankyou__upload input[type="file"]::file-selector-button {
        display: inline-block !important;
        padding: 12px 24px !important;
        margin-right: 14px !important;
        background: var(--archi-gradient) !important;
        color: #ffffff !important;
        border: 0 !important;
        border-radius: 999px !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        letter-spacing: 0.02em !important;
        cursor: pointer !important;
        transition: transform 100ms ease, box-shadow 180ms ease !important;
        box-shadow: 0 4px 18px rgba(255, 61, 127, 0.35) !important;
    }
    html body.woocommerce-order-received input[type="file"]#receipt-file::file-selector-button:hover,
    html body.woocommerce-order-received .archi-thankyou__upload input[type="file"]::file-selector-button:hover {
        box-shadow: 0 6px 22px rgba(255, 61, 127, 0.5) !important;
        transform: translateY(-1px) !important;
    }
    /* Mismo style para WebKit (Safari viejo) */
    html body.woocommerce-order-received input[type="file"]#receipt-file::-webkit-file-upload-button,
    html body.woocommerce-order-received .archi-thankyou__upload input[type="file"]::-webkit-file-upload-button {
        display: inline-block !important;
        padding: 12px 24px !important;
        margin-right: 14px !important;
        background: var(--archi-gradient) !important;
        color: #ffffff !important;
        border: 0 !important;
        border-radius: 999px !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
    }

    /* THANK-YOU: ocultar bloques reiterativos del gateway.
       El "Transfiere al alias: ARCHICOOP y subí tu comprobante" + "Nuestros detalles bancarios"
       ya los vieron en step 3 (Pagar) con el bank CTA destacado. Mostrarlos otra vez en thank-you
       solo agrega ruido visual cuando lo único que queda es subir el comprobante. */
    html body.woocommerce-order-received .archi-thankyou__upload > p:first-child,
    html body.woocommerce-order-received .archi-thankyou__upload section.woocommerce-bacs-bank-details,
    html body.woocommerce-order-received section.woocommerce-bacs-bank-details {
        display: none !important;
    }

    /* Estado "Listo" (verde): ocultar el widget de PeproDev cuando ya quedó dentro
       de order-details (= comprobante ya subido). En el estado amarillo el JS lo
       mueve a .archi-thankyou__upload, así que ahí NO se oculta. El comprobante ya
       está cargado, repetir "Subí tu comprobante" + "Fecha de carga" sobra. */
    html body.woocommerce-order-received .woocommerce-order-details .peprodev_woocommerce_receipt_uploader {
        display: none !important;
    }
    /* Ocultar la fila "Acciones" / botón "Ir a pagar" del detalle del pedido: en
       este funnel se paga por transferencia + comprobante, el cliente nunca va al
       order-pay online. Ocultamos la fila entera (quedaba vacía). */
    html body.woocommerce-order-received .woocommerce-order-details a.button.pay,
    html body.woocommerce-order-received a.button.pay.order-actions-button {
        display: none !important;
    }
    html body.woocommerce-order-received .woocommerce-order-details tr:has(a.button.pay) {
        display: none !important;
    }

    /* Paso 4 (Comprobante / amarillo): que se vea SOLO el form de subir comprobante.
       El detalle del pedido (tabla + dirección de facturación) va recién en el paso 5
       (Listo). Detectamos el paso 4 por la presencia de .archi-thankyou__upload-wrap.
       PeproDev igual se ve: el JS lo saca de order-details y lo mueve al upload-wrap. */
    html body.woocommerce-order-received .archi-thankyou:has(.archi-thankyou__upload-wrap) .woocommerce-order-details,
    html body.woocommerce-order-received .archi-thankyou:has(.archi-thankyou__upload-wrap) .woocommerce-customer-details {
        display: none !important;
    }

    /* 2 botones (Ver agenda + Cómo llegar) en /gracias-comprobante/, abajo del GIF de gatitos. */
    html body .archi-gracias-cta-buttons {
        display: flex !important;
        gap: 16px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
        max-width: 720px !important;
        margin: 32px auto !important;
        padding: 0 16px !important;
        box-sizing: border-box !important;
    }
    /* Botones sobre la tarjeta BLANCA de /gracias-comprobante/: fondo oscuro sólido + texto
       blanco para que contrasten y se lean (antes eran dark-on-dark, invisibles sobre blanco). */
    html body .archi-gracias-cta-buttons .archi-btn {
        flex: 1 1 240px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 16px 28px !important;
        background: #1c1a1f !important;
        background-image: none !important;
        border: 1px solid #1c1a1f !important;
        border-radius: 999px !important;
        color: #ffffff !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        text-decoration: none !important;
        text-align: center !important;
        transition: background 180ms ease, transform 100ms ease !important;
    }
    html body .archi-gracias-cta-buttons .archi-btn:hover {
        background: #2c2932 !important;
        border-color: #2c2932 !important;
        transform: translateY(-1px) !important;
    }
    @media (max-width: 600px) {
        html body .archi-gracias-cta-buttons {
            flex-direction: column !important;
        }
        html body .archi-gracias-cta-buttons .archi-btn {
            width: 100% !important;
            flex: none !important;
        }
    }

    /* GIF de gatito self-hosted (reemplaza el embed de giphy en /gracias-comprobante/). */
    html body .archi-gracias-gif {
        display: block !important;
        margin: 0 auto !important;
        max-width: 100% !important;
        height: auto !important;
        border-radius: 14px !important;
    }

    /* "Mientras tanto" standalone (fuera de la thank-you, en /gracias-comprobante/) - viejo.
       Conservado por si alguna otra página lo necesita. */
    html body .archi-thankyou__more--standalone {
        max-width: 720px !important;
        margin: 32px auto !important;
        background: linear-gradient(180deg, #1c1a1f, #15131a) !important;
        border: 1px solid rgba(245, 241, 234, 0.14) !important;
        border-radius: 20px !important;
        padding: 28px !important;
        color: #f5f1ea !important;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35) !important;
        text-align: center !important;
    }
    html body .archi-thankyou__more--standalone .archi-thankyou__more-title {
        color: #f5f1ea !important;
        font-family: 'AndralisND', serif !important;
        font-size: 22px !important;
        font-weight: 600 !important;
        margin: 0 0 10px !important;
    }
    html body .archi-thankyou__more--standalone .archi-thankyou__more-sub {
        color: rgba(245, 241, 234, 0.7) !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        margin: 0 0 20px !important;
    }
    html body .archi-thankyou__more--standalone .archi-thankyou__more-actions {
        display: flex !important;
        gap: 14px !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    html body .archi-thankyou__more--standalone .archi-thankyou__more-actions .archi-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 12px 24px !important;
        background: rgba(255, 255, 255, 0.06) !important;
        border: 1px solid rgba(245, 241, 234, 0.18) !important;
        border-radius: 999px !important;
        color: #f5f1ea !important;
        font-family: 'Manrope', sans-serif !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        text-decoration: none !important;
        min-width: 200px !important;
        transition: background 180ms ease, border-color 180ms ease !important;
    }
    html body .archi-thankyou__more--standalone .archi-thankyou__more-actions .archi-btn:hover {
        background: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(245, 241, 234, 0.32) !important;
    }

    /* CHECKOUT: ocultar el bloque de login/logout del plugin (.xs-login con botón azul "Logout").
       En el flujo de compra de tickets ese botón es ruido visual - el cliente logueado ya tiene
       sus datos prellenados y no necesita cerrar sesión desde acá. */
    html body.woocommerce-checkout .xs-login,
    html body.woocommerce-checkout .wslu-logout-button,
    html body.woocommerce-checkout .xs-login--style-1 {
        display: none !important;
    }

    /* Checkbox de "He leído los términos y condiciones" - inline a la izquierda del texto */
    html body.woocommerce-checkout .wc-terms-and-conditions,
    html body.woocommerce-checkout .form-row.terms,
    html body.woocommerce-checkout p.form-row.terms {
        text-align: left !important;
    }
    html body.woocommerce-checkout label.woocommerce-form__label-for-checkbox.checkbox {
        display: flex !important;
        align-items: flex-start !important;
        gap: 10px !important;
        text-align: left !important;
        line-height: 1.5 !important;
        cursor: pointer !important;
        padding: 4px 0 !important;
    }
    html body.woocommerce-checkout label.woocommerce-form__label-for-checkbox.checkbox input[type="checkbox"] {
        flex: 0 0 auto !important;
        margin: 2px 0 0 0 !important;
        width: 18px !important;
        height: 18px !important;
        accent-color: #f472b6 !important;
        cursor: pointer !important;
    }
    html body.woocommerce-checkout label.woocommerce-form__label-for-checkbox.checkbox .woocommerce-terms-and-conditions-checkbox-text {
        flex: 1 1 auto !important;
        display: inline !important;
    }
    /* El asterisco "*" de campo obligatorio queda suelto cuando hacemos flex el label.
       Lo ocultamos: el "He leído ... términos y condiciones" ya implica que es obligatorio,
       y el sistema valida igual. Sacar el * (que además quedaba flotando) limpia visualmente. */
    html body.woocommerce-checkout label.woocommerce-form__label-for-checkbox.checkbox abbr.required,
    html body.woocommerce-checkout .form-row.terms abbr.required {
        display: none !important;
    }

    /* Cart totals - líneas blancas: matar TODOS los borders de las rows del totales del carrito (Royal + WC) */
    html body.woocommerce-cart table.shop_table.shop_table_responsive,
    html body.woocommerce-cart .archi-totals__table,
    html body.woocommerce-cart .wpr-cart-totals table,
    html body.woocommerce-cart .cart_totals table {
        border: 0 !important;
        border-collapse: collapse !important;
    }
    html body.woocommerce-cart table.shop_table.shop_table_responsive tr,
    html body.woocommerce-cart table.shop_table.shop_table_responsive th,
    html body.woocommerce-cart table.shop_table.shop_table_responsive td,
    html body.woocommerce-cart .archi-totals__table tr,
    html body.woocommerce-cart .archi-totals__table th,
    html body.woocommerce-cart .archi-totals__table td,
    html body.woocommerce-cart .wpr-cart-totals tr,
    html body.woocommerce-cart .wpr-cart-totals th,
    html body.woocommerce-cart .wpr-cart-totals td,
    html body.woocommerce-cart .cart_totals tr,
    html body.woocommerce-cart .cart_totals th,
    html body.woocommerce-cart .cart_totals td {
        border: 0 !important;
        border-top: 0 !important;
        border-right: 0 !important;
        border-bottom: 0 !important;
        border-left: 0 !important;
        border-color: transparent !important;
        border-style: none !important;
        box-shadow: none !important;
        outline: none !important;
    }
    html body.woocommerce-cart .archi-totals,
    html body.woocommerce-cart .wpr-cart-totals,
    html body.woocommerce-cart .cart_totals {
        border-left: 0 !important;
    }

    /* CHECKOUT - centrado en desktop + form MÁS ANCHO (920px en desktop) */
    @media (min-width: 768px) {
        body.woocommerce-checkout .ast-container,
        body.woocommerce-checkout #primary,
        body.woocommerce-checkout #content,
        body.woocommerce-checkout .site-content,
        body.woocommerce-checkout main,
        body.woocommerce-checkout .entry-content {
            max-width: 100% !important;
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            text-align: center !important;
        }
        body.woocommerce-checkout .woocommerce {
            max-width: 100% !important;
            width: 100% !important;
            text-align: center !important;
        }
        /* Form más ancho: 920px en desktop (antes 720) */
        body.woocommerce-checkout form.checkout,
        body.woocommerce-checkout form.woocommerce-checkout {
            max-width: 920px !important;
            width: 100% !important;
            margin: 0 auto !important;
            float: none !important;
            display: block !important;
            text-align: left !important;
            padding: 0 32px !important;
        }
        body.woocommerce-checkout #customer_details,
        body.woocommerce-checkout .col2-set,
        body.woocommerce-checkout .woocommerce-billing-fields,
        body.woocommerce-checkout .woocommerce-additional-fields,
        body.woocommerce-checkout #order_review,
        body.woocommerce-checkout #order_review_heading {
            max-width: 920px !important;
            width: 100% !important;
            margin: 0 auto !important;
            float: none !important;
            clear: both !important;
            text-align: left !important;
        }
        body.woocommerce-checkout .woocommerce-form-coupon-toggle,
        body.woocommerce-checkout .woocommerce-form-coupon,
        body.woocommerce-checkout .woocommerce > .woocommerce-info {
            max-width: 920px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            text-align: left !important;
        }
        /* Heading principal del checkout (eyebrow + título) también centrado */
        body.woocommerce-checkout .archi-page-heading {
            max-width: 920px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            text-align: left !important;
        }
        /* Nombre y Apellidos en 2 columnas para aprovechar ancho */
        body.woocommerce-checkout .woocommerce-billing-fields p#billing_first_name_field,
        body.woocommerce-checkout .woocommerce-billing-fields p#billing_last_name_field {
            width: calc(50% - 8px) !important;
            display: inline-block !important;
            vertical-align: top !important;
        }
        body.woocommerce-checkout .woocommerce-billing-fields p#billing_first_name_field {
            margin-right: 16px !important;
        }
    }

    /* En pantallas muy anchas (1200+) el form puede ser hasta 1024px */
    @media (min-width: 1200px) {
        body.woocommerce-checkout form.checkout,
        body.woocommerce-checkout form.woocommerce-checkout,
        body.woocommerce-checkout #customer_details,
        body.woocommerce-checkout .archi-page-heading,
        body.woocommerce-checkout .woocommerce-form-coupon-toggle {
            max-width: 1024px !important;
        }
    }

    /* CART TOTALS - eliminar TODAS las lineas blancas (borders + pseudos + outlines) */
    body.woocommerce-cart .cart_totals,
    body.woocommerce-cart .cart_totals *,
    body.woocommerce-cart .cart_totals *::before,
    body.woocommerce-cart .cart_totals *::after,
    body.woocommerce-cart .cart-collaterals .cart_totals,
    body.woocommerce-cart .cart-collaterals .cart_totals *,
    body.woocommerce-cart .cart_totals table,
    body.woocommerce-cart .cart_totals table tbody,
    body.woocommerce-cart .cart_totals table tr,
    body.woocommerce-cart .cart_totals table th,
    body.woocommerce-cart .cart_totals table td,
    body.woocommerce-cart .cart_totals .wpr-shop-table,
    body.woocommerce-cart .cart_totals .wpr-shop-table tr,
    body.woocommerce-cart .cart_totals .wpr-shop-table th,
    body.woocommerce-cart .cart_totals .wpr-shop-table td,
    html body.woocommerce-cart .cart_totals table tr,
    html body.woocommerce-cart .cart_totals table th,
    html body.woocommerce-cart .cart_totals table td {
        border: 0 none transparent !important;
        border-top: 0 none transparent !important;
        border-right: 0 none transparent !important;
        border-bottom: 0 none transparent !important;
        border-left: 0 none transparent !important;
        border-width: 0 !important;
        border-style: none !important;
        border-color: transparent !important;
        outline: 0 none transparent !important;
        outline-width: 0 !important;
        outline-color: transparent !important;
        outline-style: none !important;
        box-shadow: none !important;
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
    }
    /* Eliminar pseudo elementos que puedan ser las lineas */
    body.woocommerce-cart .cart_totals table tr::before,
    body.woocommerce-cart .cart_totals table tr::after,
    body.woocommerce-cart .cart_totals table td::before,
    body.woocommerce-cart .cart_totals table td::after,
    body.woocommerce-cart .cart_totals table th::before,
    body.woocommerce-cart .cart_totals table th::after {
        content: none !important;
        display: none !important;
        background: none !important;
        border: none !important;
    }
    /* Borrar border-collapse + border-spacing que pueden generar lineas */
    body.woocommerce-cart .cart_totals table {
        border-collapse: collapse !important;
        border-spacing: 0 !important;
    }
    /* Restaurar SOLO el border externo de la card (no de las filas internas) */
    body.woocommerce-cart .cart_totals {
        border: 1px solid rgba(245, 241, 234, 0.08) !important;
        border-radius: 20px !important;
        padding: 24px !important;
        background: linear-gradient(180deg, #161616, #1f1f1f) !important;
    }
    /* Espaciado entre filas de la tabla sin lineas */
    body.woocommerce-cart .cart_totals table tr {
        display: table-row !important;
    }
    body.woocommerce-cart .cart_totals table td,
    body.woocommerce-cart .cart_totals table th {
        padding: 10px 0 !important;
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
        color: #d6cfc1 !important;
    }
    /* Separador sutil arriba del Total (esto SÍ queremos como única línea) */
    body.woocommerce-cart .cart_totals table tr.order-total td,
    body.woocommerce-cart .cart_totals table tr.order-total th {
        padding-top: 16px !important;
        border-top: 1px solid rgba(245, 241, 234, 0.10) !important;
        border-top-style: solid !important;
        border-top-color: rgba(245, 241, 234, 0.10) !important;
        border-top-width: 1px !important;
        color: #f5f1ea !important;
    }
    </style>
    <?php
}, 9999);

// =====================================================================
// 2. Tipografías (Andralis ND local + Manrope de Google) - solo en flujo WC
// =====================================================================
add_action('wp_head', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_woocommerce')) return;
    if (!(is_cart() || is_checkout() || is_account_page() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) return;
    $fonts_base = get_stylesheet_directory_uri() . '/fonts';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style id="archi-andralis-fontface">
    @font-face {
        font-family: 'AndralisND';
        src: url('<?php echo esc_url($fonts_base); ?>/AndralisND-Regular.otf') format('opentype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }
    @font-face {
        font-family: 'AndralisND';
        src: url('<?php echo esc_url($fonts_base); ?>/AndralisND-Italic.otf') format('opentype');
        font-weight: 400;
        font-style: italic;
        font-display: swap;
    }
    @font-face {
        font-family: 'AndralisND';
        src: url('<?php echo esc_url($fonts_base); ?>/AndralisND-Bold.otf') format('opentype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }
    </style>
    <?php
}, 4);

// =====================================================================
// 3. Helper: render del stepper visual
// =====================================================================
if (!function_exists('archi_funnel_render_stepper')) {
    function archi_funnel_render_stepper($current_step = 1) {
        // Look "pliegos del fascículo" (rebrand 2026): numeración romana,
        // labels mono uppercase, figmark del pliego actual.
        $labels = array(
            1 => 'Ticket',
            2 => 'Datos',
            3 => 'Pagar',
            4 => 'Comprobante',
            5 => 'Listo',
        );
        $romanos = array(1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V');
        $figmarks = array(
            1 => 'revisá lo que vas a llevarte',
            2 => 'para mandarte el ticket',
            3 => 'escaneá el QR desde tu app',
            4 => 'sin esto no se genera el ticket',
            5 => 'te avisamos cuando esté confirmado',
        );
        $current_step = max(1, min(5, (int) $current_step));
        $current_label = $labels[$current_step];

        echo '<nav class="archi-funnel-stepper" aria-label="Paso ' . (int) $current_step . ' de 5">';
        echo '<ol class="archi-funnel-stepper__list">';
        for ($n = 1; $n <= 5; $n++) {
            $state = ($n < $current_step) ? 'completed' : (($n === $current_step) ? 'current' : 'pending');
            $line_class = 'archi-funnel-stepper__line' . ($n < $current_step ? ' completed' : '');
            $label = $labels[$n];

            echo '<li class="archi-funnel-stepper__item">';
            echo '<span class="archi-funnel-stepper__dot ' . esc_attr($state) . '"' . ($state === 'current' ? ' aria-current="step"' : '') . '>';
            if ($state === 'completed') {
                echo '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            } else {
                echo esc_html($romanos[$n]);
            }
            echo '</span>';
            echo '<span class="archi-funnel-stepper__label">' . esc_html($label) . '</span>';
            echo '</li>';
            if ($n < 5) {
                echo '<li class="' . esc_attr($line_class) . '" aria-hidden="true"></li>';
            }
        }
        echo '</ol>';
        echo '<p class="archi-funnel-stepper__figmark"><b>Fig. ' . esc_html($romanos[$current_step]) . '</b> ' . esc_html($figmarks[$current_step]) . '</p>';
        echo '<p class="archi-funnel-stepper__mobile">Paso ' . (int) $current_step . ' de 5 — ' . esc_html($current_label) . '</p>';
        echo '</nav>';
    }
}

// =====================================================================
// 4. Hooks de display del stepper en cada página del flujo
// =====================================================================
add_action('woocommerce_before_cart', function () {
    if (!archi_funnel_should_apply()) return;
    archi_funnel_render_stepper(1);
    echo '<div class="archi-page-heading">';
    echo '<p class="archi-eyebrow">Paso 1 · Tu ticket</p>';
    echo '<h1 class="archi-page-title">Revisá lo que llevás</h1>';
    echo '<p class="archi-page-sub">Confirmá el evento, la cantidad y la variante. Después seguimos con tus datos.</p>';
    echo '</div>';
}, 5);

add_action('woocommerce_before_checkout_form', function () {
    if (!archi_funnel_should_apply()) return;
    archi_funnel_render_stepper(2);
    // Heading STEP 2 (Datos) - visible por default, oculto cuando se va a step 3
    echo '<div class="archi-page-heading archi-page-heading--step-2">';
    echo '<p class="archi-eyebrow">Tus datos</p>';
    echo '<h1 class="archi-page-title">¿A dónde te mandamos el ticket?</h1>';
    echo '<p class="archi-page-sub">Lo recibís por mail cuando confirmemos el pago.</p>';
    echo '</div>';
    // Heading STEP 3 (Pagar) - oculto por default, visible en step 3
    echo '<div class="archi-page-heading archi-page-heading--step-3" style="display:none">';
    echo '<p class="archi-eyebrow">Paso 3 · Pagá</p>';
    echo '<h1 class="archi-page-title">Transferí y subí tu comprobante</h1>';
    echo '<p class="archi-page-sub">Hacé la transferencia con los datos de abajo. Después te llevamos a subir el comprobante para que generemos el ticket.</p>';
    echo '</div>';
    // Bloque bancario destacado (CTA-like) - visible SOLO en step 3
    echo '<div class="archi-bank-cta archi-bank-cta--step-3" style="display:none" aria-label="Datos para transferir">';
    echo '  <div class="archi-bank-cta__alias-row">';
    echo '    <span class="archi-bank-cta__alias-label">Alias</span>';
    echo '    <span class="archi-bank-cta__alias-value archi-mono" id="archi-bank-alias">ARCHICOOP</span>';
    echo '    <button type="button" class="archi-bank-cta__copy" data-copy-target="archi-bank-alias" aria-label="Copiar alias">';
    echo '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2.5" ry="2.5" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>';
    echo '      <span>Copiar</span>';
    echo '    </button>';
    echo '  </div>';
    // Monto total a transferir, destacado arriba (feedback Alex: que se vea en un solo vistazo)
    if (WC()->cart) {
        echo '  <div class="archi-bank-cta__amount">';
        echo '    <span class="archi-bank-cta__amount-label">Monto a transferir</span>';
        echo '    <span class="archi-bank-cta__amount-value">' . WC()->cart->get_total() . '</span>';
        echo '  </div>';
    }
    echo '  <div class="archi-bank-cta__main">';
    echo '    <div class="archi-bank-cta__details">';
    echo '      <div class="archi-bank-cta__detail"><span class="archi-bank-cta__detail-label">Banco</span><span class="archi-bank-cta__detail-value">Credicoop</span></div>';
    echo '      <div class="archi-bank-cta__detail"><span class="archi-bank-cta__detail-label">Nº de cuenta</span><span class="archi-bank-cta__detail-value archi-mono">191-014-030007/3</span></div>';
    echo '      <div class="archi-bank-cta__detail"><span class="archi-bank-cta__detail-label">Titular</span><span class="archi-bank-cta__detail-value">Cooperativa Archicoop Ltda.</span></div>';
    echo '      <div class="archi-bank-cta__detail archi-bank-cta__detail--with-copy">';
    echo '        <span class="archi-bank-cta__detail-label">CBU</span>';
    echo '        <div class="archi-bank-cta__detail-row">';
    echo '          <span class="archi-bank-cta__detail-value archi-mono" id="archi-bank-cbu">1910014855001403000732</span>';
    echo '          <button type="button" class="archi-bank-cta__copy archi-bank-cta__copy--icon" data-copy-target="archi-bank-cbu" aria-label="Copiar CBU" title="Copiar CBU">';
    echo '            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2.5" ry="2.5" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>';
    echo '          </button>';
    echo '        </div>';
    echo '      </div>';
    echo '    </div>';
    echo '    <div class="archi-bank-cta__qr">';
    echo '      <img src="https://www.archibrazo.org/wp-content/uploads/2026/05/QR-ARCHICOOP.png" alt="Código QR para transferir a Archicoop" width="220" height="220" loading="lazy" decoding="async">';
    echo '      <span class="archi-bank-cta__qr-cap">Escaneá el QR</span>';
    echo '    </div>';
    echo '  </div>';
    echo '  <div class="archi-step3-warning" role="alert">';
    echo '    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
    echo '    <p><strong>Falta un paso.</strong> Para generar tu ticket necesitamos que nos envíes el comprobante en la siguiente pantalla.</p>';
    echo '  </div>';
    echo '</div>';
}, 5);

add_action('woocommerce_before_pay_form', function () {
    if (!archi_funnel_should_apply()) return;
    archi_funnel_render_stepper(4);
}, 5);

add_action('woocommerce_before_thankyou', function ($order_id) {
    if (!archi_funnel_should_apply()) return;
    $order = wc_get_order($order_id);
    $status = $order ? $order->get_status() : '';
    $step = ($status === 'completed' || $status === 'receipt-approval') ? 5 : 4;
    archi_funnel_render_stepper($step);
}, 5);

// =====================================================================
// 5. Banner crítico en /order-pay/
// =====================================================================
add_action('woocommerce_before_pay_form', function () {
    if (!archi_funnel_should_apply()) return;
    echo '<div class="archi-funnel-receipt-warning" role="alert">';
    echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
    echo '<p><strong>Pagar sin subir el comprobante NO genera el ticket.</strong> Te lleva 30 segundos más, pero sin ese paso no podés entrar al evento.</p>';
    echo '</div>';
}, 10);

// =====================================================================
// 6. Copy del botón final del checkout (prioridad 9999 para pisar otros plugins)
// =====================================================================
add_filter('woocommerce_order_button_text', function ($text) {
    if (!archi_funnel_should_apply()) return $text;
    return 'Ya pagué, voy a subir el comprobante →';
}, 9999);

// El plugin de checkout multi-step de PeproDev pisa el button text con
// "Ok, Subir Comprobante por $XX". Como no podemos hookear su filter,
// pisamos también con JS en client-side.
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_checkout')) return;
    if (!is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) return;
    ?>
    <script id="archi-funnel-button-text-override">
    (function() {
        function overrideButtonText() {
            var btn = document.getElementById('place_order');
            if (!btn) return;
            var target = 'Ya pagué, voy a subir el comprobante →';
            if (btn.textContent.trim() !== target) {
                btn.textContent = target;
                btn.value = target;
                if (btn.hasAttribute('data-value')) btn.setAttribute('data-value', target);
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', overrideButtonText);
        } else {
            overrideButtonText();
        }
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_checkout', function() {
                setTimeout(overrideButtonText, 100);
            });
        }
        var observer = new MutationObserver(function() { overrideButtonText(); });
        var orderReview = document.getElementById('order_review');
        if (orderReview) observer.observe(orderReview, { childList: true, subtree: true, attributes: true, attributeFilter: ['value', 'data-value'] });
    })();
    </script>
    <?php
}, 100);

// =====================================================================
// 7. Copy de la thank-you según estado de la orden
// =====================================================================
add_filter('woocommerce_thankyou_order_received_text', function ($text, $order) {
    if (!archi_funnel_should_apply()) return $text;
    if (!$order) return $text;
    $status = $order->get_status();
    $email = $order->get_billing_email();

    if ($status === 'receipt-upload' || $status === 'pending') {
        return 'Tu pedido está creado pero <strong>todavía falta un paso</strong>: subí abajo el comprobante de la transferencia. Sin ese paso no se genera el ticket.';
    }
    if ($status === 'receipt-approval' || $status === 'on-hold' || $status === 'processing') {
        return 'Recibimos tu comprobante. En cuanto lo verifiquemos te llega el ticket a <strong>' . esc_html($email) . '</strong> (suele ser en minutos, máximo 2 horas).';
    }
    if ($status === 'receipt-rejected') {
        return 'Hubo un problema con tu comprobante. Escribinos a <a href="mailto:boleteria@archibrazo.org">boleteria@archibrazo.org</a> con el comprobante real y lo resolvemos.';
    }
    if ($status === 'completed') {
        return 'Tu ticket ya está confirmado y te llegó por mail a <strong>' . esc_html($email) . '</strong>. ¡Te esperamos!';
    }
    return $text;
}, 10, 2);

// =====================================================================
// 8. Traducciones del plugin de upload (PeproDev / PCB)
// =====================================================================
add_filter('gettext', function ($translated, $original, $domain) {
    if (!archi_funnel_should_apply()) return $translated;

    static $map = array(
        'Upload receipt'              => 'Subí tu comprobante',
        'Upload Receipt'              => 'Enviar comprobante',
        'Upload your receipt'         => 'Subí tu comprobante de transferencia',
        'Current receipt:'            => 'Comprobante cargado:',
        'Current receipt'             => 'Comprobante cargado',
        'Upload Receipt:'             => 'Cargá tu comprobante:',
        'Date Uploaded:'              => 'Fecha de carga:',
        'Date Uploaded'               => 'Fecha de carga',
        'Awaiting Upload'             => 'Esperando archivo',
        'No receipt uploaded'         => 'Todavía no subiste ningún comprobante',
        'Receipt Status'              => 'Estado del comprobante',
        'Receipt Uploaded'            => 'Comprobante recibido',
        'Receipt Approved'            => 'Comprobante aprobado',
        'Receipt Rejected'            => 'Comprobante rechazado',
        'Receipt Pending'             => 'Esperando verificación',
        'Submit Receipt'              => 'Enviar comprobante',
        'Save Receipt'                => 'Guardar comprobante',
        'Receipt uploaded successfully' => 'Comprobante subido OK',
        'Please upload a valid file'  => 'Subí un archivo válido (PDF, JPG o PNG)',
        'Please select a file'        => 'Elegí un archivo',
        'Invalid file type'           => 'Tipo de archivo no permitido',
        'File is too large'           => 'El archivo es muy grande (máximo 8MB)',
        'Upload failed'               => 'Falló la subida del archivo, intentá de nuevo',
        'Awaiting Receipt Upload'     => 'Esperando comprobante',
        'Awaiting Receipt Approval'   => 'Esperando aprobación del comprobante',
        // Acción "Pagar" del Detalles del pedido en thank-you / mi-cuenta:
        // suena raro porque el cliente YA pasó por checkout, mejor "Ir a pagar".
        'Pay'                         => 'Ir a pagar',
        'Pay for order'               => 'Ir a pagar',
        // Botón del carrito (paso 1): "no finalizar" - el cliente todavía no compra,
        // recién pasa a Datos. Joaco eligió "Realizar compra" (feedback de Alex, 2026-05-20).
        'Proceed to checkout'         => 'Realizar compra',
    );
    if (isset($map[$original])) return $map[$original];
    return $translated;
}, 20, 3);

// =====================================================================
// 8b. THANK-YOU: mover el form de PeproDev desde dentro de order-details hasta el upload-wrap
//     (que está arriba del Resumen). El template thankyou.php deja un placeholder
//     [data-archi-upload-target] y este JS hace el move tan pronto como el DOM esté listo.
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_woocommerce')) return;
    if (!is_wc_endpoint_url('order-received')) return;
    ?>
    <script id="archi-thankyou-move-upload-form">
    (function () {
        'use strict';
        function moveForm() {
            var target = document.querySelector('.archi-thankyou__upload[data-archi-upload-target]');
            if (!target) return false;
            var pepro = document.querySelector('.peprodev_woocommerce_receipt_uploader');
            if (!pepro) return false;
            if (target.contains(pepro)) return true; // ya está
            target.appendChild(pepro);
            // Marcar order-details como "ready" para hacer fade-in (sin FOUC)
            var od = document.querySelector('.archi-thankyou .woocommerce-order-details');
            if (od) od.classList.add('archi-ready');
            return true;
        }
        function customizeFileInput() {
            // El input nativo dice "Choose file" / "No file chosen" según el browser locale.
            // Lo escondemos visualmente (sin display:none — eso lo rompe en algunos browsers)
            // y ponemos un <label for="receipt-file"> que actúa como botón. El label nativamente
            // dispara el file picker al ser clickeado, sin necesitar JS para input.click().
            var input = document.querySelector('.archi-thankyou__upload input[type="file"]#receipt-file');
            if (!input) return;
            if (input.dataset.archiWrapped === '1') return;
            input.dataset.archiWrapped = '1';
            // Asegurar que tiene id (para asociar el label)
            if (!input.id) input.id = 'receipt-file';
            // Ocultar visualmente (no display:none — eso puede bloquear el click en algunos browsers)
            input.classList.add('archi-file-control__input-hidden');
            // Construir wrapper: <label> [Elegir archivo] <span> [Nombre del archivo]
            var wrap = document.createElement('div');
            wrap.className = 'archi-file-control';
            var label = document.createElement('label');
            label.className = 'archi-file-control__btn';
            label.setAttribute('for', input.id);
            label.textContent = 'Elegir archivo';
            var nameEl = document.createElement('span');
            nameEl.className = 'archi-file-control__name';
            nameEl.textContent = 'Ningún archivo elegido';
            // Insertar wrap antes del input y mover input dentro al final
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(label);
            wrap.appendChild(nameEl);
            wrap.appendChild(input);
            input.addEventListener('change', function () {
                var card = document.querySelector('.archi-thankyou__upload');
                if (input.files && input.files.length > 0) {
                    nameEl.textContent = input.files[0].name;
                    nameEl.classList.add('archi-file-control__name--selected');
                    if (card) card.classList.add('archi-upload-has-file');
                    label.textContent = 'Cambiar archivo';
                } else {
                    nameEl.textContent = 'Ningún archivo elegido';
                    nameEl.classList.remove('archi-file-control__name--selected');
                    if (card) card.classList.remove('archi-upload-has-file');
                    label.textContent = 'Elegir archivo';
                }
            });
        }
        function run() {
            if (!moveForm()) {
                var od = document.querySelector('.archi-thankyou .woocommerce-order-details');
                if (od) od.classList.add('archi-ready');
            }
            customizeFileInput();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
    })();
    </script>
    <?php
}, 5);

// =====================================================================
// 8a. JS para pisar strings hardcoded de PeproDev (no pasan por gettext)
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_woocommerce')) return;
    if (!(is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) return;
    ?>
    <script id="archi-funnel-pepro-strings-override">
    (function () {
        'use strict';
        var stringMap = {
            'Current receipt:': 'Comprobante cargado:',
            'Current receipt': 'Comprobante cargado',
            'Upload Receipt:': 'Cargá tu comprobante:',
            'Upload Receipt': 'Enviar comprobante',
            'Date Uploaded:': 'Fecha de carga:',
            'Date Uploaded': 'Fecha de carga',
            'Awaiting Upload': 'Esperando archivo'
        };
        function overrideStrings() {
            var cells = document.querySelectorAll('table.upload_receipt th, table.upload_receipt td, table.woocommerce-table--upload-receipt th, table.woocommerce-table--upload-receipt td, .peprodev_woocommerce_receipt_uploader h2, button.start-upload, .receipt-status');
            cells.forEach(function (el) {
                // Solo reemplazar text nodes directos, no romper HTML interno
                if (el.children.length === 0 || el.tagName === 'BUTTON' || el.tagName === 'H2' || el.classList.contains('receipt-status')) {
                    var text = el.textContent.trim();
                    if (stringMap[text]) el.textContent = stringMap[text];
                }
            });
            // h2 del header del plugin
            var h2 = document.querySelector('.peprodev_woocommerce_receipt_uploader h2, h2.upload_receipt');
            if (h2) {
                var text = h2.textContent.trim();
                if (text === 'Upload receipt' || text === 'Upload Receipt') {
                    h2.textContent = 'Subí tu comprobante';
                }
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', overrideStrings);
        } else {
            overrideStrings();
        }
        // Re-aplicar después de un short delay para casos donde el plugin renderea async
        setTimeout(overrideStrings, 500);
        setTimeout(overrideStrings, 1500);
    })();
    </script>
    <?php
}, 98);

// =====================================================================
// 8b. JS para hacer los WC notices cerrables con X
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_woocommerce')) return;
    if (!(is_cart() || is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay'))) return;
    ?>
    <script id="archi-funnel-notice-dismiss-js">
    (function () {
        'use strict';

        // SOLO el banner del cupón tiene X. El resto no.
        function shouldAddDismiss(notice) {
            // Si el banner está dentro del wrapper de cupón, sí
            if (notice.closest('.woocommerce-form-coupon-toggle')) return true;
            if (notice.classList.contains('woocommerce-form-coupon-toggle')) return true;

            // Si el texto contiene "cupón" o "coupon", es el banner del cupón
            var text = (notice.textContent || '').toLowerCase();
            if (text.includes('cupón') || text.includes('cupon') || text.includes('coupon')) return true;

            return false;
        }

        function addDismissButton(notice) {
            if (notice.querySelector('.archi-notice-dismiss')) return;
            if (!shouldAddDismiss(notice)) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'archi-notice-dismiss';
            btn.setAttribute('aria-label', 'Cerrar este aviso');
            btn.textContent = '×';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                notice.style.transition = 'opacity 180ms ease, height 220ms ease, margin 220ms ease, padding 220ms ease';
                notice.style.opacity = '0';
                setTimeout(function () {
                    notice.style.height = '0';
                    notice.style.margin = '0';
                    notice.style.padding = '0';
                    notice.style.overflow = 'hidden';
                    notice.style.borderWidth = '0';
                }, 180);
                setTimeout(function () { notice.remove(); }, 400);
            });
            notice.appendChild(btn);
        }

        function init() {
            var notices = document.querySelectorAll('.woocommerce-message, .woocommerce-info, .woocommerce-error');
            notices.forEach(addDismissButton);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        // Re-aplicar después de cualquier update AJAX de WC
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_wc_div updated_cart_totals updated_checkout added_to_cart', function () {
                setTimeout(init, 100);
            });
        }
        // Observer para notices que se agregan dinámicamente
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) {
                    if (n.nodeType === 1 && (n.classList?.contains('woocommerce-message') || n.classList?.contains('woocommerce-info') || n.classList?.contains('woocommerce-error'))) {
                        addDismissButton(n);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}, 99);

// =====================================================================
// 9. JS multi-step UI en el checkout
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_checkout')) return;
    if (!is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) return;
    ?>
    <script id="archi-funnel-multistep-js">
    (function () {
        'use strict';

        function $(sel, ctx) { return (ctx || document).querySelector(sel); }
        function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
        function getForm() { return $('form.checkout, form.woocommerce-checkout'); }
        function getBillingSection() { return $('.woocommerce-billing-fields'); }
        function getAdditionalSection() { return $('.woocommerce-additional-fields'); }

        function updateStepper(step) {
            var ROMANOS = { 1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V' };
            var FIGMARKS = {
                1: 'revisá lo que vas a llevarte',
                2: 'para mandarte el ticket',
                3: 'escaneá el QR desde tu app',
                4: 'sin esto no se genera el ticket',
                5: 'te avisamos cuando esté confirmado'
            };
            var dots = $$('.archi-funnel-stepper__dot');
            var lines = $$('.archi-funnel-stepper__line');
            dots.forEach(function (dot, idx) {
                var n = idx + 1;
                dot.classList.remove('pending', 'current', 'completed');
                if (n < step) {
                    dot.classList.add('completed');
                    dot.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                } else if (n === step) {
                    dot.classList.add('current');
                    dot.textContent = ROMANOS[n] || String(n);
                } else {
                    dot.classList.add('pending');
                    dot.textContent = ROMANOS[n] || String(n);
                }
            });
            lines.forEach(function (line, idx) {
                var n = idx + 1;
                if (n < step) line.classList.add('completed');
                else line.classList.remove('completed');
            });
            var labels = { 1: 'Ticket', 2: 'Datos', 3: 'Pagar', 4: 'Comprobante', 5: 'Listo' };
            var mobile = $('.archi-funnel-stepper__mobile');
            if (mobile) {
                mobile.textContent = 'Paso ' + step + ' de 5 — ' + (labels[step] || '');
            }
            var figmark = $('.archi-funnel-stepper__figmark');
            if (figmark) {
                figmark.innerHTML = '<b>Fig. ' + (ROMANOS[step] || step) + '</b> ' + (FIGMARKS[step] || '');
            }
        }

        function validateBilling() {
            clearValidationError();
            var billing = getBillingSection();
            if (!billing) return true;
            var invalids = [];
            $$('.validate-required input, .validate-required select', billing).forEach(function (input) {
                if (input.type === 'hidden') return;
                if (input.offsetParent === null) return;
                var val = (input.value || '').trim();
                var row = input.closest('.form-row');
                if (!val) {
                    invalids.push({ input: input, row: row });
                    if (row) row.classList.add('woocommerce-invalid', 'woocommerce-invalid-required-field');
                } else {
                    if (row) row.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
                }
            });
            if (invalids.length > 0) {
                showValidationError('Faltan datos en ' + invalids.length + ' campo' + (invalids.length === 1 ? '' : 's') + '. Completá lo marcado en rojo para continuar.');
                if (invalids[0].input) {
                    invalids[0].input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(function () { invalids[0].input.focus(); }, 400);
                }
                return false;
            }
            return true;
        }

        function showValidationError(msg) {
            var el = $('.archi-funnel-validation-error');
            if (!el) {
                el = document.createElement('div');
                el.className = 'archi-funnel-validation-error';
                el.setAttribute('role', 'alert');
                var continueWrap = $('.archi-funnel-continue-wrap');
                if (continueWrap) continueWrap.parentNode.insertBefore(el, continueWrap);
            }
            el.textContent = msg;
            el.classList.add('visible');
        }

        function clearValidationError() {
            var el = $('.archi-funnel-validation-error');
            if (el) el.classList.remove('visible');
        }

        function toggleStepHeading(step) {
            var h2 = $('.archi-page-heading--step-2');
            var h3 = $('.archi-page-heading--step-3');
            var bank = $('.archi-bank-cta--step-3');
            if (h2) h2.style.display = (step === 2) ? '' : 'none';
            if (h3) h3.style.display = (step === 3) ? '' : 'none';
            if (bank) bank.style.display = (step === 3) ? '' : 'none';
        }

        function goToStep3() {
            if (!validateBilling()) return;
            var form = getForm();
            if (!form) return;
            form.classList.remove('archi-funnel-step-2');
            form.classList.add('archi-funnel-step-3');
            updateStepper(3);
            toggleStepHeading(3);
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (window.jQuery) {
                window.jQuery(document.body).trigger('update_checkout');
            }
        }

        function goToStep2() {
            var form = getForm();
            if (!form) return;
            form.classList.remove('archi-funnel-step-3');
            form.classList.add('archi-funnel-step-2');
            updateStepper(2);
            toggleStepHeading(2);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Resaltar ARCHICOOP en el texto del método de pago "QR o Pago con Transferencia"
        // (el texto vive en .woocommerce-checkout-review-order, no en #payment)
        function highlightAliasInPayment() {
            var paymentBox = document.querySelector('.woocommerce-checkout-review-order, form.checkout');
            if (!paymentBox) return;
            // Buscar todos los nodos texto que contengan "ARCHICOOP"
            var walker = document.createTreeWalker(paymentBox, NodeFilter.SHOW_TEXT, null);
            var nodes = [];
            while (walker.nextNode()) {
                var n = walker.currentNode;
                if (!n.nodeValue || n.nodeValue.indexOf('ARCHICOOP') === -1) continue;
                if (!n.parentElement) continue;
                // Saltar si ya está en la pill o en el CTA principal (evitar doble wrap y no tocar el chip del CTA)
                if (n.parentElement.classList.contains('archi-alias-pill')) continue;
                if (n.parentElement.classList.contains('archi-bank-cta__alias-value')) continue;
                // Saltar contextos no-texto (script, style, noscript)
                var tag = n.parentElement.tagName;
                if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') continue;
                nodes.push(n);
            }
            nodes.forEach(function (node) {
                var parts = node.nodeValue.split('ARCHICOOP');
                if (parts.length < 2) return;
                var frag = document.createDocumentFragment();
                parts.forEach(function (part, idx) {
                    frag.appendChild(document.createTextNode(part));
                    if (idx < parts.length - 1) {
                        // Botón clickeable que copia "ARCHICOOP" al clipboard
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'archi-alias-pill';
                        btn.setAttribute('data-copy-value', 'ARCHICOOP');
                        btn.setAttribute('aria-label', 'Copiar alias ARCHICOOP');
                        btn.setAttribute('title', 'Click para copiar');
                        btn.textContent = 'ARCHICOOP';
                        frag.appendChild(btn);
                    }
                });
                node.parentNode.replaceChild(frag, node);
            });
        }
        // Envolver "¿Cómo abona?" + descripción + QR en un layout 2-col (desktop)
        function buildPaymentLayout() {
            var review = document.querySelector('.woocommerce-checkout-review-order');
            if (!review) return;
            if (review.querySelector('.archi-payment-layout')) return;
            var h3 = null;
            for (var i = 0; i < review.children.length; i++) {
                var c = review.children[i];
                if (c.tagName === 'H3' && /c.mo abona/i.test(c.textContent || '')) { h3 = c; break; }
            }
            if (!h3) return;
            var p = h3.nextElementSibling;
            if (!p || p.tagName !== 'P') return;
            // El siguiente puede ser <img> directo o tener un <noscript> intermedio
            var img = p.nextElementSibling;
            while (img && img.tagName === 'NOSCRIPT') img = img.nextElementSibling;
            if (!img || img.tagName !== 'IMG') return;
            var wrap = document.createElement('div');
            wrap.className = 'archi-payment-layout';
            var left = document.createElement('div');
            left.className = 'archi-payment-layout__left';
            var right = document.createElement('div');
            right.className = 'archi-payment-layout__right';
            review.insertBefore(wrap, h3);
            wrap.appendChild(left);
            wrap.appendChild(right);
            left.appendChild(h3);
            left.appendChild(p);
            right.appendChild(img);
        }

        // Sincroniza el "Monto a transferir" del bank CTA con el total real del
        // checkout. WC re-renderea el order review (y el total) por AJAX cuando
        // se aplica o saca un cupón; el bank CTA es estático, así que lo copiamos.
        function syncBankAmount() {
            var dest = document.querySelector('.archi-bank-cta__amount-value');
            if (!dest) return;
            var src = document.querySelector('.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount')
                   || document.querySelector('.order-total .woocommerce-Price-amount');
            if (src) dest.innerHTML = src.outerHTML;
        }

        // Correr en load + en updated_checkout (WC re-renderea el payment box ahí)
        document.addEventListener('DOMContentLoaded', function () {
            highlightAliasInPayment();
            buildPaymentLayout();
            syncBankAmount();
        });
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_checkout', function () {
                setTimeout(function () {
                    highlightAliasInPayment();
                    buildPaymentLayout();
                    syncBankAmount();
                }, 50);
            });
        }
        // Y también después de goToStep3
        var _origGo3 = goToStep3;
        goToStep3 = function () {
            _origGo3();
            setTimeout(function () {
                highlightAliasInPayment();
                buildPaymentLayout();
                syncBankAmount();
            }, 400);
        };

        // Copy-to-clipboard para datos bancarios + pill ARCHICOOP
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.archi-bank-cta__copy, .archi-alias-pill');
            if (!btn) return;
            e.preventDefault();
            // 2 modos: data-copy-target (id de elemento) o data-copy-value (literal)
            var value = null;
            var literal = btn.getAttribute('data-copy-value');
            if (literal) {
                value = literal;
            } else {
                var targetId = btn.getAttribute('data-copy-target');
                var el = targetId ? document.getElementById(targetId) : null;
                if (el) value = el.textContent.trim();
            }
            if (!value) return;
            var isPill = btn.classList.contains('archi-alias-pill');
            var done = function () {
                if (isPill) {
                    var orig = btn.textContent;
                    btn.classList.add('archi-alias-pill--ok');
                    btn.textContent = '¡Copiado!';
                    setTimeout(function () {
                        btn.textContent = orig || 'ARCHICOOP';
                        btn.classList.remove('archi-alias-pill--ok');
                    }, 1400);
                    return;
                }
                var label = btn.querySelector('span');
                var originalText = label ? label.textContent : '';
                if (label) label.textContent = '¡Copiado!';
                btn.classList.add('archi-bank-cta__copy--ok');
                setTimeout(function () {
                    if (label) label.textContent = originalText || 'Copiar';
                    btn.classList.remove('archi-bank-cta__copy--ok');
                }, 1600);
            };
            // (value, isPill, done ya definidos arriba)
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done, function () {
                    // fallback
                    var ta = document.createElement('textarea');
                    ta.value = value; document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); } catch (_) {}
                    document.body.removeChild(ta);
                    done();
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = value; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (_) {}
                document.body.removeChild(ta);
                done();
            }
        });

        function init() {
            var form = getForm();
            if (!form) return;

            if (!form.classList.contains('archi-funnel-step-3') && !form.classList.contains('archi-funnel-step-2')) {
                form.classList.add('archi-funnel-step-2');
                updateStepper(2);
            }

            if (!$('.archi-funnel-continue-wrap')) {
                var anchor = getAdditionalSection() || getBillingSection();
                if (anchor) {
                    var wrap = document.createElement('div');
                    wrap.className = 'archi-funnel-continue-wrap';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'archi-funnel-continue';
                    btn.textContent = 'Continuar al pago →';
                    btn.addEventListener('click', goToStep3);
                    wrap.appendChild(btn);
                    anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
                }
            }

            if (!$('.archi-funnel-back-wrap')) {
                var orderHeading = $('#order_review_heading') || $('#order_review');
                if (orderHeading) {
                    var backWrap = document.createElement('div');
                    backWrap.className = 'archi-funnel-back-wrap';
                    var backBtn = document.createElement('button');
                    backBtn.type = 'button';
                    backBtn.className = 'archi-funnel-back';
                    backBtn.textContent = '← Volver a tus datos';
                    backBtn.addEventListener('click', goToStep2);
                    backWrap.appendChild(backBtn);
                    orderHeading.parentNode.insertBefore(backWrap, orderHeading);
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_checkout', function () {
                setTimeout(init, 50);
            });
        }
    })();
    </script>
    <?php
}, 99);

// =====================================================================
// 10b. Paso 5 unificado: /gracias-comprobante/ (ID 22158) rebota a la
//      thank-you (order-received). La thank-you en estado verde ya es la
//      vista "Listo" con el resumen real de la compra + GIF, así el último
//      paso es una sola página y no la estática suelta de antes.
//      Gated por archi_funnel_should_apply(): hoy solo aplica a admin/preview;
//      con el funnel live aplica a todos. Si no se puede resolver la orden
//      se queda en /gracias-comprobante/ (fallback, lo maneja la sección 11).
// =====================================================================
add_action('template_redirect', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_page') || !is_page(22158)) return;
    if (!function_exists('wc_get_order')) return;

    $order = null;

    // 1. Referer: PeproDev redirige acá ni bien el cliente sube el comprobante
    //    en la página order-received. Ese order-received es el referer y trae
    //    el id + key de la orden.
    $ref = function_exists('wp_get_referer') ? wp_get_referer() : '';
    if (!$ref && !empty($_SERVER['HTTP_REFERER'])) {
        $ref = (string) $_SERVER['HTTP_REFERER'];
    }
    if ($ref && preg_match('#/order-received/(\d+)#', $ref, $m)) {
        $maybe = wc_get_order((int) $m[1]);
        $qs    = parse_url($ref, PHP_URL_QUERY);
        if ($maybe && $qs) {
            parse_str($qs, $q);
            if (!empty($q['key']) && hash_equals($maybe->get_order_key(), (string) $q['key'])) {
                $order = $maybe;
            }
        }
    }

    // 2. Fallback: cliente logueado => su última orden.
    if (!$order && is_user_logged_in() && function_exists('wc_get_customer_last_order')) {
        $last = wc_get_customer_last_order(get_current_user_id());
        if ($last) {
            $order = $last;
        }
    }

    if ($order) {
        wp_safe_redirect($order->get_checkout_order_received_url(), 302);
        exit;
    }
}, 5);

// =====================================================================
// 8d. Carrito: auto-actualizar el total cuando se cambia la cantidad con los
//     botones +/- (sin tener que apretar "Actualizar carrito"). Debounce 700ms.
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_cart') || !is_cart()) return;
    ?>
    <script id="archi-cart-auto-update">
    (function () {
        'use strict';
        if (!document.querySelector('form.woocommerce-cart-form')) return;
        // Re-consultar el form en cada tick: WooCommerce reemplaza el form
        // entero tras un update AJAX (ej. quitar item), así que cachear la
        // referencia la deja apuntando a un nodo viejo.
        function getForm() { return document.querySelector('form.woocommerce-cart-form'); }
        // Polling: detecta el cambio de cantidad sin importar cómo se dispara
        // (botón +/-, tipeo). Más robusto que escuchar eventos del stepper.
        function sig() {
            var form = getForm();
            if (!form) return '';
            var s = '';
            form.querySelectorAll('input.qty').forEach(function (i) { s += i.value + ','; });
            return s;
        }
        var last = sig();
        var timer;
        function submitUpdate() {
            var form = getForm();
            if (!form) return;
            // form.submit() hace un POST crudo (saltea interceptores JS que
            // bloquean el submit del botón "Actualizar carrito"). El campo
            // update_cart inyectado le dice a WooCommerce que recalcule.
            if (!form.querySelector('input[type="hidden"][name="update_cart"]')) {
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = 'update_cart'; h.value = '1';
                form.appendChild(h);
            }
            form.submit();
        }
        setInterval(function () {
            var now = sig();
            if (now === last) return;
            last = now;
            clearTimeout(timer);
            timer = setTimeout(submitUpdate, 700);
        }, 350);
        // Quitar un item dispara el AJAX de WooCommerce, pero el bloque de
        // totales del child theme no es .cart_totals, así que WC no lo puede
        // refrescar y quedan viejos. Recargar para que el server re-renderee.
        if (window.jQuery) {
            window.jQuery(document.body).on('updated_wc_div updated_cart_totals', function () {
                window.location.reload();
            });
        }
    })();
    </script>
    <?php
}, 99);

// =====================================================================
// 8c. Datos del pedido (Nº / fecha / email) en una línea, DEBAJO de la tabla
//     "Detalles del pedido" en la thank-you (feedback de Joaco, 2026-05-20).
// =====================================================================
add_action('woocommerce_order_details_after_order_table', function ($order) {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) return;
    if (!is_a($order, 'WC_Order')) return;
    // Solo en el paso 5 "Listo" (verde). En el paso 4 (Comprobante) el detalle no va.
    if (!in_array($order->get_status(), array('receipt-approval', 'completed'), true)) return;
    ?>
    <p class="archi-thankyou__ordermeta">
        <span class="archi-thankyou__ordermeta-label">Pedido</span> #A-<?php echo esc_html($order->get_order_number()); ?>
        - <span class="archi-thankyou__ordermeta-label">Fecha:</span> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
        - <span class="archi-thankyou__ordermeta-label">Email:</span> <?php echo esc_html($order->get_billing_email()); ?>
    </p>
    <?php
}, 20);

// =====================================================================
// 11. Página "Gracias por tu Comprobante" (/gracias-comprobante/, ID 22158)
//     Reemplaza cada embed de giphy.com por el GIF self-hosted del gatito +
//     los 2 botones (Ver agenda + Cómo llegar) debajo. La página tiene una
//     sección desktop y otra mobile, cada una con su propio embed; el regex
//     reemplaza TODAS. Cero tráfico a giphy.com.
// =====================================================================
add_filter('the_content', function ($content) {
    if (!archi_funnel_should_apply()) return $content;
    if (!is_singular('page')) return $content;
    if (get_the_ID() !== 22158) return $content;
    if (is_admin()) return $content;

    ob_start();
    ?>
    <img src="https://www.archibrazo.org/wp-content/uploads/2026/05/gato-archibrazo.gif" alt="Gatito esperando el comprobante" width="380" height="264" class="archi-gracias-gif" decoding="async">
    <div class="archi-gracias-cta-buttons">
        <a href="<?php echo esc_url(home_url('/eventos/')); ?>" class="archi-btn archi-btn--secondary">Ver agenda completa →</a>
        <a href="https://www.google.com/maps/place/El+Archibrazo/@-34.6048636,-58.421484,17z/data=!3m1!4b1!4m6!3m5!1s0x95bcca8a7c0dca87:0x1154aae4e74548ca!8m2!3d-34.6048681!4d-58.4168706!16s%2Fg%2F11bbrm210t" target="_blank" rel="noopener" class="archi-btn archi-btn--secondary">Cómo llegar 🗺️</a>
    </div>
    <?php
    $replacement = ob_get_clean();

    $content = preg_replace(
        '#<iframe\b[^>]*giphy\.com.*?</iframe>\s*(?:<p\b[^>]*>\s*<a\b[^>]*giphy\.com[^>]*>.*?</a>\s*</p>)?#is',
        $replacement,
        $content
    );
    return $content;
}, 20);

// =====================================================================
// 12. Login form en checkout: stylear, esconder billing/payment hasta login.
//     El botón "Continuar con Google" lo inyecta el plugin Wp Social via
//     hook 'woocommerce_login_form'. Acá solo lo styleamos.
// =====================================================================

add_action('wp_head', function () {
    if (!archi_funnel_should_apply()) return;
    $is_checkout = function_exists('is_checkout') && is_checkout();
    $is_account = function_exists('is_account_page') && is_account_page();
    if (!$is_checkout && !$is_account) return;
    if ($is_checkout && is_user_logged_in()) return;
    ?>
    <style id="archi-checkout-login-styles">
    /* === Cuando NO estás logueado, ocultar form de billing/payment === */
    /* Solo queda visible: stepper, heading, info "¿Ya eres cliente?" y el form de login.
       NO usamos selector tipo "form > div" porque WP Social injecta sus buttons dentro de divs hijos. */
    body:is(.woocommerce-checkout, .woocommerce-account) #customer_details,
    body:is(.woocommerce-checkout, .woocommerce-account) #order_review,
    body:is(.woocommerce-checkout, .woocommerce-account) #order_review_heading,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-additional-fields,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-shipping-fields,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-checkout-payment,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-billing-fields {
        display: none !important;
    }
    /* Pero si el plugin Wp Social inyectó su container dentro de billing,
       lo dejamos visible (movido por JS abajo, igual no debería estar adentro
       en el hook woocommerce_login_form, pero por las dudas). */
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container {
        display: block !important;
        visibility: visible !important;
    }

    /* === Info "¿Ya eres cliente?" - lo redibujo como un eyebrow + heading === */
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-info:has(a.showlogin),
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login-toggle {
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        margin: 28px auto 12px !important;
        max-width: 720px !important;
        text-align: left !important;
        color: var(--archi-cream, #f5f1ea) !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 0 !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-info:has(a.showlogin)::before {
        content: '¿Ya compraste antes?';
        display: block;
        font-family: 'Manrope', Arial, sans-serif;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #ff3d7f;
        margin: 0 0 8px;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-info:has(a.showlogin)::after {
        content: 'Entrá con tu cuenta';
        display: block;
        font-family: 'AndralisND', 'Rockwell', 'Rockwell Std', Georgia, serif;
        font-size: 28px;
        font-weight: 600;
        line-height: 1.15;
        color: #f5f1ea;
        margin: 0 0 6px;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-info:has(a.showlogin) a.showlogin {
        display: none !important;
    }

    /* === El form de login propiamente dicho dentro de una card === */
    body:is(.woocommerce-checkout, .woocommerce-account) form.woocommerce-form-login,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login {
        background: #161616 !important;
        border: 1px solid #242424 !important;
        border-radius: 16px !important;
        padding: 28px 28px 28px !important;
        margin: 0 auto 28px !important;
        max-width: 720px !important;
        display: block !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login > p:first-of-type {
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 14px !important;
        color: #a8a29e !important;
        margin: 0 0 20px !important;
        line-height: 1.5 !important;
    }

    /* === Botón Google según Google Identity Branding Guidelines ===
       Spec: blanco con borde #dadce0, logo G multicolor SVG inline, Roboto-like
       font, label "Continuar con Google", border-radius 4px (no pill). */
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container {
        display: flex !important;
        justify-content: center !important;
        margin: 18px 0 22px !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login {
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        width: 100% !important;
        max-width: 320px !important;
    }
    /* Esconder cualquier elemento vacío sin texto/contenido dentro del form
       de login que esté heredando mi gradient (caso del botón naranja vacío). */
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login p.form-row:empty,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login button:empty:not([name="login"]),
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="submit"][value=""] {
        display: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 12px !important;
        width: 100% !important;
        background: #ffffff !important;
        color: #1f1f1f !important;
        border: 1px solid #dadce0 !important;
        border-radius: 4px !important;
        padding: 10px 16px !important;
        height: 44px !important;
        font-family: 'Roboto', 'Manrope', Arial, sans-serif !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        letter-spacing: 0.25px !important;
        text-decoration: none !important;
        box-shadow: none !important;
        transition: background 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease !important;
        cursor: pointer !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item:hover {
        background: #f8f9fa !important;
        border-color: #d2e3fc !important;
        box-shadow: 0 1px 2px 0 rgba(60,64,67,0.30), 0 1px 3px 1px rgba(60,64,67,0.15) !important;
        color: #1f1f1f !important;
        transform: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item:focus,
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item:active {
        background: #f1f3f4 !important;
    }
    /* Esconder el icono propio del plugin y reemplazar por G oficial via SVG */
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item--icon i,
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item--icon svg {
        display: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item--icon {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 20px !important;
        height: 20px !important;
        margin: 0 !important;
        padding: 0 !important;
        background: none !important;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'><path fill='%23EA4335' d='M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z'/><path fill='%234285F4' d='M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z'/><path fill='%23FBBC05' d='M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z'/><path fill='%2334A853' d='M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z'/></svg>") !important;
        background-repeat: no-repeat !important;
        background-position: center !important;
        background-size: 18px 18px !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .xs-login__item--label {
        font-family: 'Roboto', 'Manrope', Arial, sans-serif !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        letter-spacing: 0.25px !important;
        color: #1f1f1f !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) #xs-social-login-container .wslu-color-scheme--google {
        background: #ffffff !important;
        color: #1f1f1f !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .form-row {
        display: block !important;
        width: 100% !important;
        margin: 0 0 14px !important;
        padding: 0 !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .form-row-first,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .form-row-last {
        width: 100% !important;
        float: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login label {
        display: block !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #d6cfc1 !important;
        margin: 0 0 6px !important;
        letter-spacing: 0.02em !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="text"],
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="password"] {
        width: 100% !important;
        padding: 12px 16px !important;
        background: #0a0a0a !important;
        border: 1px solid #242424 !important;
        border-radius: 10px !important;
        color: #f5f1ea !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 14px !important;
        line-height: 1.4 !important;
        height: auto !important;
        min-height: 0 !important;
        box-sizing: border-box !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="text"]:focus,
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="password"]:focus {
        outline: none !important;
        border-color: #ff3d7f !important;
        box-shadow: 0 0 0 3px rgba(255,61,127,0.18) !important;
    }
    /* Row con remember + submit */
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login p.form-row:has(button[name="login"]) {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 14px !important;
        justify-content: flex-start !important;
        margin: 18px 0 0 !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login p.form-row:has(button[name="login"]) > * {
        margin: 0 !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .woocommerce-form-login__rememberme {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 13px !important;
        color: #d6cfc1 !important;
        order: 2 !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .woocommerce-form-login__rememberme input[type="checkbox"] {
        accent-color: #ff3d7f !important;
        width: 16px !important;
        height: 16px !important;
        margin: 0 !important;
    }
    /* Gradient SOLO al submit que tiene la clase oficial.
       Si hay otro button[name="login"] sin clase, NO le aplicamos el style y
       lo escondemos abajo. */
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login button.woocommerce-form-login__submit {
        background: linear-gradient(95deg, #ff3d7f 0%, #ff8a3d 100%) !important;
        background-color: #ff3d7f !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 999px !important;
        padding: 12px 32px !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-weight: 700 !important;
        font-size: 14px !important;
        letter-spacing: 0.02em !important;
        cursor: pointer !important;
        box-shadow: 0 6px 18px rgba(255,61,127,0.28) !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease !important;
        order: 1 !important;
        min-width: 140px !important;
        width: auto !important;
        flex: 0 0 auto !important;
        white-space: nowrap !important;
        line-height: 1.4 !important;
        height: auto !important;
        display: inline-block !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login button.woocommerce-form-login__submit:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 10px 28px rgba(255,61,127,0.36) !important;
    }
    /* Esconder cualquier button[name="login"] que NO sea el oficial.
       Plugins de social login a veces inyectan buttons vacios huérfanos. */
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login button[name="login"]:not(.woocommerce-form-login__submit),
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login input[type="submit"][name="login"]:not(.woocommerce-form-login__submit) {
        display: none !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .lost_password {
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 13px !important;
        margin: 14px 0 0 !important;
        text-align: left !important;
    }
    body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login .lost_password a {
        color: #ff8a3d !important;
        text-decoration: underline !important;
    }

    /* Stepper sigue visible y el heading "¿A dónde te mandamos el ticket?" también,
       pero le agrego copy abajo recordando que necesita login. */
    body:is(.woocommerce-checkout, .woocommerce-account) .archi-page-heading--step-2::after {
        content: '👉 Primero entrá con tu cuenta para continuar.';
        display: block;
        font-family: 'Manrope', Arial, sans-serif;
        font-size: 13px;
        color: #ff8a3d;
        margin-top: 10px;
        letter-spacing: 0.01em;
    }

    @media (max-width: 600px) {
        body:is(.woocommerce-checkout, .woocommerce-account) .woocommerce-form-login {
            padding: 22px 18px 22px !important;
            border-radius: 14px !important;
        }
    }
    </style>
    <script id="archi-google-popup-login" data-cfasync="false">
    (function () {
      // Para que el browser NO bloquee el popup, tenemos que llamar window.open
      // directamente desde el onclick original del DOM (gesto del usuario
      // certificado). Por eso reescribimos el onclick del anchor en lugar de
      // interceptar el evento con addEventListener.

      // Función global que dispara el popup. La llamamos desde el onclick del
      // anchor, así el browser la trata como user-gesture directo.
      window.__archiOpenGoogleLogin = function (url) {
        var w = 520, h = 640;
        var left = window.screen.width / 2 - w / 2;
        var top = window.screen.height / 2 - h / 2;
        var p = window.open(url, 'archi_google_login',
          'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes,toolbar=no,menubar=no');
        if (!p) {
          // popup bloqueado → fallback a redirect normal
          window.location.href = url;
          return;
        }
        try { p.focus(); } catch (e) {}
        var sameOriginHits = 0;
        var poll = setInterval(function () {
          if (p.closed) {
            clearInterval(poll);
            window.location.reload();
            return;
          }
          try {
            var pUrl = p.location.href;
            if (pUrl && pUrl.indexOf(window.location.origin) === 0) {
              sameOriginHits++;
              if (sameOriginHits >= 2) {
                clearInterval(poll);
                try { p.close(); } catch (e) {}
                window.location.reload();
              }
            }
          } catch (e) {
            sameOriginHits = 0;
          }
        }, 500);
        return false;
      };

      function rewireGoogleButton() {
        var items = document.querySelectorAll('#xs-social-login-container .xs-login__item');
        items.forEach(function (link) {
          if (link.dataset.archiRewired === '1') return;
          // Extraer la URL del onclick original (el plugin Wp Social envuelve
          // todo en un wrapper de Cloudflare Rocket Loader).
          var raw = link.getAttribute('onclick') || '';
          var m = raw.match(/location\.href\s*=\s*['"]([^'"]+)['"]/);
          var url = m ? m[1] : (link.href && link.href.indexOf('wslu-social-login') !== -1 ? link.href : null);
          if (!url) return;
          // Limpiar el onclick attribute (lleno de basura de Rocket Loader)
          // y setear el handler como PROPERTY (no attribute) para que el browser
          // lo trate como user-gesture sin interferencia de RL.
          link.removeAttribute('onclick');
          link.onclick = function (e) {
            if (e && e.preventDefault) e.preventDefault();
            window.__archiOpenGoogleLogin(url);
            return false;
          };
          link.dataset.archiRewired = '1';
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rewireGoogleButton);
      } else {
        rewireGoogleButton();
      }
      // También observar si el plugin re-inyecta el botón después del load
      // (algunos checkouts hacen reload de ese fragmento via AJAX).
      var mo = new MutationObserver(function () { rewireGoogleButton(); });
      mo.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}, 99);

// =====================================================================
// FIX UX (2026-06-06): auto-seleccionar variante cuando hay una sola
// vendible (in_stock + purchasable). Sin esto, WC deja el dropdown con el
// nombre como placeholder pero value vacio, el boton "Reserva" queda con
// clase wc-variation-selection-needed (opacity 0.5) y el cliente cree que
// ya esta elegido. Confirmado al menos en 2 funciones (5/6 y 6/6).
// =====================================================================
add_action('wp_footer', function () {
    if (!function_exists('is_product')) return;
    if (!is_product()) return;
    ?>
    <script id="archi-auto-select-variation">
    (function () {
        'use strict';

        function autoSelectVariation() {
            var form = document.querySelector('form.variations_form');
            if (!form) return;

            var raw = form.getAttribute('data-product_variations');
            if (!raw) return;

            var variations;
            try { variations = JSON.parse(raw); }
            catch (e) { return; }
            if (!Array.isArray(variations) || !variations.length) return;

            // Solo las variantes realmente vendibles
            var sellable = variations.filter(function (v) {
                return v && v.is_in_stock && v.is_purchasable;
            });

            // Si hay 0 (agotado) o 2+ (cliente elige), no tocamos
            if (sellable.length !== 1) return;

            var target = sellable[0];
            if (!target.attributes || typeof target.attributes !== 'object') return;

            var selects = form.querySelectorAll('select[name^="attribute_"]');
            if (!selects.length) return;

            var changed = false;
            selects.forEach(function (select) {
                if (select.value) return;
                var val = target.attributes[select.name];
                if (!val) return;
                var opt = Array.prototype.find.call(select.options, function (o) {
                    return o.value === val;
                });
                if (!opt) return;
                select.value = val;
                changed = true;
            });

            if (!changed) return;

            if (window.jQuery) {
                window.jQuery(form).find('select[name^="attribute_"]').trigger('change');
            } else {
                selects.forEach(function (s) {
                    s.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', autoSelectVariation);
        } else {
            autoSelectVariation();
        }

        // Re-intentar en eventos de WC y al renderizarse tarde
        if (window.jQuery) {
            window.jQuery(document).on('wc_variation_form found_variation reset_data', function () {
                setTimeout(autoSelectVariation, 50);
            });
        }
    })();
    </script>
    <?php
}, 99);
