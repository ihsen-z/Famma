# Audit de code et d'architecture — FAMMA (fammaminnou.tn) — réaudit du 25/09

- **Date** : 2026-09-25 · **Mode** : lecture seule. Seul ce rapport a été écrit, sans aucun commit.
- **Dépôt** : `E:\Ecom\famma-mennou-foundation\famma-mennou`, branche `perf/optimisation-prod` @ `e08e55a`. Cette branche n'a **pas de branche distante** (voir CODE-27).
- **Référence** : l'audit du 23/09 (`docs/audit-2026-09-23/audit-code.md`, note 6,5/10, CODE-01 à CODE-30) portait sur `2e40acc` (28/08). Entre `2e40acc` et `e08e55a` : 41 commits et 63 fichiers modifiés hors `docs/` (+10 070 / −839 lignes, `git diff --stat`).
- **Grille** : `E:\Ecom\ClaudeMemo.md`, règles 1 à 13 et règles non négociables.
- **Versions** : site Local sous WordPress 7.1 (`wp-includes/version.php:19`) et WooCommerce 11.0.1 ; prod sous WooCommerce 11.1.2 (`DEPLOIEMENT-2026-09-23.md` §3). Analyses faites avec PHP 8.3.29 (binaire LocalWP).

## 0. Méthode et limites

- **Graphe d'abord** (règle 2) : 17 appels au wrapper `gq.py` (13 `explain`, 4 `query`), tous journalisés dans `graphify-savings.jsonl`. Le wrapper estime à environ 507 000 tokens la lecture intégrale ainsi évitée. C'est une borne haute, puisque des extraits ciblés ont ensuite été lus. Les numéros de ligne du graphe correspondaient au code (`Order_Statuses` L241, `Capi` L1758, `Dashboard` L2023).
  - Seuls des fichiers cités par le graphe ont été lus en entier : `newsletter.php`, `returns-content.php`, `hardening.php`, `home-social.php`, ainsi que le rapport du 23/09.
  - **graph miss**, avec repli sur Grep : directives `phpcs:*`, propriétés CSS physiques, nonces imprimés dans les pages, parseurs `preg_split`, `wp_kses_allowed_html` et `srcset`, accès `get_post_meta` et `$wpdb`, chaînes i18n (extraites par script).
- **Analyse statique seulement** : ni LocalWP, ni Docker, ni formulaire soumis. `php -l` et `phpcs` ont été lancés.
- **Scripts de mesure** (dans le scratchpad, hors dépôt) :
  - `i18n_cov.py` extrait les chaînes gettext par domaine et les compare aux `.po`.
  - `ar_literals.py` compte les littéraux arabes hors gettext.
  - Les deux ont été **rejoués sur `2e40acc`** pour comparer à méthode constante. Quand le résultat s'écarte des chiffres du 23/09, l'écart est signalé.
- **Contrôle en ligne** : 7 requêtes GET, espacées d'au moins 4 s, avec un user-agent de navigateur, le 25/09 entre 17:03 et 17:07 UTC.
  - Pages : `/`, `/wp-json/wp/v2/users`, `/produit/extenseur-dorsal/`, `/retours/`, `/ar/produit/extenseur-dorsal/` (réponse 301) puis `/ar/product/extenseur-dorsal/`.
  - Fichier : `…/famma-child/assets/js/product-accordion.js`.
- **État de la prod** : d'après `DEPLOIEMENT-2026-09-23.md` (§2, §5 à §13), le code est en ligne jusqu'à `e08e55a`.
  - Déploiement par `git archive`, puis gestionnaire de fichiers DirectAdmin en mode « Merge and overwrite ».
  - **LiteSpeed Cache actif, 7 jours** (§9.3). Vérifié : `X-LiteSpeed-Cache: hit` sur `/` et `X-LiteSpeed-Cache-Control: public,max-age=604800` sur la fiche produit.

---

## 1. Note globale : **6,5 / 10** (stable, 6,5 au 23/09)

Le rapport du 23/09 ne publiait pas de pondération. La grille ci-dessous reprend les sept familles de son §1. Elle est calibrée pour redonner 6,5 sur l'état du 23/09, puis appliquée sans changement à l'état du 25/09.

