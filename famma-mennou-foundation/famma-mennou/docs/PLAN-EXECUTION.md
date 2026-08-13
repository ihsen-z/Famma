# Plan d'exécution — FAMMA MENNOU

> Analyse du dépôt au 2026-08-13 et plan de reprise en main.
> Statut du code analysé : `famma-core` v0.1.0, jamais exécuté sur une install WP+Woo réelle.

---

## 0. Méthode et limites de cette analyse

| Point | État |
|---|---|
| Graphe Graphify | **Graph miss.** `graphify extract . --code-only` → 2 nœuds (`backup.sh`). L'AST local n'indexe pas le PHP. Lecture directe assumée (règle 2 §4). |
| Context7 | **Indisponible** dans la session (aucun serveur MCP Context7 connecté). Toute affirmation sur une API WooCommerce/WordPress récente est marquée « à confirmer ». |
| Exécution runtime | **Aucune.** Docker Desktop n'est pas démarré ; `php`, `composer`, `phpcs` absents de la machine. Aucun constat n'a été validé à l'exécution. |
| Couverture de lecture | 11/11 fichiers, 687/687 lignes. |

---

## 1. État réel du dépôt

```
E:\Ecom\
├── CLAUDE.md                    → @ClaudeMemo.md (import)
├── ClaudeMemo.md                → source de vérité des règles
└── famma-mennou-foundation\
    └── famma-mennou\            ← racine réelle du projet (54 Ko)
        ├── .env.example                     39 l.
        ├── .gitignore                       27 l.
        ├── README.md                        73 l.
        ├── docker-compose.yml              103 l.
        ├── backups/.gitkeep
        ├── docs/  ARCHITECTURE · ORDER-WORKFLOW · PLUGIN-REGISTER · DESIGN-SYSTEM
        ├── scripts/backup.sh                13 l.
        └── wp-content/plugins/famma-core/famma-core.php   313 l.
```

**Ce qui existe vraiment** : une fondation Docker + un plugin mono-fichier de 313 lignes (7 classes, pattern singleton, namespace `Famma\Core`, HPOS déclaré).

**Ce qui n'existe pas** (mais est référencé par la doc) :

| Élément manquant | Référencé par |
|---|---|
| `MASTER-PROMPT.md` (spec 64 sections) | `ClaudeMemo.md`, tous les `§n` des docs |
| Dépôt Git (aucun `.git`) | ClaudeMemo « workflow attendu », règle 12 |
| `.env` | `docker-compose.yml` (toutes les `${VAR}`) |
| `scripts/setup.sh` | `docker-compose.yml:8`, README |
| `docker/php/uploads.ini` | `docker-compose.yml:57` (montage) |
| `wp-content/themes/famma-child/` | `docker-compose.yml:56,74`, `.gitignore:15` |
| `languages/` + `.pot` | `famma-core.php:292` |
| `DEPLOYMENT.md` | `docs/ARCHITECTURE.md:40` |

---

## 2. Constats classés par sévérité

### P0 — bloque le démarrage ou casse une règle non négociable

**P0-1. Le pont `.env` → constantes PHP n'existe pas. Toute la couche tracking + WhatsApp est du code mort.**
`famma-core.php:31-40` lit les constantes `FAMMA_META_PIXEL_ID`, `FAMMA_WHATSAPP_NUMBER`, etc. `docker-compose.yml:44-51` ne définit **aucune** de ces constantes dans `WORDPRESS_CONFIG_EXTRA`, et ne passe même pas ces variables au service `wordpress`. Enfin `.env.example:31-39` les nomme **sans le préfixe** (`META_PIXEL_ID` ≠ `FAMMA_META_PIXEL_ID`).
Rupture sur 3 couches ⇒ `Config::c()` renvoie toujours `''` ⇒ bouton WhatsApp jamais rendu, GA4/Meta/TikTok jamais injectés, `google-site-verification` jamais posé. Silencieusement. `docs/ARCHITECTURE.md:9-14` décrit ce flux comme s'il était câblé.

