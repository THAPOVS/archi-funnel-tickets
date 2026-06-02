<?php
/**
 * Archibrazo - Funnel de tickets · INTEGRACIÓN FASE 2 LITE (admin-only)
 * =====================================================================
 *
 * Versión LITE: solo multi-step UI + botones + centrado de steps.
 * NO incluye restyling agresivo del theme (eso requiere child theme).
 *
 * Qué hace:
 *   - Oculta "Tu pedido" + QR + términos + botón final en step 2
 *   - Muestra solo los datos personales en step 2
 *   - Botón "Continuar al pago →" entre billing fields y order_review
 *   - Botón "← Volver a tus datos" arriba del order_review (visible solo en step 3)
 *   - Validación client-side antes de avanzar
 *   - CENTRADO del form en step 2 (no queda a la izquierda con columna derecha vacía)
 *   - CENTRADO del order_review en step 3
 *   - Sincroniza el stepper visual de Fase 1
 *
 * NO TOCA:
 *   - Tablas, fondos, fuentes, inputs del theme (Astra sigue mandando ahí)
 *   - El submit final de WC
 *   - El form de PeproDev en order-pay
 *
 * REQUIERE Fase 1 activa, pero define su propio archi_funnel_should_apply
 * con function_exists check, así que tolera cualquier orden de carga.
 */

