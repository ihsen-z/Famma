# Audit SEO, SEO local et GEO — fammaminnou.tn (contre-audit)

**Date** : 25/09/2026, 17 h 55 à 18 h 05 · **Référence** : `docs/audit-2026-09-23/audit-seo.md` (note 4,7/10, constats SEO-01 à SEO-30)
**Périmètre** : production `https://fammaminnou.tn` (WordPress 7.1.2, WooCommerce 11.1.2, TranslatePress, Site Kit 1.187.0, LiteSpeed Cache, thème `famma-child`, plugin `famma-core`)
**Méthode** : lecture seule, site en ligne uniquement. 49 requêtes vers le domaine, espacées de 8 à 15 s avec un User-Agent de navigateur : 38 pages HTML. Côté FR, 24 pages : accueil, boutique, 5 rayons dont 2 vides, 6 fiches (dont 2 testées avec un User-Agent Googlebot et GPTBot), 8 pages d'information et légales, 3 variantes à paramètres. S'y ajoutent 11 pages `/ar/` et 3 URL en 404, robots.txt, llms.txt, index et 3 sous-sitemaps, 4 tests de redirection, et 1 lecture par un agent IA (WebFetch). 22 requêtes de visibilité (outil WebSearch) et 2 requêtes Bing. Aucun formulaire envoyé, rien modifié hors de ce rapport.
**Anti-bot** : aucune page de challenge Imunify360 détectée (0 sur 49 : aucun corps < 2 Ko, toutes les pages 200 ont un `<title>`). Toutes les mesures sont donc retenues.
*Note de méthode : 8 requêtes graphify (`explain` render_meta, ai_agents, render_structured_data, render_x_default, famma_child_structured_data ; `query` canonical/robots, données structurées, filtre de la boutique). Seules les lignes citées ont été lues : `seo.php:307-341`, `535-566`, `672-721`, `820-929` ; `functions.php:745-774` ; `shop-widgets.php:130-169`, `388-425`. Complété par `git log` depuis le 23/09. Aucun graph miss.*

---

## 1. Note globale : **5,4 / 10** (4,7 le 23/09, soit +0,7)

| Axe | 23/09 | 25/09 | Justification |
|---|---|---|---|
| Technique | 5,5 | **6,5** | Le menu, le carrousel et le pied de page pointent vers `/categorie/…` (401 liens), plus aucun lien `http://`. Le produit de test et le doublon répondent 404 et ont quitté le sitemap, qui ne liste plus que 14 produits et 5 rayons. LiteSpeed Cache sert les pages en cache en 0,2 à 0,4 s. Il reste : aucune `canonical` sur la boutique, les rayons et leurs variantes (11 pages crawlées sans canonical, toutes indexables), le filtre « Catégories » qui produit encore des liens `?product_cat=`, un robots.txt inchangé, un `x-default` qui garde les paramètres et une redirection en deux sauts. |
| On-page | 5,0 | **5,5** | La version arabe a maintenant des titres, H1, menus et pied de page en arabe. « Satisfait ou remboursé » a été retiré, et les notes « À compléter » ne sortent plus dans les descriptions. En revanche : titres du catalogue de 86 à 115 caractères, meta description en arabe sur 11 des 24 pages FR crawlées (toutes les fiches, tous les rayons), slogan arabe en description des rayons, H1 d'accueil inchangé. |
| Données structurées | 6,0 | **6,0** | Aucune évolution du JSON-LD : `Product` toujours sans `shippingDetails`, `hasMerchantReturnPolicy` ni `brand`, `sku` numérique, fil d'Ariane `&amp;amp;`, FAQPage de `/ar/faq/` en français. |
| Contenu | 3,5 | **4,5** | Le catalogue est propre (14 produits, plus de test ni de doublon). La page Livraison est complète (511 mots : délai, zones, étapes, FAQ). Retours et CGV sont structurées, mais elles annoncent toujours que la politique est « en cours de rédaction ». Les descriptions produit ne sont qu'en derja, les rayons n'ont pas de texte, et la FAQ des fiches est la même sur les 14 produits. |
| Local | 3,0 | **3,5** | Les liens `tel:` sont maintenant en +216 et les horaires (9 h à 18 h) sont publiés. Mais le JSON-LD garde `56261412` sans indicatif, l'adresse se limite à « Sousse », deux numéros coexistent (WhatsApp 26767491, téléphone 56261412) et aucune fiche Google Business n'a été trouvée. |
| GEO / IA | 5,5 | **6,0** | Le délai « 24 h à 48 h » et les horaires sont maintenant extractibles : un agent IA (WebFetch) les a relevés mot pour mot. Les UA Googlebot et GPTBot reçoivent la page complète. Mais `llms.txt` n'a pas bougé (ni délai, ni horaires, ni retours), **Bing n'indexe aucune page du site**, la FAQ arabe est en français et `sameAs` n'a qu'un profil. |

