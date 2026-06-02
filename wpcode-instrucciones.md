# Snippet WPCode para preview del funnel en archibrazo.org

> Archivo del snippet: [`wpcode-snippet.php`](./wpcode-snippet.php) (~42KB, autocontenido)

## Para qué sirve

Es un snippet de **WPCode Lite** (el plugin de snippets que ya tienen instalado en archibrazo.org, el mismo donde vive el snippet 37364 del bot Telegram). Cuando lo activan y se loguean como admin, abren una URL secreta y ven el prototipo del funnel rediseñado servido **desde el dominio real de archibrazo.org**, sin afectar a nadie más.

Sirve para que Alex (y cualquiera con acceso a admin) lo revise desde su entorno habitual de WordPress sin que tengamos que armar staging completo todavía.

## Cómo instalarlo (5 minutos)

1. **WP Admin** → menú lateral → **Code Snippets** (o **WPCode**, según versión).
2. **Add Snippet** → **Add Your Custom Code (New Snippet)**.
3. **Code Type:** PHP Snippet.
4. **Title:** `Archi Funnel Preview (staging visual)`.
5. **Code Preview:** pegar todo el contenido de `wpcode-snippet.php` (es 1 archivo, copiar tal cual).
6. **Insertion:** Auto Insert → **Run Everywhere**.
7. **Save Changes & Activate**.

## Cómo verlo

Estando logueado en archibrazo.org como **Administrator** o **Shop Manager**, abrir:

```
https://www.archibrazo.org/?archi_funnel_preview=1
```

Carga el prototipo a pantalla completa, navegable por los 5 pasos vía el devnav abajo a la derecha (en mobile arriba).

## Cómo lo comparten con Alex

Joaco activa el snippet → abre la URL → confirma que se ve bien → manda a Alex por WhatsApp:

> "Logueate al admin de archibrazo.org y abrí esta URL: `https://www.archibrazo.org/?archi_funnel_preview=1` - es un preview del rediseño del checkout que estamos revisando."

Si Alex tiene rol Administrator o Shop Manager, lo ve. Si no, le aparece un 403 "Acceso denegado".

## Por qué NO rompe nada

| Capa | Lo que NO hace |
|---|---|
| WooCommerce | No engancha ningún hook de orden, carrito, checkout, customer ni gateway. |
| FooEvents | No toca settings, hooks ni templates. |
| PeproDev | No interactúa con el form de upload ni con sus meta keys. |
| Bot Telegram (snippet 37364) | Sin contacto. |
| Make.com escenarios | Sin contacto. |
| DB | No registra nada, no crea custom post types, no toca opciones. |
| Sitio público | Inaccesible para guests y para Google (`X-Robots-Tag: noindex, nofollow` + chequeo de capability). |
| URLs existentes | Solo intercepta la URL si tiene `?archi_funnel_preview=1`. Cualquier otra URL pasa de largo. |
| Performance | El hook ejecuta una sola comparación de string en `template_redirect`. Latencia despreciable. |

## Rollback (si pasa algo raro)

WP Admin → Code Snippets → snippet **"Archi Funnel Preview (staging visual)"** → **Deactivate**. Listo. El sitio vuelve idéntico al estado actual.

Si querés borrarlo del todo: Deactivate → Delete. No queda rastro.

## Garantías técnicas internas

```php
add_action('template_redirect', function() {
    // Guardrail 1: query param exacta
    if (!isset($_GET['archi_funnel_preview']) || $_GET['archi_funnel_preview'] !== '1') return;

    // Guardrail 2: solo admin o shop manager
    if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
        status_header(403);
        wp_die('No tenés permiso...', 'Acceso denegado', ['response' => 403]);
    }

    // Headers: no cachear, no indexar
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');

    echo $html_estatico;
    exit;
}, 1);
```

3 capas de protección concurrentes: query param + capability + no-index header.

## Diferencia con "bajar el rediseño a producción"

**Este snippet es solo para PREVIEW visual.** Renderea un HTML estático que no está conectado al checkout real de WooCommerce. No procesa órdenes, no crea usuarios, no manda mails.

Para implementar el rediseño "de verdad" (que afecte al checkout real de la gente que compra) hay que ejecutar el plan completo de [`aplicacion-wordpress.md`](./aplicacion-wordpress.md) en un staging clonado de producción y después deployar al theme. Eso es otro proyecto, después de que Alex apruebe la dirección visual con este preview.

## Si WPCode no acepta el snippet

WPCode Lite tiene límite de tamaño en algunos planes (raro, suele ser >1MB). Si rechaza el snippet de 42KB:

- **Opción B:** subir `index.html` a Media Library (subiéndolo como archivo .html requiere habilitar el MIME tipo en Settings → Media). Resulta en una URL pública tipo `archibrazo.org/wp-content/uploads/2026/05/funnel-preview.html`. Más rápido pero la URL es pública (cualquiera la encuentra).
- **Opción C:** crear una Page nueva en WP con visibilidad "Privada", template "Full Width", y pegar el `<body>` del prototipo en un bloque "Custom HTML". Tailwind CDN y Google Fonts hay que cargarlos vía un snippet WPCode adicional condicional a esa página.

Si pasa alguno de estos casos, avisame y armo la variante.
