# Architecture

## Principle
Keep WordPress core, WooCommerce core, and the theme **unmodified** (§27). All
FAMMA business logic lives in one versioned custom plugin, `famma-core`. Only
`wp-content/plugins/famma-core` is committed to Git; core and third-party code
are installed at runtime and ignored.

## Config flow (no hard-coded secrets)
`.env` → docker-compose `environment:` → `wp-config` `define()` (via
`WORDPRESS_CONFIG_EXTRA`) → `Famma\Core\Config` reads the constants. The Meta
access token is defined server-side and is **never** enqueued to the browser
(only the pixel *ID* is). Adding a real value later = edit `.env`, `docker
compose up -d`. No code change.

## famma-core layout
```
famma-core.php              bootstrap + autoloader + WooCommerce/HPOS guards
includes/class-config.php           reads env-fed constants + options
includes/class-order-statuses.php   custom COD order workflow
includes/class-product-financials.php  cost×3, margin fields, admin panel
includes/class-shipping.php         0 TND customer / 7 TND internal + settings
includes/class-tunisia.php          governorates dropdown + phone validation
frontend/class-whatsapp.php         floating button + dynamic wa.me links
tracking/class-tracking.php         GA4/Meta/TikTok (ID-only) + UTM + delivery Purchase
```

## Language / RTL (§5) — decision deferred on purpose
Two free paths, documented so you choose deliberately rather than defaulting:
1. **Two-language via free plugin** (e.g. a lightweight free i18n plugin) — full
   FR + Derja content per page, language switcher.
2. **Locale + RTL only** — WordPress `ar` locale drives RTL; commercial copy is
   authored bilingually in the theme/blocks.
Derja copy is authored by a human — never machine-translated (§5). Strings in
`famma-core` are wrapped in `__()` so `.po/.mo` files can localise the UI parts.

## Migration path (§43)
Local → staging → production. Because only `famma-core` + config are custom,
migration = move DB + uploads, set production constants in the server env, set
`WP_DEBUG=0`, `WP_ENVIRONMENT_TYPE=production`. See DEPLOYMENT when written.
