# Plan UX/UI — FAMMA MENNOU

> Réponse au **Brief UX/UI complet** (37 points) fourni par le propriétaire, confronté au
> code réellement présent dans le dépôt au **2026-08-15**.
> Ce document ne remplace pas `DESIGN-SYSTEM.md` : il en est la feuille de route.
> Convention de citation : `§n` = `MASTER-PROMPT.md` · `§Bn` = le brief UX/UI.

## 0. Méthode et limites

| Point | État |
|---|---|
| Graphe Graphify | **Graph miss à la rédaction** (2026-08-15, matin) : le corpus excluait `famma-core` et `famma-child`. **Résolu depuis** — voir `ENVIRONMENT-AUDIT.md`. Le graphe couvre à présent le code (253 nœuds / 340 arêtes) ; les constats ci-dessous restent issus d'une lecture directe. |
| Context7 | **Indisponible à la rédaction** (aucun serveur MCP connecté) : aucune affirmation sur une API WordPress/WooCommerce récente n'est vérifiée en ligne, tout s'appuie sur le code présent. **Connecté depuis** (`.mcp.json`, portée projet). Les points marqués « non vérifié » restent à revalider. |
| Couverture de lecture | `tokens.css`, `style.css` (456 l.), `functions.php` (373 l.), surface de hooks de `famma-core.php`, `DESIGN-SYSTEM.md`, `DECISIONS.md`, `PLAN-EXECUTION.md`. |
| Vérification à l'exécution | **Aucune** — Docker non démarré dans cette session. Les mesures de contraste sont calculées (WCAG 2.1, formule officielle), pas capturées à l'écran. |
| Portée | Ce document **planifie**. Il ne modifie aucun fichier de rendu. |

---

## 1. Ce qui existe déjà (et qu'il ne faut pas refaire)

Le brief est écrit comme si l'on partait de zéro. Ce n'est pas le cas : environ **35 % du §B** est
déjà en place, correctement, et sert de socle.

| Brique | Où | Verdict |
|---|---|---|
| Jetons couleur / espacement / rayon | `assets/css/tokens.css` | ✔ base saine, **incomplète** (voir §3) |
| Cibles tactiles ≥ 48 px globales | `style.css:25-32` | ✔ conforme §B30 |
| Anneau de focus jamais supprimé | `style.css:47-54` | ⚠ présent mais **défectueux** (D-2) |
| Lockup logo + favicon + sceau | `functions.php:62-94`, `assets/img/` | ✔ §B6 respecté — travailler autour, pas refaire |
| Accueil « catalogue vide » | héros, chips, FAQ, contact (`style.css:195-313`) | ✔ §B10 traité comme un état réel |
| CTA collant mobile + masquage intelligent | `style.css:377-455`, `assets/js/sticky-cta.js` | ✔ §B17, y compris la dégradation sans JS |
| Réassurance sous le bouton d'achat | `functions.php:181-210` | ✔ §B15 partiel |
| Galerie produit (zoom / lightbox / slider) | `functions.php:368-370` | ✔ §B14 socle |
| Propriétés logiques (RTL sans feuille dupliquée) | tout `style.css` | ✔ §B21 socle |
| Checkout court, 24 gouvernorats, tél. TN, pays forcé | `famma-core.php:766-781` | ✔ §B18 côté données |
| WhatsApp flottant + shortcode | `famma-core.php:961-963` | ✔ §B20 |
| Données structurées Organization / WebSite | `functions.php:280-336` | ✔ |
| Barre de liens légaux | `functions.php:106-123` | ✔ §B28 partiel (1 colonne sur 4) |

**Conséquence sur la commande à passer au designer** : ne pas commander une refonte. Commander
un **système** (jetons, composants, états) et **cinq écrans** qui n'existent pas encore.

---

## 2. Écarts brief ↔ implémentation

Sévérité : **P0** = bloque une vente ou casse une règle non négociable · **P1** = coûte de la
conversion · **P2** = dette de cohérence.

