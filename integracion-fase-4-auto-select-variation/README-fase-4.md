# Fase 4 - Auto-select single variation

Fix UX urgente identificado el 2026-06-06 (día del show GUARDIANES).

## El problema

Productos WC con una sola variante disponible (típico de los shows del Archi: solo "Anticipada Online" hasta que se abra la puerta) NO se auto-seleccionan. El dropdown muestra el nombre del atributo como placeholder, pero su `value` está vacío.

Resultado: el botón "Reserva" queda con clase `wc-variation-selection-needed`, opacity 0.5, no responde al click. El cliente cree que ya está seleccionada la variante, intenta apretar Reserva 2-3 veces, y se va.

**Diagnóstico técnico** (Playwright headless contra `archibrazo.org/tickets/showdemusica/`):

```
classes: "single_add_to_cart_button button alt disabled wc-variation-selection-needed"
opacity: "0.5"
variationIdInput: "0"
selectedVariation: null
```

## La fix

Un JS chiquito que detecta el form de variations al cargar y, si hay exactamente UNA opción real en cada `<select name="attribute_X">`, la selecciona y dispara `change` para que WC habilite el botón.

Si hay 2 o más opciones reales (ej. Anticipada + Puerta), no toca nada: el cliente tiene que elegir conscientemente.

## Cómo instalar

1. WP admin → WPCode → Add Snippet → PHP Snippet
2. Title: `Archi Funnel - Auto-select single variation`
3. Pegar el contenido de `archi-funnel-auto-select-variation.php`
4. Insertion: Auto Insert → Frontend Only
5. Save & Activate
6. Refrescar `archibrazo.org/tickets/showdemusica/` → botón Reserva habilitado sin tocar nada

## Verificación post-deploy

Abrir DevTools → Console y correr:

```js
document.querySelector('.single_add_to_cart_button').classList.contains('wc-variation-selection-needed')
```

- `false` → fix andando, botón habilitado.
- `true` → algo no cargó, revisar que el snippet esté activo + cache purgado.

## Por qué no vivía ya en el child theme

El child theme actual (`astra-child-archibrazo`) cubre el rediseño del checkout (carrito → datos → pagar → comprobante → listo). El dropdown de variación está en la página de PRODUCTO, antes del carrito, que no estaba dentro del scope del rediseño original. Esta fix se va a absorber al child theme en la próxima iteración.

## Rollback

WPCode → Deactivate. 5 segundos.
