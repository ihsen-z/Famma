# Plan d'intégration du design FAMMA (Accueil / Boutique / Produit)

Source : projet Claude Design `90370726-36bf-4144-824a-394e2db3f56d`.
Références locales : `docs/design-refs/` (`README-handoff.md`, `FAMMA-Boutique.dc.html`,
`FAMMA-Produit.dc.html`). Les maquettes PNG d'origine et `FAMMA Logo.dc.html`
restent dans le projet Design.

Ce plan complète `docs/UX-PLAN.md` (qui reste la source de vérité des décisions
UX déjà tranchées) et `docs/DESIGN-SYSTEM.md` (jetons). Il ne les remplace pas.

---

## 1. Ce que contient la livraison design

Trois pages hi-fi, plus un système de logo. Chaque page est un prototype HTML à
styles inline : toute valeur (couleur, taille, rayon) est lisible directement.
Le moteur de rendu (`support.js`) et le composant `image-slot.js` sont des
outils de prototypage — **ils ne sont pas portés**, comme le demande le handoff.

| Page | Contenu propre |
|---|---|
| Accueil | hero carrousel 4 slides, grille catégories, « Nos nouveautés », « Pourquoi choisir FAMMA ? », bande newsletter, bloc avis + statistiques |
| Boutique | fil d'Ariane, titre + tri + bascule grille/liste, colonne de filtres (catégories, prix, stock, note, promo, marque), bandeau promesses, grille produits, état vide, pagination, bande newsletter claire |
| Produit | fil d'Ariane, galerie + 5 vignettes, titre/note/prix/économie, assurances, quantité + stock, CTA « Commander » + favori, carte WhatsApp, réassurances, bandeau bénéfices, onglets (Description / Caractéristiques / Avis / FAQ / Livraison), bloc avis, produits similaires |

**L'en-tête et le pied de page sont rigoureusement identiques sur les trois
pages** (vérifié ligne à ligne). C'est ce qui rend l'approche « un gabarit
partagé » évidente.

Composants communs :

- **Barre supérieure** marine : 3 promesses (livraison / COD / WhatsApp 24/7) + bascule `FR | عربي`.
- **En-tête collant** blanc : lockup logo, nav 7 entrées, recherche 250px, compte, panier + badge, burger sous 1000px.
- **Pied de page** marine 5 colonnes : marque + sociaux, Navigation, Informations, Aide & support, Paiement à la livraison — puis barre basse copyright + 4 liens légaux.
- **Bouton WhatsApp flottant** en bas.

---

## 2. État réel du site (établi, pas supposé)

| Fait | Conséquence pour le plan |
|---|---|
| Thème actif : `famma-child`, enfant de **Kadence** (`Template: kadence`). Kadence est installé à l'exécution et **non versionné** (`.gitignore`). | Tout ce qui doit survivre à une réinstallation doit vivre dans `famma-child/` ou `famma-core`. |
| `famma-child` ne contient **aucun gabarit** : que `functions.php`, `style.css`, `theme.json`, `assets/`. L'en-tête et le pied de page viennent du constructeur Kadence, donc **de la base de données**. | L'apparence actuelle n'est pas reproductible par `git clone` + `setup.sh`. C'est le vrai motif du choix d'architecture ci-dessous. |
| `famma_child_lockup_markup()` produit **déjà** le lockup exact de la maquette (icône 124×122, `FAMM` + `A` orange, slogan RTL entre deux traits). | Réutilisé tel quel dans le nouvel en-tête et le nouveau pied de page. Rien à redessiner. |
| `famma_child_legal_bar()` injecte le menu légal via `wp_footer`, faute de rangée Kadence. | Déplacé dans `footer.php`, où il redevient une vraie barre basse. |
| `famma_child_sticky_cta()` + `assets/js/sticky-cta.js` : barre d'achat collante sur la fiche produit. | Conservée. La maquette ne la montre pas (elle est desktop) mais elle est mobile-first et déjà validée (D-5, E-32). |
| Emplacements de menu disponibles (Kadence) : `primary`, `mobile`, `footer`. Menu « Principal » = Accueil / À propos / FAQ / Contact. Menu « Pied de page » = 5 pages légales. | La nav de la maquette compte 7 entrées et le pied de page en veut deux colonnes distinctes → il faut créer des entrées et un emplacement supplémentaire. |
| Bilingue par **TranslatePress** : `fr_FR` par défaut sans sous-dossier, `ar` sur `/ar/`. Le sélecteur flotte en bas sur mobile ; `configure-translatepress.php` note déjà « à déplacer dans l'en-tête ». | La bascule `FR | عربي` de la maquette est exactement ce déplacement. Elle doit rendre le sélecteur TranslatePress, **pas** un état JS. |
| Pages existantes : `a-propos`, `contact`, `faq`, `livraison`, `retours`, `conditions-generales`, `confidentialite`, `cookies`. | Les colonnes « Navigation » et « Informations » du pied de page se câblent dessus. Aucune page à inventer. |
| Context7 / WooCommerce : les surcharges se font dans `theme/woocommerce/`, **et un `woocommerce.php` à la racine du thème prend le pas sur `woocommerce/archive-product.php` et neutralise la surcharge**. | Interdiction formelle d'ajouter `woocommerce.php` à `famma-child`. (Le thème hérité `famma-mennou-woocommerce-theme-v1` en contient un — raison de plus pour ne pas le réactiver.) |

---

## 3. Décision d'architecture

### Retenu — gabarits `header.php` / `footer.php` dans `famma-child`

WordPress résout `get_header()` / `get_footer()` par `locate_template()`, qui
regarde **le thème enfant d'abord**. Kadence appelle ces deux fonctions dans
tous ses gabarits, y compris ceux de WooCommerce. Déposer `header.php` et
`footer.php` dans `famma-child` remplace donc l'en-tête et le pied de page
**sur toutes les pages du site d'un coup** — accueil, boutique, fiche produit,
panier, commande, confirmation, pages légales, 404 — ce qui est précisément la
demande.

Pourquoi c'est le bon choix ici :

- **Versionné.** Le constructeur Kadence stocke sa configuration en base ; un gabarit PHP vit dans Git et se relit en revue.
- **Exact.** La barre supérieure à 3 promesses, le lockup avec slogan RTL, le pied de page à 5 colonnes dont une illustration COD : le constructeur Kadence ne les produit pas sans acrobaties.
- **RTL et i18n gratuits.** Propriétés logiques + `esc_html__()` avec le domaine `famma-child`, au lieu de textes saisis dans le personnalisateur (non traduisibles par gettext).

Coûts assumés, à traiter dans le lot D2 :

