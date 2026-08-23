# Site de développement sous Local — procédure exécutée

Ce document décrit ce qui a été **réellement fait** le 22/08/2026 pour faire
tourner FAMMA sous **Local (Local by Flywheel)**, et comment le rejouer. La
décision et ses contraintes sont dans `DECISIONS.md`, ADR-006.

Périmètre volontairement limité : **design, paramétrage, structure**. Aucune
donnée de test n'a été migrée — ni produit, ni client, ni commande, ni
médiathèque. La stack Docker du dépôt n'a **pas** été touchée : elle n'a même
pas été démarrée.

## Environnement obtenu

| Élément | Docker (référence) | Local (développement) |
|---|---|---|
| Site | `http://localhost:8080` | `http://famma.local` (site `famma`, id `hrdMIgNPV`) |
| Serveur web | Apache | nginx 1.26.1 |
| PHP | 8.3 | **8.3.29** (identique à la cible de production) |
| Base | MariaDB 11.4 | MySQL 8.0.35, base `local`, `root`/`root` |
| Mailpit | `mailpit:1025`, UI `:8025` | SMTP **10007**, UI **http://localhost:10006** |
| `max_input_vars` | 3000 (à régler) | **4000 par défaut** — rien à faire |
| `memory_limit` | 256M | **256M par défaut** |
| Admin | `famma_admin` | `admin` |

Les ports Mailpit et MySQL sont **attribués par site** par Local : les relire
dans `%APPDATA%\Local\sites.json` plutôt que les recopier d'ici.

## 1. Site Local

Créé depuis l'interface (seule voie : Local n'expose pas de CLI) — *Create a new
site* → `famma` → *Custom* → PHP 8.3.29, nginx, MySQL 8.0.35, langue
**Français**. La langue choisie ici compte : elle décide des slugs du contenu de
démonstration (voir §5).

## 2. Code du dépôt monté par jonctions

Aucun fichier n'est copié : le dépôt reste la source unique.

```bat
set "SRC=E:\Ecom\famma-mennou-foundation\famma-mennou\wp-content"
set "DST=C:\Users\DELL\Local Sites\famma\app\public\wp-content"
mklink /J "%DST%\plugins\famma-core" "%SRC%\plugins\famma-core"
mklink /J "%DST%\themes\famma-child" "%SRC%\themes\famma-child"
mklink /J "%DST%\mu-plugins"         "%SRC%\mu-plugins"
```

Une jonction de répertoire ne demande pas les droits administrateur (contrairement
à un lien symbolique) et fonctionne d'un volume à l'autre (`C:` → `E:`).
Contrôle : `Get-Item` affiche `ReparsePoint`, et CSS, JS, polices et SVG du thème
répondent 200.

## 3. Constantes d'environnement

Local n'a pas d'environnement de conteneur. Les constantes `FAMMA_*` sont donc
définies dans `~/Local Sites/famma/app/public/wp-config.php` — fichier hors
dépôt, aucun secret versionné — avant le `if ( ! defined( 'WP_DEBUG' ) )` que
Local y place :

- `WP_DEBUG` à `true`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY` à `false`,
  `DISALLOW_FILE_EDIT`, `WP_MEMORY_LIMIT`, `AUTOMATIC_UPDATER_DISABLED` ;
- les 15 constantes `FAMMA_*`, **toutes vides** : une valeur vide = fonctionnalité
  proprement désactivée (§60) ;
- `FAMMA_LOCAL_SMTP_HOST` / `FAMMA_LOCAL_SMTP_PORT` (`127.0.0.1` / `10007`).

`famma-env` ignore toute constante déjà définie : **aucun code n'a été modifié**
pour ce pont.

## 4. Provisionnement

`scripts/setup.sh` a été rendu **portable** au lieu d'être dupliqué. Sept
variables suffisent à le rejouer hors Docker :

| Variable | Rôle |
|---|---|
| `FAMMA_WP_CLI` | commande WP-CLI complète |
| `FAMMA_SCRIPTS_DIR` | dossier des scripts, vu par WP-CLI |
| `FAMMA_LANG_DIR` | traductions de `famma-core` |
| `FAMMA_URL` | URL du site si elle diffère de `WP_HOME` du `.env` |
| `FAMMA_ADMIN` | compte administrateur si différent de `WP_ADMIN_USER` |
| `FAMMA_REWRITE_HARD` | vide sous nginx : `--hard` ne concerne qu'Apache |
| `FAMMA_SKIP_OG` | saute l'image de partage (GD + polices URW absentes) |

WP-CLI est piloté depuis Git Bash par un lanceur, sans passer par le *Site
shell* :

```bash
export PHPRC='<dossier contenant un php.ini>'    # et non php -c : WP-CLI relance
                                                 # PHP en sous-processus, qui
                                                 # n'hériterait pas de -c
