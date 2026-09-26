# Benchmark concurrentiel — FAMMA MINNOU (fammaminnou.tn), mise à jour

**Date de collecte :** 25 septembre 2026 (17 h 50 – 18 h 10, heure de Tunis)
**Référence :** `docs/audit-2026-09-23/audit-concurrence.md` (collecte du 23/09/2026, note « position concurrentielle » 5/10)
**Périmètre :** le même panel. FAMMA, 7 boutiques de même nature (Dokani, Mantoujna, Forca, Tunisia Store, Express Shop, Cheapshop, Pratiko) et 2 étalons (Tunisianet, Wamia). Jumia reste écarté (HTTP 410 le 23/09).
**Méthode :** lecture seule, en ligne uniquement.
- Pages récupérées en HTTP (curl / Python). Les boutiques rendues en JavaScript (Converty, TikTak PRO) et la fiche FAMMA ont été ouvertes dans un navigateur réel, en vue mobile 375 × 812.
- Données catalogue lues via les API publiques : WooCommerce Store API (FAMMA, Cheapshop, Express Shop) et JSON embarqué (Converty).
- Visibilité testée avec l'outil WebSearch.
- Aucun formulaire soumis, aucune commande, aucun compte créé.

**Règle :** chaque cellule marquée 25/09 a été observée sur l'URL citée. Une cellule concurrent marquée « (23/09) » reprend la valeur du 23/09 sans nouvelle vérification. « idem » signifie que la valeur a été revérifiée et n'a pas changé.

> **Limites à connaître**
> - **TTFB :** même protocole que le 23/09. `curl -w '%{time_starttransfer}'` sur l'accueil, avec un User-Agent Chrome, depuis le poste de l'audit. Concurrents : 3 appels espacés, moyenne. FAMMA : 5 appels espacés de 8 s, médiane, min et max. C'est un indicateur serveur, pas un score Lighthouse. PageSpeed n'a pas été mesuré.
> - **Anti-bot Imunify360 :** les requêtes vers fammaminnou.tn ont été espacées de 4 à 8 s, soit environ 20 pages en 20 minutes. Aucune « page vide 200 » n'a été détectée : les corps font 83 à 135 Ko décompressés (342 Ko pour l'API) et contiennent le `<title>` attendu. Aucune mesure n'a donc été écartée.
> - **WebSearch n'est pas Google.tn.** C'est un moteur centré sur les États-Unis. Le tableau de visibilité donne une tendance, pas un classement.

---

## 0. En bref

| | 23/09 | 25/09 |
|---|---|---|
| Note « position concurrentielle » | **5,0 / 10** | **7,4 / 10** (méthode au §8) |
| TTFB accueil FAMMA | 1,9–10,8 s (médiane 3,2 s), **dernier du panel** | **0,17 s** (0,13–0,24 s), **premier du panel** (page servie par le cache) |
| Délai de livraison affiché | aucun | **24 h à 48 h**, sur tout le site |
| Données fictives visibles | produit test « Prd 1 » (−46 %), doublon, 4 catégories vides | aucune |
| Retours / CGV | pages non rédigées, notes internes visibles, bandeau « Satisfait ou remboursé » en contradiction | pages propres et publiées, mais toujours « en cours de rédaction » (aucun délai de retour, ni MF, ni RNE) |
| Avis, réseaux, visibilité | 0 avis ; Facebook seul ; invisible | **inchangé** : 0 avis ; Facebook seul ; 0 requête sur 8 |

Les quatre signaux rouges du 23/09 sont levés : vitesse, délai, données fictives, pages légales contradictoires. Les écarts qui restent sont des écarts de preuve et de présence : avis, réseaux, référencement, profondeur du catalogue, politique de retour chiffrée.

---

## 1. Profil de FAMMA : 23/09 et 25/09

