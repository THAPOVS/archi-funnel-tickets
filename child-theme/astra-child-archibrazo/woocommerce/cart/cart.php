<?php
/**
 * Cart Page - Archibrazo custom template
 *
 * Sobrescribe `woocommerce/cart/cart.php` con HTML adaptado del prototipo
 * `~/Documents/Claude/Projects/Archibrazo/funnel-tickets-staging/index.html`
 *
 * Mantiene la lógica de WC (cart items, qty, coupon, totals) pero envuelve
 * todo en una estructura HTML que el CSS del child theme puede estilar como
 * cards oscuras con paleta dark + Fraunces/Manrope.
 *
 * @see WC version: 8.x
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart'); ?>

<div class="archi-cart-wrap">

<form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
    <?php do_action('woocommerce_before_cart_table'); ?>

    <?php // Heading lo renderiza el hook woocommerce_before_cart (functions.php),
          // no duplicarlo acá. ?>

    <div class="archi-cart-items">
        <?php do_action('woocommerce_before_cart_contents'); ?>

        <?php
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                ?>
                <article class="archi-cart-item">
                    <a class="archi-cart-item__media archi-cart-item__media--poster" href="<?php echo esc_url($product_permalink); ?>">
                        <?php
                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                        echo $thumbnail;
                        ?>
                    </a>
                    <div class="archi-cart-item__body">
                        <h3 class="archi-cart-item__title">
                            <?php
                            if (!$product_permalink) {
                                echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key) . '&nbsp;');
                            } else {
                                echo wp_kses_post(apply_filters('woocommerce_cart_item_name', sprintf('<a href="%s">%s</a>', esc_url($product_permalink), $_product->get_name()), $cart_item, $cart_item_key));
                            }
                            ?>
                        </h3>
                        <?php
                        // Chips de variante (rebrand 2026): la variante elegida como chip
                        // rosa, en lugar del dl plano de WC.
                        if (!empty($cart_item['variation']) && is_array($cart_item['variation'])) {
                            echo '<div class="archi-chips">';
                            foreach ($cart_item['variation'] as $attr_value) {
                                if ($attr_value === '') continue;
                                echo '<span class="archi-chip archi-chip--pink">' . esc_html($attr_value) . '</span>';
                            }
                            echo '</div>';
                        } else {
                            echo wc_get_formatted_cart_item_data($cart_item);
                        }
                        ?>

                        <div class="archi-cart-item__footer">
                            <div class="archi-cart-item__qty">
                                <?php
                                if ($_product->is_sold_individually()) {
                                    $product_quantity = sprintf('1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key);
                                } else {
                                    $product_quantity = woocommerce_quantity_input(array(
                                        'input_name'  => "cart[{$cart_item_key}][qty]",
                                        'input_value' => $cart_item['quantity'],
                                        'max_value'   => $_product->get_max_purchase_quantity(),
                                        'min_value'   => '0',
                                        'product_name' => $_product->get_name(),
                                    ), $_product, false);
                                }
                                echo apply_filters('woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item);
                                ?>
                            </div>
                            <div class="archi-cart-item__subtotal">
                                <span class="archi-cart-item__subtotal-label">Subtotal</span>
                                <span class="archi-cart-item__subtotal-value">
                                    <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="archi-cart-item__remove">
                        <?php
                        echo apply_filters(
                            'woocommerce_cart_item_remove_link',
                            sprintf('<a href="%s" class="archi-cart-item__remove-btn" aria-label="%s" data-product_id="%s" data-product_sku="%s">×</a>',
                                esc_url(wc_get_cart_remove_url($cart_item_key)),
                                esc_attr__('Quitar este item', 'astra-child-archibrazo'),
                                esc_attr($product_id),
                                esc_attr($_product->get_sku())
                            ),
                            $cart_item_key
                        );
                        ?>
                    </div>
                </article>
                <?php
            }
        }
        ?>

        <?php do_action('woocommerce_cart_contents'); ?>

        <input type="hidden" name="apply_coupon" value="1" />
        <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>

        <?php do_action('woocommerce_after_cart_contents'); ?>
    </div>

    <!-- Bloque de cupón retirado del paso 1: el checkout ya tiene su propio campo
         de cupón, mostrarlo también acá es redundante (feedback de Alex, 2026-05-20). -->

    <!-- Botón "actualizar carrito" oculto pero presente para WC AJAX -->
    <button type="submit" class="archi-cart-update-hidden" name="update_cart" value="<?php esc_attr_e('Actualizar carrito', 'woocommerce'); ?>" style="display:none"><?php esc_html_e('Actualizar carrito', 'woocommerce'); ?></button>

    <?php do_action('woocommerce_after_cart_table'); ?>
</form>

<?php do_action('woocommerce_before_cart_collaterals'); ?>

<aside class="cart-collaterals archi-cart-summary">
    <?php
    /**
     * Cart collaterals hook.
     */
    do_action('woocommerce_cart_collaterals');
    ?>
</aside>

</div><!-- /.archi-cart-wrap -->

<?php do_action('woocommerce_after_cart'); ?>