"<Local>/lightning-services/php-8.3.29+1/bin/win64/php.exe" wp-cli.phar \
  --path='C:/Users/DELL/Local Sites/famma/app/public' "$@"
```

Deux pièges rencontrés, tous deux résolus :

1. le `php.ini` du site charge `php_imagick.dll`, absent de l'installation → la
   copie utilisée pour le CLI commente cette ligne ;
2. `setup.sh` exporte `MSYS2_ARG_CONV_EXCL='*'` pour Docker, ce qui désactive la
   conversion des chemins POSIX → le lanceur n'utilise que des chemins Windows.

Puis, dans l'ordre : `setup.sh`, puis `create-shop-categories.php`,
`create-shop-filters.php`, `francise-slugs.php` (que `setup.sh` n'appelle pas).

## 5. Trois défauts de provisionnement corrigés à la source

Ils étaient **latents dans le dépôt**, pas propres à Local : une installation
Docker en français les aurait produits aussi.

| Défaut | Effet observé | Correction |
|---|---|---|
| Contenu de démonstration cherché par ses slugs **anglais** | « Bonjour tout le monde » et « Page d'exemple » restaient publiés sur une installation `fr_FR`, en violation du §58 | `setup.sh` cherche les deux jeux de slugs |
| `wp language plugin install --all fr_FR` appelé **avant** l'installation de WooCommerce | il ne traduisait rien ; le checkout restait anglais (E-28 du plan UX) et les 4 pages Woo gardaient leurs titres anglais | second passage après activation, + renommage idempotent des 4 pages (`Shop` → `Boutique`, etc.) |
| `check_if_gettext_tables_exist()` ne crée la table gettext que de la langue **du site** | `wp_trp_gettext_ar` absente : chaque page `/ar/` empilait des erreurs SQL en répondant 200 | `configure-translatepress.php` appelle `gettext_table_creation->check_gettext_table()` pour chaque langue traduite |

Un quatrième point relève de l'installation par un tiers : `blogname` n'est posé
que par `wp core install`. Local avait installé WordPress sous le nom « famma » ;
`setup.sh` applique désormais `WP_TITLE` à chaque passage.

## 6. Contrôles passés

- `php -l` et `phpcs` (ruleset WordPress) : verts sur les fichiers modifiés.
- Accueil, boutique, panier, mon-compte, 8 pages d'information, une page de
  catégorie, `/ar/`, `/ar/boutique/`, `/ar/panier/` : **200**.
- Anciennes URL : `/shop/`, `/cart/`, `/checkout/`, `/my-account/` → **301** vers
  `/boutique/`, `/panier/`, `/commande/`, `/mon-compte/`.
- `debug.log` **vide** après navigation complète : aucun warning, notice ou
  deprecated.
- `/ar/` sort en `<html dir="rtl" lang="ar">`, `/` en `lang="fr-FR"`.
- WooCommerce en français (« Votre panier est actuellement vide. »).
- Réglages : `TND`, 3 décimales, `TN:TN-11`, taxes désactivées, boutique visible,
  COD seule passerelle, livraison offerte, page d'accueil `accueil`, fuseau
  `Africa/Tunis`, slogan `فمّا ديما حاجة جديدة`.
- Structure : 5 rayons (compteurs réels à zéro), 6 filtres, 3 menus, 8 pages
  d'information, catalogue **vide**.
- Courrier : `wp_mail()` arrive dans le Mailpit du site.

## 7. Reste à faire

- **Parcours COD de bout en bout** (§11, point 3) : non joué, car il exige un
  produit de test. À faire avec un produit clairement marqué, supprimé ensuite.
- Vérification visuelle mobile/desktop écran par écran (le rendu a été contrôlé
  par le texte et les gabarits, pas au pixel).
- Points **préexistants**, hérités du dépôt et non causés par la migration :
  la devise s'affiche `د.ت` sous locale française (E-35 du plan UX) ; les
  libellés de menu « Categories », « Nouveautes », « Promotions » sont sans
  accents ; la page brouillon WooCommerce « Refund and Returns Policy » double la
  page « Retours et remboursements » du projet.
- Fournis par le propriétaire : numéro WhatsApp, identifiants de tracking, logo,
  adresse de la boutique, textes légaux, premiers produits réels.