| Élément | 23/09 | 25/09 (observé) | Évol. | Source 25/09 |
|---|---|---|---|---|
| Plateforme | WordPress + WooCommerce, LiteSpeed, aucun cache de page | Idem, avec **LiteSpeed Cache actif** : en-tête `X-LiteSpeed-Cache: hit` sur l'accueil, `/ar/` et la fiche visseuse | ↑ | en-têtes, https://fammaminnou.tn/ |
| Catalogue | 16 fiches : 14 réels + « Prd 1 » (test) + doublon « Pince de Câblage Pro » | **14 fiches, 14 produits réels distincts**, aucun prix barré | ↑ | https://fammaminnou.tn/wp-json/wc/store/v1/products?per_page=100 |
| Catégories | 10 dans le menu, dont 4 vides | **5 catégories, toutes pourvues** : Cuisine 4, Outils & Bricolage 3, Enfants 3, Maison & Plein air 2, Bien-être & Sport 2 | ↑ | API Store, menu |
| Prix | 25–110 DT | 25–110 DT (Pince de câblage 25 → Pilates Pro 110) | = | API Store |
| Livraison | 0 TND sans seuil ; **aucun délai** | 0 TND sans seuil, bandeau « LIVRAISON GRATUITE 100% » ; **« Livraison en 24 h à 48 h »** dans le bandeau, sur la fiche (bloc réassurance), sur la page Livraison (tableau des 7 zones, 4 étapes, FAQ) | ↑ | https://fammaminnou.tn/livraison/ |
| Commande express | 7 champs (prénom, nom, téléphone, gouvernorat, ville, adresse, quantité) + case CGV | **6 champs** : nom complet, téléphone, gouvernorat, ville/délégation **(facultatif)**, adresse, quantité. **4 obligatoires** + case CGV. Libellés bilingues, total « à payer à la livraison » | ↑ partiel | fiche visseuse |
| Mobile, premier écran (375 × 812) | prix repoussé sous le premier écran | Titre visible. **Prix visible** dans la barre fixe (« 79,000 DT », WhatsApp, « Commander / أطلب توا »). Le prix de la fiche est à y ≈ 725 px, en partie sous la barre. **Le formulaire commence à y ≈ 1 210 px**, soit environ 1,5 écran | ↑ partiel | navigateur, fiche visseuse |
| WhatsApp | bouton flottant et blocs, numéro 56261412 | Liens **`wa.me/21626767491`**, au format international, dans le bouton flottant, la fiche, le pied de page, les pages Livraison et Retours et le formulaire Retours. Le téléphone 56261412 reste affiché à part (`tel:+21656261412`) | ↑ | HTML accueil, fiche, /livraison/ |
| Langues | FR + AR RTL + hreflang. Sur /ar/, menu, catégories et titres produits restaient en français | Sur /ar/, **menu et catégories traduits** (« الحانوت », « الكوجينة », « العدّة والبريكولاج »…), ainsi que le titre de la page et les titres de section. **Titres produits encore en français** sur /ar/ | ↑ partiel | https://fammaminnou.tn/ar/ |
| Avis | 0 | **0** (`review_count` = 0 sur les 14 produits). Le bloc « Ils nous font confiance » **n'apparaît pas** sur l'accueil | = | API Store, accueil |
| Retours | page non rédigée, note interne visible, contredite par le bandeau « Satisfait ou remboursé » | Page publiée : procédure en 3 étapes, formulaire qui prépare un message WhatsApp (motif, remboursement ou échange), FAQ, horaires 9 h–18 h. Mais la page dit **« Notre politique de retour et de remboursement est en cours de rédaction »** : aucun délai, aucune condition chiffrée. Le bandeau « Satisfait ou remboursé » a disparu de l'accueil | ↑ partiel | https://fammaminnou.tn/retours/ |
| CGV | page non rédigée, liste interne « Raison sociale… matricule fiscal… » visible | Page datée du 25/09 : 5 rubriques courtes (prix, commande, livraison, paiement, contact), avec la mention **« conditions générales de vente complètes en cours de rédaction »**. **Raison sociale, MF et RNE toujours absents.** La case CGV reste obligatoire | ↑ partiel | https://fammaminnou.tn/conditions-generales/ |
| Nouveaux blocs d'accueil | — | Newsletter (« فمّا ديما حاجة جديدة… », champ e-mail, pot de miel anti-spam). Bloc « Pourquoi choisir FAMMA ? ». Plus de bloc « Les premiers produits arrivent ». Liens de pied de page en https | ↑ | accueil |
| Réseaux sociaux | Facebook seulement | **Facebook seulement** (`sameAs` = facebook.com/famma.shop.tn). Pas d'Instagram ni de TikTok | = | HTML, JSON-LD |
| Tracking | Pixel Meta + GA4 ; pixel TikTok absent | Idem : `fbq(`, gtag `GT-WRCTBPR9` ; aucun pixel TikTok | = | HTML accueil |
| SEO technique | Product + Offer + BreadcrumbList + OnlineStore ; manquaient `aggregateRating`, `shippingDetails`, `hasMerchantReturnPolicy`, `brand` | **Inchangé.** Le Product ne contient que `sku` et `offers`. `OnlineStore.telephone` = « 56261412 », sans +216. hreflang fr/ar/x-default et robots IA autorisés (GPTBot, OAI-SearchBot…) conservés | = | JSON-LD fiche, /robots.txt |
| Vitesse (TTFB) | accueil 1,9–10,8 s ; fiche 5,0–6,4 s | **Accueil 0,13–0,24 s** (médiane 0,17). **Fiche 0,13–0,18 s.** `/ar/` 0,16 s. Tout est servi par le cache. **Hors cache :** 2,07 s avec paramètre anti-cache ; 1,9–4,0 s de temps total au premier passage sur des pages froides | ↑ | curl, §4 |

---

## 2. Panel : changements chez les concurrents depuis le 23/09

