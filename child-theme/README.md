# Astra Child Archibrazo - Instalación

> Child theme de Astra con el rediseño completo del funnel de tickets. Reemplaza eventualmente a los snippets de WPCode "Archi Funnel - Fase 1" y "Archi Funnel - Fase 2".

## Estructura

```
astra-child-archibrazo/
├── style.css           ← Header WP + CSS de restyling (paleta dark, Fraunces+Manrope, etc)
└── functions.php       ← Enqueue + Google Fonts + stepper + JS multi-step + filters
```

Total ~30 KB descomprimido. Sin dependencias externas (solo Google Fonts vía CDN).

## Por qué un child theme y no más snippets

- **CSS sin `!important` peleas:** el child theme carga DESPUÉS del padre, así que su CSS pisa naturalmente.
- **Mantenible:** todo el código del rediseño vive en un solo lugar versionable.
- **Sobrevive updates de Astra:** las actualizaciones del padre NO pisan el child.
- **Restyling completo:** podemos restilar el thank-you, mi-cuenta, y donde haga falta sin tener que pelear con cache de plugin snippet.

## Lo que cubre

- Páginas del flujo de compra: `/adquiridos/` (cart), `/finalizar-compra/`, `/order-pay/`, `/order-received/`
- Páginas de cuenta del cliente: `/mi-cuenta/` y todos sus endpoints
- El resto del sitio (home, eventos, talleres, contacto) **NO se toca** - sigue con look Astra original

## Lo que NO toca

- WooCommerce: form de checkout, nonce, customer creation, hooks de orden
- FooEvents: generación de PDF, app Check-Ins, mails
- PeproDev: form de upload del comprobante
- Snippet 37364 (bot Telegram): sigue funcionando idéntico
- Make.com escenarios A y B

---

## INSTALACIÓN (5 minutos)

### Paso 1 - Generar el zip

En tu Mac, en Terminal:

```bash
cd ~/Documents/Claude/Projects/Archibrazo/funnel-tickets-staging/child-theme/
zip -r astra-child-archibrazo.zip astra-child-archibrazo/
```

Eso crea `astra-child-archibrazo.zip` en la misma carpeta.

### Paso 2 - Subirlo a WNPower

**Opción A - vía WP Admin (más simple):**

1. WP Admin → **Apariencia → Temas** → botón **"Añadir nuevo"**
2. Botón **"Subir tema"** arriba
3. Seleccionar `astra-child-archibrazo.zip` → **Instalar ahora**
4. NO le des "Activar" todavía - solo dejalo instalado.

**Opción B - vía cPanel File Manager:**

1. cPanel WNPower → **File Manager**
2. Navegar a `/public_html/wp-content/themes/`
3. **Upload** → subir el zip
4. Botón derecho sobre el zip → **Extract**
5. Eliminar el zip cuando termine

### Paso 3 - Verificar que aparece pero NO activar

WP Admin → **Apariencia → Temas** → deberías ver "Astra Child Archibrazo" en la lista. **No le des Activate todavía.**

---

## ACTIVACIÓN (en 2 etapas, conservador)

El child theme arranca en modo **admin-only** por default (igual que los snippets). Activarlo NO afecta a visitantes públicos.

### Etapa 1 - Activar en modo admin-only

1. WP Admin → **Apariencia → Temas** → click sobre **"Astra Child Archibrazo"** → botón **"Activar"**
2. Abrir el sitio en una pestaña LOGUEADO como admin → ir a un evento → agregar al carrito → checkout. Tenés que ver el rediseño completo.
3. Abrir otra pestaña en **incógnito** (sin sesión) → mismo flujo. Tenés que ver el sitio IDÉNTICO al actual (sin rediseño).
4. Si las 2 cosas se cumplen: la activación está OK.
5. Si algo se rompe: volver a Apariencia → Temas → **activar Astra** padre de nuevo. 5 segundos de rollback.

### Etapa 2 - Pasar a live (cuando estés convencido)