Pondération identique au 23/09 : technique 25 %, on-page 20 %, contenu 25 %, données structurées 10 %, local 10 %, GEO 10 %.
Calcul : 6,5×0,25 + 5,5×0,20 + 6,0×0,10 + 4,5×0,25 + 3,5×0,10 + 6,0×0,10 = **5,4**.

**En une phrase** : en deux jours, le maillage, le catalogue, la version arabe et les pages de confiance ont nettement progressé. Mais aucune des corrections SEO propres au code (`canonical`, `noindex` des variantes, robots.txt, schéma Product, fil d'Ariane) n'a été faite, et le site reste **invisible sur 22 requêtes sur 22, sa propre marque comprise**. Bing n'a aucune page en index.

---

## 2. Évolution depuis le 23/09

Statuts : **Corrigé**, **Partiel**, **Inchangé**, **Régression**, **Non revérifiable**.

| ID | Constat (23/09) | Statut | Preuve (25/09) |
|---|---|---|---|
| SEO-01 | 98 liens internes vers `/boutique/?product_cat=` au lieu de `/categorie/` | **Partiel** (en grande partie corrigé) | Menu, carrousel d'accueil et pied de page pointent vers `/categorie/<slug>/` : 401 liens vers `/categorie/` ou `/ar/categorie/` sur 35 pages (2 à 4 vers `/categorie/cuisine/` le 23/09). Il reste 63 liens `?product_cat=` sur les 11 pages de listing (5 par page, 13 sur une page déjà filtrée), tous issus du filtre « Catégories » de la colonne de la boutique (`shop-widgets.php:417` → `famma_child_filter_url()`, `:139-158`, qui construit `/boutique/?product_cat=`). Aucun sur l'accueil, les fiches ni les pages d'information. |
| SEO-02 | Pas de canonical sur boutique et rayons, variantes indexables | **Inchangé** | Aucune `canonical` sur `/boutique/`, les 5 rayons, `/ar/boutique/`, `/ar/categorie/cuisine/`, `?orderby=price`, `?min_price=10&max_price=50`, `?product_cat=cuisine`. `robots` = `max-image-preview:large` seul. `/boutique/?product_cat=cuisine` a le même titre et le même H1 que `/categorie/cuisine/`. Code inchangé : `filter_robots()` (`seo.php:535-549`) ne traite que le tunnel, et `render_meta()` (`seo.php:307-341`) n'émet aucune canonical. |
| SEO-03 | Descriptions produit en derja, même en FR | **Inchangé** | 6 fiches FR sur 6 : meta description, `og:description` et `Product.description` en derja (« راكلات تدور 360°… »). Des H2 en arabe apparaissent sur les pages FR (« المواصفات التقنية », « شنوة تلقى في الكرتونة؟ »). |
| SEO-04 | Anti-bot Imunify360 : page vide en HTTP 200 | **Non revérifiable** (rassurant) | 0 challenge sur 49 requêtes espacées de 8 s ou plus. UA Googlebot : 145 Ko, page complète. UA GPTBot : 138 Ko, page complète. WebFetch (agent IA) : contenu lu. Le seuil de rafale (environ 18 requêtes en 40 s) n'a volontairement pas été re-testé, et un UA usurpé depuis une IP ordinaire ne prouve pas le traitement des vrais robots. À confirmer dans GSC (« Statistiques sur l'exploration »). |
| SEO-05 | Produit de test `prd-1` publié | **Corrigé** | `/produit/prd-1/` → 404, `noindex`, absent du sitemap. |
| SEO-06 | Doublon Pince de Câblage Pro | **Corrigé** | `/produit/pince-de-cablage-pro/` → 404. Seule `/produit/pince-cablage-pro/` reste dans le sitemap. Une 301 serait plus propre si l'ancienne URL a été partagée. |
| SEO-07 | 4 rayons vides en 200, liés partout | **Partiel** | Les rayons vides ont disparu du menu, du carrousel et du sitemap (5 rayons listés). Mais `/categorie/electronique/` (« premiers produits arrivent ») et `/categorie/uncategorized/` (« Sans rayon ») répondent encore 200, indexables et sans `noindex` : ce sont des soft 404 orphelines. |
| SEO-08 | Retours et CGV en brouillon, « Satisfait ou remboursé » | **Partiel** | Le bandeau « Satisfait ou remboursé » a disparu (0 occurrence). Retours : processus en 3 étapes, formulaire WhatsApp, FAQ, horaires, mais le corps dit « Notre politique de retour et de remboursement est en cours de rédaction ». CGV : 5 sections (prix, commande, livraison, paiement, contact), meta description « …complètes sont en cours de rédaction… ». Pas de délai de rétractation ni d'identité légale. Les deux pages restent indexables. |
| SEO-09 | Titres de 86 à 116 caractères, suffixe identique | **Inchangé** | Catalogue FR : 86 à 115 caractères, même suffixe « – Livraison offerte partout en Tunisie, paiement à la livraison \| FAMMA MINNOU ». Catalogue AR : 70 à 93 caractères. |
| SEO-10 | Description des rayons = slogan arabe | **Inchangé** | 5 rayons FR, les rayons vides et `/ar/categorie/cuisine/` : D = 20 (« فمّا ديما حاجة جديدة »). |
| SEO-11 | Version `/ar/` partielle | **Partiel** (bien avancé) | Titres arabes (« الحانوت », « الكوجينة », « التوصيل », « الشروط العامة للبيع »…), H1, menu (« الصحة والرياضة », « العدّة والبريكولاج »…), pied de page et H2 d'accueil traduits. Il reste : noms de produits en FR dans les titres et H1 de `/ar/product/…`, fil d'Ariane JSON-LD « Boutique » et « Cuisine » en FR, meta description FR sur `/ar/conditions-generales/` (SEO-34), une phrase FR sur `/ar/contact/`. |
| SEO-12 | FAQPage `/ar/faq/` en français, OnlineStore FR | **Inchangé** | `/ar/faq/` : FAQPage `inLanguage: "ar"`, première question « Comment je paie ? ». `description` d'OnlineStore en FR sur toutes les pages `/ar/`. |
| SEO-13 | Product sans `shippingDetails`, retours, `brand` | **Inchangé** | 6 fiches : `shippingDetails` null, `hasMerchantReturnPolicy` null, `brand` absent, `sku` = ID (184, 246, 170, 141). Prix du schéma = prix affiché. Le délai étant maintenant publié, `deliveryTime` peut être renseigné. |
| SEO-14 | Fil d'Ariane `&amp;amp;` | **Inchangé** | « Outils &amp;amp; Bricolage » et « Maison &amp;amp; Plein air » dans BreadcrumbList, en FR comme en AR. |
| SEO-15 | Liens de menu en `http://` et vers des paramètres | **Partiel** | 0 lien `http://` (264 + 132 + 132 le 23/09). « Promotions » (`?on_sale=1`) a été retiré, et l'accent de « Nouveautés » corrigé. Mais « Nouveautés » pointe toujours vers `/boutique/?orderby=date` (81 liens), une URL à paramètre indexable. |
| SEO-16 | hreflang avec paramètres | **Partiel** | Les hreflang TranslatePress sont maintenant propres : sur `?orderby=price`, `fr-FR` → `/boutique/` et `ar` → `/ar/boutique/`. Le `x-default` émis par `famma-core` (`seo.php:842`, `current_page_url`) garde la query string (`/boutique/?orderby=price`, `?min_price=…`), y compris sur `/ar/boutique/?orderby=price`. |
| SEO-17 | robots.txt : groupe Bingbot, deux groupes `*` | **Inchangé** | Fichier identique : `Bingbot` et 11 autres robots ont leur propre groupe, sans `Disallow: /wp-admin/` ni `/*?add-to-cart=`. Deux groupes `User-agent: *`. Code : `seo.php:680-720`. |
| SEO-18 | TTFB de 2 à 15 s, pas de cache | **Partiel** | LiteSpeed Cache actif (`x-litespeed-cache: hit`) : 9 pages sur 35 servies depuis le cache, en 0,2 à 0,4 s. Les 26 autres (MISS : fiches, `/ar/`, variantes) mettent 2,1 à 7,2 s (médiane 3,2 s). Voir l'audit performance. |
| SEO-19 | NAP minimal, pas de +216, pas de GBP | **Partiel** | `tel:+21656261412` et `wa.me/21626767491` sont en E.164, horaires « De 9 h à 18 h » sur Contact, Retours et CGV. Mais JSON-LD `telephone: "56261412"` sans indicatif, adresse = `addressLocality: "Sousse"` seule, deux numéros (voir SEO-33). Fiche Google Business : non revérifiable (aucune trouvée dans les résultats). |
| SEO-20 | Marque disputée, homonymes | **Inchangé** | Les requêtes de marque remontent `famma.tn`, `fammamenou.tn`, `famma-shop.com`, Famma Boutique (@fammaboutique sur Instagram et Facebook), `famma-dhaw.com`, `fama-me.com` et le titre de RedStar « Famma Mennou ». Pas d'`alternateName`, un seul `sameAs`. |
| SEO-21 | Aucun délai de livraison ni horaires | **Partiel** (en grande partie corrigé) | « Livraison en 24 h à 48 h » apparaît dans le bandeau de toutes les pages, sur les fiches, et sur Livraison (H2, tableau, étapes ; en AR « التوصيل في 24 لـ 48 ساعة »). Les horaires 9 h à 18 h sont publiés. Il manque le délai dans `llms.txt` et dans `/faq/` (4 questions, aucune sur le délai). |
| SEO-22 | Mots collés dans les descriptions | **Inchangé** | `/confidentialite/` : « …dans le code.Données collectées à la commandeNom et… ». `/cookies/` : « …boutique.Cookies nécessairesWooCommerce… ». |
| SEO-23 | Coquille « prix affiché . » | **Inchangé** | Toujours dans la meta de l'accueil, la description d'OnlineStore (toutes les pages) et `llms.txt`. |
| SEO-24 | Open Graph : dimensions forcées, `og:url` avec paramètres | **Partiel** | `og:url` sans query string (`?orderby=price` → `og:url` `/boutique/`). Mais `og:image:width/height` restent forcés à 1200×630 sur des WebP produit carrés (`seo.php:332-333`), et `og:title` de l'accueil vaut « FAMMA MINNOU » (`seo.php:313`). |
| SEO-25 | Logo du schéma 1200×630 | **Inchangé** | `famma-og.png` 1200×630 (`seo.php:907-912`). |
| SEO-26 | H2 dans le pied de page | **Inchangé** | « Navigation », « Informations », « Aide & support », « Paiement à la livraison » en H2 sur toutes les pages. |
| SEO-27 | H1 d'accueil sans mot-clé, bloc « premiers produits » | **Partiel** | Le bloc « Les premiers produits arrivent » a disparu de l'accueil (il ne reste que sur les rayons vides). H1 inchangé (« FAMMA — Il y a toujours quelque chose de nouveau. »), et le titre ne contient pas « Tunisie ». |
| SEO-28 | Sitemap avec `prd-1`, doublon, `uncategorized`, sans `/ar/` | **Partiel** | Sitemap propre : 14 produits (lastmod du 23 au 25/09), 10 pages, 5 rayons. Toujours ni URL `/ar/` ni images. |
| SEO-29 | Redirection en deux sauts, base `/ar/product/` | **Inchangé** | `http://www.` → 301 `https://www.` → 301 apex. `/ar/produit/raclette-magique/` → 301 `/ar/product/raclette-magique/`. |
| SEO-30 | Flux RSS vides annoncés, image `http://` sur `/ar/` | **Inchangé** | `/feed/`, `/comments/feed/` et un flux par page dans le `<head>`. `src="http://…/famma-badge.svg"` toujours présent sur `/ar/`. |

