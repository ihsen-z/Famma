# Benchmark concurrentiel — FAMMA (fammaminnou.tn)

**Date de collecte :** 23 septembre 2026
**Périmètre :** FAMMA + 7 boutiques tunisiennes de même nature + 2 références « grandes » (hors catégorie)
**Méthode :** lecture seule. Pages récupérées en HTTP (curl / Python) et, pour les boutiques rendues en JavaScript (Converty, TikTak PRO), dans un navigateur réel en vue mobile 375 px. Recherches de visibilité faites avec l'outil WebSearch. Aucun formulaire soumis, aucune commande passée.
**Règle :** tout ce qui suit a été observé sur l'URL citée. Si une information n'a pas pu être trouvée, c'est écrit « non observé ».

> **Limites à connaître**
> - **PageSpeed : non mesuré.** Le quota anonyme de l'API Google PageSpeed était épuisé. À la place, j'ai mesuré le temps de première réponse du serveur (TTFB) avec curl : moyenne de 3 appels, depuis le poste de l'audit. C'est un indicateur, pas un score Lighthouse.
> - **WebSearch n'est pas Google Tunisie.** C'est un moteur centré sur les États-Unis. Le tableau de visibilité donne une tendance, pas un classement Google.tn.
> - **Taille des catalogues :** comptée par l'API publique WooCommerce (en-tête `X-WP-Total`), par le compteur affiché, ou par les liens produits présents sur la page. La méthode est indiquée à chaque fois.

---

## 1. Profil de FAMMA

| Élément | Observé | Source |
|---|---|---|
| Plateforme | WordPress 7.1.2 + WooCommerce 11.0.1 + Site Kit, serveur LiteSpeed | meta `generator`, en-têtes — https://fammaminnou.tn |
| Title / meta de l'accueil | « FAMMA MINNOU — Boutique en ligne, paiement à la livraison » / « Boutique en ligne tunisienne : vous commandez, vous payez en espèces à la livraison… Livraison offerte… » | https://fammaminnou.tn |
| Promesse | « Il y a toujours quelque chose de nouveau » / « فمّا ديما حاجة جديدة ». Produits « pratiques et utiles », COD, livraison offerte, commande sans compte | accueil, https://fammaminnou.tn/a-propos/ |
| Catalogue | **16 fiches** via l'API Store : 14 produits réels distincts, **1 produit de test visible (« Prd 1 », 75 DT barré 140 DT, sans catégorie)** et **1 doublon (« Pince de Câblage Pro » listée deux fois)** | https://fammaminnou.tn/wp-json/wc/store/v1/products |
| Catégories | 10 dans le menu, dont **4 vides** (Maison & Cuisine, Électronique, Auto & Moto, Beauté & Santé : 0 produit chacune) | filtres de https://fammaminnou.tn/boutique/ |
| Prix | 25 à 110 DT (Pince de câblage 25 DT → Pilates Pro 110 DT). Aucun prix barré, sauf sur le produit de test | API Store |
| Positionnement réel | Gadgets et produits malins, multi-univers : outils (visseuse 21V 79 DT), cuisine (coupe-légumes 39 DT, boîtes à épices magnétiques 49 DT), enfants, plein air, bien-être | boutique |
| Livraison | 0 TND partout, 24 gouvernorats, incluse dans le prix. **Aucun délai affiché** (ni fiche, ni page Livraison, ni FAQ) | https://fammaminnou.tn/livraison/ |
| Paiement | COD uniquement (« en espèces, au livreur ») | FAQ |
| Commande express | Oui, formulaire sur la fiche produit : prénom, nom, téléphone, gouvernorat, ville, adresse, quantité + case CGV obligatoire (7 champs), total « à payer à la livraison » | fiche visseuse |
| WhatsApp | Oui : bouton flottant, blocs « Écrire sur WhatsApp », numéro affiché **56261412** | footer, contact |
| Langues | FR + arabe/Derja, version `/ar/` en `dir="rtl"`, hreflang fr/ar/x-default. **Traduction partielle :** en version AR, le menu, les catégories et les titres produits restent en français ; en version FR, les descriptions produits sont en Derja | https://fammaminnou.tn/ar/ |
| Avis | Module WooCommerce natif, **0 avis** sur tous les produits | API (`review_count` = 0) |
| Retours | **Page non rédigée, avec la note interne visible :** « Points à trancher : délai de rétractation accordé… ». Pourtant le bandeau du haut promet « Satisfait ou remboursé » | https://fammaminnou.tn/retours/ |
| CGV / mentions légales | **Page non rédigée :** « Informations légales à fournir : Raison sociale… matricule fiscal… ». La case CGV est pourtant obligatoire pour commander | https://fammaminnou.tn/conditions-generales/ |
| Signaux de confiance | Ville (Sousse), téléphone/WhatsApp, e-mail sur le domaine (contact@fammaminnou.tn). Adresse précise, MF et RNE non observés | footer |
| Réseaux sociaux | Facebook seulement (facebook.com/famma.shop.tn). Instagram et TikTok : aucun lien | HTML de l'accueil |
| Tracking | Pixel Meta + GA4 (gtag). Pixel TikTok non détecté dans le HTML | HTML |
| SEO technique | Schema `Product` + `Offer` + `BreadcrumbList` + `Organization/OnlineStore` ; canonical ; og:image ; sitemap WP ; robots.txt qui autorise explicitement les robots IA (GPTBot, ClaudeBot, PerplexityBot…). **Manquent :** `aggregateRating`, `shippingDetails`, `hasMerchantReturnPolicy`, `brand`. Titles produits d'environ 105 caractères (tronqués dans Google) | fiche visseuse, /robots.txt |
| Blog | Aucun (le sitemap ne contient que pages, produits et catégories) | https://fammaminnou.tn/wp-sitemap.xml |
| Vitesse serveur (TTFB) | **Accueil 1,9 à 10,8 s selon l'appel ; fiche produit 5,0 à 6,4 s ; même robots.txt 3,6 à 4,4 s.** Aucun en-tête de cache de page | curl, 3 appels |
| Mobile (impression) | Bonne base : barre fixe « Commander / أطلب توا », WhatsApp flottant, cibles larges. Mais **le prix n'apparaît pas dans le premier écran** de la fiche (fil d'Ariane, grande image et deux rangées de miniatures le repoussent) | capture 375 px, fiche visseuse |
| Incohérences visibles | Bloc d'accueil « Les premiers produits arrivent / استنّاو الجديد » alors que 16 produits sont en ligne ; liens Promotions/Nouveautés du footer en `http://` | accueil |