| # | Boutique | URL vérifiées le 25/09 | Changements notables |
|---|---|---|---|
| 1 | **Dokani** | https://www.dokani.tn/, https://www.dokani.tn/shop/gh-325-mini-glaciere-thermoelectrique-chaud-froid-75l-9405, https://www.dokani.tn/politique-expedition | Frais (8 DT, offerte dès 199 DT), délai (24–72 h, express < 24 h avant 20 h), « ouvrir le colis avant de payer » et retours 14 j : **inchangés**. **Nouveau ou non relevé le 23/09 :** l'accueil affiche une preuve sociale chiffrée, « 230+ avis clients vérifiés » et « 4,9/5 … sur 280 avis clients vérifiés » (deux chiffres différents sur la même page), plus des témoignages nommés (« Leila M. — Sousse »), « 95 % livraisons dans les délais », « 1000+ produits en stock » et un menu « Ventes Flash ». La fiche vue affiche toujours « (0 avis) » et « 1 vendu ces derniers 24 heures ». Le texte de modèle « Votre snippet dynamique s'affichera ici… » est toujours visible (2 blocs) |
| 2 | **Mantoujna** | https://mantoujna.net/, https://mantoujna.net/product/appareil-de-massage-chauffant-electrique-pour-les-articulations | **Inchangé (vérifié le 25/09)** : 89 DT + 7 DT = 96 DT, « توصيل 2-3 أيام », « ضمان 7 أيام », offres 1/2/3 appareils (89/159/219 DT), 3 champs (nom, téléphone, adresse), liens FB/IG/TikTok et wa.me. Section « منتجاتنا الجديدة » avec badges « جديد » ; 2 produits en rupture sur l'accueil |
| 3 | **Forca** | https://forca.tn/, https://forca.tn/product/qtaa-khdr-5-fy-1 | **Inchangé (vérifié le 25/09)** : 10 produits, coupe-légumes 5-en-1 à 28 DT (barré 38) + 7 DT, visseuse (« مفك براغي كهربائي ») à 64 DT, 3 champs. Remarque : le lien WhatsApp rendu est `wa.me/26298330`, **sans indicatif 216**, donc probablement inopérant. Instagram et TikTok (@forca.tn) figurent dans la configuration de la boutique mais ne sont pas liés sur la page |
| 4 | **Tunisia Store** | https://tunisia-store.tn/, fiche https://tunisia-store.tn/product/1066374/ecouteurs-sans-fil-airpod-a9-pro-avec-ecran-tactile | **Inchangé (vérifié le 25/09)** : 59,9 DT (barré 119,9), « 27 avis vérifiés », compte à rebours « Promo limitée 00d 11h 44m », 3 champs, frais de livraison non affichés dans le formulaire |
| 5 | **Express Shop** | https://expressshop.tn/, https://expressshop.tn/produit/set-de-24-pots-a-epices-en-verre-avec-couvercles-bambou/, https://expressshop.tn/livraison-et-echange/ | **Frais de livraison passés de 6 à 8 DT.** Formulaire : « Sous-total 89.00 TND · Livraison 8.000 TND · Totale 97.00 TND » (95 DT le 23/09). Seuil de gratuité 250 DT, délai 48–72 h, 3 champs, remise carte −5 % (92,15 TND) : inchangés. Catalogue : 61 produits (API) contre 63. Accueil servi par LiteSpeed (`hit`) |
| 6 | **Cheapshop** | https://cheapshop.tn/, https://cheapshop.tn/produit/visseuse-dyllu-12v/, https://cheapshop.tn/conditions-generales-de-vente/ | **Inchangé (vérifié le 25/09)** : 7 DT forfait. CGV : « traitement dans 24h, livraison du colis de 24h à 72h ». Visseuse Dyllu 12V à 79 DT (barré 100). Chaînes anglaises (« Add to wishlist », « Quick view »), onglet « Shipping & Delivery » vide. Catalogue : 1 217 produits (API) contre 1 219 |
| 7 | **Pratiko** | https://pratiko.tn/, https://pratiko.tn/product/1086925/mini-aspirateur-sans-fil-rechargeable | **Inchangé (vérifié le 25/09)** : 1 seul produit, 27,92 DT (−20 %), « 4.9 (27 avis) », liens sociaux et WhatsApp en `#` |
| * | **Tunisianet** | https://www.tunisianet.com.tn/perceuse/30467-perceuse-a-main-sans-fil-21v.html | Perceuse 21V toujours à 205 DT TTC, désormais **« Hors stock »** |
| * | **Wamia** | https://www.wamia.tn/mandoline-coupe-legumes.html | Mandoline à 14 DT (barré 24), désormais **« En rupture de stock »**. « Livraison à 8 DT partout en Tunisie » et retour 30 jours : inchangés |

---

## 3. Matrice comparative

Légende : ✅ présent · ⚠️ partiel ou problème · ❌ absent · n.o. = non observé · **Évol.** : ↑ amélioré, = inchangé, ↓ dégradé.
Colonnes concurrents : valeurs revérifiées le 25/09 (« idem » = pas de changement) sauf « (23/09) ». Tunisianet et Wamia sont des étalons hors catégorie.

### 3a. Offre, prix, logistique, conversion

| Critère | FAMMA 23/09 | **FAMMA 25/09** | Évol. | Dokani | Mantoujna | Forca | Tunisia Store | Express Shop | Cheapshop | Pratiko | Tunisianet* | Wamia* |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Plateforme | WooCommerce, sans cache | **WooCommerce + LiteSpeed Cache** | ↑ | Odoo | Converty | Converty | TikTak PRO | WooCommerce (+ LiteSpeed) | WooCommerce | TikTak PRO | PrestaShop | Magento (probable) |
| Catalogue visible | 14 réels + 1 test + 1 doublon ; 4 catégories vides | **14 réels, 5 catégories pleines** | ↑ | « 1000+ produits » affiché (1 155 le 23/09) | ≥ 14 sur l'accueil (idem) | 10 (idem) | 16 sur l'accueil (23/09) | **61** (API ; 63) | **1 217** (API ; 1 219) | 1 (idem) | milliers | milliers |
| Fourchette de prix | 25–110 DT | 25–110 DT | = | 3,90–949 DT (23/09) | 29–179 DT (idem) | 16–99 DT (idem) | 18,9–439 DT (23/09) | 8–959 DT (23/09) | 4–860 DT (23/09) | 27,92 DT (idem) | perceuse 21V 205 DT, **hors stock** | mandoline 14 DT, **rupture** |
| Prix barrés / promos | ❌ (choix « prix clair ») ; produit test à −46 % | ❌ (choix) ; **plus aucun faux barré** | ↑ | ✅ + menu « Ventes Flash » | ✅ −29 à −63 % (idem) | ✅ −19 à −44 % (idem) | ✅ + compte à rebours (idem) | ✅ (−43 % sur la fiche) | ✅ | ✅ −20 % (idem) | n.o. | ✅ |
| Frais de livraison | 0 DT sans seuil | **0 DT sans seuil** (« LIVRAISON GRATUITE 100% ») | = | 8 DT, offerte ≥ 199 DT, retrait gratuit (idem) | 7 DT (idem) | 7 DT (idem) | n.o. (idem) | **8 DT** (6 DT le 23/09), offerte ≥ 250 DT | 7 DT forfait (idem) | 7 DT (23/09) | n.o. | 8 DT (idem) |
| Délai affiché | ❌ | ✅ **24 h à 48 h** (bandeau, fiche, page Livraison) | ↑ | ✅ 24–72 h, < 24 h si commande avant 20 h (idem) | ✅ 2–3 jours (idem) | n.o. | n.o. | ✅ 48–72 h (idem) | ✅ 24 h de traitement + 24–72 h de livraison (CGV) | n.o. | n.o. | ✅ 1–4 j ouvrables (23/09) |
| Paiement | COD seul | COD seul | = | COD + carte (23/09) | COD | COD | n.o. | COD + carte **−5 %** (idem) | COD (23/09) | COD | carte + 3 à 36 mois (23/09) | COD + en ligne |
| Commande express sur la fiche | ✅ 7 champs + CGV | ✅ **6 champs, dont 4 obligatoires**, + CGV ; formulaire à ~1,5 écran | ↑ partiel | ⚠️ tunnel classique (idem) | ✅ 3 champs (idem) | ✅ 3 champs (idem) | ✅ 3 champs + compte à rebours (idem) | ✅ 3 champs + quantité, au 1er écran (idem) | ⚠️ bouton « Acheter - شراء » (idem) | ✅ 4 champs (23/09) | ❌ | ❌ |
| Offres par quantité | ❌ | ❌ | = | ❌ | ✅ 1 = 89 / 2 = 159 / 3 = 219 DT (idem) | ❌ | ❌ | ⚠️ « Packs » | ⚠️ « Packs en promo » | ❌ | ❌ | ❌ |
| WhatsApp | ✅ flottant + contact (56261412) | ✅ **`wa.me/21626767491`** partout ; téléphone 56261412 à part | ↑ | ⚠️ pas de wa.me (idem) | ✅ wa.me/21623616215 | ⚠️ **`wa.me/26298330` sans 216** | ❌ (idem) | ❌ (idem) | ❌ (idem) | ⚠️ `wa.me/#` (idem) | ❌ | ⚠️ |
| Langues | FR + AR/Derja, RTL, hreflang ; AR partiel | FR + AR RTL + hreflang ; **menu et catégories en AR** ; titres produits en FR sur /ar/ | ↑ partiel | FR | AR, titres FR (idem) | AR (RTL) + Derja (idem) | FR + AR (idem) | FR | FR + restes EN (idem) | FR + Derja (idem) | FR | FR |

