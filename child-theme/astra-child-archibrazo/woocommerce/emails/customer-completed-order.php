<?php
/**
 * Customer Completed Order email - override Archibrazo (Mail 4).
 *
 * Variables en scope (provistas por WC_Email):
 *   $order, $email_heading, $additional_content, $sent_to_admin, $plain_text, $email
 */

defined('ABSPATH') || exit;

if (!empty($plain_text)) {
    echo sprintf(esc_html__('Hola %s,', 'astra-child-archibrazo'), esc_html($order->get_billing_first_name())) . "\n\n";
    echo esc_html__('Tu entrada esta lista. Mas abajo el detalle del pedido y el ticket.', 'astra-child-archibrazo') . "\n\n";
    do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
    do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
    do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
    if (!empty($additional_content)) {
        echo "\n" . esc_html(wp_strip_all_tags($additional_content)) . "\n";
    }
    echo "\n" . esc_html__('Te esperamos en Mario Bravo 441, Almagro.', 'astra-child-archibrazo') . "\n";
    return;
}

$archi_nombre   = trim($order->get_billing_first_name());
$archi_pedido   = $order->get_order_number();
$archi_maps_url = 'https://www.google.com/maps/place/El+Archibrazo/@-34.6048636,-58.421484,17z/data=!3m1!4b1!4m6!3m5!1s0x95bcca8a7c0dca87:0x1154aae4e74548ca!8m2!3d-34.6048681!4d-58.4168706!16s%2Fg%2F11bbrm210t';

// ===== FooEvents tickets para el QR =====
// Solo busca tickets si hay un order_id valido.
// IMPORTANTE: el meta_query a veces tira false positives (devolvia tickets de
// OTRAS ordenes), por eso ademas hacemos doble-check manual con get_post_meta.
$archi_ticket_hashes = array();
$archi_order_id_int  = (int) $order->get_id();
if ($archi_order_id_int > 0 && post_type_exists('event_magic_tickets')) {
    $archi_tickets = get_posts(array(
        'post_type'   => 'event_magic_tickets',
        'meta_query'  => array(
            array(
                'key'   => 'WooCommerceEventsOrderID',
                'value' => $archi_order_id_int,
            ),
        ),
        'numberposts' => 50, // cap defensivo
        'orderby'     => 'ID',
        'order'       => 'ASC',
        'post_status' => 'any',
    ));
    foreach ($archi_tickets as $archi_tk) {
        // Doble-check: el ticket TIENE que pertenecer a esta orden.
        $archi_tk_order = (int) get_post_meta($archi_tk->ID, 'WooCommerceEventsOrderID', true);
        if ($archi_tk_order !== $archi_order_id_int) continue;
        $archi_hash = get_post_meta($archi_tk->ID, 'WooCommerceEventsTicketHash', true);
        if (!$archi_hash) {
            $archi_hash = get_post_meta($archi_tk->ID, 'WooCommerceEventsTicketHashID', true);
        }
        if ($archi_hash) {
            $archi_ticket_hashes[] = array('hash' => $archi_hash, 'id' => $archi_tk->ID);
        }
    }
}

// Preview de WC settings: usa una orden sample con ID 12345 que no tiene tickets
// reales asociados. Para que el admin pueda ver el render del QR, inyectamos uno
// de muestra. NO afecta clientes reales (sus orders tienen ID distinto).
if (empty($archi_ticket_hashes) && $archi_order_id_int === 12345) {
    $archi_ticket_hashes[] = array(
        'hash' => 'ARCHIBRAZO-PREVIEW-SAMPLE-TICKET',
        'id'   => 'preview',
    );
}

// ===== Download button (dentro del toggle) =====
$archi_downloads = method_exists($order, 'get_downloadable_items') ? $order->get_downloadable_items() : array();
$archi_dl_url    = '';
$archi_dl_text   = '';
$archi_expires   = '';
if (count($archi_downloads) === 1) {
    $archi_dl      = reset($archi_downloads);
    $archi_dl_url  = isset($archi_dl['download_url']) ? $archi_dl['download_url'] : $order->get_view_order_url();
    $archi_dl_text = 'Descargar ticket';
    if (!empty($archi_dl['access_expires'])) {
        $archi_exp_ts = is_numeric($archi_dl['access_expires']) ? (int) $archi_dl['access_expires'] : strtotime($archi_dl['access_expires']);
        if ($archi_exp_ts) {
            $archi_expires = date_i18n('d/m/Y', $archi_exp_ts);
        }
    }
} elseif (count($archi_downloads) > 1) {
    $archi_dl_url  = $order->get_view_order_url();
    $archi_dl_text = 'Ver mis tickets (' . count($archi_downloads) . ')';
}

