# Propuesta funnel rediseñado

## Resumen ejecutivo

Pasamos de un flujo de **1 página densa + 1 confirmación opaca** (estado actual) a un flujo de **5 pasos explícitos** con stepper visible. La copy de cada CTA deja clarísimo qué acción dispara y qué viene después. La pantalla de "subir comprobante" queda como paso terminal obligatorio, no como afterthought.

## Principio rector de implementación

El rediseño es **UI-only**. WooCommerce sigue siendo el dueño del checkout, la orden, el customer, el estado y los hooks. FooEvents sigue generando los tickets. PeproDev sigue manejando el upload. El bot Telegram + Make.com + Gemini siguen aprobando como hasta hoy.

Los 5 pasos del prototipo son **una capa visual (CSS + JS) sobre las páginas reales de WP que ya existen**:

| Paso del prototipo | Página real de WordPress | Estado de la orden |
|---|---|---|
| 1 - Tu ticket | `/carrito/` | (sin orden todavía) |
| 2 - Tus datos + 3 - Pagá | `/finalizar-compra/` (un solo form, multi-step UI) | orden creada con estado `receipt-upload` al submit final |
| 4 - Subí el comprobante | `/order-pay/<id>/` (con form de PeproDev) | orden pasa a `receipt-approval` al upload |
| 5 - Listo | thank-you page | la orden queda en `receipt-approval` hasta que la apruebe el bot/coope, después pasa a `completed` y FooEvents manda el ticket |

Detalles técnicos completos en [aplicacion-wordpress.md](./aplicacion-wordpress.md).

## Los 5 pasos

### Paso 1/5 - Tu ticket

> "Revisá lo que vas a llevarte"

- Producto + foto + variante elegida
- Cantidad editable (-/+)
- Precio unitario, subtotal, cargo del servicio explicado en hover/inline ("10% para sostener el sistema de tickets"), total
- Cupón colapsable ("¿Tenés un código?")
- **CTA primario:** "Continuar →"
- **Link secundario:** "Seguir comprando"

### Paso 2/5 - Tus datos

> "Para mandarte el ticket"

- Nombre, apellido, mail, teléfono
- Mensaje arriba: "Los datos quedan asociados a tu ticket. Si entrás con cuenta, los precargamos."
- **CTA primario:** "Continuar al pago →"
- **Link secundario:** "← Volver al carrito"

### Paso 3/5 - Pagá por transferencia

> "Escaneá el QR desde tu app del banco"

- Card grande con QR centrado
- Debajo del QR: alias **ARCHICOOP**, monto exacto a transferir, botón "Copiar alias"
- Instrucciones numeradas (1. Escaneá / 2. Pagá desde tu app / 3. Sacá screenshot del comprobante)
- **Banner crítico arriba:** "⚠️ Tu ticket NO está confirmado hasta que subas el comprobante en el próximo paso"
- **CTA primario:** "Ya pagué, voy a subir el comprobante →"
- **Link secundario:** "← Volver"

### Paso 4/5 - Subí tu comprobante

> "Último paso. Sin esto no se genera el ticket."

- Drag & drop area + botón "Elegir archivo" (acepta PDF, JPG, PNG)
- Preview del archivo subido + botón "Cambiar"
- Texto: "Aceptamos screenshot del comprobante o PDF de tu banco."
- Checkbox de términos
- **CTA primario:** "Enviar comprobante y finalizar →" (disabled hasta que haya archivo + términos)
- **Link secundario:** "← Volver al QR"

### Paso 5/5 - Listo, recibimos tu pago

> "Te avisamos cuando esté confirmado"

- Ícono grande de check
- Mensaje: "Recibimos tu comprobante. En cuanto lo verifiquemos te llega el ticket al mail **joaquinpovina@gmail.com** (suele ser en minutos, máximo 2 horas)."
- Resumen del pedido (producto, evento, fecha del show)
- Sección "Mientras tanto":
  - Botón "Sumate al grupo de Telegram del evento" (opcional, si aplica)
  - Botón "Ver agenda completa →"
  - Botón "Volver al home"

## Reglas de diseño del prototipo

### Stepper visual

Siempre visible arriba: `● ● ● ○ ○` con labels (Ticket · Datos · Pagar · Comprobante · Listo). Pasos completados verde, paso actual rosa-naranja, pendientes grises. Click en pasos pasados los reabre.

### Jerarquía de CTAs

- **Primario** (continuar al siguiente paso): gradiente rosa-naranja, grande, full-width en mobile.
- **Secundario** (volver, cancelar): texto plano subrayado o ghost button.
- **Crítico** (advertencias): banner rojo/amarillo con ícono, NO dismiseable hasta avanzar.

### Tipografía

- Display: **Fraunces** (Google Fonts, serif moderno con personalidad - reemplaza la "tipo Archi" actual hasta que se incorpore Andralis ND oficialmente al theme).
- Body: **Manrope** (sans humanista, alta legibilidad).

### Paleta (custom, no Tailwind default)

- Background base: `#0a0a0a` (casi negro, mantiene identidad dark del Archi)
- Surface elevada: `#161616`
- Surface flotante: `#1f1f1f`
- Texto primario: `#f5f1ea` (off-white cálido)
- Texto secundario: `#a8a29e`
- Acento primario (gradiente CTA): `#ff3d7f → #ff8a3d` (similar al Archi original pero más controlado)
- Acento crítico: `#fbbf24` (warning amarillo)
- Verde éxito: `#34d399`
- Borde sutil: `rgba(245, 241, 234, 0.08)`

### Sombras

Layered + tintadas:
- `box-shadow: 0 1px 0 rgba(245,241,234,0.04) inset, 0 20px 40px -20px rgba(255,61,127,0.25), 0 8px 16px -8px rgba(0,0,0,0.6)`

### Mobile-first

Todo el flujo se diseña primero para 375px. Cards full-width, stepper colapsa a "Paso 3 de 5", CTAs sticky en bottom.

### Honestidad de copy

Nunca decir "Tu pago está confirmado" hasta que un humano apruebe el comprobante. La pantalla 5 dice "Recibimos tu comprobante" (cierto) y promete el ticket "cuando lo verifiquemos" (cierto). El usuario se entera por mail.

## Decisiones que dejo pendientes

1. **Auto-aprobación de comprobante con OCR/IA:** no propongo nada en este prototipo. Ortogonal al rediseño UX.
2. **Integración con bot Telegram:** el bot está afuera del prototipo (es para el equipo, no para el cliente). Aplica idéntico al backend.
3. **MODO Empresas:** si en algún momento se suma como gateway, los pasos 3 y 4 colapsan en uno: "Pagá con MODO" → directo a paso 5. El stepper se acorta a 4/4. Fuera de alcance acá.
