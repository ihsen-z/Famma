# Audit SEO, SEO local et GEO — fammaminnou.tn

**Date** : 23/09/2026 · **Périmètre** : production `https://fammaminnou.tn` (WordPress 7.1.2, WooCommerce 11.0.1, TranslatePress, Site Kit 1.187.0, thème `famma-child`, plugin `famma-core`)
**Méthode** : lecture seule. Crawl HTTP de 53 URL (10 pages, 16 fiches produits, 7 catégories du sitemap, tunnel, 404, variantes à paramètres, 10 URL `/ar/`), plus robots.txt, sitemaps, llms.txt, flux et redirections. Lecture du code (`includes/seo.php`, `inc/catalog.php`, `scripts/deploy/seed-seo-settings.php`). Recherches web (outil de recherche US, pas Google : valeur indicative). Aucun fichier du projet modifié, aucun formulaire soumis.
*Note de méthode : graph miss sur `graphify query "SEO meta description title JSON-LD…"`. Le graphe date du 25/08, avant `includes/seo.php`. J'ai donc lu directement les lignes citées.*

---

## 1. Note globale : **4,7 / 10**

| Axe | Note | Justification |
|---|---|---|
| Technique | **5,5** | Solide : HTTPS, 301 propres, sitemap 200, tunnel en `noindex`, 404 réelle, auteurs fermés. Pénalisé par : pas de `canonical` sur boutique et catégories, variantes à paramètres indexables, menu qui pointe vers `?product_cat=` en `http://`, catégories vides en soft 404, TTFB de 2 à 15 s, anti-bot Imunify360 qui sert une page vide en HTTP 200. |
| On-page | **5,0** | Un seul H1 partout, slugs FR lisibles, `alt` renseignés. En revanche : titres de 86 à 116 caractères avec le même suffixe partout, meta descriptions en derja arabe sur les pages FR, slogan arabe en description de toutes les catégories, titres et H1 non traduits sur `/ar/`. |
| Données structurées | **6,0** | OnlineStore, WebSite, Product, BreadcrumbList et FAQPage sont présents, et le prix du schéma correspond au prix affiché sur les 16 fiches. Il manque `shippingDetails`, `hasMerchantReturnPolicy` et `brand`. Le fil d'Ariane est doublement échappé (`&amp;amp;`), la FAQ `/ar/` est en français et l'adresse est incomplète. |
| Contenu | **3,5** | 14 produits réels, dont les descriptions n'existent qu'en derja arabe (y compris sur la version FR). Un produit de test et un doublon restent en ligne. Retours et CGV sont des brouillons. 4 catégories sont vides, il n'y a ni blog ni avis. |
| Local | **3,0** | Le NAP est cohérent à l'intérieur du site mais réduit au minimum (ville seule, téléphone sans +216). Aucune fiche Google Business visible. Le numéro diffère de celui noté comme confirmé (26767491). La marque « FAMMA » est très disputée (homonymes). |
| GEO / IA | **5,5** | llms.txt, robots IA autorisés, FAQPage et pages factuelles sont en place. Mais le délai de livraison et la politique de retour manquent, la FAQ AR est en FR, `sameAs` ne contient qu'un profil, et l'anti-bot risque de bloquer les robots IA. |

Pondération : technique 25 %, on-page 20 %, contenu 25 %, données structurées 10 %, local 10 %, GEO 10 %.

**En une phrase** : les fondations posées en août tiennent (8 des 12 actions du plan précédent sont vérifiées en ligne). Le site perd maintenant son potentiel sur trois fronts : l'architecture des URL de catalogue (paramètres, doublons, catégories vides, pas de canonical), la langue du contenu produit (derja servie comme du français) et les pages de confiance restées à l'état de brouillon.

---

## 2. Tableau récapitulatif par URL

T = longueur du `<title>`, D = longueur de la meta description. « Indexable » = 200 sans `noindex`.