| # | Écart (§B) | Sév. | Effort | Impact KPI |
|---|---|---|---|---|
| E-01 | Contraste : CTA orange/blanc à **2,61:1** — échoue AA, y compris en grand texte (§B30) | **P0** | S | Lisibilité du bouton qui fait vendre |
| E-02 | Anneau de focus orange **invisible sur le CTA orange** et sous 3:1 sur blanc (§B30) | **P0** | S | Accessibilité clavier |
| E-03 | Aucune échelle typographique : ni H1-H4, ni Body/Small/Caption, ni **Price/Discount/Button** (§B7, §B8) | **P0** | M | Dérive visuelle dès le 2ᵉ écran |
| E-04 | Pile de polices `system-ui, Tahoma, Noto Sans Arabic` — **aucun couple FR/AR choisi** (§B8) | **P0** | M | Rendu arabe aléatoire selon l'appareil |
| E-05 | **Pas de `theme.json`** dans `famma-child` : l'éditeur de blocs ignore la palette FAMMA | **P0** | S | Le propriétaire composera hors système |
| E-06 | **Product card non conçue** — cartes Kadence par défaut (§B13) | **P0** | M | Landing → Product View |
| E-07 | **Page de confirmation de commande non conçue** (§B31, §14) — moment de confiance n°1 en COD | **P0** | M | Taux de refus à la livraison |
| E-08 | Le contenu de l'accueil vit **en base**, pas dans Git : le design n'est ni relisible ni reproductible | **P0** | M | Risque de perte pure |
| E-09 | Aucun état conçu : chargement, panier vide, 404, aucun résultat, rupture, erreur formulaire (§B31) | P1 | M | Abandon silencieux |
| E-10 | **Pas de navigation mobile basse** (§B12) | P1 | M | Engagement, profondeur de visite |
| E-11 | **Recherche absente de l'interface** (§B23) — pourtant déclarée en `SearchAction` JSON-LD | P1 | M | Découverte |
| E-12 | Boutique sans grille, tri, ni **filtres en bottom sheet** (§B22) | P1 | L | Découverte |
| E-13 | **Cartes catégories** inexistantes (§B24) | P1 | S | Découverte |
| E-14 | **Identité promo** inexistante : badge, ancien prix, économie (§B25) | P1 | S | ✔ **levé** — badge et économie calculés depuis les prix réels |
| E-15 | Champs de formulaire non stylés (§B18, §B30) | P1 | S | ✔ **levé** — 48px partout. *Correction : `autocomplete` était déjà posé par WooCommerce, l'écart annoncé était faux* |
| E-16 | Confiance **absente du checkout lui-même** (§B19) | P1 | S | ✔ **levé** — réassurance au-dessus du bouton |
| E-17 | Footer réduit à la barre légale : ni navigation, ni assistance, ni social (§B28) | P1 | S | Confiance |
| E-18 | Sélecteur de langue **flottant en rustine**, en concurrence avec WhatsApp et le CTA collant (§B21) | P1 | S | Bilingue |
| E-19 | Le CTA collant **recouvre** le bas de page (aucune compensation de hauteur) | P1 | S | Fin de fiche produit inatteignable |
| E-20 | `z-index: 998` en dur + décalage WhatsApp `84px` magique — pas d'échelle | P2 | S | Dette |
| E-21 | Composants avis / preuve sociale non prévus, même vides (§B27) | P2 | M | Plus tard, données réelles |
| E-22 | Aucune charte photo produit (ratio, fond, nombre, vidéo verticale) | P1 | S | Qualité perçue |
| E-23 | `famma-child/languages/` déclaré mais **inexistant** (`functions.php:365`) | P2 | S | Chaînes du thème non traduisibles |
| E-24 | `DESIGN-SYSTEM.md` mi-anglais mi-français, contrairement aux autres docs | P2 | S | Cohérence doc |
| E-25 | Encarts `.famma-todo` (§45) encore présents — à faire disparaître avant prod | P2 | S | Crédibilité |
| E-26 | Anneau de focus du bouton WhatsApp : orange sur vert = **1,32:1**, invisible (`famma-core.php:1027`) | **P0** | S | ✔ levé |
| E-27 | `z-index: 9999` du bouton WhatsApp contre `998` du CTA collant — le flottant passait devant le bouton d'achat | P1 | S | ✔ levé |

### Écarts découverts à l'exécution (2026-08-15)

Aucun n'était visible en lecture de code seule. Ils justifient à eux seuls le passage
au navigateur avant tout commit.