### 3b. Confiance, contenu, référencement, technique

| Critère | FAMMA 23/09 | **FAMMA 25/09** | Évol. | Dokani | Mantoujna | Forca | Tunisia Store | Express Shop | Cheapshop | Pratiko | Tunisianet* | Wamia* |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Avis clients affichés | ❌ 0 avis | ❌ **0 avis** ; bloc « Ils nous font confiance » non affiché | = | ⚠️ fiche : 0 avis ; **accueil : « 4,9/5, 280 avis » et « 230+ avis »** (chiffres discordants) + témoignages | ❌ n.o. (idem) | ⚠️ 0 dans les données | ⚠️ « 27 avis vérifiés » (idem) | ✅ vrais avis nommés, « Avis 99 % positifs » (idem) | ⚠️ « Avis (0) » sur la fiche | ⚠️ « 4.9 (27 avis) » (idem) | n.o. | ⚠️ « Soyez le premier à commenter » |
| Politique retour / échange | ❌ page non rédigée, « Satisfait ou remboursé » affiché | ⚠️ **procédure publiée** (3 étapes + formulaire WhatsApp + FAQ), mais **politique « en cours de rédaction », non chiffrée** ; promesse contradictoire retirée | ↑ partiel | ✅ 14 j, satisfait ou remboursé (idem) | ✅ « ضمان 7 أيام — استرجاع أو تبديل » (idem) | ✅ (23/09) | ⚠️ (23/09) | ⚠️ échange si défaut (23/09) | ⚠️ colis endommagé seulement (23/09) | n.o. | n.o. | ✅ 30 j (idem) |
| « Ouvrir le colis avant de payer » | ❌ | ❌ | = | ✅ sur chaque fiche (idem) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Adresse / MF / RNE | ville seulement ; CGV non rédigées | ville (Sousse) ; CGV en 5 rubriques, « complètes en cours de rédaction » ; **MF et RNE ❌** | ↑ partiel | ✅ adresse Sousse + tél. +216 52 408 804 | ❌ Gmail (idem) | ❌ Outlook (idem) | ❌ Gmail (idem) | ⚠️ 3 téléphones (idem) | ⚠️ CGV complètes (idem) | ❌ | ✅ | ✅ |
| Chiffres de clientèle affichés | ❌ | ❌ (aucun chiffre inventé) | = | **« 230+ avis », « 95 % dans les délais », « 1000+ produits »** | « +50 000 » (idem) | ❌ | ❌ | « +15 000 » (23/09) + « 99 % positifs » | ❌ | ❌ | n.o. | n.o. |
| Blog / contenu | ❌ | ❌ | = | ✅ Blog au menu | ❌ | ❌ | ❌ | ✅ lien Blog (idem) | ❌ | ❌ | n.o. | n.o. |
| Newsletter | ❌ n.o. | ✅ inscription e-mail sur l'accueil | ↑ | non relevé | non relevé | non relevé | non relevé | non relevé | non relevé | non relevé | non relevé | non relevé |
| Réseaux sociaux (liens sur le site) | FB | **FB seul** | = | FB, IG, YouTube (idem) | FB, IG, **TikTok** (idem) | FB (IG/TikTok configurés, non liés) | FB, IG (idem) | FB, IG, YouTube (idem) | FB (idem) | ⚠️ liens `#` (idem) | (23/09) | FB, IG, TikTok, YouTube (idem) |
| Title de l'accueil | ✅ explicite (COD) | ✅ idem | = | ✅ « … Livraison 24h » | ⚠️ « منتوجنا - Mantoujna » | ✅ mots-clés gadgets | ⚠️ « Tunisia Store » | ✅ | ❌ « Accueil - Cheapshop » | ✅ | ✅ | ✅ |
| Schema Product sur la fiche | ✅ sans avis, livraison ni retours | ⚠️ **Product + Offer (`sku`, `offers`) seulement**, sans `brand`, `shippingDetails`, `hasMerchantReturnPolicy`, `aggregateRating` | = | ✅✅ Product + Brand + OfferShippingDetails + ShippingDeliveryTime + FAQPage | ✅ Product + Brand + OfferShippingDetails + ShippingDeliveryTime | ✅ idem Mantoujna | ✅ Product + Brand + AggregateRating | ❌ (idem) | ✅ Product | ✅ Product + Brand + AggregateRating | ✅ microdonnées | n.o. |
| hreflang FR/AR | ✅ seule boutique | ✅ **toujours la seule** | = | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Robots IA autorisés explicitement | ✅ | ✅ | = | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. | n.o. |
| **TTFB accueil (curl)** | ❌ 1,9–10,8 s (médiane 3,2) | ✅ **0,17 s** (0,13–0,24) en cache ; 2,07 s hors cache | ↑ | 0,40 s | 0,31 s | 0,37 s | 0,31 s | 0,86 s | 0,28 s | 0,30 s | 0,35 s | 0,27 s |
| Mobile (impression) | CTA fixe bilingue + WhatsApp ; prix sous le 1er écran | CTA fixe **avec prix** + WhatsApp ; formulaire à ~1,5 écran | ↑ partiel | ⚠️ (23/09) | ✅ barre « Total 96 DT » (idem) | ✅ (idem) | ✅ (idem) | ✅✅ formulaire au 1er écran (idem) | n.o. | ✅ | n.o. | n.o. |
| Textes de modèle oubliés | ⚠️ retours, CGV, « premiers produits arrivent », produit test | ⚠️ **réduit** à 2 mentions « en cours de rédaction » (Retours, CGV) | ↑ | ⚠️ « Votre snippet dynamique s'affichera ici… » (idem) | ❌ | ❌ | ⚠️ (23/09) | ❌ | ⚠️ chaînes EN, onglet vide | ⚠️ liens `#` | ❌ | ❌ |

