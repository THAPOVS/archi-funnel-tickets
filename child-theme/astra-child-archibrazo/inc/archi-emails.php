<?php
/**
 * Archi Emails - Automatización de mails del funnel de tickets.
 *
 * Mails que maneja este módulo (5):
 *   1. te-falta    - tiempo  - reserva sin comprobante hace +3h
 *   2. cancelada   - tiempo  - reserva sin comprobante hace +2 días (+ cancela la orden)
 *   3. recibimos   - evento  - colchón a +5 min de subir el comprobante
 *   5. rechazado   - evento  - la orden pasa a receipt-rejected
 *   6. post-evento - tiempo  - lunes posterior al evento
 *
 * El mail 4 "Tu entrada está lista" NO vive acá: FooEvents engancha el ticket al
 * mail de WooCommerce "Pedido completado", así que ese se restylea aparte.
 *
 * MODO (constante ARCHI_EMAILS_MODE en functions.php):
 *   'off'  - no hace nada.
 *   'test' - los mails de evento van SOLO a la casilla de prueba; los crons de
 *            scan NO corren. Para probar los de tiempo: ?archi_test=...&order=ID
 *   'live' - manda a los clientes y los crons escanean órdenes reales.
 */

if (!defined('ABSPATH')) return;

// --- Config con defaults (ARCHI_EMAILS_MODE se define en functions.php) ---
if (!defined('ARCHI_EMAILS_TEST_ADDR')) define('ARCHI_EMAILS_TEST_ADDR', 'boleteria@archibrazo.org');
if (!defined('ARCHI_EMAILS_FROM'))      define('ARCHI_EMAILS_FROM', 'El Archibrazo <boleteria@archibrazo.org>');
if (!defined('ARCHI_FALTA_DELAY'))      define('ARCHI_FALTA_DELAY', 5 * MINUTE_IN_SECONDS);
if (!defined('ARCHI_CANCELADA_DELAY'))  define('ARCHI_CANCELADA_DELAY', 2 * DAY_IN_SECONDS);
if (!defined('ARCHI_COLCHON_DELAY'))    define('ARCHI_COLCHON_DELAY', 5 * MINUTE_IN_SECONDS);
if (!defined('ARCHI_RESENA_URL'))       define('ARCHI_RESENA_URL', 'https://www.google.com/maps/place/El+Archibrazo/@-34.6048636,-58.421484,17z/data=!3m1!4b1!4m6!3m5!1s0x95bcca8a7c0dca87:0x1154aae4e74548ca!8m2!3d-34.6048681!4d-58.4168706!16s%2Fg%2F11bbrm210t');

/**
 * Modo actual, validado. Si la constante falta o es inválida -> 'off'.
 */
function archi_emails_mode() {
    $m = defined('ARCHI_EMAILS_MODE') ? ARCHI_EMAILS_MODE : 'off';
    return in_array($m, array('off', 'test', 'live'), true) ? $m : 'off';
}

// =====================================================================
// RENDER + ENVÍO
// =====================================================================

/**
 * Carga un template de /emails/<nombre>.html y reemplaza los tokens {{CLAVE}}.
 */
function archi_email_render($template, $vars) {
    $path = get_stylesheet_directory() . '/emails/' . $template . '.html';
    if (!file_exists($path)) return '';
    $html = file_get_contents($path);
    if ($html === false) return '';
    foreach ($vars as $k => $v) {
        $html = str_replace('{{' . $k . '}}', $v, $html);
    }
    return $html;
}

/**
 * Variables base que usan todos los templates.
 */
function archi_email_base_vars($order) {
    $nombre = trim($order->get_billing_first_name());
    return array(
        'NOMBRE' => esc_html($nombre !== '' ? $nombre : 'qué tal'),
        'PEDIDO' => esc_html($order->get_order_number()),
    );
}

/**
 * Total de la orden como texto plano (ej. "$8.000").
 */
function archi_email_price($amount) {
    return trim(html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8'));
}

/**
 * Envía un mail. En modo 'test' redirige a la casilla de prueba con prefijo [TEST].
 * Devuelve true/false. Deja una nota en la orden.
 */
function archi_email_send($order, $template, $subject, $vars) {
    $mode = archi_emails_mode();
    if ($mode === 'off') return false;

    // $GLOBALS['archi_emails_force_test'] lo setea el trigger manual ?archi_test=...
    // para forzar redirect aun cuando el modo global esté en 'live'.
    $force_test = !empty($GLOBALS['archi_emails_force_test']);
    $to = $order->get_billing_email();
    if ($mode === 'test' || $force_test) {
        $to      = ARCHI_EMAILS_TEST_ADDR;
        $subject = '[TEST] ' . $subject;
    }
    if (!$to || !is_email($to)) return false;

    $html = archi_email_render($template, $vars);
    if ($html === '') return false;

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ARCHI_EMAILS_FROM,
    );
    $sent = wp_mail($to, $subject, $html, $headers);

    if (is_callable(array($order, 'add_order_note'))) {
        $order->add_order_note('Archi mail [' . $mode . ']: "' . $subject . '" -> ' . $to . ' (' . ($sent ? 'ok' : 'FALLO') . ')');
    }
    return $sent;
}