| # | Écart | Sév. | État |
|---|---|---|---|
| E-28 | **Le checkout s'affichait en anglais** — « First name », « Place order ». `WPLANG=fr_FR` et pack du cœur installé, mais `setup.sh` n'installait aucun pack d'**extension** : WooCommerce retombait sur ses chaînes d'origine | **P0** | ✔ levé — corrigé dans `setup.sh`, donc non reproductible |
| E-29 | Palette Kadence restée sur ses valeurs par défaut (bleu `#2B6CB0`) : tout ce que le thème parent stylait était hors marque | **P0** | ✔ levé — les 15 emplacements pointent sur les jetons FAMMA |
| E-30 | Cibles tactiles du checkout : champs 42,8px, gouvernorat (select2) 38px, cases à cocher **13px** | **P0** | ✔ levé — 48px, case 24px dans une ligne de 48px |
| E-31 | Champ quantité à 40px sur la fiche produit | P1 | ✔ levé |
| E-32 | Le CTA collant s'affichait **sans formulaire à déclencher** quand la boutique est en « bientôt disponible » — CTA mort | P1 | ✔ levé |
| E-33 | La boutique entière était masquée : `woocommerce_coming_soon = yes` (défaut WooCommerce sur installation neuve, jamais un choix) | — | ✔ **tranché** — le propriétaire veut la boutique ouverte. Posé explicitement dans `setup.sh` : le catalogue vide se traite par l'accueil dédié (§9), pas en masquant le site |
| E-34 | Titres de page en anglais dans le **contenu** : « Shop », « Checkout » (pas une question de traduction) | P2 | partiel — la confirmation est corrigée par filtre ; les pages Boutique et Checkout restent à renommer |
| E-38 | Sous HPOS, `wp post delete` **ne supprime pas** une commande : elle ne vit pas dans `posts`. Passer par `$order->delete( true )` | P2 | consigné — piège de maintenance, pas un défaut UX |
| E-37 | Slugs en anglais sur un site français : `/shop/`, `/cart/`, `/checkout/`. `/boutique/` et `/panier/` renvoient 404 | P1 | ouvert — compte pour le SEO Tunisie (§22) et pour la lisibilité d'une URL partagée sur WhatsApp |
| E-35 | Prix rendu `120,000 د.ت` : symbole de devise en arabe sous locale française | P1 | ouvert — Lot 4, position et format de devise par langue |
| E-36 | Produit `teste11` publié, non marqué comme donnée de test (règle 12) | P1 | ouvert — à supprimer avant mise en production |

---

## 3. Les huit défauts à corriger en premier

### D-1 — Le contraste de l'orange (P0)

`--famma-orange: #FF7A00` est utilisé en **fond de CTA avec texte blanc** (`style.css:34-44`,
`.famma-sticky-cta__button`) et comme couleur d'accent textuelle. Calcul WCAG :

| Combinaison | Ratio | Seuil requis | Verdict |
|---|---|---|---|
| Blanc sur `#FF7A00` | **2,61:1** | 4,5:1 (texte) / 3:1 (grand texte) | ❌ échoue **les deux** |
| `#FF7A00` en texte sur blanc | **2,61:1** | 4,5:1 | ❌ (prix promo, liens) |
| `#FF7A00` en bordure/icône sur blanc | 2,61:1 | 3:1 (composant) | ❌ |
| Navy `#0B1F3A` sur `#FF7A00` | **6,31:1** | 4,5:1 | ✔ |
| Blanc sur `#B85300` (orange-700) | **4,91:1** | 4,5:1 | ✔ |

