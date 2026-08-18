# Handoff : FAMMA — identité + page d'accueil e-commerce

> Copie verbatim du `README.md` du projet Claude Design
> `90370726-36bf-4144-824a-394e2db3f56d` (dossier `design_handoff_famma_accueil/`).
> Ne pas éditer : c'est la spécification livrée par le designer.
> Le plan d'intégration côté WordPress est dans `docs/DESIGN-INTEGRATION-PLAN.md`.

## Overview
FAMMA (فمّا) est une boutique en ligne tunisienne, paiement à la livraison, trafic majoritairement mobile.
Ce paquet contient (1) le système de logo et (2) la page d'accueil complète, en copie fidèle des maquettes fournies par le client.
Slogan : « فمّا ديما حاجة جديدة » / « Il y a toujours quelque chose de nouveau ».

## About the Design Files
Les fichiers de ce paquet sont des **références de design réalisées en HTML** — des prototypes qui montrent l'apparence et le comportement attendus, **pas du code de production à copier tel quel**.
La tâche est de **recréer ces designs dans l'environnement existant du projet cible** (React, Next.js, Vue, thème WordPress/WooCommerce, etc.) avec ses conventions, ses composants et sa librairie de styles.

Note technique : les fichiers `.dc.html` utilisent un moteur de rendu propriétaire (template + classe de logique). Ne pas chercher à porter ce moteur. Les lire comme une spécification : le markup donne la structure et **tous les styles sont en inline `style=""`**, donc chaque valeur (couleur, taille, padding, radius) est lisible directement dans le fichier. La classe `Component` en bas du fichier contient les données (produits, catégories, traductions FR/AR) et l'état (langue, panier, carrousel).

## Fidelity
**High-fidelity (hifi).** Couleurs, typographie, espacements et interactions sont définitifs.
Seule exception : les **photos produits sont absentes**. Elles sont représentées par des emplacements `<image-slot>`. À remplacer par les vraies images / le champ média du CMS.

## Screens / Views

### 1. Page d'accueil (`FAMMA Accueil.dc.html`)
Largeur de contenu : conteneur centré `max-width: 1240px`, `padding: 0 20px`. Fond `#FFFFFF`.
Point de rupture unique : **1000px** (`isDesktop = window.innerWidth >= 1000`). En dessous : nav remplacée par un menu burger dépliant, recherche déplacée dans le menu. Toutes les grilles sont en `repeat(auto-fit, minmax(X, 1fr))` et se réorganisent sans media query.

**a. Barre supérieure**
- Fond `#0B1F3A`, texte `#FFFFFF`, `font-size: 13px`, `padding: 11px 20px`.
- Gauche : 3 items, `gap: 40px`, chacun icône 17px (stroke `#FFFFFF`, `stroke-width: 1.8`) + libellé, `gap: 9px`.
  « Livraison partout en Tunisie », « Paiement à la livraison », « Support WhatsApp 24/7 ».
- Droite : `FR` | `عربي`, boutons, la langue active en `#FF7A00`, l'inactive en `#FFFFFF`, séparateur `|` à `opacity: 0.4`.

**b. En-tête (sticky, `top: 0`, `z-index: 40`)**
- Fond `#FFFFFF`, `border-bottom: 1px solid #EDEFF3`, `padding: 14px 20px`, `gap: 22px`.
- Logo : icône SVG 42×41 + bloc texte. Mot-symbole `font-weight: 700; font-size: 25px`, le **A final en `#FF7A00`**. Sous-ligne RTL : trait 12×2px orange + « فمّا ديما حاجة جديدة » en Noto Kufi Arabic 9px `#46536B` + trait orange.
- Nav (desktop) : `font-size: 14.5px; font-weight: 500; gap: 22px`. Item actif « Accueil » en `#FF7A00` avec `border-bottom: 2px solid #FF7A00`. « Catégories » suivi d'un chevron 11px.
- Recherche : `width: 250px`, `border: 1px solid #DFE3EA`, `radius: 8px`, `padding: 9px 14px`, fond `#FBFCFD`, icône loupe `#8A94A6`, placeholder « Rechercher un produit... » 13.5px.
- Actions : icône compte 21px, icône panier 22px, badge orange `#FF7A00` en haut à la fin (`inset-inline-end: -6px`), `min-width: 17px; height: 17px; radius: 9px; font-size: 10.5px; font-weight: 600`, texte blanc.
- Mobile : bouton burger 23px ; le menu ouvert liste les 7 liens (`padding: 9px 0`, séparateurs `1px solid #F3F5F8`) puis le champ de recherche.

