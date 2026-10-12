# Rapport d'architecture — FAMMA MENNOU

_État du dépôt au 2026-09-21, branche `feat/maquette-fidelite-fiche-produit`._

Ce rapport répond à trois questions : **quelle démarche** a conduit le projet,
**quelle logique** gouverne le code, **quelle architecture et quelle stack** en
résultent. Il décrit ce qui est réellement dans le dépôt, pas une intention.

---

## 1. La démarche

### 1.1 Une spécification avant une ligne de code

Le projet part de `MASTER-PROMPT.md` — 64 sections numérotées qui décrivent la
boutique attendue (métier, UX, SEO, sécurité, déploiement). Toute la
documentation cite ces sections sous la forme `§n`. Une hiérarchie explicite
tranche les conflits (ADR-002) :

```
MASTER-PROMPT.md   le QUOI      (spécification produit)
ClaudeMemo.md      le COMMENT   (qualité, sécurité, règles non négociables)
docs/*.md          le RÉEL      (ce qui est implémenté, et comment ça se comporte)
```

### 1.2 Toute décision structurante est un ADR daté

`docs/DECISIONS.md` contient 7 décisions d'architecture, chacune avec sa date,
son statut (agent ou propriétaire), les options écartées, la justification et
le **plan de sortie**. Une décision non écrite là est réputée non prise.

| ADR | Sujet | Choix |
|---|---|---|
| 001 | Racine Git, dépôt distant | `E:\Ecom`, dépôt privé, aucun `push` sans feu vert |
| 002 | Spécification de référence | `MASTER-PROMPT.md` versionné |
| 003 | Type de checkout | Checkout **classique** (shortcode), pas blocs |
| 004 | Thème | **Kadence** gratuit + thème enfant `famma-child` |
| 005 | Multilingue / RTL | **TranslatePress** gratuit, FR par défaut + AR (Tunisie) |
| 006 | Environnement local | **Local by Flywheel** au quotidien, Docker conservé comme référence |
| 007 | Contenu institutionnel | Zéro texte en dur : tout en options éditables |

Deux de ces choix méritent d'être soulignés parce qu'ils ont façonné le reste :

- **ADR-003 (checkout classique)** : le checkout en blocs n'autorise pas de
  *retirer* les champs d'adresse du cœur (société, adresse 2, code postal, et
  surtout **Pays** — inutile pour une boutique qui ne vend qu'en Tunisie).
  L'API `woocommerce_register_additional_checkout_field` ajoute des champs,
  elle n'en enlève pas. Le classique donne le contrôle total exigé, et pèse
  beaucoup moins en JS sur un réseau mobile tunisien. Le plan de sortie est
  circonscrit : seule la classe `Tunisia` serait à réécrire.
- **ADR-005 (TranslatePress)** : Polylang gratuit est inutilisable ici — son
  extension WooCommerce est payante. TranslatePress gratuit autorise
  exactement **une** langue additionnelle, soit précisément le besoin
  FR + Derja, et traduit le rendu final donc couvre Woo sans payer.
  Traduction automatique **désactivée** : la Derja est écrite par un humain.

### 1.3 Une contrainte de véracité absolue

Aucune donnée inventée : pas de faux produits, faux avis, fausses statistiques,
faux textes légaux. Le catalogue démarre vide. Cette règle se voit dans le code :
le module SEO n'émet une balise que si la source existe réellement, et le
schéma FAQ est extrait du contenu publié — jamais fabriqué.

### 1.4 Une porte de qualité avant de déclarer « fini »

Une tâche n'est close que lorsque : `php -l` passe, `phpcs` (WordPress Coding
Standards + PHPCompatibility) ne renvoie aucune erreur, le plugin tourne avec
`WP_DEBUG=true` et un `debug.log` propre, et le parcours réel a été rejoué de
bout en bout (ajout au panier → checkout COD → confirmé → expédié → livré).
`composer.json`, `phpcs.xml.dist` et `scripts/lint.sh` outillent cette porte
dans un conteneur jetable, sans rien installer sur la machine.

---

## 2. La logique — les six règles qui gouvernent le code

### 2.1 Le cœur n'est jamais modifié

