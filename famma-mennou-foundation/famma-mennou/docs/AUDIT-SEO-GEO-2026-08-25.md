# Audit SEO & GEO — fammaminnou.tn — 25/08/2026

Audit réalisé sur le site en production (HTTP live), pas sur l'environnement local.
Pages inspectées : accueil, /ar/, boutique, produit/prd-1, panier, commande, mon-compte,
contact, faq, livraison, à-propos, robots.txt, wp-sitemap.xml.

---

## 1. Verdict

Les fondations techniques sont saines (HTTPS 200, sitemap XML natif WP valide, robots.txt
propre, canonical présent partout, hreflang FR/AR posé, un seul `<h1>`, 100 % des images
avec `alt`, JSON-LD Organization + WebSite + Product/BreadcrumbList).

Ce qui manque relève de deux familles :

1. **Aucune description meta sur aucune page du site** — plus gros levier CTR immédiat.
2. **Rien n'est en place pour le GEO** (réponses des moteurs génératifs) : pas de FAQPage,
   pas de LocalBusiness, pas de contenu répondant à des questions, pas de llms.txt.

Le site est aussi **quasi vide de contenu** (1 produit, `prd-1`, 465 mots en accueil), ce qui
plafonne mécaniquement tout ce qui suit.

---

## 2. Bloquants (à corriger avant toute campagne)

### B1 — Zéro `<meta name="description">` sur l'ensemble du site

Vérifié sur accueil, boutique, produit, contact, faq, livraison, à-propos : aucune balise.
Google génère alors un extrait au hasard dans la page. `og:description` manque aussi, donc
un partage WhatsApp/Facebook affiche un titre nu.
→ `themes/famma-child/functions.php:176` (`famma_child_open_graph`) émet og:title / type / url /
locale / image mais pas de description, et rien n'émet la meta description.

### B2 — Panier / Commande / Mon compte indexables et dans le sitemap

`wp-sitemap-posts-page-1.xml` liste `/panier/`, `/commande/`, `/mon-compte/`, et aucune de
ces pages ne renvoie `<meta name="robots" content="noindex">`. Ce sont des pages sans valeur
de recherche qui diluent le budget de crawl et peuvent remonter sur des requêtes de marque.

### B3 — Page auteur exposée : `/author/admin/` répond 200

Elle est en plus listée dans `wp-sitemap-users-1.xml`. Double problème : contenu vide
indexable, et divulgation publique du login administrateur (`admin`) — surface d'attaque par
force brute.

### B4 — Le produit publié est une donnée de test

`https://fammaminnou.tn/produit/prd-1/` — titre « Prd 1 », slug `prd-1`, prix barré 140,000.
C'est en production, dans le sitemap, indexable. Contraire à la règle §58/§60 du projet
(aucune donnée fictive) et destructeur pour la confiance comme pour le SEO.

---

## 3. SEO — améliorations importantes

### S1 — Titres par défaut de WordPress

`Prd 1 – FAMMA MINNOU`, `Boutique – FAMMA MINNOU`, `Contact – FAMMA MINNOU`. Aucun titre ne
porte de mot-clé ni le marché. Un gabarit du type
`{Produit} – Livraison gratuite partout en Tunisie | FAMMA MINNOU` capte la requête réelle.

### S2 — Le JSON-LD ne sort que sur l'accueil

`functions.php:657` : `if ( ! is_front_page() ) return;`. Conséquence vérifiée : les pages FAQ
et Contact ne contiennent **aucun** bloc `application/ld+json`. Organization et WebSite
devraient être présents sur toutes les pages.

### S3 — Organization incomplet

Le graphe (`functions.php:668-686`) ne déclare ni `telephone`, ni `address`, ni `sameAs`.
Les coordonnées existent pourtant et sont confirmées (contact@fammaminnou.tn, 26767491,
Sousse) : ce n'est plus une donnée inventée, elle peut être déclarée. Sans elle, pas de
Knowledge Panel, pas de rattachement local.

### S4 — Pas de `hreflang="x-default"`

Les couples FR/AR sont corrects et réciproques, mais aucun `x-default` : Google choisit seul
la version servie aux visiteurs hors zone FR/AR.

### S5 — Contenu trop mince

465 mots en page d'accueil, 1 produit, 0 article. Aucune page ne cible une requête
informationnelle. Sans corpus, ni le SEO ni le GEO n'ont de matière.

### S6 — Images en JPG/PNG

5 JPG + 2 PNG en accueil, aucun WebP/AVIF. Sur un site mobile-first en 3G/4G tunisienne,
c'est le premier poste de LCP.

---

## 4. GEO — optimisation pour les moteurs génératifs

Le GEO consiste à être **cité** dans les réponses de ChatGPT, Perplexity, Google AI Overviews
et Gemini. Ces moteurs extraient des passages courts, factuels et attribuables. État actuel :
rien n'est en place.

### G1 — Aucun schéma FAQPage (impact le plus élevé)

`/faq/` existe et ne contient aucun JSON-LD. C'est la page la plus facilement citable du site.
Un balisage `FAQPage` sur les questions réellement présentes (délais, frais, paiement à la
livraison, retours) la rend directement exploitable par les LLM et éligible aux rich results.

### G2 — Aucun LocalBusiness / OnlineStore

Un moteur interrogé sur « boutique en ligne tunisienne paiement à la livraison » n'a aucun
signal permettant de rattacher FAMMA à la Tunisie au-delà de `areaServed`. Un nœud
`OnlineStore` avec zone de service, devise (TND), moyens de paiement (COD) et langues
(fr, ar) est directement lisible par une IA.

### G3 — Les réponses ne sont pas rédigées en « format extractible »

