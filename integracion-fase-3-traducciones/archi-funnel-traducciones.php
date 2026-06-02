<?php
/**
 * Archibrazo - Traducciones del plugin de upload de comprobante (PeproDev / PCB)
 *
 * Reemplaza al vuelo los strings en inglés del plugin que aparecen en el paso 4
 * del checkout (subir comprobante) por su versión en castellano rioplatense.
 *
 * Usa el filtro `gettext` de WordPress que se ejecuta antes de mostrar cada
 * string traducible. Si el string original (en inglés) está en este mapa,
 * se reemplaza por el del array. Si no, sigue como está.
 *
 * Ventajas:
 *   - No requiere instalar Loco Translate ni archivos .po/.mo
 *   - Funciona aunque alguien borre/regenere las traducciones del plugin
 *   - Más controlable (vos editás el array y queda)
 *
 * INSTALACIÓN
 * -----------
 *   1. WP admin → Fragmentos de código (WPCode) → Add Snippet → PHP Snippet
 *   2. Title: "Archi Funnel - Traducciones upload comprobante"
 *   3. Pegar este código
 *   4. Insertion: Auto Insert → Run Everywhere
 *   5. Save & Activate
 *
 * ROLLBACK: Deactivate. Sitio vuelve a verse con los strings en inglés.
 */

if (!defined('ABSPATH')) return;

add_filter('gettext', function ($translated, $original, $domain) {

    // Mapa de strings: inglés → castellano
    static $map = array(
        // Headers / labels principales
        'Upload receipt'              => 'Subí tu comprobante',
        'Upload Receipt'              => 'Enviar comprobante',
        'Upload your receipt'         => 'Subí tu comprobante de transferencia',
        'Current receipt:'            => 'Comprobante cargado:',
        'Current receipt'             => 'Comprobante cargado',
        'Upload Receipt:'             => 'Cargá tu comprobante:',
        'Awaiting Upload'             => 'Esperando archivo',
        'No receipt uploaded'         => 'Todavía no subiste ningún comprobante',
        'Receipt Status'              => 'Estado del comprobante',
        'Receipt status'              => 'Estado del comprobante',
        'Receipt Uploaded'            => 'Comprobante recibido',
        'Receipt uploaded'            => 'Comprobante recibido',
        'Receipt Approved'            => 'Comprobante aprobado',
        'Receipt Rejected'            => 'Comprobante rechazado',
        'Receipt Pending'             => 'Esperando verificación',

        // Botones
        'Submit Receipt'              => 'Enviar comprobante',
        'Submit'                      => 'Enviar',
        'Save Receipt'                => 'Guardar comprobante',

        // Mensajes de estado / error
        'Receipt uploaded successfully'  => 'Comprobante subido OK',
        'File uploaded successfully'     => 'Archivo subido OK',
        'Please upload a valid file'     => 'Subí un archivo válido (PDF, JPG o PNG)',
        'Please select a file'           => 'Elegí un archivo',
        'Invalid file type'              => 'Tipo de archivo no permitido',
        'File is too large'              => 'El archivo es muy grande (máximo 8MB)',
        'File too large'                 => 'El archivo es muy grande (máximo 8MB)',
        'Receipt already uploaded'       => 'Ya subiste un comprobante para esta orden',
        'Upload failed'                  => 'Falló la subida del archivo, intentá de nuevo',
        'Please wait for approval'       => 'Esperando que verifiquemos tu comprobante',

        // Estados de orden custom
        'Awaiting Receipt Upload'        => 'Esperando comprobante',
        'Esperando Subir Comprobantes'   => 'Esperando comprobante',
        'Awaiting Receipt Approval'      => 'Esperando aprobación del comprobante',
        'Esperando Aprobación de los Comprobantes' => 'Esperando aprobación del comprobante',
        'Receipt Rejected'               => 'Comprobante rechazado',
        'Comprobantes Rechazados'        => 'Comprobante rechazado',

        // Bank transfer / transferencia (por si vienen del gateway BACS)
        'Bank Transfer'                  => 'Transferencia bancaria',
        'Direct Bank Transfer'           => 'Transferencia bancaria',
        'Our Bank Details'               => 'Datos para transferir',
        'Account Number'                 => 'Número de cuenta',
        'Bank'                           => 'Banco',
        'Sort Code'                      => 'CBU',
        'IBAN'                           => 'CBU',
        'BIC'                            => 'Alias',
        'SWIFT'                          => 'Alias',
        'Routing Number'                 => 'CBU',
    );

    if (isset($map[$original])) {
        return $map[$original];
    }
    return $translated;
}, 20, 3);

/**
 * Sobre los strings del INPUT FILE nativo del browser:
 *   - "Choose File"
 *   - "No file chosen"
 *
 * Esos NO se pueden traducir desde PHP/WP porque vienen del navegador del
 * usuario. Cada navegador los muestra en el idioma del sistema operativo
 * del visitante. Si el visitante tiene su Mac/Windows/Chrome en español,
 * verá "Seleccionar archivo" / "Ningún archivo seleccionado" automáticamente.
 * Si está en inglés, los verá en inglés. No hay forma de forzarlo desde
 * el servidor (es una limitación del navegador, no del sitio).
 */