WordPress, WooCommerce et le thème parent restent intacts. **Toute** la logique
métier vit dans un seul plugin versionné, `famma-core`. Le thème enfant
`famma-child` ne fait que de la présentation. Conséquence directe : changer de
thème ne casse pas la boutique, et une mise à jour de Woo n'écrase rien.
Seuls trois répertoires sont custom, et ce sont exactement les trois que le
script de déploiement transfère.

### 2.2 Une commande COD n'est « payée » qu'à la livraison

C'est la règle métier centrale, et celle qui a demandé le plus de travail réel
contre WooCommerce. Le workflow :

```
NEW → PENDING CONFIRMATION → CONFIRMED → PROCESSING → SHIPPED → DELIVERED → COMPLETED
      (+ CANCELLED · FAILED DELIVERY · RETURNED · REFUSED · REFUNDED)
```

Sept statuts sont créés par `famma-core` (`pending-confirm`, `confirmed`,
`shipped`, `delivered`, `failed-delivery`, `returned`, `refused`), les autres
sont natifs.

Trois obstacles concrets, documentés dans `docs/ORDER-WORKFLOW.md` :

1. `payment_complete()` **ne fait rien** depuis un statut personnalisé :
   WooCommerce n'effectue l'écriture comptable que depuis
   `on-hold, pending, failed, cancelled`. Il faut ajouter `delivered` au filtre
   `woocommerce_valid_order_statuses_for_payment_complete`.
2. `WC_Gateway_COD` force le retour en `completed` via
   `woocommerce_payment_complete_order_status`. La passerelle étant instanciée
   après `plugins_loaded`, elle gagne à priorité égale — d'où une
   **priorité 99** pour maintenir la commande en `delivered`.
3. Une commande COD neuve tombe dans `processing`, qui est un statut *payé*
   côté Woo : une commande non livrée se déclarait donc payée. Corrigé en
   redirigeant la création vers `pending-confirm` et en retirant `processing`
   des statuts payés.

L'idempotence est gardée par une méta `_famma_cod_paid` et **pas** par
`date_paid` : `maybe_set_date_paid()` s'exécute pendant `save()`, avant la
transition, donc un garde sur `date_paid` sortirait trop tôt et le stock ne
serait jamais décrémenté.

> Limite assumée et écrite : le stock n'est décrémenté qu'à la livraison, donc
> deux commandes peuvent porter sur le même article. Réserver plus tôt est une
> décision métier non encore prise.

### 2.3 Aucun secret dans le code, aucune valeur devinée

Chaîne de configuration :

```
.env → docker-compose environment: → mu-plugin famma-env.php (define) → Famma\Core\Config
```

Le mu-plugin existe pour une raison précise : l'image officielle WordPress
n'écrit `wp-config.php` que si le volume est vide et ne le régénère jamais —
tout ce qu'on ajoute ensuite dans `WORDPRESS_CONFIG_EXTRA` n'atteint jamais PHP.
`getenv()` est utilisé plutôt que `$_ENV`, car `variables_order` vaut `GPCS`
par défaut et ne peuple pas `$_ENV`.

Le **token Meta reste strictement serveur** : seul l'*ID* du pixel est envoyé au
navigateur. Une valeur vide signifie « fonctionnalité désactivée » — jamais un
repli sur une valeur devinée.

### 2.4 Tout est paramétrable depuis l'administration (ADR-007)

Aucun texte visible ni coordonnée en dur. Les gabarits lisent les options du
plugin et **se taisent quand un champ est vide**. Un menu FAMMA en admin
(capacité `manage_woocommerce`, chargé uniquement si `is_admin()`) regroupe :
tableau de bord, Pages, Suivi/tracking, SEO.

### 2.5 Ne pas réimplémenter ce que WooCommerce fait déjà