Les LLM citent des phrases qui répondent seules, hors contexte. « Livraison gratuite » en
visuel n'est pas extractible ; « La livraison est gratuite partout en Tunisie, dans les
24 gouvernorats, sous 48 à 72 h » l'est. Les pages Livraison / Retours / FAQ doivent
commencer par la réponse, en une phrase autonome, avant tout développement.

### G4 — Pas de `/llms.txt` (404)

Fichier devenu convention pour décrire à un agent ce qu'est le site, ce qu'il vend, à qui,
avec les URL canoniques. Coût : un fichier statique. Non normatif, mais gratuit.

### G5 — Aucune politique explicite pour les crawlers IA

`robots.txt` ne mentionne ni GPTBot, ni ClaudeBot, ni PerplexityBot, ni Google-Extended.
Par défaut ils sont autorisés — ce qui est le bon choix pour être cité. Il faut le rendre
**explicite** pour que ce soit une décision et non un effet de bord.

### G6 — Product schema sans avis ni disponibilité fiable

Le produit expose `Offer` et `UnitPriceSpecification` mais aucun `aggregateRating` ni
`review`. À ne surtout pas fabriquer (§58) : la seule voie légitime est de collecter de vrais
avis après livraison, puis de les baliser.

---

## 5. Plan d'action priorisé

| # | Action | Effort | Impact |
|---|---|---|---|
| 1 | Supprimer / dépublier le produit `prd-1` | 5 min | Bloquant |
| 2 | `noindex` sur panier / commande / mon-compte + sortie du sitemap | 30 min | Bloquant |
| 3 | Désactiver les archives auteur (404 sur `/author/*`) + retirer le sitemap users | 20 min | Bloquant / sécurité |
| 4 | Meta description + og:description, éditables par page depuis l'admin | 2 h | Fort |
| 5 | Schéma FAQPage sur `/faq/` | 1 h | Fort (GEO) |
| 6 | JSON-LD Organization sur toutes les pages + telephone / address / sameAs | 1 h | Fort |
| 7 | Gabarit de titres orienté requête | 1 h | Moyen |
| 8 | Réécrire Livraison / Retours / FAQ en réponses autonomes | 3 h | Fort (GEO) |
| 9 | `hreflang="x-default"` | 15 min | Moyen |
| 10 | `/llms.txt` + directives IA explicites dans robots.txt | 45 min | Moyen (GEO) |
| 11 | Conversion WebP des visuels | 1 h | Moyen (LCP) |
| 12 | Vrais produits + vrais avis collectés après livraison | — | Structurel |

Les points 1 à 3 sont à faire avant toute autre chose : ils ne coûtent presque rien et
retirent des pages nuisibles de l'index.

---

## 6. Ce qui est déjà correct — à ne pas casser

- HTTPS, 200 sur toutes les pages testées, aucun 404 dans la navigation.
- `robots.txt` bien construit (blocage `wc-logs`, `woocommerce_uploads`, `?add-to-cart=`).
- Sitemap index WP natif valide et déclaré dans robots.txt.
- `<link rel="canonical">` auto-référent sur chaque page, y compris `/ar/`.
- `<html lang="fr-FR">` et `<html dir="rtl" lang="ar">` corrects.
- Un seul `<h1>` par page (le double h1 d'accueil a été traité, `functions.php:726`).
- 10 images sur 10 avec attribut `alt` en accueil.
- OG/Twitter et JSON-LD neutralisés si un plugin SEO est activé — bon réflexe anti-doublon.

---

## 7. État d'application — 25/08/2026, en production

Appliqué et vérifié en ligne sur `fammaminnou.tn` :

| # | Action | État |
|---|---|---|
| 1 | Produit `prd-1` | **Non fait** — exclu à votre demande |
| 2 | `noindex` panier / commande / compte + sortie du sitemap | Fait |
| 3 | `/author/*` en 404 + sitemap des comptes retiré | Fait |
| 4 | Meta description et og:description, éditables par page (FR/AR) | Fait — accueil, boutique, à propos, contact, FAQ, livraison |
| 5 | Schéma `FAQPage` sur `/faq/` | Fait — 4 questions balisées |
| 6 | JSON-LD sur toutes les pages, `OnlineStore` + téléphone / e-mail / adresse | Fait |
| 7 | Gabarit de titre du catalogue | Fait — actif dès qu'un produit est publié |
| 8 | Pages Livraison / Retours / FAQ en réponses autonomes | Partiel — voir ci-dessous |
| 9 | `hreflang="x-default"` | Fait |
| 10 | `/llms.txt` + robots des moteurs génératifs autorisés explicitement | Fait |
| 11 | Conversion WebP des visuels | Non fait |
| 12 | Vrais produits et vrais avis | Non fait — dépend de vous |

### Ce qui reste, et pourquoi

- **Page Retours** : son contenu est encore une note de travail (« Points à trancher : délai
  de rétractation, qui paie le retour, délai de remboursement… »). Ces choix sont des
  engagements commerciaux et juridiques : ils vous appartiennent. Une fois tranchés, la
  page se rédige en dix minutes et le schéma la reprend automatiquement.
- **Profils officiels** (`sameAs`) : le champ existe dans « FAMMA → Référencement », laissé
  vide faute d'adresses réelles à déclarer.
- **WebP** : demande de retraiter les visuels de la médiathèque.

### Point de vigilance relevé au passage

Le téléphone déclaré dans les réglages est **56261412**, alors que le numéro noté comme
confirmé pour la marque est **26767491**. C'est ce premier numéro qui part désormais dans
les données structurées et dans `/llms.txt`. À vérifier depuis « FAMMA → Pages ».
