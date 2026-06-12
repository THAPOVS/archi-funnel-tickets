<?php
/**
 * Proceed to checkout button - Archibrazo custom template
 *
 * Texto del CTA del paso 1 alineado al design handoff 2026: "Continuar →"
 * (el default de WC decía "Finalizar compra" / "Realizar compra").
 *
 * @see woocommerce/templates/cart/proceed-to-checkout-button.php
 */

defined('ABSPATH') || exit;
?>
<a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="checkout-button button alt wc-forward<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>">
    <?php esc_html_e('Continuar', 'astra-child-archibrazo'); ?>
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
</a>
