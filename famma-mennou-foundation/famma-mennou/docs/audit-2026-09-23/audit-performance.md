# Audit de performance — fammaminnou.tn

**Date** : 23 septembre 2026 · **Périmètre** : production (https://fammaminnou.tn), lecture seule · **Cible** : mobile, trafic publicitaire Meta / TikTok sur 4G tunisienne

---

## 0. Méthode et limites (à lire avant les chiffres)

| Élément | Détail |
|---|---|
| API PageSpeed Insights | **Échec** : 8 appels, tous en HTTP 429 « Quota exceeded … Queries per day » (projet anonyme partagé). Pas de nouvel appel, sur consigne. JSON d'erreur conservés dans `scratchpad/psi/*-mobile.json` et `*-desktop.json`. |
| Mesures labo de remplacement | **Lighthouse 13.5.0 en CLI**, Chrome headless local, lancé **depuis une connexion en Tunisie** (le serveur est lui aussi à Tunis, AS328853 OXAHOST, IP 102.204.204.69). Throttling **simulé** : mobile = RTT 150 ms, 1,6 Mbit/s, CPU ×4 (profil « 4G lente » de Lighthouse) ; desktop = RTT 40 ms, 10 Mbit/s. JSON bruts : `scratchpad/psi/lh-*.json`. |
| Données terrain (CrUX) | **Aucune** : l'API PSI ne répondait pas. Tous les Core Web Vitals ci-dessous sont des mesures de labo, pas de l'expérience réelle des utilisateurs. |
| Répétitions | Un seul passage Lighthouse par page. Avec un TTFB qui varie de 2 à 15 s, le score peut bouger de ±5 à 10 points d'un passage à l'autre. |
| Mesures curl | 2 séries de 20 requêtes espacées (vers 03 h 00–03 h 15), plus des contrôles ponctuels vers 14 h 45. Charge négligeable, aucun formulaire soumis. |
| Contexte serveur (fourni par le coordinateur, vérifié dans le panneau) | LiteSpeed 6.3.7, Xeon E5-2680 v4 **mutualisé**, MariaDB 10.6. **Seulement 4 plugins en prod** : famma-core, woocommerce, translatepress-multilingual, google-site-kit. **LiteSpeed Cache n'est pas installé.** Aucune tâche cron système : WP-Cron ne tourne qu'au passage des visiteurs. Le compte héberge aussi comin.tn, qui partage donc les mêmes ressources. |

> **Attention sur les scores Lighthouse.** Le modèle « simulate » estime le temps serveur à partir de toutes les requêtes vers l'origine. Les fichiers statiques rapides diluent donc un document HTML lent : pour la fiche produit desktop, Lighthouse annonce un LCP de 0,6 s alors que le document a mis 4,2 s à arriver. **Sans aucun throttling**, depuis la Tunisie, le LCP réellement observé est déjà de **2,4 s (accueil) et 3,1 s (produit)**. Sur un Android milieu de gamme en 4G, le LCP réel se situe très probablement **entre 4 et 5,5 s**, ce qui le classe « mauvais » au sens des Core Web Vitals.

---

## 1. Note globale : **4 / 10**

**Pourquoi 4 :**
- **Le front-end est plutôt propre (≈ 7/10 à lui seul).** Brotli actif ; HTTP/2 et HTTP/3 annoncés (`alt-svc: h3`) ; images produit en WebP avec `width`/`height` ; polices auto-hébergées en woff2 avec `font-display: swap`, `unicode-range` et préchargement ; `fetchpriority="high"` sur l'image principale de la fiche produit ; pas de `wc-cart-fragments`, pas de dashicons, pas de Google Fonts ; panier et checkout bien servis en `no-cache` ; nonce de la commande express récupéré dynamiquement, donc compatible avec un cache de page.
- **Le serveur fait s'effondrer la note.** Aucun cache de page, et PHP est très lent : **TTFB HTML médian de 3,6 s, p90 de 7,1 s, pic à 14,9 s**. Même un `robots.txt` de 1,6 Ko met de 1,6 à 15,6 s à sortir. Pour une visite venue d'une pub, c'est le premier facteur de rebond.
- **La fiche produit, qui est la page d'atterrissage des pubs, pèse 2 056 Kio pour 100 requêtes.** Environ **1 Mo d'images de galerie y est téléchargé en double ou en triple**.
- **La boutique mobile a un CLS de 0,487** (seuil « mauvais » : 0,25).
- **Le clic sur « Commander » (formulaire express) enchaîne 3 appels PHP non cachables.** Au TTFB actuel, cela fait de 6 à 20 s d'attente, voire plus.

**Potentiel :** installer LiteSpeed Cache avec les réglages de la section 6, puis appliquer les quick wins de la section 5, devrait amener la note vers 7,5–8/10 : TTFB des pages en cache d'environ 0,15–0,3 s et LCP mobile réel visé à ≤ 2,5 s.

---

## 2. Scores Lighthouse (substitut à PSI)

| Page | Profil | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---:|---:|---:|---:|
| Accueil `/` | mobile | **78** | 97 | 77 | 92 ¹ |
| Accueil `/` | desktop | 93 | 97 | 77 | 100 |
| Fiche produit `/produit/coupe-legumes-4-en-1/` | mobile | **72** | 97 | 77 | 100 |
| Fiche produit | desktop | 92 | 97 | 77 | 92 ¹ |
| Boutique `/boutique/` | mobile | **58** | 97 | 77 | 100 |
| Panier `/panier/` (vide) | mobile | 82 | 100 | 77 | 61 ² |
| Checkout `/commande/` | — | non mesurable : redirection 302 vers `/panier/` quand le panier est vide (normal) | | | |

¹ Audit `robots-txt` en échec : « Fetch of robots.txt failed: Timed out ». C'est un symptôme de PERF-02 : le robots.txt est généré par PHP.
² `noindex` volontaire sur le panier : ce n'est pas un défaut.
Bonnes pratiques à 77 partout : cookie tiers `fr` de Meta (`third-party-cookies`) et problème associé dans la console (`inspector-issues`). C'est inhérent au pixel.
Accessibilité : contraste du CTA « Découvrir la boutique », blanc sur `#ff7a00` = 2,61:1 (4,5 attendu), déjà recensé en D-1 dans UX-PLAN.

### Métriques (labo)

| Page / profil | FCP | LCP | TBT | CLS | Speed Index | TTI | TTFB doc | Poids | Requêtes | Élément LCP |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|
| Accueil mobile | 1,8 s | 3,2 s | 430 ms | 0,001 | 4,9 s | 7,1 s | 1,92 s | 991 Kio | 54 | image hero `visseuse-…-600x600.webp` |
| Produit mobile | 2,3 s | 3,6 s | 390 ms | 0,006 | 6,6 s | 8,0 s | 2,38 s | **2 056 Kio** | **100** | `h1.product_title` |
| Boutique mobile | 1,8 s | 3,0 s | 430 ms | **0,487** | 5,1 s | 7,1 s | 1,95 s | 869 Kio | 61 | 1re vignette produit |
| Panier mobile | 1,6 s | 2,0 s | 400 ms | 0 | 7,4 s | 8,0 s | 4,16 s | 1 061 Kio | 51 | message « panier vide » |
| Accueil desktop | 0,9 s | 1,1 s | 10 ms | 0,007 | 2,0 s | 1,9 s | 1,83 s | 1 013 Kio | 59 | image hero |
| Produit desktop | 0,5 s ³ | 0,6 s ³ | 30 ms | 0 | 3,4 s | 2,0 s | **4,21 s** | 1 772 Kio | 94 | `h1` |

³ Valeur simulée irréaliste, voir l'encadré de la section 0.

**Décomposition du LCP de l'accueil mobile (trace observée)** : TTFB 2 053 ms (**86 %**), délai de chargement 50 ms, chargement 182 ms, rendu 152 ms. **Le serveur représente l'essentiel du LCP.**

**LCP observé sans throttling** : accueil 2 436 ms, produit 3 090 ms.

### Répartition du poids (mobile)

| Page | Scripts | Images | Polices | CSS | HTML |
|---|---:|---:|---:|---:|---:|
| Accueil | 429 Kio (21) | 327 Kio (13) | 144 Kio (4) | 67 Kio (13) | 22 Kio |
| Produit | 455 Kio (29) | **1 350 Kio (42)** | 145 Kio (5) | 77 Kio (19) | 28 Kio |
| Boutique | 428 Kio (20) | 202 Kio (21) | 144 Kio (4) | 67 Kio (13) | 26 Kio |
| Panier | **832 Kio (29)** | 5 Kio | 144 Kio | 60 Kio | 19 Kio |

### Tiers (pixels)

| Tiers | Transfert | Temps thread principal (mobile) | Remarque |
|---|---:|---:|---|
| Meta Pixel (`fbevents.js` + `signals/config`) | **≈ 190 Kio** | 186–385 ms, tâches longues de 221–256 ms | Cache navigateur de 20 min seulement ; 34–37 % de code inutilisé ; script injecté en fin de `<body>` en async (bien) |
| Google tag via Site Kit (`gtag/js?id=GT-WRCTBPR9` → G-0M9XKVLDBB) | **172 Kio** | 216–253 ms | 42 % inutilisé ; ajoute 2 scripts « events provider » de Site Kit |
| TikTok Pixel | — | — | **Absent** (pas de `TIKTOK_PIXEL_ID` configuré). À budgéter avant activation, voir la section 7 |
| s.w.org (emojis WordPress) | 6–13 Kio, **8 à 13 requêtes** | faible | Nouvelle origine = DNS et TLS en plus sur 4G |

**Environ 365 Kio de JavaScript tiers**, soit 37 % du poids de l'accueil, et **450 à 640 ms de thread principal** sur mobile.

---

## 3. TTFB (curl, depuis la Tunisie)

Réseau sain : connexion TCP de 0,03 à 0,25 s, TLS terminé en ≈ 0,1 s. **Le temps perdu est entièrement du temps PHP.**

| Ressource | n | Min | Médiane | Max | Commentaire |
|---|---:|---:|---:|---:|---|
| Accueil, URL nue | 5 | 1,90 s | 3,20 s | **14,89 s** | 22 Kio br |
| Accueil `?nocache=…` | 5 | 2,08 s | 2,77 s | 4,34 s | **aucune différence avec l'URL nue : pas de cache de page** |
| Produit, URL nue | 5 | 2,96 s | **5,44 s** | 6,75 s | 28 Kio br |
| Produit `?nocache=…` | 5 | 2,12 s | 2,55 s | 5,16 s | |
| **Toutes les réponses PHP mesurées** (HTML, JSON wc-ajax) | 37 | 1,90 s | **3,59 s** | 14,89 s | p75 = 5,16 s, p90 = 7,15 s ; **65 % > 2,5 s, 43 % > 4 s** |
| `robots.txt` (généré par WP) | 3 | 1,58 s | 8,32 s | **15,65 s** | 1,6 Kio : le temps de démarrage de WordPress seul est déjà énorme |
| `/?wc-ajax=get_refreshed_fragments` | 3 | 1,73 s | 6,72 s | 16,64 s | idem |
| `/?wc-ajax=famma_express_nonce` | 1 | 1,89 s | — | — | en-têtes `no-store` corrects |
| Fichiers statiques (woff2, webp) | 6 | 0,13 s | 0,17 s | 0,27 s | LiteSpeed sert vite le statique |

**En-têtes observés sur le HTML** : `Server: LiteSpeed`, `Content-Encoding: br`, `Vary: Accept-Encoding,User-Agent`, `alt-svc: h3=":443"`. **Pas de `X-LiteSpeed-Cache`, pas de `Cache-Control`**, et aucun commentaire LiteSpeed Cache dans le HTML. HTTP/2 confirmé via Node `http2`. Le curl de Git Bash (Schannel) ne gère pas h2, ce qui explique le HTTP/1.1 dans les logs.

**Fichiers statiques** : `Cache-Control: public, max-age=604800` (7 jours) et ETag. Pages panier, commande et compte : `Cache-Control: no-cache, must-revalidate, max-age=0, private` et `Expires: 1984` → **correctement exclues.**

> **Anomalie écartée des statistiques** : entre ≈ 03 h 03 et 03 h 05, 17 réponses sont arrivées en 0,12–0,25 s avec un corps de 12 Ko br au lieu de 22–28 Ko. Le contenu n'a pas été sauvegardé. Il s'agissait probablement d'une page différente et légère, servie brièvement (mode « bientôt disponible », maintenance ou page d'erreur ?). À vérifier si quelqu'un a modifié la visibilité du site à ce moment-là.