| Critère | Poids | 23/09 | 25/09 | Ce qui a bougé |
|---|---|---|---|---|
| Sécurité des entrées/sorties, secrets | 20 % | 9 | 9 | Le nouveau code suit la séquence nonce → capacité → assainissement → échappement (`newsletter.php:164-183`, `:339-343`), neutralise les formules CSV (`:391-393`) et la redirection WhatsApp est bornée (`returns-content.php:339-357`). XML-RPC est fermé et `/wp/v2/users` répond 401 en ligne. Aucun secret en dur. |
| Standards (php -l, phpcs, typage, ABSPATH) | 10 % | 9 | 8,5 | 0 erreur et 0 avertissement sur 61 fichiers. En contrepartie : les directives `phpcs:*` passent de 28 à 45, 7 nouveaux callbacks de filtre sont typés strictement (CODE-22) et le `@phpcs:disable` sans fin est toujours là (CODE-25). |
| Données WooCommerce / HPOS | 10 % | 9 | 9 | Aucun `get_post_meta` ni `$wpdb` sur une commande. `get_post_meta` ne sert qu'au type `famma_subscriber` et aux méta SEO de pages. |
| Règles métier (COD, e-mails, tracking, prix) | 25 % | 5 | 5,5 | CODE-01 est corrigé. Restent ouverts CODE-02, 03, 04, 08 et 09. La newsletter se casse avec le cache (CODE-31, nouveau). Le pixel Meta est désormais **actif en ligne sans consentement** (CODE-10). |
| Architecture et dette | 15 % | 5 | 4 | 27 fichiers dépassent 250 lignes (contre 17), avec 5 000 lignes PHP de plus. De nouveaux doublons sont apparus (CODE-33). En progrès : `tabs.php` n'est plus qu'un relais vers le gabarit du cœur, et la réassurance du tunnel a une source unique. |
| i18n et RTL | 10 % | 6 | 6 | Toujours zéro propriété CSS physique. L'arabe du thème passe de 117/143 à 164/188 chaînes (82 % → 87 %). Côté famma-core, le `.pot` et `ar.po` n'ont pas bougé, et les gouvernorats restent en caractères latins sur `/ar/` (constaté en ligne). |
| Exploitation (déploiement, sauvegarde, Git) | 10 % | 3 | 3,5 | Progrès : mu-plugins listés un par un, cron serveur, sauvegarde manuelle le 23/09. Reculs : **44 commits jamais poussés**, `main` en retard de 71 commits, déploiement manuel qui ne supprime rien (CODE-37). |
| **Total** | | **6,5** | **6,5** (6,48) | |

**Lecture.** En deux jours, on compte environ 40 commits de fonctionnalités d'une qualité locale élevée : phpcs à zéro, échappement systématique, CSS logique, textes éditables et aucune promesse inventée. Mais le seul correctif métier est CODE-01. Les trois autres constats « Élevés » du 23/09 portant sur des règles métier (UTM, CAPI, statut « Terminée ») n'ont pas été touchés.

Deux nouveaux risques compensent les gains :
- un nonce figé par le cache de page ;
- une copie unique, non poussée, du code qui tourne en production.

La dette de taille continue de croître plus vite qu'elle n'est remboursée.

---

## 2. Points forts (à ne pas casser)

1. **CODE-01 corrigé proprement** (`famma-core.php:321-364`). Le correctif passe par les classes e-mail de WooCommerce : destinataires, gabarits et interrupteurs restent ceux de l'administration, et `WC_Email_New_Order` évite déjà l'envoi en double. Le cas `failed → pending-confirm` est couvert.
2. **Durcissement** (`includes/hardening.php`) : XML-RPC est coupé, `X-Pingback` supprimé et `/wp/v2/users` réservé à `list_users`/`edit_posts`. Les callbacks acceptent `mixed` puis convertissent, ce qui est la bonne réponse à CODE-22. En ligne, la route renvoie HTTP 401 `rest_user_cannot_view`.
3. **Numéros normalisés** à un seul endroit (`Config::international_phone()` et `tel_uri()`, `famma-core.php:115-139`), réutilisé par la CAPI (`:1819-1821`). En ligne, 4 liens pointent vers `wa.me/216…` et 1 vers `tel:+216…`.
4. **Newsletter** : nonce, pot de miel, limite de fréquence par empreinte d'IP (jamais l'IP en clair), `sanitize_email` puis `is_email`. Aucune énumération possible, puisque la réponse est la même que l'adresse soit connue ou non. Capacités mappées sur `manage_options`, avec `create_posts => do_not_allow`. Export CSV derrière capacité et nonce.
5. **Aucune promesse par défaut** : les blocs qui engagent la boutique (retours, garanties, chiffres) sont livrés vides et restent masqués (`returns-content.php:10-21`). ADR-007 « tout paramétrable » est respecté : 5 nouvelles pages de réglages dans FAMMA → Pages.
6. **CSS et RTL** : 14 feuilles et **0 propriété physique** (seules deux lignes de commentaire correspondent au motif). Les propriétés logiques passent de 187 à 293 lignes (même motif appliqué aux deux états). Les feuilles sont chargées selon la page (`inc/assets.php:184-506`, `inc/cart.php:347-377`), avec `filemtime()`.
7. **Moins de dette de surcharge** : `woocommerce/single-product/tabs/tabs.php` ne fait plus que charger le gabarit du cœur (`tabs.php:32-36`). La présentation passe par des hooks : dans `checkout-layout.php`, les actions natives sont rejouées sans copier de gabarit.
8. **Intégrité** : aucun doublon de classe ni de fonction (31 classes et 239 fonctions globales, `uniq -d` vide). La garde `ABSPATH` est présente dans les 51 fichiers PHP de `wp-content/`.

