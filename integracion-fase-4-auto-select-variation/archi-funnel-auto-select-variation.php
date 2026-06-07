<?php
/**
 * Archibrazo - Auto-seleccionar variante segun stock
 * ===================================================
 *
 * Fix UX urgente identificado el 2026-06-06 (dia del show GUARDIANES).
 *
 * EL BUG
 * ------
 * En productos con variantes, WooCommerce NO selecciona ninguna opcion al
 * cargar. El dropdown muestra el nombre del atributo como placeholder, pero
 * su `value` esta vacio, asi que el boton "Reserva" queda con la clase
 * `wc-variation-selection-needed` (opacity 0.5, no responde al click).
 *
 * El cliente cree que la variante ya esta seleccionada (porque ve el nombre
 * en el dropdown), intenta apretar Reserva 2-3 veces, y se va. Perdida
 * directa de ventas. Confirmado al menos en 2 funciones (5/6 y 6/6 de 2026).
 *
 * COMO LO RESOLVEMOS
 * ------------------
 * Al cargar la pagina, miramos el JSON de `data-product_variations` (que WC
 * embebe en el form) y:
 *
 *   1. Filtramos solo las variantes con `is_in_stock = true` Y `is_purchasable
 *      = true`. (Las out-of-stock / no comprables se descartan: no tiene sentido
 *      auto-seleccionar algo que no se puede vender.)
 *
 *   2. Si queda una sola, aplicamos sus atributos a cada select, disparamos
 *      `change`, y WC habilita el boton.
 *
 *   3. Si quedan varias (ej. Anticipada + Puerta ambas disponibles), el cliente
 *      tiene que elegir conscientemente. No tocamos nada.
 *
 *   4. Si quedan cero, el producto esta agotado de verdad y el boton sigue
 *      deshabilitado, como corresponde.
 *
 * Esto cubre los 2 casos del bug:
 *   - Producto con 1 sola variante total (la mayoria de shows arrancan asi)
 *   - Producto con N variantes pero 1 sola in_stock (caso GUARDIANES 6/6:
 *     "Anticipada Online" disponible + "Entradas" out_of_stock)
 *
 * INSTALACION
 * -----------
 *   1. WP admin -> WPCode -> Add Snippet -> PHP Snippet
 *   2. Title: "Archi Funnel - Auto-select variation according to stock"
 *   3. Pegar este codigo
 *   4. Insertion: Auto Insert -> Frontend Only
 *   5. Save & Activate
 *   6. Refrescar archibrazo.org/tickets/showdemusica/ (o cualquier producto):
 *      el boton Reserva tiene que estar habilitado sin tocar nada cuando
 *      hay 1 sola variante vendible.
 *
 * ROLLBACK: WPCode -> Deactivate.
 */

if (!defined('ABSPATH')) return;

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

            // Leer las variantes embebidas (las pone WC en data-product_variations)
            var raw = form.getAttribute('data-product_variations');
            if (!raw) return;

            var variations;
            try { variations = JSON.parse(raw); }
            catch (e) { return; }
            if (!Array.isArray(variations) || !variations.length) return;

            // Filtrar solo las realmente vendibles
            var sellable = variations.filter(function (v) {
                return v && v.is_in_stock && v.is_purchasable;
            });

            // Si hay 0 o 2+, el cliente decide (o el producto esta agotado)
            if (sellable.length !== 1) return;

            var target = sellable[0];
            if (!target.attributes || typeof target.attributes !== 'object') return;

            var selects = form.querySelectorAll('select[name^="attribute_"]');
            if (!selects.length) return;

            var changed = false;
            selects.forEach(function (select) {
                if (select.value) return; // ya esta elegido

                // Match: el name del select es `attribute_pa_tipo` o similar.
                // Las keys de `target.attributes` son `attribute_pa_tipo`.
                var key = select.name;
                var val = target.attributes[key];
                if (!val) return;

                // Verificar que la opcion exista en este select
                var opt = Array.prototype.find.call(select.options, function (o) {
                    return o.value === val;
                });
                if (!opt) return;

                select.value = val;
                changed = true;
            });

            if (!changed) return;

            // Disparar change para que WC variations.js calcule precio, stock,
            // imagen, y habilite el boton sacandole la clase wc-variation-selection-needed.
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

        // Por si el form se renderiza tarde (ajax, royal addons, etc).
        // Tambien re-intentamos en eventos de WC para cubrir el caso de
        // updates parciales del DOM.
        if (window.jQuery) {
            window.jQuery(document).on('wc_variation_form found_variation reset_data', function () {
                setTimeout(autoSelectVariation, 50);
            });
        }
    })();
    </script>
    <?php
}, 99);