---

## 4. Constats détaillés

Sévérité : **Critique** = perte de conversions probable ; **Haute** = CWV dégradés ; **Moyenne** / **Basse**. Effort : **S** < 1 h, **M** = ½ à 1 jour, **L** > 1 jour.

| ID | Sévérité | Constat et preuve chiffrée | Impact estimé | Correctif précis | Effort |
|---|---|---|---|---|---|
| **PERF-01** | **Critique** | **Aucun cache de page.** LiteSpeed Cache n'est pas installé, aucun en-tête `X-LiteSpeed-Cache`, TTFB identique avec ou sans cache-buster. Chaque visite venue d'une pub régénère la page en PHP. | TTFB médian 3,6 s → ≈ 0,15–0,3 s en cas de HIT. Lighthouse chiffre le gain à **1,8–2,3 s** sur FCP et LCP mobiles (4,1 s sur la fiche produit desktop). | Installer **LiteSpeed Cache** (plugin officiel, compatible avec le serveur LiteSpeed 6.3.7 déjà en place) avec les réglages de la **section 6**. C'est une décision propriétaire : ajout d'un 5e plugin. | S |
| **PERF-02** | **Critique** | **PHP très lent, même hors rendu.** `robots.txt` 1,6–15,6 s ; `wc-ajax` 1,7–16,6 s ; statique 0,13 s. Le goulot est le démarrage de WordPress ou les limites du compte mutualisé (partagé avec comin.tn), pas le thème. Le cache ne résoudra rien pour le panier, le checkout, `wc-ajax` et la commande express. | 2 à 15 s sur **chaque étape non cachable** du tunnel COD. | Dans DirectAdmin :<br>① **PHP Selector** : PHP 8.3 + **OPcache activé** (`opcache.memory_consumption=128`, `opcache.max_accelerated_files=10000`, `opcache.revalidate_freq=60`).<br>② **Resource Usage (LVE)** : repérer les *faults* CPU, EP, PMEM et IO. Si les limites sont atteintes, passer à une offre supérieure ou isoler comin.tn.<br>③ Taille des options autoloadées (`wp_options`, `autoload='yes'`) : objectif < 800 Ko, purger les transients expirés.<br>④ Demander à OXA Host si **Redis ou LSMCD** est disponible, puis activer l'Object Cache de LSCache (onglet Object).<br>⑤ Sur une copie de staging, mesurer avec Query Monitor le coût de TranslatePress et de Site Kit par requête. | S à M |
| **PERF-03** | **Critique** | **La commande express enchaîne 3 appels PHP au clic** (`express-order.js`, l. 167 à 222 de la version en ligne) : `wc-ajax=add_to_cart`, puis `famma_express_nonce`, puis `checkout`. | Au TTFB actuel : ≈ 6 s en médiane, 20 s et plus en pic entre le clic et la confirmation. Risque d'abandon et de double clic, donc de doublons de commande. | Côté famma-core : fusionner en **un seul endpoint `wc-ajax`** qui ajoute au panier et crée la commande dans la même requête, en gardant la vérification du nonce. Cela supprime 2 démarrages de WordPress sur 3. En attendant, désactiver le bouton et afficher un état « Envoi en cours » (le libellé existe déjà). À tester de bout en bout (règle §11). | M |
| **PERF-04** | **Haute** | **Galerie produit téléchargée 2 à 3 fois.** Sur `coupe-legumes-4-en-1` (10 photos) : les versions **768**, puis les **originaux 1080 px** (04 à 10, soit **≈ 670 Kio**), puis les **600** (05 à 10), plus l'original 01 (94 Kio) chargé par jQuery Zoom. Images : **1 350 Kio sur 42 requêtes** ; `image-delivery-insight` estime **692 Kio d'économie et 1,05 s de LCP**. Cause probable : `sizes="auto, …"`, ajouté par WordPress aux images lazy, se résout mal dans le carrousel FlexSlider pendant qu'il se met en place. | −1 Mo et −40 requêtes sur la page d'atterrissage des pubs : ≈ **5 à 6 s de téléchargement en moins en « 4G lente »**, sans parler de la data mobile des clients. | Dans le thème enfant ou famma-core :<br>① `add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );` (filtre existant depuis WP 6.7.1, utilisé aussi par `wp_get_attachment_image()`), ou forcer `sizes="(max-width: 767px) 100vw, 600px"` sur les images de galerie ;<br>② `remove_theme_support( 'wc-product-gallery-zoom' )` : le zoom au survol ne sert à rien au doigt, et on économise 94 Kio et `jquery.zoom` ;<br>③ vérifier qu'au chargement seule la photo 1 est chargée (les photos 2 et 3 le sont aujourd'hui sans lazy). À contrôler dans l'onglet Réseau après le correctif. | S à M |
| **PERF-05** | **Haute** | **CLS de 0,487 sur la boutique mobile.** Le serveur rend la colonne de filtres `aside.famma-shop__aside` visible, puis `shop-view.js` lui ajoute `hidden` sous 1000 px. Toute la grille `div.famma-shop__results` remonte alors d'un coup. | CLS de 0,49 → ≈ 0. Score Performance de la boutique de 58 → ≈ 80 et plus. | Rendre `hidden` **côté PHP** sur `<aside class="famma-shop__aside" id="famma-shop-filters" hidden>`. La règle CSS `@media (min-width:1000px){.famma-shop__aside[hidden]{display:flex}}` existe déjà pour le desktop. Le script se contente ensuite de retirer ou remettre `hidden` au clic sur « Filtres ». | S |
| **PERF-06** | **Haute** | **Rendu bloqué dans le `<head>`** : 13 CSS (accueil) à **20 CSS** (produit), plus 6 JS bloquants (`jquery`, `jquery-migrate`, `blockUI`, `js-cookie`, 2 fichiers TranslatePress). Économie estimée : **780 ms** de FCP (accueil) et **1 270 ms** de FCP et LCP (produit). `jquery-migrate` génère une tâche longue de **200 à 213 ms** sur mobile. | 0,8 à 1,3 s de FCP. | ① LSCache : CSS Minify et JS Minify, JS en **Deferred** (section 6).<br>② famma-core ou thème enfant : ne charger que le nécessaire, page par page. Sur la fiche produit : `photoswipe*.css/js` si la lightbox n'est pas utilisée, `kadence-comments`, `related-posts` et `kadence-splide` si les avis et le carrousel ne sont pas affichés, `comment-reply.js`. Partout : `wc-blocks.css` si aucun bloc Woo n'est utilisé, `brands.css` si les marques ne servent pas.<br>③ Retirer `jquery-migrate` via le hook `wp_default_scripts`, après un test complet du parcours. | M |
| **PERF-07** | **Haute** | **≈ 365 Kio de JS tiers** (Meta 190 Kio, gtag 172 Kio), soit 37 % du poids de l'accueil et 450 à 640 ms de thread principal. TBT mobile de 390 à 430 ms. | −200 à 300 ms de TBT, meilleur INP. | Garder le **Pixel Meta** chargé tôt : il est indispensable à l'optimisation des campagnes, ne pas le « retarder à l'interaction ». Retarder en revanche **gtag / Site Kit** après l'événement `load` (LSCache « JS Delayed Includes », section 6), en acceptant de perdre quelques sessions très courtes dans GA4. À terme, envoyer `PageView`, `ViewContent` et `AddToCart` aussi par **CAPI** depuis famma-core (déjà en place pour `Purchase`), avec déduplication par `eventID`. | M |
| **PERF-08** | **Haute** | **381 Kio de `zxcvbn.min.js` plus l'indicateur de force du mot de passe sur `/panier/`** : scripts `password-strength-meter` et `wc-password-strength-meter`. S'y ajoutent `selectWoo` (20 Kio), `country-select` et `address-i18n` pour le calculateur de livraison. Scripts du panier : **832 Kio**. | −400 Kio et une tâche longue de 167 ms sur le panier, probablement pareil sur le checkout. | WooCommerce > Réglages > **Comptes** : cocher « envoyer un lien pour définir le mot de passe », ce qui supprime le champ mot de passe et donc zxcvbn. Ou décharger `wc-password-strength-meter` sur le panier et le checkout pour les invités (famma-core). WooCommerce > Réglages > Livraison > **décocher « Activer le calculateur de livraison sur la page panier »** : la livraison affichée est à 0 TND, le calculateur est inutile. | S |
| **PERF-09** | Moyenne | **Hero de l'accueil** : les 4 diapositives sont chargées tout de suite (230 Kio, dont **193 Kio d'images cachées**) et entrent en concurrence avec le LCP. L'image LCP n'a ni `fetchpriority` (`lcp-discovery-insight` en échec) ni `srcset`. `tapis-pique-nique-xl-01-600x600.webp` pèse **110 Kio**, dont 62 Kio récupérables. | −150 à 350 ms de LCP, −200 Kio. | Dans le gabarit du hero (thème enfant) : `fetchpriority="high"` sur la diapo 1, `loading="lazy"` et `fetchpriority="low"` sur les suivantes. Recompresser les originaux trop lourds (WebP qualité ≈ 75, objectif ≤ 60 Kio en 600 px). | S |
| **PERF-10** | Moyenne | **Police arabe de 121 Kio sur toutes les pages, français compris.** Le slogan, le hero, les accroches des cartes et le pied de page contiennent de l'arabe au-dessus de la ligne de flottaison. Il s'agit de `noto-kufi-arabic-var.woff2` avec l'axe de graisse complet (100 à 900). | −70 à 90 Kio par première visite. | Sous-ensemble de la police avec fonttools : `fonttools varLib.instancer` pour limiter la graisse à 400–700, puis `pyftsubset` sur le bloc arabe U+0600–06FF, U+FE70–FEFF et la ponctuation. Objectif : 35 à 55 Kio. Précharger `poppins-700`, utilisé au-dessus de la ligne de flottaison mais découvert tard, et supprimer une graisse de Poppins si possible. | M |
| **PERF-11** | Moyenne | **Emojis WordPress** : script module et 8 à 13 SVG chargés depuis `s.w.org` sur la fiche produit et la boutique (🔍 du déclencheur de galerie Woo, emojis des descriptions). | Une origine tierce et 13 requêtes en moins. | LSCache > Page Optimization > HTML > **Remove WordPress Emoji : ON**. Les navigateurs mobiles actuels affichent les emojis nativement. | S |
| **PERF-12** | Moyenne | **Images hors WebP** : vignettes de catégories `FAMMA_*_carousel-300x300.jpg` (≈ 15 Kio chacune, 13 Kio récupérables par image) ; `ChatGPT-Image-16-aout-2026…-300x300.png` = **127 Kio** pour une vignette de 300 px (produit `prd-1`). | −40 Kio sur l'accueil, −110 Kio sur la boutique. | Réencoder en WebP. Pour les futurs envois, utiliser le filtre `image_editor_output_format` (JPEG/PNG → WebP) dans famma-core. **Hors performance** : le produit `prd-1` illustré par une image « ChatGPT » ressemble à un produit de test. Le soumettre au propriétaire (règle §12, aucune donnée fictive). | S |
| **PERF-13** | Moyenne | **`robots.txt` généré par PHP** : jusqu'à 15,6 s, et Lighthouse n'arrive pas à le récupérer (SEO 92). Si le fichier répond trop lentement, Google peut ralentir l'exploration du site. | Fiabilité de l'exploration. | Réglé en grande partie par PERF-02. Sinon, créer un fichier `robots.txt` **statique** reprenant le contenu actuel. Contrepartie : il ne serait plus modifiable depuis l'admin (règle « tout paramétrable »), donc à arbitrer par le propriétaire. | S |
| **PERF-14** | Moyenne | **WP-Cron déclenché par les visites, sans cron système.** Une fois le cache de page actif, les visites servies depuis le cache n'exécutent plus PHP : les tâches planifiées (Action Scheduler de Woo, nettoyages, relances) seront retardées de façon imprévisible. | Fiabilité ; supprime le coût du cron pris sur les requêtes des visiteurs. | DirectAdmin > Cron Jobs, toutes les 5 min : `/usr/local/bin/php /home/fammamin/domains/fammaminnou.tn/public_html/wp-cron.php >/dev/null 2>&1` (chemin PHP à confirmer), et `define( 'DISABLE_WP_CRON', true );` dans `wp-config.php`. **À faire en même temps que PERF-01.** | S |
| **PERF-15** | Basse | **Cache navigateur de 7 jours** sur des ressources pourtant versionnées (`?ver=`). La moitié des Kio que Lighthouse juge « mal cachés » viennent de Meta (20 min, non maîtrisable). `Vary: User-Agent` sur tout le statique. | Visites de retour (retargeting) plus rapides. | LSCache > Cache > Browser : **Browser Cache ON, TTL 31557600**. | S |
| **PERF-16** | Basse | **CSS du thème enfant non minifiés** (17 à 18 Kio récupérables) ; `kadence/woocommerce.min.css` fait 144 Kio bruts et est **inutilisé à 97 %** sur la boutique. 8 fichiers CSS rien que pour le thème enfant. | −15 à 30 Kio, 150 ms de FCP. | LSCache CSS Minify, avec ou sans CSS Combine (section 6). À plus long terme : un seul `famma.min.css` construit par page type. | S à M |
| **PERF-17** | Basse | **Sélecteur flottant de TranslatePress en double** du lien `عربي` de l'en-tête : CSS de 9 Kio et JS de 16 Kio bruts bloquants dans le `<head>`, drapeaux SVG, et un défaut d'accessibilité (`label-content-name-mismatch`). | −2 requêtes bloquantes. | TranslatePress > Réglages > Sélecteur de langue : **désactiver le sélecteur flottant** si le lien de l'en-tête suffit (validation propriétaire). | S |
| **PERF-18** | Basse | **HTML lourd** : 124 Ko (accueil), 150 Ko (produit), 178 Ko (boutique) décompressés, dont ≈ 34 Ko de CSS en ligne dans le `<head>` (`global-styles` 22 Ko, Kadence 12 Ko). | Faible, grâce à brotli (22–28 Ko transférés). | HTML Minify via LSCache ; réduire les préréglages de `theme.json` si c'est possible sans casser l'éditeur. | S |
| **PERF-19** | Basse | Speculation Rules en mode `conservative` : préchargement au clic seulement. | Navigation accueil → produit quasi instantanée. | **Une fois le cache actif**, passer en `moderate` (préchargement au survol ou au toucher) via le filtre `wp_speculation_rules_configuration`. Avant le cache, cela ne ferait qu'ajouter de la charge PHP. | S |