**Bilan** : 2 corrigés, 12 partiels, 15 inchangés, 1 non revérifiable, 0 régression. Les corrections viennent toutes du thème, du contenu et de l'admin. `seo.php` n'a reçu que le filtre des notes « À compléter » (487cef0) et le titre arabe via `trp_translate()` (6160cce).

---

## 3. Tableau récapitulatif par URL (25/09)

T = longueur du `<title>`, D = longueur de la meta description. « Indexable » = 200 sans `noindex`. Cache : H = `x-litespeed-cache: hit`.

| URL | T | Title (début) | D | Description | H1 | Canonical | Indexable |
|---|---|---|---|---|---|---|---|
| `/` (H, 0,3 s) | 57 | FAMMA MINNOU — Boutique en ligne, paiement à la livraison | 159 | FR, coquille « affiché . » | FAMMA — Il y a toujours quelque chose de nouveau. | OK | Oui |
| `/boutique/` (H) | 87 | Boutique – Livraison offerte… | 135 | FR | Boutique | **absent** | Oui |
| `/categorie/cuisine/` (H) | 86 | Cuisine – Livraison offerte… | 20 | **slogan AR** | Cuisine | **absent** | Oui |
| `/categorie/outils-bricolage/` | 97 | Outils & Bricolage – … | 20 | slogan AR | Outils & Bricolage | absent | Oui |
| `/categorie/maison-plein-air/` | 97 | Maison & Plein air – … | 20 | slogan AR | Maison & Plein air | absent | Oui |
| `/categorie/electronique/` | 91 | Électronique – … | 20 | slogan AR | Électronique (0 produit) | absent | **Oui (orpheline)** |
| `/categorie/uncategorized/` | 89 | Sans rayon – … | 20 | slogan AR | Sans rayon (0 produit) | absent | **Oui (orpheline)** |
| `/produit/raclette-magique/` | 100 | Raclette Magique 360° – … | 116 | **derja** | FR | OK | Oui |
| `/produit/visseuse-perceuse-sans-fil-21v/` (H) | 109 | Visseuse Perceuse Sans Fil 21V – … | 155 | derja | FR | OK | Oui |
| `/produit/pince-cablage-pro/` | 99 | Pince de Câblage Pro – … | 160 | derja | FR | OK | Oui |
| `/produit/gilet-natation-enfant/` | 109 | Gilet de Natation pour Enfants – … | 116 | derja | FR | OK | Oui |
| `/produit/coupe-legumes-4-en-1/` (UA Googlebot, H) | 99 | Coupe-Légumes 4-en-1 – … | 115 | derja | FR | OK | Oui |
| `/produit/tapis-dessin-eau-enfant/` (UA GPTBot) | 115 | Tapis de Dessin à l'Eau pour Enfants – … | 153 | derja | FR | OK | Oui |
| `/produit/prd-1/`, `/produit/pince-de-cablage-pro/` | 31 | Page non trouvée | 20 | — | — | — | Non (404) |
| `/livraison/` (H) | 24 | Livraison – FAMMA MINNOU | 147 | FR | Livraison | OK | Oui |
| `/conditions-generales/` (H) | 44 | Conditions générales de vente – … | 157 | FR, « en cours de rédaction » | CGV | OK | Oui |
| `/retours/` (H) | 40 | Retours et remboursements – … | 153 | FR, correcte | Retours et remboursements | OK | Oui |
| `/contact/` | 22 | Contact – FAMMA MINNOU | 126 | FR | Nous contacter | OK | Oui |
| `/a-propos/` | 23 | À propos – FAMMA MINNOU | 147 | FR | Toujours quelque chose de nouveau | OK | Oui |
| `/faq/` | 35 | Questions fréquentes – … | 158 | FR | Questions fréquentes | OK | Oui |
| `/confidentialite/`, `/cookies/` | 43 / 35 | Politique de … | 153 / 160 | **mots collés** | idem | OK | Oui |
| `/boutique/?orderby=price`, `?min_price=10&max_price=50` | 87 | = boutique | 135 | = boutique | Boutique | **absent** | **Oui (doublons)** |
| `/boutique/?product_cat=cuisine` | 86 | Cuisine – … | 135 | description de la boutique | Cuisine | **absent** | **Oui (doublon de `/categorie/cuisine/`)** |
| 404 (`/page-inexistante-audit-2509/`) | 31 | Page non trouvée | 20 | — | oui | — | Non (vraie 404) |
| `/ar/` (H) | 53 | فمّا مينّو — حانوت أونلاين تونسي… | 113 | AR | FAMMA — ديما فمّا حاجة جديدة. | OK | Oui |
| `/ar/boutique/` | 70 | الحانوت – توصيل مجاني… | 94 | AR | الحانوت | absent | Oui |
| `/ar/categorie/cuisine/` | 71 | الكوجينة – … | 20 | slogan | الكوجينة | absent | Oui |
| `/ar/product/raclette-magique/` | 84 | Raclette Magique 360° – توصيل… | 116 | AR | **nom FR** | OK | Oui |
| `/ar/product/visseuse-perceuse-sans-fil-21v/` | 93 | Visseuse Perceuse… – توصيل… | 155 | AR | nom FR | OK | Oui |
| `/ar/livraison/` | 22 | التوصيل – FAMMA MINNOU | 97 | AR | التوصيل | OK | Oui |
| `/ar/conditions-generales/` | 34 | الشروط العامة للبيع – … | 157 | **FR** | AR | OK | Oui |
| `/ar/retours/` | 36 | الإرجاع وترجيع الفلوس – … | 118 | AR | AR | OK | Oui |
| `/ar/faq/` | 32 | الأسئلة المتكرّرة – … | 89 | AR | AR (schéma en FR) | OK | Oui |
| `/ar/a-propos/`, `/ar/contact/` | 24 | شكون أحنا / اتصل بينا – … | 80 / 88 | AR | AR | OK | Oui |