// =====================================================================
// LOS 5 MAILS
// =====================================================================

function archi_send_te_falta($order) {
    $vars = archi_email_base_vars($order);
    $vars['MONTO']      = archi_email_price($order->get_total());
    $vars['UPLOAD_URL'] = esc_url($order->get_checkout_order_received_url());
    return archi_email_send($order, 'te-falta', 'Te falta un paso para tu reserva en el Archibrazo', $vars);
}

function archi_send_cancelada($order) {
    return archi_email_send($order, 'cancelada', 'Tu reserva en el Archibrazo se canceló', archi_email_base_vars($order));
}

function archi_send_recibimos($order) {
    return archi_email_send($order, 'recibimos', 'Recibimos tu comprobante', archi_email_base_vars($order));
}

function archi_send_rechazado($order) {
    return archi_email_send($order, 'rechazado', 'Hubo un problema con tu comprobante', archi_email_base_vars($order));
}

function archi_send_postevento($order) {
    $vars = archi_email_base_vars($order);
    $vars['RESENA_URL'] = ARCHI_RESENA_URL;
    return archi_email_send($order, 'post-evento', '¿Cómo la pasaste en el Archibrazo?', $vars);
}

// =====================================================================
// MAILS DE EVENTO (cambio de estado de la orden)
// =====================================================================

/**
 * Mail 5 (rechazado) cuando la orden pasa a receipt-rejected.
 * Mail 3 (colchón): al subir el comprobante (receipt-approval) se programa un
 * chequeo a +5 min.
 */
function archi_emails_status_changed($order_id, $old_status, $new_status, $order) {
    if (archi_emails_mode() === 'off') return;
    if (!is_a($order, 'WC_Order')) return;
    if ($order->get_meta('_bot_silent_close')) return;

    if ($new_status === 'receipt-rejected') {
        if (!$order->get_meta('_archi_rechazado_enviado')) {
            archi_send_rechazado($order);
            $order->update_meta_data('_archi_rechazado_enviado', current_time('mysql'));
            $order->save();
        }
        return;
    }

    if ($new_status === 'receipt-approval') {
        $args = array((int) $order_id);
        if (!wp_next_scheduled('archi_email_colchon_check', $args)) {
            wp_schedule_single_event(time() + ARCHI_COLCHON_DELAY, 'archi_email_colchon_check', $args);
        }
    }
}
add_action('woocommerce_order_status_changed', 'archi_emails_status_changed', 20, 4);

/**
 * Mail 3 (colchón): corre 5 min después de subir el comprobante.
 * Solo manda si la orden SIGUE en receipt-approval (es decir: todavía no se
 * aprobó ni se rechazó). Si ya se resolvió, no hace falta.
 */
function archi_emails_colchon_check($order_id) {
    if (archi_emails_mode() === 'off') return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    if ($order->get_meta('_bot_silent_close')) return;
    if ($order->get_meta('_archi_recibido_enviado')) return;
    if ($order->get_status() !== 'receipt-approval') return;

    archi_send_recibimos($order);
    $order->update_meta_data('_archi_recibido_enviado', current_time('mysql'));
    $order->save();
}
add_action('archi_email_colchon_check', 'archi_emails_colchon_check', 10, 1);

// =====================================================================
// MAILS DE TIEMPO (cron) - solo corren en modo 'live'
// =====================================================================

/**
 * Marca de go-live: la primera corrida en modo live registra el momento.
 * El cron solo procesa órdenes creadas DESPUÉS de esa marca, para no tocar
 * el backlog de órdenes viejas.
 */
function archi_emails_golive_ts() {
    $ts = (int) get_option('archi_emails_golive', 0);
    if (!$ts) {
        $ts = time();
        update_option('archi_emails_golive', $ts);
    }
    return $ts;
}

/**
 * Cron horario: mail 1 (te-falta) y mail 2 (cancelada).
 */