**Points vérifiés sans défaut** : pas de `cart-fragments` ni de dashicons ; aucun embed ou iframe ; **aucune vidéo produit en ligne** (aucune balise `<video>` sur les 16 fiches) ; images produit en WebP avec dimensions ; `font-display: swap` ; en-têtes no-cache du panier, du checkout et du compte ; endpoint de nonce express en `no-store` ; aucune détection mobile côté serveur. Graph miss sur `wp_is_mobile`, puis grep sur le thème enfant et famma-core : seule `stamp_capi_signals()` lit le User-Agent, et seulement au moment de la commande.

---

## 5. Quick wins (par ordre de rendement)

1. **Installer et régler LiteSpeed Cache** (PERF-01, section 6), et **en même temps** configurer le cron système avec `DISABLE_WP_CRON` (PERF-14). Gain attendu : **−2 s et plus de LCP** sur toutes les pages publiques.
2. **OPcache et PHP 8.3, et vérifier les *faults* LVE** dans DirectAdmin (PERF-02). C'est la seule action qui accélère le panier, le checkout et la commande express.
3. **Corriger le CLS de la boutique** : attribut `hidden` rendu en PHP (PERF-05). Une ligne.
4. **Galerie produit** : désactiver `sizes="auto"` et le zoom (PERF-04), soit −1 Mo sur la page d'atterrissage.
5. **Panier** : réglage « lien pour définir le mot de passe » et suppression du calculateur de livraison (PERF-08), soit −400 Kio.
6. **Remove WordPress Emoji** et **Browser Cache 1 an** dans LSCache (PERF-11, PERF-15).
7. **Hero** : `fetchpriority` sur la diapo 1 et `lazy` sur les suivantes (PERF-09).

