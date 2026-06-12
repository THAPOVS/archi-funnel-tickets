<?php
/**
 * Thankyou Page - Archibrazo custom template
 *
 * Sobrescribe `woocommerce/checkout/thankyou.php` con HTML adaptado del paso 5
 * del prototipo. Renderea diferente según el estado de la orden.
 */

defined('ABSPATH') || exit;

// Dispara hook que renderea el stepper visual (sino no aparece en thank-you)
if ($order) {
    do_action('woocommerce_before_thankyou', $order->get_id());
}
?>

<div class="archi-thankyou">

<?php if ($order) : ?>
    <?php
    $status = $order->get_status();
    $email = $order->get_billing_email();
    $first_name = $order->get_billing_first_name();
    $order_id = $order->get_id();
    $total = $order->get_formatted_order_total();
    $payment_method = $order->get_payment_method_title();
    ?>

    <?php if ($order->has_status('failed')) : ?>
        <div class="archi-thankyou__hero archi-thankyou__hero--error">
            <p class="archi-eyebrow archi-eyebrow--red">Hubo un problema</p>
            <h1 class="archi-thankyou__title">No pudimos procesar tu pedido</h1>
            <p class="archi-thankyou__sub"><?php esc_html_e('Probá pagar de nuevo o escribinos a boleteria@archibrazo.org', 'astra-child-archibrazo'); ?></p>
            <p class="archi-thankyou__actions">
                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="archi-btn archi-btn--primary"><?php esc_html_e('Pagar de nuevo', 'woocommerce'); ?></a>
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="archi-btn archi-btn--secondary"><?php esc_html_e('Ir a mi cuenta', 'woocommerce'); ?></a>
                <?php endif; ?>
            </p>
        </div>
    <?php else : ?>

        <?php
        // Regla simple:
        //   - AMARILLO (warning) → pendiente de subir comprobante (receipt-upload, pending, processing, on-hold)
        //   - VERDE (success)   → comprobante subido (receipt-approval, completed)
        //   - ROJO (error)      → rechazado (receipt-rejected)
        $comprobante_subido = in_array($status, ['receipt-approval', 'completed'], true);
        $rechazado = ($status === 'receipt-rejected');
        ?>

        <?php if (!$comprobante_subido && !$rechazado) : ?>
            <!-- AMARILLO: pendiente de subir comprobante -->
            <div class="archi-thankyou__hero archi-thankyou__hero--warning">
                <div class="archi-thankyou__icon archi-thankyou__icon--warning" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <p class="archi-eyebrow archi-eyebrow--yellow">Último paso · falta un detalle</p>
                <h1 class="archi-thankyou__title">Tu pedido está creado pero falta el comprobante</h1>
                <p class="archi-thankyou__sub">Subí abajo el comprobante de la transferencia. Sin ese paso no se genera el ticket.</p>
            </div>
        <?php elseif ($status === 'receipt-approval' || $status === 'completed') : ?>
            <!-- VERDE: comprobante subido (ambos estados son "listo" desde la vista del cliente).
                 El 'completed' es interno nuestro (cuando la automatización valida y emite el ticket),
                 pero al cliente le decimos lo mismo en ambos casos: "te llega en 24/48hs". -->
            <div class="archi-thankyou__hero archi-thankyou__hero--success">
                <div class="archi-thankyou__icon archi-thankyou__icon--success" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="archi-eyebrow archi-eyebrow--green">Recibimos tu comprobante</p>
                <h1 class="archi-thankyou__title">Gracias<?php echo $first_name ? ', ' . esc_html($first_name) : ''; ?></h1>
                <p class="archi-thankyou__sub">Estamos verificando tu pago. Apenas lo confirmemos, te mandamos el ticket a <strong><?php echo esc_html($email); ?></strong>.</p>
            </div>
        <?php elseif ($status === 'receipt-rejected') : ?>
            <div class="archi-thankyou__hero archi-thankyou__hero--error">
                <div class="archi-thankyou__icon archi-thankyou__icon--error" aria-hidden="true">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <p class="archi-eyebrow archi-eyebrow--red">Comprobante rechazado</p>
                <h1 class="archi-thankyou__title">Hubo un problema con tu comprobante</h1>
                <p class="archi-thankyou__sub">Escribinos a <a href="mailto:boleteria@archibrazo.org">boleteria@archibrazo.org</a> con el comprobante real y lo resolvemos.</p>
            </div>
        <?php else : ?>
            <div class="archi-thankyou__hero">
                <h1 class="archi-thankyou__title">Gracias. Tu pedido ha sido recibido.</h1>
            </div>
        <?php endif; ?>

        <?php
        // PRIMERO (visual): placeholder vacío donde el JS va a mover el form de PeproDev.
        // PeproDev renderea el form DENTRO de order/order-details.php (no en woocommerce_thankyou
        // directo), por eso lo dejamos vacío y un JS abajo lo mueve hacia acá apenas el DOM carga.
        $estados_necesita_upload = ['receipt-upload', 'on-hold', 'pending', 'processing'];
        if (in_array($status, $estados_necesita_upload, true)) {
            ?>
            <div class="archi-thankyou__upload-wrap" id="subir-comprobante">
                <div class="archi-thankyou__upload" data-archi-upload-target></div>

                <?php // Mini-resumen del pedido (design handoff: "TU PEDIDO" dentro de la card) ?>
                <div class="archi-fminipedido">
                    <h4>Tu pedido</h4>
                    <?php foreach ($order->get_items() as $item) : ?>
                        <div class="archi-fminipedido__row">
                            <span><?php echo esc_html($item->get_name()) . ' × ' . (int) $item->get_quantity(); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="archi-fminipedido__row archi-fminipedido__row--total">
                        <span>Total a transferir</span>
                        <data><?php echo wp_kses_post($order->get_formatted_order_total()); ?></data>
                    </div>
                </div>

                <?php // Volver al QR: con el carrito persistente, el checkout reabre en el paso 3 ?>
                <p class="archi-thankyou__backqr">
                    <a href="<?php echo esc_url(add_query_arg('archi_step', '3', wc_get_checkout_url())); ?>">← Volver al QR de pago</a>
                </p>
            </div>
            <?php
        }
        ?>

        <?php if ($status === 'receipt-approval' || $status === 'completed') : ?>
        <div class="archi-verif">
            <div class="archi-verif__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></svg>
            </div>
            <div>
                <b>Verificación manual</b>
                <small>Estamos revisando tu comprobante a mano. Te avisamos por mail apenas esté confirmado.</small>
            </div>
        </div>

        <div class="archi-thankyou__card archi-thankyou__card--eta">
            <div class="archi-thankyou__card-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="archi-thankyou__card-body">
                <p class="archi-thankyou__card-title">Te llega el ticket por mail</p>
                <p class="archi-thankyou__card-sub">Te mandamos los tickets a <strong><?php echo esc_html($email); ?></strong> dentro de las próximas 24/48 horas.</p>
                <p class="archi-thankyou__card-sub">Nuestros ingenieros de sistemas ya tienen tus datos 😸</p>
            </div>
        </div>

        <?php
        // Card resumen del pedido (design handoff): ítem con póster + chips
        // + tabla Pedido / Fecha del show / Total pagado.
        $resumen_items = $order->get_items();
        $primer_item   = reset($resumen_items);
        $show_date_str = '';
        if ($primer_item) {
            $pid = $primer_item->get_product_id();
            $foo_date = get_post_meta($pid, 'WooCommerceEventsDate', true);
            $foo_hour = get_post_meta($pid, 'WooCommerceEventsHour', true);
            $foo_min  = get_post_meta($pid, 'WooCommerceEventsMinutes', true);
            if ($foo_date) {
                $show_date_str = $foo_date;
                if ($foo_hour !== '' && $foo_hour !== null) {
                    $show_date_str .= ' — ' . sprintf('%02d:%02d', (int) $foo_hour, (int) ($foo_min !== '' ? $foo_min : 0)) . ' h';
                }
            }
        }
        ?>
        <div class="archi-thankyou__resumen">
            <?php if ($primer_item) :
                $rp = $primer_item->get_product();
                $variation_names = array();
                if ($primer_item->get_variation_id()) {
                    $vp = wc_get_product($primer_item->get_variation_id());
                    if ($vp) { $variation_names = array_values($vp->get_attributes()); }
                }
                ?>
                <div class="archi-thankyou__resumen-item">
                    <?php if ($rp) : ?>
                        <div class="archi-thankyou__resumen-poster"><?php echo $rp->get_image('woocommerce_thumbnail'); ?></div>
                    <?php endif; ?>
                    <div class="archi-thankyou__resumen-main">
                        <h3><?php echo esc_html($primer_item->get_name()); ?></h3>
                        <p><?php echo (int) $primer_item->get_quantity(); ?> × entrada<?php echo $show_date_str ? ' · ' . esc_html($show_date_str) : ''; ?></p>
                        <?php if (!empty($variation_names)) : ?>
                            <div class="archi-chips">
                                <?php foreach ($variation_names as $vn) : if (!is_string($vn) || $vn === '') continue; ?>
                                    <span class="archi-chip archi-chip--pink"><?php echo esc_html($vn); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="archi-thankyou__resumen-rows">
                <div class="archi-thankyou__resumen-row">
                    <span>Pedido</span>
                    <data>#<?php echo esc_html($order->get_order_number()); ?></data>
                </div>
                <?php if ($show_date_str) : ?>
                <div class="archi-thankyou__resumen-row">
                    <span>Fecha del show</span>
                    <data><?php echo esc_html($show_date_str); ?></data>
                </div>
                <?php endif; ?>
                <div class="archi-thankyou__resumen-row archi-thankyou__resumen-row--total">
                    <span>Total pagado</span>
                    <data><?php echo wp_kses_post($order->get_formatted_order_total()); ?></data>
                </div>
            </div>
        </div>

        <img src="https://www.archibrazo.org/wp-content/uploads/2026/05/gato-archibrazo.gif"
             alt="Gatito"
             width="380" height="264"
             class="archi-thankyou__gif" loading="lazy" decoding="async">

        <!-- "Mientras tanto" va justo debajo del GIF (feedback de Joaco, 2026-05-20). -->
        <div class="archi-thankyou__more">
            <p class="archi-thankyou__more-title">Mientras tanto</p>
            <p class="archi-thankyou__more-sub">Te esperamos en Mario Bravo 441, Almagro. Caé tipo media hora antes del evento, tomá o comé algo en nuestro bar, así vivís el Archi desde que entrás.</p>
            <div class="archi-thankyou__more-actions">
                <a href="<?php echo esc_url(home_url('/eventos/')); ?>" class="archi-btn archi-btn--primary">Ver agenda completa →</a>
                <a href="https://www.google.com/maps/place/El+Archibrazo/@-34.6048636,-58.421484,17z/data=!3m1!4b1!4m6!3m5!1s0x95bcca8a7c0dca87:0x1154aae4e74548ca!8m2!3d-34.6048681!4d-58.4168706!16s%2Fg%2F11bbrm210t" target="_blank" rel="noopener" class="archi-btn archi-btn--primary">Cómo llegar 🗺️</a>
            </div>
        </div>

        <p class="archi-marginalia"><span class="archi-dingbat">✻</span> <b>Fig. V</b> fin del fascículo — la fiesta sigue en la sala</p>

        <!-- Confeti al abrir el paso final (feedback Alex: festejemos el último paso) -->
        <script id="archi-thankyou-confetti">
        (function () {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (window.__archiConfettiFired) return;
            window.__archiConfettiFired = true;
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
            s.onload = function () {
                if (typeof confetti !== 'function') return;
                var colors = ['#f472b6', '#fb923c', '#22c55e', '#fde68a', '#f5f1ea'];
                confetti({ particleCount: 90, spread: 100, startVelocity: 45, origin: { y: 0.45 }, colors: colors });
                var end = Date.now() + 900;
                (function frame() {
                    confetti({ particleCount: 5, angle: 60, spread: 75, startVelocity: 55, origin: { x: 0, y: 0.7 }, colors: colors });
                    confetti({ particleCount: 5, angle: 120, spread: 75, startVelocity: 55, origin: { x: 1, y: 0.7 }, colors: colors });
                    if (Date.now() < end) requestAnimationFrame(frame);
                })();
            };
            document.head.appendChild(s);
        })();
        </script>
        <?php endif; ?>

        <!-- Pedido/Fecha/Email: se renderean DEBAJO de la tabla "Detalles del pedido"
             via el hook woocommerce_order_details_after_order_table (functions.php sección 8c). -->

        <?php
        // DESPUÉS DEL RESUMEN: emitir 'woocommerce_thankyou' que renderea los Detalles del pedido +
        // Dirección de facturación (y también el form de PeproDev embebido en order-details, que
        // el JS mueve arriba al upload-wrap). Es el flow estándar de WC.
        do_action('woocommerce_thankyou', $order->get_id());
        ?>

        <?php if ($comprobante_subido) : ?>
        <!-- Cierre del paso "Listo": link al histórico de pedidos del cliente. -->
        <div class="archi-thankyou__closing">
            <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="archi-thankyou__closing-link">Ver todos mis pedidos →</a>
        </div>
        <?php endif; ?>

    <?php endif; ?>

<?php else : ?>
    <div class="archi-thankyou__hero">
        <h1 class="archi-thankyou__title"><?php esc_html_e('Gracias. Tu pedido ha sido recibido.', 'woocommerce'); ?></h1>
    </div>
<?php endif; ?>

</div><!-- /.archi-thankyou -->