---

## 3. Nouveaux constats (depuis le 23/09)

Sévérité : **Critique / Élevée / Moyenne / Faible**. Effort : **S** (< ½ j), **M** (1 à 3 j), **L** (> 3 j). Colonne Prod : **oui** si le code est en ligne d'après le journal de déploiement.

| ID | Sév. | Constat | Preuve | Impact | Correctif recommandé | Effort | Prod |
|---|---|---|---|---|---|---|---|
| CODE-31 | **Élevée** | **Nonce de la newsletter figé dans une page mise en cache 7 jours.** Le formulaire imprime un nonce (valable 12 à 24 h) dans l'accueil, que LiteSpeed sert jusqu'à 7 jours. Au-delà de 24 h sans purge, **toutes** les inscriptions tombent dans `expired` et reçoivent un message d'erreur générique. Réessayer ne change rien : la page en cache porte le même jeton. | `inc/home-social.php:113` ; `newsletter.php:164-168` ; `home-social.php:60-66`. En ligne : `X-LiteSpeed-Cache: hit` sur `/`, `max-age=604800` sur une fiche. Le risque est noté mais non traité dans `DEPLOIEMENT-2026-09-23.md:246`, alors que le cache 7 jours est confirmé actif en `:329`. Le même piège est identifié et évité pour l'autre formulaire (`returns-content.php:291-296`). Au moment du test, l'ETag de `/` portait l'horodatage 1790352867 (≈ 16:14 UTC, 50 min plus tôt) : le défaut est **latent** et non encore observé. | Inscriptions perdues en silence sur l'accueil, pendant l'essentiel de la durée de vie du cache. | Servir le jeton par un point `wc-ajax` non caché, comme `Express_Order::send_nonce()` (`express-order.php:151-155`). Ou retirer le nonce : une inscription sans privilège est déjà protégée par le pot de miel et la limite par IP. Ou déclarer le nonce en ESI dans LiteSpeed Cache. À vérifier ensuite par une inscription depuis une copie en cache de plus de 24 h. | S | oui |
| CODE-32 | Moyenne | **Newsletter : nouveau stock de données personnelles sans outils de confidentialité.** Les adresses e-mail et la langue sont stockées dans `famma_subscriber`. Aucun exporteur ni effaceur n'est enregistré, rien ne trace le consentement (date, source, texte affiché) et aucune désinscription n'est prévue. | `newsletter.php:120-156`, `:195-203`. Aucun `wp_privacy_personal_data_exporters` ni `_erasers` dans le dépôt (grep vide). | Impossible de répondre à une demande d'accès ou d'effacement (loi 2004-63, RGPD). Même défaut que CODE-10, étendu à un nouveau jeu de données. | Déclarer un exporteur et un effaceur pour `famma_subscriber`. Stocker `_famma_nl_consent_at` et le texte de la mention. Prévoir la désinscription dans l'outil d'envoi. | M | oui |
| CODE-33 | Moyenne | **Doublons dans les nouvelles classes de contenu, et deux parseurs FAQ divergents.**<br>- `field()` identique en deux exemplaires, `text()` en 4 et `lines()` en 2.<br>- `seo_description()` existe en deux versions : l'une code le slug `'livraison'` en dur, l'autre utilise `self::SLUG`.<br>- `Product_Faq::parse()` découpe sur `\R{2,}`, renvoie des clés `q/a` et joint les lignes par `"\n"`. `Pages_Content::qa_pairs()` découpe sur `\R\s*\R`, renvoie `question/answer` et joint par `" "`. Le thème doit remapper les clés, et une ligne faite d'espaces ne sépare pas deux questions produit.<br>- Le picto WhatsApp est dessiné en SVG en ligne à deux endroits, avec deux tracés différents, alors que `famma_child_the_icon()` existe. | `delivery-content.php:120`, `:137`, `:147`, `:200` ; `returns-content.php:211`, `:228`, `:238`, `:276` ; `home-content.php:143` ; `product-content.php:200` ; `famma-core.php:936-958` vs `pages-content.php:518-537` ; `inc/product.php:815-829` ; SVG : `inc/product.php:125`, `functions.php:667`. | Règle 6 (« zéro doublon ») : un correctif appliqué à une copie n'atteint pas l'autre. C'est exactement ce qui s'est passé avec `bb0f2bd`, qui a corrigé le découpage d'une page sans toucher à la FAQ produit. | Un trait ou une classe abstraite `Editable_Page` (schéma, `field`, `text`, `lines`, `seo_description` par `SLUG`). Un parseur FAQ unique (`qa_pairs`), utilisé aussi par `Product_Faq`. Pictos via `famma_child_the_icon()`. | M | oui |
| CODE-34 | Faible | `famma_child_kses_image()` ajoute `fetchpriority` et `decoding`, mais **retire `srcset` et `sizes`**, que `wp_kses_allowed_html( 'post' )` n'autorise pas. L'image LCP de l'accueil est donc servie en 600×600 sur tous les écrans. Le défaut existait déjà avec `wp_kses_post()` ; la fonction ajoutée le 25/09 ne l'a pas repéré. | `inc/home.php:37-44`, `:239` ; WordPress 7.1 `wp-includes/kses.php:215-227`, où `img` n'a ni `srcset` ni `sizes`. En ligne, le premier `<img … fetchpriority="high">` de `/` n'a pas de `srcset`. | Aucune variante adaptée à l'écran pour l'élément LCP (LCP simulé 7,7-8 s selon le journal, §11.2). | `$allowed['img']['srcset'] = true; $allowed['img']['sizes'] = true;` | S | oui |
| CODE-35 | Faible | **Le compteur « En promotion » et la liste filtrée n'utilisent pas la même source.** Le compteur revérifie chaque produit avec `is_on_sale()`, la liste prend `wc_get_product_ids_on_sale()` tel quel. Si ce cache de WooCommerce est périmé, le menu « Promotions » disparaît (compteur à 0) alors que `?on_sale=1` liste un produit sans remise, ou l'inverse. | `inc/shop-widgets.php:81-111` vs `inc/shop.php:567-574` ; menu : `inc/header-parts.php:619` | Incohérence visible dans un cas rare, et logique catalogue toujours dans le thème (CODE-12). | Une fonction unique dans famma-core qui renvoie les identifiants vérifiés, utilisée par le compteur, le filtre et le menu. | S | oui |
| CODE-36 | Faible | Le **schéma d'une page est reconstruit à chaque lecture** d'un champ non enregistré : chaque appel de `text()` refait tout le tableau, soit des dizaines d'appels `__()`. `Returns_Content::schema()` compte 61 champs. | `pages-content.php:442-475` → `:362-371`, sans mise en mémoire ; `returns-content.php:79-198` ; appelé sur le panier et la commande (pages non cachées) par `famma_child_reassurance_items()` (`functions.php:298-322`) | Coût PHP inutile sur les pages non cachables, déjà lentes selon PERF-02. | Mettre en cache statique le schéma et l'option par page et par langue. | S | oui |
| CODE-37 | Faible | **Dérive entre la prod et le dépôt.** Le déploiement réel (archive et « Merge and overwrite ») ne supprime jamais rien, et `deploy.sh` n'est pas utilisé. Un fichier retiré du dépôt reste donc en ligne, et `tabs.php` n'est conservé que pour cette raison. | `DEPLOIEMENT-2026-09-23.md:12`, `:380` ; `tabs.php:18-21`. `product-accordion.js`, supprimé par `b1b4680` le 25/09, répond toujours **HTTP 200** (5 327 octets). | Code mort servi, et état de la prod impossible à reconstituer depuis Git. | Chaque journal liste les suppressions à faire (`git diff --diff-filter=D --name-only <déployé>..HEAD`). À terme, mode `tar`/`rsync` de `deploy.sh`, qui supprime. | S | n/a |
| CODE-38 | Faible | **Priorités de hooks réparties sur deux fichiers.** `functions.php` accroche la réassurance (35) et l'économie réalisée (11), puis `inc/product.php` les retire et les ré-accroche aux priorités 7 et 8 sur `wp`. À la lecture de `functions.php`, l'ordre du résumé est donc faux. | `functions.php:346`, `:463` ; `inc/product.php:35-54` | Maintenance piégeuse, et un `remove_action` qui ne trouve plus son hook échoue sans rien dire. | Accrocher chaque hook directement à sa priorité finale, dans le fichier qui le déclare. | S | oui |
| CODE-39 | Faible | Deux écarts de rigueur :<br>- **échappement précoce** : `esc_html()` est appliqué à la mise en cache du titre traduit, et non à l'affichage (règle 4) ;<br>- **l'en-tête de famma-core est périmé** : il décrit un fichier unique avec « one exception » (le contenu des pages) alors que 15 fichiers sont inclus, dont la newsletter, le SEO et la CAPI. | `includes/seo.php:497` ; `famma-core.php:14-23` vs `:33-47` | Double échappement possible si le titre est réutilisé, et documentation d'architecture trompeuse. | Rendre le texte brut et échapper au moment de la sortie. Mettre à jour l'en-tête et `docs/ARCHITECTURE.md`. | S | oui |
| CODE-40 | Faible | **Chaînes introduites sans traduction ou en dur** :<br>- le message 401 de `hardening.php` manque dans `fr_FR.po` et sort en anglais en prod ;<br>- le symbole `DT` est écrit en dur, hors gettext ;<br>- les nouvelles chaînes famma-core ont une source française, ce qui aggrave la langue source mixte (CODE-13). | `hardening.php:116` (absent de `languages/*.po`) ; en ligne, `/wp-json/wp/v2/users` renvoie « Sorry, you are not allowed to list users. » ; `famma-core.php:1197-1203` ; environ 201 sources françaises contre 137 anglaises (heuristique `i18n_cov.py`, contre 71/116 sur `2e40acc`) | Règle 7. Administration et API à moitié en anglais. | Ajouter la chaîne à `fr_FR.po`. `_x( 'DT', 'currency symbol', 'famma-core' )`. Trancher la langue source dans un ADR (voir CODE-13). | S | oui |
| CODE-41 | Faible | **La version PHP de la prod n'est documentée nulle part.** `38140b5` a remplacé le type de retour `never` (PHP 8.1) par `void` « pour compatibilité PHP 8.0 », alors que l'en-tête exige 8.1 et que phpcs teste `8.1-`. Si la prod tourne en 8.0, PHPCompatibility ne détectera pas la prochaine syntaxe réservée à 8.1. | `git show 38140b5` ; `DEPLOIEMENT-2026-09-23.md:240` ; `famma-core.php:7` ; `phpcs.xml.dist:26` | Erreur fatale possible au prochain usage d'une syntaxe 8.1, ou contrainte inutile. | Relever la version dans DirectAdmin (PHP Selector), viser 8.3 avec OPcache (PERF-02), puis aligner `Requires PHP` et `testVersion`. | S | n/a |