---

## 6. Réglages LiteSpeed Cache recommandés (plugin à installer)

Contexte : WooCommerce + TranslatePress (langues par répertoire `/ar/`) + famma-core (UTM capturés **en JS** et nonce express récupéré en AJAX : ✅ compatible cache) + en-tête avec **compteur de panier rendu côté serveur** (`span.famma-cart__count`). Documentation LSCWP consultée (docs.litespeedtech.com, dépôt `litespeedtech/lscache_wp`).

### Cache → Cache
| Réglage | Valeur | Raison |
|---|---|---|
| Enable Cache | **ON** | |
| Cache Logged-in Users | **OFF** | Clients COD invités ; évite le cache privé par utilisateur sur un serveur mutualisé |
| Cache Commenters | OFF | |
| Cache REST API | **OFF** | Rien ne passe par REST en front ; évite de mettre en cache la Store API |
| Cache Login Page | OFF | |
| Cache Mobile | **OFF** | HTML responsive identique sur mobile et desktop, aucun `wp_is_mobile()` (vérifié) |
| Private Cached URIs / Force Cache URIs | vide | |
| **Drop Query String** | `fbclid` `gclid` `gbraid` `wbraid` `ttclid` `utm*` `_ga` `_gl` `srsltid` `igshid` `mc_cid` `mc_eid` (un par ligne) | **Essentiel pour le trafic publicitaire** : sans cette liste, chaque clic Meta ou TikTok (`fbclid` / `ttclid` uniques) produit un MISS. Aucun risque pour le tracking : la capture UTM de famma-core et l'attribution Woo (sourcebuster) lisent `location.search` **côté navigateur** |