**P0-2. « COD payé uniquement à la livraison » ne fonctionne pas.**
`famma-core.php:87-90` appelle `$o->payment_complete()` sur la transition `delivered`. Or `WC_Order::payment_complete()` ne marque la commande payée que si son statut courant est dans `woocommerce_valid_order_statuses_for_payment_complete` — par défaut `on-hold, pending, failed, cancelled`. Le statut est ici `delivered` : la branche est ignorée, donc **pas de `date_paid`, pas de décrément de stock, pas d'événement `woocommerce_payment_complete`**. Seul `woocommerce_payment_complete_order_status_delivered` est déclenché, sans effet.
De plus `wc_get_is_paid_statuses()` ne contient pas `delivered` ⇒ `is_paid()` reste faux et le CA n'est jamais compté.
C'est la règle non négociable n°4 du projet. *(À confirmer sur la version Woo installée — Context7 indisponible.)*
Correctif : 3 filtres — `woocommerce_valid_order_statuses_for_payment_complete` (+`delivered`), `woocommerce_payment_complete_order_status` (renvoyer `delivered` pour ne pas sauter en `processing`), `woocommerce_order_is_paid_statuses` (+`delivered`).

**P0-3. `docker compose up` produit des artefacts cassés.**
`docker-compose.yml:56-57` monte deux chemins hôtes inexistants : Docker les **crée en tant que dossiers**. Résultat : un répertoire `uploads.ini/` dans `conf.d` de PHP (warnings au démarrage, config uploads jamais appliquée) et un thème `famma-child` vide. Par ailleurs `docker-compose.yml:8` renvoie l'utilisateur vers `scripts/setup.sh`, qui n'existe pas.

**P0-4. Aucun versionnage.** Pas de `.git`. Le `.gitignore` (bon, complet) ne protège rien tant que le dépôt n'est pas initialisé. Le risque immédiat : `.env` créé puis committé plus tard par inadvertance.

**P0-5. `MASTER-PROMPT.md` introuvable.** Tous les docs et `ClaudeMemo.md` s'appuient sur des `§1–§64` qui ne sont vérifiables nulle part. Sans ce fichier, « conforme à la spec » n'est pas une affirmation contrôlable.

### P1 — casse en conditions réelles

**P1-1. Checkout en blocs : toute la personnalisation Tunisie est probablement inopérante.**
`famma-core.php:170-171` utilise `woocommerce_checkout_fields` et `woocommerce_after_checkout_validation` — filtres du checkout **shortcode**. Les installs WooCommerce récentes créent un checkout **en blocs** par défaut, qui passe par la Store API et ignore ces deux hooks. ⇒ 24 gouvernorats, libellés bilingues et validation du téléphone TN sans effet. *(Priorité de vérification n°1 dès que la stack tourne.)*

**P1-2. `billing_country` supprimé sans valeur par défaut.**
`famma-core.php:184` retire le champ pays. Sans forcer `TN` (`woocommerce_customer_default_country` / `default_checkout_billing_country`), le pays est vide ⇒ aucune zone de livraison ne matche ⇒ le « flat rate 0 TND » du README ne s'applique pas.

**P1-3. L'événement Purchase serveur (Meta CAPI) n'est pas implémenté.**
`famma-core.php:277-287` pose bien l'idempotence (`_famma_purchase_sent`) et l'`event_id` de dédup, puis `do_action('famma_capi_purchase', …)` — **aucun listener**. Le TODO est explicite ligne 284. Or `ClaudeMemo.md` et `README.md` présentent le tracking serveur comme livré.
Note : `_famma_purchase_sent` est écrit *avant* l'appel ⇒ un échec réseau consommera définitivement le droit d'émettre.

**P1-4. Chargement du textdomain trop tôt.** `famma-core.php:292` : `load_plugin_textdomain()` sur `plugins_loaded`. Depuis WP 6.7 cela déclenche le `_doing_it_wrong` « `_load_textdomain_just_in_time` » — et l'image est **WordPress 6.8** (`docker-compose.yml:30`). Le critère de validation n°2 de la règle 11 (« aucun notice dans `debug.log` ») échoue dès la première activation. Correctif : charger sur `init`. Aucun `languages/` ni `.pot` n'existe par ailleurs.

**P1-5. Politique de stock indéfinie.** Woo ne décrémente le stock que sur `processing/on-hold/completed`. Avec `pending-confirm → confirmed → shipped → delivered`, le stock n'est jamais réservé. À décider explicitement (réservation à `confirmed` ?).

**P1-6. Aucun thème.** `docs/DESIGN-SYSTEM.md` (tokens, CTA sticky, RTL, home catalogue vide) est à 0 % implémenté.

### P2 — qualité, dette, incohérences doc/code