**Réévaluation d'un point du 23/09.** Le rapport du 23/09 situait la préparation SEO de FAMMA « au-dessus de la moyenne des boutiques Converty et TikTak ». La lecture du JSON-LD le 25/09 montre l'inverse sur la fiche produit : Converty publie par défaut `Brand` et `OfferShippingDetails` (avec `ShippingDeliveryTime`), et TikTak publie `Brand` et `AggregateRating`. FAMMA garde son avance sur le hreflang, les robots IA et le schéma `OnlineStore`. En revanche, son schéma Product est le plus pauvre du panel après Express Shop.

**Sources principales (fiches examinées le 25/09) :**
- FAMMA https://fammaminnou.tn/produit/visseuse-perceuse-sans-fil-21v/, https://fammaminnou.tn/livraison/, https://fammaminnou.tn/retours/, https://fammaminnou.tn/conditions-generales/, https://fammaminnou.tn/ar/, https://fammaminnou.tn/robots.txt
- Dokani https://www.dokani.tn/shop/gh-325-mini-glaciere-thermoelectrique-chaud-froid-75l-9405, https://www.dokani.tn/politique-expedition
- Mantoujna https://mantoujna.net/product/appareil-de-massage-chauffant-electrique-pour-les-articulations
- Forca https://forca.tn/product/qtaa-khdr-5-fy-1
- Tunisia Store https://tunisia-store.tn/product/1066374/ecouteurs-sans-fil-airpod-a9-pro-avec-ecran-tactile
- Express Shop https://expressshop.tn/produit/set-de-24-pots-a-epices-en-verre-avec-couvercles-bambou/, https://expressshop.tn/livraison-et-echange/
- Cheapshop https://cheapshop.tn/produit/visseuse-dyllu-12v/, https://cheapshop.tn/conditions-generales-de-vente/
- Pratiko https://pratiko.tn/product/1086925/mini-aspirateur-sans-fil-rechargeable
- Tunisianet https://www.tunisianet.com.tn/perceuse/30467-perceuse-a-main-sans-fil-21v.html
- Wamia https://www.wamia.tn/mandoline-coupe-legumes.html, https://www.wamia.tn/ensemble-de-4-pots-pices-magn-tiques-muraux-350397296352460.html

### 3c. Prix comparés : total payé, livraison comprise (25/09)

Les produits ne sont pas strictement identiques. Le total ajoute les frais de livraison observés. Chez FAMMA, le prix affiché est le total.

| Produit FAMMA | FAMMA (total) | Concurrents : prix + livraison = total | Lecture |
|---|---|---|---|
| **Visseuse perceuse 21V** (2 batteries, coffret) | **79 DT** | Forca, visseuse électrique (tension non précisée) : 64 + 7 = **71 DT** (idem 23/09) · Cheapshop, visseuse perceuse Dingqi 21V 2 Ah brushless : 109 + 7 = **116 DT** · Cheapshop, visseuse à percussion 21V Yovlet : 109 + 7 = **116 DT** · Cheapshop, Meakida 21V : 129 + 7 = **136 DT** · Cheapshop, Dyllu **12V** : 79 + 7 = 86 DT · Tunisianet, perceuse 21V : 205 DT, **hors stock** | Sur des visseuses 21V comparables, FAMMA est moins chère de 37 à 57 DT. Seule Forca est moins chère, sans tension affichée |
| **Boîtes à épices magnétiques** | **49 DT** | Wamia, « Ensemble de 4 pots à épices magnétiques muraux » : 49,5 (barré 65) + 8 = **57,5 DT** · Express Shop, « Lot de 12 pots à épices magnétiques en INOX » : 69 + 8 = **77 DT** · Express Shop, set de 24 pots en verre (autre produit) : 89 + 8 = 97 DT (95 DT le 23/09) · Dokani : 17 porte-épices en verre dans la catégorie, **aucun magnétique** | FAMMA est la moins chère sur le magnétique, de 8,5 à 28 DT |
| **Tapis de pique-nique XL** 200 × 145 cm | **39 DT** | **Aucun équivalent dans le panel** : Dokani, 0 résultat (« pique nique ») ; Cheapshop, 0 ; Wamia, seulement un « tapis de plage rond » (21,6 + 8 = 29,6 DT, produit plus petit et différent) | Pas de concurrent direct sur le panel |
| **Coupe-légumes 4-en-1** | **39 DT** | Forca, coupe-légumes **5-en-1** : 28 + 7 = **35 DT** (idem 23/09) · Wamia, mandoline : 14 + 8 = 22 DT, **rupture** · Express Shop : aucun résultat pour « coupe » ou « légumes » dans l'API · Dokani : 0 résultat | Forca reste 4 DT moins chère, avec une lame de plus : c'est le seul produit où FAMMA est battue sur le total |