### Cache → TTL
| Réglage | Valeur |
|---|---|
| Default Public Cache TTL | 604800 (7 j) |
| Default Private Cache TTL | 1800 |
| Default Front Page TTL | 604800 |
| Default Feed TTL | 604800 |
| Default HTTP Status Code Page TTL | `403 3600` et `404 3600` (**ne pas mettre en cache les erreurs 500**) |

### Cache → Purge
| Réglage | Valeur |
|---|---|
| Purge All On Upgrade | **ON** |
| Auto Purge Rules For Publish/Update | Front page, Home page, Pages, Product category archives (Term archive), Post type archive (boutique). La fiche modifiée est toujours purgée |
| Serve Stale | **OFF** (prix et stock doivent être exacts) |

### Cache → Excludes
| Réglage | Valeur |
|---|---|
| Do Not Cache URIs | `/panier/` `/commande/` `/mon-compte/` et leurs équivalents `/ar/…`. **Vérifier les slugs traduits par TranslatePress.** WooCommerce pose déjà `DONOTCACHEPAGE`, que LSCache respecte : cette liste est une double sécurité |
| Do Not Cache Query Strings | `add-to-cart` `wc-ajax` |
| Do Not Cache Cookies / User Agents / Roles | vide |

### Cache → WooCommerce
| Réglage | Valeur | Raison |
|---|---|---|
| Product Update Interval | « Purge product on changes to the quantity or stock status. Purge categories only when stock status changes. » | |
| **Vary for Mini Cart** | **ON** | Le compteur de l'en-tête est rendu en PHP, sans mise à jour JS : sinon un client avec un panier rempli verrait « 0 » sur les pages en cache. Alternative plus performante : mettre à jour le compteur en JS seulement si le cookie `woocommerce_items_in_cart` existe, puis repasser ce réglage à OFF |