**Correctif** : dissocier l'orange *de surface* de l'orange *de texte*.
`--famma-orange` (#FF7A00) reste la couleur de remplissage ; on ajoute
`--famma-orange-700: #B85300` pour tout texte ou icône orange sur fond clair. Pour le CTA, deux
options défendables — **texte navy sur orange vif** (6,31:1, garde la vibration de la marque) ou
**texte blanc sur orange-700** (4,91:1, plus sobre). Recommandation : **navy sur orange vif**,
c'est le plus proche de l'identité et le plus lisible des deux.

Même problème sur le vert : blanc sur `--famma-success #1FA65A` = **3,16:1** → le CTA WhatsApp
(`.famma-wa-cta`, blanc gras) échoue. Ajouter `--famma-success-700: #157A45` (5,39:1).
Et `--famma-danger #D64545` en texte sur blanc = **4,38:1**, juste sous le seuil : les messages
d'erreur de checkout passent en `--famma-danger-700: #C13333` (5,54:1).

### D-2 — L'anneau de focus est invisible là où il compte (P0)

`style.css:47-54` pose `outline: 3px solid var(--famma-orange)`. Sur le CTA principal — qui est
orange — l'anneau disparaît. Sur fond blanc il reste sous le seuil de 3:1.
**Correctif** : anneau navy `#0B1F3A` doublé d'un liseré blanc
(`outline: 3px solid var(--famma-blue); box-shadow: 0 0 0 5px #fff;`), qui reste visible sur
clair comme sur orange. Un seul jeton : `--focus-ring`.

### D-3 — Aucune échelle typographique, aucun couple de polices (P0)

`--famma-font` délègue l'arabe à `Tahoma` ou à ce que l'appareil possède : le rendu Derja varie
d'un téléphone à l'autre, exactement ce que le §B8 interdit. Et sans jetons de taille, chaque
composant réinventera son `font-size` (`clamp()` apparaît déjà trois fois en dur dans
`style.css`).

**Recommandation** : **IBM Plex Sans Arabic** (SIL OFL, variable) pour les deux écritures — la
famille embarque un latin dessiné pour s'accorder à l'arabe, ce qui évite le désaccord de
graisse et de hauteur d'x typique des couples improvisés. Auto-hébergée en `woff2`, découpée par
`unicode-range` afin que les glyphes arabes ne se téléchargent qu'en AR, deux graisses au-dessus
de la ligne de flottaison (400 / 700), `font-display: swap`.
Replis assumés : *Inter + Noto Sans Arabic* (deux familles, métriques non harmonisées) ou
*Cairo* (arabe-first, latin plus faible). — **Décision propriétaire D-9.**

### D-4 — Le design system n'existe pas dans l'éditeur (P0) — **résolu le 2026-08-15**

Sans `theme.json`, la palette proposée par Gutenberg est celle de Kadence. Le propriétaire
composerait ses pages avec des couleurs hors marque, sans garde-fou.

**Écrire le fichier ne suffisait pas.** Kadence branche `edit_theme_json` en priorité 10 et
**remplace l'intégralité** des données theme.json par un tableau PHP figé, tant que le réglage
`theme_json_mode` reste désactivé — son défaut
(`kadence/inc/components/editor/component.php:219`). Le fichier du parent comme celui de l'enfant
étaient donc pure décoration. Constaté à l'exécution : le résolveur ne renvoyait que les 15
entrées codées en dur par Kadence, alors que le fichier enfant en déclarait 27 et décodait sans
erreur.

Deux conséquences pour la suite : **tout `theme.json` d'enfant de Kadence est inerte par défaut**
— il faut un filtre en priorité 20 (`famma_child_theme_json`) qui relit le fichier ; et la palette
enfant devait **reprendre les 15 emplacements `theme-palette1..15`** avant d'ajouter les couleurs
FAMMA, faute de quoi les blocs déjà composés sur l'accueil, qui les référencent par leur slug,
auraient perdu leur couleur.

Vérifié : palette 27 entrées (15 Kadence + 12 FAMMA), `fontSizes` 14, `spacingSizes` 5.

### D-5 — Le CTA collant recouvre le bas de page (P1)

`.famma-sticky-cta` est `position: fixed` sans compensation : sur mobile, la barre masque
définitivement les ~64 derniers pixels de la page — donc la fin de la description produit, et
potentiellement le premier lien du footer. **Correctif** : `--sticky-cta-h: 64px` et
`body.single-product { padding-block-end: calc(var(--sticky-cta-h) + env(safe-area-inset-bottom)); }`.
Le même jeton remplace le `84px` magique du décalage WhatsApp (`style.css:452-454`).

### D-6 — Trois éléments flottants se disputent le coin bas (P1)

Bouton WhatsApp + sélecteur de langue TranslatePress (poussé en `inset-inline-start` par rustine,
`style.css:315-326`) + CTA collant. Sur un écran de 360 px, cela consomme la zone du pouce.
**Correctif** : le sélecteur de langue **remonte dans l'en-tête** (c'était déjà la dette
identifiée en Phase 5), le WhatsApp flottant ne survit que hors fiche produit, et une **échelle
de z-index** (`--z-*`) remplace les `998` en dur.

### D-7 — Le design de l'accueil n'est pas versionné (P0)

Les sections `.famma-hero`, `.famma-chips`, `.famma-faq` ont un CSS versionné mais un **contenu
en base de données**. Une réinstallation, une migration ratée ou une manipulation dans l'éditeur
efface le travail sans trace Git, et aucune relecture de diff n'est possible.
**Correctif** : enregistrer ces sections comme **block patterns** dans
`famma-child/patterns/*.php`. Le design devient relisible, diffable, réinstallable — et le
propriétaire garde la liberté de composer par-dessus.

### D-8 — La confirmation de commande, angle mort du COD (P0)

En paiement à la livraison, le moment de vérité n'est pas le paiement : c'est **l'écran qui suit
la commande** et l'appel de confirmation. Rien n'est conçu pour lui aujourd'hui. Il doit
répondre, sans faire défiler : numéro de commande, **numéro de téléphone tel qu'il a été saisi**
(avec possibilité de le corriger), ce qui se passe ensuite (« nous vous appelons pour
confirmer »), le montant à préparer en espèces, et un lien WhatsApp direct.
Un écran de confirmation faible se paie en **taux de refus à la livraison** — le KPI le plus
coûteux du modèle COD, et déjà mesurable via les statuts `refused` de `famma-core`.

---

## 4. Socle : design system v1

### 4.1 Jetons à ajouter dans `tokens.css`

Aucun jeton existant n'est supprimé — on complète. Nommage stable, à répliquer **à l'identique**
dans les variables Figma si un designer intervient (contrat de nommage).

