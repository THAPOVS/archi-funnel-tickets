<?php
/**
 * Archibrazo - Funnel de tickets · INTEGRACIÓN FASE 1 (admin-only)
 * =================================================================
 *
 * Snippet para WPCode Lite. Agrega capa visual sobre el checkout actual:
 *   - Stepper visual de 5 pasos en cart / checkout / order-pay / thank-you
 *   - Copy del botón final del checkout cambiada
 *   - Banner crítico en /order-pay/ ("sin subir comprobante no hay ticket")
 *   - Copy del thank-you adaptada según estado de la orden
 *
 * SOLO se aplica si el visitante:
 *   - es admin logueado (manage_options) o shop manager (manage_woocommerce)
 *   - O está en un subdominio que empieza con "staging."
 *
 * Visitantes públicos siguen viendo el sitio actual EXACTO. Cero riesgo.
 *
 * No toca:
 *   - Hooks de orden / customer / gateway
 *   - El form de WC ni el de PeproDev
 *   - El snippet 37364 del bot Telegram
 *   - DB, rewrite rules, post types
 *
 * INSTALACIÓN
 * -----------
 *   1. WP admin → Code Snippets / WPCode → Add Snippet → PHP Snippet
 *   2. Title: "Archi Funnel - Fase 1 (admin-only)"
 *   3. Pegar este código
 *   4. Insertion: Auto Insert → Run Everywhere
 *   5. Save & Activate
 *
 * VERIFICACIÓN
 * ------------
 *   - Logueado como admin, ir a archibrazo.org/finalizar-compra/ con algo en el carrito.
 *   - Tiene que aparecer arriba el stepper "Ticket · Datos · Pagar · Comprobante · Listo".
 *   - El botón final dice "Ya pagué, voy a subir el comprobante →".
 *   - Si abrís en incógnito (no logueado), el sitio se ve idéntico al actual.
 *
 * ROLLBACK: WPCode → este snippet → Deactivate. 5 segundos. Cero rastro.
 */

if (!defined('ABSPATH')) return;

