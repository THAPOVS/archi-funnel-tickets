# HANDOFF: sacar texto "envíalo a boleteria@archibrazo.org" del thank-you

> Tarea puntual de WordPress admin. Lo autorizo a hacerlo en producción de archibrazo.org. Es un cambio de texto en la config de un gateway de WooCommerce, no toca código.

## Qué hay que hacer

En la pantalla de **thank-you / order-received** de archibrazo.org aparece un texto del estilo:

> *"Transfiere al alias: ARCHICOOP y subí tu comprobante o envíalo a boleteria@archibrazo.org"*

Quiero **sacar la parte de "o envíalo a boleteria@archibrazo.org"**. Dejar solo *"Transfiere al alias: ARCHICOOP y subí tu comprobante"* (o variante similar sin la opción de mail).

Razón: tenemos un bot Telegram + flujo de subida en la web que ya cubre el caso. La opción de mail al boleteria@ confunde y descontrola la cadena de aprobación (las órdenes pagadas por mail no entran al bot, se pierden).

## Dónde está ese texto (3 lugares posibles)

Probablemente está en uno de estos lugares, en orden de probabilidad:

### Opción A - WooCommerce → Ajustes → Pagos → Gateway de Transferencia (lo más probable)

1. WP admin → **WooCommerce** → **Ajustes** → tab **"Pagos"**
2. Buscar el gateway activo: nombre puede ser "Pago con Transferencia", "BACS", "Transferencia Bancaria", "Pago con Transferencia QR" o variante.
3. Click "Administrar" / "Manage" / botón de configuración
4. Buscar campos:
   - **Instructions / Instrucciones** (textarea grande con texto markdown/HTML)
   - **Description / Descripción** (textarea, lo que se ve en el checkout antes de pagar)
   - **Title / Título**
5. En cualquiera de esos campos debería estar el texto "boleteria@archibrazo.org"
6. Editar el texto borrando la frase "o envíalo a boleteria@archibrazo.org" (mantener el resto intacto)
7. **Guardar cambios**

### Opción B - PeproDev settings (plugin de upload de comprobantes)

Si no encontrás el texto en WC → Pagos:

1. WP admin → busca en sidebar **"PeproDev"** o **"Receipt Upload"** o similar
2. Settings del plugin → buscar campos de texto que mencionen "boleteria@"
3. Editar igual que arriba

### Opción C - Customización en theme padre (Astra) o page-builder

Si no aparece en A ni B:

1. WP admin → **Páginas** → buscar página tipo "Thank you" / "Pedido recibido" / "Order received"
2. Si existe, editar y sacar el texto desde Elementor / Gutenberg
3. **Alternativa:** WP admin → **Apariencia → Editor** → buscar template `thankyou.php` o similar

## Credenciales

- WP Admin: https://www.archibrazo.org/wp-admin/
- Usuario: `joaquinpovina`
- Application Password (REST API): `C5E9 ZHTz AKI4 TTj4 hlfD qbC8`
- Rol: super_admin (id 113), tenés permisos para tocar todo

## Verificación

Después del cambio:

1. **Pestaña logueado:** hacer una orden de prueba con un evento cualquiera → llegar al thank-you → confirmar que el texto ya NO dice "envíalo a boleteria@..."
2. **Pestaña incógnito:** Si el flujo se completa de invitado, verificar también ahí
3. **Email de confirmación:** revisar la cuenta de mail (joaquinpovina@gmail.com) - WC manda el texto del gateway también por mail. Si el mail también lo menciona, está bien (porque editamos la config del gateway, debería actualizarse en ambos lados)

## Lo que NO podés tocar

- Snippets WPCode 37636 ("Archi Funnel - Fase 1") y 37640 ("Archi Funnel - Fase 2") - son del rediseño de checkout, no relacionados con este texto
- Snippet WPCode 37364 - bot Telegram, crítico, no tocar
- Hooks de orden, FooEvents, PeproDev (al menos no su lógica, sí podés tocar settings de display)

## Reporte esperado

Mandame:
- Captura del campo donde encontraste el texto (antes de editar)
- Captura del campo después de editar y guardar
- Captura del thank-you de una orden de prueba mostrando el texto nuevo

Si NO encontrás el texto en ninguna de las 3 opciones, avisame con captura de WC → Pagos para ver qué gateway está activo, y vemos juntos dónde más buscar.