function archi_emails_cron_hourly() {
    if (archi_emails_mode() !== 'live') return;
    if (!function_exists('wc_get_orders')) return;

    $golive = archi_emails_golive_ts();
    $now    = time();

    $orders = wc_get_orders(array(
        'status'  => 'receipt-upload',
        'limit'   => 100,
        'orderby' => 'date',
        'order'   => 'ASC',
    ));
    if (empty($orders)) return;

    foreach ($orders as $order) {
        if ($order->get_meta('_bot_silent_close')) continue;
        $created = $order->get_date_created();
        if (!$created) continue;
        $created_ts = $created->getTimestamp();
        if ($created_ts < $golive) continue; // backlog pre go-live

        $age = $now - $created_ts;

        // Mail 2 (cancelada): +2 días. Se chequea primero (umbral más viejo).
        if ($age >= ARCHI_CANCELADA_DELAY) {
            if (!$order->get_meta('_archi_cancelada_enviado')) {
                archi_send_cancelada($order);
                $order->update_meta_data('_archi_cancelada_enviado', current_time('mysql'));
                $order->update_status('cancelled', 'Archi: comprobante no subido en 2 días, reserva cancelada automáticamente.');
            }
            continue;
        }

        // Mail 1 (te-falta): +3h.
        if ($age >= ARCHI_FALTA_DELAY && !$order->get_meta('_archi_falta_enviado')) {
            archi_send_te_falta($order);
            $order->update_meta_data('_archi_falta_enviado', current_time('mysql'));
            $order->save();
        }
    }
}
add_action('archi_email_cron_hourly', 'archi_emails_cron_hourly');

/**
 * Fecha del evento (timestamp) de una orden, leída de los productos FooEvents.
 * Devuelve 0 si no la encuentra.
 */
function archi_emails_order_event_date($order) {
    foreach ($order->get_items() as $item) {
        $pid = $item->get_product_id();
        if (!$pid) continue;
        $ts = get_post_meta($pid, 'WooCommerceEventsDateTimeTimestamp', true);
        if ($ts && is_numeric($ts)) return (int) $ts;
        $d = get_post_meta($pid, 'WooCommerceEventsDate', true);
        if ($d) {
            $parsed = strtotime($d);
            if ($parsed) return $parsed;
        }
    }
    return 0;
}

/**
 * Cron diario: mail 6 (post-evento). Solo actúa los lunes.
 */
function archi_emails_cron_daily() {
    if (archi_emails_mode() !== 'live') return;
    if (!function_exists('wc_get_orders')) return;
    if ((int) current_time('N') !== 1) return; // 1 = lunes

    $golive = archi_emails_golive_ts();
    $now    = time();

    $orders = wc_get_orders(array(
        'status'  => 'completed',
        'limit'   => 200,
        'orderby' => 'date',
        'order'   => 'DESC',
    ));
    if (empty($orders)) return;

    foreach ($orders as $order) {
        if ($order->get_meta('_bot_silent_close')) continue;
        if ($order->get_meta('_archi_postevento_enviado')) continue;
        $created = $order->get_date_created();
        if (!$created || $created->getTimestamp() < $golive) continue;

        $event_ts = archi_emails_order_event_date($order);
        if (!$event_ts) continue;
        $days = ($now - $event_ts) / DAY_IN_SECONDS;
        if ($days < 0 || $days > 8) continue; // evento de la última semana

        archi_send_postevento($order);
        $order->update_meta_data('_archi_postevento_enviado', current_time('mysql'));
        $order->save();
    }
}
add_action('archi_email_cron_daily', 'archi_emails_cron_daily');

// =====================================================================
// REGISTRO DE CRONS
// =====================================================================

// Agregamos un schedule custom de 5 minutos para que el cron de te-falta
// pueda dispararse con esa precisión. (WP-Cron solo trae hourly/daily/etc
// por default.)
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['archi_5min'])) {
        $schedules['archi_5min'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Cada 5 minutos (Archi)', 'astra-child-archibrazo'),
        );
    }
    return $schedules;
});

function archi_emails_schedule_crons() {
    // Si quedó scheduleado el viejo cron 'hourly', limpiarlo - lo reemplazamos
    // por el de 5 minutos para que te-falta dispare con más precisión.
    $hourly = wp_get_schedule('archi_email_cron_hourly');
    if ($hourly === 'hourly') {
        wp_clear_scheduled_hook('archi_email_cron_hourly');
    }
    if (!wp_next_scheduled('archi_email_cron_hourly')) {
        wp_schedule_event(time() + 60, 'archi_5min', 'archi_email_cron_hourly');
    }
    if (!wp_next_scheduled('archi_email_cron_daily')) {
        wp_schedule_event(time() + 120, 'daily', 'archi_email_cron_daily');
    }
}
add_action('init', 'archi_emails_schedule_crons');

function archi_emails_unschedule_crons() {
    wp_clear_scheduled_hook('archi_email_cron_hourly');
    wp_clear_scheduled_hook('archi_email_cron_daily');
}
add_action('switch_theme', 'archi_emails_unschedule_crons');