Esto se hace editando UNA línea del `functions.php` del child theme.

1. WP Admin → **Apariencia → Editor de archivos del tema** (o vía FTP/cPanel File Manager en `/wp-content/themes/astra-child-archibrazo/functions.php`)
2. Buscar la línea (cerca del inicio):
   ```php
   define('ARCHI_FUNNEL_ADMIN_ONLY', true);
   ```
3. Cambiarla a:
   ```php
   define('ARCHI_FUNNEL_ADMIN_ONLY', false);
   ```
4. Guardar el archivo.
5. Hard reload del sitio público en incógnito → ahora SÍ deberías ver el rediseño live para todos.

**Rollback de la Etapa 2:** volver a poner `true` en la constante. 5 segundos.

## DESPUÉS DE ACTIVAR (limpieza opcional)

Una vez que el child theme esté activo y funcionando, podés **DESACTIVAR los snippets de WPCode**:

1. WP Admin → **Fragmentos de código**
2. Toggle a "Inactivo" en:
   - `Archi Funnel - Fase 1 (admin-only)` (ID 37636)
   - `Archi Funnel - Fase 2 (admin-only)` (ID 37640)

Los snippets quedan guardados pero no se ejecutan. Todo lo que hacían lo hace ahora el child theme.

**NO los borres todavía.** Dejalos inactivos por 1-2 semanas como backup. Si el child theme se rompe por algún motivo, reactivás los snippets y vuelve la funcionalidad básica.

---

## ROLLBACK COMPLETO

Si en cualquier momento querés volver al estado pre-rediseño:

1. WP Admin → **Apariencia → Temas** → activar **"Astra"** (padre)
2. WP Admin → **Fragmentos de código** → desactivar los 2 snippets de "Archi Funnel"

El sitio vuelve idéntico al estado previo al rediseño. Sin pérdida de datos, sin órdenes afectadas.

---

## TROUBLESHOOTING

### "Se ve igual que antes - el child theme no parece cargar"

- Verificar que esté activado en Apariencia → Temas (no solo instalado)
- Limpiar cache de plugin de cache si existe (W3 Total Cache, WP Rocket, etc)
- Hard reload (Cmd+Shift+R) en el browser
- Verificar en DevTools que el archivo CSS del child se está cargando: `view-source:` y buscar `astra-child-archibrazo`

### "El sitio se rompió, no se ve nada"

Activar Astra de vuelta inmediatamente (Apariencia → Temas → Astra → Activar). El child se desactiva automáticamente.

### "Faltan estilos en alguna página específica"

El child theme apunta a selectors específicos de body class: `body.woocommerce-cart`, `body.woocommerce-checkout`, etc. Si Astra tiene un body class diferente en alguna página, hay que ajustar el CSS. Mandar screenshot + URL y lo ajusto.

### "Conflicto con un plugin"

Algunos plugins de optimización (cache + minify CSS/JS) pueden romper el orden de carga. Verificar:
- WP Rocket: en "File Optimization", agregar `style.css` del child theme a "Excluded CSS files"
- Autoptimize: lo mismo

---

## PRÓXIMAS ITERACIONES (si querés ir más lejos)

Cuando este child theme esté estable, se pueden agregar:

### 1. Sobrescritura de templates de WC

Crear `astra-child-archibrazo/woocommerce/checkout/form-checkout.php` con HTML adaptado del prototipo. Eso permite reorganizar la estructura del checkout sin pelear con CSS, replicando el prototipo casi 1:1.

### 2. Restyling de home + eventos + talleres

Extender el CSS para incluir `body.home`, `body.page-id-XX`, etc. Da coherencia visual a todo el sitio.

### 3. Logo + tipografía global

Mover Fraunces a headings de todo el sitio (no solo WC). Cambia la identidad visual general - decisión de diseño/cooperativa.

### 4. Versionado con git

Inicializar el folder del child theme como repo git, así cada cambio queda versionado. Idealmente con un workflow de deploy via FTP/SSH desde una rama `main` después de PR review.