| URL | T | Title (début) | D | Description | H1 | Canonical | Indexable |
|---|---|---|---|---|---|---|---|
| `/` | 57 | FAMMA MINNOU — Boutique en ligne, paiement à la livraison | 159 | OK (FR, coquille « affiché . ») | « FAMMA — Il y a toujours quelque chose de nouveau. » | auto | Oui |
| `/boutique/` | 87 | Boutique – Livraison offerte partout en Tunisie… | 135 | OK (FR) | Boutique | **absent** | Oui |
| `/a-propos/` | 23 | À propos – FAMMA MINNOU | 147 | OK | Toujours quelque chose de nouveau | auto | Oui |
| `/contact/` | 22 | Contact – FAMMA MINNOU | 126 | OK | Nous contacter | auto | Oui |
| `/faq/` | 35 | Questions fréquentes – FAMMA MINNOU | 158 | OK | Questions fréquentes | auto | Oui |
| `/livraison/` | 24 | Livraison – FAMMA MINNOU | 147 | OK | Livraison | auto | Oui |
| `/retours/` | 40 | Retours et remboursements – … | 153 | **« À compléter par le propriétaire… »** | Retours et remboursements | auto | Oui |
| `/conditions-generales/` | 44 | Conditions générales de vente – … | 154 | **« À compléter par le propriétaire… »** | CGV | auto | Oui |
| `/confidentialite/` | 43 | Politique de confidentialité – … | 153 | **mots collés** (« code.Données… commandeNom ») | idem | auto | Oui |
| `/cookies/` | 35 | Politique de cookies – … | 160 | **mots collés** (« boutique.Cookies nécessairesWooCommerce ») | idem | auto | Oui |
| `/produit/prd-1/` | 84 | **Prd 1** – Livraison offerte… | 20 | slogan arabe | Prd 1 | auto | **Oui (produit de test)** |
| `/produit/pince-de-cablage-pro/` | 99 | Pince de Câblage Pro – … | 113 | derja + tableau collé | Pince de Câblage Pro | auto | **Oui (doublon)** |
| `/produit/pince-cablage-pro/` | 99 | Pince de Câblage Pro – … | 160 | derja | Pince de Câblage Pro | auto | Oui |
| `/produit/gilet-natation-enfant/` | 109 | Gilet de Natation pour Enfants – … | 157 | derja | idem | auto | Oui |
| `/produit/masque-plongee-enfant-shark/` | 113 | Masque de Plongée Enfant « Shark » – … | 143 | derja | idem | auto | Oui |
| `/produit/pilates-pro/` | 90 | Pilates Pro – … | 107 | derja + emojis | idem | auto | Oui |
| `/produit/pistolet-visseuse-adaptateur/` | 107 | Adaptateur Pistolet Visseuse – … | 144 | derja | idem | auto | Oui |
| `/produit/raclette-magique/` | 100 | Raclette Magique 360° – … | 116 | derja | idem | auto | Oui |
| `/produit/trancheuse-electrique-sans-fil/` | 116 | Trancheuse Électrique Sans Fil 4-en-1 – … | 124 | derja | idem | auto | Oui |
| `/produit/boite-salade-nomade/` | 112 | Boîte à Salade Nomade (Salad Pod) – … | 113 | derja | idem | auto | Oui |
| `/produit/coupe-legumes-4-en-1/` | 99 | Coupe-Légumes 4-en-1 – … | 115 | derja | idem | auto | Oui |
| `/produit/extenseur-dorsal/` | 95 | Extenseur Dorsal – … | 136 | derja | idem | auto | Oui |
| `/produit/boites-epices-magnetiques/` | 106 | Boîtes à Épices Magnétiques – … | 127 | derja | idem | auto | Oui |
| `/produit/tapis-dessin-eau-enfant/` | 115 | Tapis de Dessin à l'Eau pour Enfants – … | 155 | derja | idem | auto | Oui |
| `/produit/tapis-pique-nique-xl/` | 112 | Tapis de Pique-Nique XL 200x145cm – … | 113 | derja | idem | auto | Oui |
| `/produit/visseuse-perceuse-sans-fil-21v/` | 109 | Visseuse Perceuse Sans Fil 21V – … | 158 | derja | idem | auto | Oui |
| `/categorie/cuisine/` (et accessoires, enfants, bien-etre-sport, outils-bricolage, maison-plein-air) | 86–97 | {Rayon} – Livraison offerte… | 20 | **slogan arabe** | {Rayon} | **absent** | Oui |
| `/categorie/uncategorized/` | 89 | **Sans rayon** – … | 20 | slogan arabe | Sans rayon | absent | **Oui (dans le sitemap)** |
| `/categorie/electronique/`, `/maison-cuisine/` (+ auto-moto, beaute-sante) | ~90 | {Rayon} – … | 20 | slogan | {Rayon} | absent | **Oui, 0 produit (soft 404)** |
| `/panier/`, `/mon-compte/` (`/commande/` redirige vers `/panier/` quand le panier est vide) | 21–25 | Panier / Mon compte | 20 | slogan | oui | auto | Non (`noindex, nofollow`) |
| `/?s=…` | 87 | titre de la boutique | 135 | boutique | Résultats… | absent | Non |
| 404 (`/page-inexistante…/`, `/boutique/page/2/`) | 31 | Page non trouvée | — | — | oui | — | Non (vraie 404) |
| `/boutique/?orderby=price`, `?min_price=…`, `/categorie/cuisine/?orderby=…` | 86–87 | identique à la page mère | — | identique | identique | **absent** | **Oui (doublons)** |
| `/ar/` | 53 | فمّا مينّو — حانوت أونلاين تونسي… | 113 | AR | AR | auto | Oui |
| `/ar/boutique/` | 71 | Boutique – توصيل مجاني… | 94 | AR | **« Boutique » (FR)** | absent | Oui |
| `/ar/product/raclette-magique/` (`/ar/produit/…` → 301) | 84 | Raclette Magique 360° – توصيل مجاني… | 116 | AR | nom FR | auto | Oui |
| `/ar/livraison/`, `/ar/faq/`, `/ar/a-propos/`, `/ar/contact/` | 22–35 | **titres FR** (« Livraison – FAMMA MINNOU »…) | 80–97 | AR | FR sur livraison et faq | auto | Oui |
| `/ar/categorie/cuisine/` | 70 | Cuisine – توصيل مجاني… | 20 | slogan | **Cuisine (FR)** | absent | Oui |

---

## 3. Constats