| # | Constat | Emplacement |
|---|---|---|
| P2-1 | `echo '<script>…'` en dur — viole la règle 9 du projet (`wp_enqueue_script` / `wp_add_inline_script`) | `famma-core.php:254,258,262,266` |
| P2-2 | Nonce non vérifié + `phpcs:ignore` (Woo vérifie en amont, mais la règle 4 exige la vérification explicite) | `famma-core.php:122-123` |
| P2-3 | `sanitize_text_field()` appliqué à du JSON avant `json_decode`, sans plafond de taille du cookie | `famma-core.php:270` |
| P2-4 | `esc_attr( rawurlencode( … ) )` sur une URL — `esc_url()` attendu | `famma-core.php:253` |
| P2-5 | `margin()` lit `get_regular_price()` : marge fausse dès qu'un prix promo existe | `famma-core.php:133` |
| P2-6 | ARCHITECTURE décrit une arbo `includes/class-*.php` qui n'existe pas — contredit la règle 7 de `ClaudeMemo.md` (mono-fichier assumé) | `docs/ARCHITECTURE.md:16-26` |
| P2-7 | README affirme `WP_ENVIRONMENT_TYPE=local` défini — c'est faux | `README.md:68` vs `docker-compose.yml:44-51` |
| P2-8 | Porte de validation inapplicable : ni `php`, ni `composer`, ni `phpcs` sur la machine | règle 11 §1 |
| P2-9 | `graphify-out/` (créé pendant cette analyse) doit être ignoré par Git | racine projet |
| P2-10 | Double imbrication `famma-mennou-foundation\famma-mennou\` — choisir la racine Git | arborescence |

**Bilan de l'écart doc/code** : sur les 6 briques annoncées comme « déjà implémentées » dans `ClaudeMemo.md`, **2 fonctionnent probablement telles quelles** (statuts custom, champs financiers coût×3), **1 est partielle** (split livraison : l'option existe, le flat-rate 0 est manuel), **3 sont non fonctionnelles en l'état** (WhatsApp, tracking, checkout Tunisie) — plus la règle COD cassée.

---

## 3. Décisions à prendre (propriétaire) — bloquantes pour certaines phases

| # | Décision | Bloque |
|---|---|---|
| D1 | Racine Git : `famma-mennou/` ou `E:\Ecom` ? Remote privé ou local seul ? | Phase 0 |
| D2 | Fournir `MASTER-PROMPT.md` (ou acter qu'il n'existe plus et que `ClaudeMemo.md` devient la seule référence) | Phases 3+ |
| D3 | Checkout **blocs** (moderne, hooks Store API) ou **shortcode** (compatible avec le code actuel) | Phase 3 |
| D4 | Thème : Kadence / Blocksy / Astra + enfant, ou thème bloc maison | Phase 5 |
| D5 | Multilingue : plugin i18n gratuit vs locale + RTL seuls (§5) | Phase 5 |
| D6 | Politique de stock (à quel statut réserver ?) | Phase 3 |
| D7 | Numéro WhatsApp réel + IDs Pixel/GA4/TikTok (peuvent rester vides — le code doit dégrader proprement) | Phase 4 |

---

## 4. Plan d'exécution par phases

Chaque phase se termine par un commit et un critère de sortie **vérifiable**. Aucune phase ne démarre avant que la précédente soit verte.

### Phase 0 — Reprise en main (aucune modification fonctionnelle)
**Objectif** : rendre le projet versionné et reproductible.
1. `git init` à la racine décidée (D1) ; premier commit de l'existant.
2. Compléter `.gitignore` : `graphify-out/`, `docker/`(si généré), `*.original.md`.
3. Créer `.env` depuis `.env.example` avec des mots de passe locaux réels (jamais committé).
4. Committer ce plan.
**Sortie** : `git status` propre, `.env` non suivi, 1 commit initial.
**Risque** : nul.

### Phase 1 — Rendre la stack démarrable
**Objectif** : `docker compose up -d` → site accessible, plugin actif, `debug.log` vide.
1. Créer `docker/php/uploads.ini` (upload 64M, `post_max_size`, `memory_limit`).
2. Créer `wp-content/themes/famma-child/` minimal (`style.css` + `theme.json` + `functions.php`) **ou** retirer le montage jusqu'à D4.
3. Écrire `scripts/setup.sh` idempotent via WP-CLI : install WP → install/activate WooCommerce → activate famma-core → store Tunisie/TND → COD activé par défaut → zone « Tunisie » flat rate 0 → suppression des produits d'exemple.
4. **Câbler le pont env → constantes** (P0-1) : ajouter les variables au service `wordpress`, aligner les noms sur `FAMMA_*` dans `.env.example`, et les `define()` dans `WORDPRESS_CONFIG_EXTRA` ; ajouter `WP_ENVIRONMENT_TYPE` (corrige P2-7).
**Sortie** : `http://localhost:8080` répond, `wp plugin list` montre `famma-core` actif, `debug.log` sans erreur, bouton WhatsApp visible dès qu'un numéro est renseigné.
**Risque** : le montage `wp_core` volume + bind-mounts peut nécessiter un ajustement de droits sous Windows.

