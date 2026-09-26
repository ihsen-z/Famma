# Audit de performance — fammaminnou.tn (contre-audit)

**Date** : 25 septembre 2026 (mesures prises de 17 h 51 à 18 h 16) · **Périmètre** : production (https://fammaminnou.tn), lecture seule · **Cible** : mobile, trafic publicitaire Meta / TikTok sur 4G tunisienne · **Référence** : `docs/audit-2026-09-23/audit-performance.md` (note 4/10)

---

## 0. Méthode et limites (à lire avant les chiffres)

| Élément | Détail |
|---|---|
| API PageSpeed Insights | **Échec, quota épuisé** : 1 appel test, HTTP 429 « Quota exceeded … Queries per day » (projet anonyme partagé, `quota_limit_value: 0`). Pas de nouvel essai. Réponse conservée dans `scratchpad/perf25/psi/home-mobile.json`. |
| Données terrain (CrUX) | **Aucune**, pour la même raison (CrUX passe par PSI ou par une clé API que nous n'avons pas). Tous les Core Web Vitals ci-dessous sont des mesures de labo. |
| Lighthouse | **Lighthouse 13.5.0 en CLI** (`npx -y lighthouse@13`), Chrome headless local, depuis une connexion en Tunisie, **exactement les réglages du 23/09** : mobile « 4G lente » simulée (RTT 150 ms, 1,6 Mbit/s, CPU ×4), desktop preset. 8 passages (4 pages × 2 profils) avec 60 s de pause entre deux passages, plus 3 passages de contrôle : 2e passage mobile accueil et fiche (variance) et 1 passage fiche avec **bridage appliqué** (`--throttling-method=devtools`, réseau et CPU réellement ralentis). |
| TTFB | curl (`%{time_starttransfer}`), User-Agent Android Chrome, `Accept-Encoding: br`, **même protocole que le 23/09** : 5 appels espacés de 6 à 13 s par page, médiane, p90, max. 52 requêtes au total, puis 12 vers les concurrents (autres hôtes). En-tête `X-LiteSpeed-Cache` relevé à chaque réponse. |
| Anti-bot Imunify360 | Aucune page de challenge détectée : les 52 réponses HTML font plus de 80 Ko décompressés et contiennent un `<title>` (contrôle automatique dans `stats.py`). **Aucune mesure écartée.** |
| Commande express | **Analyse statique du code uniquement.** Aucune commande passée, aucun formulaire envoyé. |
| Faits « relevé dans le panneau » | Fournis par le coordinateur après lecture de wp-admin et DirectAdmin le 25/09, non modifiés : LiteSpeed Cache 7.9.1 (installé le 24/09), cache public ACTIF, cache privé ACTIF, cache objet INACTIF, cache navigateur INACTIF, QUIC.cloud non activé, optimisation d'images 0 % ; `.htaccess` : `CacheLookup on`, Drop Query String = `fbclid`, `gclid`, `utm*`, `_ga` ; cron système */5 min vers `wp-cron.php` et `DISABLE_WP_CRON` (mu-plugin `famma-cron.php`) ; serveur LiteSpeed 6.3.7, HTTP/3, Brotli, Imunify360. |
| Répétitions | 1 passage par page, 2 pour l'accueil et la fiche mobile. Les deux passages de la fiche mobile donnent 41 et 49 : **±5 à 8 points de bruit** à garder en tête. |

> **Lire les scores Lighthouse mobile avec prudence (voir §3.1).** Ils baissent alors que tout ce qui se mesure directement s'améliore : TTFB divisé par 15, poids en baisse de 17 à 45 %, premier affichage observé plus rapide de 24 à 43 %. Il s'agit surtout d'un effet du modèle « simulate ». Mais cet effet révèle un vrai problème : maintenant que le serveur répond vite, **ce sont les octets tiers et la galerie qui retardent l'affichage**.

---

## 1. Note globale : **6 / 10** (contre 4 / 10 le 23/09)

**Pourquoi 6 :**
- **Le premier constat critique est réglé.** Les pages publiques sont servies depuis le cache LiteSpeed : **TTFB médian de 0,16 s sur 26 réponses HIT (max 0,35 s)**, contre 3,2 à 5,4 s le 23/09. L'accueil répond en **0,20 s de médiane, soit 16 fois plus vite**. FAMMA passe de 5 à 10 fois plus lent que ses concurrents à **plus rapide que les trois** (Mantoujna 0,34 s, Dokani 0,48 s, Express Shop 0,86 s en moyenne). `fbclid` et `utm_*` sont bien ignorés par le cache, donc un clic Meta tombe sur une page en cache.
- **Les correctifs front du 25/09 sont en ligne et visibles** : CLS de la boutique à **0** (0,487 avant), fiche produit à **1 411 Kio en 73 requêtes** (2 056 Kio en 100 requêtes avant), plus aucune image en double ni aucun original 1080 px, police arabe de 121 à 31 Kio, emojis retirés, zxcvbn absent du panier (poids du panier de 1 061 à 585 Kio), préchargement `moderate`, cron serveur actif.
- **Mais tout ce qui passe par PHP reste lent.** Panier : 2,33 s de médiane, jusqu'à 5,97 s. `wc-ajax` : jusqu'à 4,73 s. Médiane des réponses PHP : **2,55 s, p90 5,73 s**. Le plancher d'environ 2 s n'a pas bougé (OPcache, *faults* LVE et cache objet non traités). La **commande express enchaîne toujours 3 appels PHP**, plus la page de remerciement : environ 8 à 10 s en médiane entre le clic et la confirmation.
- **Deux trous dans le cache pénalisent la publicité** : `ttclid` n'est pas ignoré, si bien que **chaque clic TikTok produit un MISS à 3 s** (PERF-20). Et chaque « Tout purger » laisse les fiches froides pour les visiteurs suivants (PERF-21).
- **Côté front, il reste le plus lourd** : environ 365 Kio de pixels non retardés, 10 photos de galerie téléchargées d'emblée (795 Kio d'images sur la fiche), 4 diapos du hero chargées tout de suite, aucune optimisation de page LiteSpeed (CSS/JS non minifiés, JS non différé), cache navigateur à 7 jours. D'où des scores Lighthouse mobile de 41 à 71.

**Potentiel** : PERF-20 et PERF-21 (réglages, moins d'une heure) plus la section 7 (JS différé, GA4 retardé, minification, cache navigateur), puis la galerie (PERF-04) et OPcache (PERF-02), devraient amener la note à **7,5–8 / 10**.

---

## 2. Évolution depuis le 23/09

### 2.1 Mesures avant / après

| Indicateur | 23/09 | 25/09 | Évolution |
|---|---:|---:|---|
| TTFB accueil, URL nue (médiane / p90 / max) | 3,20 s / ≈ 7 s / 14,89 s | **0,20 s / 0,29 s / 0,35 s** (5 HIT) | ÷ 16 |
| TTFB fiche produit, URL nue (médiane) | 5,44 s | **0,18 s** (1 MISS froid à 2,54 s, puis 4 HIT) | ÷ 30 |
| TTFB boutique / catégorie (médiane) | — / — | **0,18 s / 0,16 s** (1er appel MISS à 2,4–2,55 s) | nouveau |
| TTFB panier (médiane / max) | 4,16 s (document Lighthouse) | **2,33 s / 5,97 s** | −44 %, toujours non cachable |
| Toutes réponses PHP (MISS + non cachables) : médiane / p90 / max | 3,59 / 7,15 / 14,89 s (n = 37) | **2,55 / 5,73 / 6,09 s** (n = 26) | pics réduits, plancher de 2 s inchangé |
| Part des réponses PHP > 2,5 s / > 4 s | 65 % / 43 % | 57 % / 26 % | mieux |
| `robots.txt` (médiane) | 8,32 s | **0,15 s** (HIT) ; 2,58 s au 1er appel | ÷ 55 |
| `?wc-ajax=get_refreshed_fragments` (médiane) | 6,72 s | 3,55 s | −47 % |
| URL avec `?fbclid=` unique | MISS (pas de cache) | **HIT, 0,15 s** | ✅ |
| URL avec `?ttclid=` unique | MISS (pas de cache) | **MISS, 3,04 s** | ❌ PERF-20 |
| Lighthouse mobile : accueil / fiche / boutique / panier | 78 / 72 / 58 / 82 | **65 (57) / 41 (49) / 60 / 71** | baisse, surtout liée au modèle (§3.1) |
| Lighthouse desktop : accueil / fiche | 93 / 92 | **100 / 97** (boutique 99, panier 96) | hausse |
| Premier affichage observé sans bridage : accueil / fiche / boutique / panier | 2,44 / 3,09 / 2,46 / 4,65 s | **1,42 / 2,35 / 1,45 / 2,65 s** | −24 à −43 % |
| LCP fiche mobile, bridage **appliqué** (devtools) | non mesuré (4 à 5,5 s estimés) | **3,51 s** (FCP 2,96 s) | encore > 2,5 s |
| Poids / requêtes fiche produit (mobile) | 2 056 Kio / 100 | **1 411 Kio / 73** | −31 % |
| Images de la fiche produit | 1 350 Kio / 42 requêtes | **795 Kio / 19 requêtes** | −41 % ; plus aucun doublon |
| Poids accueil / boutique / panier (mobile) | 991 / 869 / 1 061 Kio | **819 / 724 / 585 Kio** | −17 % / −17 % / −45 % |
| CLS boutique mobile | **0,487** | **0** | ✅ |
| Polices (transfert) | 144 Kio | **54 Kio** | −62 % |
| JS tiers | ≈ 365 Kio | ≈ 363–367 Kio | inchangé |
| TBT mobile : accueil / fiche | 430 / 390 ms | 350–581 / 602–1 310 ms | pire sur la fiche (tâche `jquery-migrate` de 336–421 ms, pixels) |
| Commande express : appels serveur entre le clic et la confirmation | 3 appels PHP + page de remerciement | **identique** | inchangé |

### 2.2 Statut des constats du 23/09

| ID | Constat (23/09) | Statut | Preuve (25/09) |
|---|---|---|---|
| PERF-01 | Aucun cache de page | **Corrigé** | `X-LiteSpeed-Cache: hit` sur 26 réponses, 0,12–0,35 s ; commentaire `<!-- Page cached by LiteSpeed Cache 7.9.1 … -->` dans le HTML. Reste PERF-20 (`ttclid`) et PERF-21 (cache froid après purge). |
| PERF-02 | PHP très lent, OPcache, LVE | **Partiel** | Médiane PHP 3,59 → 2,55 s, max 14,9 → 6,1 s (le cache et le cron soulagent le compte). Mais le minimum reste à 1,98 s (1,90 s le 23/09) : un démarrage de WordPress coûte toujours environ 2 s. Cache objet INACTIF (relevé dans le panneau) ; OPcache et *faults* LVE non vérifiés, toujours listés comme « restant » dans le journal de déploiement §11.3. |
| PERF-03 | Commande express = 3 appels PHP en chaîne | **Inchangé** | `express-order.js` l. 372 (`add_to_cart`), l. 418 (`famma_express_nonce`), l. 427 (`checkout`), puis l. 435 redirection vers la page de remerciement (4e rendu PHP, non cachable). Au TTFB PHP mesuré : ≈ 8 s en médiane avant la redirection, ≈ 10 s jusqu'à la confirmation, 17 à 23 s au p90. S'y ajoute l'envoi synchrone de 2 e-mails dans l'appel checkout (PERF-23). |
| PERF-04 | Galerie téléchargée 2 à 3 fois | **Partiel** | Doublons supprimés : plus d'originaux 1080 px ni de versions 600 en double, `sizes="auto"` absent, zoom retiré (commit `bf4df15`). **Mais les 10 photos en 768 px (≈ 640 Kio) et 7 miniatures en 300 px sont téléchargées dès le chargement** : 19 images, 795 Kio. Même résultat dans un vrai Chrome mobile (DPR 2) : 10 requêtes 768 px lancées dans les 330 premières ms. Les photos 2 et 3 sont toujours sans `loading="lazy"`. `image-delivery-insight` : **611 Kio d'économie possible, −1,4 s de LCP**. |
| PERF-05 | CLS 0,487 sur la boutique mobile | **Corrigé** | `<aside class="famma-shop__aside" id="famma-shop-filters" hidden>` rendu par le serveur ; CLS mobile 0 et desktop 0,003. |
| PERF-06 | CSS et JS bloquants dans le `<head>` | **Partiel** | JS bloquants : 6 → 4 (`jquery`, `jquery-migrate`, 2 fichiers TranslatePress), car WooCommerce 11.1.2 diffère désormais `blockUI` et `js-cookie` sur la fiche. CSS dans le `<head>` : 12 (accueil) et 18 (fiche), contre 13 et 20. Aucune optimisation de page LiteSpeed active (pas de fichiers dans `/wp-content/litespeed/`). Économie estimée : 610 ms (accueil), 860 ms (fiche), 1 090 ms (boutique). `jquery-migrate` est la plus longue tâche propriétaire : 232 à 421 ms. |
| PERF-07 | ≈ 365 Kio de JS tiers | **Inchangé** | gtag 173 Kio + Meta 190 Kio (`fbevents` 109–112 + `signals/config` 81–82), non retardés. Ils représentent désormais **45 % du poids de l'accueil** et les plus longues tâches (fbevents 217–437 ms). Préconnexion à `connect.facebook.net` ajoutée (bien). |
| PERF-08 | zxcvbn (381 Kio) et calculateur de livraison sur le panier | **Partiel** | zxcvbn et `password-strength-meter` absents du panier (`Performance::drop_password_meter()`) ; scripts du panier 832 → 445 Kio. Le calculateur est toujours chargé : `selectWoo` (20 Kio), `select2.css`, `country-select`, `address-i18n`. |
| PERF-09 | Hero : 4 diapos chargées d'emblée, pas de `fetchpriority` | **Partiel** | Diapo 1 en `fetchpriority="high"`, diapos 2 à 4 en `loading="lazy"` + `fetchpriority="low"` (vérifié dans le HTML). **Sans effet sur le téléchargement** : les diapos sont empilées dans la fenêtre (`position:absolute; opacity:0`, `home.css` l. 274–283), donc le navigateur les considère visibles et les charge tout de suite (230 Kio, dont 110 Kio pour `tapis-pique-nique-xl-01-600x600.webp`, 62 Kio récupérables). |
| PERF-10 | Police arabe de 121 Kio | **Corrigé** | `noto-kufi-arabic-var.woff2` servi à 31 Kio (graisses 400–700) ; polices 144 → 54 Kio. `poppins-700` reste découverte tardivement (demandée à 684 ms, non préchargée). |
| PERF-11 | Emojis WordPress (s.w.org) | **Corrigé** | Aucune requête vers s.w.org ; `wp-emoji` absent du HTML ; préfetch DNS vers s.w.org retiré. |
| PERF-12 | Images hors WebP | **Corrigé** | Aucune image `.jpg` ou `.png` dans le HTML de l'accueil, de la boutique et de la catégorie ; filtre `image_editor_output_format` en place pour les futurs envois. Le produit de test `prd-1` n'apparaît plus dans la boutique. |
| PERF-13 | `robots.txt` généré par PHP (jusqu'à 15,6 s) | **Corrigé** | Servi par le cache LiteSpeed : 0,14–0,15 s (HIT), 2,58 s au 1er appel. SEO Lighthouse accueil 92 → 100 (l'audit `robots-txt` passe). |
| PERF-14 | Pas de cron système | **Corrigé** | Relevé dans le panneau : cron */5 min vers `wp-cron.php` ; `DISABLE_WP_CRON` posé par `mu-plugins/famma-cron.php` l. 26–29 quand l'environnement vaut `production`. |
| PERF-15 | Cache navigateur de 7 jours | **Inchangé** | Relevé dans le panneau : cache navigateur INACTIF. `cacheLifetimeMs = 604800000` (7 j) sur les images, polices et JS dans Lighthouse. |
| PERF-16 | CSS du thème enfant non minifiés | **Inchangé** | `unminified-css` : 15 à 19 Kio récupérables (`tokens.css` 75 % récupérable). `product.css` fait maintenant 42,5 Ko bruts dans le dépôt (30 Ko le 23/09). |
| PERF-17 | Sélecteur flottant TranslatePress en double | **Partiel** | Plus de balisage du sélecteur flottant ni de drapeaux SVG téléchargés. Mais `trp-language-switcher-v2.css` et `trp-frontend-language-switcher.js` restent **bloquants** dans le `<head>` de toutes les pages. |
| PERF-18 | HTML lourd, CSS en ligne | **Inchangé** | HTML décompressé : 115 Ko (accueil), 147 Ko (fiche), 160 Ko (boutique) ; 39 à 42 Ko de `<style>` en ligne dans le `<head>`. Transfert brotli : 20 à 26 Ko. |
| PERF-19 | Speculation Rules `conservative` | **Corrigé** | `"eagerness":"moderate"` dans le HTML ; panier, commande et compte exclus (`Performance::speculation_excludes()`). |

**Bilan** : 8 corrigés (01, 05, 10, 11, 12, 13, 14, 19), 6 partiels (02, 04, 06, 08, 09, 17), 5 inchangés (03, 07, 15, 16, 18), aucune régression de code. Les scores Lighthouse mobile reculent, mais la cause est expliquée en §3.1.

---

## 3. Scores Lighthouse (substitut à PSI)

| Page | Profil | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---:|---:|---:|---:|
| Accueil `/` | mobile | **65** (2e passage : 57) | 97 | 77 | 100 |
| Accueil `/` | desktop | **100** | 97 | 77 | 100 |
| Fiche `/produit/coupe-legumes-4-en-1/` | mobile | **41** (2e passage : 49 ; bridage appliqué : 58) | 97 | 77 | 100 |
| Fiche produit | desktop | **97** | 97 | 77 | 100 |
| Boutique `/boutique/` | mobile | **60** | 97 | 77 | 100 |
| Boutique | desktop | **99** | 92 ¹ | 77 | 100 |
| Panier `/panier/` (vide) | mobile | **71** | 96 | 77 | 66 ² |
| Panier | desktop | **96** | 96 | 77 | 66 ² |

¹ Nouvel échec `aria-allowed-attr` sur la boutique desktop (hors périmètre performance, à signaler à l'audit UX). Contraste des CTA toujours en échec partout (`color-contrast`).
² `noindex` volontaire sur le panier (`is-crawlable`) : ce n'est pas un défaut.
Bonnes pratiques à 77 partout : cookie tiers `fr` de Meta et problème associé dans la console. C'est inhérent au pixel.

### 3.1 Pourquoi les scores mobiles baissent alors que tout est plus rapide

Le mode « simulate » de Lighthouse (Lantern) **reconstruit** le LCP à partir de toutes les requêtes lancées avant le premier affichage observé, puis rejoue ce graphe à 1,6 Mbit/s.
- **Le 23/09**, le HTML mettait 2 s à arriver et le premier affichage suivait immédiatement le `DOMContentLoaded` (2,42 s puis 2,44 s). Les pixels et la galerie partaient **après** et n'entraient pas dans le calcul : LCP simulé de 3,2 s.
- **Le 25/09**, le HTML arrive en 30 à 60 ms. Tout part en même temps, avant le premier affichage observé (qui arrive 0,7 à 0,9 s après le `DOMContentLoaded`, les pixels s'exécutant sur le fil principal entre 0,65 et 0,83 s) : gtag 173 Kio, Meta 190 Kio, les 4 diapos du hero et, sur la fiche, les 10 photos en 768 px. Rejoués à 1,6 Mbit/s, ces 600 à 1 100 Kio donnent un **LCP simulé de 7,1 s (accueil) à 9,5 s (fiche)**, et le score chute.

Ce n'est donc pas le site qui est devenu plus lent : le premier affichage observé est plus rapide de 24 à 43 %. **Mais le diagnostic du modèle est juste** : sur une vraie 4G lente, ce sont désormais **les octets tiers, la galerie et le JS** qui retardent l'affichage, plus le serveur. Le passage avec bridage appliqué le confirme sur la fiche : **FCP 2,96 s, LCP 3,51 s, TBT 1 477 ms** (score 58). Les leviers sont PERF-04, PERF-07, PERF-09 et la section 7.

### 3.2 Métriques (labo)

| Page / profil | FCP | LCP | TBT | CLS | Speed Index | TTI | TTFB doc | Poids | Requêtes | Élément LCP |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|
| Accueil mobile | 2,2 s | 7,1 s ³ | 350 ms | 0 | 3,6 s | 7,7 s | 0,06 s | 819 Kio | 44 | image hero `visseuse-…-600x600.webp` |
| Fiche mobile | 3,1 s | 9,5 s ³ | 1 310 ms | 0 | 5,3 s | 9,6 s | 0,03 s | **1 411 Kio** | **73** | `h1.product_title` |
| Fiche mobile (bridage appliqué) | 3,0 s | **3,5 s** | 1 477 ms | 0,008 | 4,3 s | — | — | 1 364 Kio | 72 | `h1` |
| Boutique mobile | 2,3 s | 6,4 s ³ | 570 ms | **0** | 3,3 s | 6,5 s | 0,06 s | 724 Kio | 43 | 1re vignette produit |
| Panier mobile | 2,1 s | 3,9 s | 465 ms | 0 | 5,0 s | 5,9 s | **2,05 s** | 585 Kio | 46 | `p.famma-cart-empty__sub` |
| Accueil desktop | 0,5 s | 0,7 s | 9 ms | 0,001 | 0,6 s | 1,6 s | 0,04 s | 884 Kio | 54 | image hero |
| Fiche desktop | 0,8 s | 1,0 s | 58 ms | 0,001 | 1,2 s | 2,1 s | 0,08 s | 1 141 Kio | 75 | `<p>` de la description |
| Boutique desktop | 0,7 s | 0,7 s | 0 ms | 0,003 | 0,8 s | 0,7 s | 0,05 s | 773 Kio | 53 | 1re vignette |
| Panier desktop | 0,7 s | 0,7 s | 3 ms | 0 | 2,0 s | 1,3 s | **2,17 s** | 585 Kio | 46 | `h1.famma-tunnel-head__title` |

³ LCP simulé gonflé par le modèle, voir §3.1.

**Premier affichage observé sans bridage** (FCP = LCP sur les 4 pages) : accueil **1 423 ms** (2 436 le 23/09), fiche **2 354 ms** (3 090), boutique **1 445 ms** (2 464), panier **2 649 ms** (4 651).

**Décomposition du LCP de l'accueil mobile** : TTFB 228 ms, délai de chargement 39 ms, chargement 225 ms, **délai de rendu 930 ms (65 %)**. Le 23/09, le TTFB représentait 86 % du LCP ; c'est maintenant le délai de rendu (CSS et JS bloquants, pixels, mise en page forcée par Kadence, voir PERF-24) qui domine.

### 3.3 Répartition du poids (mobile, transfert)

| Page | Scripts | Images | Polices | CSS | HTML | dont tiers |
|---|---:|---:|---:|---:|---:|---:|
| Accueil | 425 Kio (19) | 251 Kio (6) | 54 Kio (4) | 66 Kio (12) | 20 Kio | 367 Kio |
| Fiche | 453 Kio (27) | **795 Kio (19)** | 54 Kio (4) | 81 Kio (18) | 26 Kio | 364 Kio |
| Boutique | 421 Kio (18) | 160 Kio (6) | 54 Kio (4) | 66 Kio (12) | 23 Kio | 363 Kio |
| Panier | 445 Kio (24) | 0 | 54 Kio (4) | 69 Kio (14) | 16 Kio | 363 Kio |

JS propriétaire : 139 à 172 Kio. **Les tiers représentent 45 % (accueil) à 62 % (panier) du poids total.**

### 3.4 Tiers (pixels)

| Tiers | Transfert | Temps thread principal (fiche mobile) | Remarque |
|---|---:|---:|---|
| Meta Pixel (`fbevents.js` + `signals/config`) | **≈ 190 Kio** | 631 + 355 ms ; tâches longues de 193 à 437 ms | Cache navigateur de 20 min (non maîtrisable) ; 34–37 % inutilisé ; préconnexion en place |
| Google tag via Site Kit (`GT-WRCTBPR9`) | **173 Kio** | 445 ms ; tâches longues de 188 à 229 ms | 41 % inutilisé ; non retardé |
| TikTok Pixel | — | — | Toujours absent. À budgéter avant activation (§8) |
| s.w.org | 0 | 0 | ✅ retiré |

---

## 4. TTFB (curl, depuis la Tunisie)

Réseau sain : connexion TCP de 0,03 à 0,14 s, TLS terminé en ≈ 0,1 s. Mesures du 25/09 entre 17 h 51 et 17 h 59, brutes dans `scratchpad/perf25/ttfb.txt`.

| Ressource | n | Min | Médiane | p90 | Max | Cache (`X-LiteSpeed-Cache`) |
|---|---:|---:|---:|---:|---:|---|
| Accueil, URL nue | 5 | 0,16 s | **0,20 s** | 0,29 s | 0,35 s | hit ×5 |
| Fiche produit, URL nue | 5 | 0,15 s | **0,18 s** | 1,61 s | 2,54 s | **miss** (froid) puis hit ×4 |
| Boutique | 5 | 0,12 s | **0,18 s** | 1,52 s | 2,41 s | **miss** puis hit ×4 |
| Catégorie `/categorie/cuisine/` | 5 | 0,14 s | **0,16 s** | 1,59 s | 2,55 s | **miss** puis hit ×4 |
| Panier | 5 | 2,15 s | **2,33 s** | 4,84 s | 5,97 s | aucun (`no-cache, private` : correct) |
| Accueil `?nocache=<unique>` (cache froid) | 5 | 1,98 s | 2,56 s | 6,05 s | 6,09 s | miss ×5 |
| Fiche `?nocache=<unique>` (cache froid) | 5 | 2,15 s | 2,39 s | 4,98 s | 5,34 s | miss ×5 |
| Fiche `?fbclid=<unique>` | 3 | 0,13 s | **0,15 s** | — | 0,23 s | **hit ×3** ✅ |
| Fiche `?ttclid=<unique>` | 3 | 2,88 s | **3,04 s** | — | 5,49 s | **miss ×3** ❌ |
| Accueil `?fbclid=test` ×2 | 2 | 0,14 s | 0,15 s | — | 0,15 s | hit, hit |
| Accueil `?ttclid=test` ×2 | 2 | 0,14 s | — | — | 2,20 s | miss puis hit : une entrée de cache distincte par valeur |
| Accueil `?utm_source=…&utm_campaign=<unique>` | 1 | — | 0,16 s | — | — | hit ✅ |
| `robots.txt` | 3 | 0,14 s | 0,15 s | — | 2,58 s | miss puis hit ×2 |
| `/?wc-ajax=get_refreshed_fragments` | 3 | 1,98 s | 3,55 s | — | 4,73 s | aucun (`no-store` : correct) |
| **Toutes les réponses HIT** | 26 | 0,12 s | **0,16 s** | 0,20 s | 0,35 s | |
| **Toutes les réponses PHP** (MISS et non cachables) | 26 | 1,98 s | **2,55 s** | 5,73 s | 6,09 s | p75 4,21 s ; 57 % > 2,5 s ; 26 % > 4 s |

**Cache chaud et froid.** Les pages en cache répondent en 0,12 à 0,35 s. Une page froide coûte 2 à 6 s de PHP. À 17 h 52, soit 20 minutes après le dernier « Tout purger » du déploiement (journal §13), **la fiche produit, la boutique et la catégorie étaient encore froides** : le premier visiteur a payé 2,4 à 2,55 s (PERF-21).

**En-têtes HTML** : `X-LiteSpeed-Cache: hit|miss`, `Etag`, `Content-Encoding: br`, `Vary: Accept-Encoding,User-Agent`, `alt-svc: h3`. Pas de `Cache-Control` sur le HTML public (normal avec LSCache). Panier : `no-cache, must-revalidate, max-age=0, private`, bien exclu.

### 4.1 Comparaison avec les concurrents (accueil, 3 appels, moyenne)

| Site | 23/09 | 25/09 | Détail 25/09 |
|---|---:|---:|---|
| **FAMMA** | 1,9–10,8 s | **0,22 s** (médiane 0,20 s, 5 appels) | HIT |
| Mantoujna (mantoujna.net) | 0,63 s | 0,34 s | 0,30 / 0,35 / 0,36 |
| Dokani (www.dokani.tn) | 0,43 s | 0,48 s | 0,46 / 0,44 / 0,54 en direct ; 0,87 s en passant par `dokani.tn` (2 redirections) |
| Express Shop (expressshop.tn) | 1,35 s | 0,86 s | 2,17 (froid) / 0,17 / 0,25, médiane 0,25 s |

**FAMMA est passé de dernier à premier des quatre** sur le TTFB d'une page en cache. L'avantage disparaît sur tout ce qui n'est pas en cache : clic TikTok, page froide, panier, commande.

---

## 5. Constats détaillés

Sévérité : **Critique** = perte de conversions probable ; **Haute** = CWV dégradés ; **Moyenne** / **Basse**. Effort : **S** < 1 h, **M** = ½ à 1 jour, **L** > 1 jour. Qui : **Code** (famma-core / thème enfant), **Admin** (réglage wp-admin), **Toi** (propriétaire), **Hébergeur**.

### 5.1 Constats du 23/09 encore ouverts (reste à faire)

| ID | Sévérité | Reste à faire | Qui | Effort |
|---|---|---|---|---|
| **PERF-02** | **Critique** | Plancher de ≈ 2 s par démarrage de WordPress. DirectAdmin : ① PHP Selector, PHP 8.3 + **OPcache** (`memory_consumption=128`, `max_accelerated_files=10000`, `revalidate_freq=60`) ; ② Resource Usage, *faults* CPU / EP / PMEM / IO ; ③ demander à OXA Host **Redis ou LSMCD**, puis activer le cache objet de LSCache (INACTIF aujourd'hui) ; ④ taille de l'autoload `wp_options` inférieure à 800 Ko. Seule piste pour le panier, le checkout et la commande express. | Hébergeur / Toi | S à M |
| **PERF-03** | **Critique** | Fusionner `add_to_cart`, le nonce et `checkout` en **un seul endpoint `wc-ajax`** dans famma-core (ajout au panier et `process_checkout` dans la même requête, avec vérification du nonce de session conservée). On passe de 4 démarrages de WordPress (3 AJAX + page de remerciement) à 2, soit ≈ −5 s en médiane. Test de bout en bout obligatoire (règle §11). | Code | M |
| **PERF-04** | **Haute** | Les doublons sont réglés ; **reste le chargement d'emblée des 10 photos en 768 px et des 7 miniatures en 300 px** (795 Kio, −611 Kio possibles, −1,4 s de LCP). ① `loading="lazy"` sur les photos 2 et 3 (encore en `eager`) ; ② FlexSlider place les diapos dans un conteneur que le lazy loading natif ne sait pas différer : remplacer la galerie par un carrousel CSS `scroll-snap` (défilement horizontal natif, où `loading="lazy"` fonctionne), ou n'injecter `src`/`srcset` des photos 2 à 10 qu'au premier geste ; ③ miniatures : réduire `data-thumb-srcset` au 100 et 150 px (le 300 px est choisi à cause du DPR). Contrôle : moins de 250 Kio d'images au chargement de la fiche. | Code | M |
| **PERF-06** | **Haute** | Activer l'optimisation de page LiteSpeed (section 7 : CSS/JS Minify, JS Deferred) ; retirer `jquery-migrate` (tâche de 232 à 421 ms) via `wp_default_scripts` après test du parcours complet ; décharger sur la fiche `photoswipe*.css/js` si la lightbox ne sert pas, `comments.min.css`, `related-posts.min.css`, `kadence-splide.min.css` et `comment-reply.js` ; `brands.css` partout si les marques ne servent pas. | Admin + Code | M |
| **PERF-07** | **Haute** | Garder le Pixel Meta tôt, mais **retarder gtag / Site Kit** (LSCache « JS Delayed Includes », section 7). Gain attendu : −173 Kio avant le premier affichage, −300 à 450 ms de thread principal. À terme, `PageView` / `ViewContent` / `AddToCart` aussi en CAPI avec `eventID`. | Admin | S |
| **PERF-08** | Moyenne | Décocher WooCommerce → Réglages → Livraison → « Activer le calculateur de livraison sur la page panier » (livraison affichée à 0 TND) : −`selectWoo`, `select2.css`, `country-select`, `address-i18n`. | Admin | S |
| **PERF-09** | Moyenne | `loading="lazy"` ne sert à rien sur des diapos empilées dans la fenêtre. Dans `hero.js` / `inc/home.php` : n'écrire `src` (en `data-src`) que pour la diapo 1, et charger la suivante juste avant sa rotation (ou après l'événement `load`). Recompresser `tapis-pique-nique-xl-01` (110 Kio, objectif ≤ 60 Kio). Gain : −193 Kio avant le premier affichage. | Code + Toi | S |
| **PERF-10** | Basse | Précharger `poppins-700` (demandée à 684 ms, utilisée au-dessus de la ligne de flottaison) ou fusionner 600 et 700. | Code | S |
| **PERF-15** | Basse | LSCache → Cache → Browser : **ON, TTL 31557600**. | Admin | S |
| **PERF-16** | Basse | CSS Minify (section 7). `product.css` est passé à 42,5 Ko bruts : à surveiller. | Admin | S |
| **PERF-17** | Basse | Le sélecteur flottant est parti, mais ses CSS et JS restent bloquants. Les décharger depuis le thème enfant si le lien `عربي` de l'en-tête ne les utilise pas, ou les passer en `defer`. À tester en FR et en AR. | Code | S |
| **PERF-18** | Basse | HTML Minify (section 7). | Admin | S |

### 5.2 Nouveaux constats

| ID | Sévérité | Constat et preuve chiffrée | Impact estimé | Correctif précis | Qui | Effort |
|---|---|---|---|---|---|---|
| **PERF-20** | **Haute** | **`ttclid` n'est pas dans le Drop Query String.** Relevé dans le panneau : liste = `fbclid`, `gclid`, `utm*`, `_ga`. Mesure : fiche `?ttclid=<unique>` = **MISS ×3, 2,88 à 5,49 s** (médiane 3,04 s), contre **HIT à 0,15 s** avec `?fbclid=`. Chaque clic TikTok porte un `ttclid` unique, donc crée une nouvelle entrée de cache qui ne resservira jamais. | Toutes les visites TikTok à 3 s de TTFB au lieu de 0,15 s, et un cache pollué d'une entrée par clic. | LiteSpeed Cache → Cache → **Drop Query String** : ajouter une valeur par ligne : `ttclid` `gbraid` `wbraid` `_gl` `srsltid` `igshid` `mc_cid` `mc_eid`. Contrôle : `curl -sI "https://fammaminnou.tn/?ttclid=test$RANDOM"` doit renvoyer `x-litespeed-cache: hit`. Sans risque pour le tracking : les paramètres restent lus côté navigateur. | Admin | S |
| **PERF-21** | **Haute** | **Cache froid après chaque « Tout purger ».** Le journal de déploiement indique 4 purges complètes le 25/09 (§11 à §13). 20 min après la dernière, 3 pages publiques sur 4 étaient encore en MISS (2,41 à 2,55 s). Avec un trafic encore faible, **le visiteur venu d'une pub est souvent celui qui réchauffe la page**, au prix de 2 à 6 s. | Le gain de PERF-01 perdu sur la première visite de chaque page après chaque mise en production. | ① Remplacer « Tout purger » par des purges ciblées (LSCache → Boîte à outils → Purger par URL, ou laisser les règles de purge automatique après une mise à jour de contenu) ; ② après une purge complète, **réchauffer** les pages d'atterrissage : accueil, boutique, 5 catégories et fiches en campagne. Soit par le Crawler LSCache si OXA l'autorise, soit par un script curl d'environ 15 URL espacées de 5 s (sous le seuil d'Imunify360). | Toi / Admin | S |
| **PERF-22** | Moyenne | **Nonce de la newsletter figé dans l'accueil mis en cache.** `<input … name="famma_nl_nonce" value="b2ea5089a0">` dans la page servie « Page cached by LiteSpeed Cache 7.9.1 on 2026-09-25 17:14:27 ». Un nonce WordPress vit 12 à 24 h ; le TTL de page recommandé est de 7 jours. Passé ce délai, `Newsletter::handle()` (l. 164–166) refuse les inscriptions. Risque déjà noté dans `DEPLOIEMENT-2026-09-23.md` §7.3 avant l'activation du cache. | Inscriptions newsletter refusées en silence au-delà d'environ 24 h de cache. | Côté famma-core : récupérer le nonce en AJAX au focus du champ (même principe que `Express_Order::send_nonce()`), ou, pour les visiteurs anonymes, s'appuyer sur le pot de miel et la limite de débit déjà présents. À défaut : LSCache « Default Front Page TTL » ≤ 43200. Vérifier que le formulaire n'est pas aussi imprimé dans le pied de page d'autres pages. | Code | S |
| **PERF-23** | Moyenne | **Les e-mails de commande partent en synchrone dans l'appel checkout.** `Order_Statuses::send_new_order_emails()` (`famma-core.php` l. 328–363) est accroché à la transition `pending → pending-confirm`, qui a lieu pendant `process_payment()`. Les e-mails « Nouvelle commande » (admin) et « Commande reçue » (client) sont donc envoyés **avant** la réponse au 3e appel de la commande express. Non mesuré : aucune commande passée. | Plusieurs centaines de ms à quelques secondes de `mail()`/SMTP ajoutées à l'étape la plus critique du tunnel. Limite OXA de 40 e-mails par jour. | Mettre l'envoi en file : `as_enqueue_async_action( 'famma_send_new_order_emails', [ $order_id ] )`, exécutée par le cron serveur (au plus 5 min de délai, acceptable pour une commande COD à confirmer). Mesurer d'abord la durée du checkout avec Query Monitor en staging. À faire avec PERF-03. | Code | M |
| **PERF-24** | Basse | **Mise en page forcée par un script en ligne de Kadence** en tête de `<body>` : `document.documentElement.style.setProperty('--scrollbar-offset', window.innerWidth - document.documentElement.clientWidth …)`. `forced-reflow-insight` : **176 ms (accueil) et 201 ms (fiche)** de reflow synchrone en CPU ×4, avant que le reste du HTML soit analysé. La variable n'est utilisée que par `famma-child/assets/css/layout.css`. | −150 à 200 ms de délai de rendu sur mobile. | Depuis le thème enfant : retirer l'action Kadence qui imprime ce script (le thème parent n'est pas dans le dépôt, hook à identifier) et remplacer l'usage dans `layout.css` par `scrollbar-gutter: stable` ou une valeur calculée après `load`. | Code | S |

**Points vérifiés sans défaut** : panier, commande et `wc-ajax` bien exclus du cache (`no-cache, private` / `no-store`) ; `utm_*` et `fbclid` ignorés par le cache ; aucune page de challenge Imunify360 sur 52 requêtes espacées ; aucun nonce figé sur la fiche produit (le nonce express est toujours récupéré en AJAX, `express-order.js` l. 411–418) ; images LCP en `fetchpriority="high"` sur l'accueil et la fiche ; aucune image JPEG ou PNG sur les pages publiques ; `font-display: swap` validé (`font-display-insight` passe).

---

## 6. Quick wins (par ordre de rendement)

1. **Ajouter `ttclid` et les autres identifiants de clic au Drop Query String** (PERF-20). Cinq minutes ; TikTok passe de 3 s à 0,15 s de TTFB.
2. **Purges ciblées et réchauffage** des pages d'atterrissage après chaque déploiement (PERF-21).
3. **Retarder gtag / Site Kit** et **activer JS Deferred, CSS/JS/HTML Minify, cache navigateur 1 an** dans LSCache, un réglage à la fois, en testant à chaque fois ajout au panier → commande express → checkout COD (PERF-06, 07, 15, 16, 18, section 7).
4. **OPcache, PHP 8.3, *faults* LVE, Redis ou LSMCD** avec OXA Host (PERF-02). Seule action qui accélère le panier et la commande.
5. **Hero** : ne charger que la diapo 1 (PERF-09), −193 Kio.
6. **Galerie** : ne charger que la photo 1 au départ (PERF-04), −611 Kio sur la page d'atterrissage des pubs.
7. **Nonce newsletter** récupéré en AJAX (PERF-22), avant que le cache ne casse le formulaire.
8. **Décocher le calculateur de livraison** du panier (PERF-08).

---

## 7. Réglages LiteSpeed Cache restants

État relevé dans le panneau le 25/09 comparé aux recommandations du 23/09 (§6 de l'audit de référence). Seul ce qui reste à faire ou à vérifier figure ici.

| Onglet | Réglage | État relevé | Recommandé | Raison |
|---|---|---|---|---|
| Cache → Cache | **Drop Query String** | `fbclid` `gclid` `utm*` `_ga` | **ajouter** `ttclid` `gbraid` `wbraid` `_gl` `srsltid` `igshid` `mc_cid` `mc_eid` | PERF-20 |
| Cache → Cache | Cache Logged-in Users / Commenters (« cache privé ») | ACTIF | **OFF** sauf besoin précis | Clients invités ; le cache privé par utilisateur consomme du disque sur un compte mutualisé partagé avec comin.tn. À vérifier : c'est peut-être ce qui apparaît comme « cache privé actif » |
| Cache → TTL | Default Front Page TTL | non relevé | 604800 **une fois PERF-22 corrigé** ; en attendant, **43200** | Nonce newsletter imprimé dans l'accueil |
| Cache → TTL | HTTP Status Code Page TTL | non relevé | `403 3600`, `404 3600`, jamais 500 | |
| Cache → Purge | Pratique de purge | « Tout purger » à chaque déploiement | Purge par URL ; règles automatiques : Front page, Home, Pages, Term archive, Post type archive | PERF-21 |
| Cache → Excludes | Do Not Cache URIs | non relevé (mesure : panier bien exclu) | `/panier/` `/commande/` `/mon-compte/` et leurs équivalents `/ar/…` | Double sécurité, WooCommerce pose déjà `DONOTCACHEPAGE` |
| Cache → WooCommerce | Vary for Mini Cart | non relevé | **ON** tant que le compteur de l'en-tête est rendu en PHP | Sinon un panier rempli affiche « 0 » sur les pages en cache. À tester : ajouter un produit, puis ouvrir une catégorie déjà en cache |
| Cache → Object | Object Cache | INACTIF | ON **si** OXA fournit Redis ou LSMCD | PERF-02 |
| Cache → Browser | Browser Cache | INACTIF | **ON, TTL 31557600** | PERF-15 ; les ressources sont versionnées (`?ver=`) |
| Crawler | Crawler | non relevé | ON si l'hébergeur l'autorise, limité au sitemap produits et catégories | PERF-21 |
| Page Optimization → CSS | CSS Minify | inactif (aucun fichier `/wp-content/litespeed/css/`) | **ON** | PERF-16 |
| Page Optimization → CSS | CSS Combine | inactif | à tester, OFF si le rendu change | |
| Page Optimization → CSS | UCSS / CCSS | QUIC.cloud non activé | OFF, sauf si le propriétaire accepte QUIC.cloud | Plus gros gain restant sur le rendu bloquant (PERF-06) |
| Page Optimization → JS | JS Minify | inactif | **ON** | |
| Page Optimization → JS | JS Combine | inactif | **OFF** | Risque avec WooCommerce et `express-order.js` |
| Page Optimization → JS | Load JS Deferred | inactif | **Deferred** (pas « Delayed ») | PERF-06 ; ne pas retarder le Pixel Meta |
| Page Optimization → Tuning | JS Delayed Includes | — | `googletagmanager.com/gtag/js` `google_gtagjs-js-after` `googlesitekit-events-provider` | PERF-07 |
| Page Optimization → Tuning | JS Deferred/Delayed Excludes | — | `connect.facebook.net` `famma-pixels` ; ajouter `express-order` et `jquery` si le formulaire express casse | |
| Page Optimization → HTML | HTML Minify | inactif | ON | PERF-18 |
| Page Optimization → HTML | Remove WordPress Emoji | — | inutile (déjà fait par famma-core, `Performance::hooks()`) | Éviter les doublons de réglage |
| Page Optimization → Media | Lazy Load Images | — | OFF (sinon exclure `famma-hero2__slide` et `wp-post-image`) | Ne pas rendre lazy l'image LCP |
| Image Optimization | QUIC.cloud | 0 % | facultatif, décision propriétaire | Les nouveaux envois sont déjà convertis en WebP (PERF-12) |

**Contrôle après chaque réglage** : `curl -sI https://fammaminnou.tn/?ttclid=test$RANDOM` doit renvoyer `hit` ; `/panier/` doit renvoyer `no-cache` ; parcours COD complet avec `WP_DEBUG` et `debug.log` propre (règle §11) ; puis Lighthouse mobile sur l'accueil, une fiche et la boutique.

---

## 8. Budget de performance (site COD, mobile, 4G tunisienne)

| Indicateur | Budget | 23/09 | 25/09 |
|---|---|---|---|
| TTFB HTML en cache (p75) | **≤ 0,3 s** | 3,2 s / 5,4 s (médianes accueil / fiche) | ✅ **0,16 s** médiane, 0,20 s p90 (HIT) |
| TTFB page publique froide ou clic TikTok | ≤ 0,8 s | — | ❌ 2,4–3 s (PERF-20, PERF-21) |
| TTFB PHP non cachable (panier, checkout, `wc-ajax`) | **≤ 0,8 s** | 1,7 à 16 s | ❌ 2 à 6 s (médiane 2,55 s) |
| Clic « Commander » (express) → confirmation | **≤ 3 s** | ≈ 6 à 20 s | ❌ ≈ 8 à 10 s en médiane, 17 à 23 s au p90 (estimation statique) |
| LCP (p75) | **≤ 2,5 s** | 3,2 s / 3,6 s simulés | ❌ fiche 3,5 s avec bridage appliqué ; ✅ 1,4–2,4 s observés sans bridage |
| FCP | ≤ 1,8 s | 1,8 s / 2,3 s | ❌ 2,2 s / 3,1 s simulés |
| INP (terrain) / TBT (labo) | ≤ 200 ms / ≤ 200 ms | — / 430 et 390 ms | ❌ 350–581 / 602–1 310 ms |
| CLS | ≤ 0,1 | boutique 0,487 | ✅ 0 sur les 4 pages |
| Poids première visite : accueil / fiche / boutique / panier | ≤ 700 / 900 / 700 / 500 Kio | 991 / 2 056 / 869 / 1 061 | 819 / **1 411** / 724 / 585 : aucun dans le budget, tous en progrès |
| Requêtes | ≤ 50 | 54 / 100 | 44 / **73** / 43 / 46 |
| JS total (compressé) | ≤ 300 Kio, dont propriétaire ≤ 80 Kio | 429 / 455 Kio | 421–453 Kio, dont 139–172 Kio propriétaire |
| JS tiers | ≤ 220 Kio avec TikTok | ≈ 365 Kio sans TikTok | ≈ 365 Kio sans TikTok |
| CSS | ≤ 60 Kio, ≤ 6 fichiers bloquants | 67–77 Kio, 13–20 fichiers | 66–81 Kio, 12–18 fichiers |
| Image LCP | ≤ 60 Kio | 37 Kio ✅ | 37 Kio ✅ |
| Images au-dessus de la ligne de flottaison | ≤ 150 Kio | ≈ 230 / 218 Kio | ≈ 230 Kio (hero) / ≈ 800 Kio chargés d'emblée (fiche) |
| Polices | ≤ 100 Kio, ≤ 2 préchargées | 144 Kio | ✅ 54 Kio, 2 préchargées |
| Origines tierces | ≤ 4 | 5 | ✅ 4 (connect.facebook.net, facebook.com, googletagmanager, google-analytics) |

---

## 9. Poids des ressources du thème dans le dépôt (pour information)

`wp-content/themes/famma-child/assets/` au 25/09 : `fonts/` 8 fichiers, 76 Ko (Noto Kufi 31 288 octets, 4 Poppins d'environ 7,8 Ko chacune) ; `css/` 12 fichiers, 209 Ko, **aucun minifié** (`product.css` 42,5 Ko, `info-pages.css` 25,5 Ko, `layout.css` 23,7 Ko, `home.css` 19,6 Ko, `shop.css` 18,5 Ko) ; `js/` 10 fichiers, 31 Ko ; `img/` 5 fichiers, 31 Ko. Le CSS a gagné 81 Ko bruts depuis le 23/09 (refonte de la fiche, du tunnel et des pages d'information) : à compenser par la minification (section 7).

---

## 10. Fichiers produits pendant l'audit

Dans `scratchpad/perf25/` :
- `ttfb.sh`, `ttfb.txt` (52 mesures curl brutes avec `X-LiteSpeed-Cache`), `stats.py` (médiane, p90, max, détection des pages de challenge), `comp.txt` (concurrents)
- `bodies/*.html`, `hdrs/*.hdr` (corps et en-têtes de chaque réponse)
- `lh.sh`, `lh2.sh`, `lh.log`, `lh2.log`, `lh/lh-*.json` (11 rapports Lighthouse 13.5.0), `lh/lh-home-mobile-2-0.trace.json` (trace de performance de l'accueil)
- `psi/home-mobile.json` (réponse 429 de l'API PSI)
- Référence du 23/09 : `scratchpad/psi/lh-*.json`