Conclusion : l'avantage « prix final » s'est renforcé. Express Shop a relevé sa livraison à 8 DT, et sur 3 des 4 produits suivis FAMMA est la moins chère une fois la livraison comptée. Le coupe-légumes est l'exception : il est à revoir face au 5-en-1 de Forca.

---

## 4. Vitesse serveur (TTFB) : avant / après

Protocole identique au 23/09 : `curl -s -o /dev/null -w '%{time_starttransfer}'` sur l'accueil, User-Agent Chrome, `--compressed`.

| Boutique | 23/09 (moyenne de 3) | **25/09** | Détail des appels 25/09 | Rang 25/09 |
|---|---|---|---|---|
| **FAMMA** | 1,9–10,8 s (médiane 3,2) | **médiane 0,167 s** · min 0,129 · max 0,239 (5 appels) | 0,239 / 0,197 / 0,167 / 0,147 / 0,129, tous `X-LiteSpeed-Cache: hit`, corps de 115 Ko (pas de page vide) | **1ᵉʳ** |
| Wamia* | 0,28 s | 0,265 s | 0,313 / 0,255 / 0,226 | 2ᵉ |
| Cheapshop | 0,24 s | 0,284 s | 0,298 / 0,254 / 0,300 | 3ᵉ |
| Pratiko | 0,40 s | 0,300 s | 0,336 / 0,266 / 0,299 | 4ᵉ |
| Tunisia Store | 0,53 s | 0,306 s | 0,379 / 0,293 / 0,247 | 5ᵉ |
| Mantoujna | 0,63 s | 0,310 s | 0,360 / 0,299 / 0,271 | 6ᵉ |
| Tunisianet* | 0,31 s | 0,352 s | 0,349 / 0,356 / 0,351 | 7ᵉ |
| Forca | 0,40 s | 0,368 s | 0,418 / 0,389 / 0,297 | 8ᵉ |
| Dokani | 0,43 s | 0,398 s | 0,393 / 0,362 / 0,440 | 9ᵉ |
| Express Shop | 1,35 s | 0,863 s | 1,935 / 0,259 / 0,395 | 10ᵉ |

Mesures FAMMA complémentaires (25/09) :

| Page | TTFB | Cache |
|---|---|---|
| Fiche visseuse (3 appels) | 0,173 / 0,180 / 0,132 s (0,13–0,18 s ; 5,0–6,4 s le 23/09) | hit |
| `/ar/` | 0,158 s | hit |
| Accueil avec paramètre anti-cache `?audit=…` | **2,07 s** | miss |
| Premières visites de pages froides (fiche, Livraison, Retours, CGV, API, /ar/) | 1,9–4,0 s de temps **total** | miss |

**Lecture.**
- Pour un visiteur anonyme sur une page déjà en cache, FAMMA passe de dernière à **première** du panel, avec un gain d'environ 20 fois sur la médiane.
- L'origine reste lente : environ 2 s hors cache. Les pages non cachées (panier, commande, premier visiteur après une purge) et les visiteurs porteurs d'un cookie de panier continuent de subir ce délai.
- L'avantage tient tant que le cache est chaud. Un préchargement (crawler LSCache) et des purges ciblées plutôt que globales le protègent.

---

## 5. Visibilité sur des requêtes représentatives (WebSearch, 25/09/2026)

| # | Requête | Sites qui apparaissent | Concurrents du panel | FAMMA |
|---|---|---|---|---|
| 1 | « visseuse sans fil tunisie » | facebook.com (INGCO Tunisie), totaltunisia.com, arkan.tn, technoquip-tn.com, mytek.tn, egm.tn (×2), lastpricetunisie.tn, ingco.tn | aucun (Cheapshop et Tunisianet, présents le 23/09 sur une requête proche, ne sortent pas) | ❌ |
| 2 | « boîte à épices magnétique tunisie » | **wamia.tn**, amazon.fr, facebook.com (Casa Déco), geantdrive.tn (×2), mumuso.co.ma, guirat.tn, cdiscount.com, ordex.tn, **dokani.tn** | Wamia, Dokani | ❌ |
| 3 | « tapis pique-nique tunisie » | cdiscount.com, lespetitsbaroudeurs.com, youtube.com, **tiktok.com (« Pack Picnic Tunisie »)**, promotionice.com, tanitdeco.com, comandgift.fr, all-in-company.com, mademoiselledennery.com, reddeco.com | aucun | ❌ (aucun e-commerçant tunisien du créneau : **opportunité**) |
| 4 | « coupe légumes tunisie » | **tunisianet.com.tn** (×2), wikipedia, frigotechnique.com (×2), shopiwell-ecom.tn, infotec.tn, finesse.ra.tn, behtunisie.store (×2) | Tunisianet | ❌ |
| 5 | « famma minnou » (marque) | music.apple.com (« Famma Mennou », RedStar feat. 4LFA), shazam.com, spotify.com (×2), facebook.com, soundcloud.com (« Minnou », Malka Family), wikipedia | — | ❌ **même sur la marque** : la requête est occupée par des titres musicaux |
| 6 | « fammaminnou.tn » (domaine) | uniquement des sites du Tennessee (« TN ») et Wikipédia | — | ❌ |
| 7 | « tapis de dessin à l'eau enfant prix Tunisie » | fnac.com, cdiscount.com, zeeman.com, jumia.com.tn (fermé), allobebe.ma, montessori-univers.com, 9moisetmoi.com, theaquadoodle.com, mon-petit-ange.com | aucun | ❌ (aucun concurrent tunisien actif : **opportunité persistante**) |
| 8 | « gadget cuisine Tunisie livraison paiement à la livraison » | spacenet.tn, **dokani.tn (×3)**, maalejaudio.tn, guirat.tn, ikitchen.com.tn, **wamia.tn**, behtunisie.store | Dokani, Wamia | ❌ |