// ===== Split del additional_content en gracias (top) + disclaimer =====
$archi_top_msg    = '';
$archi_disclaimer = '';
if (!empty($additional_content)) {
    if (stripos($additional_content, 'IMPORTANTE:') !== false) {
        $archi_parts      = preg_split('/IMPORTANTE:/i', $additional_content, 2);
        $archi_top_msg    = trim($archi_parts[0]);
        $archi_disclaimer = trim($archi_parts[1]);
    } else {
        $archi_top_msg = trim($additional_content);
    }
}
?><!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Tu entrada esta lista - El Archibrazo</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap">
<style>
  @font-face { font-family: 'AndralisND'; src: url('https://www.archibrazo.org/wp-content/themes/astra-child-archibrazo/fonts/AndralisND-Regular.otf') format('opentype'); font-weight:400; font-style:normal; font-display:swap; }
  @font-face { font-family: 'AndralisND'; src: url('https://www.archibrazo.org/wp-content/themes/astra-child-archibrazo/fonts/AndralisND-Italic.otf') format('opentype'); font-weight:400; font-style:italic; font-display:swap; }
  @font-face { font-family: 'AndralisND'; src: url('https://www.archibrazo.org/wp-content/themes/astra-child-archibrazo/fonts/AndralisND-Bold.otf') format('opentype'); font-weight:700; font-style:normal; font-display:swap; }