---

## 4. Constats ouverts et nouveaux

### 4.1 Constats du 23/09 encore ouverts (par priorité)

| ID | Sévérité | Reste à faire | Qui | Effort |
|---|---|---|---|---|
| SEO-02 | Haute | Dans `render_meta()` (`seo.php:307`), émettre une canonical propre (sans query string) sur `is_shop()` et `is_product_category()`, en pointant `/boutique/?product_cat=x` vers `/categorie/x/`. Dans `filter_robots()` (`seo.php:535`), passer en `noindex, follow` dès qu'un paramètre `orderby`, `min_price`, `max_price`, `stock_status`, `on_sale` ou `product_cat` est présent. | Code | S |
| SEO-01 (reste) | Haute | Dans `famma_child_filter_url()` (`shop-widgets.php:139`), quand seul `product_cat` est demandé, renvoyer `get_term_link( $term )` plutôt que `add_query_arg()`. Garder les paramètres uniquement pour les combinaisons de filtres, qui seront en `noindex` avec SEO-02. | Code | S |
| SEO-03 | Haute | Descriptions FR dans l'éditeur, derja en traduction AR via TranslatePress. Commencer par les 4 produits à requête prouvée : visseuse, raclette, trancheuse, coupe-légumes. | Toi | L |
| SEO-08 | Haute | Rédiger la politique de retour (délai, état de l'article, frais, mode de remboursement en COD) et les CGV (identité légale). D'ici là, passer ces deux pages en `noindex` depuis la boîte Référencement. | Toi | M |
| SEO-13 | Moyenne | Filtre `woocommerce_structured_data_product` dans `famma-core` : `OfferShippingDetails` (0 TND, TN, `deliveryTime` 1 à 2 jours, maintenant publié), `brand`, SKU texte. `hasMerchantReturnPolicy` après SEO-08. | Code | M |
| SEO-09 | Moyenne | Raccourcir `title_suffix_fr` et `title_suffix_ar` (FAMMA > Référencement), par exemple « – Prix Tunisie, livraison gratuite \| FAMMA » (≤ 65 caractères). | Admin | S |
| SEO-10 | Moyenne | Texte de 80 à 150 mots par rayon (Produits > Catégories), FR et AR. | Toi | M |
| SEO-17 | Moyenne | Retirer `Bingbot` d'`ai_agents()` (`seo.php:717`), ajouter `Disallow: /wp-admin/` et `/*?add-to-cart=` dans chaque groupe, fusionner les deux groupes `*`. | Code | S |
| SEO-07 (reste) | Moyenne | `noindex` sur un rayon vide dans `filter_robots()`, ou suppression d'« Électronique » et des autres rayons vides en admin, puis renommage de « Uncategorized ». | Code ou Admin | S |
| SEO-12, SEO-14, SEO-16 (reste), SEO-22, SEO-23, SEO-24 (reste), SEO-25 à SEO-30 | Moyenne à basse | Inchangés depuis le 23/09 : les correctifs décrits dans l'audit de référence restent valables (paires FAQ AR, `html_entity_decode` du fil d'Ariane, `x-default` sans query string à `seo.php:842`, espaces avant `wp_strip_all_tags`, coquille à corriger en admin avec un seed en fusion, dimensions OG réelles, logo carré, H2 du pied de page, règle www unique, flux et badge `http://`). | Code, Admin | S |