Le meilleur exemple est la **commande express** sur la fiche produit
(`includes/express-order.php`, 400 lignes). Le trafic arrive d'une publicité
Meta et atterrit sur un produit ; le parcours normal impose fiche → panier →
checkout. Le formulaire supprime les deux dernières étapes — mais il **ne crée
aucune commande et n'écrit rien en base**. Il poste vers les deux points AJAX de
Woo (`?wc-ajax=add_to_cart` puis `?wc-ajax=checkout`), donc
`WC_Checkout::process_checkout()` s'exécute tel quel. Restent actifs sans une
ligne de plus : validation du téléphone tunisien et du gouvernorat, contrôle de
stock, statut COD initial, capture UTM, coût de livraison interne, règle
« payé à la livraison », CAPI. Il ne s'affiche pas sur les produits variables,
pour ne pas dédoubler le sélecteur de variations.

### 2.6 Internationalisation et RTL par construction

Toute chaîne passe par `__()` / `esc_html__()` avec le domaine `famma-core`
(ou `famma-child`). Le CSS utilise des **propriétés logiques**
(`margin-inline-start`, `padding-inline-end`) plutôt que `left`/`right` : une
seule feuille de style sert le FR et l'arabe. `/ar/` rend bien `dir="rtl"`.

---

## 3. L'architecture

### 3.1 Vue d'ensemble

```
E:\Ecom                                   racine Git
├── MASTER-PROMPT.md                      spécification, 64 §
├── ClaudeMemo.md                         règles qualité/sécurité
├── graphify-out/                         graphe de connaissance du code
└── famma-mennou-foundation/famma-mennou/
    ├── docker-compose.yml                stack de référence épinglée
    ├── composer.json · phpcs.xml.dist    porte qualité
    ├── .env / .env.example               configuration, jamais commitée
    ├── docs/                             22 documents : ADR, audits, déploiement
    ├── scripts/                          amorçage, lint, sauvegarde, déploiement
    └── wp-content/
        ├── mu-plugins/                   famma-env · famma-local-mail · famma-legacy-urls
        ├── plugins/famma-core/           TOUT le métier (~6 800 l.)
        └── themes/famma-child/           présentation seule (~14 800 l.)
```

Seuls `famma-core`, `famma-child` et `mu-plugins` sont versionnés côté
`wp-content` ; le cœur et les extensions tierces s'installent à l'exécution.

### 3.2 `famma-core` — un fichier consolidé, par décision

Le plugin est délibérément un **fichier unique** de 2 313 lignes : pas
d'autoloader, donc aucun chargement croisé ne peut échouer et aucune classe ne
peut être déclarée deux fois (cause n°1 d'erreur fatale à l'activation). Le
ruleset phpcs exclut pour cela le sniff « une structure par fichier ».

Onze classes finales, chacune en singleton, namespace `Famma\Core` :

| Classe | Rôle |
|---|---|
| `Config` | lit les constantes issues de `.env` + les options WP |
| `Order_Statuses` | workflow COD, les trois filtres de paiement, idempotence |
| `Stock` | décrément à la livraison |
| `Product_Financials` | coût × 3, champs de marge, panneau admin |
| `Product_Faq` | FAQ produit éditable, source du schéma `FAQPage` |
| `Shipping` | 0 TND affiché client / 7 TND coût interne stampé sur la commande |
| `Tunisia` | 24 gouvernorats, validation du téléphone, purge des champs inutiles |
| `WhatsApp` | bouton flottant + liens `wa.me` dynamiques |
| `Tracking` | GA4 / Meta / TikTok côté navigateur (ID seul) + capture UTM |
| `Capi` | Meta Conversions API serveur, Graph `v21.0`, 5 tentatives max |
| `Dashboard` | tableau de bord marchand |

Le volume ayant franchi 2 100 lignes, la règle « scinder par responsabilité »
s'est appliquée de façon **étroite** : huit modules en `includes/`, chargés par
`require_once` explicite (jamais un autoloader), une seule classe par fichier.

| Fichier `includes/` | Lignes | Rôle |
|---|---|---|
| `seo.php` | 1 048 | propriétaire unique du `<head>` : description, OG, titres, données structurées, directives d'indexation |
| `pages-content.php` | 688 | contenu éditable des pages institutionnelles (ADR-007) |
| `topbar-content.php` | 527 | barre supérieure paramétrable |
| `seo-admin.php` | 407 | écran de réglages SEO |
| `tracking-events.php` | 407 | émission des événements |
| `express-order.php` | 400 | commande express sur la fiche produit |
| `tracking-settings.php` | 329 | écran de réglages du suivi |
| `product-video.php` | 247 | vidéo publicitaire pilotée depuis le back-office |

