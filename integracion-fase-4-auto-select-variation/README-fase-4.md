# Fase 4 - Auto-seleccionar variante segun stock

Fix UX urgente identificado el 2026-06-06 (dia del show GUARDIANES). Confirmado tambien en el show del 5/06: misma persona o distinta, mismo bug, anticipadas perdidas.

## El problema (root cause)

Productos WooCommerce con variantes NO tienen ninguna opcion auto-seleccionada al cargar. El dropdown muestra el primer atributo como placeholder (ej. "Anticipada Online") pero su `value` real esta vacio.

Resultado:
- El boton "Reserva" carga con clases `single_add_to_cart_button button alt disabled wc-variation-selection-needed`
- `opacity: 0.5`
- `data-variation_id="0"`
- No responde al click

**El cliente lee "Anticipada Online" en el dropdown** y asume que la variante ya esta elegida. Aprieta Reserva, no pasa nada. Aprieta de vuelta, nada. Se va. Anticipada perdida.

### 2 sub-casos del mismo bug

Lo confirme con Playwright contra el sitio real:

**Caso A: 1 sola variante total** (mayoria de los shows del Archi al lanzar entradas).
Ej: `cumbion-amiguero`, `pop-rock`, `locro` cuando arrancan a la venta. Solo "Anticipada Online" existe.

**Caso B: N variantes pero solo 1 vendible** (caso GUARDIANES 6/6).
Diagnostico contra `archibrazo.org/tickets/showdemusica/`:

```
variations_count: 2
in_stock: 1         <- solo una vendible
selects_count: 1
real_opts_per_select: [2]  <- el dropdown muestra las 2
```

El cliente ve 2 opciones en el dropdown ("Anticipada Online" + "Entradas"), una de las dos esta sold-out pero WC las muestra a las dos. Como ninguna esta seleccionada al cargar, el boton queda trabado igual.

## La fix (cubre ambos casos)

Snippet JS que al cargar:

1. Lee `data-product_variations` del form (JSON que WC embebe).
2. Filtra por `is_in_stock && is_purchasable`.
3. Si queda **una sola variante vendible**, aplica sus atributos a cada `<select name="attribute_*">` y dispara `change`.
4. WC recalcula precio, stock, imagen, y le saca al boton la clase `wc-variation-selection-needed`.

Si hay 2+ variantes vendibles (ej. Anticipada Online + Puerta ambas disponibles), no toca nada. El cliente elige conscientemente.

Si hay 0 vendibles, el producto esta agotado de verdad y el boton sigue gris, como corresponde.

## Donde vive el codigo

**Dos lugares, mismo codigo:**

1. **Child theme oficial** (`child-theme/astra-child-archibrazo/functions.php`, ultimo bloque). Esto es lo que importa: si Joaco usa el child theme en produccion, la fix viaja con el theme.
2. **Snippet WPCode** (`integracion-fase-4-auto-select-variation/archi-funnel-auto-select-variation.php`). Backup standalone, util si el child theme no esta activo o si Joaco prefiere instalarlo via WPCode.

**Solo hace falta UNO.** Si el child theme esta activo, no instales el snippet WPCode (no duplica nada porque el listener es idempotente, pero es ruido).

## Como instalar (en orden de preferencia)

### Opcion A: child theme (recomendado, vida permanente)

El fix ya esta en `functions.php`. Subi el child theme actualizado a produccion:

```sh
cd ~/Documents/Claude/Projects/Archibrazo/funnel-tickets-staging/child-theme
zip -r astra-child-archibrazo.zip astra-child-archibrazo -x "*.DS_Store"
```

Luego en WP admin -> Apariencia -> Temas -> Subir Tema -> reemplazar el child theme actual.

### Opcion B: snippet WPCode (rapido, sin tocar theme)

1. WP admin -> WPCode -> Add Snippet -> PHP Snippet
2. Title: `Archi Funnel - Auto-select variation according to stock`
3. Copiar/pegar el contenido de `archi-funnel-auto-select-variation.php`
4. Insertion: Auto Insert -> Frontend Only
5. Save & Activate

## Verificacion post-deploy

Abrir `archibrazo.org/tickets/showdemusica/` (o cualquier producto activo) en incognito.

**Test 1:** el boton "Reserva" tiene que estar habilitado (gradient rosa-naranja saturado, NO opacity 0.5), sin haber tocado el dropdown.

**Test 2:** en DevTools Console:

```js
document.querySelector('.single_add_to_cart_button').classList.contains('wc-variation-selection-needed')
```

Tiene que dar `false`. Si da `true`, purgar cache (W3 Total Cache / WP Rocket -> Purge All) y refrescar.

**Test 3:** apretar Reserva. Tiene que agregar al carrito y redirigir a `/carrito/` (sin sumarse nuevo evento, esto ya es el flow normal del rediseño).

## Impacto historico estimado

Sin acceso al admin no puedo contar las ordenes perdidas. Pero el bug afecta a todo producto con variantes y al menos 1 cliente del 5/6 lo reporto a Joaco. Asumir como minimo:
- 1 cliente confirmado el 5/6 (reportado a Joaco)
- N clientes potenciales por show, todos los shows con variantes desde que se hizo el rediseño

Cuando este la fix live, vale la pena medir % de page views de producto vs adds-to-cart en Google Analytics para confirmar el lift.

## Rollback

- Opcion A (child theme): revertir el commit del functions.php o subir el zip anterior.
- Opcion B (snippet WPCode): Deactivate desde WPCode admin. 5 segundos.

Cualquiera de las dos, el sitio vuelve al estado pre-fix sin perder nada.