### 4.2 Nouveaux constats

| ID | Sévérité | Constat | Preuve | Correctif | Qui | Effort |
|---|---|---|---|---|---|---|
| **SEO-31** | Haute | **Bing n'a aucune page du site en index**, et la marque n'y existe pas. Or Bing alimente ChatGPT Search et Copilot. Aucune balise `msvalidate.01` ni `google-site-verification` dans le HTML (GSC passe probablement par Site Kit, `GT-WRCTBPR9`). | Bing `site:fammaminnou.tn` : « لا توجد نتائج » (aucun résultat). Bing `"famma minnou"` : aucun résultat. | Bing Webmaster Tools (import depuis GSC), soumission de `wp-sitemap.xml`, puis IndexNow. Dans GSC : « Pages » et « Sitemaps », et inspection d'URL sur l'accueil et 3 fiches. | Toi | S |
| **SEO-32** | Moyenne | La FAQ des fiches produit est générique : les mêmes 5 questions (coût, zone, paiement, hors Tunisie, compte) et le même bloc « Livraison & Retours » sur les 14 fiches, reprenant mot pour mot la page Livraison. Aucune question propre au produit. | Onglet FAQ de `/produit/raclette-magique/`, identique sur les 6 fiches crawlées et sur `/livraison/`. | 3 à 5 questions réelles par produit (dimensions, compatibilité, entretien, contenu du colis), éditables en admin, et un bloc livraison court avec lien. Contenu unique et longue traîne (« raclette 360 ça marche sur le parquet ? »). Ne pas émettre de FAQPage pour le bloc générique. | Code + Toi | M |
| **SEO-33** | Moyenne | Deux numéros publiés sans hiérarchie : WhatsApp +216 26767491 (48 liens `wa.me`, jamais affiché en clair) et téléphone 56261412 (lien `tel:`, JSON-LD, `llms.txt`, pied de page). Pour Google, les IA et les annuaires, le seul numéro de la marque reste 56261412, sans indicatif. | Pied de page : lien « WhatsApp » → `wa.me/21626767491`, puis lien « 56261412 » → `tel:+21656261412`. JSON-LD `telephone: "56261412"`. | Propriétaire : confirmer les deux numéros (le 26767491 est noté comme provisoire). Côté code : `telephone` en E.164 et un `contactPoint` WhatsApp dans `organization_node()` (`seo.php:928`), avec le même numéro partout, y compris sur Facebook et une future fiche GBP. | Code + Toi | S |
| **SEO-34** | Basse | Meta description en français sur `/ar/conditions-generales/` : la description composée (`famma_seo_composed_description`, 487cef0) n'est pas passée par la traduction. | `/ar/conditions-generales/` : D = 157, « Nos conditions générales de vente complètes sont en cours de rédaction… ». | Faire passer la description composée par `trp_translate()` comme le titre (6160cce), ou renseigner la description AR dans la boîte Référencement. | Code | S |
| **SEO-35** | Basse | `llms.txt` figé depuis août : ni délai 24 à 48 h, ni horaires, ni rayons, ni processus de retour, téléphone sans +216, et la coquille. C'est pourtant la fiche d'identité lue en premier par les agents. | `/llms.txt` (433 octets compressés) : 5 faits, 7 liens, contact `56261412`. | Générer `llms.txt` depuis les mêmes réglages que Livraison et Contact (délai, horaires, zones), et ajouter les 5 rayons. | Code | S |
| **SEO-36** | Basse | Politique de confidentialité rédigée pour un développeur : « telles qu'elles sont implémentées dans le code » ouvre la page et sa meta description. | `/confidentialite/`, D = 153. | Reformuler pour le client, en admin. | Toi | S |