---

## 4. Évolution depuis le 23/09

Légende : **Corrigé**, **Partiel** (une partie du correctif est faite), **Inchangé**, **Régression** (plus grave qu'au 23/09), **Non revérifiable**. Pour les constats dont le code n'a pas changé, les hunks de `git diff 2e40acc..HEAD -- famma-core.php` sont limités à `Config`, `Order_Statuses`, `Product_Faq::parse`, `Tunisia`, `Capi::normalise_phone` et `boot()`.

| ID | Constat du 23/09 | Statut | Preuve |
|---|---|---|---|
| CODE-01 | Aucun e-mail « Nouvelle commande » | **Corrigé** | `famma-core.php:328-329` et `:343-364`, via les classes e-mail de WooCommerce. Reste à constater un envoi réel (journal : « à confirmer à la prochaine vraie commande »). Toujours pas de SMTP, avec un quota de 40 e-mails par jour. |
| CODE-02 | UTM dépendante des pixels | Inchangé | Même retour anticipé (`famma-core.php:1614-1616`) avant l'ajout du script UTM (`:1637-1638`), et toujours sans `Secure`. Fonctionne en ligne **uniquement parce que** le pixel Meta est configuré (`fbq('init',…)` et `famma_utm` présents sur `/`). |
| CODE-03 | `Purchase` CAPI fragile | Inchangé | `event_time` = date de création (`:1922`), `MAX_ATTEMPTS` sans mécanisme de reprise (`:1778`, `:1916`), `timeout` 8 s synchrone (`:1949`). |
| CODE-04 | « Terminée » contourne la règle COD | Inchangé | `add_to_wc_list()` ne retire aucun statut natif (`:413-431`). |
| CODE-05 | `deploy.sh` destructeur | **Partiel** | mu-plugins listés un par un et `famma-local-mail.php` exclu (`deploy.sh:85-91`, `:180`). Toujours pas d'instantané ni de mise en ligne atomique, et `--yes` couple encore confirmation et contrôle Git (`:149`). Les déploiements réels contournent le script (CODE-37). |
| CODE-06 | Aucune sauvegarde en prod | **Partiel** | Une sauvegarde complète manuelle le 23/09 (`DEPLOIEMENT-2026-09-23.md:7-8`). Aucune planification vérifiée, question posée à l'hébergeur (`:100`). `backup.sh` n'a pas changé (Docker seulement). |
| CODE-07 | oEmbed non mis en cache, cookies YouTube | **Partiel** | Mise en cache par transient 24 h, 1 h en cas d'échec (`product-video.php:249-257`). Toujours pas de `youtube-nocookie` ni de lecture au clic (grep vide). |
| CODE-08 | Tableau de bord : coûts non figés, 3 × `wc_get_orders(1000)` | Inchangé | `famma-core.php:2139-2148`, `:2209` (coût du produit actuel), `:2237-2239` (3 collectes). |
| CODE-09 | Règle coût×3 incomplète | Inchangé | `Product_Financials` (`:617-787`) non modifié. Aucun hook import, REST ou variation (grep vide). |
| CODE-10 | Données personnelles et consentement | Inchangé | Aucun exporteur, effaceur ni consentement (grep vide). **Aggravé dans les faits** : pixel Meta initialisé en ligne, zéro occurrence de « consent » sur `/`. La newsletter ajoute un jeu de données (CODE-32). |
| CODE-11 | Taille des fichiers et duplication | **Régression** | 17 → **27** fichiers de plus de 250 lignes. `famma-core.php` 2 313 → 2 491 lignes, `inc/product.php` 680 → 933, 19 772 lignes PHP au total (14 772 avant). Réassurance : source unique pour fiche, panier et commande (`functions.php:298-322`), mais copies restantes dans `home.php:138-148`, `footer-parts.php:217-226`, `header-parts.php:142-146`, `seo.php:918`, `topbar-content.php:202`. Parseur FAQ encore en double (CODE-33). |
| CODE-12 | Logique catalogue dans le thème | Inchangé | `inc/shop.php:557-576`, `inc/shop-widgets.php:118-126` et `:780`, `functions.php:730-737`. S'y ajoutent le compteur de promotions (`shop-widgets.php:81-111`) et le masquage de menu (`header-parts.php:618`). |
| CODE-13 | i18n incomplète, langue source mixte | Inchangé | `.pot` toujours à 64 entrées (13/08) pour 338 chaînes. `ar.po` famma-core couvre 29/338 (8,6 %), contre 28/187 re-mesuré sur `2e40acc` (24/190 selon le 23/09). Gouvernorats en dur (`famma-core.php:1234-1258`), en caractères latins sur `/ar/` (constaté). Côté thème, l'arabe passe de 117/143 à 164/188. |
| CODE-14 | Scripts non idempotents | Inchangé | `scripts/` : seul `deploy.sh` a changé (`git diff --stat`). |
| CODE-15 | Double balisage Google | Inchangé | Aucune garde `GOOGLESITEKIT_VERSION` (grep vide). En ligne, un seul `gtag` (`GT-…`, Site Kit) : le doublon n'existe pas tant que `GA4_MEASUREMENT_ID` reste vide. Pas d'événements e-commerce GA4. |
| CODE-16 | `famma_child_is_derja()` mémorisée trop tôt | Inchangé | `inc/i18n.php:312-325`. Symptôme **non observé** sur `/ar/product/extenseur-dorsal/` (onglet « الوصف », fil d'Ariane et stock en derja). |
| CODE-17 | Surcharges WooCommerce sans `@version` | Inchangé | `archive-product.php`, `single-product.php` et `loop/no-products-found.php` n'ont toujours pas de `@version`. `tabs.php` est désormais un simple relais, ce qui est une bonne chose. |
| CODE-18 | `! get_option( 'woocommerce_enable_myaccount_registration' )` | Inchangé | `inc/checkout.php:187` |
| CODE-19 | Redirections qui corrompent les UTM encodées | Inchangé | `mu-plugins/famma-legacy-urls.php` non modifié |
| CODE-20 | Livraison à 0 garantie par la configuration seule | Inchangé | `Shipping` non modifié. Aucun `woocommerce_package_rates` (grep vide). |
| CODE-21 | Cas limites COD | Inchangé | `Stock` non modifié. `refunded` absent des statuts qui libèrent le stock (grep vide). |
| CODE-22 | Callbacks de filtre strictement typés | Inchangé | Bonne pratique appliquée dans `hardening.php` et `newsletter.php`, mais **7 nouveaux** callbacks stricts : `famma-core.php:1148`, `:1179`, `:1197` ; `express-order.php:113` ; `performance.php:81`, `:97`, `:161`. |
| CODE-23 | JSON-LD sans `JSON_HEX_TAG` | Inchangé | `includes/seo.php:885` |
| CODE-24 | Purge de transients par `DELETE` direct | Inchangé | `inc/shop-widgets.php:118-126`, toujours lancée 3 fois par sauvegarde de produit |
| CODE-25 | `@phpcs:disable` sans fin | Inchangé | `includes/tracking-events.php:176` ; directives 28 → 45 |
| CODE-26 | 7 scripts CLI sans garde `ABSPATH` | Inchangé | Les mêmes 7 fichiers dans `scripts/` |
| CODE-27 | Hygiène du dépôt, `main` en retard | **Régression** | **44 commits n'existent sur aucune branche distante** (`git rev-list --count HEAD --not --remotes`) : `perf/optimisation-prod` n'a pas de branche distante, et le code en production n'a qu'**une seule copie**, sur ce poste. `main` est en retard de **71 commits** (30 au 23/09). L'ancien thème v1 et `zinger product*/` (62 et 10 Mo) ne sont toujours pas ignorés. |
| CODE-28 | Graph API `v21.0` | Inchangé | `famma-core.php:1773`. La fin de vie approche (à confirmer sur le journal des modifications de Meta). |
| CODE-29 | Métadonnées et capacités incohérentes | Inchangé | Version `0.2.0` et `Requires at least: 6.9` (`:5-6`, `:30`). Le type Newsletter demande `manage_options` sous un menu en `manage_woocommerce` (`newsletter.php:135-148`). Voir aussi CODE-41. |
| CODE-30 | `backup.sh` : mot de passe en argument | Inchangé | Fichier non modifié |

**Bilan** : 1 constat corrigé, 3 partiels, 24 inchangés, 2 en régression, 0 non revérifiable. Les correctifs du 23/09 visaient surtout les autres audits (UX-01, SEC-06, SEC-12, PERF-14, SEO-01/07). Parmi les 6 constats « Élevés » du 23/09, seul CODE-01 est clos ; CODE-05 et CODE-06 sont partiels.

---

## 5. Résultats `php -l`, `phpcs` et mesures chiffrées

- **`php -l`** : 61 fichiers PHP suivis hors `vendor/` (46 au 23/09), **0 erreur**.
- **`phpcs --standard=phpcs.xml.dist`** (WPCS 3 et PHPCompatibilityWP `8.1-`) : 61 fichiers en 24,9 s, **0 erreur et 0 avertissement**, code de sortie 0 (rapport JSON : `{'errors': 0, 'warnings': 0}`). Le jeu de règles n'a changé que par l'ajout de 86 noms de fonctions à la liste blanche `functionWhitelist` de `PHPCompatibility.Extensions.RemovedExtensions` (`phpcs.xml.dist:110-112`).
- **Réserves** : 45 directives `phpcs:*`, contre 28. Elles sont justifiées une à une en commentaire, mais `returns-content.php:304-312` désactive la vérification du nonce sur un bloc, par choix documenté. 7 nouveaux callbacks sont typés strictement (CODE-22). Aucun `declare(strict_types=1)`.
- **Garde `ABSPATH`** : présente dans les 51 fichiers de `wp-content/`, absente des 7 scripts CLI (CODE-26).
- **HPOS** : aucun accès `postmeta` ni `$wpdb` aux commandes. Le seul `$wpdb` reste la purge de transients (CODE-24).
- **Doublons** : aucune classe ni fonction déclarée deux fois (31 classes, 239 fonctions).

| Mesure | 23/09 (`2e40acc`) | 25/09 (`e08e55a`) |
|---|---|---|
| Fichiers PHP (hors `vendor/`) | 46 | 61 |
| Lignes PHP | 14 772 | 19 772 |
| Erreurs `php -l` | 0 | 0 |
| `phpcs` erreurs / avertissements | 0 / 0 | 0 / 0 |
| Directives `phpcs:*` | 28 | 45 |
| Lignes de `famma-core.php` | 2 313 | 2 491 |
| Fichiers de plus de 250 lignes | 17 | 27 |
| Plus gros fichiers | 2 313 · 1 048 · 808 | 2 491 (`famma-core.php`) · 1 137 (`seo.php`) · 933 (`product.php`) · 910 (`functions.php`) |
| `famma-core-ar.po` : couverture du code | 24/190 selon le 23/09 (28/187 re-mesuré) | 29/338 (8,6 %) |
| `famma-core.pot` | 64 entrées (13/08) | 64 entrées (13/08) |
| `famma-child-ar.po` : couverture | 117/143 (82 %) | 164/188 (87 %) |
| Littéraux arabes hors gettext | 75 | 165, dont 134 valeurs par défaut éditables des `*-content.php` (ADR-007). Thème : 48 → 42. |
| Propriétés CSS physiques / `!important` | 0 / 4 | 0 / 10 (6 dans `cart.css`, face aux `!important` de WooCommerce) |
| Commits hors de toute branche distante / retard de `main` | 3 / 30 | **44 / 71** |

## 6. Gabarits WooCommerce surchargés (thème enfant)

| Surcharge | `@version` enfant | Cœur (WC 11.0.1 local) | Écart |
|---|---|---|---|
| `woocommerce/archive-product.php` | *(absent)* | 8.6.0 | non traçable |
| `woocommerce/single-product.php` | *(absent)* | 1.6.4 | non traçable |
| `woocommerce/loop/no-products-found.php` | *(absent)* | 7.8.0 | non traçable |
| `woocommerce/single-product/tabs/tabs.php` | 9.8.0 | 9.8.0 | **relais vers le gabarit du cœur** (`tabs.php:32-36`) : plus de copie à resynchroniser |

Panier et commande sont restructurés par hooks (`inc/cart.php`, `inc/checkout-layout.php`, `inc/tunnel.php`), sans copie de gabarit. C'est conforme à la règle 13. En échange, le thème fait `remove_action` / `add_action` sur des callbacks internes de WooCommerce et de Kadence (`checkout-layout.php:51-53`, `tunnel.php:58-59`), qu'il faudra revérifier à chaque mise à jour.

## 7. Actifs (poids mesurés sur le dépôt, non minifiés)

- **CSS maison chargé** (d'après le HTML en ligne) : **137,8 Ko** sur une fiche produit (environ 120 Ko au 23/09) et 103,6 Ko sur l'accueil. `product.css` passe de 30 à 42,5 Ko. Nouveaux fichiers : `info-pages.css` 25,5 Ko, `cart.css` 17,8 Ko, `checkout.css` 17,3 Ko, `tunnel.css` 6,8 Ko, tous chargés selon la page. La minification LiteSpeed n'est pas encore active (journal §11.3).
- **Police arabe** : 123 688 → 31 288 octets (sous-ensemble, `bf4df15`), confirmé en ligne par le journal (§11.2).
- **JS maison** : 1 538 lignes au total. `product-accordion.js` a été retiré du dépôt au profit de `product-tabs.js`, mais il est toujours servi en prod (CODE-37).
- **Image LCP de l'accueil** : aucun `srcset` (CODE-34).

---

## 8. Quick wins (≤ ½ journée chacun, par gain décroissant)

1. **Pousser la branche** (`git push -u origin perf/optimisation-prod`), puis la fusionner dans `main` : aujourd'hui, le code de la prod n'existe que sur ce poste (CODE-27). À faire par le propriétaire, car c'est une action irréversible selon ClaudeMemo.
2. **Nonce de la newsletter servi par `wc-ajax`**, ou nonce retiré (CODE-31).
3. **Alias `completed` → callbacks de « livré »**, ou retrait des statuts natifs du sélecteur (CODE-04, toujours ouvert).
4. **Handle `famma-utm` indépendant des pixels**, et `;Secure` sur le cookie (CODE-02).
5. **`srcset` et `sizes`** dans `famma_child_kses_image()` (CODE-34).
6. Relever la version PHP de la prod et aligner `Requires PHP` et `testVersion` (CODE-41). PHP 8.3 avec OPcache (PERF-02).
7. `'yes' !== get_option(...)` (CODE-18), `@version` sur les 3 surcharges (CODE-17), `JSON_HEX_TAG` (CODE-23), `phpcs:disable` ciblé (CODE-25).
8. Chaîne 401 dans `fr_FR.po`, `DT` via `_x()` (CODE-40), mise en mémoire du schéma (CODE-36), source unique « en promotion » (CODE-35).
9. Journal de déploiement : liste des fichiers à supprimer, en commençant par `product-accordion.js` (CODE-37).
10. SMTP transactionnel et sauvegardes planifiées chez l'hébergeur (CODE-01 et CODE-06, côté propriétaire ou hébergeur).

## 9. Chantiers structurants (après les quick wins)

- **CAPI par Action Scheduler**, avec reprise, horodatage de livraison et passage à la Graph API courante (CODE-03 et CODE-28 : la fin de vie de v21.0 rend ce chantier urgent).
- **Consentement et confidentialité** : bannière avec Consent Mode, exporteurs et effaceurs pour les commandes et la newsletter (CODE-10, CODE-32). C'est désormais un sujet en ligne, puisque le pixel Meta est actif.
- **Coûts figés sur la ligne de commande**, et tableau de bord en une seule requête paginée (CODE-08).
- **Remboursement de la dette de taille** : `Editable_Page` commun aux 5 classes de contenu, découpage de `famma-core.php` (statuts, stock, financials, tracking, CAPI, dashboard), `inc/product.php` et `functions.php`, fin des copies de réassurance (CODE-11, CODE-33).
- **Logique catalogue déplacée dans famma-core** (CODE-12, CODE-35).
- **Passe i18n** : ADR sur la langue source, `.pot` régénéré, `fr_FR` et `ar` complétés, gouvernorats traduisibles (CODE-13, CODE-40).