**Un seul propriétaire par sortie.** Dès que la classe `Seo` existe, le thème
s'efface (`famma_child_open_graph()` se désactive) : deux jeux de balises
concurrents valent moins qu'aucun.

### 3.3 `famma-child` — présentation seule

Le thème enfant fournit ses propres `header.php` et `footer.php`. Mécanique :
`get_header()` passe par `locate_template()`, qui regarde l'enfant avant le
parent ; Kadence appelant `get_header()`/`get_footer()` dans **tous** ses
gabarits, WooCommerce compris, le remplacement couvre toutes les pages d'un
coup. Les feuilles d'en-tête/pied de page du parent, devenues sans objet, sont
retirées par `wp_dequeue_style()` sur une liste filtrable, chaque poignée étant
testée avant — une poignée absente d'une autre version de Kadence est ignorée.

```
famma-child/
├── functions.php (808 l.)  ~20 hooks, un par responsabilité
├── header.php · footer.php
├── inc/     icons · header-parts · footer-parts · assets · home · shop ·
│            shop-widgets · product · checkout · pages · i18n
├── woocommerce/  surcharges de gabarit (archive-product.php…)
└── assets/  css: tokens · layout · home · shop · product · product-card · pages
           js:  carousel · sticky-cta · shop-view
           fonts: Noto Kufi Arabic (variable, woff2, auto-hébergée)
```

`tokens.css` (297 l.) porte le système de design : couleurs, espacements,
typographie en variables CSS. Les CTA font ≥ 48 px, mobile-first.

> **Piège documenté** : ne jamais créer `famma-child/woocommerce.php`. Un
> `woocommerce.php` à la racine d'un thème prend le pas sur
> `woocommerce/archive-product.php` et neutralise toutes les surcharges du
> dossier. Le thème hérité `famma-mennou-woocommerce-theme-v1` en contient un —
> raison de plus pour ne pas le réactiver.

### 3.4 Sécurité — séquence obligatoire sur toute entrée