### Cache → ESI / Object / Browser / Advanced
| Réglage | Valeur |
|---|---|
| ESI | OFF (nécessite LiteSpeed Enterprise ; à valider auprès d'OXA Host) |
| Object Cache | **ON uniquement si OXA Host fournit Redis ou LSMCD** (Method, Host et Port donnés par l'hébergeur ; Default Object Lifetime 360 ; Persistent Connection ON). Sinon OFF |
| Browser Cache | **ON**, TTL **31557600** |
| Instant Click | OFF (préférer les Speculation Rules en `moderate`, PERF-19) |

### Page Optimization (à activer **un par un**, en testant à chaque fois ajout au panier → commande express → checkout COD)
| Onglet | Réglage | Valeur |
|---|---|---|
| CSS | CSS Minify | **ON** |
| CSS | CSS Combine | ON, à tester (gain modéré en HTTP/2 ; revenir à OFF si le rendu change) |
| CSS | Generate UCSS / Load CSS Asynchronously (CCSS) | OFF au départ (nécessitent un compte **QUIC.cloud**, décision propriétaire). Ce sont les plus gros gains restants sur le rendu bloquant, si le propriétaire accepte le service |
| CSS | Font Display Optimization | Swap (règle aussi la police `star.woff2` de Kadence) |
| JS | JS Minify | **ON** |
| JS | JS Combine | **OFF** (risque avec WooCommerce et express-order) |
| JS | Load JS Deferred | **Deferred** (pas « Delayed », qui retarderait le Pixel Meta jusqu'à l'interaction et ferait perdre des PageView d'annonces) |
| Tuning | JS Delayed Includes | `googletagmanager.com/gtag/js` `google_gtagjs-js-after` `googlesitekit-events-provider` (GA4 retardé, voir PERF-07) |
| Tuning | JS Deferred/Delayed Excludes | `famma-pixels` `connect.facebook.net` (Pixel non retardé) ; ajouter `express-order` si le formulaire casse |
| HTML | HTML Minify | ON |
| HTML | DNS Prefetch | `//connect.facebook.net` `//www.facebook.com` `//www.google-analytics.com` |
| HTML | DNS Preconnect | `//connect.facebook.net` |
| HTML | **Remove WordPress Emoji** | **ON** |
| HTML | Remove Query Strings | OFF (les `?ver=` servent justement de cache-busting) |
| Media | Lazy Load Images | OFF : WordPress et le thème gèrent déjà le lazy loading, et l'activer risquerait de rendre lazy l'image LCP. S'il est activé, exclure les classes `famma-hero2__slide` et `wp-post-image` (Lazy Load Image Class Name Excludes) |
| Media | Viewport Images (VPI) / LQIP | OFF (QUIC.cloud) |
| Image Optimization | — | Facultatif, via QUIC.cloud (WebP/AVIF automatiques), décision propriétaire. Sinon, filtre `image_editor_output_format` (PERF-12) |

**Contrôle après installation** : `curl -sI https://fammaminnou.tn/` deux fois de suite doit renvoyer `x-litespeed-cache: miss` puis `hit`. Idem avec `?fbclid=test123` (doit renvoyer `hit`, preuve que le Drop Query String fonctionne). `/panier/` doit renvoyer `no-cache`. Refaire le parcours COD complet avec `WP_DEBUG` et contrôler `debug.log` (règle §11).

---

## 7. Budget de performance recommandé (site COD, mobile, 4G tunisienne)

Référence de mesure : Lighthouse mobile « 4G lente » (150 ms, 1,6 Mbit/s, CPU ×4) et, dès que le trafic le permet, les données terrain CrUX et Search Console au 75e centile.

| Indicateur | Budget | Actuel (accueil / produit) |
|---|---|---|
| TTFB HTML en cache (p75) | **≤ 0,3 s** | 3,2 s / 5,4 s (médianes) |
| TTFB PHP non cachable (panier, checkout, `wc-ajax`) | **≤ 0,8 s** | 1,7 à 16 s |
| Clic « Commander » (express) → confirmation | **≤ 3 s** | ≈ 6 à 20 s et plus (3 appels PHP) |
| LCP (p75) | **≤ 2,5 s** | 3,2 s / 3,6 s simulés ; 4 à 5,5 s probables en réel |
| FCP | ≤ 1,8 s | 1,8 s / 2,3 s |
| INP (terrain) / TBT (labo) | ≤ 200 ms / ≤ 200 ms | — / 430 ms et 390 ms |
| CLS | ≤ 0,1 (objectif 0,02) | 0,001 / 0,006 ; **boutique 0,487** |
| Poids première visite | Accueil ≤ 700 Kio · Produit ≤ 900 Kio · Boutique ≤ 700 Kio · Checkout ≤ 500 Kio | 991 / **2 056** / 869 / panier 1 061 |
| Requêtes | ≤ 50 | 54 / **100** |
| JS total (compressé) | ≤ 300 Kio, dont 1re partie ≤ 80 Kio | 429 / 455 Kio |
| JS tiers | ≤ 220 Kio **avec** TikTok ; à défaut, GA4 retardé | ≈ 365 Kio **sans** TikTok |
| CSS (compressé) | ≤ 60 Kio, ≤ 6 fichiers bloquants | 67 à 77 Kio, 13 à 20 fichiers |
| Image LCP | ≤ 60 Kio | 37 Kio (accueil) ✅ |
| Images au-dessus de la ligne de flottaison | ≤ 150 Kio | ≈ 230 Kio (hero 4 diapos) / ≈ 218 Kio (produit, 3 images en 768) |
| Polices | ≤ 100 Kio, ≤ 2 préchargées | 144 Kio |
| Origines tierces | ≤ 4 | 5 (fb.net, facebook.com, gtm, ga, s.w.org) |

**Règle d'usage** : tout ajout (pixel TikTok, avis clients, chat, nouvelle bibliothèque) doit tenir dans ce budget, ou retirer quelque chose d'équivalent. Refaire un passage Lighthouse mobile sur l'accueil, une fiche produit et la boutique à chaque mise en production.

---

## 8. Poids des ressources du thème dans le dépôt (pour information)

`E:\Ecom\famma-mennou-foundation\famma-mennou\wp-content\themes\famma-child\assets\` : **412 Ko, 31 fichiers**.

| Dossier | Fichiers | Octets | Plus gros fichiers |
|---|---:|---:|---|
| `fonts/` | 8 | 167 375 | `noto-kufi-arabic-var.woff2` **123 688** ; poppins 400/500/600/700 ≈ 7,8 Ko chacune ; 2 licences OFL et un README (non servis) |
| `css/` | 9 | 127 459 | `product.css` 30 056 (**26 Ko en production** : la branche locale est en avance), `layout.css` 23 717, `shop.css` 18 467, `home.css` 15 652, `tokens.css` 13 624, `product-card.css` 12 564. **Aucun fichier minifié** |
| `js/` | 9 | 29 945 | `shop-view.js` 6 093, `sticky-cta.js` 3 856, `carousel.js` 3 847 |
| `img/` | 5 | 31 302 | `famma-og.png` 25 158, SVG < 3,1 Ko |

Côté plugin : `famma-core/assets/css/express-order.css` 5,5 Ko et `express-order.js` 7,6 Ko en production.

---

## 9. Fichiers produits pendant l'audit

- `scratchpad/psi/lh-home-mobile.json`, `lh-home-desktop.json`, `lh-product-mobile.json`, `lh-product-desktop.json`, `lh-shop-mobile.json`, `lh-cart-mobile.json` (rapports Lighthouse bruts)
- `scratchpad/psi/*-mobile.json` et `*-desktop.json` (réponses 429 de l'API PSI)
- `scratchpad/ttfb.txt`, `scratchpad/ttfb2.txt` (mesures curl brutes)
- `scratchpad/home.html`, `shop.html`, `prod-*.html`, `panier.html` (HTML capturé)
- `scratchpad/assets-sizes.txt` (poids, compression et cache de chaque CSS/JS de l'accueil)