```
Couleur     famma-blue (+600, +50 fond bleuté)
            famma-orange (surface) · famma-orange-700 (texte/icône)
            famma-success (+700) · famma-warning (+700) · famma-danger (+700)
            famma-ink · famma-muted · famma-line · famma-bg · famma-white
Typo        fs-caption 12 · fs-small 14 · fs-body 16 · fs-h4 18
            fs-h3/h2/h1 fluides (clamp) · fs-price · fs-price-old · fs-badge · fs-button
            lh-tight 1.15 · lh-base 1.5 · lh-ar 1.7
            fw-regular 400 · fw-medium 500 · fw-bold 700
Espacement  sp-1…sp-8 (existant) + sp-10 40 · sp-12 48
Rayon       radius-sm 8 · radius (12, existant) · radius-lg 20 · radius-pill 999
Élévation   elev-1 cartes · elev-2 barres collantes/drawers · elev-3 modales
Mouvement   dur-fast 120ms · dur-base 180ms · ease-out
Profondeur  z-sticky-cta · z-float · z-header · z-drawer · z-modal · z-toast
Gabarit     container-max 1200 · gutter · sticky-cta-h · focus-ring
```

Deux règles non négociables : **plus aucune valeur littérale hors `tokens.css`** (déjà l'intention
du fichier, aujourd'hui contournée par les `clamp()` en dur), et **`16px` minimum sur tout
`input`** — en dessous, Safari iOS zoome automatiquement au focus et casse le checkout mobile.

### 4.2 Règles typographiques bilingues

| Règle | Raison |
|---|---|
| `:lang(ar) { line-height: var(--lh-ar); letter-spacing: 0; }` | l'interlettrage **casse les ligatures arabes** |
| Jamais `text-transform: uppercase` sur de l'arabe | sans effet, et signale un composant non testé |
| Chiffres occidentaux (0-9) pour tous les prix, dans les deux langues | cohérence des montants, lisibilité des TND |
| `lang` + `dir` sur chaque fragment de langue mixte | déjà pratiqué dans `functions.php:201` — à généraliser |
| Icônes directionnelles retournées par classe utilitaire, jamais le logo | `[dir="rtl"] .icon--dir { transform: scaleX(-1) }` |

---

## 5. Composants — inventaire priorisé

Le §B7 liste 24 composants. Tous ne se valent pas : classés par distance au chiffre d'affaires.

**P0 — sur le chemin de l'achat**
Button (3 variantes × 5 états) · Product card · Bloc prix (prix / ancien prix / économie /
badge) · Trust chips *(existe)* · Galerie produit · CTA collant *(existe)* · Sélecteur de
quantité · Champs de formulaire (input, select, radio, checkbox) + états d'erreur · Récapitulatif
de commande · Écran de confirmation · En-tête mobile · Navigation mobile basse · CTA WhatsApp
*(existe)* · États vides · Footer complet.

**P1 — découverte**
Carte catégorie · Grille boutique + tri · Filtres en bottom sheet · Recherche (champ, suggestions,
résultats, aucun résultat) · Fil d'Ariane · Pagination · Badge promo · Panier (drawer) ·
Accordéon fiche produit · Squelettes de chargement.

**P2 — dépendant de données réelles, à concevoir vide**
Avis + étoiles · Galerie UGC · Rails « nouveautés / meilleures ventes » · Modale · Onglets ·
Toasts.

> **Règle §58/§60 appliquée au design** : les composants P2 se conçoivent et se livrent, mais ne
> s'affichent que lorsque la donnée réelle existe. Un bloc « avis » avec zéro avis ne s'affiche
> pas — il ne s'affiche pas *rempli d'exemples*.

---

## 6. Écrans — état et cible

Les 28 écrans du §B33 traduits en artefacts WordPress réels.

| # | Écran | Artefact WP | Prio | État |
|---|---|---|---|---|
| 1-2 | Accueil (mobile / desktop) | pattern `famma/home-launch` + `famma/home-discovery` | P0 | ✔ état lancement — ✗ état catalogue rempli |
| 3-4 | Boutique | `woocommerce/archive-product.php` (surcharge enfant) | P1 | ✗ |
| 5-6 | Catégorie | même gabarit + en-tête de catégorie | P1 | ✗ |
| 7-8 | Fiche produit | hooks Woo depuis `functions.php` | P0 | partiel (réassurance + CTA collant) |
| 9-10 | Panier | `cart.php` ou drawer | P1 | ✗ |
| 11-12 | Checkout | checkout classique (ADR-003) | P0 | données ✔ / habillage ✗ |
| 13 | **Confirmation de commande** | `thankyou.php` | **P0** | ✗ — voir D-8 |
| 14 | Recherche | `search.php` + champ d'en-tête | P1 | ✗ |
| 15 | Catalogue vide | pattern accueil | P0 | ✔ |
| 16 | Panier vide | `cart-empty.php` | P1 | ✗ |
| 17 | 404 | `404.php` | P1 | ✗ |
| 18-25 | À propos, Contact, FAQ, Livraison, Retours, CGV, Confidentialité, Cookies | pages + pattern commun | P1 | squelettes §45, textes propriétaire |
| 26-28 | Promotions, Nouveautés, Meilleures ventes | archives filtrées | P2 | ✗ (données réelles requises) |

---

## 7. Parcours — la décision qui pèse le plus

Le §B34 pose le bon cadre : l'utilisateur arrive **d'une publicité**, sur une fiche produit, sans
connaître FAMMA. Le parcours par défaut de WooCommerce lui impose alors trois étapes :
*fiche → panier → checkout*. Chaque étape coûte.

Trois options, à trancher (**D-8 propriétaire**) :

| Option | Parcours | Gain attendu | Coût / risque |
|---|---|---|---|
| **A — standard** | fiche → panier → checkout | aucun | nul — c'est l'existant |
| **B — recommandée** | CTA « Commander maintenant » = ajout + redirection checkout ; « Ajouter au panier » en lien secondaire | supprime une étape entière du tunnel publicitaire | faible ; l'AOV multi-produits reste possible par le lien secondaire |
| **C — agressive** | formulaire nom + téléphone + gouvernorat **sur la fiche produit**, sans page checkout | pratique dominante du COD au Maghreb, tunnel le plus court | contourne le checkout Woo → toutes les règles métier (stock, statuts, UTM, CAPI) doivent être re-branchées ; risque de doublon avec `famma-core` |

Recommandation : **B maintenant**, C **seulement** si les données du tableau de bord montrent une
fuite massive entre *Add to Cart* et *Checkout*, et alors en réutilisant le checkout classique
comme cible de soumission — jamais en réimplémentant la commande (§27, §61).

---

## 8. Plan par lots

Chaque lot se termine par un **critère de sortie vérifiable** et un commit. Estimations
indicatives, un seul développeur.

### Lot 0 — Socle du système *(2-3 j)* — **partiellement livré le 2026-08-15**

| # | Tâche | État |
|---|---|---|
| 1 | Compléter `tokens.css` (§4.1) — palette étendue, échelle typo, z-index, élévation, mouvement, gabarit, focus | ✔ livré |
| 2 | Corriger **D-1** (contrastes) et **D-2** (focus) dans `style.css` **et** dans le CSS en ligne de `famma-core` | ✔ livré |
| 3 | Purger les valeurs littérales de `style.css` (tailles, ombres, durées, z-index) | ✔ livré |
| 4 | Corriger **D-5** (réserve de hauteur du CTA collant) et tokeniser le décalage WhatsApp | ✔ livré |
| 5 | Créer `theme.json` + filtre de réapplication | ✔ livré — voir D-4 |
| 6 | Couple typographique auto-hébergé | **bloqué** — décision D-9 + fichiers `woff2` absents |
| 7 | Sélecteur de langue dans l'en-tête (D-6) | reporté au Lot 4 — exige le balisage réel de TranslatePress |

**Vérifié à l'exécution le 2026-08-15** : palette 27 entrées dans l'éditeur (15 Kadence + 12
FAMMA) ; jeton d'accent contextuel correct au navigateur (`rgb(255,122,0)` sur le héros sombre,
`rgb(184,83,0)` sur fond clair) ; `phpcs` sans erreur sur `functions.php` ; `debug.log` sans
nouvelle entrée ; accueil en HTTP 200.