---

## 2. Concurrents retenus et justification

### Concurrents directs (même nature : gadgets / maison / cuisine, COD, ciblage social, site propre)

| # | Boutique | URL | Pourquoi elle est pertinente |
|---|---|---|---|
| 1 | **Dokani** | https://www.dokani.tn/ | Même ville (**Sousse**, Kalaa Sghira), COD, maison/cuisine/électroménager. Apparaît sur « gadget cuisine Tunisie » et « boîte à épices ». C'est le concurrent local le plus structuré |
| 2 | **Mantoujna (منتوجنا)** | https://mantoujna.net/ | « Gadgets pratiques, cuisine, automobile, téléphonie » : c'est exactement le créneau de FAMMA. Nom en Derja, forte présence sociale (FB, IG, TikTok) |
| 3 | **Forca** | https://forca.tn/ | « Gadgets pratiques, maison, jardin, cuisine, auto », interface arabe, descriptions en Derja. Vend un coupe-légumes 5-en-1 et une visseuse, des produits proches de ceux de FAMMA |
| 4 | **Tunisia Store** | https://tunisia-store.tn/ | « Accessoires, gadgets, high-tech », univers Maison & Cuisine, Auto, Enfants. Commande express, orienté social ads |
| 5 | **Express Shop** | https://expressshop.tn/ | Maison, cuisine, « Gadgets pour tous les jours », « Tout à 10 DT ». Apparaît sur « coupe-légumes 4 en 1 ». Taille moyenne |
| 6 | **Cheapshop** | https://cheapshop.tn/ | Bricolage, maison, auto, camping. Apparaît sur « visseuse 21V prix Tunisie », donc concurrent frontal sur la catégorie Outils de FAMMA |
| 7 | **Pratiko** | https://pratiko.tn/ | « Gadgets pratiques maison, cuisine, quotidien ». Apparaît sur « livraison gratuite… gadgets pratiques ». Représente les micro-boutiques de dropshipping, avec **1 seul produit visible** |

### Références « grandes » (hors catégorie, étalon)

| Boutique | URL | Rôle |
|---|---|---|
| **Tunisianet** | https://www.tunisianet.com.tn/ | Gros e-commerçant généraliste high-tech, avec des magasins physiques. Premier résultat sur « visseuse 21V prix Tunisie » |
| **Wamia** | https://www.wamia.tn/ | Marketplace généraliste avec application. Apparaît sur « boîte à épices » et « coupe-légumes 4 en 1 » |