if (!defined('ABSPATH')) return;

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
// CSS LITE: solo botones del prototipo + centrado de steps
// =====================================================================
add_action('wp_head', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_checkout')) return;
    $is_cart_page = function_exists('is_cart') && is_cart();
    $is_checkout_page = is_checkout() && !is_wc_endpoint_url('order-received') && !is_wc_endpoint_url('order-pay');
    if (!$is_cart_page && !$is_checkout_page) return;
    ?>
    <style id="archi-funnel-fase-2-lite-css">
        /* ============================================ */
        /* CART: el cart_totals ocupa todo el ancho, con layout horizontal en desktop */
        /* ============================================ */
        body.woocommerce-cart .cart-collaterals,
        body.woocommerce-cart .cart-collaterals .cart_totals {
            width: 100% !important;
            max-width: 100% !important;
            float: none !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        body.woocommerce-cart .cart_totals h2 {
            margin-bottom: 16px !important;
        }
        body.woocommerce-cart .cart_totals > .shop_table {
            margin-bottom: 0 !important;
        }

        @media (min-width: 768px) {
            /* Desktop: tabla a la izquierda + botón a la derecha en la misma fila */
            body.woocommerce-cart .cart_totals {
                display: grid !important;
                grid-template-columns: 1fr auto !important;
                grid-template-rows: auto auto !important;
                grid-template-areas:
                    "header header"
                    "table button" !important;
                gap: 0 32px !important;
                align-items: center !important;
            }
            body.woocommerce-cart .cart_totals h2 {
                grid-area: header !important;
            }
            body.woocommerce-cart .cart_totals > .shop_table,
            body.woocommerce-cart .cart_totals table.shop_table_responsive {
                grid-area: table !important;
                margin: 0 !important;
            }
            body.woocommerce-cart .cart_totals > .wc-proceed-to-checkout {
                grid-area: button !important;
                margin: 0 !important;
                padding: 0 !important;
                white-space: nowrap !important;
            }
        }

        /* ============================================ */
        /* CENTRADO AGRESIVO: el form.checkout entero es 720px centrado */
        /* ============================================ */
        body.woocommerce-checkout form.checkout,
        body.woocommerce-checkout form.woocommerce-checkout {
            max-width: 720px !important;
            margin-left: auto !important;
            margin-right: auto !important;
            float: none !important;
            width: 100% !important;
        }

        /* Single column: todos los wrappers de columnas pasan a 100% */
        body.woocommerce-checkout form.checkout #customer_details,
        body.woocommerce-checkout form.checkout .col2-set,
        body.woocommerce-checkout form.checkout .col2-set > .col-1,
        body.woocommerce-checkout form.checkout .col2-set > .col-2,
        body.woocommerce-checkout form.checkout #customer_details > .col-1,
        body.woocommerce-checkout form.checkout #customer_details > .col-2,
        body.woocommerce-checkout form.checkout .woocommerce-billing-fields,
        body.woocommerce-checkout form.checkout .woocommerce-additional-fields,
        body.woocommerce-checkout form.checkout #order_review,
        body.woocommerce-checkout form.checkout #order_review_heading {
            width: 100% !important;
            max-width: 100% !important;
            float: none !important;
            clear: both !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        /* También centrar el banner de cupón y notices que van fuera del form */
        body.woocommerce-checkout .woocommerce > .woocommerce-info,
        body.woocommerce-checkout .woocommerce > .woocommerce-form-coupon-toggle,
        body.woocommerce-checkout .woocommerce > form.woocommerce-form-coupon,
        body.woocommerce-checkout .woocommerce-notices-wrapper,
        body.woocommerce-checkout .woocommerce-form-coupon-toggle,
        body.woocommerce-checkout .woocommerce-form-coupon {
            max-width: 720px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        /* Wrapper general del wc-checkout también centrado */
        body.woocommerce-checkout .woocommerce {
            max-width: none !important;
        }
        body.woocommerce-checkout .entry-content > .woocommerce,
        body.woocommerce-checkout main .woocommerce {
            max-width: 100% !important;
        }

        /* ============================================ */
        /* MULTI-STEP: ocultar bloques según estado */
        /* ============================================ */
        form.checkout.archi-funnel-step-2 #order_review_heading,
        form.checkout.archi-funnel-step-2 #order_review {
            display: none !important;
        }
        form.checkout.archi-funnel-step-3 .woocommerce-billing-fields,
        form.checkout.archi-funnel-step-3 .woocommerce-additional-fields,
        form.checkout.archi-funnel-step-3 .archi-funnel-continue-wrap {
            display: none !important;
        }
        form.checkout.archi-funnel-step-2 .archi-funnel-back-wrap { display: none !important; }
        form.checkout.archi-funnel-step-3 .archi-funnel-back-wrap { display: block !important; }

        /* ============================================ */
        /* Botón "Continuar al pago" */
        /* ============================================ */
        .archi-funnel-continue-wrap {
            max-width: 640px;
            margin: 32px auto 16px;
            text-align: center;
            padding: 0 16px;
        }
        .archi-funnel-continue {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(95deg, #ff3d7f, #ff8a3d);
            color: white !important;
            border: none;
            padding: 16px 36px;
            border-radius: 999px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.15) inset,
                0 16px 32px -16px rgba(255, 61, 127, 0.5);
            transition: transform 180ms cubic-bezier(0.34, 1.56, 0.64, 1);
            min-width: 280px;
        }
        .archi-funnel-continue:hover { transform: translateY(-2px); }
        .archi-funnel-continue:active { transform: translateY(0); }

        /* ============================================ */
        /* Botón "← Volver a tus datos" */
        /* ============================================ */
        .archi-funnel-back-wrap {
            max-width: 720px;
            margin: 0 auto 16px;
            padding: 0 16px;
            display: none;
            text-align: center;
        }
        .archi-funnel-back {
            background: none;
            border: none;
            color: #a8a29e;
            font-size: 14px;
            cursor: pointer;
            padding: 6px 10px;
            font-family: inherit;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .archi-funnel-back:hover { color: #f5f1ea; }

        /* ============================================ */
        /* Mensaje de error de validación */
        /* ============================================ */
        .archi-funnel-validation-error {
            max-width: 640px;
            margin: 0 auto 12px;
            padding: 12px 16px;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.4);
            border-radius: 10px;
            color: #fca5a5;
            font-size: 14px;
            font-family: inherit;
            display: none;
        }
        .archi-funnel-validation-error.visible { display: block; }

        /* ============================================ */
        /* Mobile - CTA full width */
        /* ============================================ */
        @media (max-width: 640px) {
            .archi-funnel-continue { width: 100%; min-width: 0; }
        }
    </style>
    <?php
}, 6);

// =====================================================================
// JS multi-step UI (idéntico al de la versión anterior)
// =====================================================================
add_action('wp_footer', function () {
    if (!archi_funnel_should_apply()) return;
    if (!function_exists('is_checkout')) return;
    if (!is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay')) return;
    ?>
    <script id="archi-funnel-fase-2-lite-js">
    (function () {
        'use strict';

        function $(sel, ctx) { return (ctx || document).querySelector(sel); }
        function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
        function getForm() { return $('form.checkout, form.woocommerce-checkout'); }
        function getBillingSection() { return $('.woocommerce-billing-fields'); }
        function getAdditionalSection() { return $('.woocommerce-additional-fields'); }

        function updateStepper(step) {
            var dots = $$('.archi-funnel-stepper__dot');
            var lines = $$('.archi-funnel-stepper__line');
            dots.forEach(function (dot, idx) {
                var n = idx + 1;
                dot.classList.remove('pending', 'current', 'completed');
                if (n < step) {
                    dot.classList.add('completed');
                    dot.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                } else if (n === step) {
                    dot.classList.add('current');
                    dot.textContent = String(n);
                } else {
                    dot.classList.add('pending');
                    dot.textContent = String(n);
                }
            });
            lines.forEach(function (line, idx) {
                var n = idx + 1;
                if (n < step) line.classList.add('completed');
                else line.classList.remove('completed');
            });
            var mobile = $('.archi-funnel-stepper__mobile');
            if (mobile) {
                var labels = { 1: 'Ticket', 2: 'Datos', 3: 'Pagar', 4: 'Comprobante', 5: 'Listo' };
                mobile.textContent = 'Paso ' + step + ' de 5 · ' + (labels[step] || '');
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

        function goToStep3() {
            if (!validateBilling()) return;
            var form = getForm();
            if (!form) return;
            form.classList.remove('archi-funnel-step-2');
            form.classList.add('archi-funnel-step-3');
            updateStepper(3);
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
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

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