- Kadence continue de charger le CSS/JS de son en-tête. À neutraliser proprement (déréférencer ses feuilles d'en-tête/pied de page), sans jamais éditer le thème parent.
- Le collant, le menu burger et le compteur de panier sont à réimplémenter — environ 60 lignes de JS, sans dépendance.
- `wp_head()`, `wp_body_open()` et `wp_footer()` doivent être appelés, sinon le suivi serveur de `famma-core`, TranslatePress et WooCommerce cassent.

### Écarté — tout faire au personnalisateur Kadence

Reproductible seulement par capture d'écran, invisible en revue de code,
impossible à traduire par gettext, et incapable de rendre le pied de page
demandé. Ce serait rejouer D-4 (« theme.json inerte : Kadence écrase les
données »).

---

## 4. Écarts entre la maquette et les règles du projet

Ces cinq points sont des conflits réels, pas des détails.

> **Arbitrages rendus par le propriétaire le 15/08/2026** — les quatre
> recommandations ci-dessous ont été retenues :
> **C-1** corriger les glyphes (aplats orange inchangés) · **C-3** polices
> auto-hébergées · **C-5** bande newsletter remplacée par un appel WhatsApp ·
> **architecture** gabarits `header.php` / `footer.php` dans `famma-child`.

### C-1 — Contraste : la maquette échoue à WCAG AA en neuf endroits *(arbitrage requis)*

`docs/DESIGN-SYSTEM.md` et `style.css` ont tranché l'inverse il y a peu
(D-1, E-29) : `#FF7A00` est une couleur de **surface**, jamais de **texte** sur
fond clair, et le texte posé sur l'orange est **bleu**, pas blanc.

Ratios mesurés sur les valeurs exactes de la maquette :

| Endroit dans la maquette | Mesuré | Seuil AA | Verdict |
|---|---|---|---|
| Texte blanc sur CTA orange (hero, carte produit, « Commander », newsletter) | 2,61:1 | 4,5:1 | échec |
| Prix produit orange sur blanc (fiche produit, 38px) | 2,61:1 | 3:1 (grand texte) | échec, même en grand |
| Lien « Voir tous les avis » orange sur blanc | 2,61:1 | 4,5:1 | échec |
| Lien de nav actif orange sur blanc | 2,61:1 | 4,5:1 | échec |
| « Réinitialiser » orange (filtres boutique) | 2,61:1 | 4,5:1 | échec |
| Prix barré `#98A2B3` sur blanc (13px) | 2,58:1 | 4,5:1 | échec |
| Nombre d'avis / placeholder / fil d'Ariane `#8A94A6` sur blanc (12–13,5px) | 3,06:1 | 4,5:1 | échec |
| « En stock » et « Achat vérifié » `#16A34A` sur blanc (12–13,5px) | 3,30:1 | 4,5:1 | échec |
| Pastille « économie » `#C25E00` sur `#FFF1E3` (13,5px) | 3,87:1 | 4,5:1 | échec |
| Texte secondaire `#56617A` sur blanc | 6,20:1 | 4,5:1 | conforme |
| Liens de pied de page `#C4CEDD` sur marine | 10,40:1 | 4,5:1 | conforme |
| Survol orange sur marine | 6,32:1 | 4,5:1 | conforme — à conserver tel quel |

Résolution proposée, **sans toucher au dessin** : garder tous les aplats orange
(surfaces, badges, traits, points du carrousel, étoiles décoratives) et
n'échanger que la couleur des **glyphes** :

- texte sur CTA orange → `var(--famma-blue)` `#0B1F3A` (**6,32:1**) ;
- texte/lien orange sur fond clair → `var(--famma-orange-700)` `#B85300` (4,91:1) ;
- texte orange sur fond marine → `#FF7A00` conservé (6,32:1), via `--famma-accent-text` déjà prévu pour ça ;
- gris trop clairs assombris d'un cran : `#8A94A6` → `#5B6470` (`--famma-muted`, 6,0:1), `#98A2B3` → `#6B7383`, `#16A34A` (texte) → `--famma-success-700` `#157A45` (5,39:1), `#C25E00` → `#9A4A00` sur `#FFF1E3`. Les mêmes teintes restent inchangées quand elles servent de **surface** (pastille verte « Nouveau », coche de filtre).

L'identité visuelle est préservée à distance de lecture ; seule la couleur des
glyphes bouge. **Décision propriétaire** : appliquer cette correction, ou
signer une dérogation écrite à l'accessibilité.

### C-2 — Contenu inventé dans la maquette *(non négociable, aucun arbitrage)*

La règle §58 / §60 interdit toute donnée fictive. La maquette en contient
beaucoup, parce que c'est son rôle de prototype :

- « + 2 500 clients satisfaits », « 4,8/5 », « Basé sur 538 avis » ;
- 3 témoignages signés (Sarah M., Yassine K., Amel J.) et 4 avis « achat vérifié » datés ;
- 5 produits avec prix, remises et nombres d'avis ;
- « +216 XX XXX XXX », `contact@famma-mennou.tn` ;
- 6 catégories, 2 marques, compteurs de filtres.

Résolution : **chaque bloc est construit comme un composant piloté par la
donnée réelle, avec un état vide, et masqué quand la donnée n'existe pas.**
Concrètement : les statistiques se calculent depuis les commentaires WooCommerce
réels ; la section avis disparaît tant qu'il n'y a aucun avis ; les catégories
viennent de `product_cat` ; les produits de `wc_get_products()`. Aucune chaîne
de la maquette n'est recopiée comme contenu.

### C-3 — Polices Google en lien direct *(arbitrage requis)*

La maquette charge Poppins + Noto Kufi Arabic depuis `fonts.googleapis.com`.
Trois problèmes : performance sur mobile tunisien (deux requêtes bloquantes),
vie privée (l'IP du visiteur part chez Google — sujet sensible côté RGPD), et
la décision D-9 du plan UX est encore ouverte.

Résolution proposée : **auto-héberger** les deux familles en `woff2` dans
`famma-child/assets/fonts/`, avec `font-display: swap` et un `preload` sur les
graisses réellement utilisées (Poppins 400/500/600/700, Noto Kufi 400/700).
Cela clôt D-9 par la même occasion.
**Décision propriétaire** : valider Poppins + Noto Kufi Arabic comme couple
définitif, ou en rester à la pile système.

### C-4 — Empilement des éléments flottants

La maquette pose le WhatsApp à `z-index: 50` et l'en-tête à `40`, donc le
bouton flottant passe **devant** l'en-tête. Le projet a délibérément tranché
l'inverse (D-6, E-27) et possède une échelle unique de jetons : `--z-float: 290`
(WhatsApp) < `--z-sticky-cta: 300` (CTA d'achat) < `--z-header: 400`.

Résolution : **l'échelle du projet gagne.** Aucun `z-index` littéral dans le
nouveau code. Le WhatsApp reste remonté de `--sticky-cta-h` sur la fiche produit
pour ne pas recouvrir le CTA d'achat.

### C-5 — Newsletter sans destinataire *(arbitrage requis)*

Le formulaire apparaît sur l'accueil et sur la boutique. Aucun service
d'emailing n'est configuré dans le projet, et un champ qui ne mène nulle part
est pire que pas de champ.

Options : (a) omettre la bande newsletter pour l'instant et garder l'espace ;
(b) la brancher sur un fournisseur réel ; (c) la remplacer par un appel WhatsApp,
qui est de toute façon le canal du COD tunisien. **Recommandation : (c)**, puis
(b) quand un fournisseur sera choisi. **Décision propriétaire.**

### Écarts mineurs, tranchés sans arbitrage

| Écart | Résolution |
|---|---|
| Conteneur 1240px (maquette) vs `--container-max: 1200px` (jeton) | Le jeton passe à **1240px**. Une seule valeur, dans `tokens.css`. |
| Nuances de gris légèrement différentes (`#E7EAF0` vs `--famma-line: #E6E9EE`, `#F4F6F9` vs `--famma-bg: #F7F8FA`) | Les valeurs de la maquette gagnent ; les jetons existants sont mis à jour. Aucune valeur littérale ailleurs. |
| Point de rupture JS à 1000px | Implémenté en **CSS pur** (`@media (min-width: 1000px)`), comme le recommande le handoff lui-même. |
| Nav maquette : Promotions, Nouveautés, Catégories | N'existent que si une archive réelle les sert. Sinon elles sortent du menu — pas de lien mort. |
| Slugs `/shop/`, `/cart/` sur un site français (E-37) | Traité dans le lot D3, en même temps que le fil d'Ariane de la boutique. |

---

## 5. Le plan, lot par lot

Chaque lot est livrable seul, testable seul, et fait l'objet d'un commit.
L'ordre compte : D1 et D2 conditionnent tout le reste.

| Lot | État |
|---|---|
| D1 — socle (jetons, polices) | **livré et vérifié sur la pile** |
| D2 — en-tête et pied de page | **livré et vérifié sur la pile** |
| D3 — boutique | **livré et vérifié sur la pile** |
| D4 — fiche produit | **livré et vérifié sur la pile** |
| D5 — accueil | **livré et vérifié sur la pile** |
| D6 — menus, slugs et contenus | **livré et vérifié** (reste le retrait du produit de test) |
| D7 — recette d'accessibilité | **passée** (contraste, focus, cibles, RTL, mobile) |

### Recette passée sur D1 + D2

`php -l` sur les 15 fichiers PHP : aucune erreur. `phpcs` au ruleset WordPress
sur `famma-child`, `mu-plugins` et `scripts/create-menus.php` : **zéro erreur,
zéro avertissement**. `wp-content/debug.log` vide après le parcours complet.

| Page | Code | `<main>` | En-tête | Pied de page | Erreurs PHP |
|---|---|---|---|---|---|
| `/` | 200 | 1 | 1 | 1 | 0 |
| `/shop/` | 200 | 1 | 1 | 1 | 0 |
| `/product/teste11/` | 200 | 1 | 1 | 1 | 0 |
| `/cart/` (panier rempli) | 200 | 1 | 1 | 1 | 0 |
| `/checkout/` (panier rempli) | 200 | 1 | 1 | 1 | 0 |
| `/faq/`, `/a-propos/` | 200 | 1 | 1 | 1 | 0 |
| `/ar/`, `/ar/shop/` | 200 | 1 | 1 | 1 | 0 |
| 404 | 404 | 1 | 1 | 1 | 0 |

Le badge du panier passe bien à 1 après ajout, et `/ar/` rend
`<html dir="rtl" lang="ar">` sans feuille RTL dédiée.

**Comportement « aucune donnée inventée » confirmé au rendu** : la colonne
« Aide & support » et les icônes sociales ne sont pas rendues (rien n'est
configuré dans `.env`), et la troisième promesse de la barre supérieure
disparaît faute de numéro WhatsApp. Le pied de page tombe à quatre colonnes,
sans trou.

### Trois défauts trouvés par la recette, corrigés

1. **Aucun repère `<main>`** — j'avais supposé que Kadence en posait un. Vérifié
   sur la page rendue : son conteneur est un `div id="main"` sur une page
   ordinaire, mais ses gabarits WooCommerce, eux, posent bien un `<main>`. Sans
   arbitrage on obtenait zéro repère sur le site, ou deux sur la boutique et la
   fiche produit. D'où `famma_child_content_tag()`.
2. **Langue active jamais mise en évidence** — `trp_get_current_language()`
   n'existe pas dans cette version. Remplacé par `get_locale()`, que
   TranslatePress bascule déjà.
3. **Le sélecteur de langue ne permettait pas de revenir au français** — le
   réglage `force-language-to-custom-links` réécrivait le lien « FR » vers
   `/ar/`. Corrigé par le point d'extension `trp_force_custom_links`. Piège
   découvert au passage : **un attribut HTML sans valeur** (`data-no-translation`
   nu) fait perdre l'attribut suivant au parseur `simple_html_dom` embarqué par
   TranslatePress — le lien perdait sa `class` et l'exception ne s'appliquait
   plus.

### Recette passée sur D3

`phpcs` reste à **zéro erreur, zéro avertissement** sur `famma-child`,
`mu-plugins` et les deux scripts. `debug.log` vide après le parcours complet,
tri et filtres compris.

| Page | Code | `<main>` | Erreurs PHP |
|---|---|---|---|
| `/shop/` | 200 | 1 | 0 |
| `/shop/?orderby=price` | 200 | 1 | 0 |
| `/shop/?min_price=9999&max_price=99999` (aucun résultat) | 200 | 1 | 0 |
| `/ar/shop/` | 200 | 1 | 0 |

L'état vide distingue bien les deux cas : avec filtres actifs il affiche
« Aucun produit ne correspond » et propose de les réinitialiser ; sans filtre,
il annonce que la boutique se remplit et renvoie vers WhatsApp — jamais un
produit fictif.

Les widgets de filtre se masquent seuls tant qu'il n'y a rien à filtrer : avec
un seul produit de test, seule la colonne « Catégories » s'affiche. C'est le
comportement de WooCommerce, et il va dans le sens de la règle §58.

### Recette passée sur D4

`phpcs` : zéro erreur, zéro avertissement sur les 16 fichiers analysés.
`debug.log` vide. Un seul `<main>`, aucune erreur PHP sur la fiche.

Ordre du résumé vérifié dans le HTML rendu, conforme à la maquette :
**titre · accroche · prix · économie · réassurance · formulaire d'achat**.
La note et la carte WhatsApp sont absentes du rendu — respectivement parce que
le produit n'a aucun avis et parce qu'aucun numéro n'est configuré. C'est le
comportement voulu, pas un oubli.

Onglets rendus : **Description · Livraison · Avis (0)**. L'onglet Livraison
tire son contenu de la page `/livraison/` : une seule source, la fiche suit
automatiquement toute mise à jour de la politique.

Le fil d'Ariane manquait totalement sur la fiche produit avant ce lot
(`grep -c woocommerce-breadcrumb` = 0) ; il est désormais rendu.

**Deux écarts assumés par rapport à la maquette**, tous deux motivés :

- **Le prix reste bleu**, alors que la maquette le met en orange 38px.
  `#FF7A00` en texte sur blanc vaut 2,61:1 et échoue **même** le seuil
  « grand texte » de 3:1. Le prix est l'information la plus lue de la page.
- **Le bouton favori est omis** : il n'existe aucune liste d'envies dans le
  projet. Un cœur qui ne mémorise rien est un bouton qui ment.

### Recette passée sur D5

12 pages contrôlées : un `<main>`, un `<h1>`, un en-tête, un pied de page,
zéro erreur PHP partout. `debug.log` vide. `phpcs` propre sur tout le code du
thème, les mu-plugins et les scripts.

**Le gabarit compose autour du contenu, il ne l'écrase pas.** `front-page.php`
rend le héros, les catégories et les nouveautés, **puis** le contenu éditorial
de la page « Accueil » (COD, FAQ, WhatsApp — écrit par le propriétaire dans
l'éditeur), **puis** les raisons de choisir FAMMA, les avis et l'appel
WhatsApp. Un gabarit qui remplace le contenu de sa page transforme
l'administration en décor.

`scripts/create-homepage.php` ne génère plus le bloc héros ni la bande de
promesses : le gabarit les porte désormais, et les laisser en produirait deux,
dont un sans carrousel.

Comportement observé avec le catalogue actuel (un produit sans image, aucun
avis) : le héros reste un **panneau fixe** — pas de points, pas de défilement,
puisqu'il n'y a pas deux visuels à montrer ; la section « Ils nous font
confiance » n'existe pas ; la bande WhatsApp non plus. Les catégories et les
nouveautés s'affichent. Aucun chiffre inventé : ni « + 2 500 clients », ni
« 4.8/5 », ni témoignage signé.

Les agrégats passent par des transients de 15 minutes, purgés à chaque
enregistrement de produit ou d'avis — sinon une première fiche publiée
resterait invisible un quart d'heure, exactement au moment où le propriétaire
rafraîchit son accueil.

### Défaut trouvé par la recette D5, corrigé

**L'accueil s'est retrouvé sans aucun `<h1>`.** Le titre vivait dans le bloc
héros de l'éditeur, que ce lot a retiré ; et le titre du thème est déjà détaché
sur cette page par `famma_child_remove_front_page_hero()`. Le mot-symbole du
héros porte désormais le `<h1>`, complété pour les lecteurs d'écran et les
moteurs par la baseline française — sans rien changer au dessin.

## 5 bis. Conformité mesurée de l'habillage à la maquette

Contrôle mené dans le navigateur, en lisant les styles calculés du site rendu
et en les confrontant aux valeurs de `docs/design-refs/`. **30 des 32 valeurs
mesurées sont identiques**, les deux dernières à un pixel d'arrondi près.

| Mesure | Maquette | Site |
|---|---|---|
| Hauteur de la barre supérieure | 41px | **41px** |
| Hauteur de l'en-tête | 70px | **70px** |
| Conteneur | 1240px | 1240px |
| Barre supérieure : corps · marge · écart d'item · icône | 13px · 11px · 9px · 17px | identiques |
| Lockup : icône · mot-symbole · baseline · couleur | 42×41 · 25px · 9px · `#46536B` | identiques |
| Traits orange de la baseline | 12×2px | 12×2px |
| Navigation : corps · écart | 14,5px · 22px | identiques |
| En-tête : marge · écart · filet | 14px · 22px · `#EDEFF3` | identiques |
| Recherche : largeur · marge · corps · hauteur | 250px · 9/14px · 13,5px · 38px | 250 · 9/14 · 13,5 · **39px** |
| Actions : écart | 16px | 16px |
| Badge panier : corps · rayon | 10,5px · 9px | identiques |
| Pied de page : corps · couleur · marges · gouttière | 13px · `#C4CEDD` · 44/30px · 32px | identiques |
| Colonnes : titre · liens · écart · interligne du texte | 15px · 13px · 11px · 1,65 | identiques |
| Barre basse : corps · écart des liens | 12px · 22px | identiques |

### Trois corrections nécessaires pour y arriver

1. **L'échelle typographique.** La maquette compose l'habillage sur une
   demi-échelle (13 · 13,5 · 14,5 · 15px) que j'avais initialement rabattue sur
   l'échelle éditoriale du projet (12 · 14 · 16px). L'habillage a désormais ses
   propres jetons `--fs-chrome-*` : il est dessiné, pas rédigé, et sa fidélité
   compte davantage que son alignement sur la hiérarchie de titres.
2. **Les cibles tactiles.** La maquette dessine des cibles de 29 à 38px, le
   projet en impose 48 (règle 9). Les deux ont raison, mais pas pour le même
   appareil. La contrainte tactile s'applique désormais **sous 1000px**, le
   dessin de la maquette au-dessus : 48px protègent le doigt, 38px tiennent la
   barre à sa hauteur dessinée.
3. **Une spécificité CSS de Kadence.** Le thème parent style les champs via
   `input[type="search"], …`, de spécificité (0,1,1), qui l'emportait sur
   `.famma-search__input` (0,1,0). Son remplissage de `0.4em` poussait la barre
   de recherche à 52px. Corrigé par un sélecteur à deux classes — par
   précision, jamais par `!important`.

### Menu principal — les sept entrées de la maquette

`Accueil · Boutique · Catégories · Promotions · Nouveautés · À propos · Contact`

Chaque entrée pointe vers une destination réelle : « Catégories » vise la
boutique et reçoit les vraies catégories produit en sous-menu ; « Nouveautés »
est la boutique triée par date ; « Promotions » utilise `?on_sale=1`, servi par
un filtre de requête appuyé sur `wc_get_product_ids_on_sale()` — la fonction du
cœur qui fait autorité sur ce qu'est une promotion. Sans produit en promotion,
la page affiche l'état vide plutôt que le catalogue entier : un visiteur qui
clique « Promotions » et voit toute la boutique croit à une promotion générale.

### Ce qui reste différent de la maquette, et pourquoi

Trois écarts subsistent, tous dus à des **données manquantes**, aucun à du code :

| Écart | Cause | Ce qu'il faut fournir |
|---|---|---|
| 2 promesses en barre supérieure au lieu de 3 | pas de numéro WhatsApp | `FAMMA_WHATSAPP_NUMBER` |
| 4 colonnes de pied de page au lieu de 5 | « Aide & support » sans donnée | `FAMMA_CONTACT_*` |
| Aucune icône sociale, aucun bouton WhatsApp flottant | aucune URL configurée | `FAMMA_SOCIAL_*` |
| 3 liens légaux au lieu de 4 | la page « Mentions légales » n'existe pas | le texte légal |

C'est le comportement voulu : la maquette montrait `+216 XX XXX XXX` et quatre
comptes sociaux, qui sont du remplissage de prototype. Renseignez `.env`,
relancez `docker compose up -d`, et les quatre lignes apparaissent sans toucher
au code.

## 5 ter. Lots D6 et D7 — URL françaises et recette d'accessibilité

### D6 — les URL se prononcent enfin

`scripts/francise-slugs.php` renomme les pages et les préfixes de réécriture.
Sur une boutique COD tunisienne, l'URL est **lue** : copiée dans WhatsApp,
dictée au téléphone, collée dans une publicité. Elle doit se prononcer.

| Avant | Après |
|---|---|
| `/shop/` · `/cart/` · `/checkout/` · `/my-account/` | `/boutique/` · `/panier/` · `/commande/` · `/mon-compte/` |
| `/product/` · `/product-category/` · `/product-tag/` | `/produit/` · `/categorie/` · `/etiquette/` |
| Titres « Shop », « Cart », « Checkout », « My account » | « Boutique », « Panier », « Commande », « Mon compte » |

**Les anciennes adresses répondent 301**, chaîne de requête comprise — un lien
publicitaire garde ses UTM, donc l'attribution de campagne survit. WordPress ne
redirige que l'ancien slug d'un *article* (`wp_old_slug_redirect()` lit la
variable `name`) ; une **page** est résolue par `pagename` et n'est donc pas
couverte. Constaté à l'exécution : `/shop/` et `/cart/` répondaient 404. D'où
`wp-content/mu-plugins/famma-legacy-urls.php`, qui ne fige pas les nouvelles
adresses — il nomme les anciennes et demande à WooCommerce l'URL courante.

Opération faite maintenant à dessein : le catalogue ne contient encore aucun
produit réel. Le même changement après la mise en ligne demanderait un plan de
redirections complet, le préfixe produit n'ayant aucun mécanisme de repli.

### D7 — recette d'accessibilité menée sur le rendu

Audit de contraste automatisé, exécuté dans le navigateur sur la page réelle :
chaque élément portant du texte, sa couleur calculée, le fond effectif remonté
dans l'arbre, le seuil WCAG selon la taille et la graisse.

| Page | Éléments testés | Échecs |
|---|---|---|
| Accueil | 89 | **0** |
| Boutique | 59 | **0** |
| Fiche produit | 79 | **0** |

Focus clavier : les 40 éléments focusables de l'accueil sont couverts par une
règle `:focus-visible` portant l'anneau bleu et son halo blanc.
Cibles tactiles à 375px : burger 48×48, actions d'en-tête 48×48, liens de pied
de page 44px de haut. Les seuls éléments sous 24px sont des liens de fil
d'Ariane — texte en ligne, explicitement exempté par WCAG 2.2 (2.5.8).
RTL : `/ar/` inverse la mise en page sans feuille dédiée, aucun défilement
horizontal. `debug.log` vide sur les onze pages contrôlées.

### Trois défauts que seule la mesure a révélés

1. **Le logo du pied de page était invisible.** `.famma-lockup` fixe
   `color: var(--famma-blue)` sur lui-même ; cette couleur l'emporte sur celle
   héritée du lien parent. Posé sur le marine du pied de page : marine sur
   marine, **ratio 1:1**, icône comprise puisqu'elle suit `currentColor`. Une
   relecture de code ne l'aurait pas montré, et l'œil ne cherche pas un logo là
   où il n'y a rien.
2. **Le `<h1>` du héros d'accueil était invisible** pour la même raison : devenu
   un titre, il recevait la couleur de titre du thème — marine sur marine,
   ratio 1,1. Le nom de la marque, en haut de la page d'accueil.
3. **Deux fils d'Ariane sur la fiche produit.** Le nôtre
   (`.woocommerce-breadcrumb`) et celui de Kadence (`.kadence-breadcrumbs`). Un
   contrôle qui ne cherchait que la classe WooCommerce ne pouvait pas le voir :
   les deux n'ont pas le même nom. Celui de Kadence est retiré du registre de
   hooks — pas masqué en CSS, un fil d'Ariane invisible restant lu par les
   lecteurs d'écran et dupliqué dans les données structurées.

Un quatrième point, plus discret : le prix barré mesurait **4,49:1** sur la
fiche produit là où le jeton en promettait 4,77 — WooCommerce y applique une
légère transparence. Le jeton passe à 5,52:1 : un seuil ne doit pas dépendre
d'un `opacity` qu'une mise à jour peut changer.

### Reste à faire, avec l'accord du propriétaire

Le produit de test `teste11` (E-36) est toujours en ligne. **Sa suppression
n'est pas faite ici** : effacer une donnée est irréversible et relève d'une
décision, pas d'un lot. À supprimer avant la mise en production, en même temps
que la catégorie « Uncategorized » qui apparaît dans le fil d'Ariane et dans le
sous-menu « Catégories ».

## 5 quater. Boutique — conformité complète à la maquette

### Catégories créées

Les cinq rayons du design existent : **Maison & Cuisine · Électronique ·
Auto & Moto · Beauté & Santé · Accessoires** (`create-shop-categories.php`,
idempotent). Une catégorie est une **structure de catalogue**, pas une donnée
inventée : elle n'affirme rien sur un produit, un avis ou un chiffre. Le
compteur affiché reste celui de WooCommerce — une catégorie vide affiche zéro,
ce qui est exact.

`hide_empty` est mis à zéro sur le widget : les rayons doivent se voir dès le
premier jour, sinon la colonne de filtres reste vide jusqu'au premier produit.

« Nouveautés » n'est volontairement **pas** une catégorie : dans la maquette
c'est une tuile vers les derniers produits publiés, pas un rayon. Elle est
servie par `?orderby=date`. En faire une catégorie obligerait à y ranger chaque
produit puis à l'en sortir à la main.

`Uncategorized` est renommée « Sans rayon » mais **pas supprimée** : effacer un
terme déplace les produits qu'il contient, ce qui est irréversible.

### Widgets de filtre — les six blocs de la maquette

WooCommerce n'en fournit que quatre. **La disponibilité et les promotions
n'existent pas** en widget classique : `inc/shop-widgets.php` les écrit.

| Bloc | Origine |
|---|---|
| Catégories · Prix · Note · Marque | widgets WooCommerce natifs |
| **Disponibilité** (En stock / Rupture) | écrit ici — `?stock_status=` |
| **Promotions** (En promotion) | écrit ici — `?on_sale=1` |

Aucun ne réimplémente de requête : chacun pose un paramètre d'URL, et
`woocommerce_product_query` contraint la requête existante. Tri, pagination et
compte de résultats continuent de fonctionner ensemble.

Rendus en **liens**, pas en cases à cocher : un filtre est une navigation, pas
une saisie. Le lien fonctionne sans JavaScript, se partage, s'ouvre dans un
onglet et revient avec le bouton « précédent ».

Prix, Note et Marque ne s'affichent pas encore : ces widgets se masquent
d'eux-mêmes tant qu'il n'y a rien à filtrer. C'est le comportement de
WooCommerce, et il va dans le sens du §58.

### Repliage des blocs, et bouton favori

Le chevron de la maquette n'est dessiné **que** lorsque le repliage existe
vraiment : `shop-view.js` transforme chaque titre en bouton, et le CSS ne pose
le chevron que sur `.is-collapsible`. Une flèche qui ne replie rien serait une
promesse d'interface non tenue.

Le cœur des cartes est **réellement fonctionnel** : la sélection vit dans
`localStorage`, survit à la navigation, sans compte ni donnée envoyée au
serveur. Je l'avais d'abord omis au motif qu'un cœur sans liste d'envies ment ;
il ne ment plus. Le bouton est **frère** du lien produit, jamais dedans — un
`<button>` dans un `<a>` est du HTML invalide.

### Trois doublons que seul le rendu a révélés

1. **Deux sélecteurs de tri.** Retirer `woocommerce_catalog_ordering` du hook ne
   suffisait pas : Kadence n'utilise pas ce rappel, il rend sa propre rangée
   `.kadence-shop-top-row`. Détachée — mais elle emportait le compte de
   résultats, remis à sa place d'origine.
2. **Deux `<h1> Boutique`.** Une sonde posée à l'exécution a établi que le titre
   ne venait pas de `output_product_above_title` (bien détaché) mais du
   **conteneur de contenu** de Kadence. Celui-ci est retiré sur les archives
   produit seulement, où le gabarit fournit sa propre mise en page ; le panier,
   la commande et le compte gardent l'enveloppe du parent, qui les porte.
   `header.php` reprend alors le `<main>` — d'où l'ajustement de
   `famma_child_content_tag()`.
3. **Le cœur actif échouait le contraste.** `#FF7A00` sur le blanc de la
   pastille vaut 2,61:1, sous le seuil 3:1 des éléments graphiques porteurs de
   sens (WCAG 1.4.11). Passé en `--famma-orange-700` (4,91:1). L'état ne repose
   de toute façon pas sur la seule couleur : le cœur passe de contour à plein.

### État vérifié de la boutique

| Élément | Attendu | Rendu |
|---|---|---|
| `<h1>` · `<main>` · fil d'Ariane · tri | 1 chacun | **1 chacun** |
| Blocs de filtre repliables | oui | 3, tous repliables |
| Catégories listées | 5 | **5** |
| Lignes cochables (stock, promo) | 3 | **3**, compteurs réels |
| Bascule grille/liste · promesses · compte de résultats | présents | présents |
| Favori mémorisé | oui | vérifié : écrit puis retiré de `localStorage` |
| Contraste | 0 échec | **0 échec / 49 éléments** |
| Erreurs PHP · `debug.log` | 0 | 0, journal vide |

### Boutique — mesure au pixel avec un catalogue réel

Contrôle mené avec trois produits publiés, images et prix barrés compris.
**22 des 22 valeurs mesurées sont identiques à la maquette.**

| Mesure | Maquette | Site |
|---|---|---|
| Fil d'Ariane | 13px | 13px |
| Titre : échelle · interlettrage | `clamp(28px, 3.4vw, 36px)` · −0,01em | identiques |
| En-tête : gouttière | 18px | 18px |
| Tri : corps · largeur mini · rayon | 13,5px · 150px · 8px | identiques |
| Bascule d'affichage | 38×38 | 38×38 |
| Gabarit : colonne · gouttière | 260px · 24px | identiques |
| Carte de filtre : marge · rayon | 20px · 12px | identiques |
| Filtres : titre · ligne · compteur | 14,5 · 13,5 · 12,5px | identiques |
| Promesses : marge | 16px 18px | identique |
| Résultats · grille : gouttière | 18px | 18px |
| Compte de résultats | 13,5px | 13,5px |
| Carte : nom · prix · prix barré | 14 · 19 · 13px | identiques |
| Badge : corps · rayon | 11,5px · 5px | identiques |
| Bouton : corps · rayon | 13,5px · 7px | identiques |
| Cœur favori | 30×30 | 30×30 |

Contraste : **0 échec sur 92 éléments**. Vue liste fonctionnelle. Sur mobile :
filtres repliés au chargement, bouton 335×48, cœur 48×48, grille à une colonne,
aucun défilement horizontal, `<select>` à 16px contre le zoom iOS.

### Colonne de filtres — réécrite d'après la maquette

Deux écarts **structurels**, invisibles dans une comparaison valeur par valeur
mais évidents côte à côte avec la capture :

1. **La maquette ne pose qu'UNE carte**, avec six sections séparées par un
   filet interne de 1px. J'en posais six, donc six bordures et six ombres là où
   le dessin n'en veut qu'une. La barre latérale rend désormais une carte
   `.famma-filters` (en-tête « Filtres » + « Réinitialiser »), chaque widget
   étant une **section** à l'intérieur.
2. **Il manquait « Toutes les catégories »**, l'entrée qui porte le total du
   catalogue et l'état actif par défaut, et le **curseur de prix en dinars**.

Les cinq filtres sont désormais écrits par le thème (`inc/shop-widgets.php`),
sur une classe de base commune :

| Section | Paramètre d'URL | Lu par |
|---|---|---|
| Catégories | `product_cat` | WooCommerce |
| Prix | `max_price` | WooCommerce |
| Disponibilité | `stock_status` | `woocommerce_product_query` (thème) |
| Note moyenne | `rating_filter` | WooCommerce |
| Promotions | `on_sale` | `woocommerce_product_query` (thème) |

**Aucun ne réimplémente de requête.** Les widgets natifs produisaient chacun un
balisage différent — liste hiérarchique, curseur jQuery UI à deux poignées — et
les ramener au dessin aurait demandé plus de CSS de correction que de code de
rendu. Toutes les lignes passent maintenant par un rendu unique : deux
balisages divergeraient à la première retouche.

Le curseur de prix est un `<form>` GET complet : sans JavaScript le champ reste
utilisable et son bouton d'envoi, visible au clavier, valide la sélection. Le
script se contente d'afficher la valeur en direct et de soumettre au
relâchement, pour ne pas recharger à chaque pixel parcouru.

**Une section vide n'est pas rendue du tout**, pas même son titre : « Note
moyenne » sans un seul produit noté afficherait un intitulé suivi de rien.

Filtres vérifiés sur le catalogue réel, y compris combinés :

| URL | Produits |
|---|---|
| `/boutique/` | 3 |
| `?product_cat=accessoires` | 2 |
| `?product_cat=electronique` | 1 |
| `?max_price=50` | 1 |
| `?on_sale=1` | 3 |
| `?product_cat=accessoires&on_sale=1` | **2** — les filtres se cumulent |
| `?rating_filter=4` | 0 — aucun produit noté |

Conformité de la carte : **20 valeurs sur 21** au premier passage, 21/21 après
correction de la marge (20px et non 24). Contraste : **0 échec sur 98 éléments**
en desktop, **0 sur 63** en mobile.

### ⚠ C-1 renversé — fidélité stricte demandée le 16/08/2026

Le propriétaire a demandé la fidélité stricte à la maquette, couleurs
comprises. Deux jetons ont donc été basculés dans `tokens.css` :

| Jeton | Avant (C-1) | Maintenant | Contraste |
|---|---|---|---|
| `--famma-on-orange` | `--famma-blue` | `--famma-white` | 6,32:1 → **2,61:1** |
| `--famma-accent-text` | `--famma-orange-700` | `--famma-orange` | 4,91:1 → **2,61:1** sur fond clair |

Effet mesuré sur la boutique : **14 échecs de contraste sur 116 éléments**, tous
à 2,61:1 — libellé de catégorie actif, « Réinitialiser », texte du badge de
remise, libellé du bouton d'ajout au panier, étoiles des filtres. Avant la
bascule : 0 échec sur 98.

Les neuf usages d'orange textuel des feuilles passent désormais par
`var(--famma-accent-text)` : **modifier ce seul jeton rétablit la conformité
partout**, sans toucher à une seule règle.

L'orange sur le marine du pied de page reste conforme (6,32:1) : c'est le même
jeton, mais le fond change tout.

### Carte produit — détail par détail

**28 valeurs sur 29** au premier passage, 29/29 après correction de la marge du
badge (4px 9px et non 4px 8px).

| Détail | Maquette | Site |
|---|---|---|
| Carte : bordure · rayon · ombre au survol | `#E7EAF0` · 12px · `0 10px 24px rgba(11,31,58,.1)` | identiques |
| Image : hauteur · fond · cadrage | 178px · `#F4F6F9` · `cover` | identiques |
| Badge : corps · rayon · marge · couleur | 11,5px · 5px · 4/9px · blanc | identiques |
| Badge « Nouveau » | vert `#16A34A` | identique |
| Cœur favori | 30×30, blanc à 92 % | identique |
| Corps : marge · gouttière | 14/14/16px · 9px | identiques |
| Nom : corps · graisse · hauteur mini | 14px · 500 · 39px | identiques |
| Note : écart · étoiles · nombre d'avis | 7px · 13px orange · 12px | identiques |
| Prix : écart · corps · prix barré | 9px · 19px · 13px | identiques |
| Bouton : marge · écart · rayon · corps · icône | 11/16px · 8px · 7px · 13,5px · 15px | identiques |

Trois éléments manquaient et ont été ajoutés :

1. **Le nombre d'avis** à côté des étoiles. `woocommerce_template_loop_rating`
   n'affiche que les étoiles ; la maquette pose « ★★★★★ (128) ». C'est ce
   nombre qui rend la note crédible — cinq étoiles sur un avis ne valent pas
   cinq étoiles sur cent. Rien n'est affiché sans avis : une rangée d'étoiles
   vides ferait croire à une mauvaise note plutôt qu'à une absence de note.
2. **Le badge vert « Nouveau »**, absent de WooCommerce. Il se lit sur la date
   de publication — ce n'est pas une donnée inventée — et ne s'affiche pas sur
   un produit déjà en promotion : deux badges dans le même coin d'image seraient
   illisibles, et la remise est le message qui vend.
3. **L'icône panier** dans le bouton, insérée par filtre sans réécrire le lien :
   ses classes AJAX et ses attributs `data-product_id` portent tout le
   comportement d'ajout au panier.

Le badge de remise passe de « -50 % » à « -50% » : la forme de la maquette, et
celle qu'on lit sur une étiquette. L'espace insécable français allongeait le
badge d'un tiers dans un coin déjà étroit.

Le survol ne déplace plus la carte (`transform` retiré) : la maquette ne pose
qu'une ombre, et le soulèvement faisait sauter la grille au survol accidentel.

### Fiche produit — mesure au pixel

Passée de **8 valeurs conformes sur 25** à **20/20**.

| Mesure | Maquette | Avant | Après |
|---|---|---|---|
| Fil d'Ariane | 13px | 14 | **13** |
| Gouttière des deux colonnes | 34px | 32 | **34** |
| Galerie : rayon | 14px | 12 | **14** |
| Badge : corps · marge · rayon | 15px · 7/14px · 8px | 14 · 8/12 · 8 | **identiques** |
| Titre : interligne · interlettrage | 1,22 · −0,01em | 1,15 · normal | **identiques** |
| Étoiles du résumé | 17px, interlettrage 2px | 14 | **17** |
| Accroche | 14,5px, interligne 1,65 | 16 · 1,5 | **identiques** |
| Prix : couleur · écart · prix barré | orange · 14px · 19px | bleu · 12 · 16 | **identiques** |
| CTA : rayon · marge | 9px · 16/24px | 8 · 12/24 | **identiques** |
| Bénéfices : rayon · marge · gouttière | 14px · 22px · 22px | 12 · 16 · 16 | **identiques** |
| Onglets : marge · panneau | 15/22px · 26px | 7,65/15,3 · 0 | **identiques** |

**Trois éléments manquaient**, ajoutés :

1. **Ligne « ✓ En stock »** contre le sélecteur de quantité. WooCommerce
   n'affiche sa ligne `.stock` que si le stock est géré ou épuisé : sur un
   produit vendu sans gestion de stock, le client n'avait aucune confirmation
   de disponibilité au moment d'ajouter au panier. L'information vient de
   `is_in_stock()`, pas d'une affirmation.
2. **Bouton favori 56×56** à côté du CTA — le même composant que sur les
   cartes, donc la même sélection mémorisée. Un cœur qui oublierait ce qu'on
   lui confie d'une page à l'autre serait pire qu'un cœur absent.
3. **Badge vert « Nouveau »** en tête du résumé, sur la même règle que la
   carte : date de publication, et jamais sur un produit déjà en promotion.

**Une spécificité de plus sur les onglets.** Kadence les stylise avec
`.woocommerce div.product .woocommerce-tabs ul.tabs li a` — exactement la même
spécificité que notre sélecteur sans `div.product`, et **sa feuille est chargée
après la nôtre**. À égalité, la dernière gagne : nos 15/22px étaient écrasés par
ses `0.5em/1em`, soit 7,65/15,3px. Un cran de plus a suffi, sans `!important`.

Mobile (375px) : une colonne, CTA 285×48, favori 56×56, quantité 80×48, barre
d'achat collante à 65px, aucun défilement horizontal.

⚠ Contraste : **14 échecs sur 105 éléments**, dont le prix à **2,46:1** — c'est
le coût du renversement de C-1, mesuré et assumé. Le prix est l'information la
plus lue de la page.

### La carte produit est une feuille partagée

`assets/css/product-card.css`, chargée partout où une carte apparaît :
**accueil, boutique, archives de catégorie, produits liés d'une fiche.**

Ces règles vivaient dans `shop.css`, chargée sur la seule boutique. Constaté au
rendu de l'accueil : l'icône du bouton s'affichait à sa taille naturelle — un
chariot occupant toute la hauteur du bouton — les badges s'étiraient sur toute
la largeur, les boutons ne s'alignaient pas et le libellé restait en capitales.
Aucun de ces défauts n'existait sur la boutique : c'était le même code, mais il
n'atteignait qu'une page sur trois.

La portée est unique : `ul.products.famma-cards`. La classe est posée par
`inc/shop.php` sur les listes de WooCommerce (boutique, catégories, produits
liés) et par `inc/home.php` sur celle de l'accueil. Un seul crochet, un seul
dessin — au lieu de trois copies vouées à diverger.

Les règles de carte ont été retirées de `shop.css`, `home.css` et
`product.css` : elles n'existent plus qu'à un seul endroit. `shop.css` ne
conserve que ce qui est propre à la boutique — colonnes de la grille, vue
liste, pagination, filtres.

Vérifié après extraction, accueil et boutique donnent les mêmes mesures :

| Mesure | Accueil | Boutique |
|---|---|---|
| Hauteur d'image | 178px | 178px |
| Badge | 65×24 (23 %) | 45×24 (21 %) |
| Icône chariot | 15×15 | 15×15 |
| Hauteur de bouton · graisse | 40px · 600 | 40px · 600 |
| Boutons alignés | **oui** | **oui** |
| Flèches de Kadence visibles | 0 | 0 |

`product-card.css` n'est pas chargée sur les pages sans carte — vérifié sur
`/faq/`.

### Trois défauts de carte venus du thème parent

Signalés sur capture par le propriétaire, diagnostiqués sur le rendu — aucun
n'était visible dans une comparaison de valeurs, tous venaient de règles de
Kadence qui l'emportaient sur les nôtres.

| Défaut | Cause | Correction |
|---|---|---|
| Le badge occupait **toute la largeur** de la carte (200px pour « -50% ») | Kadence pose `right: 6px` **en plus** du `left` : les deux côtés ancrés, le badge s'étire | `inset-inline-end: auto` |
| « Nouveau » sortait **orange** au lieu de vert | `.famma-badge--new` (0,1,0) perdait contre `…li.product .onsale` (0,3,2) | sélecteur de même spécificité, `#16A34A` vérifié au rendu |
| Le bouton **n'apparaissait qu'au survol** et **recouvrait le prix** | classe `woo-archive-action-on-hover` : bouton en `position: absolute; opacity: 0`, et le bloc de texte glisse de `-2rem` au survol | bascule vers l'autre mode de Kadence, `woo-archive-btn-button` |

Le mode est échangé côté serveur — `str_replace` sur
`woocommerce_product_loop_start` — plutôt que neutralisé par trois règles CSS :
la présentation reste celle du parent, seul le mode change. Sur une boutique
mobile-first, c'est de toute façon le seul comportement praticable : il n'y a
pas de survol au doigt, donc pas de bouton du tout.

**Un défaut que j'avais moi-même introduit** : mon filtre d'icône panier
enveloppait tout le contenu du bouton dans un `<span>`, y compris les libellés
d'état AJAX que Kadence y cache (« Continuer », « Chargement », « Terminé »).
Sortis de leur positionnement, ils repassaient dans le flux et le bouton faisait
deux lignes — 58px au lieu de 41. Le filtre est supprimé : **Kadence fournit
déjà une icône panier**, il suffisait de la mettre à 15px.

Résultat mesuré : badge 45×24 (21 % de la carte en desktop, 13 % en mobile),
« Nouveau » à `rgb(22, 163, 74)`, bouton 40px de haut sur une ligne, visible sur
les quatre cartes sans survol, et le prix ne bouge plus.

### Deux causes structurelles, corrigées

1. **L'échelle de la carte produit.** Comme pour l'habillage, la boutique était
   composée sur l'échelle éditoriale (12 · 14 · 16 · 20px) là où la maquette
   emploie sa demi-échelle (11,5 · 12,5 · 13,5 · 14 · 19). La carte a désormais
   ses propres jetons `--fs-card-*` et ses rayons `--radius-badge` /
   `--radius-card-button` : elle est répétée des dizaines de fois dans une
   grille, où un demi-pixel se voit à la répétition.
2. **Le plancher tactile global.** `style.css` impose 48px à **tout** `button`
   (règle 10/44). Il protégeait le doigt mais écrasait le cœur (30px) et la
   bascule d'affichage (38px), qui ne sont pas des cibles de commande. Le
   plancher est levé pour ces deux boutons **uniquement au-dessus de 1000px** —
   là où l'on pointe au lieu de toucher. Sous 1000px, le cœur garde sa cible de
   48px et son dessin de 30px : c'est sa zone cliquable qui s'étend.

### Compteurs de filtre, avec de vrais produits

`En stock 3 · Rupture 0 · En promotion 3` — chiffres calculés par WooCommerce,
purgés à chaque enregistrement de produit. `/categorie/accessoires/` affiche
zéro produit et son état vide : les trois produits de test sont encore dans
« Sans rayon ».

### C-6 — CTA de carte : orange ou bleu ? *(conflit trouvé pendant D3)*

`style.css` avait tranché le **bleu** pour le bouton des cartes de la grille,
avec ce motif : « l'orange reste au bouton d'achat de la fiche produit ; s'il
couvre aussi la grille, il ne signale plus rien ». La maquette met de l'orange
partout.

Retenu : **l'orange de la maquette**, avec le texte en bleu (6,32:1). C'est la
conséquence directe de l'arbitrage C-1, qui disait « garder tous les aplats
orange, ne corriger que les glyphes ». La règle du CTA bleu de `style.css` est
donc dépassée pour la grille — la surcharge est portée par `shop.css`, par
précision de sélecteur et sans un seul `!important`.

### Constat de recette à traiter en D6

Les slugs sont bien `/shop/` et `/cart/` en anglais sur un site français
(E-37 confirmé au rendu ; `/boutique/` et `/panier/` renvoient 404).

### Lot D1 — Socle : jetons alignés et polices

**But** : que toute valeur de la maquette existe comme jeton avant qu'une seule
page ne soit écrite.

- `assets/css/tokens.css` : ajouter les nuances manquantes (`--famma-line-header: #EDEFF3`, `--famma-line-field: #DFE3EA`, `--famma-surface-field: #FBFCFD`, `--famma-surface-media: #F4F6F9`, `--famma-surface-icon: #F1F4F9`, `--famma-price-old: #98A2B3`, textes sur marine `#C4CEDD` / `#93A0B5` / `#A9B6CA` / `#B9C4D6` / `#C9D3E2`, `--famma-orange-hover: #E86E00`, `--famma-new: #16A34A`, `--famma-whatsapp: #25D366`) ; aligner `--container-max` sur 1240px ; ajouter les ombres de survol de la maquette.
- Polices auto-hébergées (sous réserve C-3) + `--famma-font` et `--famma-font-ar` mis à jour.
- Documenter chaque ajout dans `docs/DESIGN-SYSTEM.md`, avec son contraste, comme le fait déjà le fichier.

**Écart assumé sur la typographie.** La maquette utilise une échelle en demi-pixels
(13 · 13,5 · 14,5 · 15,5 px). Le projet a déjà une échelle nommée
(`--fs-caption` 12px, `--fs-small` 14px, `--fs-body` 16px…). Les tailles sont
**alignées sur l'échelle du projet**, pas recopiées à la demi-décimale : un demi-pixel
n'est pas perceptible, alors qu'une échelle à trous se paie sur chaque composant
suivant. Les tailles hors échelle sont conservées quand elles portent du sens —
le mot-symbole du hero, le prix, le titre de fiche produit.

**Recette** : aucune régression visuelle sur les pages existantes ; `--container-max` change la largeur partout de façon cohérente.

### Lot D2 — En-tête et pied de page partagés *(le cœur de la demande)*

**Fichiers créés**

| Fichier | Rôle |
|---|---|
| `famma-child/header.php` | `<!doctype>` → barre supérieure → en-tête collant → ouverture du conteneur de contenu |
| `famma-child/footer.php` | pied de page 5 colonnes → barre légale → WhatsApp flottant → `wp_footer()` |
| `famma-child/inc/header-parts.php` | fonctions de rendu : promesses, sélecteur de langue, lockup, nav, recherche, panier, burger |
| `famma-child/inc/footer-parts.php` | colonnes marque / navigation / informations / aide / COD |
| `famma-child/assets/js/navigation.js` | burger (`aria-expanded`), fermeture au `Escape`, focus piégé dans le menu ouvert |
| `famma-child/assets/css/layout.css` | en-tête, pied de page, conteneur — chargé après `style.css` |

**Câblage — aucune donnée inventée**

| Élément de la maquette | Source réelle |
|---|---|
| 3 promesses de la barre supérieure | chaînes `esc_html__()`, domaine `famma-child` — toutes vraies (livraison Tunisie, COD, WhatsApp) |
| `FR \| عربي` | sélecteur TranslatePress (`trp_custom_language_switcher`), stylé aux couleurs de la maquette ; désactivation du sélecteur flottant par défaut |
| Logo | `famma_child_lockup_markup()` existant, inchangé |
| Nav 7 entrées | `wp_nav_menu( 'primary' )` ; `menu-item-current` porte l'état actif orange |
| Recherche | `get_search_form()` limité à `post_type=product` |
| Compte | `wc_get_page_permalink( 'myaccount' )` |
| Panier + badge | `WC()->cart->get_cart_contents_count()`, rafraîchi par `woocommerce_add_to_cart_fragments` (badge masqué à zéro) |
| Colonne « Navigation » | `wp_nav_menu( 'primary' )` |
| Colonne « Informations » | nouvel emplacement `famma_footer_info` enregistré par le thème enfant + entrée dans `scripts/create-menus.php` |
| Colonne « Aide & support » | WhatsApp depuis `FAMMA_WHATSAPP_*`, téléphone / email / adresse depuis des options — **chaque ligne absente n'est pas rendue** |
| Réseaux sociaux | seules les URL renseignées produisent un rond |
| Colonne COD | illustration à fournir ; à défaut, la colonne rend le texte seul |
| Barre légale basse | `famma_child_legal_bar()` déplacée ici et retirée de `wp_footer` |
| WhatsApp flottant | implémentation `famma-core` existante, `--z-float`, décalé de `--sticky-cta-h` sur la fiche produit |

**Points de vigilance**

- Neutraliser le CSS/JS d'en-tête et de pied de page de Kadence via `wp_dequeue_style()` sur `wp_enqueue_scripts` à priorité tardive. Ne jamais éditer le parent.
- `wp_body_open()` juste après `<body>` — TranslatePress et le suivi en dépendent.
- Le collant est du `position: sticky` CSS, pas du JS.
- Vérifier `header.php` sur les gabarits WooCommerce (panier, commande, confirmation), pas seulement sur l'accueil.

**Recette** : les 8 pages existantes + panier + commande + confirmation + 404 affichent le nouvel en-tête et le nouveau pied de page ; navigation entièrement au clavier ; `/ar/` retourne la mise en page sans feuille RTL dédiée ; aucune erreur dans `debug.log` avec `WP_DEBUG=true`.

### Lot D3 — Boutique (`archive-product`)

**Fichiers** : `famma-child/woocommerce/archive-product.php`, `content-product.php`, `loop/orderby.php`, `loop/no-products-found.php`, `loop/pagination.php` ; `assets/css/shop.css` ; `assets/js/shop-view.js`.
**Rappel Context7** : ne pas créer `famma-child/woocommerce.php`, sinon ces surcharges sont ignorées.

| Élément | Implémentation |
|---|---|
| Fil d'Ariane | `woocommerce_breadcrumb()` restylé ; slugs francisés en même temps (E-37) |
| Titre + sous-titre | `woocommerce_page_title()` + description de la catégorie |
| Tri | `woocommerce_catalog_ordering()` restylé — pas de `<select>` maison |
| Bascule grille / liste | `assets/js/shop-view.js`, classe sur le conteneur, mémorisée en `localStorage` ; la liste est une variante CSS de la même carte |
| Colonne filtres | **widgets/blocs de filtre WooCommerce natifs** (prix, stock, note, attribut, marque `product_brand` si WooCommerce ≥ 9.4) dans une barre latérale déclarée par le thème enfant, restylés à la maquette. La logique de requête reste celle de WooCommerce. |
| Compteurs par filtre | fournis par les widgets natifs — jamais codés en dur |
| Bandeau 3 promesses | chaînes traduites, sous le même composant que la barre supérieure |
| Nombre de résultats | `woocommerce_result_count()` |
| Carte produit | reprend et étend `famma_child_loop_benefit()`, `famma_child_sale_badge()` existants ; bouton favori **omis tant qu'il n'y a pas de liste d'envies** — pas de bouton décoratif |
| État vide | `loop/no-products-found.php` au dessin de la maquette |
| Pagination | `woocommerce_pagination()` restylée |
| Bande newsletter | selon l'arbitrage C-5 |

**Recette** : filtres et tri modifient réellement la requête ; état vide atteignable ; grille correcte de 320px à 1440px ; RTL vérifié.

### Lot D4 — Fiche produit (`single-product`)

**Fichiers** : `famma-child/woocommerce/single-product.php`, `single-product/product-image.php`, `single-product/product-thumbnails.php`, `single-product/tabs/tabs.php` ; extension de `functions.php` ; `assets/css/product.css`.

| Élément | Implémentation |
|---|---|
| Galerie + 5 vignettes + zoom | galerie WooCommerce native (`wc-product-gallery-zoom`, `-lightbox`, `-slider`) restylée. On ne réécrit pas une visionneuse. |
| Badge remise en surimpression | `famma_child_sale_badge()` existant |
| Titre, note, nombre d'avis | cœur WooCommerce |
| Prix + prix barré + « économie » | `famma_child_price_savings()` existant (priorité 11), restylé |
| 3 assurances | `famma_child_product_reassurance()` existant (priorité 35), redessiné en ligne d'icônes |
| Quantité + stock | formulaire `add-to-cart/simple.php` du cœur, cibles ≥ 48px (E-30) |
| CTA « Commander » | bouton du cœur ; le CTA collant mobile existant est conservé |
| Favori | omis tant qu'il n'y a pas de liste d'envies |
| Carte WhatsApp | bouton `famma-core` piloté par `.env`, habillé en carte verte |
| Bandeau bénéfices | champs produit ; masqué si vides |
| Onglets | filtre `woocommerce_product_tabs` : Description (cœur), Caractéristiques (`woocommerce_display_product_attributes()`), Avis (cœur), FAQ (champ produit, onglet masqué si vide), Livraison (contenu partagé, source unique avec la page Livraison) |
| Bloc note moyenne + avis | agrégats WooCommerce réels ; **section entière masquée à zéro avis** |
| Produits similaires | `woocommerce_upsell_display()` / produits liés |

**Recette** : parcours complet ajout au panier → checkout COD → confirmation intact ; les onglets vides ne s'affichent pas ; aucun avis fictif ; galerie utilisable au clavier.

### Lot D5 — Accueil

**Fichiers** : `famma-child/front-page.php` (ou blocs, selon ce que rend `scripts/create-homepage.php`), `assets/css/home.css`, `assets/js/hero.js`.

| Section | Implémentation |
|---|---|
| Hero carrousel | `scroll-snap` CSS + ~40 lignes de JS (auto 6 s, flèches, points, `aria-live`), **arrêt sous `prefers-reduced-motion`** et à la mise au point clavier. Aucune bibliothèque. Les slides sont du contenu éditable, pas des chaînes en dur. |
| Catégories | termes `product_cat` réels, image de terme ; la vignette « Nouveautés » est le disque orange à étoile de la maquette |
| « Nos nouveautés » | `wc_get_products()` récents, **mis en cache par transient** (règle §10), même carte que la boutique |
| « Pourquoi choisir FAMMA ? » | 4 affirmations vraies et vérifiables, traduites |
| Newsletter | selon l'arbitrage C-5 |
| Avis + statistiques | calculés depuis les avis WooCommerce réels ; **section masquée tant qu'il n'y en a pas**. « + 2 500 » et « 538 avis » ne sont pas repris. |
| `famma_child_remove_front_page_hero()` | à réexaminer : cette fonction retire le hero actuel ; elle devient inutile ou change de rôle |

**Recette** : accueil correct avec 0 produit, 1 produit et 12 produits ; carrousel arrêté si le visiteur préfère moins d'animation ; LCP mesuré sur mobile.

### Lot D6 — Menus, pages et contenus réels

- Ajouter l'emplacement `famma_footer_info` et étoffer `scripts/create-menus.php` (le script reste idempotent).
- Remettre « Boutique » au menu principal : le catalogue n'est plus masqué (E-33).
- Décider du sort de « Catégories / Promotions / Nouveautés » : les garder seulement si une archive réelle les sert.
- Franciser les slugs `/shop/` et `/cart/` (E-37) avec les redirections correspondantes.
- Retirer le produit de test `teste11` (E-36).

### Lot D7 — Recette et durcissement

1. `php -l` sur chaque fichier modifié ; `phpcs` au ruleset WordPress sans erreur.
2. `WP_DEBUG=true`, `debug.log` vierge de tout avertissement introduit.
3. Accessibilité : contrastes recalculés après C-1, anneau de focus visible partout, cibles ≥ 48px, parcours clavier complet en-tête → contenu → pied de page.
4. RTL : `/ar/` sur les trois pages, sans feuille dupliquée — uniquement des propriétés logiques.
5. Parcours métier : ajout au panier → checkout COD → confirmé → expédié → **livré** marque payé et déclenche le suivi serveur **une seule fois**.
6. Performance mobile : Lighthouse avant/après, polices en `preload`, CSS Kadence inutile retiré.
7. Mettre à jour `docs/DESIGN-SYSTEM.md`, `docs/ARCHITECTURE.md` (les gabarits du thème enfant existent désormais) et `docs/UX-PLAN.md` (clôture de D-9, E-29, E-33, E-37).

---

## 6. Ce que le propriétaire doit fournir ou trancher

**Trancher** — bloquant pour les lots indiqués :

1. **C-1, contraste** — appliquer la correction de couleur de texte, ou signer la dérogation. *(bloque D1)*
2. **C-3, polices** — Poppins + Noto Kufi Arabic auto-hébergées, ou pile système. *(bloque D1)*
3. **C-5, newsletter** — omettre / brancher un fournisseur / remplacer par WhatsApp. *(bloque D5, D3)*
4. Menu : garder ou retirer « Catégories / Promotions / Nouveautés ». *(bloque D6)*

**Fournir** — sans quoi les blocs concernés restent masqués, jamais remplis
d'un contenu inventé :

- Numéro WhatsApp réel, téléphone, email de contact, adresse.
- URL des comptes Facebook / Instagram / TikTok / YouTube.
- Illustration « Paiement à la livraison » (150×96) du pied de page.
- Photos produits (emplacements `famma-hero-1..4`, `famma-cat-1..5`, `famma-p1..p5`, `famma-cod`).
- Les premiers vrais produits, avec prix de revient.
- Textes légaux (retours, CGV) — jamais générés automatiquement.

---

## 7. Ordre d'exécution

```
D1 socle ──► D2 en-tête + pied de page ──┬──► D3 boutique ──┐
                                          ├──► D4 produit ───┼──► D6 menus ──► D7 recette
                                          └──► D5 accueil ───┘
```

D2 est le lot qui répond littéralement à la demande (« le même en-tête et le
même pied de page sur toutes nos pages ») et il livre de la valeur seul, même
si D3–D5 attendent. C'est par là qu'on commence.