---

## 5. Statut des audits précédents (24/08 et 25/08)

Points qui ont bougé depuis le 23/09 : produit de test B4 et §4 **corrigés** (SEO-05). Pages légales §4 **partielles** (SEO-08). Contenu mince S5 **partiel**, avec la page Livraison enrichie mais les fiches toujours en derja. Réponses extractibles G3 **en grande partie corrigées** : délai et horaires publiés, retours encore en rédaction. Poids et vitesse 3.5 **partiels** (cache actif, SEO-18). Canonical 3.1 **toujours ouvert** (SEO-02). Tous les autres statuts du tableau §4 de l'audit du 23/09 sont inchangés.

---

## 6. Indexation, visibilité et concurrence

- **Google** : pas de mesure directe possible (l'outil de recherche n'est pas Google). `site:fammaminnou.tn` ne remonte aucune page du domaine dans l'outil utilisé, comme le 23/09. À vérifier dans GSC.
- **Bing** : **0 page indexée** (SEO-31). DuckDuckGo a répondu par un CAPTCHA, que je n'ai pas contourné : pas de mesure.
- **Résultat** : **0 requête sur 22** où FAMMA apparaît (0 sur 11 le 23/09, sur les mêmes 11 requêtes de référence).

### Requêtes de référence (liste du 23/09)