### Phase 2 — Boucle de qualité exécutable
**Objectif** : rendre la règle 11 applicable (P2-8).
1. Service Compose `tools` (profil) avec PHP 8.3 + Composer + `squizlabs/php_codesniffer` + `wp-coding-standards/wpcs`.
2. `scripts/lint.sh` : `php -l` sur tous les `.php` modifiés + `phpcs --standard=WordPress`.
3. Corriger les erreurs WPCS remontées sur `famma-core.php`.
**Sortie** : `bash scripts/lint.sh` → 0 erreur.

### Phase 3 — Réparer les règles métier (P0-2, P1-1 → P1-5)
**Objectif** : le parcours COD réel est conforme.
1. **COD payé à la livraison** : les 3 filtres décrits en P0-2 + test de non-régression (la commande reste bien en `delivered`, `date_paid` posée, stock décrémenté une seule fois).
2. **Checkout** selon D3 : si blocs → réécrire gouvernorats/validation via la Store API (`woocommerce_store_api_checkout_update_order_from_request`, `woocommerce_blocks_loaded`) ; si shortcode → forcer la page checkout en shortcode et documenter le choix.
3. Forcer le pays `TN` par défaut (P1-2).
4. Déplacer `load_plugin_textdomain` sur `init` + créer `languages/` et un `.pot` (P1-4).
5. Trancher et implémenter la politique de stock (D6).
**Sortie** : parcours e2e de la règle 11 §3 vert, `debug.log` propre.

### Phase 4 — Tracking serveur (Meta CAPI)
**Objectif** : honorer la promesse « Purchase déclenché côté serveur à *livré*, une seule fois ».
1. Listener sur `famma_capi_purchase` : POST Graph API avec `event_id` de dédup, `test_event_code` si présent, timeout court, échec silencieux mais journalisé **sans le token**.
2. Inverser l'ordre : marquer `_famma_purchase_sent` **après** succès, avec compteur de tentatives (corrige P1-3).
3. Extraire les snippets inline vers des assets enqueued (P2-1).
**Sortie** : événement visible dans le **Test Events** Meta ; aucune occurrence du token dans `debug.log` ni dans le HTML (`grep`).
**Dépend de** : D7.

### Phase 5 — Thème et design system
**Objectif** : implémenter `docs/DESIGN-SYSTEM.md`.
1. Thème enfant selon D4 ; tokens CSS ; CTA « Commander / أطلب توا » sticky mobile ≥ 48 px.
2. RTL par propriétés logiques, une seule feuille de style.
3. Home « catalogue vide » (hero, promesse, chips confiance COD/livraison/WhatsApp, FAQ) — **aucune donnée fictive**.
4. Multilingue selon D5.
**Sortie** : rendu vérifié aux breakpoints 320/375/768/1440, en LTR et RTL.

### Phase 6 — Documentation et conformité
1. Resynchroniser `README.md` et `docs/ARCHITECTURE.md` avec le code réel (P2-6, P2-7).
2. Écrire `DEPLOYMENT.md` (local → staging → prod, `WP_DEBUG=0`, constantes serveur).
3. Mettre à jour `PLUGIN-REGISTER.md` à chaque ajout.
4. Décider si le mono-fichier de 313 l. doit être scindé (le seuil « ~200-250 lignes » de la règle 6 est déjà franchi) — **ne pas le faire avant la Phase 3**, le risque de doublon de classe est le mode d'échec n°1 documenté.

---

## 5. Ordre de démarrage recommandé

1. Répondre à **D1** et **D2** (2 minutes, débloque tout le reste).
2. Démarrer Docker Desktop.
3. Exécuter la **Phase 0** puis la **Phase 1** dans la foulée — c'est le seul moyen de transformer les constats « à confirmer » (P0-2, P1-1) en faits.
4. Vérifier immédiatement, une fois le site debout, **quel type de checkout** WooCommerce a créé : c'est ce qui détermine l'ampleur réelle de la Phase 3.

## 6. Non fait sans feu vert explicite

`git push`, achat de domaine, saisie de secrets réels, `docker compose down -v`, suppression de données, et tout choix propriétaire listé en §3.