// =====================================================================
// TRIGGER MANUAL DE PRUEBA (solo admin / shop manager)
//   ?archi_test=tefalta|cancelada|recibimos|rechazado|postevento&order=ID
// Fuerza el envío de un mail para una orden puntual, sin tocar marcas ni estado.
// =====================================================================

/**
 * Test del mail 4 (mail nativo de WooCommerce "Pedido completado" con el
 * template overrideado por Archibrazo). Redirige el recipient a
 * ARCHI_EMAILS_TEST_ADDR y fuerza prefijo [TEST] en el subject. NO toca
 * el flujo real del cliente: solo se usa desde el trigger ?archi_test=...
 */
function archi_emails_test_entradalista($order) {
    if (!function_exists('WC')) return false;
    // Asegurar que las emails de WC estén inicializadas.
    if (!did_action('woocommerce_email')) {
        WC()->mailer();
    }

    $redirect_cb = function () { return ARCHI_EMAILS_TEST_ADDR; };
    $subject_cb  = function ($subject) { return '[TEST] ' . $subject; };
    add_filter('woocommerce_email_recipient_customer_completed_order', $redirect_cb, 9999);
    add_filter('woocommerce_email_subject_customer_completed_order',   $subject_cb,  9999);

    // Disparar via la acción canónica de WC (no por class lookup).
    do_action('woocommerce_order_status_completed_notification', $order->get_id(), $order);

    remove_filter('woocommerce_email_recipient_customer_completed_order', $redirect_cb, 9999);
    remove_filter('woocommerce_email_subject_customer_completed_order',   $subject_cb,  9999);

    if (is_callable(array($order, 'add_order_note'))) {
        $order->add_order_note('Archi mail [test]: "[TEST] Pedido completado (mail 4)" -> ' . ARCHI_EMAILS_TEST_ADDR . ' (trigger manual)');
    }
    return true;
}

function archi_emails_manual_trigger() {
    if (!isset($_GET['archi_test'])) return;
    if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) return;
    if (!function_exists('wc_get_order')) return;

    $what  = sanitize_key($_GET['archi_test']);
    $oid   = isset($_GET['order']) ? absint($_GET['order']) : 0;
    $order = $oid ? wc_get_order($oid) : null;
    if (!$order) wp_die('archi_test: agregá &order=ID con un número de pedido válido.');

    $map = array(
        'tefalta'      => 'archi_send_te_falta',
        'cancelada'    => 'archi_send_cancelada',
        'recibimos'    => 'archi_send_recibimos',
        'rechazado'    => 'archi_send_rechazado',
        'postevento'   => 'archi_send_postevento',
        'entradalista' => 'archi_emails_test_entradalista',
    );
    if (!isset($map[$what])) {
        wp_die('archi_test: valores válidos -> tefalta | cancelada | recibimos | rechazado | postevento | entradalista');
    }

    // Override opcional del destinatario via ?to=email. Si no, va a ARCHI_EMAILS_TEST_ADDR.
    $override_to = isset($_GET['to']) ? sanitize_email(wp_unslash($_GET['to'])) : '';
    $wp_mail_filter = null;
    $wc_recipient_filter = null;
    if (!empty($override_to) && is_email($override_to)) {
        $wp_mail_filter = function ($args) use ($override_to) {
            $args['to'] = $override_to;
            return $args;
        };
        add_filter('wp_mail', $wp_mail_filter, 9999);
        // WC mail 4 usa filtros propios para el recipient antes de wp_mail.
        $wc_recipient_filter = function () use ($override_to) { return $override_to; };
        add_filter('woocommerce_email_recipient_customer_completed_order', $wc_recipient_filter, 99999);
    }

    // Forzar redirect a la casilla de prueba incluso en modo 'live'.
    // Este endpoint es solo test, nunca debe llegar a un cliente real.
    $GLOBALS['archi_emails_force_test'] = true;
    $ok = call_user_func($map[$what], $order);
    unset($GLOBALS['archi_emails_force_test']);

    if ($wp_mail_filter)      remove_filter('wp_mail', $wp_mail_filter, 9999);
    if ($wc_recipient_filter) remove_filter('woocommerce_email_recipient_customer_completed_order', $wc_recipient_filter, 99999);

    $dest = (!empty($override_to) && is_email($override_to)) ? $override_to : ARCHI_EMAILS_TEST_ADDR;
    wp_die('archi_test: "' . esc_html($what) . '" para pedido #' . esc_html($order->get_order_number())
        . ' -> ' . ($ok ? 'ENVIADO' : 'FALLO') . ' (destinatario ' . esc_html($dest) . ', modo global: ' . esc_html(archi_emails_mode()) . ')');
}
add_action('init', 'archi_emails_manual_trigger', 99);