1. **Nonce** — `wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()`
2. **Capacité** — `current_user_can()` ; jamais « la page est dans l'admin »
3. **Assainir en entrée** — `wp_unslash()` puis `sanitize_text_field()`, `absint()`, `wc_clean()`…
4. **Échapper en sortie**, tardivement — `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
5. **SQL** — jamais de concaténation ; APIs WP/Woo, sinon `$wpdb->prepare()`

Les accès aux commandes passent par les objets CRUD (`wc_get_order()`,
`$order->update_meta_data()` + `save()`), jamais par `postmeta` en direct :
le projet déclare la compatibilité **HPOS**, donc la commande peut ne pas
vivre dans `posts`/`postmeta`. Toutes les métas et options sont préfixées
`_famma_` / `famma_`.

### 3.5 Performance

Le tableau de bord ne lit pas `wc_get_orders( limit = -1 )`. Les figures sont
mises en cache dans un transient 5 minutes, avec un bouton « recalculer »
protégé par nonce, et la lecture est **plafonnée à 1 000 commandes**. Quand le
plafond est atteint, la page le dit — au lieu de présenter un total partiel
comme s'il était complet. Le code admin ne se charge que dans l'admin.

Le tableau de bord ne compte **que les commandes livrées** : les chiffres
diffèrent donc volontairement des rapports WooCommerce, et la page l'écrit à
l'écran. Marge estimée = total livré − (coût produit × quantité + manutention +
acquisition + livraison interne stampée sur la commande).

---

## 4. La stack technologique

### 4.1 Exécution

| Couche | Technologie | Version |
|---|---|---|
| CMS | WordPress | ≥ 6.9 |
| E-commerce | WooCommerce | 11.0.1 (HPOS déclaré) |
| Langage | PHP typé strictement | 8.1+ ciblé, 8.3 en local et en production |
| Base de données | MariaDB | 11.4 |
| Serveur (local) | Apache (image `wordpress:7.0-php8.3-apache`) | — |
| Serveur (production) | LiteSpeed, Oxahost DirectAdmin, datacenter Tunis | — |
| Thème parent | Kadence (gratuit, GPL) | 1.5.2 |
| Multilingue | TranslatePress (gratuit) | 3.3.2 |
| Front | HTML/CSS/JS **sans framework ni build** — variables CSS, JS vanilla | — |
| Typographie | Noto Kufi Arabic (variable woff2, auto-hébergée) | — |

Le front n'a **ni bundler, ni npm, ni React**. Tout passe par
`wp_enqueue_style()` / `wp_enqueue_script()` avec une version pour le
cache-busting. C'est un choix de performance sur réseau mobile tunisien, cohérent
avec le refus du checkout en blocs.

### 4.2 Développement et outillage

| Besoin | Outil |
|---|---|
| Environnement quotidien | **Local by Flywheel** — site `famma` / `famma.local`, PHP 8.3.29 |
| Environnement de référence reproductible | **Docker Compose** — versions épinglées, ce que `sites.json` de Local ne fait pas |
| Services Docker | `db` (MariaDB 11.4) · `wordpress` · `wpcli` · `phpmyadmin` (profil `tools`) · `mailpit` |
| Mail local | **Mailpit** sur `:8025` — l'image WordPress n'a aucun transport mail ; routé par mu-plugin gardé sur `wp_get_environment_type() === 'local'` |
| Qualité | PHP_CodeSniffer + **WPCS** + PHPCompatibility (+ PHPCSUtils, PHPCSExtra), via Composer |
| Lint | `scripts/lint.sh` — `php -l` + `phpcs` dans un conteneur jetable |
| Versioning | Git, dépôt privé ; `.env` et `backups/` jamais commités |
| Cartographie du code | **Graphify** — graphe de connaissance interrogé avant toute lecture de fichier |

### 4.3 Mesure et acquisition

- **GA4**, **Meta Pixel**, **TikTok Pixel** côté navigateur — *ID seul*, pilotés
  par `.env`, inertes si la variable est vide.
- **Meta Conversions API** côté serveur (`Capi`, Graph `v21.0`, 5 tentatives
  max) : l'événement `Purchase` part **à la livraison**, une seule fois, jamais
  à la commande — cohérent avec la règle COD.
- **Capture UTM** rattachée à la commande, remontée dans le tableau de bord
  comme source d'acquisition.
- **SEO/GEO** : module maison plutôt qu'une extension, propriétaire unique du
  `<head>`, données structurées extraites du contenu réellement publié.

### 4.4 Déploiement

`scripts/deploy/deploy.sh` transfère **trois répertoires et rien d'autre** :
`famma-core`, `famma-child`, `mu-plugins`. Il commence par une porte qualité
(`php -l` + `phpcs`), refuse de partir si l'arbre Git n'est pas propre, et
supporte `--dry-run`. **Il ne touche jamais à la base de production** :
écraser une base est irréversible, la migration reste un geste séparé et
délibéré (`DEPLOY-OXAHOST.md`, Phase 6).

Le manuel Oxahost est séquentiel, chaque phase se terminant par un contrôle.
Sa Phase 0 fait confirmer par écrit dix points auprès de l'hébergeur avant de
construire quoi que ce soit — dont `max_input_vars ≥ 3000` : au-delà de la
valeur par défaut de 1000, PHP **tronque en silence** le formulaire produit de
WooCommerce et des variations disparaissent sans message ni ligne de journal.

---

## 5. Ce qui reste ouvert

| Sujet | État |
|---|---|
| Réservation de stock avant livraison | Décision métier non prise (D6) |
| Sélecteur de langue | Actuellement en bas à gauche ; attendu dans l'en-tête (§9) |
| Découpage complet de `famma-core` en modules | Objectif à terme, conditionné aux règles métier vertes |
| Ajout d'une passerelle de paiement en ligne | Imposerait de revoir `paid_statuses()` — un prépayé est légitimement payé dès `processing` |
| Contenu réel | Produits, prix de revient, textes légaux, visuels, numéro WhatsApp, IDs Pixel/GA4 — fournis par le propriétaire, jamais inventés |