**c. Hero (carrousel)**
- Carte `background: #0B1F3A; border-radius: 16px; overflow: hidden`, marge haute 22px.
- Contenu : flex wrap, `gap: 28px`, `padding: 44px clamp(24px, 5vw, 68px)`. Colonne texte `flex: 1 1 340px`, colonne visuel `flex: 1 1 340px; height: 330px`.
- Mot-symbole : `font-weight: 700; font-size: clamp(46px, 7vw, 78px); line-height: 1`, blanc, A en `#FF7A00`.
- Slogan arabe : `direction: rtl`, trait 26×3px orange de chaque côté, Noto Kufi Arabic 700 `clamp(19px, 2.6vw, 27px)`, « فمّا » en `#FF7A00`, reste en blanc.
- Phrase : `clamp(15px, 1.6vw, 17.5px); line-height: 1.65; color: #C9D3E2; max-width: 430px` — **change à chaque slide**.
- Avantages : 3 blocs, `gap: 26px`, chacun `min-width: 175px`, icône 21px stroke `#FF7A00`, titre 13.5px/600 blanc `line-height: 1.35`, sous-titre 12.5px `#A9B6CA` `line-height: 1.4`.
- CTA : fond `#FF7A00`, texte blanc 15.5px/600, `radius: 9px`, `padding: 15px 26px`, séparateur vertical 1px `rgba(255,255,255,0.45)` puis flèche 17px. Hover `#E86E00`.
- Visuel : disque plein `#FF7A00` de 250px centré, ellipse `330×26px` `border: 3px solid #FF7A00; opacity: .65` en bas (socle), image produit par-dessus (86% × 92%, `object-fit: contain`).
- Flèches : 38px, `border-radius: 50%`, `background: rgba(255,255,255,0.14)` (hover `0.3`), positionnées `inset-inline-start/end: 16px`, centrées verticalement.
- Points : 4, en bas centré, `gap: 9px`, `height: 9px`, inactif `9px` large `rgba(255,255,255,0.35)`, actif `26px` large `#FF7A00`, `radius: 5px`.

**d. Titres de section (motif réutilisé 4×)**
Rangée centrée : trait `44×3px #FF7A00` — `<h2>` `clamp(21px, 2.4vw, 26px); font-weight: 600; color: #0B1F3A` — trait orange. `gap: 18px`.
Titres : « Parcourez nos catégories », « Nos nouveautés », « Pourquoi choisir FAMMA ? », « Ils nous font confiance ».