**Reste à prouver** : navigation clavier avec anneau visible sur le CTA orange, et rendu du CTA
principal — le catalogue est vide, donc aucune fiche produit n'existe encore pour l'observer.

### Lot 1 — Le chemin d'achat *(5-7 j)* — **entamé le 2026-08-15**

| # | Tâche | État |
|---|---|---|
| 1 | **Product card** (§B13) : image 1:1, titre 2 lignes, bénéfice, bloc prix, badge, CTA | à faire |
| 2 | **Bloc prix** : prix courant, ancien prix barré, économie calculée, badge de remise réelle | ✔ livré |
| 3 | **Fiche produit** (§B14-15) : accordéons, bénéfice principal en une ligne | partiel — quantité et prix faits, accordéons à faire |
| 4 | **Checkout** (§B18-19) : champs à 48px, réassurance avant validation, erreurs en `-700` | ✔ livré |
| 5 | **Écran de confirmation** (D-8) | ✔ livré |
| 6 | Parcours **B** du §7 | bloqué — décision D-8 |

L'écran de confirmation reprend le workflow réel de `famma-core`
(`pending-confirm → confirmed → shipped → delivered`), pas une promesse commerciale, et
n'annonce **aucun délai** (§60). Il rappelle le téléphone tel qu'il a été saisi : c'est le seul
champ dont une faute de frappe fait échouer toute la commande, et le client n'avait nulle part
où s'en apercevoir.

Découvert en le construisant : le `<h1>` affichait « Checkout ». La confirmation est un point de
terminaison de la page Checkout et en héritait le titre. Le filtre officiel de WooCommerce est
inopérant ici — `wc_page_endpoint_title()` exige `in_the_loop()`, or **Kadence rend son bandeau
de titre hors boucle**. À retenir pour toute autre page de terminaison (`view-order`,
`edit-address`) : elles portent le même défaut.

