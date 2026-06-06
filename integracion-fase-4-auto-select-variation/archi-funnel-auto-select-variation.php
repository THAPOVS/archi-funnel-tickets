<?php
/**
 * Archibrazo - Auto-seleccionar variante unica
 * =============================================
 *
 * Fix UX urgente (2026-06-06): cuando un producto WC tiene una sola variante
 * disponible, WooCommerce NO la auto-selecciona. El dropdown muestra el nombre
 * de la variante como placeholder, pero su `value` esta vacio, asi que el
 * boton "Reserva" queda deshabilitado (clase `wc-variation-selection-needed`,
 * opacity 0.5). El cliente cree que ya esta seleccionada, intenta apretar y no
 * pasa nada. Perdida directa de ventas.
 *
 * Este snippet detecta el form de variations al cargar y, si hay UNA SOLA
 * opcion real en cada select de atributo, la elige automaticamente y dispara
 * el `change` para que WC habilite el boton.
 *
 * Si hay 2 o mas opciones reales (ej. Anticipada + Puerta), no toca nada:
 * el cliente tiene que elegir conscientemente.
 *
 * INSTALACION
 * -----------
 *   1. WP admin -> Code Snippets / WPCode -> Add Snippet -> PHP Snippet
 *   2. Title: "Archi Funnel - Auto-select single variation"
 *   3. Pegar este codigo
 *   4. Insertion: Auto Insert -> Frontend Only (o Run Everywhere)
 *   5. Save & Activate
 *   6. Refrescar archibrazo.org/tickets/showdemusica/ -> el boton Reserva
 *      ahora deberia estar habilitado sin tocar nada.
 *
 * ROLLBACK: WPCode -> este snippet -> Deactivate.
 */

if (!defined('ABSPATH')) return;

add_action('wp_footer', function () {
    if (!function_exists('is_product')) return;
    if (!is_product()) return;
    ?>
    <script id="archi-auto-select-variation">
    (function () {
        'use strict';

        function autoSelectSingleVariation() {
            var form = document.querySelector('form.variations_form');
            if (!form) return;

            var selects = form.querySelectorAll('select[name^="attribute_"]');
            if (!selects.length) return;

            var changed = false;
            selects.forEach(function (select) {
                // ignorar si ya tiene value seteado
                if (select.value) return;

                // contar opciones reales (no las vacias / placeholders)
                var realOptions = Array.prototype.filter.call(
                    select.options,
                    function (opt) { return opt.value && opt.value.length > 0; }
                );

                if (realOptions.length === 1) {
                    select.value = realOptions[0].value;
                    changed = true;
                }
            });

            if (changed) {
                // jQuery trigger para que WC variations.js reaccione
                if (window.jQuery) {
                    window.jQuery(form).find('select[name^="attribute_"]').trigger('change');
                } else {
                    selects.forEach(function (s) {
                        s.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', autoSelectSingleVariation);
        } else {
            autoSelectSingleVariation();
        }

        // por si el form se renderiza tarde (ajax, royal addons, etc)
        if (window.jQuery) {
            window.jQuery(document).on('wc_variation_form found_variation reset_data', function () {
                setTimeout(autoSelectSingleVariation, 50);
            });
        }
    })();
    </script>
    <?php
}, 99);