**e. Catégories**
- Grille `repeat(auto-fit, minmax(148px, 1fr))`, `gap: 16px`, marge haute 28px.
- Carte : `border: 1px solid #E7EAF0; radius: 12px; padding: 22px 14px`, colonne centrée, `gap: 14px`. Hover : `border-color: #FF7A00; box-shadow: 0 8px 20px rgba(11,31,58,.08)` (transition 200ms).
- Visuel 62×62px. Libellé 13.5px/500 centré.
- 6 entrées : Maison & Cuisine, Électronique, Auto & Moto, Beauté & Santé, Accessoires, **Nouveautés** (disque plein `#FF7A00` 62px avec étoile blanche 28px, au lieu d'une photo).

**f. Produits (« Nos nouveautés »)**
- Grille `repeat(auto-fit, minmax(196px, 1fr))`, `gap: 18px`.
- Carte : `border: 1px solid #E7EAF0; radius: 12px; overflow: hidden`. Hover `box-shadow: 0 10px 24px rgba(11,31,58,.1)`.
- Image : hauteur 178px, fond `#F4F6F9`, `object-fit: cover`.
- Badge : absolu `top: 10px; inset-inline-start: 10px`, 11.5px/600 blanc, `padding: 4px 9px; radius: 5px`. Remise → `#FF7A00`. « Nouveau » → `#16A34A`.
- Corps : `padding: 14px 14px 16px; gap: 9px`. Nom 14px/500 `line-height: 1.4; min-height: 39px` (aligne les cartes). Étoiles `#FF7A00` 13px + nombre d'avis 12px `#8A94A6`. Prix 19px/700 ; prix barré 13px `#98A2B3` `line-through`.
- Bouton : pleine largeur, `#FF7A00`, blanc 13.5px/500, `radius: 7px; padding: 11px`, icône panier 15px + « Commander ». Hover `#E86E00`.

**g. « Pourquoi choisir FAMMA ? »**
Grille `minmax(240px, 1fr)`, `gap: 16px`. Carte `border: 1px solid #E7EAF0; radius: 12px; padding: 18px`, ligne `gap: 14px`. Pastille icône 46×46px `radius: 10px` fond `#F1F4F9`, icône 23px stroke `#0B1F3A`. Titre 14.5px/600, texte 13px `#56617A` `line-height: 1.55`.
4 items : Produits innovants / Confiance & sécurité / Livraison rapide / Support à l'écoute.

**h. Newsletter**
Bande `#0B1F3A`, `radius: 14px`, `padding: 30px clamp(22px, 4vw, 44px)`, flex wrap `gap: 26px`, `justify-content: space-between`.
Gauche : icône cadeau 58px (stroke `#FF7A00`, ruban plein orange) + titre RTL Noto Kufi Arabic 700 `clamp(19px, 2.4vw, 25px)` blanc avec « فمّا » orange, suivi de « ... » ; sous-texte 13.5px `#B9C4D6`.
Droite : groupe champ + bouton en `radius: 8px; overflow: hidden` — input `padding: 14px 16px; font-size: 14px`, bouton `#FF7A00` blanc 14px/600 `padding: 14px 22px` (hover `#E86E00`). Mention 11.5px `#93A0B5`.

**i. Avis**
Grille `minmax(270px, 1fr)`, `gap: 18px`.
Bloc stats : carte bordée, 2 colonnes séparées par un filet `1px #E7EAF0` — « + 2 500 » 28px/700 + « Clients satisfaits » 13px `#56617A` ; « 4.8/5 » 28px/700 + 5 étoiles `#FF7A00` + « Basé sur 538 avis ».
Témoignages : carte `padding: 20px; gap: 11px`, étoiles orange, texte 13.5px `#34405A` `line-height: 1.6`, signature 13px/500 `#56617A`.

**j. Pied de page**
Fond `#0B1F3A`, texte `#C4CEDD`. Grille `repeat(auto-fit, minmax(190px, 1fr))`, `gap: 32px`, `padding: 44px 20px 30px`.
- Colonne marque : logo blanc/orange 40px + mot-symbole 24px, description 13px `line-height: 1.65; max-width: 240px`, 4 ronds sociaux 32px `background: rgba(255,255,255,.1)` (hover `#FF7A00`), icône 15px blanche.
- Colonnes « Navigation » (7 liens) et « Informations » (6 liens) : titre 15px/600 blanc, liens 13px `#C4CEDD`, hover `#FF7A00`, `gap: 11px`.
- Colonne « Aide & support » : 4 lignes icône 14px + texte 13px ; l'icône WhatsApp est en `#25D366`, les autres en `#C4CEDD`.
- Colonne « Paiement à la livraison » : centrée, illustration 150×96px, texte 12.5px.
- Barre basse : `border-top: 1px solid rgba(255,255,255,.12)`, `padding: 16px 20px`, 12px `#93A0B5` — copyright à gauche, 4 liens légaux à droite (`gap: 22px`).

**k. Bouton WhatsApp flottant**
`position: fixed; bottom: 24px; inset-inline-end: 24px`, 56px, `radius: 50%`, `background: #25D366`, glyphe blanc 30px, `box-shadow: 0 6px 18px rgba(37,211,102,.4)`, `z-index: 50`.

### 2. Système de logo (`FAMMA Logo.dc.html`)
Piste retenue par le client : **2a** (revectorisation de son visuel).
- **Icône** : bulle de dialogue arrondie, tracé ouvert `stroke-width: 9`, `stroke-linecap/linejoin: round`, queue en pointe en bas à gauche ; antenne + point orange en haut ; œil gauche = rectangle arrondi orange 10×20 (`rx: 5`) ; œil droit = chevron `<` navy `stroke-width: 7` (clin d'œil) ; sourire = courbe navy ; 3 étincelles orange en haut à droite. viewBox `0 0 124 122`.
- **Lockup** : icône + « FAMM**A** » (Poppins 700, A en `#FF7A00`) + slogan arabe entre deux traits orange.
- **Icône simplifiée** (favicon, avatar, app) : carré plein `#0B1F3A` `rx: 24` (ou cercle `rx: 50`), seulement œil orange + clin d'œil + sourire en blanc. **La bulle complète n'est pas lisible sous ~48px — utiliser cette version.**
- **Badge circulaire** (`2b`) : anneau navy 270° + quart orange, bandeau navy en bas avec 3 pictos blancs.
- **Monochromes** : tout en `#0B1F3A` sur fond clair, tout en `#FFFFFF` sur fond navy. Aucun aplat orange en mono.
- Zone de protection recommandée : la hauteur de l'antenne (≈ 1/8 de la hauteur de l'icône) sur les 4 côtés.

## Interactions & Behavior
- **Carrousel hero** : 4 slides. Défilement automatique toutes les **6000 ms**. Flèches précédent/suivant en modulo 4. Clic sur un point → slide correspondante. Transition : `opacity` sur **500 ms ease**, slides empilées en absolu (`z-index` 2 pour l'active, 1 sinon). Seuls la phrase et l'image changent.
- **Ajout au panier** : chaque bouton « Commander » et le CTA du hero incrémentent le compteur du badge panier.
- **Bascule FR / عربي** : change `dir` sur la racine (`ltr` ↔ `rtl`) et remplace **tous** les libellés. Le layout se retourne via `inset-inline-start/end` et les propriétés logiques — aucun style directionnel en dur.
- **Menu mobile** : ouverture/fermeture au burger, sous l'en-tête sticky.
- **Hover** : boutons orange → `#E86E00` ; cartes catégorie → bordure orange + ombre ; cartes produit → ombre ; liens de pied de page → `#FF7A00`. Toutes les transitions en **200 ms ease**.
- **Responsive** : un seul point de rupture JS à 1000px ; tout le reste en `auto-fit` + `clamp()`. À implémenter de préférence en CSS pur (media query 1000px pour la nav).
- **États absents du prototype** (à prévoir côté dev) : chargement, rupture de stock, erreur/succès du formulaire newsletter, validation email, page 404.

## State Management
| État | Type | Rôle |
|---|---|---|
| `lang` | `'fr' \| 'ar'` | langue + direction du document |
| `cart` | `number` | compteur du badge panier |
| `slide` | `0..3` | slide active du hero |
| `menuOpen` | `boolean` | menu burger mobile |
| `w` | `number` | largeur de fenêtre (dérive `isDesktop`) |

Données à câbler sur l'API / le CMS : liste produits (nom, prix, prix barré, note, nombre d'avis, badge, image, url), catégories, avis clients, compteurs de la section confiance.

## Design Tokens
**Couleurs**
| Rôle | Valeur |
|---|---|
| Marine (primaire) | `#0B1F3A` |
| Orange (accent) | `#FF7A00` |
| Orange hover | `#E86E00` |
| Blanc | `#FFFFFF` |
| Vert « Nouveau » | `#16A34A` |
| Vert WhatsApp | `#25D366` |
| Bordure | `#E7EAF0` |
| Bordure en-tête | `#EDEFF3` |
| Bordure champ | `#DFE3EA` |
| Fond champ | `#FBFCFD` |
| Fond image produit | `#F4F6F9` |
| Fond pastille icône | `#F1F4F9` |
| Texte secondaire | `#56617A` |
| Texte tertiaire | `#8A94A6` |
| Texte sur marine | `#C4CEDD` |
| Texte discret sur marine | `#93A0B5` / `#A9B6CA` / `#B9C4D6` / `#C9D3E2` |
| Prix barré | `#98A2B3` |

**Typographie**
- Latin : **Poppins** (400 / 500 / 600 / 700).
- Arabe : **Noto Kufi Arabic** (400 / 500 / 700).
- Échelle : 11.5 · 12 · 12.5 · 13 · 13.5 · 14 · 14.5 · 15 · 15.5 · 19 · 21 · 25 · 28 px ; titres de section `clamp(21px, 2.4vw, 26px)` ; mot-symbole hero `clamp(46px, 7vw, 78px)`.
- Interlignes : 1 (mot-symbole), 1.35–1.4 (titres courts), 1.55–1.65 (paragraphes).

**Espacement** — 4 · 6 · 9 · 10 · 11 · 14 · 16 · 18 · 20 · 22 · 26 · 28 · 32 · 44 · 52 px. Gouttières de grille : 16 px (catégories, avantages), 18 px (produits, avis).

**Rayons** — 5 (badge) · 7 (bouton produit) · 8 (champ, bouton) · 9 (CTA) · 10 (pastille) · 12 (carte) · 14 (bande newsletter) · 16 (hero) · 50% (rond).

**Ombres** — carte catégorie `0 8px 20px rgba(11,31,58,.08)` ; carte produit `0 10px 24px rgba(11,31,58,.1)` ; WhatsApp `0 6px 18px rgba(37,211,102,.4)`. Aucune ombre au repos : elles n'apparaissent qu'au survol.

## Assets
- **Icônes** : toutes en SVG inline, style ligne, `stroke-width` 1.6–1.9, bouts arrondis. Remplaçables par un jeu équivalent (Lucide / Feather) sans perte visuelle.
- **Logo** : SVG inline, présent dans l'en-tête, le hero et le pied de page. Source complète dans `FAMMA Logo.dc.html`.
- **Photos produits, catégories, illustration « paiement à la livraison »** : **non fournies**. Représentées par `<image-slot>`. Identifiants utilisés : `famma-hero-1..4`, `famma-cat-1..5`, `famma-p1..p5`, `famma-cod`.
- **Polices** : Google Fonts (Poppins, Noto Kufi Arabic). Prévoir un auto-hébergement pour la performance mobile.

## Files
| Fichier | Contenu |
|---|---|
| `FAMMA Accueil.dc.html` | Page d'accueil complète (reste dans le projet Claude Design) |
| `FAMMA Boutique.dc.html` | Grille catalogue + filtres → copie locale `FAMMA-Boutique.dc.html` |
| `FAMMA Produit.dc.html` | Fiche produit → copie locale `FAMMA-Produit.dc.html` |
| `FAMMA Logo.dc.html` | Planches logo (reste dans le projet Claude Design) |
| `image-slot.js` | Composant de placeholder image — **design uniquement, ne pas embarquer** |
| `support.js` | Runtime du moteur de rendu — **référence, à ne pas porter** |
| `maquettes/` | Les 3 maquettes d'origine du client : accueil, boutique, fiche produit |
