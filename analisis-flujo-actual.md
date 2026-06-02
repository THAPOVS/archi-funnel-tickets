# Análisis del flujo actual - archibrazo.org checkout

> Basado en 5 screenshots del flujo de compra real (mayo 2026).
> Stack: WordPress + WooCommerce + FooEvents.

## Pasos visibles del flujo actual

1. **Carrito** (`/carrito/` aproximado)
   - Tabla: producto + precio + cantidad + subtotal
   - "Aplicar cupón" + "Actualizar carrito" + total + "Finalizar compra"

2. **Checkout** (`/finalizar-compra/`)
   - Columna izq: "Detalles del ticket" - botón Logout + Nombre/Apellido/Tel/Mail + "Información adicional"
   - Columna der: "Tu pedido" - resumen + sección "¿Cómo abona?" con QR

3. **QR Pago con Transferencia** (mismo checkout, debajo del resumen)
   - QR grande de Pago con Transferencia
   - Label amarilla "ACREDITÁ TU ABONO - Subí tu Comprobante de Transferencia en el próximo paso"
   - Globo amarillo de advertencia: "Tu Ticket se genera una vez que cargues tu COMPROBANTE DE PAGO en el próximo paso"

4. **Confirmación de orden** (sigue dentro del checkout)
   - Texto legal de uso de datos
   - Checkbox de términos
   - Botón **"Ok, Subir Comprobante por $24200"**

5. **(No visible en screenshots)** Pantalla post-orden donde se sube el comprobante (plugin PeproDev según `bot-tickets-troubleshooting.md`).

## Pain points específicos del UX

### 1. No hay sentido de progreso

No existe un stepper visual. El usuario no sabe en qué paso de cuántos está. Aparenta ser un flujo lineal de 1 página + 1 confirmación, cuando en realidad son 4+ pasos lógicos (carrito → datos → pagar → subir comprobante → ticket).

**Consecuencia:** el usuario no anticipa que falta subir el comprobante.

### 2. El botón final es ambiguo

`Ok, Subir Comprobante por $24200`

- "Ok" sugiere aceptación/finalización (≈"listo, hecho").
- "Subir Comprobante" sugiere acción de upload.
- "$24200" sugiere que se está cobrando AHORA.

Las 3 cosas juntas confunden: el usuario que no leyó el cartel amarillo arriba puede pensar que apretar ese botón **es** pagar, no que está confirmando datos y pasando a una pantalla de upload.

### 3. El QR de pago y los datos personales viven en la misma página

El checkout mezcla "completar tus datos" con "acá está el QR para pagar". El usuario:
- Completa datos
- Ve el QR
- Saca screenshot mentalmente "ya lo abro en la app del banco"
- Cierra la pestaña pensando que sigue después
- **Nunca llega a la pantalla de upload del comprobante**

El warning amarillo de "Tu Ticket se genera una vez que cargues tu COMPROBANTE" aparece DESPUÉS del QR, en un globo de tooltip. La gente que ya está mirando el QR no vuelve para arriba a leerlo.

### 4. "Coste del Servicio" no se explica

$2.200 sobre $22.000 = 10%. Sin explicación, el usuario:
- O lo nota y duda ("¿qué cargo es este?")
- O no lo nota y se sorprende en el total

Si es comisión de transferencia / margen del sistema, conviene nombrarlo.

### 5. Visual: tipografía + jerarquía

- 3 familias tipográficas distintas visibles (sans, serif rustica, otra sans bold).
- "Total del carrito" usa serif rústica grande, pero los datos del pedido en checkout son serif también: pierde la jerarquía de "este es el header de la sección".
- Botones rosa→naranja gradiente: ok como acento, pero todos los CTA del flujo tienen el mismo estilo. "Finalizar compra" y "Ok, Subir Comprobante" se ven idénticos, sin progresión visual.

### 6. Mobile (inferido)

En mobile la tabla del carrito y el split izq/der del checkout probablemente colapsan mal. Las screenshots muestran desktop. Hay que verificar pero el theme actual no parece optimizado.

### 7. Sin confirmación de éxito clara post-upload

El flujo termina en "subiste el comprobante" pero no comunica:
- Cuánto va a tardar la aprobación (¿inmediato? ¿hasta 2h?)
- Cómo se entera el cliente (¿mail? ¿WhatsApp?)
- Qué pasa si el comprobante es rechazado

## Lo que sí funciona y NO hay que romper

- El QR de Pago con Transferencia es el método elegido (decisión histórica, evita comisión MP).
- El warning amarillo (aunque mal ubicado) tiene la copy correcta: "Tu Ticket se genera UNA VEZ que cargues tu COMPROBANTE".
- La paleta dark + gradiente rosa-naranja es identitaria del Archi, no es genérica.
- WooCommerce + FooEvents + PeproDev son la base técnica y NO se migra.

## Resumen: 3 fixes con mayor impacto

1. **Stepper visual** en todo el flujo (1/5, 2/5, ...) para que el usuario sepa que falta.
2. **Separar "pagar" de "subir comprobante"** en pantallas distintas, con CTAs diferentes.
3. **Copy del botón post-checkout:** dejar de decir "Ok, Subir Comprobante por $X" y decir algo como **"Continuar al pago →"** (en la pantalla de datos) y luego **"Ya pagué, voy a subir el comprobante →"** (en la pantalla de QR).