| # | Requête | 23/09 | 25/09 | Sites en tête le 25/09 |
|---|---|---|---|---|
| 1 | gadget cuisine Tunisie | absente | **absente** | guirat.tn, avantica.tn, maalejaudio.tn, chillandlit.tn, casa-square.com, wamia.tn |
| 2 | livraison gratuite Tunisie paiement à la livraison gadgets pratiques | absente | **absente** | mytek.tn, tunisiatech.tn, spacenet.tn, oneaday.tn, web6.tn, didactico.tn, dokani.tn |
| 3 | boîte à épices magnétique prix Tunisie | absente | **absente** | amazon.fr, wamia.tn, geantdrive.tn, lastpricetunisie.tn, dokani.tn, guirat.tn, ordex.tn |
| 4 | visseuse perceuse sans fil 21V prix Tunisie | absente | **absente** | tunisianet.com.tn, arkan.tn, technoquip-tn.com, mytek.tn, lastpricetunisie.tn, carrefour.tn, cheapshop.tn |
| 5 | متجر الكتروني تونس الدفع عند الاستلام توصيل مجاني منتجات جديدة | absente | **absente** | ubuy.tn, converty.shop, universalnet.tn, twenty.tn, mrketly.com |
| 6 | coupe légumes 4 en 1 prix Tunisie livraison | absente | **absente** | wamia.tn, unitech.com.tn, premia.tn, shopiwell-ecom.tn, petitsprix.tn, expressshop.tn |
| 7 | produits tendance Tunisie boutique en ligne nouveautés gadgets | absente | **absente** | technopro-online.com, hajetna.converty.shop, wamia.tn, mantoujna.net, forca.tn, tunisia-store.tn |
| 8 | tapis de dessin à l'eau enfant prix Tunisie | absente | **absente** | fnac, cdiscount, zeeman, jumia.com.tn (fermé) : **toujours aucun concurrent tunisien actif** |
| 9 | توصيل مجاني الدفع عند الاستلام تونس أدوات مطبخ | absente | **absente** | vidéos Facebook et TikTok, ubuy.tn |
| 10 | FAMMA MINNOU boutique Tunisie | absente | **absente** | wamia.tn, fama-me.com, famma-dhaw.com, **famma.tn**, zen.com.tn |
| 11 | fammaminnou.tn | absente | **absente** | aucun résultat pertinent (Tennessee) |

### Requêtes complémentaires