// =====================================================================
// GUARDRAIL: cuándo aplicar el rediseño
// =====================================================================
if (!function_exists('archi_funnel_should_apply')) {
    function archi_funnel_should_apply() {
        // FASE 3 - LIVE: el rediseño aplica a TODOS los visitantes
        return true;
        // (código original abajo - sin efecto porque retornamos arriba)
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
// Stepper HTML helper
// =====================================================================
if (!function_exists('archi_funnel_render_stepper')) {
    function archi_funnel_render_stepper($current_step = 1) {
        if (!archi_funnel_should_apply()) return;

        $steps = [
            1 => 'Ticket',
            2 => 'Datos',
            3 => 'Pagar',
            4 => 'Comprobante',
            5 => 'Listo',
        ];
        $current_label = isset($steps[$current_step]) ? $steps[$current_step] : '';

        echo '<nav class="archi-funnel-stepper" aria-label="Progreso de la compra">';
        echo '<ol class="archi-funnel-stepper__list">';
        foreach ($steps as $n => $label) {
            $class = 'pending';
            $content = (string) $n;
            if ($n < $current_step) {
                $class = 'completed';
                $content = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            } elseif ($n === (int) $current_step) {
                $class = 'current';
            }
            $line_class = ($n < $current_step) ? 'archi-funnel-stepper__line completed' : 'archi-funnel-stepper__line';
            echo '<li class="archi-funnel-stepper__item">';
            echo '<span class="archi-funnel-stepper__dot ' . esc_attr($class) . '">' . $content . '</span>';
            echo '<span class="archi-funnel-stepper__label">' . esc_html($label) . '</span>';
            echo '</li>';
            if ($n < 5) {
                echo '<li class="' . esc_attr($line_class) . '" aria-hidden="true"></li>';
            }
        }
        echo '</ol>';
        echo '<p class="archi-funnel-stepper__mobile">Paso ' . (int) $current_step . ' de 5 · ' . esc_html($current_label) . '</p>';
        // Notice solo visible para admins (no para visitantes públicos)
        if (function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('manage_woocommerce'))) {
            echo '<p class="archi-funnel-stepper__notice">Rediseño live · estás logueado como admin (este aviso solo lo ves vos).</p>';
        }
        echo '</nav>';
    }
}

// =====================================================================
// CSS scopeado al stepper + banner (NO restilea el resto del checkout)
// =====================================================================
add_action('wp_head', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_cart')) return;
    if (!is_cart() && !is_checkout() && !is_wc_endpoint_url('order-pay') && !is_wc_endpoint_url('order-received')) return;
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
    <style id="archi-funnel-fase-1-css">
        /* Stepper */
        .archi-funnel-stepper {
            font-family: 'Manrope', system-ui, sans-serif;
            max-width: 720px;
            margin: 24px auto 32px;
            padding: 0 16px;
        }
        .archi-funnel-stepper__list {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .archi-funnel-stepper__item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
            margin: 0 !important;
        }
        .archi-funnel-stepper__dot {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: all 220ms ease;
        }
        .archi-funnel-stepper__dot.pending {
            background: rgba(245, 241, 234, 0.06);
            color: rgba(245, 241, 234, 0.5);
            border: 1px solid rgba(245, 241, 234, 0.12);
        }
        .archi-funnel-stepper__dot.current {
            background: linear-gradient(95deg, #ff3d7f, #ff8a3d);
            color: white;
            box-shadow: 0 0 0 4px rgba(255, 61, 127, 0.18), 0 8px 16px -8px rgba(255, 61, 127, 0.5);
        }
        .archi-funnel-stepper__dot.completed {
            background: #34d399;
            color: #0a0a0a;
        }
        .archi-funnel-stepper__label {
            font-size: 13px;
            color: rgba(245, 241, 234, 0.7);
            font-weight: 500;
        }
        .archi-funnel-stepper__item:has(.current) .archi-funnel-stepper__label {
            color: #f5f1ea;
            font-weight: 600;
        }
        .archi-funnel-stepper__line {
            flex: 1;
            height: 2px;
            background: rgba(245, 241, 234, 0.1);
            list-style: none !important;
            margin: 0 !important;
        }
        .archi-funnel-stepper__line.completed { background: #34d399; }
        .archi-funnel-stepper__mobile {
            display: none;
            font-size: 13px;
            color: rgba(245, 241, 234, 0.7);
            margin: 12px 0 0;
            text-align: center;
        }
        .archi-funnel-stepper__notice {
            margin: 14px 0 0;
            padding: 8px 12px;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.25);
            border-radius: 8px;
            font-size: 12px;
            color: #fbbf24;
            text-align: center;
        }
        @media (max-width: 640px) {
            .archi-funnel-stepper__label { display: none; }
            .archi-funnel-stepper__mobile { display: block; }
            .archi-funnel-stepper__dot { width: 28px; height: 28px; font-size: 12px; }
        }

        /* Banner crítico en order-pay */
        .archi-funnel-pay-banner {
            font-family: 'Manrope', system-ui, sans-serif;
            max-width: 720px;
            margin: 0 auto 24px;
            padding: 14px 18px;
            background: linear-gradient(95deg, rgba(251, 191, 36, 0.14), rgba(255, 138, 61, 0.08));
            border: 1px solid rgba(251, 191, 36, 0.4);
            border-radius: 12px;
            color: #fde68a;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 14px;
            line-height: 1.55;
        }
        .archi-funnel-pay-banner strong { color: #fbbf24; }
        .archi-funnel-pay-banner svg { flex-shrink: 0; margin-top: 2px; color: #fbbf24; }

        /* Eyebrow heading antes del form en order-pay */
        .archi-funnel-pay-intro {
            font-family: 'Manrope', system-ui, sans-serif;
            max-width: 720px;
            margin: 0 auto 18px;
        }
        .archi-funnel-pay-intro__eyebrow {
            color: #ff3d7f;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 6px;
        }
        .archi-funnel-pay-intro__title {
            font-family: 'Fraunces', serif;
            font-weight: 500;
            font-size: 32px;
            letter-spacing: -0.025em;
            margin: 0 0 6px;
            line-height: 1.1;
        }
        .archi-funnel-pay-intro__sub {
            font-size: 15px;
            opacity: 0.7;
            margin: 0;
        }
    </style>
    <?php
}, 5);

// =====================================================================
// Renders del stepper en cada página del flujo
// =====================================================================
add_action('woocommerce_before_cart', function () {
    archi_funnel_render_stepper(1);
}, 5);

add_action('woocommerce_before_checkout_form', function () {
    // En checkout estamos entre los pasos 2 y 3 (multi-step UI todavía no implementado).
    // Para Fase 1 mostramos 2 como "current" (datos). En Fase 2 con JS multi-step, switcheamos a 3 cuando avancen.
    archi_funnel_render_stepper(2);
}, 5);

add_action('woocommerce_before_pay_form', function () {
    // Eyebrow + título + banner crítico antes del form de PeproDev
    archi_funnel_render_stepper(4);
    ?>
    <div class="archi-funnel-pay-intro">
        <p class="archi-funnel-pay-intro__eyebrow">Último paso</p>
        <h1 class="archi-funnel-pay-intro__title">Subí tu comprobante</h1>
        <p class="archi-funnel-pay-intro__sub">Sin este paso <strong>no se genera el ticket</strong>. Es lo único que nos falta para confirmar tu lugar.</p>
    </div>
    <div class="archi-funnel-pay-banner" role="alert">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><strong>Pagar sin subir el comprobante NO genera el ticket.</strong> Te lleva 30 segundos más, pero sin ese paso no podés entrar al evento.</span>
    </div>
    <?php
}, 5);

add_action('woocommerce_before_thankyou', function ($order_id) {
    // En el thank-you decidimos paso según estado
    $order = wc_get_order($order_id);
    $status = $order ? $order->get_status() : '';
    $step = ($status === 'completed' || $status === 'receipt-approval') ? 5 : 4;
    archi_funnel_render_stepper($step);
}, 5);

// =====================================================================
// Copy del botón final del checkout
// =====================================================================
add_filter('woocommerce_order_button_text', function ($text) {
    if (!archi_funnel_should_apply()) return $text;
    return 'Ya pagué, voy a subir el comprobante →';
});

// =====================================================================
// Copy del thank-you adaptada al estado real de la orden
// =====================================================================
add_filter('woocommerce_thankyou_order_received_text', function ($text, $order) {
    if (!archi_funnel_should_apply()) return $text;
    if (!$order) return $text;

    $status = $order->get_status();
    $email = $order->get_billing_email();

    if ($status === 'receipt-upload') {
        return 'Tu pedido está creado pero <strong>todavía falta un paso</strong>: subí abajo el comprobante de la transferencia para que se genere el ticket.';
    }
    if ($status === 'receipt-approval') {
        return 'Recibimos tu comprobante. En cuanto lo verifiquemos te llega el ticket a <strong>' . esc_html($email) . '</strong> (suele ser en minutos, máximo 2 horas).';
    }
    if ($status === 'receipt-rejected') {
        return 'Hubo un problema con tu comprobante. Escribinos a <a href="mailto:archibrazo@gmail.com">archibrazo@gmail.com</a> con el comprobante real y lo resolvemos.';
    }
    if ($status === 'completed') {
        return 'Tu ticket ya está confirmado y te llegó por mail a <strong>' . esc_html($email) . '</strong>. ¡Te esperamos!';
    }
    return $text;
}, 10, 2);
