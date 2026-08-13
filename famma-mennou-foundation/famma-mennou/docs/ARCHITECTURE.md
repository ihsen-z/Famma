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

## famma-core layout — état réel

Le plugin est un **fichier unique consolidé**, délibérément : pas d'autoloader,
donc aucun chargement croisé ne peut échouer, et aucun risque de définir deux
fois la même classe (cause n°1 d'erreur fatale à l'activation). Ce choix est
verrouillé par la règle 7 de `ClaudeMemo.md`.

```
famma-core.php   (313 l.)   un seul fichier, namespace Famma\Core, singletons
  ├── Config              lit les constantes issues de .env + les options WP
  ├── Order_Statuses      workflow COD personnalisé
  ├── Product_Financials  coût×3, champs de marge, panneau admin
  ├── Shipping            0 TND client / 7 TND interne + réglages
  ├── Tunisia             gouvernorats + validation du téléphone
  ├── WhatsApp            bouton flottant + liens wa.me dynamiques
  └── Tracking            GA4/Meta/TikTok (ID seul) + UTM + Purchase à la livraison
```

> Le découpage en `includes/`, `frontend/`, `tracking/` décrit par le §27 reste
> l'objectif à terme. Il ne sera fait qu'une fois les règles métier vertes en
> Phase 3 : scinder un fichier avant d'avoir un test de bout en bout est le
> meilleur moyen d'introduire un doublon de classe sans s'en apercevoir.

## Qualité du code
`composer.json` + `phpcs.xml.dist` + `scripts/lint.sh` : `php -l` et `phpcs`
(standard WordPress + PHPCompatibility) tournent dans un conteneur jetable,
sans rien installer sur la machine.

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
