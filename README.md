# archi-funnel-tickets

Sistema de venta de tickets de El Archibrazo (archibrazo.org). Funnel rediseñado de 5 pasos sobre WordPress + WooCommerce + FooEvents + PeproDev + bot Telegram con IA Gemini para aprobación de comprobantes.

> **Empezá por [CLAUDE.md](./CLAUDE.md).** Es la fuente única de verdad: arquitectura, flow, estados de orden, automatización de mails, bot, lessons learned y notas para migrar a otro stack (ej. Cardano + MeshJS).

---

## Mapa del repo

```
.
├── CLAUDE.md                   ← LEER PRIMERO. Doc maestro del sistema.
│
├── propuesta-funnel.md         Spec UX/UI de los 5 pasos
├── aplicacion-wordpress.md     Cómo bajar el spec a WP sin romper nada
├── analisis-flujo-actual.md    Auditoría del flow pre-rediseño
├── no-romper-nada-checklist.md Verificaciones pre-deploy
├── PLAN-automatizacion-mails.md Plan original del módulo de mails
├── README-para-alex.md         Handoff al equipo
├── HANDOFF-*.md                Handoffs de sesiones de trabajo
├── wpcode-instrucciones.md     Cómo instalar los snippets WPCode
│
├── child-theme/astra-child-archibrazo/    ← TODO el rediseño UI vive acá
│   ├── functions.php           ~2.8k líneas: hooks, multi-step UI, stepper
│   ├── style.css               ~1.8k líneas: CSS scopeado al checkout
│   ├── inc/archi-emails.php    Módulo de 5 mails automáticos
│   ├── fonts/                  Andralis ND OTFs (Juan Andralis 1966)
│   ├── emails/                 Templates HTML de los 5 mails
│   └── woocommerce/            Overrides de templates WC
│
├── integracion-fase-1/         Snippet WPCode: stepper + copy
├── integracion-fase-2/         Snippet WPCode: multi-step UI checkout
├── integracion-fase-3-traducciones/  Snippet WPCode: gettext map EN→ES PeproDev
│
├── emails/                     Templates iniciales de mails (versión preview)
│
├── bot/                        Bot Telegram + Make.com + Gemini
│   ├── plan-tickets-nivel1-bot-telegram.md   Spec del bot
│   ├── bot-tickets-troubleshooting.md        8 bugs documentados con fix
│   ├── snippet-order-receipt-webhook.php     WPCode que dispara el webhook
│   ├── scenario-A-blueprint-with-gemini.json Make blueprint: notificar pendiente + IA
│   └── scenario-B-blueprint-revoke-2step.json Make blueprint: procesar aprobación
│
└── screenshots/                Capturas finales de cada paso (para deck del equipo)
```

---

## Secretos y credenciales

Los archivos de este repo están **sanitizados**: webhook URLs, tokens y API keys reemplazados por placeholders tipo `<TELEGRAM_BOT_TOKEN>`, `<GEMINI_API_KEY>`, `https://hook.<region>.make.com/<REPLACE_ME>`.

Los valores reales viven solo en el archivo local `secrets/bot-tickets-credentials.md` de la máquina del project lead (Joaco), **fuera de este repo** y excluido por `.gitignore`.

Si necesitás esos valores para reproducir el sistema, pedírselos por canal privado, nunca por commit.

---

## Estado actual (a 2026-06-02)

- Rediseño del checkout: live en archibrazo.org desde mayo 2026
- Bot Telegram + Make.com: live, procesando órdenes diarias
- Gemini auto-aprobación: activa, threshold conservador (monto exacto, no duplicado, confidence >=85)
- Módulo de 5 mails automáticos: live en modo `live` desde el child theme
- Detección de duplicados: implementada en el endpoint propio + rama DUPLICADO del router Make

---

## Para migrar a otro stack

Leé la sección **§10 "Notas para una reescritura en Cardano + MeshJS"** del [CLAUDE.md](./CLAUDE.md). Cubre qué sigue siendo válido (lógica de negocio), qué desaparece (PeproDev, OCR de comprobantes), qué aparece (wallet, smart contract, NFT-ticket) y 5 trade-offs honestos a discutir antes de invertir.

---

## Contacto

Boletería pública: **boleteria@archibrazo.org**
Project lead del rediseño: Joaquín Poviña (Síndico Titular Archicoop)
