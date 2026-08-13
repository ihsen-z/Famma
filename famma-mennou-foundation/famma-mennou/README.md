# FAMMA MENNOU — local development

E-commerce platform for **FAMMA** (فمّا) — Tunisia, COD-first, mobile-first.
Slogan: **فمّا ديما حاجة جديدة** / *Il y a toujours quelque chose de nouveau.*

> **Scope of this repository.** This is the environment-independent **foundation**:
> the Docker stack, env/git scaffolding, and the `famma-core` custom plugin that
> encodes the business rules (order workflow, cost×3 pricing + margins, Tunisia
> COD/shipping/phone rules, WhatsApp, env-driven tracking). WordPress core and the
> theme are **not** committed — they are installed at runtime / chosen on-site.
> The code here was authored to spec but has **not** yet been run against a live
> WordPress+WooCommerce install; treat the first activation as a test.

## Requirements
- Docker Desktop (with the Compose plugin)
- Git

## First run
```bash
cp .env.example .env
# edit .env: set strong DB passwords. Leave tracking/WhatsApp blank for now.
docker compose up -d
```

Then open:

| Service      | URL                     | Notes                                   |
|--------------|-------------------------|-----------------------------------------|
| WordPress    | http://localhost:8080   | run the famous 5-minute install         |
| phpMyAdmin   | http://localhost:8081   | dev only                                |
| Mailpit      | http://localhost:8025   | catches all outgoing mail locally       |

## After WordPress installs
1. **Plugins → Installed:** activate **WooCommerce**, then **FAMMA Core**.
   (FAMMA Core refuses to load without WooCommerce and says so.)
2. Run the WooCommerce setup wizard: **Store = Tunisia, Currency = TND (د.ت),
   Selling location = Tunisia only**, enable **Cash on Delivery**, make COD the
   default (§11–§12).
3. **WooCommerce → Settings → Shipping:** add a zone "Tunisie / التوصيل" with a
   **Flat rate = 0**. (The real 7 TND is tracked internally by FAMMA Core, not
   charged — §3/§4.)
4. Delete any WooCommerce **sample products** — the catalog must start empty (§58).
5. **WooCommerce → Settings → General:** you'll see the FAMMA block (price
   multiplier, internal shipping cost).
6. Language / RTL: install **WordPress core language** + a multilingual approach
   documented in `docs/ARCHITECTURE.md` (kept out of code so you can pick the
   free option deliberately — §5).

## What each product now supports
Open any product → **Product data → General**: a private *FAMMA financials*
panel (cost, internal shipping, handling, acquisition) with a live margin
preview. Leave the selling price blank and it auto-fills to **cost × 3**; type a
price and your manual value always wins (§3).

## Order workflow
Custom statuses are live: Pending confirmation → Confirmed → Processing →
Shipped → Delivered → Completed, plus Failed delivery / Returned / Refused /
Cancelled / Refunded. **COD is only marked paid at "Delivered"** (§12). Full map:
`docs/ORDER-WORKFLOW.md`.

## Backups before any change (§36)
```bash
scripts/backup.sh    # dumps DB + uploads into ./backups (git-ignored)
```

## Security notes
- `.env` is git-ignored; `META_ACCESS_TOKEN` is **never** sent to the browser.
- `DISALLOW_FILE_EDIT` and `WP_ENVIRONMENT_TYPE=local` are set in the WP config.
- Turn `WP_DEBUG=0` before any staging/production deploy.

## Docs
`docs/ARCHITECTURE.md`, `docs/ORDER-WORKFLOW.md`, `docs/PLUGIN-REGISTER.md`,
`docs/DESIGN-SYSTEM.md`.