> **Pourquoi pas Jumia :** https://www.jumia.com.tn renvoie **HTTP 410 Gone** (constaté en curl et dans le navigateur). Jumia a annoncé le 16 octobre 2024 cesser ses activités en Tunisie ([ilboursa](https://www.ilboursa.com/marches/jumia-annonce-la-cessation-de-ses-activites-en-tunisie_48745), [webdo](https://www.webdo.tn/fr/actualite/national/jumia-se-retire-du-marche-tunisien-fin-d-une-decennie-de-presence/217010/)). Cela libère de la visibilité sur les requêtes produits (voir §4).

### Écartés après visite

| Site | Raison |
|---|---|
| hajetna.converty.shop | Créneau identique, mais sur un sous-domaine Converty : pas de site propre |
| oneaday.tn | Shopify, bien exécuté, mais papeterie et fournitures scolaires : hors créneau |
| trendtn.shop | Mode |
| shopiwell-ecom.tn | Bien-être (2 120 produits) : autre créneau |
| tunisieshopping.tn | Maison + vêtements, 115 produits : moins pertinent que les 7 retenus |
| petitsprix.tn | Blocage Cloudflare, non audité |
| premia.tn, promosells.tn, famma-tn.shop | Non joignables lors de la visite |
| famma.tn | **Homonyme** : vêtements de seconde main, COD, plateforme Bagisto, « Mestika consulting ». Pas un concurrent produit, mais un risque de confusion de marque (voir §4) |

---

## 3. Matrice comparative

Légende : ✅ présent · ⚠️ partiel ou problème · ❌ absent · n.o. = non observé.
Tunisianet et Wamia sont des étalons hors catégorie.

### 3a. Offre, prix, logistique, conversion

