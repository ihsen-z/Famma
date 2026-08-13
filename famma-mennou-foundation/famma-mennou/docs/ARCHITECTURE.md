# Architecture

## Principle
Keep WordPress core, WooCommerce core, and the theme **unmodified** (§27). All
FAMMA business logic lives in one versioned custom plugin, `famma-core`. Only
`wp-content/plugins/famma-core` is committed to Git; core and third-party code
are installed at runtime and ignored.

## Config flow (no hard-coded secrets)

`.env` → docker-compose `environment:` → **`wp-content/mu-plugins/famma-env.php`**
`define()` → `Famma\Core\Config` reads the constants. Adding a real value
later = edit `.env`, `docker compose up -d`. No code change.

The Meta access token is defined server-side and is **never** enqueued to the
browser (only the pixel *ID* is). Verified by grepping the rendered HTML.

> **Why a mu-plugin and not `WORDPRESS_CONFIG_EXTRA`.** The official WordPress
> image writes `wp-config.php` only when the volume is empty, and never
> regenerates it. Anything added to `WORDPRESS_CONFIG_EXTRA` after the first
> `docker compose up` therefore never reaches PHP. This was verified the hard
> way: the container had the variable, and `wp-config.php` contained not a
> single `FAMMA_` constant — nor even `WP_HOME`. A mu-plugin loads before every
> plugin and is immune to how the config file was generated.
>
> `getenv()` is used rather than `$_ENV`: PHP's `variables_order` defaults to
> `GPCS`, which does not populate `$_ENV`, while `getenv()` always reads the
> real process environment.

`WP_ENVIRONMENT_TYPE` needs no constant at all — WordPress core reads that
environment variable directly.

## Local mail
The WordPress image ships no mail transport, so every transactional email
failed with `sendmail: can't connect`. `wp-content/mu-plugins/famma-local-mail.php`
points PHPMailer at the Mailpit container, guarded on
`wp_get_environment_type() === 'local'` so it can never act elsewhere. Mailpit
is deliberately **not** behind the `tools` profile for this reason.
Inbox: `http://localhost:8025`.

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

## Tableau de bord marchand (§28)

Menu **FAMMA** dans l'administration, capacité `manage_woocommerce`, chargé
uniquement quand `is_admin()` est vrai.

**Le chiffre d'affaires ne compte que les commandes livrées.** Une commande
COD n'est pas de l'argent tant que l'espèce n'est pas encaissée : les chiffres
diffèrent donc volontairement des rapports WooCommerce, qui comptent une
commande passée comme du revenu. La page le dit explicitement à l'écran.

Marge estimée = total livré − (coût produit × quantité + manutention +
acquisition + coût interne de livraison stampé sur la commande).

Deux garde-fous de performance (règle 10) :

- les figures sont mises en cache dans un transient pendant 5 minutes, avec
  un bouton « recalculer » protégé par nonce ;
- la lecture est plafonnée à 1000 commandes par fenêtre. **Quand le plafond
  est atteint, la page l'affiche** au lieu de présenter un total partiel
  comme s'il était complet.

Vérifié à l'exécution sur une commande réelle : produit à 30 TND de coût
vendu 90, quantité 2, livraison interne 7 → CA 180, coût 67, marge 113, et
la source d'acquisition remontée depuis l'UTM.

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