**Bilan : 8 requêtes, FAMMA trouvée 0 fois (0 sur 11 le 23/09).**
- Deux jours ne suffisent pas pour faire bouger un index, et WebSearch n'est pas Google.tn. Le signal reste le même : FAMMA est absente de ce moteur, y compris sur sa marque et son domaine.
- **Dokani** et **Wamia** captent les requêtes cuisine, **Tunisianet** le coupe-légumes. Les requêtes « tapis pique-nique » et « tapis dessin à l'eau » n'ont aucun e-commerçant tunisien en tête : c'est là que FAMMA peut entrer en premier.
- La requête de marque est polluée par des titres musicaux (« Famma Mennou »). Écrire systématiquement « FAMMA MINNOU » avec « boutique » / « Tunisie », et « فمّا مينّو », reste nécessaire.
- Le social (TikTok) ressort sur une requête produit FAMMA (« tapis pique-nique »).

---

## 6. Écarts réduits et écarts restants

### Écarts réduits (depuis le 23/09)
1. **Vitesse : de dernière à première.** TTFB médian de 3,2 s à 0,17 s sur l'accueil, et de 5–6 s à 0,13–0,18 s sur la fiche, en cache. Les concurrents mesurent 0,27 à 0,86 s.
2. **Délai affiché : de rien à « 24 h à 48 h ».** Le délai figure dans le bandeau, sur la fiche et sur la page Livraison. C'est le délai standard le plus court annoncé dans le panel (Dokani 24–72 h, Express Shop 48–72 h, Mantoujna 2–3 j, Wamia 1–4 j), à condition de le tenir.
3. **Données fictives et textes de modèle retirés :** « Prd 1 » et sa fausse remise de −46 %, le doublon, les 4 catégories vides, « Les premiers produits arrivent », les liens http://.
4. **Contradiction de confiance levée.** Le bandeau « Satisfait ou remboursé » n'est plus affiché. Les notes internes ont disparu des pages Retours et CGV. Un parcours de retour guidé (formulaire vers WhatsApp) existe.
5. **WhatsApp au format international** (`wa.me/21626767491`) sur toutes les pages vues. Forca (lien sans 216) et Pratiko (`wa.me/#`) ont toujours un WhatsApp cassé ou douteux.
6. **Formulaire allégé** : de 7 à 6 champs, dont 4 obligatoires. Le nom est en un seul champ et la ville est facultative.
7. **Prix visible au premier écran mobile**, grâce à la barre fixe (prix, WhatsApp et « Commander »).
8. **Arabe :** menu, catégories, titre et sections de /ar/ sont traduits. FAMMA reste la seule boutique du panel avec hreflang FR/AR.
9. **Prix final :** l'écart se creuse, car Express Shop passe de 6 à 8 DT de livraison. FAMMA est la moins chère sur la visseuse 21V et les épices magnétiques une fois la livraison comptée.

