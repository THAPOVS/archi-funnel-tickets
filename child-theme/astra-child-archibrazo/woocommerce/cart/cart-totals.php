<?php
/**
 * Cart Totals - Archibrazo custom template
 *
 * Sobrescribe `woocommerce/cart/cart-totals.php` con layout horizontal en
 * desktop: header arriba, tabla de totales a la izquierda, botón "Finalizar
 * compra" a la derecha. En mobile colapsa a una sola columna.
 *
 * @see WC version: 8.x
 */

defined('ABSPATH') || exit;
?>
<div class="archi-totals">
    <header class="archi-totals__header">
        <h2 class="archi-totals__title">Totales del carrito</h2>
    </header>

    <div class="archi-totals__body">
        <table class="archi-totals__table" role="presentation">
            <tbody>
                <tr class="archi-totals__row archi-totals__row--subtotal">
                    <th>Subtotal</th>
                    <td><?php wc_cart_totals_subtotal_html(); ?></td>
                </tr>

                <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
                    <tr class="archi-totals__row archi-totals__row--coupon coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
                        <th><?php wc_cart_totals_coupon_label($coupon); ?></th>
                        <td><?php wc_cart_totals_coupon_html($coupon); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach (WC()->cart->get_fees() as $fee) : ?>
                    <tr class="archi-totals__row archi-totals__row--fee fee">
                        <th>
                            <span class="archi-tipwrap"><?php echo esc_html($fee->name); ?>
                                <span class="archi-tip" tabindex="0">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                                    <span class="archi-tip__bubble">10% que sostiene el sistema de tickets, la barra y la cooperativa.</span>
                                </span>
                            </span>
                        </th>
                        <td><?php wc_cart_totals_fee_html($fee); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php
                // Tooltip del cargo del servicio (rebrand 2026): el "Coste del Servicio"
                // es una tax rate de WC, se renderiza en este bloque.
                $archi_tip_servicio = '<span class="archi-tip" tabindex="0">'
                    . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>'
                    . '<span class="archi-tip__bubble">10% que sostiene el sistema de tickets, la barra y la cooperativa.</span>'
                    . '</span>';
                ?>
                <?php if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) :
                    $taxable_address = WC()->customer->get_taxable_address();
                    $estimated_text  = '';
                    if (WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping()) {
                        $estimated_text = sprintf(' <small>' . esc_html__('(estimated for %s)', 'woocommerce') . '</small>', WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]);
                    }
                    if ('itemized' === get_option('woocommerce_tax_total_display')) {
                        foreach (WC()->cart->get_tax_totals() as $code => $tax) : ?>
                            <tr class="archi-totals__row archi-totals__row--tax tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                                <th><span class="archi-tipwrap"><?php echo esc_html($tax->label) . $estimated_text . $archi_tip_servicio; ?></span></th>
                                <td><?php echo wp_kses_post($tax->formatted_amount); ?></td>
                            </tr>
                        <?php endforeach;
                    } else { ?>
                        <tr class="archi-totals__row archi-totals__row--tax tax-total">
                            <th><span class="archi-tipwrap"><?php echo esc_html(WC()->countries->tax_or_vat()) . $estimated_text . $archi_tip_servicio; ?></span></th>
                            <td><?php wc_cart_totals_taxes_total_html(); ?></td>
                        </tr>
                    <?php } endif; ?>

                <?php do_action('woocommerce_cart_totals_before_order_total'); ?>

                <tr class="archi-totals__row archi-totals__row--total order-total">
                    <th>Total</th>
                    <td><?php wc_cart_totals_order_total_html(); ?></td>
                </tr>

                <?php do_action('woocommerce_cart_totals_after_order_total'); ?>
            </tbody>
        </table>
    </div>

    <div class="archi-totals__action wc-proceed-to-checkout">
        <?php do_action('woocommerce_proceed_to_checkout'); ?>
    </div>
</div>

<?php do_action('woocommerce_after_cart_totals'); ?>