| Critère | **FAMMA** | Dokani | Mantoujna | Forca | Tunisia Store | Express Shop | Cheapshop | Pratiko | Tunisianet* | Wamia* |
|---|---|---|---|---|---|---|---|---|---|---|
| Plateforme | WooCommerce | Odoo | Converty | Converty | TikTak PRO | WooCommerce (WPBakery) | WooCommerce (Elementor) | TikTak PRO | PrestaShop | Magento (motif d'URL `catalog/category/view`, probable) |
| Catalogue visible | 14 réels (+1 test, +1 doublon) | 1 155 articles (compteur) | ≥ 14 sur l'accueil (bouton « عرض المزيد ») | 10 (catégorie « tous ») | 16 sur l'accueil (total n.o.) | 63 (API Woo) | 1 219 (API Woo) | 1 | milliers (non compté) | « des milliers » (meta) |
| Fourchette de prix | 25–110 DT | 3,90–949 DT (filtre) | 29–179 DT | 16–99 DT | 18,9–439 DT | 8–959 DT | 4–860 DT | 27,92 DT | ex. perceuse 21V 205 DT | ex. mandoline 14 DT |
| Prix barrés / promos | ❌ (choix « prix clair ») | ✅ | ✅ −29 à −63 % partout | ✅ −19 à −44 % partout | ✅ partout | ✅ | ✅ | ✅ −20 % | n.o. | ✅ |
| Frais de livraison | **0 DT, sans seuil** | 8 DT, offerte dès 199 DT, retrait magasin gratuit | 7 DT | 7 DT | n.o. (page vide) | 6 DT, offerte dès 250 DT | 7 DT forfait | 7 DT | n.o. | 8 DT |
| Délai affiché | ❌ n.o. | ✅ 24–72 h, express < 24 h si commande avant 20 h | ✅ 2–3 jours | n.o. | n.o. | ✅ 48–72 h (24–48 h sur « À propos ») | ✅ traitement 24 h + livraison 24 h | n.o. | n.o. | ✅ 1–4 jours ouvrables |
| Paiement | COD seul | COD + carte | COD | COD | n.o. | COD + carte (**−5 % par carte**) | COD (carte « bientôt ») | n.o. (formulaire COD) | carte + **3 à 36 mois** | COD + en ligne |
| Commande express sur la fiche | ✅ 7 champs + CGV | ⚠️ « Acheter maintenant » puis tunnel classique | ✅ 3 champs | ✅ 3 champs | ✅ 3 champs + compte à rebours | ✅ 3 champs, visibles dès le 1er écran mobile | ⚠️ bouton « Acheter - شراء » (tunnel n.o.) | ✅ 4 champs dont gouvernorat | ❌ panier classique | ❌ panier classique |
| Offres par quantité | ❌ | ❌ | ✅ 1 = 89 / 2 = 159 / 3 = 219 DT | ❌ | ❌ | ⚠️ rubrique « Packs » | ⚠️ « Packs en promo » | ❌ | ❌ | ❌ |
| WhatsApp | ✅ flottant + contact | ⚠️ mentionné, pas de lien wa.me vu | ✅ wa.me | ✅ wa.me | ❌ n.o. | ❌ | ❌ | ⚠️ lien cassé (`wa.me/#`) | ❌ | ⚠️ mentionné |
| Langues | FR + AR/Derja, RTL, **hreflang** (traduction partielle) | FR | Interface AR, titres FR, descriptions AR | AR (RTL) + Derja | FR, descriptions FR + AR | FR (quelques titres AR) | FR + restes EN (« Add to wishlist », « Quick view ») + bouton bilingue | FR, descriptions Derja | FR | FR (+ appli) |

### 3b. Confiance, contenu, référencement, technique

| Critère | **FAMMA** | Dokani | Mantoujna | Forca | Tunisia Store | Express Shop | Cheapshop | Pratiko | Tunisianet* | Wamia* |
|---|---|---|---|---|---|---|---|---|---|---|
| Avis clients affichés | ❌ 0 avis | ⚠️ module présent, 0 avis sur la fiche vue | ❌ n.o. | ⚠️ onglet « التقييم » (contenu n.o.) | ⚠️ « 4.9 (27 avis) » **identique sur les 16 produits de l'accueil** | ✅ vrais avis nommés, note 4,8/5 et histogramme ; 57 produits sur 63 ont des avis ; page /reviews/ | ⚠️ 1 produit avec avis parmi les 100 moins chers | ⚠️ « 27 avis vérifiés », même valeur que Tunisia Store (même plateforme) | n.o. | ⚠️ module présent, 0 sur la fiche vue |
| Politique retour / échange | ❌ **page non rédigée** alors que « Satisfait ou remboursé » est affiché | ✅ 14 jours, satisfait ou remboursé ; enlèvement à domicile | ✅ retour 3 j / échange 7 j, **−30 % (min. 25 DT)** sans défaut | ✅ texte identique à Mantoujna (modèle Converty) | ⚠️ page présente (contenu n.o.) | ⚠️ échange si défaut seulement, pas de remboursement si changement d'avis | ⚠️ seulement si le colis est endommagé, à signaler le jour même | n.o. | n.o. | ✅ 30 jours |
| Argument « ouvrir le colis avant de payer » | ❌ | ✅ sur chaque fiche | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Adresse / MF / RNE | Ville seulement ; MF et RNE ❌ (CGV non rédigées) | ✅ adresse complète Sousse + magasin ; MF n.o. | ❌ (Gmail) | ❌ (Outlook) | ❌ (Gmail) | ⚠️ 3 téléphones, e-mail sur le domaine, tickets | ⚠️ CGV complètes, téléphone | ❌ | ✅ magasins | ✅ téléphone + horaires |
| Chiffres de clientèle affichés | ❌ | ❌ | « +50 000 clients » | ❌ | ❌ | « +15 000 clients » | ❌ | ❌ | n.o. | n.o. |
| Blog / contenu | ❌ | ✅ blog recettes et conseils ; fiches très longues + FAQ | ❌ | ❌ | ❌ | ✅ lien Blog | ❌ | ❌ | n.o. | n.o. |
| Réseaux sociaux (liens sur le site) | FB | FB, IG, YouTube | FB, IG, **TikTok** | FB | FB, IG | FB, IG, YouTube | FB | ⚠️ liens cassés (`#`) | FB, IG, LinkedIn, Pinterest, YouTube, X | FB, IG, TikTok, LinkedIn, YouTube + appli |
| Title de l'accueil | ✅ explicite (COD) | ✅ « Electroménager & Cuisine en Tunisie \| Livraison 24h » | ⚠️ « منتوجنا - Mantoujna » | ✅ mots-clés gadgets | ⚠️ « Tunisia Store » | ✅ | ❌ « Accueil - Cheapshop » + meta en liste de mots-clés | ✅ | ✅ | ✅ |
| Schema Product sur la fiche | ✅ (sans avis, livraison ni retours) | ✅✅ Product + Offer + **OfferShippingDetails + ShippingDeliveryTime + FAQPage** | ✅ | ✅ | ✅ | ❌ (WebPage/Breadcrumb seulement) | ✅ | ✅ | ✅ microdonnées | n.o. (pas de JSON-LD dans le HTML) |
| hreflang FR/AR | ✅ **seule boutique** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Robots IA autorisés explicitement | ✅ | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. |
| TTFB moyen (curl, 3 appels) | ❌ **1,9–10,8 s** (accueil), 5–6 s (fiche) | 0,43 s | 0,63 s | 0,40 s | 0,53 s | 1,35 s | 0,24 s | 0,40 s | 0,31 s | 0,28 s |
| PageSpeed mobile | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré | non mesuré |
| Mobile (impression) | Bonne : CTA fixe bilingue + WhatsApp ; prix sous le 1er écran | ⚠️ image non chargée après 3 s, logo absent de l'en-tête mobile, « 3 vendu ces dernières 24 heures » | ✅ prix barré + barre fixe « Total 96 DT » au 1er écran | ✅ simple, RTL | ✅ | ✅✅ titre, note, prix et formulaire dès le 1er écran | n.o. | ✅ | n.o. | n.o. |
| Textes de modèle oubliés | ⚠️ retours, CGV, « premiers produits arrivent », produit test | ⚠️ « Votre snippet dynamique s'affichera ici… » (accueil) ; « Pour avoir du succès, votre contenu doit être utile… » (politiques) | ❌ | ❌ | ⚠️ page « Frais de livraison » vide | ❌ | ⚠️ chaînes EN | ⚠️ liens sociaux `#` | ❌ | ❌ |

**Sources principales (fiches examinées) :**
- FAMMA https://fammaminnou.tn/produit/visseuse-perceuse-sans-fil-21v/
- Dokani https://www.dokani.tn/shop/gh-325-mini-glaciere-thermoelectrique-chaud-froid-75l-9405, https://www.dokani.tn/politique-expedition, https://www.dokani.tn/politique-retours, https://www.dokani.tn/politique-paiement
- Mantoujna https://mantoujna.net/product/appareil-de-massage-chauffant-electrique-pour-les-articulations, https://mantoujna.net/page/refund
- Forca https://forca.tn/product/qtaa-khdr-5-fy-1, https://forca.tn/page/refund
- Tunisia Store https://tunisia-store.tn/product/1066374/ecouteurs-sans-fil-airpod-a9-pro-avec-ecran-tactile
- Express Shop https://expressshop.tn/produit/set-de-24-pots-a-epices-en-verre-avec-couvercles-bambou/, https://expressshop.tn/livraison-et-echange/, https://expressshop.tn/politique-dechange/, https://expressshop.tn/about/
- Cheapshop https://cheapshop.tn/produit/visseuse-dyllu-12v/, https://cheapshop.tn/conditions-generales-de-vente/, https://cheapshop.tn/politique-dechange-et-de-retour/
- Pratiko https://pratiko.tn/product/1086925/mini-aspirateur-sans-fil-rechargeable
- Tunisianet https://www.tunisianet.com.tn/perceuse/30467-perceuse-a-main-sans-fil-21v.html
- Wamia https://www.wamia.tn/mandoline-coupe-legumes.html

### 3c. Repères de prix sur des produits comparables

Les produits ne sont pas strictement identiques. Le « total payé » ajoute les frais de livraison observés.

| Produit FAMMA | Prix FAMMA (total payé) | Concurrent le plus proche observé | Total payé chez le concurrent |
|---|---|---|---|
| Coupe-légumes 4-en-1 | 39 DT | Forca, coupe-légumes **5-en-1** 28 DT + 7 DT | 35 DT |
| Visseuse perceuse 21V (2 batteries, coffret) | 79 DT | Forca, visseuse électrique 64 + 7 = 71 DT · Cheapshop, visseuse Dyllu **12V** 79 + 7 = 86 DT · Tunisianet, perceuse 21V 205 DT TTC | 71 à 205 DT |
| Boîtes à épices magnétiques | 49 DT | Express Shop, set de 24 pots en verre (autre produit) 89 DT | 95 DT |

Conclusion : grâce à la livraison incluse, le **total payé** chez FAMMA est compétitif. Le **prix facial** est parfois plus élevé que celui des boutiques Converty, qui ajoutent 7 DT au dernier moment.

---

## 4. Visibilité sur des requêtes commerciales (WebSearch, 23/09/2026)

| # | Requête (langue) | Sites qui apparaissent | FAMMA |
|---|---|---|---|
| 1 | « gadget cuisine Tunisie » (FR) | guirat.tn, avantica.tn, **dokani.tn**, casa-square.com, chillandlit.tn, **wamia.tn** | ❌ absente |
| 2 | « livraison gratuite Tunisie paiement à la livraison gadgets pratiques » (FR) | mytek.tn, tunisiatech.tn, spacenet.tn, oneaday.tn, **pratiko.tn**, web6.tn (blog), **dokani.tn**, transporti.tn, orgaflam.com | ❌ |
| 3 | « boîte à épices magnétique prix Tunisie » (produit FAMMA) | amazon.fr, **wamia.tn**, lafoirfouille.fr, geantdrive.tn, ordex.tn, flytinbottle.com, mumuso.co.ma, lastpricetunisie.tn, **dokani.tn**, guirat.tn | ❌ |
| 4 | « visseuse perceuse sans fil 21V prix Tunisie » (produit FAMMA) | **tunisianet.com.tn**, arkan.tn, technoquip-tn.com, mytek.tn, lastpricetunisie.tn, carrefour.tn, meakida.tn, **cheapshop.tn**, rs-online.com | ❌ |
| 5 | « متجر الكتروني تونس الدفع عند الاستلام توصيل مجاني منتجات جديدة » (AR) | ubuy.tn, converty.shop, universalnet.tn, twenty.tn, mrketly.com, joshop.site, midanalmal.com | ❌ |

Requêtes complémentaires :

| Requête | Sites qui apparaissent | FAMMA |
|---|---|---|
| « coupe légumes 4 en 1 prix Tunisie livraison » | **wamia.tn**, unitech.com.tn, premia.tn, tunisieshopping.tn, gopatisserie.tn, shopiwell-ecom.tn, petitsprix.tn, promosells.tn, **expressshop.tn** | ❌ |
| « produits tendance Tunisie boutique en ligne nouveautés gadgets » | technopro-online.com, hajetna.converty.shop, **wamia.tn**, proxity.tn (blog), **mantoujna.net**, **forca.tn**, zen.com.tn, drest.tn, **tunisia-store.tn**, trendtn.shop | ❌ |
| « tapis de dessin à l'eau enfant prix Tunisie » (produit FAMMA) | uniquement des sites étrangers (Rakuten, Fnac, Cdiscount, Zeeman…) et une page Jumia Tunisie fermée | ❌, mais **aucun concurrent tunisien non plus : opportunité** |
| « توصيل مجاني الدفع عند الاستلام تونس أدوات مطبخ » (AR) | vidéos Facebook et TikTok, ubuy.tn, all.biz | ❌ (le social domine en arabe) |
| « FAMMA MINNOU boutique Tunisie » (marque) | fama-me.com, famma-dhaw.com, **famma.tn** (homonyme), **famma-tn.shop** (homonyme), zen.com.tn… | ❌ **même sur sa propre marque** |
| « fammaminnou.tn » (domaine) | aucun résultat pertinent | ❌ |

**Lecture :**
- FAMMA n'apparaît sur **aucune** requête testée, pas même sur son nom de marque ou son domaine. Le site est jeune et ce moteur n'est pas Google.tn, mais le signal est net.
- Deux homonymes (famma.tn, famma-tn.shop) occupent la requête de marque. Il faut associer systématiquement « FAMMA MINNOU / فمّا مينّو ».
- Les concurrents qui ressortent le plus : **Dokani** et **Wamia** (fiches longues, catalogue profond), et les boutiques Converty (**Mantoujna**, **Forca**) sur les requêtes « gadgets / tendance ».
- Sur les requêtes en arabe, les vidéos TikTok et Facebook dominent : le référencement naturel arabe pèse moins que la présence sociale.

---

## 5. Forces et faiblesses de FAMMA

### En avance
1. **Livraison 0 DT sans seuil.** Tous les concurrents facturent 6 à 8 DT, ou offrent la livraison seulement au-dessus de 199–250 DT. Le prix affiché est le prix payé.
2. **Bilinguisme FR / Derja structuré.** C'est la seule boutique avec une version arabe RTL indexable et des hreflang corrects. Les autres sont soit FR, soit AR.
3. **Préparation SEO et GEO.** Robots IA explicitement autorisés, schema Product et Organization, canonical, Open Graph. C'est au-dessus de la moyenne des boutiques Converty et TikTak.
4. **Honnêteté.** Pas de prix barrés gonflés, pas de faux compteurs d'urgence, pas d'avis de modèle. Chez les concurrents on voit des remises permanentes de −20 à −63 %, « باقي 23 جهاز فقط », « 8 personnes consultent ceci », des comptes à rebours, et la même note « 4.9 (27 avis) » sur tous les produits (Tunisia Store, Pratiko).
5. **Parcours COD mobile clair.** CTA fixe « Commander / أطلب توا », WhatsApp flottant, total « à payer à la livraison » explicite.
6. **E-mail sur le domaine et téléphone joignable**, contre Gmail ou Outlook chez Mantoujna, Forca et Tunisia Store.

### À égalité
- Commande express sans compte (standard du marché).
- Schema Product présent (la plupart l'ont, sauf Express Shop).
- Descriptions produits en Derja orientées bénéfices (comme Pratiko et Forca).

### En retard
1. **Vitesse serveur : c'est le problème n°1.** TTFB de 1,9 à 10,8 s sur l'accueil et 5 à 6 s sur les fiches, contre 0,24 à 1,35 s chez tous les concurrents. Sur du trafic payé Facebook/TikTok, chaque seconde fait perdre des clics.
2. **Pages légales et retours non rédigées, avec les notes internes visibles.** Le bandeau « Satisfait ou remboursé » contredit la page Retours. La case CGV est obligatoire alors que les CGV sont vides. C'est un risque de confiance et un risque juridique.
3. **Produit de test « Prd 1 » et doublon « Pince de Câblage Pro » en ligne.** Cela contrevient aussi à la règle projet « aucune donnée fictive ».
4. **Catalogue mince** : 14 produits réels, 4 catégories vides dans le menu. Contre 10 à 1 219 chez les autres. Pour une promesse « ديما حاجة جديدة », c'est le point faible.
5. **Aucun délai de livraison affiché**, alors que tous les concurrents sérieux le chiffrent (24–72 h, « < 24 h avant 20 h »).
6. **0 avis client.** Aucun mécanisme de preuve sociale.
7. **Invisibilité dans les recherches**, y compris sur la marque, et aucun contenu éditorial (blog, guides).
8. **Traduction incomplète** : menu, catégories et titres produits en français sur la version arabe ; description Derja sur la version FR.
9. **Réseaux sociaux** : Facebook seulement, pas de lien Instagram ni TikTok, pas de pixel TikTok détecté. C'est pourtant le canal dominant du créneau.
10. **Formulaire express plus long** (7 champs + CGV) que chez Mantoujna, Forca et Express Shop (3 champs). Et sur mobile, le prix est sous le premier écran.

---

## 6. Dix bonnes pratiques à reprendre (avec exemple)

| # | Pratique observée | Exemple (URL) | Application chez FAMMA |
|---|---|---|---|
| 1 | **Formulaire express réduit et visible dès le 1er écran mobile** : titre, note, prix, puis Nom / Téléphone / Adresse, puis bouton | https://expressshop.tn/produit/set-de-24-pots-a-epices-en-verre-avec-couvercles-bambou/ | Remonter le prix et le formulaire au-dessus des miniatures. Regrouper prénom et nom. Déduire la ville ou le gouvernorat en une seule liste. Viser 4 champs |
| 2 | **Délai chiffré et heure limite** : « Livraison expresse en moins de 24h si vous commandez avant 20h » | https://www.dokani.tn/shop/gh-325-mini-glaciere-thermoelectrique-chaud-froid-75l-9405 | Afficher un délai réel (ex. « 24–72 h ») sur chaque fiche, dans la FAQ et sur la page Livraison, réglable depuis l'admin |
| 3 | **« Vous pouvez ouvrir et contrôler votre colis avant de payer »** | même fiche Dokani | C'est le levier de confiance COD le plus fort observé. À adopter si le transporteur le permet |
| 4 | **Politique de retour publiée et chiffrée** (14 jours, enlèvement à domicile) | https://www.dokani.tn/politique-retours | Rédiger et publier la page Retours avant toute campagne. Aligner le bandeau « Satisfait ou remboursé » sur le texte réel |
| 5 | **Offres par quantité** : 1 = 89 DT, 2 = 159 DT (« le plus demandé »), 3 = 219 DT | https://mantoujna.net/product/appareil-de-massage-chauffant-electrique-pour-les-articulations | Remise de lot sur les produits cadeaux ou famille. FAMMA paie 7 DT de livraison par commande : augmenter le panier moyen protège directement la marge |
| 6 | **Avis réels nommés, avec note et histogramme**, plus une page « Avis clients » | https://expressshop.tn/reviews/ | Après chaque statut « livré », demander un avis par WhatsApp. N'afficher que de vrais avis (règle projet) |
| 7 | **Schema Product enrichi** : `OfferShippingDetails`, `ShippingDeliveryTime`, `FAQPage` | fiche Dokani ci-dessus (JSON-LD) | Ajouter `shippingDetails` (0 TND, délai), `hasMerchantReturnPolicy`, `brand`, puis `aggregateRating` dès qu'il y a de vrais avis |
| 8 | **Questions rapides en Derja sous le formulaire** : « ينفع للكرهبة؟ البطارية تدوم قدّاش؟ » | https://pratiko.tn/product/1086925/mini-aspirateur-sans-fil-rechargeable | Mini-FAQ produit bilingue, paramétrable par produit. Réduit les allers-retours WhatsApp et nourrit le référencement longue traîne |
| 9 | **Blog de conseils et de recettes** relié aux catégories | https://www.dokani.tn/blog | Guides courts FR + Derja (« 5 gadgets pour gagner du temps en cuisine », « bien choisir une visseuse »). Seule façon de ranker sans catalogue profond |
| 10 | **Paiement en ligne optionnel avec remise** : « Payer par carte de crédit −5 % » | fiche Express Shop ci-dessus | À moyen terme : une option carte, D17 ou e-dinar avec petite remise réduit les refus de colis COD. À garder optionnel |

Deux autres pratiques utiles : lien TikTok et Instagram dans l'en-tête et le footer (https://mantoujna.net/) ; adresse physique complète et retrait en boutique (https://www.dokani.tn/politique-expedition).

---

## 7. Recommandations de positionnement

### 7.1 Positionnement proposé : « la nouveauté utile, au prix final, sans mauvaise surprise »

Le marché des gadgets COD en Tunisie joue sur trois leviers : **urgence** (comptes à rebours, stocks « limités »), **remises permanentes** (−30 à −60 %), **frais ajoutés** au dernier moment (+7 DT). FAMMA peut prendre la place opposée, et la tenir :

1. **« Le prix affiché, c'est le prix payé. »** 0 DT de livraison, sans seuil, sans frais cachés. C'est l'avantage le plus net et le plus rare observé. À marteler dans le hero, les créatives publicitaires et les titles SEO, sans citer de concurrent.
2. **« De vrais produits, de vrais avis. »** Pas de prix barrés fictifs, pas de faux compteurs. En faire un engagement de marque visible, puis le prouver avec les avis collectés après livraison.
3. **« ديما حاجة جديدة » comme rythme, pas seulement comme slogan.** Une « nouveauté de la semaine » datée, une page Nouveautés vivante, un catalogue qui tourne. Cela suppose d'**étoffer le catalogue** (objectif réaliste : 40 à 60 références) et de **masquer les catégories vides**.
4. **La seule boutique vraiment bilingue.** Finir la traduction (menu, catégories, titres produits en arabe ; descriptions FR sur la version FR) pour posséder les requêtes en français **et** en arabe, ce qu'aucun concurrent ne fait.

### 7.2 Priorités (ordre conseillé)

| Priorité | Action | Pourquoi |
|---|---|---|
| P0 (avant toute pub) | Retirer « Prd 1 » et le doublon, masquer les catégories vides, supprimer le bloc « Les premiers produits arrivent » | Données fictives visibles, crédibilité |
| P0 | Rédiger et publier Retours et CGV avec les vraies informations légales (raison sociale, MF, RNE) | Confiance, conformité, cohérence avec « Satisfait ou remboursé » |
| P0 | Régler le TTFB : cache de page (le serveur est LiteSpeed, donc LSCache est possible si l'hébergeur l'autorise), sinon revoir l'hébergement | 5 à 10 fois plus lent que tous les concurrents |
| P1 | Afficher un délai de livraison chiffré ; si possible « ouvrir le colis avant de payer » | Standard du marché, levier COD |
| P1 | Raccourcir le formulaire express et remonter prix + CTA au premier écran mobile | Conversion sur trafic social |
| P1 | Liens Instagram et TikTok, pixel TikTok | Canal dominant du créneau |
| P2 | Collecte d'avis après livraison + schema enrichi (livraison, retours, avis) | Preuve sociale et résultats enrichis |
| P2 | Offres par quantité sur les produits cadeaux ou famille | Panier moyen et marge (7 DT de livraison internes) |
| P2 | 1 à 2 guides par mois (FR + Derja) sur les produits phares ; cibler d'abord les requêtes sans concurrent tunisien (ex. « tapis dessin à l'eau enfant Tunisie ») | Visibilité organique |
| P3 | Toujours accoler « FAMMA MINNOU / فمّا مينّو » (title, Organization, réseaux) | Se distinguer des homonymes famma.tn et famma-tn.shop |

### 7.3 Face aux concurrents clés

- **Dokani (Sousse)** : plus fort en profondeur de catalogue (1 155 articles), en marques d'électroménager et en confiance (adresse, magasin, retours 14 jours, schema riche). Ne pas l'attaquer sur l'électroménager de marque. Se différencier sur **la nouveauté et le gadget malin**, la **livraison toujours offerte** (Dokani : 8 DT sous 199 DT) et la **Derja**.
- **Mantoujna, Forca (Converty)** : mêmes produits, même public, plus présents sur les réseaux sociaux. FAMMA gagne sur le **prix final**, un **domaine et un e-mail professionnels**, et l'**absence de pression artificielle**. Elle perd sur le **volume de catalogue** et les **offres par quantité**.
- **Express Shop** : c'est la référence d'exécution à imiter (formulaire au premier écran, vrais avis, page avis, remise carte).
- **Tunisianet, Wamia** : ils captent les requêtes génériques. FAMMA ne gagnera pas ces requêtes ; elle doit viser la **longue traîne produit bilingue** et le **social**.

---

*Données collectées le 23/09/2026. Prix, catalogues et pages peuvent évoluer. PageSpeed non mesuré (quota API épuisé). TTFB mesuré par curl, moyenne de 3 appels.*