### Écarts restants
1. **0 avis.** Express Shop affiche de vrais avis nommés, et Dokani une note de 4,9/5 (avec des chiffres discordants). TikTak affiche « 27 avis » partout. FAMMA n'a aucune preuve sociale, et ne doit en afficher que de vraies.
2. **Politique de retour non chiffrée, CGV incomplètes** (pas de raison sociale, de MF ni de RNE), alors que la case CGV est obligatoire pour commander. Dokani promet 14 j, Mantoujna 7 j, Wamia 30 j.
3. **Invisibilité dans la recherche** : 0 requête sur 8, marque et domaine compris.
4. **Réseaux :** Facebook seul. Pas d'Instagram, pas de TikTok, pas de pixel TikTok. Mantoujna et Wamia ont TikTok, et TikTok ressort sur « tapis pique-nique tunisie ».
5. **Formulaire encore plus long que le marché** (6 champs contre 3 chez Mantoujna, Forca, Tunisia Store et Express Shop) et placé à environ 1,5 écran. Express Shop le montre dès le premier écran.
6. **Catalogue mince :** 14 produits, contre 61 à 1 217 chez les boutiques comparables.
7. **Schema Product pauvre** : pas de `brand`, `shippingDetails` ni `hasMerchantReturnPolicy`, alors que Converty et Dokani les publient.
8. **Pas d'offres par quantité** (Mantoujna : 1/2/3), **pas d'« ouvrir le colis avant de payer »** (Dokani).
9. **Titres produits en français sur /ar/**, descriptions en Derja sur la version FR.
10. **Origine lente hors cache** (environ 2 s) : cela touche le panier, la commande et les visites après une purge.
11. Détail : deux numéros différents (WhatsApp 26767491, téléphone 56261412), et `telephone` sans +216 dans le JSON-LD.

---

## 7. Cinq priorités concurrentielles

| # | Action | Pourquoi (face à qui) | Exemple observé |
|---|---|---|---|
| **1** | **Collecter de vrais avis après chaque statut « livré »** (message WhatsApp avec lien vers la fiche). Les afficher sur la fiche et sur l'accueil (le bloc « Ils nous font confiance » seulement quand de vrais avis existent), puis ajouter `aggregateRating` au schéma | C'est le plus gros écart restant. Express Shop gagne la confiance avec des avis nommés, Dokani et TikTak affichent des notes. Règle projet : jamais d'avis inventés | https://expressshop.tn/produit/set-de-24-pots-a-epices-en-verre-avec-couvercles-bambou/ |
| **2** | **Chiffrer la politique de retour et compléter les CGV** : délai de rétractation, état exigé, frais de retour ; raison sociale, MF, RNE. Voir ensuite avec le transporteur si le client peut **ouvrir le colis avant de payer** | La case CGV est obligatoire alors que les CGV sont « en cours de rédaction » : c'est un risque de confiance et un risque juridique. Dokani (14 j + ouverture du colis) et Mantoujna (7 j) rassurent mieux | https://www.dokani.tn/shop/gh-325-mini-glaciere-thermoelectrique-chaud-froid-75l-9405 |
| **3** | **Ouvrir Instagram et TikTok** : liens dans l'en-tête et le pied de page, `sameAs` dans le JSON-LD, pixel TikTok. Premières vidéos sur les produits sans concurrent (tapis pique-nique XL, tapis dessin à l'eau) | Le créneau vit sur le social : TikTok sort sur « tapis pique-nique tunisie ». Mantoujna et Wamia y sont, FAMMA non | https://mantoujna.net/ (FB, IG, TikTok) |
| **4** | **Remonter le formulaire express dans les deux premiers écrans** et n'afficher que 4 champs (nom, téléphone, gouvernorat, adresse ; ville et quantité repliées). Tester des **offres par quantité** sur 2 ou 3 produits famille ou cadeau | Le formulaire est à environ 1,5 écran, contre le 1er écran chez Express Shop. Les boutiques Converty et TikTak demandent 3 champs. Chaque colis coûte 7 DT à FAMMA : un panier plus gros protège la marge | https://mantoujna.net/product/appareil-de-massage-chauffant-electrique-pour-les-articulations |
| **5** | **SEO produit bilingue** : ajouter au schéma `brand`, `shippingDetails` (0 TND, 1–2 j) et `hasMerchantReturnPolicy` (dès que la politique existe). Traduire les titres produits sur /ar/. Écrire 1 ou 2 guides FR + Derja par mois sur les requêtes sans concurrent tunisien. Garder le cache chaud (préchargement LSCache) pour que l'avantage de vitesse tienne | FAMMA est absente de la recherche (0 sur 8). Son schéma Product est plus pauvre que celui des boutiques Converty. L'origine hors cache reste à 2 s | fiche Dokani ci-dessus (JSON-LD Product + OfferShippingDetails + FAQPage) |

---

## 8. Note « position concurrentielle »

Le rapport du 23/09 ne détaille pas la grille qui aboutit à 5/10. J'ai donc reconstruit **10 critères à poids égal** à partir de son §5 (forces et faiblesses). Chacun est noté de 0 à 10 **par rapport au panel**, aux deux dates. La note du 25/09 = **5,0 (note officielle du 23/09) + l'écart mesuré sur la grille**, pour garder la même base de comparaison.

| # | Critère (§5 du 23/09) | 23/09 | 25/09 | Justification 25/09 |
|---|---|---|---|---|
| 1 | Prix final / livraison 0 DT sans seuil | 10 | 10 | Avantage conservé et renforcé (Express Shop passe à 8 DT) |
| 2 | Bilinguisme FR / Derja structuré | 7 | 8 | Menu et catégories traduits ; titres produits encore en FR sur /ar/ |
| 3 | Honnêteté (pas de fausse remise ni d'urgence) | 7 | 10 | Produit test à −46 % retiré ; aucun barré, compteur ou chiffre inventé |
| 4 | Parcours de commande express mobile | 5 | 6 | 6 champs dont 4 obligatoires, prix dans la barre fixe ; formulaire à 1,5 écran, contre 3 champs ailleurs |
| 5 | Vitesse serveur | 0 | 8 | 1ᵉʳ du panel en cache ; 2 s hors cache |
| 6 | Confiance légale et retours | 1 | 3 | Pages propres, contradiction levée ; mais politique non chiffrée, pas de MF ni de RNE |
| 7 | Délai de livraison affiché | 0 | 8 | 24–48 h partout ; pas d'heure limite (Dokani : avant 20 h) |
| 8 | Catalogue (profondeur et propreté) | 2 | 3 | Propre et sans catégorie vide, mais 14 produits |
| 9 | Preuve sociale (avis) | 3 | 3 | 0 avis, sans changement |
| 10 | Visibilité et présence (SEO, réseaux) | 4 | 4 | 0 sur 8 requêtes, Facebook seul, schéma Product pauvre ; hreflang et robots IA conservés |
| | **Moyenne de la grille** | **3,9** | **6,3** | **Écart : +2,4** |

**Note du 25/09 : 5,0 + 2,4 = 7,4 / 10.**

Pour aller au-delà de 8, il faut agir sur les critères 6, 9 et 10 (retours chiffrés et CGV complètes, vrais avis, présence Instagram/TikTok et référencement). Ce sont les priorités 1, 2, 3 et 5 du §7.

---

*Données collectées le 25/09/2026, entre 17 h 50 et 18 h 10. Prix, catalogues et pages peuvent évoluer. PageSpeed non mesuré. TTFB mesuré par curl : moyenne de 3 appels pour les concurrents, médiane de 5 appels pour FAMMA. Aucune commande, aucun formulaire soumis, aucun compte créé.*