</style>
<style>
  body { margin:0; padding:0; background:#0a0a0a; -webkit-text-size-adjust:100%; }
  a { color:#ff8a3d; }
  /* Forzar dark SOLO sobre las tablas especificas de WC dentro del wrapper.
     OJO: NO usar selector generico ".archi-wc-block table" porque pisaria mis
     propios botones (background transparente = boton sin shape). */
  .archi-wc-block table.woocommerce-table, .archi-wc-block table.td {
    background:transparent !important; color:#d6cfc1 !important; border-color:#3a3a3a !important;
    font-family:'Manrope',Arial,sans-serif !important;
  }
  .archi-wc-block table.woocommerce-table th, .archi-wc-block table.woocommerce-table td,
  .archi-wc-block table.td th, .archi-wc-block table.td td {
    background:transparent !important; color:#d6cfc1 !important; border-color:#3a3a3a !important;
  }
  .archi-wc-block h2, .archi-wc-block h3 {
    color:#f5f1ea !important;
    font-family:'AndralisND','Rockwell','Rockwell Std',Georgia,'Times New Roman',serif !important;
    font-weight:600 !important;
  }
  .archi-wc-block h2 { font-size:22px !important; margin:0 0 14px !important; line-height:1.2 !important; }
  .archi-wc-block h3 { font-size:16px !important; margin:18px 0 8px !important; }
  .archi-wc-block h2 a { color:#f5f1ea !important; text-decoration:none !important; }
  .archi-wc-block strong { color:#f5f1ea !important; }
  .archi-wc-block p, .archi-wc-block li { color:#d6cfc1 !important; }
  .archi-wc-block a { color:#ff8a3d !important; }
  /* Triangulito del details */
  details > summary { list-style:none; }
  details > summary::-webkit-details-marker { display:none; }
  @media (max-width:620px) {
    .card { width:100% !important; border-radius:0 !important; }
    .pad { padding-left:24px !important; padding-right:24px !important; }
    .h1 { font-size:30px !important; }
  }
</style>
</head>
<body style="margin:0; padding:0; background:#0a0a0a;">

<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:#0a0a0a;">Tu entrada ya esta emitida.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a" style="background:#0a0a0a;">
<tr>
<td align="center" style="padding:40px 16px;">

  <table role="presentation" class="card" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#161616" style="width:600px; max-width:600px; background:#161616; border:1px solid #242424; border-radius:20px; overflow:hidden;">

    <tr>
    <td class="pad" align="center" style="padding:32px 48px 0;">
      <img src="https://www.archibrazo.org/wp-content/uploads/2026/05/archibrazo-logo-cream-v2.png" alt="El Archibrazo" width="180" style="display:block; max-width:180px; height:auto; margin:0 auto;">
    </td>
    </tr>

    <tr>
    <td class="pad" align="center" style="padding:32px 48px 0;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
      <tr>
      <td width="76" height="76" align="center" valign="middle" bgcolor="#0f2a1e" style="width:76px; height:76px; background:#0f2a1e; border:1px solid #1e4634; border-radius:999px; font-family:Arial,sans-serif; font-size:34px; line-height:34px;">&#127915;</td>
      </tr>
      </table>
    </td>
    </tr>

    <tr>
    <td class="pad" align="center" style="padding:24px 48px 0;">
      <div style="font-family:'Manrope',Arial,sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#34d399;">Tu entrada est&aacute; lista</div>
    </td>
    </tr>

    <tr>
    <td class="pad" align="center" style="padding:12px 48px 0;">
      <h1 class="h1" style="margin:0; text-align:center; font-family:'AndralisND','Rockwell','Rockwell Std',Georgia,'Times New Roman',serif; font-size:34px; line-height:1.18; font-weight:600; color:#f5f1ea;">&iexcl;Listo<?php echo $archi_nombre !== '' ? ', ' . esc_html($archi_nombre) : ''; ?>!<br>Tu entrada te espera</h1>
    </td>
    </tr>

    <?php if ($archi_top_msg !== '') : ?>
    <tr>
    <td class="pad" align="center" style="padding:14px 48px 0; font-family:'Manrope',Arial,sans-serif; font-size:15px; line-height:1.6; color:#d6cfc1;">
      <?php echo wp_kses_post(wpautop(wptexturize($archi_top_msg))); ?>
    </td>
    </tr>
    <?php endif; ?>

    <tr>
    <td class="pad" align="center" style="padding:18px 48px 0;">
      <p style="margin:0; font-family:'Manrope',Arial,sans-serif; font-size:15px; line-height:1.6; color:#a8a29e;">Mostr&aacute; tu QR en la puerta desde el celu o impreso.</p>
    </td>
    </tr>

    <?php if (!empty($archi_ticket_hashes)) : ?>
      <?php foreach ($archi_ticket_hashes as $archi_qr) : ?>
      <!-- QR card (una por ticket real de la orden) -->
      <tr>
      <td class="pad" align="center" style="padding:22px 48px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f1ea" style="background:#f5f1ea; border-radius:16px;">
          <tr>
          <td align="center" style="padding:22px 22px 10px; line-height:0;">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&amp;margin=0&amp;data=<?php echo esc_attr(rawurlencode($archi_qr['hash'])); ?>" width="200" height="200" alt="C&oacute;digo QR" style="display:block; width:200px; height:200px;">
          </td>
          </tr>
          <tr>
          <td align="center" style="padding:0 22px 8px; font-family:'Manrope',Arial,sans-serif; font-size:13px; font-weight:700; letter-spacing:0.04em; color:#3a3631;">Mostr&aacute; este c&oacute;digo en la puerta</td>
          </tr>
          <tr>
          <td align="center" style="padding:0 22px 18px; font-family:'Manrope',Arial,sans-serif; font-size:12px; color:#8a8378;">Entrada #<?php echo esc_html($archi_qr['id']); ?></td>
          </tr>
        </table>
      </td>
      </tr>
      <?php endforeach; ?>
    <?php else : ?>
      <!-- Fallback: link a la pagina del pedido -->
      <tr>
      <td class="pad" align="center" style="padding:24px 48px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
        <td align="center" bgcolor="#ff3d7f" style="border-radius:999px; background:#ff3d7f; background:linear-gradient(95deg,#ff3d7f,#ff8a3d);">
          <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="display:inline-block; padding:14px 32px; font-family:'Manrope',Arial,sans-serif; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px;">Ver mi pedido &rarr;</a>
        </td>
        </tr>
        </table>
      </td>
      </tr>
    <?php endif; ?>

    <!-- Detalles del pedido -->
    <tr>
    <td class="pad" style="padding:32px 48px 0; font-family:'Manrope',Arial,sans-serif; color:#d6cfc1;">
      <div style="background:#161616; border:1px solid #242424; border-radius:14px; padding:24px 22px;">
        <div style="font-family:'Manrope',Arial,sans-serif; font-size:11px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#ff3d7f; margin:0 0 14px;">Detalles del pedido</div>

        <?php if ($archi_dl_url !== '') : ?>
        <!-- Boton de descarga estilo funnel (gradient pink/orange).
             OUT del wrapper .archi-wc-block para que el CSS de WC no le pise el background -->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
          <tr>
          <td align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
            <td align="center" bgcolor="#ff3d7f" style="border-radius:999px; background-color:#ff3d7f; background-image:linear-gradient(95deg,#ff3d7f,#ff8a3d);">
              <a href="<?php echo esc_url($archi_dl_url); ?>" style="display:inline-block; padding:12px 28px; font-family:'Manrope',Arial,sans-serif; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px;"><?php echo esc_html($archi_dl_text); ?> &darr;</a>
            </td>
            </tr>
            </table>
            <?php if ($archi_expires !== '') : ?>
            <div style="margin-top:8px; font-family:'Manrope',Arial,sans-serif; font-size:12px; color:#8a857c;">Vence el <?php echo esc_html($archi_expires); ?></div>
            <?php endif; ?>
          </td>
          </tr>
        </table>
        <?php endif; ?>

        <div class="archi-wc-block">
        <?php
        do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
        do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
        do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
        ?>
        </div>
      </div>
    </td>
    </tr>

    <!-- Te esperamos -->
    <tr>
    <td class="pad" style="padding:24px 48px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#11271d" style="background:#11271d; border:1px solid #1e4634; border-radius:14px;">
      <tr>
      <td style="padding:16px 18px; font-family:'Manrope',Arial,sans-serif; font-size:14px; line-height:1.6; color:#d6cfc1;">
        <strong style="color:#34d399;">Te esperamos en Mario Bravo 441, Almagro.</strong> Ca&eacute; tipo media hora antes del evento, tom&aacute; o com&eacute; algo en nuestro bar, as&iacute; viv&iacute;s el Archi desde que entr&aacute;s.
      </td>
      </tr>
      </table>
    </td>
    </tr>

    <!-- Cómo llegar -->
    <tr>
    <td class="pad" align="center" style="padding:24px 48px 0;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
      <tr>
      <td align="center" bgcolor="#ff3d7f" style="border-radius:999px; background:#ff3d7f; background:linear-gradient(95deg,#ff3d7f,#ff8a3d);">
        <a href="<?php echo esc_url($archi_maps_url); ?>" style="display:inline-block; padding:16px 34px; font-family:'Manrope',Arial,sans-serif; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px;">C&oacute;mo llegar &#128506;</a>
      </td>
      </tr>
      </table>
    </td>
    </tr>

    <!-- Contacto -->
    <tr>
    <td class="pad" align="center" style="padding:22px 48px 0;">
      <p style="margin:0; font-family:'Manrope',Arial,sans-serif; font-size:14px; line-height:1.6; color:#a8a29e;">&iquest;Alg&uacute;n problema con tu entrada? Escribinos a <a href="mailto:boleteria@archibrazo.org" style="color:#ff8a3d; text-decoration:underline;">boleteria@archibrazo.org</a>.</p>
    </td>
    </tr>

    <?php if ($archi_disclaimer !== '') : ?>
    <!-- Disclaimer (Importante) - inline, italic chico tipo P.S. -->
    <tr>
    <td class="pad" align="center" style="padding:18px 48px 0;">
      <p style="margin:0; font-family:'Manrope',Arial,sans-serif; font-style:italic; font-size:12px; line-height:1.55; color:#6f6a63;">
        <span style="font-style:normal; font-weight:600; color:#8a857c;">Importante:</span> <?php echo esc_html(trim(wp_strip_all_tags($archi_disclaimer))); ?>
      </p>
    </td>
    </tr>
    <?php endif; ?>

    <!-- Divider -->
    <tr>
    <td class="pad" style="padding:28px 48px 0;">
      <div style="height:1px; background:#242424; line-height:1px; font-size:0;">&nbsp;</div>
    </td>
    </tr>

    <!-- Footer -->
    <tr>
    <td class="pad" align="center" style="padding:22px 48px 36px;">
      <p style="margin:0; font-family:'Manrope',Arial,sans-serif; font-size:13px; line-height:1.6; color:#6f6a63;">Pedido <strong style="color:#a8a29e;">#<?php echo esc_html($archi_pedido); ?></strong></p>
      <p style="margin:8px 0 0; font-family:'Manrope',Arial,sans-serif; font-size:13px; line-height:1.6; color:#6f6a63;">El Archibrazo &middot; Mario Bravo 441, Almagro, CABA</p>
      <p style="margin:11px 0 0; font-family:'Manrope',Arial,sans-serif; font-size:13px; line-height:1.6; color:#6f6a63;">Segu&iacute;nos en <a href="https://instagram.com/archibrazo" style="color:#ff8a3d; text-decoration:none;">Instagram</a> &middot; <a href="https://facebook.com/archibrazo" style="color:#ff8a3d; text-decoration:none;">Facebook</a> &middot; <a href="https://www.archibrazo.org" style="color:#ff8a3d; text-decoration:none;">archibrazo.org</a></p>
    </td>
    </tr>

  </table>

</td>
</tr>
</table>

</body>
</html>
