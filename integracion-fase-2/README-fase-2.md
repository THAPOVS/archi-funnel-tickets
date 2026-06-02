# Integración Fase 2 - multi-step UI + restyling

> Snippet adicional sobre Fase 1. Activa el multi-step UI del checkout (oculta "Pagar" hasta apretar "Continuar al pago") + CSS de restyling completo. Mismo guardrail admin-only que Fase 1.

## Pre-requisito

La Fase 1 (`Archi Funnel - Fase 1 (admin-only)`) tiene que estar **activa** en WPCode antes de pegar esto. La Fase 2 reutiliza la función `archi_funnel_should_apply()` definida en Fase 1.

Si pegás Fase 2 sin tener Fase 1 activa, el snippet detecta la situación y muestra un aviso en el WP admin: "Archi Funnel Fase 2: requiere que la Fase 1 esté activa". No rompe nada, solo no hace nada.

## Qué hace este snippet

- **Oculta el bloque "Tu pedido + pago"** hasta que apretás un botón.
- Inserta un botón **"Continuar al pago →"** después de los campos de datos.
- Insert un link **"← Volver a tus datos"** arriba del order_review (visible solo cuando estás en paso 3).
- Cuando apretás continuar, **valida client-side** que los campos required estén llenos. Si falta algo, muestra mensaje rojo + scroll al campo vacío.
- **Sincroniza el stepper** visual con el step activo (paso 2 = Datos, paso 3 = Pagar).
- Restilea el form de WC con la paleta del Archi (sin tocar el JS de WC).

## Qué NO hace

- **No reemplaza** el form de WC ni el submit nativo.
- **No toca** PeproDev, FooEvents, bot Telegram, gateway QR, hooks de orden.
- **No valida server-side** (eso lo sigue haciendo WC). La validación client-side es solo para no avanzar de paso si hay errores obvios.

## Instalación

1. WP admin → **Code Snippets** (WPCode) → **Add Snippet** → **PHP Snippet**
2. Title: `Archi Funnel - Fase 2 (admin-only)`
3. Pegar el contenido de [`archi-funnel-fase-2.php`](./archi-funnel-fase-2.php)
4. Insertion: **Auto Insert** → **Run Everywhere**
5. **Save & Activate**

## Cómo probarlo

Estando logueado como admin:

1. Agregar ticket al carrito → ir a `/finalizar-compra/`.
2. Verás solo los **datos personales** (nombre, mail, tel, etc) + botón **"Continuar al pago →"** abajo a la derecha. El bloque "Tu pedido" + QR + términos + "Place order" está OCULTO.
3. Dejar un campo required vacío (ej. mail). Click en "Continuar al pago". Debería:
   - Marcar el campo en rojo con la clase `woocommerce-invalid`
   - Mostrar mensaje "Faltan datos en N campos"
   - Scrollear al primer campo vacío
   - NO avanzar
4. Completar todos los campos. Click "Continuar al pago". Debería:
   - Ocultar datos personales
   - Mostrar el bloque del QR + términos + botón "Ya pagué, voy a subir el comprobante →"
   - Stepper visual marca paso 3 con verde el 1 y 2
   - Scroll arriba
   - Aparece link "← Volver a tus datos" sobre el order_review
5. Apretar "← Volver a tus datos". Debería:
   - Ocultar el order_review
   - Volver a mostrar los datos personales (con todo lo que cargaste, no se borra)
   - Stepper marca paso 2 como current
6. Volver a "Continuar al pago", apretar el botón final "Ya pagué...". Debería:
   - Crear la orden normalmente
   - Redirigirte a `/order-pay/<id>/` con el form de PeproDev (banner crítico de Fase 1 visible arriba)
7. Subir comprobante de prueba → thank-you con copy de Fase 1.

## Edge cases que cubre

| Escenario | Comportamiento |
|---|---|
| Cliente cambia país / código postal en step 2 (dispara AJAX `update_order_review`) | WC actualiza solo `#order_review`. El botón "Continuar" sigue en su lugar. El JS re-inyecta el botón "Volver" porque vive al lado de `#order_review_heading`. |
| Cliente cambia gateway de pago en step 3 | WC actualiza order_review. Step sigue siendo 3, no hay regresión. |
| Cliente refresca la página en step 3 | Vuelve al step 2 (estado inicial). Aceptable. |
| JS se rompe (error en consola) | El form de WC sigue funcionando vanilla con todos los campos visibles (sin ocultar nada). El submit nativo funciona. |
| Cliente quita JavaScript del navegador | Form de WC vanilla, 1 página densa como hoy. El snippet de Fase 1 (stepper + copy) sigue funcionando porque es PHP. |
| Plugin de terceros sobreescribe `#order_review` | Si usa el mismo selector, sigue funcionando. Si cambia el ID, hay que actualizar el JS. |

## Si algo se rompe

1. **El multi-step no funciona pero el form sí** → revisar consola del navegador. Probablemente algún plugin pisa los selectors. Deactivate la Fase 2 (la Fase 1 sigue activa, sitio queda con stepper + copy nueva pero sin multi-step).
2. **El form no se submitea** → Deactivate Fase 2 inmediato. La Fase 1 NO toca el submit, así que el problema es de Fase 2.
3. **Visitantes públicos ven el rediseño cuando no deberían** → confirmar que `archi_funnel_should_apply()` retorna false para no-admins. Probablemente cambió la lógica de capability de WP.

## Cuando Fase 1 + Fase 2 te convencen

Pasar a **Fase 3 (deploy final)**:

1. Editar el snippet de Fase 1 en WPCode.
2. Buscar la función `archi_funnel_should_apply()`.
3. Cambiar el body a `return true;` (o sacar el guardrail entero).
4. Save. Ya está live para todos.

Antes de hacer ese cambio:
- Recomendado: hacer un staging clonado con Cyn y testear con el bot Telegram apuntando a un grupo de prueba (no contaminar el grupo real).
- Verificar el checklist completo en [`../no-romper-nada-checklist.md`](../no-romper-nada-checklist.md).
- Hacer el rollout en franja de bajo tráfico (martes 10-12hs).

## Archivos en este paquete

- `archi-funnel-fase-2.php` - snippet a pegar en WPCode
- `README-fase-2.md` - este archivo