Le badge et l'économie sont **calculés depuis le prix barré et le prix promo réels**. Un produit
sans promo ne porte aucun badge, et aucun compte à rebours n'accompagne l'offre (§B25, §60).

Le prix ne prend pas l'orange : `#FF7A00` en texte sur blanc vaut 2,61:1. La hiérarchie se fait
par la graisse et la taille — l'orange reste au CTA.

**Sortie** : parcours pub → fiche → commande → confirmation réalisé au doigt sur un écran de
360 px, en FR et en AR, sans zoom ni défilement horizontal ; règle §12 intacte (la commande n'est
payée qu'à `delivered`).

### Lot 2 — Découverte *(4-5 j)*
Grille boutique, tri, filtres en **bottom sheet** avec piège à focus et fermeture par `Échap`,
cartes catégories, recherche accessible depuis l'en-tête, état « aucun résultat » utile
(suggestions + retour boutique), variante « accueil catalogue rempli » du pattern d'accueil.

**Sortie** : depuis n'importe quelle page, un produit est atteignable en **≤ 3 interactions**.

### Lot 3 — Confiance et contenu *(2-3 j)*
Footer complet 4 colonnes (§B28), section « Pourquoi FAMMA ? » (§B26) réutilisable en pattern,
pages information avec un gabarit commun, charte photo produit (§10 ci-dessous), composants avis
livrés **masqués tant qu'aucun avis réel n'existe**, suppression des encarts `.famma-todo`.

**Sortie** : plus aucun `.famma-todo` en production ; chaque page information a un contenu validé
par le propriétaire ou n'est pas publiée.

### Lot 4 — Bilingue et RTL *(2-3 j)*
Sélecteur en en-tête ≥ 48 px, passe RTL **composant par composant** (pas un `direction: rtl`
global — §B21), règles typographiques arabes du §4.2, position de la devise et format des
montants dans les deux locales, icônes directionnelles.

**Sortie** : capture de chaque composant en FR et en AR ; aucun débordement, aucune ligature
brisée, aucun chevron pointant du mauvais côté.

### Lot 5 — États *(2 j)*
Squelettes de chargement, panier vide, 404, rupture de stock, produit indisponible, erreur réseau,
erreur de formulaire (§B31).

**Sortie** : chacun des 9 états est atteignable en local et fait l'objet d'une capture.

### Lot 6 — Recette *(2-3 j)*
1. Accessibilité : checklist §9.
2. Performance : budget §11, mesuré sur profil mobile lent.
3. Rendu vérifié à **320 / 360 / 375 / 390 / 414 / 768 / 1024 / 1280 / 1440**, LTR et RTL.
4. Instrumentation KPI §12.

**Sortie** : le rapport de recette est vert sur les trois axes, sinon le lot n'est pas clos.

**Total indicatif : 17 à 23 jours.** Les lots 0 et 1 (≈ 8-10 j) suffisent à ouvrir la boutique
avec les trois premiers produits ; les suivants accompagnent la montée du catalogue.

---

## 9. Recette accessibilité (§B30, §44)

| Contrôle | Seuil |
|---|---|
| Contraste texte | ≥ 4,5:1 (≥ 3:1 si ≥ 24 px ou 18,66 px gras) |
| Contraste composants (bordures, icônes, états) | ≥ 3:1 |
| Cible tactile | ≥ 48 px (le brief dit 44 ; le §10 du projet impose 48 — on garde 48) |
| Focus | visible sur **tous** les fonds, jamais supprimé |
| Formulaires | `<label>` réel, erreur liée par `aria-describedby`, `autocomplete`, `inputmode` |
| Clavier | drawer et bottom sheet : piège à focus, fermeture `Échap`, retour du focus au déclencheur |
| Structure | un seul `h1`, ordre des titres respecté, points de repère ARIA, lien d'évitement |
| Mouvement | `prefers-reduced-motion` honoré (déjà fait sur le CTA collant) |
| Langue | `lang` et `dir` corrects sur chaque fragment |

---

## 10. Charte photo produit (à appliquer dès le 1ᵉʳ produit)

Le brief n'en parle pas, c'est pourtant ce qui décide de la qualité perçue avant tout pixel de
design. Aucune photo de stock d'un produit non détenu (§60).

| Règle | Valeur |
|---|---|
| Ratio | 1:1, **1400 × 1400 px** minimum, `webp` |
| Image 1 | produit seul, fond uni clair, cadrage identique sur tout le catalogue |
| Image 2 | produit **en main** ou en usage — donne l'échelle, première question du client |
| Images 3-5 | détail, contenu de la boîte, dimensions annotées |
| Vidéo | 1 verticale 9:16 de 5-10 s, réutilisable en publicité TikTok/Reels |
| Interdit | filigrane d'un fournisseur, capture AliExpress, montage de prix |

---

## 11. Budget de performance (§B36, §23, §50)

Cible : Android milieu de gamme, réseau 4G tunisien encombré.

| Métrique | Budget |
|---|---|
| LCP | ≤ 2,5 s (4G) · ≤ 4 s (3G) |
| CLS | ≤ 0,1 — risques : images sans dimensions, CTA collant, bandeau de langue |
| INP | ≤ 200 ms |
| JS transféré (fiche produit) | ≤ 150 Ko compressés |
| CSS | ≤ 60 Ko compressés |
| Polices | 2 graisses au-dessus de la ligne de flottaison, préchargées, `swap`, arabe séparé par `unicode-range` |
| Scripts de tracking | chargés après interaction ou en `requestIdleCallback` — jamais bloquants |

Points de vigilance propres à cette installation : Kadence charge des blocs optionnels à
désactiver ; TranslatePress ajoute des requêtes par page (mise en cache indispensable) ; trois
pixels publicitaires cohabitent (`famma-core.php:1086-1090`).

---

## 12. Mesure — relier le design aux KPI (§B35)

Le tunnel du §B35 se branche sur l'instrumentation déjà présente.

| Étape | Événement | Où |
|---|---|---|
| Acquisition | UTM → commande | ✔ `stamp_utm_on_order` |
| Landing → Product View | `view_item_list`, `select_item`, `view_item` | à ajouter (Lot 6) |
| Intention | `add_to_cart` | à ajouter |
| Checkout | `begin_checkout` | à ajouter |
| Commande | `order_placed` (client) | à ajouter — **distinct** de `Purchase` |
| Conversion réelle | `Purchase` **serveur**, à `delivered` | ✔ `fire_purchase_on_delivery` + CAPI |
| Refus / retour | statuts `refused`, `returned` | ✔ statuts, à remonter dans le tableau de bord |

L'écart entre `order_placed` et `Purchase` **est** le taux de refus. C'est la métrique qui juge
la qualité du design de confiance (§B26, D-8) — pas le taux de clic. À croiser par gouvernorat :
un taux de refus concentré géographiquement est un problème de livraison, pas d'interface.

---

## 13. Décisions propriétaire à trancher

Numérotation à la suite des ADR existantes (D1-D7 dans `DECISIONS.md`).

| # | Décision | Bloque |
|---|---|---|
| **D-8** | Parcours d'achat : A / **B** / C (§7) | Lot 1 |
| **D-9** | Couple typographique : **IBM Plex Sans Arabic** / Inter+Noto / Cairo | Lot 0 |
| **D-10** | CTA : texte **navy sur orange vif** ou blanc sur orange-700 (§3, D-1) | Lot 0 |
| **D-11** | Taxonomie des catégories — dépend des 3 premiers produits (§B24) | Lot 2 |
| **D-12** | Politique promo : quand afficher « économie », jamais de faux minuteur (§B25) | Lot 1 |
| **D-13** | Charte photo : qui produit les visuels, avec quel fond (§10) | Lot 1 |
| **D-14** | Avis : activer les avis WooCommerce dès l'ouverture ou après N livraisons (§B27) | Lot 3 |
| **D-15** | Numéro WhatsApp réel + horaires affichés (§B20) | Lot 1 |
| **D-16** | Textes légaux : CGV, retours, confidentialité, cookies — fournis, pas inventés (§45) | Lot 3 |

---

## 14. Livrables

Il n'y a pas de Figma dans ce projet, et le §25 interdit le constructeur de pages. Le design
system **est du code** :

| Livrable | Forme |
|---|---|
| Jetons | `famma-child/assets/css/tokens.css` + `theme.json` (source unique) |
| Composants | CSS de `famma-child` + surcharges de gabarits WooCommerce |
| Écrans réutilisables | `famma-child/patterns/*.php` (block patterns versionnés) |
| Spécification | `docs/DESIGN-SYSTEM.md` mis à jour à chaque lot |
| Recette | captures LTR/RTL par point de rupture + rapport contraste/perf |

Si un designer externe intervient : il travaille **autour du logo existant** (§B6), et ses
variables Figma reprennent **exactement** les noms de jetons du §4.1 — sinon l'intégration
réintroduit des valeurs en dur.

---

## 15. Hors périmètre de ce plan

Aucun contenu inventé — produits, avis, témoignages, statistiques, textes légaux (§58, §60).
Aucun minuteur de promotion factice (§B25). Aucune refonte du logo (§B6, §7). Aucune modification
du cœur WordPress/WooCommerce ni du thème parent. Aucune action irréversible sans feu vert :
`git push`, suppression de données, achat de domaine.