Sévérité : **Critique** (bloque le classement ou l'indexation), **Haute**, **Moyenne**, **Basse**. Effort : S (< 1 h), M (demi-journée), L (plusieurs jours / contenu).

| ID | Sévérité | Constat | Preuve | Impact | Correctif (cause) | Effort |
|---|---|---|---|---|---|---|
| **SEO-01** | Critique | Le menu principal (toutes les pages) et le carrousel d'accueil pointent vers `/boutique/?product_cat=<slug>` au lieu de `/categorie/<slug>/`. | 98 liens `/boutique/?product_cat=…` dans le crawl, contre 2 à 4 liens vers `/categorie/cuisine/`. Ces deux URL servent le même contenu, sans canonical. | Le maillage interne alimente des URL à paramètres en doublon au lieu des pages de rayon du sitemap. Google doit choisir seul l'URL à classer, et les pages catégories n'ont aucun jus interne. | `wp-content/themes/famma-child/inc/catalog.php:104-112` (`add_query_arg( 'product_cat', … )`), utilisé dans `inc/home.php:286` et le menu. Remplacer par `get_term_link( $term )`. | S |
| **SEO-02** | Haute | Pas de `rel=canonical` sur la boutique, les catégories et leurs variantes (`?orderby`, `?min_price`, `?stock_status`, `?on_sale`, `?product_cat`). Toutes sont indexables. | `/boutique/`, `/categorie/*`, `/boutique/?orderby=price` : balise canonical absente, `robots` = `max-image-preview:large` seul. | Contenu dupliqué sans limite (combinaisons de filtres), budget de crawl dilué. Constat 3.1 du 24/08 toujours ouvert. | WordPress ne pose la canonical que sur les contenus singuliers, et `seo.php` n'en émet aucune (aucune occurrence de « canonical »). Dans `Seo::render_meta()` (`seo.php:275`), ajouter une canonical propre sur `is_shop()` et `is_product_category()`. Dans `filter_robots` (`seo.php:446-477`), passer en `noindex, follow` dès qu'un paramètre de tri ou de filtre est présent. | S |
| **SEO-03** | Haute | Les descriptions produit sont uniquement en derja (écriture arabe), y compris sur les pages FR (`lang="fr-FR"`). Les meta descriptions FR, `og:description` et `Product.description` sont donc en arabe. | Par exemple `/produit/raclette-magique/` : titre et H1 en FR, H3 et corps « نظام عصرة مزدوج… ». Les versions FR et `/ar/` ont un corps quasi identique. | Sur les requêtes françaises (« raclette 360 prix Tunisie »), seuls le titre et le H1 portent des mots-clés. La paire hreflang FR/AR ressemble à un doublon, et la page FR est contradictoire (lang fr, contenu ar). | Côté contenu, pas code : TranslatePress traite le français comme langue d'origine. Il faut donc écrire la fiche en FR dans l'éditeur et mettre la derja en traduction AR, ou renseigner la « description FR » dans la boîte Référencement FAMMA. Seul l'extrait court sert de repli à la description (`seo.php:231-232`). | L |
| **SEO-04** | Haute | Anti-bot Imunify360 (hébergeur) : après environ 18 requêtes en 40 s, le serveur renvoie **HTTP 200** avec la page « Un instant, s'il vous plaît… Votre demande est en cours de vérification » (10 mots, sans titre ni schéma). | 17 URL reçues sous cette forme pendant le premier crawl (1,2 s d'intervalle, navigateur standard). Aucun problème à 5 s d'intervalle. | Googlebot et Bingbot vérifiés sont en principe en liste blanche. GPTBot, ClaudeBot et PerplexityBot ne le sont pas forcément : ils peuvent indexer une page vide en 200, ce qui fait perdre la citabilité voulue par le robots.txt. Risque de soft 404. | Côté hébergeur (DirectAdmin / Imunify360) : mettre les robots IA en liste blanche ou relever le seuil. Vérifier dans GSC, rubrique « Statistiques sur l'exploration », et avec l'outil d'inspection d'URL. | S (ticket hébergeur) |
| **SEO-05** | Haute | Produit de test `prd-1` (« Prd 1 », −46 %) toujours publié, indexable, présent dans le sitemap et dans « Sans rayon ». | `/produit/prd-1/`, title « Prd 1 – … », description = slogan. | Signal de site inachevé, contraire à la règle §58/§60. Constat B4 du 25/08 toujours ouvert. | Mettre à la corbeille (pas de suppression définitive sans accord) et rediriger en 301 vers `/boutique/`. | S |
| **SEO-06** | Haute | Doublon produit : `/produit/pince-de-cablage-pro/` (17/09, 619 mots) et `/produit/pince-cablage-pro/` (23/09, 829 mots) portent le même title et le même H1. | Les deux dans le sitemap, les deux indexables. | Cannibalisation : les deux pages se concurrencent et le signal se divise. | Dépublier l'ancienne et poser une 301 vers `pince-cablage-pro`. | S |
| **SEO-07** | Haute | 4 catégories vides (Électronique, Maison & Cuisine, Auto & Moto, Beauté & Santé) en 200, indexables, liées depuis le menu et le carrousel d'accueil. | `/categorie/electronique/` : « Électronique (0) », pas de `noindex`. | Soft 404, pages pauvres liées depuis tout le site, mauvaise impression en navigation. | `inc/catalog.php:36` : `'hide_empty' => false`. Passer à `true` pour le menu et le carrousel, ou `noindex` sur une catégorie vide dans `filter_robots`. | S |
| **SEO-08** | Haute | Retours et CGV sont des brouillons publiés, alors que le bandeau de tout le site promet « Satisfait ou remboursé ». Aucune identité légale (raison sociale, matricule fiscal, RNE). | `/retours/` : « Points à trancher : délai de rétractation… ». `/conditions-generales/` : « Informations légales à fournir… ». | Confiance (E-E-A-T) : la page Retours est ce que Google et les IA lisent pour la politique marchande. La promesse sans politique écrite est trompeuse et expose juridiquement (loi tunisienne 2000-83). `hasMerchantReturnPolicy` est impossible sans elle. | Décision du propriétaire : rédiger les deux pages. En attendant, retirer « Satisfait ou remboursé » du bandeau et passer ces deux pages en `noindex` (boîte Référencement). | M (propriétaire) |
| **SEO-09** | Moyenne | Titres du catalogue trop longs (86 à 116 caractères) avec un suffixe identique de 77 caractères sur les 30 pages du catalogue. | « Trancheuse Électrique Sans Fil 4-en-1 – Livraison offerte partout en Tunisie, paiement à la livraison \| FAMMA MINNOU » (116). | Tronqué vers 60 caractères, donc réécrit par Google. Le suffixe répété n'apporte rien, et il manque des modificateurs comme « prix » ou « Tunisie » près du nom. | Réglage admin `title_suffix_fr` (issu de `scripts/deploy/seed-seo-settings.php:44`) appliqué dans `seo.php:397-414`. Viser par exemple « {Produit} – Prix Tunisie, livraison gratuite \| FAMMA » (≤ 65) et un titre manuel par fiche pour les meilleures ventes. | S |
| **SEO-10** | Moyenne | La meta description de toutes les catégories (et des pages sans description) est le slogan arabe « فمّا ديما حاجة جديدة », y compris en FR. | 7 catégories, D = 20. | Extrait de résultat vide de sens en FR, dupliqué sur 10 URL ou plus. | Le repli `seo.php:217-245` tombe sur `get_bloginfo('description')` car les descriptions de termes sont vides. Rédiger une description (80 à 150 mots) par rayon dans Produits > Catégories. Elle sert aussi de texte d'introduction et corrige le contenu mince. | M |
| **SEO-11** | Moyenne | Version arabe incomplète : titres FR sur `/ar/livraison/`, `/ar/faq/`, `/ar/a-propos/` et `/ar/contact/`. H1 « Boutique », « Livraison », « Cuisine » en FR. Noms de rayons et fil d'Ariane en FR. H2 d'accueil en FR sur `/ar/` (« Les premiers produits arrivent », « Questions fréquentes »). | Crawl `/ar/`. | Le SEO arabe est faible : pas de mots arabes dans les balises les plus pondérées. | Traduire dans TranslatePress (titres, noms de catégories, blocs d'accueil) ou renseigner `title_ar` dans la boîte Référencement. Pour les pages hors catalogue, `seo.php:393-395` renvoie le titre WordPress non traduit. | M |
| **SEO-12** | Moyenne | Le schéma FAQPage de `/ar/faq/` contient les questions et réponses **en français** avec `inLanguage: "ar"`. Le nœud OnlineStore de `/ar/` a une description FR. | JSON-LD `/ar/faq/` : « Comment je paie ? ». | Données structurées incohérentes avec la page, et source IA arabe erronée. | `seo.php:949` lit `post_content` (langue d'origine), et TranslatePress ne traduit pas le JSON-LD. `seo.php:833` lit toujours `home_description_fr`. Utiliser `self::bilingual()` et extraire les paires depuis le HTML traduit, ou maintenir des paires AR en réglage. | M |
| **SEO-13** | Moyenne | Le schéma Product ne contient ni `shippingDetails` (0 TND, TN), ni `hasMerchantReturnPolicy`, ni `brand`. Le `sku` est l'ID WordPress (entier `184`). | JSON-LD de `/produit/raclette-magique/`. Prix 49.000 TND = prix affiché (cohérence vérifiée sur les 16 fiches). | Avertissements « fiches marchand » dans Rich Results et GSC. La livraison gratuite n'apparaît pas dans l'extrait enrichi, alors que c'est l'argument principal. | Filtre `woocommerce_structured_data_product` dans `famma-core` : `OfferShippingDetails` (0 TND, TN, délai une fois fixé), `MerchantReturnPolicy` (après SEO-08), `brand` si connu. Renseigner de vrais SKU texte. | M |
| **SEO-14** | Moyenne | Fil d'Ariane JSON-LD doublement échappé : `"name": "Maison &amp;amp; Plein air"`. | Toutes les fiches et catégories dont le nom contient « & ». | Affichage « Maison &amp; Plein air » dans les résultats enrichis. | Comportement de WooCommerce avec les noms de termes contenant `&`. Soit filtrer `woocommerce_structured_data_breadcrumblist` (`html_entity_decode`) dans `famma-core`, soit renommer « Maison et plein air ». | S |
| **SEO-15** | Moyenne | Liens de menu en `http://` : « Boutique », « Promotions », « Nouveautes ». | 264 `href="http://fammaminnou.tn/boutique/"`, 132 `…?on_sale=1`, 132 `…?orderby=date` sur les pages crawlées. | Une redirection 301 à chaque clic et à chaque exploration, sur tout le site. Les liens mènent en plus vers des URL à paramètres (voir SEO-02). | Liens personnalisés du menu WordPress (Apparence > Menus) : passer en `https://` ou en chemins relatifs. Corriger au passage « Nouveautes » et « Categories » (accents). | S |
| **SEO-16** | Moyenne | Les `hreflang` reprennent les paramètres d'URL : `?add-to-cart=1`, `?orderby=`, `?s=`, et même `/ar/?lang=ar`. Sur `/produit/…?add-to-cart=1`, la canonical est propre mais les hreflang pointent vers l'URL à paramètre. | Crawl des variantes. | Les hreflang doivent viser des URL canoniques. Ici les signaux se contredisent et le cluster de langues est pollué. | `x-default` (`seo.php:753`) reprend `current_page_url` avec sa query string, et TranslatePress fait de même pour fr/ar. Retirer les paramètres dans `render_x_default` et, pour TranslatePress, filtrer ses hreflang (vérifier le nom du filtre dans la doc TP via Context7). Le `noindex` des variantes (SEO-02) règle l'essentiel. | S |
| **SEO-17** | Moyenne | robots.txt : `Bingbot` figure dans la liste des « agents IA ». Son groupe dédié remplace le groupe `*`, donc **Bingbot n'est plus exclu de `/wp-admin/` ni de `?add-to-cart=`**. Même effet pour GPTBot, ClaudeBot, etc. Le fichier contient aussi deux groupes `User-agent: *` (fusionnés par Google, pas par tous les robots). | robots.txt en production. | Bing (source de ChatGPT Search et Copilot) peut explorer les URL d'ajout au panier : pièges à crawl et sessions inutiles. | `seo.php:599-606` et `seo.php:616-632` : retirer `Bingbot` de `ai_agents()`, reprendre les `Disallow` WooCommerce (`/*?add-to-cart=`, `/wp-admin/`) dans chaque groupe et fusionner le second groupe `*` dans celui de WordPress. | S |
| **SEO-18** | Moyenne | Serveur lent : TTFB de 2 à 15 s, sans en-tête `x-litespeed-cache` (pas de cache de page). | Mesures curl répétées (voir aussi `ttfb2.txt` de l'agent performance). | Budget de crawl réduit, LCP mobile dégradé. Classement et conversion touchés. | Hors périmètre SEO : voir l'audit performance. Activer un cache de page (LSCache) compatible WooCommerce et TranslatePress. | M |
| **SEO-19** | Moyenne | SEO local minimal : adresse réduite à `addressLocality: "Sousse"`, téléphone `56261412` sans `+216`, aucune fiche Google Business trouvée. Le numéro publié diffère de celui noté comme confirmé pour la marque (26767491). | JSON-LD OnlineStore, pied de page, `/contact/`, llms.txt : tous affichent 56261412, donc cohérents entre eux. | Pas de panneau de connaissance ni de pack local. Un NAP divergent entre le site et Facebook ou GBP brouille l'entité. | Propriétaire : confirmer le numéro et créer la fiche GBP (zone desservie, sans adresse publique si pas de boutique physique). Côté code, `seo.php:851-858` : ajouter `streetAddress`, `postalCode` et `addressRegion` si fournis, et le téléphone au format E.164. | S + propriétaire |
| **SEO-20** | Moyenne | Marque très disputée : `famma.tn`, `famma-tn.shop`, `famma-shop.com`, `fammamenou.tn` (boutique Converty « Famma Menou », indexée), un titre musical « Famma Mennou ». La recherche « FAMMA MINNOU Tunisie » remonte ces homonymes, pas le site. | Recherches web du 23/09. | Requêtes de marque captées par des tiers, et confusion possible dans les réponses des IA. | Schéma : `alternateName` [« فمّا مينّو », « FAMMA »], `sameAs` complet (Facebook, Instagram, TikTok, GBP). Nommer la marque de façon constante « FAMMA MINNOU » partout. Si `fammamenou.tn` appartient au propriétaire, le rediriger en 301. | S |
| **SEO-21** | Moyenne | Livraison : aucun délai annoncé nulle part (ni `/livraison/`, ni FAQ, ni llms.txt). Horaires du service client absents. | `/livraison/` : 3 blocs (zone, frais, paiement), 224 mots. | La question la plus posée (« en combien de temps ? ») reste sans réponse citable. Le délai est aussi requis pour `shippingDetails.deliveryTime`. | Propriétaire : fixer un délai réel (par exemple « 24 à 72 h selon le gouvernorat ») et le publier en phrase autonome sur Livraison, FAQ et llms.txt. | S |
| **SEO-22** | Basse | Bug de découpe des descriptions : les mots sont collés entre blocs HTML. | `/confidentialite/` : « …dans le code.Données collectées à la commandeNom et… ». `/cookies/` et `pince-de-cablage-pro` (tableau) touchés de la même façon. | Extraits illisibles. | `seo.php:157` : `wp_strip_all_tags()` retire les balises sans insérer d'espace. Remplacer les fermetures de blocs (`</p>`, `</h*>`, `</li>`, `</td>`) par une espace avant le strip. | S |
| **SEO-23** | Basse | Coquille dans la description d'accueil : « vous ne payez que le prix affiché . ». Le texte en production ne correspond plus au script de seed. | Meta, og, Twitter, OnlineStore et llms.txt. | Faute propagée partout. | Corriger dans FAMMA > Référencement. **Attention** : `seed-seo-settings.php:95` fait un `update_option()` complet. Le rejouer **écraserait `same_as` (Facebook) et les modifications faites en admin**. Le passer en fusion (`array_merge` avec l'existant). | S |
| **SEO-24** | Basse | Les dimensions Open Graph sont forcées à 1200×630 alors que les visuels produit font 1080×1080 (WebP). `og:title` de l'accueil = « FAMMA MINNOU » seul. `og:url` des archives inclut les paramètres. | `seo.php:298-302`, `seo.php:281`, `seo.php:335-347`. | Aperçus Facebook et WhatsApp possiblement recadrés, titre de partage d'accueil pauvre. | Lire les dimensions réelles avec `wp_get_attachment_image_src()`, utiliser le titre SEO de l'accueil, retirer la query string de `og:url`. | S |
| **SEO-25** | Basse | Logo du schéma = bannière OG 1200×630 (pas carrée). | `seo.php:818-823`. | Recadrage possible dans le panneau de connaissance. | Logo carré dédié (≥ 112 px, idéalement 512×512). | S |
| **SEO-26** | Basse | Hiérarchie des titres : H2 dans le pied de page (« Navigation », « Informations », « Aide & support », « Paiement à la livraison ») et H2 par carte produit (accueil, « Produits similaires »). | Toutes les pages. | Plan de document dilué, secondaire. | Rétrograder en `<p class>` ou en H3 dans `inc/footer-parts.php` et dans les cartes. | S |
| **SEO-27** | Basse | Accueil : le H1 « FAMMA — Il y a toujours quelque chose de nouveau. » ne contient aucun mot-clé, et le title ne mentionne pas « Tunisie ». Le bloc « Les premiers produits arrivent » est périmé (16 produits en ligne). | `/`. | Pertinence de la page la plus forte du site. | H1 par exemple « FAMMA MINNOU, boutique en ligne en Tunisie : paiement à la livraison ». Title : « … en Tunisie ». Masquer le bloc d'attente dès qu'un produit existe. | S |
| **SEO-28** | Basse | Le sitemap contient `prd-1`, le doublon pince et `uncategorized`. Il ne contient ni les URL `/ar/` (TranslatePress gratuit) ni d'entrées images. | `wp-sitemap-*.xml` (3 sous-sitemaps, 200, `lastmod` présent). | Découverte AR limitée aux hreflang et aux liens. | Réglé en partie par SEO-05, -06 et -07. Exclure la catégorie par défaut avec `wp_sitemaps_taxonomies_query_args`. Pour AR, TranslatePress SEO Pack ou un fournisseur de sitemap maison. | S |
| **SEO-29** | Basse | Redirection en deux sauts : `http://www.` → `https://www.` → `https://fammaminnou.tn/`. `/ar/produit/…` → 301 `/ar/product/…` (base de slug anglaise côté AR). | Tests curl. | Négligeable pour le SEO, un peu de latence. | Règle unique `http(s)://www` → `https://` apex. Traduire la base de slug produit dans TranslatePress. | S |
| **SEO-30** | Basse | Flux RSS vides annoncés dans le `<head>` (articles et commentaires), flux par catégorie. Image `http://…/famma-badge.svg` (contenu mixte) sur `/ar/`. | `/feed/` sans item. | Bruit de crawl. | Retirer `feed_links` et `feed_links_extra` si le site n'a pas de blog, et corriger l'URL de l'image dans la traduction TranslatePress. | S |

---

## 4. Statut des constats des audits précédents

### Audit du 24/08 (`docs/AUDIT-SEO-2026-08-24.md`)

| Réf. | Constat | Statut au 23/09 |
|---|---|---|
| 2.1 | Aucune meta description | **Corrigé** (toutes les pages). Qualité à reprendre : slogan arabe sur les catégories, derja sur les produits FR (SEO-03, SEO-10) |
| 2.2 | Sitemap en 404 | **Corrigé** (index et 3 sous-sitemaps en 200) |
| 2.3 | Aucune extension SEO | **Contourné** par la classe `Famma\Core\Seo` (réglages admin FR/AR) |
| 3.1 | Canonical absente sur la boutique | **Ouvert**, et étendu aux catégories et aux variantes (SEO-02) |
| 3.2 | `alt` vide sur les vignettes de catégories | **Clos par conception** : `alt=""` à côté du nom visible dans le même lien, c'est la bonne pratique (`inc/home.php:289`) |
| 3.3 | Pas de `x-default` | **Corrigé**, mais il suit les paramètres d'URL (SEO-16) |
| 3.4 | Slogan arabe dans le titre d'accueil | **Corrigé** pour le titre. Le slogan reste la description de repli (SEO-10) |
| 3.5 | Poids et nombre de requêtes | **Ouvert** : TTFB de 2 à 15 s (SEO-18, audit performance) |
| §4 | Produits de test | **Ouvert** : `prd-1` en ligne (SEO-05) |
| §4 | Pages légales vides | **Ouvert** : Retours et CGV en brouillon (SEO-08) |

### Audit SEO & GEO du 25/08 (`docs/AUDIT-SEO-GEO-2026-08-25.md`, `docs/RAPPORT-SEO-2026-08-25.html`)

| Réf. | Constat | Statut au 23/09 |
|---|---|---|
| B1 | Aucune meta description ni og:description | **Corrigé** |
| B2 | Tunnel indexable et dans le sitemap | **Corrigé** (`noindex, nofollow`, hors sitemap) |
| B3 | `/author/admin/` en 200, sitemap des comptes | **Corrigé** (404 sur les deux) |
| B4 | Produit de test `prd-1` | **Ouvert** (SEO-05) |
| S1 | Titres WordPress par défaut | **Corrigé**, mais ils sont maintenant trop longs et répétitifs (SEO-09) |
| S2 | JSON-LD sur l'accueil seulement | **Corrigé** (toutes les pages hors tunnel) |
| S3 | Organization incomplet | **Partiel** : téléphone, e-mail et ville présents. Adresse incomplète, pas de format E.164, numéro à confirmer (SEO-19) |
| S4 | Pas de `x-default` | **Corrigé** (réserve SEO-16) |
| S5 | Contenu trop mince | **Partiel** : 14 produits réels de 700 à 960 mots, mais uniquement en derja. Pas de blog, pas de texte de rayon (SEO-03, SEO-10) |
| S6 | Images JPG/PNG | **Partiel** : produits en WebP. Vignettes du carrousel de catégories encore en JPG |
| G1 | Pas de FAQPage | **Corrigé** en FR (4 questions). En français sur `/ar/` (SEO-12) |
| G2 | Pas d'OnlineStore | **Corrigé** |
| G3 | Réponses non extractibles | **Partiel** : Livraison claire mais sans délai, Retours en brouillon (SEO-08, SEO-21) |
| G4 | Pas de `/llms.txt` | **Corrigé** (200, `X-Robots-Tag: noindex`) |
| G5 | Robots IA non mentionnés | **Corrigé**, avec deux effets de bord : groupe Bingbot (SEO-17) et anti-bot hébergeur (SEO-04) |
| G6 | Pas d'avis | **Ouvert, et c'est normal** : « Avis (0) », aucun avis inventé. Collecter de vrais avis après livraison |
| Vigilance | Téléphone 56261412 contre 26767491 | **Toujours à trancher** par le propriétaire (SEO-19) |
| Rapport §08 | `sameAs` vide | **Corrigé** en admin (Facebook `famma.shop.tn`), mais menacé par le script de seed (SEO-23) |

---

## 5. Indexation, visibilité et concurrence (constat du 23/09)

- **`site:fammaminnou.tn`** : aucun résultat du domaine dans l'outil de recherche utilisé. Cet index est américain et différent de Google : l'absence est indicative, pas probante. Couverture réelle à vérifier dans GSC (« Pages » et « Sitemaps »).
- **Marque** : « FAMMA MINNOU Tunisie », « famma minnou » et « فمّا مينّو » remontent `fammamenou.tn` (Converty), `famma.tn`, `famma-tn.shop`, `famma-shop.com` et un titre musical, mais pas le site (SEO-20).
- **Requêtes commerciales testées** (le site n'apparaît sur aucune) :
  - « raclette magique 360 prix Tunisie » : petitsprix.tn (raclette magique à 59 TND selon l'extrait, contre 49 TND chez FAMMA), allopromo.tn, Facebook Last Price.
  - « visseuse perceuse sans fil 21V prix Tunisie » : tunisianet, mytek, technopro, arkan, lastpricetunisie, cheapshop.tn.
  - « trancheuse électrique sans fil 4 en 1 Tunisie » : shopiwell-ecom.tn (67,5 TND selon l'extrait, contre 59 TND chez FAMMA), expressshop.tn, jumia.com.tn.
  - « gilet natation enfant Tunisie » : decathlon.tn, tunisiejouets.com, piscineshop.tn.
- **Lecture** : la concurrence classe sur des fiches **en français**, avec « prix » et « Tunisie » dans le titre, et des catégories bien maillées. FAMMA est souvent moins cher d'après les extraits, mais invisible. Les leviers sont SEO-01, SEO-02, SEO-03 et SEO-09.

## 6. Search Console, Bing, suivi

- **Google** : pas de balise `google-site-verification` dans le HTML. Site Kit 1.187.0 est actif avec la balise Google `GT-WRCTBPR9` : la propriété GSC est probablement validée par Site Kit (API, DNS ou fichier). À confirmer dans GSC, et soumettre `wp-sitemap.xml` si ce n'est pas fait.
- **Bing** : aucune balise `msvalidate.01`. Importer la propriété depuis GSC dans Bing Webmaster Tools. C'est prioritaire pour le GEO : l'index Bing alimente ChatGPT Search et Copilot. Activer IndexNow ensuite.
- **Meta Pixel** présent (`1061419273284333`).

## 7. GEO — état de la citabilité

| Signal | État |
|---|---|
| `llms.txt` | Présent et factuel. Il manque le délai de livraison, les rayons, la politique de retour, et le téléphone en +216. Il reprend la coquille (SEO-23) |
| Robots IA | Autorisés explicitement, mais exposés à l'anti-bot (SEO-04) |
| FAQPage | FR correcte (4 Q/R). Depuis 2023, Google réserve les résultats enrichis FAQ aux sites officiels : l'intérêt est ici le GEO, pas l'extrait Google. AR incohérente (SEO-12) |
| Faits extractibles | Paiement : oui. Frais (0 TND) : oui. Zone (24 gouvernorats) : oui. Commande sans compte : oui. **Délai : non. Retours : non. Identité légale : non. Horaires : non** |
| Autorité de marque | Faible : un seul `sameAs`, aucune mention externe trouvée, homonymes forts (SEO-20) |
| Avis réels | Aucun pour l'instant (normal). Mettre en place une sollicitation après livraison, puis `aggregateRating` |

## 8. Stratégie mots-clés recommandée

> **Réserve** : aucun outil de volumes n'était disponible. La priorité ci-dessous repose sur l'intention d'achat et la concurrence observée dans les résultats, **pas sur des volumes mesurés**. À valider avec GSC (requêtes réelles après 4 à 6 semaines) et Google Keyword Planner (zone Tunisie). En Tunisie, les recherches Google d'achat se font surtout en **français + « prix » + « Tunisie »**. L'arabizi (derja en lettres latines) est surtout présent sur Facebook et TikTok, et l'arabe standard ou la derja en écriture arabe servent à des requêtes plus descriptives.

| # | Requête cible | Langue | Page cible | Priorité |
|---|---|---|---|---|
| 1 | visseuse sans fil 21v prix tunisie | FR | `/produit/visseuse-perceuse-sans-fil-21v/` | Haute |
| 2 | raclette 360 / balai serpillière rotatif 360 tunisie | FR | `/produit/raclette-magique/` | Haute |
| 3 | trancheuse électrique sans fil / râpe électrique 4 en 1 tunisie | FR | `/produit/trancheuse-electrique-sans-fil/` | Haute |
| 4 | coupe légumes mandoline prix tunisie | FR | `/produit/coupe-legumes-4-en-1/` | Haute |
| 5 | gilet natation enfant tunisie | FR | `/produit/gilet-natation-enfant/` | Haute (saison) |
| 6 | masque plongée enfant / masque snorkeling enfant tunisie | FR | `/produit/masque-plongee-enfant-shark/` | Moyenne (saison) |
| 7 | tapis pique-nique imperméable tunisie | FR | `/produit/tapis-pique-nique-xl/` | Moyenne |
| 8 | boîte à épices magnétique tunisie | FR | `/produit/boites-epices-magnetiques/` | Moyenne |
| 9 | tapis dessin à l'eau enfant tunisie | FR | `/produit/tapis-dessin-eau-enfant/` | Moyenne |
| 10 | pince à dénuder / pince à sertir câble tunisie | FR | `/produit/pince-cablage-pro/` | Moyenne |
| 11 | étireur de dos / extenseur dorsal tunisie | FR | `/produit/extenseur-dorsal/` | Moyenne |
| 12 | outillage / bricolage pas cher tunisie livraison gratuite | FR | `/categorie/outils-bricolage/` | Moyenne |
| 13 | ustensiles cuisine pratiques tunisie | FR | `/categorie/cuisine/` | Moyenne |
| 14 | boutique en ligne tunisie paiement à la livraison | FR | `/` | Haute (très concurrentielle) |
| 15 | famma minnou / fammaminnou | FR / latin | `/` | Haute (marque) |
| 16 | visseuse 21v soum / prix visseuse tounes | Arabizi | fiche visseuse (mention naturelle dans la FAQ) | Basse (à tester) |
| 17 | فمّا مينّو | AR | `/ar/` | Haute (marque) |
| 18 | بيع أونلاين تونس الخلاص عند الاستلام | AR / derja | `/ar/` | Moyenne |
| 19 | سعر فيساز 21 فولت تونس / آلة ثقب بلا خيط | AR | `/ar/product/visseuse-perceuse-sans-fil-21v/` | Moyenne |
| 20 | سترة سباحة للأطفال تونس | AR | `/ar/product/gilet-natation-enfant/` | Moyenne |

**Mise en œuvre** : titre FR du type « {Nom générique} {caractéristique} – Prix Tunisie | FAMMA » ; description FR (bénéfices, caractéristiques, contenu du colis, FAQ produit de 3 à 5 questions réelles) ; version AR en derja pour `/ar/` avec titre et H1 AR ; texte d'introduction pour chaque rayon ; plus tard, quelques guides (« comment choisir une visseuse sans fil ») si le propriétaire veut du contenu éditorial.

## 9. Quick wins (par gain / effort)

1. **Menu et carrousel vers `/categorie/<slug>/`, en `https://`** (SEO-01, SEO-15) : `inc/catalog.php:104-112` et Apparence > Menus. *30 min.*
2. **Canonical sur boutique et catégories, `noindex, follow` sur les variantes triées ou filtrées** (SEO-02, SEO-16) : `seo.php`. *1 h.*
3. **Corbeille + 301 pour `prd-1` et `pince-de-cablage-pro`** (SEO-05, SEO-06) : après accord du propriétaire. *15 min.*
4. **Masquer les catégories vides** (`hide_empty`) et sortir « Sans rayon » du sitemap (SEO-07, SEO-28). *30 min.*
5. **Retirer « Satisfait ou remboursé »** tant que Retours n'est pas rédigé, et passer Retours et CGV en `noindex` provisoire (SEO-08). *15 min.*
6. **Raccourcir `title_suffix_fr` et `title_suffix_ar`** en admin (SEO-09). *10 min.*
7. **Corriger `robots.txt`** : retirer Bingbot de la liste IA, reprendre les Disallow WooCommerce (SEO-17). *20 min.*
8. **Bing Webmaster Tools** (import depuis GSC) et contrôle de la couverture GSC (§6). *20 min.*
9. **Liste blanche Imunify360** pour les robots IA (ticket à l'hébergeur) (SEO-04). *Ticket.*
10. **Coquille d'accueil, bug de découpe `seo.php:157`, seed en fusion** (SEO-22, SEO-23). *30 min.*
11. **Délai de livraison** publié sur Livraison, FAQ et llms.txt, une fois fixé par le propriétaire (SEO-21). *15 min.*
12. **Fil d'Ariane `&amp;amp;`** : renommer les rayons avec « et » ou filtrer (SEO-14). *15 min.*

**Chantiers de fond**, dans l'ordre : descriptions produit en français (SEO-03), textes de rayon (SEO-10), traduction AR complète (SEO-11, SEO-12), `shippingDetails` et `hasMerchantReturnPolicy` (SEO-13), cache serveur (SEO-18), entité locale et marque (SEO-19, SEO-20), avis réels collectés après livraison.