| Requête | 23/09 | 25/09 | Ce qui remonte le 25/09 |
|---|---|---|---|
| site:fammaminnou.tn | 0 | **0** (Bing : 0) | pages sans rapport |
| FAMMA MINNOU Tunisie | absente | **absente** | tunisierap.com (RedStar « Famma Mennou »), famma-dhaw.com, fama-me.com |
| famma minnou | absente | **absente** (Bing : 0) | Apple Music RedStar, Malka Family « Minnou » |
| famma mennou | homonyme noté | **absente** | RedStar « Famma Mennou » (Apple Music, Spotify, Amazon) |
| fammaminnou | non testée | **absente** | Wikipédia (Ammamma), Faminino |
| فمّا مينّو | absente | **absente** | Wikipédia « مينو », chaînes YouTube |
| "famma minnou" OR "fammaminnou" boutique paiement à la livraison | non testée | **absente** | famma.tn, **Famma Boutique (@fammaboutique)**, famma-shop.com, **fammamenou.tn** |
| famma menou boutique en ligne Tunisie | non testée | **absente** | wamia.tn, famma.tn, **fammamenou.tn** |
| raclette magique 360 prix Tunisie | absente | **absente** | petitsprix.tn (59 TND selon l'extrait, contre 49 TND chez FAMMA), allopromo.tn, Facebook Last Price |
| trancheuse électrique sans fil 4 en 1 Tunisie | absente | **absente** | expressshop.tn, benova.tn, shopiwell-ecom.tn, ordex.tn, jumia |
| gilet natation enfant Tunisie | absente | **absente** | decathlon.tn (à partir de 120 TND), spacenet.tn, tunisiejouets.com |

**Lecture** : deux jours ne suffisent pas pour être indexé et classé, et ce test ne passe pas par Google.tn. Le résultat Bing est en revanche factuel : le site n'y existe pas. Sur la marque, le terrain est occupé par six homonymes, dont une boutique sociale « Famma Boutique » que le 23/09 n'avait pas relevée. D'après les extraits, FAMMA reste moins cher sur la raclette (49 contre 59 TND) et le tapis de dessin à l'eau n'a toujours aucun concurrent tunisien actif. Ce sont les deux premières fiches à rédiger en FR.

## 7. Search Console, Bing, suivi

- **Google** : pas de balise de vérification dans le HTML. Site Kit 1.187.0 et la balise Google `GT-WRCTBPR9` sont présents. À vérifier dans GSC : sitemap soumis, pages indexées, et exclusions « Page en double sans URL canonique » attendues à cause de SEO-02.
- **Bing** : 0 page indexée, aucune propriété visible (SEO-31). **Action la plus rentable de la liste pour le GEO.**
- **Meta Pixel** toujours présent (`1061419273284333`).

## 8. GEO : état de la citabilité

| Signal | 23/09 | 25/09 |
|---|---|---|
| Délai de livraison | absent | **« 24 h à 48 h »** dans le bandeau, sur les fiches et sur Livraison (FR et AR). Relevé mot pour mot par un agent IA (WebFetch) |
| Horaires | absents | **9 h à 18 h** (Contact, Retours, CGV) |
| Politique de retour | brouillon | processus clair (écrire d'abord, photo, accord), mais politique « en cours de rédaction » |
| Identité légale | absente | absente |
| `llms.txt` | présent, incomplet | **inchangé** (SEO-35) |
| Robots IA | autorisés, anti-bot à risque | autorisés. UA GPTBot et Googlebot servis normalement à faible cadence |
| FAQPage | FR correcte, AR en français | inchangé |
| Autorité de marque | un `sameAs`, homonymes | inchangé, avec un homonyme de plus (Famma Boutique) |
| Index Bing (ChatGPT Search, Copilot) | non mesuré | **0 page** |
| Avis réels | aucun (normal) | aucun (normal) |

## 9. Stratégie mots-clés

Inchangée : voir §8 de l'audit du 23/09, avec la même réserve (aucun volume mesuré, à valider dans GSC et Keyword Planner). Nouvelle priorité d'exécution : **tapis de dessin à l'eau** (aucun concurrent tunisien actif) et **raclette 360** (FAMMA moins cher d'après les extraits) en premier, puis la visseuse 21V, très concurrentielle.

## 10. Quick wins actualisés (par gain et effort)

1. **Bing Webmaster Tools** : import depuis GSC, sitemap, IndexNow (SEO-31). *20 min, toi.*
2. **Canonical et `noindex, follow` des variantes** dans `seo.php` (SEO-02, et SEO-16 via le `x-default`). *1 h, code.*
3. **Filtre « Catégories » vers `get_term_link()`** (reste de SEO-01). *20 min, code.*
4. **Robots.txt** : retirer Bingbot, Disallow dans chaque groupe (SEO-17). *20 min, code.*
5. **`shippingDetails` avec `deliveryTime` 1 à 2 jours** et `brand` dans le schéma Product (SEO-13). *1 h, code.*
6. **Suffixes de titre raccourcis** (SEO-09). *10 min, admin.*
7. **`noindex` provisoire sur Retours et CGV**, et sur les rayons vides (SEO-08, SEO-07). *15 min, admin et code.*
8. **`llms.txt` alimenté par les réglages** : délai, horaires, rayons (SEO-35). *30 min, code.*
9. **Téléphone en E.164 et `contactPoint` WhatsApp** dans le schéma, une fois les numéros confirmés (SEO-33, SEO-19). *20 min, code et toi.*
10. **Coquille, mots collés, fil d'Ariane `&amp;amp;`, description AR des CGV** (SEO-23, SEO-22, SEO-14, SEO-34). *45 min.*

**Chantiers de fond**, dans l'ordre : descriptions produit FR (SEO-03, en commençant par le tapis de dessin et la raclette), politique de retour et CGV (SEO-08), FAQ propre à chaque produit (SEO-32), textes de rayon (SEO-10), finition AR (SEO-11, SEO-12), entité de marque (SEO-20 : `alternateName`, `sameAs`, GBP).
