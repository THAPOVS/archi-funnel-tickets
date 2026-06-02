# Archi Funnel Preview - para Alex

Hola Ale. Esto es un preview visual del rediseño del checkout de tickets que estamos revisando con Joaco. Lo armé para que lo puedas ver desde el propio admin de archibrazo.org sin que toque nada del flujo real.

## Qué hay en este zip

| Archivo | Para qué |
|---|---|
| `wpcode-snippet.php` | El código que vas a pegar en WPCode Lite (mismo plugin donde vive el snippet del bot Telegram). |
| `wpcode-instrucciones.md` | Pasos detallados de instalación + garantías técnicas de que no rompe nada. |

## TL;DR - cómo lo activás (5 minutos)

1. WP admin de archibrazo.org → **Code Snippets** (o WPCode) → **Add Snippet** → **PHP Snippet**.
2. Title: `Archi Funnel Preview (staging visual)`.
3. Pegar todo el contenido de `wpcode-snippet.php`.
4. Insertion: **Auto Insert** → **Run Everywhere**.
5. **Save & Activate**.

Después abrí esta URL estando logueado como admin:

```
https://www.archibrazo.org/?archi_funnel_preview=1
```

Te carga el prototipo del nuevo checkout a pantalla completa. Tiene un mini-nav abajo a la derecha para saltar entre los 5 pasos (Ticket → Datos → Pagar → Comprobante → Listo).

## Qué NO hace (importante)

- **NO toca WooCommerce**, FooEvents, PeproDev, el snippet del bot, ni ningún hook de orden.
- **NO procesa órdenes reales** - es HTML estático servido en una URL secreta.
- **NO es accesible al público** - usuarios no logueados o no-admins reciben 403.
- **NO aparece en Google** - tiene `noindex, nofollow` y nadie linkea la URL.
- **NO toca DB**, no crea posts, no registra rewrite rules.

## Si querés sacarlo

WPCode → snippet "Archi Funnel Preview" → **Deactivate**. 5 segundos. El sitio queda 100% idéntico al estado actual.

## Si rompe algo (no debería pero)

Avisame a mí (Joaco) por WhatsApp o tirá el snippet a Deactivate. El sitio vuelve al estado de antes sin perder nada.

## Qué hago después de mirarlo

Mandame por WhatsApp o Slack:
- Cosas que te chocan visualmente.
- Si la copy te parece clara o confusa.
- Si hay algún paso que cambiarías.
- Si ves algún edge case que no contemplamos (cliente logueado vs no, tickets múltiples, cupones, etc).

El prototipo es **solo visual** - todavía no está conectado al checkout real. Cuando lo aprobemos, recién ahí Cyn lo baja al theme con CSS+JS sobre el motor que ya tenemos. Esa segunda etapa tiene su propio plan documentado, sin migrar de plugins.

---

Cualquier cosa preguntame. Gracias por revisarlo.
