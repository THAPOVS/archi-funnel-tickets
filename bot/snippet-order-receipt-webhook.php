<?php
// Snippet WPCode - "Order receipt webhook"
// snippet_id: 37364 | Tipo: PHP, Auto Insert "Run Everywhere", ACTIVO
//
// NOTA: en el editor de WPCode el codigo va SIN la etiqueta <?php inicial.
// Este archivo es el espejo local; al pegar en WPCode, omitir la primera linea.
/**
 * Notifica via webhook cuando una orden cambia a "esperando aprobacion comprobante".
 * Arma el caption ya formateado server-side (Variante B con fecha del evento de FooEvents).
 *
 * Mantenedor: cambios documentar en
 * ~/Documents/Claude/Projects/Archibrazo/bot-tickets-troubleshooting.md
 */
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status, $order) {
    if ($new_status !== 'receipt-approval') return;

    // Skip ordenes de testeo: si el email del cliente contiene "+test", no notificar al bot.
    if (strpos(strtolower($order->get_billing_email()), '+test') !== false) return;
    $webhook_url = 'https://hook.<region>.make.com/<REPLACE_ME>';
    if (strpos($webhook_url, 'REPLACE_') !== false) return;

    $dias  = array('dom','lun','mar','mié','jue','vie','sáb');
    $meses = array('ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic');

    $fmt_money = function($n) {
        return number_format((float)$n, 0, ',', '.');
    };
    $fmt_short = function($ts) use ($meses) {
        // "5 may 14:36"
        return date('j', $ts) . ' ' . $meses[(int)date('n', $ts) - 1] . ' ' . date('H:i', $ts);
    };
    $fmt_full = function($ts, $with_time) use ($dias, $meses) {
        // "vie 9 may 2026 23:30" (o sin hora)
        $s = $dias[(int)date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $meses[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
        if ($with_time) $s .= ' ' . date('H:i', $ts);
        return $s;
    };

    $customer = strtoupper(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
    $customer = str_replace('"', "'", $customer);

    $caption = "👤 " . $customer . "\n";
    $caption .= "━━━━━━━━━━━━━━━━━━━\n\n";

    // Campos sueltos para el mensaje compacto de auto-aprobada (primer item / qty total).
    $event_name = '';
    $event_date = '';
    $qty_total  = 0;

    foreach ($order->get_items() as $item) {
        $qty  = $item->get_quantity();
        $qty_total += $qty;
        $sub  = (float) $item->get_total();
        $unit = $qty > 0 ? ($sub / $qty) : 0;
        $name = str_replace('"', "'", $item->get_name());

        if ($event_name === '') $event_name = $name;

        $caption .= "🎭 " . $name . "\n";

        $product_id     = $item->get_product_id();
        $event_date_raw = get_post_meta($product_id, 'WooCommerceEventsDate', true);
        $event_hour     = get_post_meta($product_id, 'WooCommerceEventsHour', true);
        $event_min      = get_post_meta($product_id, 'WooCommerceEventsMinutes', true);

        if ($event_date_raw) {
            $parts = explode('/', $event_date_raw);
            if (count($parts) === 3) {
                $h = ($event_hour !== '' && $event_hour !== null) ? (int)$event_hour : 0;
                $m = ($event_min !== '' && $event_min !== null) ? (int)$event_min : 0;
                $ts = mktime($h, $m, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
                if ($ts) {
                    $with_time = ($event_hour !== '' && $event_hour !== null && $event_min !== '' && $event_min !== null);
                    $caption .= "📅 " . $fmt_full($ts, $with_time) . "\n";
                    if ($event_date === '') $event_date = $fmt_full($ts, $with_time);
                }
            }
        }

        $caption .= "🎫 " . $qty . " × $" . $fmt_money($unit) . " = $" . $fmt_money($sub) . "\n\n";
    }

    $caption .= "━━━━━━━━━━━━━━━━━━━\n\n";

    $email = str_replace('"', "'", $order->get_billing_email());
    $phone = $order->get_billing_phone();

    $caption .= "📨 " . $email . "\n";
    if ($phone) $caption .= "📞 " . $phone . "\n";

    $receipt_uploaded = $order->get_meta('receipt_upload_date_uploaded');
    $receipt_short = '';
    if ($receipt_uploaded) {
        $rt = strtotime($receipt_uploaded);
        if ($rt) $receipt_short = $fmt_short($rt);
    }
    $order_ts = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();
    $order_short = $fmt_short($order_ts);

    $caption .= "🧾 " . $receipt_short . "  ·  🛒 " . $order_short . "\n\n";
    $caption .= "#" . $order_id;

    $attachment_id = $order->get_meta('receipt_uploaded_attachment_id');
    $receipt_url   = $attachment_id ? wp_get_attachment_url($attachment_id) : '';

    $payload = array(
        'order_id'     => $order_id,
        'caption_full' => $caption,
        'receipt_url'  => $receipt_url,
        'customer'     => $customer,
        'email'        => $email,
        'phone'        => $phone,
        'total'        => $order->get_total(),
        'total_int'    => (int) round((float) $order->get_total()),
        'currency'     => $order->get_currency(),
        'event_name'   => $event_name,
        'event_date'   => $event_date,
        'qty'          => $qty_total,
        'admin_url'    => admin_url('post.php?post=' . $order_id . '&action=edit'),
        'fired_at'     => current_time('c'),
    );

    wp_remote_post($webhook_url, array(
        'body'     => wp_json_encode($payload),
        'headers'  => array('Content-Type' => 'application/json'),
        'timeout'  => 5,
        'blocking' => false,
    ));
}, 10, 4);
