# Rapport UX/UI — Fiche produit fammaminnou.tn

23 septembre 2026, après le déploiement du jour (`DEPLOIEMENT-2026-09-23.md`) · fiche étudiée : **Visseuse Perceuse Sans Fil 21V** (`/produit/visseuse-perceuse-sans-fil-21v/`), et les 15 fiches du catalogue relevées par l'API publique · mobile d'abord, puis desktop et arabe.

Ce rapport **approfondit** la section 2.3 de `audit-2026-09-23/audit-ux.md`, sans la recopier. Il reprend ses numéros (UX-xx) quand un constat est le même, et il donne pour chaque défaut **la cause dans le code** : fichier, ligne et correctif.

---

## 0. Méthode et limites

| Point | Détail |
|---|---|
| Outil | Chrome du propriétaire (extension Claude). Mobile émulé par une vue de 390 px (**375 px utiles**, barre de défilement déduite), hauteur visible de **674 px** (téléphone avec barres du navigateur) et de **844 px** (iPhone 12–15 sans barres). Desktop : 1 536 × 674. |
| Mesures | Positions, tailles, couleurs, directions et recouvrements lus dans le DOM (`getBoundingClientRect`, `getComputedStyle`), règles CSS gagnantes lues dans `document.styleSheets`, temps lus avec l'API Performance. Captures d'écran à l'appui. |
| Code | Graphe graphify (à jour, 23/09 16:01) pour localiser, puis lecture ciblée de `inc/product.php`, `functions.php`, `style.css`, `assets/css/product.css`, `assets/js/sticky-cta.js` et `famma-core/includes/express-order.php`. Le CSS n'est pas indexé par le graphe (graph miss) : il a été cherché directement. |
| Non fait | Aucun formulaire rempli ni envoyé, aucune commande, aucun ajout au panier. **DirectAdmin n'a pas été ouvert** : le code en ligne est celui du dépôt (déployé aujourd'hui), l'accès serveur n'apportait rien à l'analyse UX. Pas de test sur un vrai téléphone (le rendu tactile et le clavier virtuel restent à vérifier). |
| Temps de réponse | Une mesure avec contournement du cache, depuis le poste du propriétaire. Valeur indicative. |

---

## 1. Synthèse

### Note de la fiche produit : **5,0 / 10**

| Critère | /10 | En une ligne |
|---|---|---|
| Premier écran mobile | **4** | Ni nom ni prix sans défiler : le prix est à 973 px (1,44 écran sur 674 px de haut). |
| Galerie | **5,5** | 6 belles photos 1:1 en WebP, mais les vignettes mordent 32 px sur la photo, à 50 % d'opacité, sur 2 rangées. |
| Prix et offre | **5** | Prix orange à 2,46:1 ; sur la page FR, le symbole « د.ت » s'affiche **avant** le nombre ; aucune caractéristique clé près du prix. |
| Tunnel (formulaire express + barre) | **6** | Bien codé, mais 1 191 px de haut, 7 champs, arrivée sous l'en-tête, deux quantités, deux tunnels. |
| Réassurance et confiance | **5** | Messages vrais et bien écrits, mais répétés 5 fois, sans délai de livraison, avec « Avis (0) ». |
| Contenu | **7** | Descriptions riches en derja, fiche technique, contenu du coffret. Mais la description s'ouvre sur 2 492 px. |
| Ergonomie mobile | **5** | Cibles à 48 px, mais 44 px de marge de chaque côté, et le prix de la barre caché par le sélecteur de langue. |
| Desktop | **3** | Galerie et résumé écrasés à 280 px chacun dans un conteneur de 1 200 px. |
| Arabe / RTL | **5,5** | Miroir RTL propre, mais titre, rayon et fil d'Ariane restent en français ; derja et arabe standard se doublent. |
| Accessibilité | **5** | Contrastes du prix et d'« Ajouter au panier » insuffisants, flèches de 32 px nommées « Previous / Next » en anglais. |
| Performance ressentie | **4** | TTFB de 2,2 s, 74 requêtes dont 29 scripts. |

La note est plus basse que le 6/10 de l'audit du matin parce qu'elle compte aussi le desktop, l'accessibilité et la vitesse de la fiche elle-même.

### Ce qu'il faut retenir

1. **Une seule erreur de CSS explique trois défauts visibles.** Les règles de la fiche (`product.css`) ont une spécificité de (0,3,1), celles de Kadence (0,3,2) : Kadence gagne. D'où les colonnes desktop de 280 px (48 % d'une colonne de 583 px), les vignettes qui chevauchent la photo et leur opacité de 50 %. Le correctif tient en une dizaine de lignes.
2. **Le premier écran mobile est gaspillé**, pas trop petit. Le prix peut remonter d'environ 280 px **sans rien retirer** : un seul rembourrage au lieu de deux, les vignettes sur une rangée, le prix placé avant la description courte, un fil d'Ariane sur une ligne.
3. **Le geste d'achat est dilué.** Formulaire express, puis une deuxième quantité, « Ajouter au panier », le favori et le bouton WhatsApp : 4 boutons pleins en 300 px. Un visiteur venu d'une publicité doit trouver **un** geste évident.
4. **Deux défauts connus (UX-13, UX-14) sont toujours en ligne** et touchent la barre collante, l'élément qui vend le plus sur mobile : son prix est caché, et le formulaire s'ouvre sous l'en-tête.

### Les 5 actions les plus rentables

| # | Action | Constats | Effort |
|---|---|---|---|
| 1 | Corriger la spécificité CSS de la galerie et du résumé (desktop et vignettes) | FP-01, FP-02 | S (≈ 1 h) |
| 2 | Désactiver le sélecteur flottant de TranslatePress, puis ajouter `scroll-margin` au formulaire express | FP-04, FP-05 | S (réglage + 3 lignes) |
| 3 | Réorganiser le premier écran mobile : marges à 16 px, vignettes sur une rangée, prix avant l'extrait | FP-03 | S–M |
| 4 | Afficher le prix en bleu nuit et en « DT » sur la version FR | FP-06 | S |
| 5 | Un seul tunnel : formulaire express seul, « Ajouter au panier » en lien secondaire, une seule quantité | FP-07 | M |

---

## 2. Ce qui fonctionne (à préserver)

- **Galerie** : 6 photos carrées en WebP 600 px, `srcset` de 8 tailles, **`fetchpriority="high"` sur la première image**. Le carrousel ne s'initialise pas pour un produit à une seule image (`product.php:667`), comme prévu.
- **Formulaire express** (`express-order.php`) : champs de 48 px en 16 px (pas de zoom automatique sur iOS), `autocomplete` renseigné partout (`given-name`, `tel`, `address-level1`…), `type="tel"` + `inputmode="tel"`, 24 gouvernorats, total « à payer à la livraison » juste au-dessus du bouton. Le jeton de commande est servi à la demande pour que la fiche reste cachable (`express-order.php:103-127`) : c'est le bon choix pour les pages d'atterrissage publicitaires.
- **Barre collante** (`sticky-cta.js`) : elle s'efface quand le formulaire est à l'écran (`IntersectionObserver`), elle reste visible si le script échoue, elle pose le curseur dans le premier champ vide sans casser le défilement (`preventScroll`), et elle respecte `prefers-reduced-motion`.
- **Honnêteté** : aucun minuteur, aucun « plus que N en stock », aucun faux avis. L'onglet Livraison ne dit que du vrai (24 gouvernorats, 0 TND, espèces).
- **Contenu** : 1 000 à 2 000 caractères de description en derja, avec fiche technique (`المواصفات التقنية`) et contenu du coffret, sur les 15 fiches.
- **Corrigé aujourd'hui** : WhatsApp en `wa.me/21656261412` et `tel:+216…` (UX-01), sélecteur de quantité − / + à 48 px (UX-40), produit test « Prd 1 » absent de l'API publique (UX-05).

---

## 3. Radiographie du parcours mobile

Positions depuis le haut de la page, sur 375 px utiles.

| Élément | Position | Écrans (674 px) | Écrans (844 px) |
|---|---|---|---|
| Bandeau + en-tête collant | 0–143 px | — | — |
| *Vide + fil d'Ariane sur 2 lignes* | 143–248 px | — | — |
| Galerie (photo 287 px + 2 rangées de vignettes) | 248–658 px | 0,37 | 0,29 |
| Badge « Nouveau » | 710 px | 1,05 | 0,84 |
| **H1** | **752 px** | **1,12** | 0,89 |
| Description courte (96 px) | 832 px | 1,23 | 0,99 |
| **Prix** | **973 px** | **1,44** | **1,15** |
| Réassurance (3 lignes) | 1 039 px | 1,54 | 1,23 |
| Formulaire express (1 191 px de haut) | 1 288 px | 1,91 | 1,53 |
| Bouton « Je confirme ma commande » | 2 406 px | 3,57 | 2,85 |
| 2ᵉ quantité, « Ajouter au panier », favori, WhatsApp | 2 520–2 761 px | 3,7–4,1 | 3,0–3,3 |
| Bloc « 4 bénéfices » (381 px) | 2 898 px | 4,3 | 3,4 |
| Accordéon Description, **ouvert** (2 492 px) | 3 337 px | 4,95 | 3,95 |
| Accordéon **Livraison** | **5 927 px** | **8,8** | 7,0 |
| Produits similaires (2) | 6 043 px | 9,0 | 7,2 |
| **Hauteur totale** | **8 906 px** | **13,2** | 10,6 |

Desktop (1 536 px) : galerie de 280 × 403 px à gauche, résumé de 280 px à 617 px de là, **~600 px de blanc** au milieu. Le formulaire express, enfermé dans cette colonne de 280 px, mesure 1 131 px.

---

## 4. Constats détaillés

Gravité : **Haute** = freine directement la commande ou la lisibilité ; **Moyenne** = coûte de la confiance ou de l'effort ; **Basse** = finition. Effort : S < ½ jour, M = 1 à 3 jours.

### 4.1 Mise en page et galerie

**FP-01 — Desktop : deux colonnes de 280 px dans un conteneur de 1 200 px** · Haute · S · (UX-15)
- *Constat* : la grille de `div.product` a bien deux colonnes de 583 px (`product.css:50-53`), mais la galerie et le résumé n'en occupent que 48 %.
- *Cause* : Kadence (`woocommerce.min.css`) impose `.woocommerce div.product div.images { float: left; width: 48% }`, et la même chose pour `div.summary`. Sa spécificité est de (0,3,2). Les règles du thème enfant, `.famma-product div.product .woocommerce-product-gallery` (`product.css:69-73`) et `.famma-product div.product .summary` (`product.css:148-155`), n'ont que (0,3,1) : leur `inline-size: 100%` et leur `float: none` sont ignorés. Vérifié : `float` calculé = `left`, largeur = 279,8 px.
- *Correctif* : viser `div.images` et `div.summary` pour égaliser la spécificité (le thème enfant est chargé après Kadence) — voir l'annexe A.1.

**FP-02 — Vignettes posées sur la photo, pâles, sur deux rangées** · Haute · S
- *Constat* : la première rangée de vignettes commence à 504 px alors que la photo finit à 536 px : **32 px de chevauchement**. Les vignettes inactives sont à **50 % d'opacité**. 6 vignettes tiennent sur 2 rangées (4 + 2), encadrées par des flèches.
- *Cause* : même guerre de spécificité. Kadence, `.woocommerce div.product div.images .flex-control-thumbs` (0,4,2), impose `margin: -32px 0 0`, `max-width: 94%`, `transform: translate(-50%)` et `display: flex`, puis `opacity: .5` sur les images. Le thème enfant, `.famma-product .flex-control-thumbs` (0,2,0) (`product.css:90-118`), perd sur tous ces points : sa grille de 5 colonnes et son `opacity: 1` ne s'appliquent pas.
- *Correctif* : annexe A.1. Sur mobile, **une seule rangée défilante** de vignettes de 56 px, ou des points de pagination, et le glissement du doigt sur la photo.

**FP-03 — Premier écran mobile sans nom ni prix** · Haute · S–M · (UX-12)
- *Constat* : sur un téléphone avec ses barres de navigateur (674 px visibles), le premier écran ne montre que la galerie. Le H1 arrive à 1,12 écran, le prix à 1,44.
- *Causes cumulées* :
  1. **Deux rembourrages empilés** : Kadence `.content-container.site-container` (24 px) + `.famma-container.famma-product` (20 px) = **44 px de chaque côté**. Le contenu fait 287 px sur 375, soit 23 % de la largeur perdus, et la photo carrée perd 56 px de haut.
  2. **48 px de vide** sous l'en-tête, puis un fil d'Ariane sur 2 lignes (42 px), qui répète le nom du produit.
  3. **Vignettes sur 2 rangées** (138 px, voir FP-02).
  4. **La description courte passe avant le prix** : `famma_child_product_reorder_summary()` place l'extrait en priorité 11 et le prix en 12 (`product.php:37-41`). 96 px de texte en derja s'intercalent entre le nom et le prix.
  5. Le badge « Nouveau » prend une ligne de 43 px au-dessus du H1.
- *Budget estimé après correctifs* : en-tête 143 px → fil d'Ariane sur une ligne (32 px) → photo de 343 px → vignettes sur une rangée (72 px) → H1 vers **606 px** → prix vers **690 px**. Soit **−280 px (−29 %)** : le H1 entre dans le premier écran à 674 px, le prix dans celui à 844 px. Estimation à confirmer au rendu.

**FP-12 — Bouton WhatsApp flottant posé sur le contenu** · Moyenne · S
- *Constat* : la pastille verte de 56 px (x = 303–359, juste au-dessus de la barre collante) couvre en permanence la fin des lignes à droite : « Commande sans créer de compt… », les champs du formulaire, le texte de la description. En arabe, elle passe à gauche, sur le début des lignes.
- *Correctif* : sur la fiche mobile, **mettre WhatsApp dans la barre collante** (prix · icône WhatsApp 48 px · « Commander ») et masquer la pastille tant que la barre est affichée. Le bouton « Une question sur ce produit ? » de la fiche (à 3,2 écrans) reste en place.

### 4.2 Prix et offre

**FP-06 — Prix peu lisible, symbole inversé sur la page FR** · Haute · S · (UX-22)
- *Contraste* : prix en `#FF7A00` sur blanc, **2,46:1** (38 px en desktop, 30 px en mobile). Le DESIGN-SYSTEM réserve l'orange aux surfaces, pas au texte.
- *Direction* : dans le HTML, le texte est « 79,000 د.ت ». Mais dans un paragraphe LTR, l'algorithme bidirectionnel traite un nombre suivi de lettres arabes comme un bloc de droite à gauche. **Le symbole s'affiche donc à gauche du nombre** : mesuré à x = 44 px pour le symbole, x = 95 px pour le nombre. Même inversion dans la barre collante et sur les cartes « Produits similaires ». Un lecteur francophone voit « د.ت 79,000 ».
- *Correctif* : prix en bleu nuit (`--famma-blue`, 6,32:1) ; symbole « **DT** » sur la version FR (usage courant des boutiques tunisiennes), « د.ت » sur la version AR ; `unicode-bidi: isolate` sur le symbole pour les deux langues (annexe A.4).

**FP-18 — « Nouveau » sur 15 fiches sur 15** · Basse · S
- La règle des 30 jours (`product.php:118-124`) marque aujourd'hui tout le catalogue, et le badge ne distingue donc rien. Il s'éteindra de lui-même, pour tous les produits en même temps. À trancher : le poser **sur la photo** (il libère une ligne du résumé) et réduire la fenêtre (par exemple 14 jours), ou le réserver aux arrivages.

**Opportunité — « L'essentiel » en 3 puces sous le prix**
La fiche technique existe déjà (`famma_child_product_spec_rows()`, `product.php:406`) : 21 V, 1 800 tr/min, moteur brushless, mandrin 10 mm. Trois puces courtes tirées de ces **données réelles**, entre le prix et le formulaire, disent ce qu'on achète mieux qu'un paragraphe. Rien n'est inventé : si une fiche n'a pas de spécifications, le bloc n'apparaît pas.

### 4.3 Tunnel de commande sur la fiche

**FP-04 — Le prix de la barre collante est caché** · Haute · S (réglage) · (UX-13)
- Le sélecteur flottant de TranslatePress (`z-index: 99999`, 119 × 55 px) se pose **sur la moitié de la barre qui porte le prix** : à gauche en FR, à droite en AR, avec un drapeau saoudien pour l'arabe. Il fait doublon avec « FR | عربي » du bandeau.
- *Correctif* : TranslatePress → Sélecteur de langue → désactiver le sélecteur flottant (déjà listé au §4.3 du journal de déploiement).

**FP-05 — Le formulaire s'ouvre sous l'en-tête collant** · Haute · S · (UX-14)
- *Constat, mesuré après un appui sur la barre* : titre « Commander maintenant » à −122 px (hors écran), libellé « Prénom » à 17 px et champ à 40–88 px, **sous l'en-tête de 77 px**. Le curseur clignote dans un champ que le client ne voit pas.
- *Cause* : `sticky-cta.js:72` fait `express.scrollIntoView({ block: 'start' })` sur `.famma-express__form`, sans `scroll-margin`. Le titre du formulaire, qui est au-dessus de cet élément, reste hors cadre.
- *Correctif* : défiler vers la section `.famma-express`, avec le titre, et lui donner `scroll-margin-block-start: calc(77px + 16px)` (annexe A.3).

**FP-07 — Deux tunnels, deux quantités, quatre boutons pleins** · Haute · M · (UX-21, UX-33)
- *Constat* : sous le bouton « Je confirme ma commande » viennent une **deuxième quantité** (− 1 +, 48 px), « En stock », « **Ajouter au panier** » (pleine largeur, blanc sur orange, 2,61:1), le favori (56 px, plus gros que le bouton principal) et « Une question sur ce produit ? » (vert, 48 px). Le formulaire a sa propre quantité, en champ numérique. Le client ne sait plus laquelle compte, ni quel bouton est le bon.
- *Correctif* : un seul geste principal, le formulaire express. « Ajouter au panier » devient un lien texte secondaire (« ou ajouter au panier »), sans sa quantité. Le favori passe à 48 px ou disparaît de la fiche. Le formulaire garde **une** quantité, en − / + de 48 px comme l'autre.

**FP-08 — Formulaire long, téléphone non validé, promesse contredite** · Moyenne-Haute · S + M · (UX-19, UX-20)
- *Longueur* : 1 191 px, soit 1,8 écran. 7 saisies obligatoires (Prénom, Nom, Téléphone, Gouvernorat, Ville/Délégation, Adresse) plus la case CGV, avec un pas de **117 px par champ** (libellé, champ de 48 px, grands espacements).
- *Téléphone* : ni `pattern` ni `maxlength` ; « 12 » passe le navigateur et l'erreur n'arrive qu'après l'envoi. L'indication « 2X XXX XXX » exclut les numéros en 4, 5 et 9 (celui de la boutique commence par 5).
- *Promesse* : le bloc bénéfices dit « Nom, téléphone, adresse. **Rien de plus à remplir.** » (`product.php:256`), alors que le formulaire demande 6 champs et une case.
- *Correctif rapide (S)* : indication « XX XXX XXX », validation à la sortie du champ (8 chiffres commençant par 2, 4, 5 ou 9, espaces et +216 tolérés) avec un message relié par `aria-describedby`, pas vertical ramené vers 88 px.
- *Correctif de fond (M, décision propriétaire)* : « Nom complet » au lieu de Prénom + Nom, Ville facultative (le gouvernorat suffit à répartir les colis). Le formulaire passe à 4 champs, et la promesse devient vraie. À valider avec la personne qui confirme les commandes par téléphone.

**FP-15 — Libellés qui changent d'une zone à l'autre** · Moyenne · S
- FR : barre « Commander », formulaire « Je confirme ma commande / **أطلب توا** » (le sous-titre veut dire « commande maintenant », il ne traduit pas le bouton), puis « Ajouter au panier ».
- AR : barre « أطلب | أطلب توا », bouton « أكّد الطلب / أطلب توا », panier « أطلب » : le même verbe sert à trois actions différentes.
- *Correctif* : barre et titre « Commander / أطلب توا » ; bouton d'envoi « Confirmer ma commande / أكّد طلبيتك » ; un seul libellé par langue dans la barre.

### 4.4 Réassurance et confiance

**FP-09 — Réassurance répétée cinq fois, mais sans délai** · Moyenne · S + propriétaire · (UX-29)
- *Répétitions* : bandeau (4 promesses), liste sous le prix (3), chapeau du formulaire (« Paiement à la livraison. Sans créer de compte. Livraison offerte… »), bloc « 4 bénéfices » (381 px, `product.php:241-281`), onglet Livraison. C'est le même message cinq fois, alors que la première question d'un client COD, « **quand est-ce que je reçois ?** », n'a de réponse nulle part.
- *Bug de mise en page* : dans la liste sous le prix, la puce verte de « Livraison offerte, partout en Tunisie » est **seule sur sa ligne**, le texte passe dessous. La cause est `flex-wrap: wrap` sur `.famma-reassure__item` (`style.css:665-672`) : dès que le texte FR dépasse la largeur restante, il passe à la ligne sans sa puce. Annexe A.5.
- *Correctif* : une seule liste compacte sous le prix (3 lignes, dont **le délai réel fourni par le propriétaire**) ; le bloc « 4 bénéfices » passe en bas de page ou disparaît de la fiche mobile.

**FP-13 — « Avis (0) » et formulaire d'avis ouvert à tous** · Moyenne · S · (UX-31)
- L'accordéon affiche « Avis (0) » et, ouvert, demande note, avis, nom et e-mail à n'importe quel visiteur. Il dit « personne n'a acheté » et laisse la porte ouverte au spam.
- *Correctif* : WooCommerce → Produits → avis réservés aux « propriétaires vérifiés », et accordéon masqué tant qu'il n'y a aucun avis.

**FP-21 — Aucune preuve tangible sur la fiche** · Moyenne · propriétaire
- Aucune vidéo (la section est déployée mais vide), aucun avis, aucun lien vers les pages Facebook, Instagram ou TikTok. En COD, le visiteur vérifie la page de la marque avant de commander.
- *Correctif, sans rien inventer* : vidéos **réelles** du produit (la section `Product_Video` est prête), liens sociaux réels (réglage `FAMMA_SOCIAL_*`), puis de vrais avis d'acheteurs vérifiés au fil des livraisons.

Rappels hors fiche mais visibles dessus : « **Satisfait ou remboursé** » reste dans le bandeau desktop sans politique de retour (UX-04), et la case CGV obligatoire renvoie à un squelette (UX-03).

### 4.5 Contenu et lecture

**FP-10 — La description s'ouvre sur 3,7 écrans** · Moyenne · S
- L'accordéon Description est ouvert d'office (`woocommerce/single-product/tabs/tabs.php:80`, premier élément `open`) : **2 492 px**. Il repousse l'accordéon Livraison à 8,8 écrans. Son titre est doublé : l'en-tête de l'accordéon dit « Description », et WooCommerce ajoute un H2 « Description » juste dessous.
- *Correctif* : sur mobile, accordéons fermés, ou description tronquée à environ 400 px avec « Lire la suite ». Livraison passe juste après la Description. H2 retiré par le filtre `woocommerce_product_description_heading`.

**FP-11 — Derja affichée de gauche à droite sur la version FR** · Moyenne · S · (UX-23)
- La description courte, écrite en derja, est rendue en `direction: ltr` : « Visseuse برو 21V بموتور Brushless… » commence à gauche, la ponctuation se déplace et l'ordre de lecture se casse. Même défaut sur l'extrait des cartes « Produits similaires ».
- *Piège* : `dir="auto"` **ne suffit pas**, car le texte commence par un mot français (« Visseuse »), et `auto` en déduit LTR. Il faut décider la direction selon la **majorité** de lettres arabes ou latines (petit utilitaire PHP, annexe A.6), ou prévoir un champ « langue du texte » sur le produit.

**FP-14 — Version arabe incomplète** · Moyenne · M · (UX-37)
- H1, fil d'Ariane et rayon restent en français (« Visseuse Perceuse Sans Fil 21V », « Outils & Bricolage »). L'URL devient `/ar/product/…`, un slug anglais.
- Doublons : la liste sous le prix affiche « الدفع كي يوصلك » **et** « الدفع عند التوصيل », c'est-à-dire la même chose en derja puis en arabe standard. La troisième ligne n'a pas d'équivalent arabe dans la version FR.
- Registre : la case CGV est en arabe littéraire (« لقد قرأتُ الشروط والأحكام وأوافق عليها لهذا الموقع ») au milieu d'un formulaire en derja.
- *Correctif* : traduire dans TranslatePress les titres produits, les rayons et le slug `produit` ; dans la version AR, afficher une seule ligne par promesse ; harmoniser en derja.

### 4.6 Accessibilité, produits liés, performance

**FP-16 — Flèches de galerie à 32 px, nommées en anglais** · Basse-Moyenne · S
- `a.flex-prev` / `a.flex-next` : 32 × 32 px (cible minimale du projet : 48 px) ; textes « Previous » et « Next » de FlexSlider, lus en anglais par les lecteurs d'écran sur les pages FR et AR.
- *Correctif* : `prevText` / `nextText` traduits dans `famma_child_gallery_carousel_options()` (`product.php:675`) et zone de 48 px (annexe A.7).

**FP-17 — Produits similaires pauvres** · Basse · S · (UX-06)
- Seulement 2 produits, dont le **doublon** « Pince de Câblage Pro » (deux fiches publiées, 25 DT chacune). Cartes sur une colonne de 540 px de haut, boutons « Commander » en icône de 48 × 34 px.
- *Correctif* : supprimer le doublon (avec une redirection 301), afficher 4 produits sur 2 colonnes en carte compacte, boutons de 48 px de haut.

**FP-19 — Fiche lente à servir** · Haute (impact) · M · (UX-08, PERF-01, PERF-04)
- TTFB de **2,16 s** (contournement du cache), `DOMContentLoaded` à 3,0 s, 74 requêtes dont 29 scripts et 20 `link`. Les **14 émojis** de la description sont convertis en images par le script `wp-emoji`. L'image en pleine taille (`…-01.webp`) est chargée en plus de la version 600 px, pour le zoom.
- *Correctif* : LiteSpeed Cache (réglages de `audit-performance.md` §6, fiche cachable grâce au jeton servi à la demande), désactivation de `wp-emoji` (les téléphones affichent les émojis nativement), image pleine taille chargée seulement à l'ouverture du zoom.

---

## 5. État des constats UX du matin qui touchent la fiche

| Constat | État le 23/09 au soir | Preuve |
|---|---|---|
| UX-01 WhatsApp sans +216 | **Corrigé** | `wa.me/21656261412` sur la fiche. Le numéro reste à confirmer (56261412 affiché, 26767491 dans les notes du projet). |
| UX-04 « Satisfait ou remboursé » | Ouvert | Bandeau desktop de la fiche. |
| UX-05 Produit test « Prd 1 » | **Corrigé** | Absent de l'API publique (15 produits). |
| UX-06 Doublon « Pince de Câblage Pro » | Ouvert | 2 fiches publiées, l'une affichée dans « Produits similaires ». |
| UX-12 Prix à 1,2 écran, marges de 44 px | Ouvert | Mêmes mesures : 44 px, prix à 973 px. Causes détaillées en FP-03. |
| UX-13 Sélecteur flottant sur la barre | Ouvert | FR et AR (FP-04). |
| UX-14 Formulaire sous l'en-tête | Ouvert | Champ à 40–88 px sous un en-tête de 77 px (FP-05). |
| UX-15 Desktop à 280 px | Ouvert, **cause trouvée** | Spécificité (0,3,1) contre (0,3,2) (FP-01). |
| UX-19 / UX-20 Téléphone, 7 champs | Ouvert | FP-08. |
| UX-21 Deux tunnels | Ouvert | FP-07. |
| UX-22 Contrastes | Ouvert | Prix à 2,46:1, « Ajouter au panier » à 2,61:1. |
| UX-23 Derja en LTR | Ouvert | FP-11. |
| UX-29 Aucun délai | Ouvert | FP-09. |
| UX-31 Avis (0) | Ouvert | FP-13. |
| UX-33 Favori à 56 px | Ouvert | FP-07. |
| UX-40 Quantité − / + et vidéo | **Déployé** | − / + à 48 px en ligne ; section vidéo prête, aucune vidéo renseignée. |

---

## 6. Fiche cible (mobile)

```
┌──────────────────────────────────────┐
│ Bandeau · En-tête collant (143 px)   │
├──────────────────────────────────────┤
│ Accueil / Outils & Bricolage         │  ← une ligne, sans le nom du produit
│ ┌──────────────────────────────────┐ │
│ │  [Nouveau]                        │ │  ← badge posé sur la photo
│ │        PHOTO 343 × 343            │ │  ← marges de 16 px, glissement du doigt
│ │                         ● ○ ○ ○   │ │
│ └──────────────────────────────────┘ │
│ ▢ ▢ ▢ ▢ ▢ ▢  →  (1 rangée défilante)  │
│ Visseuse Perceuse Sans Fil 21V (H1)  │  ≈ 606 px
│ 79,000 DT            (bleu nuit)     │  ≈ 690 px
│ ✓ Paiement à la livraison            │
│ ✓ Livraison offerte · délai réel     │  ← délai fourni par le propriétaire
│ • 21 V · 1 800 tr/min · brushless    │  ← tiré de la fiche technique
│ ┌ Commander / أطلب توا ─────────────┐ │
│ │ Nom complet                       │ │
│ │ Téléphone  (XX XXX XXX)           │ │  ← validé à la sortie du champ
│ │ Gouvernorat ▾                     │ │
│ │ Adresse                           │ │
│ │ Quantité  − 1 +                   │ │
│ │ Total à payer à la livraison  79  │ │
│ │ [ Confirmer ma commande ]         │ │
│ └───────────────────────────────────┘ │
│ ou ajouter au panier · Une question ?│  ← liens secondaires
│ ▸ Description (tronquée, Lire la suite)
│ ▸ Fiche technique                    │
│ ▸ Livraison                          │
│ Produits similaires (2 colonnes × 2) │
├──────────────────────────────────────┤
│ 79,000 DT │ ◯ WhatsApp │ Commander  │  ← barre collante sans sélecteur de langue
└──────────────────────────────────────┘
```

Desktop : galerie à 55 % et résumé à 45 % du conteneur de 1 240 px. Le formulaire express tient dans la colonne de droite, et la galerie reste collante pendant le défilement du résumé.

---

## 7. Plan d'action

Chaque lot se livre, se vérifie et se déploie seul. Le code reste dans le thème enfant (présentation) et dans `famma-core` (logique), conformément à ClaudeMemo §6 et §13.

### Lot 0 — Réglages d'administration (propriétaire, ~30 min, sans code)

| # | Action | Constat | Critère de réussite |
|---|---|---|---|
| 0.1 | TranslatePress : désactiver le sélecteur flottant | FP-04 | Le prix de la barre collante est visible en FR et en AR. |
| 0.2 | Mettre à la corbeille l'ancienne « Pince de Câblage Pro », avec une redirection 301 vers la bonne fiche | FP-17 | Une seule fiche dans la boutique et dans les produits similaires. |
| 0.3 | WooCommerce → Produits : avis réservés aux acheteurs vérifiés | FP-13 | Formulaire d'avis invisible pour un visiteur anonyme. |
| 0.4 | Barre supérieure : retirer « Satisfait ou remboursé » | UX-04 | Absent du bandeau desktop. |
| 0.5 | **Fournir** : délai de livraison réel (par zone si besoin), vidéos réelles des produits, liens des réseaux sociaux, numéro WhatsApp définitif | FP-09, FP-21 | Données reçues. Rien n'est affiché tant qu'elles n'existent pas. |

### Lot 1 — Correctifs CSS et JS du thème enfant (développeur, ~1 jour, risque faible)

| # | Action | Fichier | Constat |
|---|---|---|---|
| 1.1 | Spécificité : galerie et résumé en pleine largeur de colonne, vignettes sans chevauchement ni opacité, en LTR et en RTL | `assets/css/product.css:69-155` | FP-01, FP-02 |
| 1.2 | Un seul rembourrage de 16 px sur la fiche mobile | `product.css` (+ neutralisation du rembourrage Kadence sur `.single-product`) | FP-03 |
| 1.3 | Prix juste après le H1, extrait après la réassurance ; fil d'Ariane sur une ligne ; badge sur la photo ; vignettes sur une rangée défilante | `inc/product.php:37-41`, `product.css` | FP-03, FP-18 |
| 1.4 | Défilement vers `.famma-express` avec `scroll-margin-block-start` | `assets/js/sticky-cta.js:72`, CSS du formulaire | FP-05 |
| 1.5 | Prix en bleu nuit ; « DT » sur la version FR ; symbole isolé (`unicode-bidi: isolate`) | `product.css:207`, filtre `woocommerce_currency_symbol` dans `famma-core` | FP-06 |
| 1.6 | Direction du texte selon la langue majoritaire (description courte, extraits des cartes) | utilitaire PHP + filtre `woocommerce_short_description` | FP-11 |
| 1.7 | Réassurance en grille : la puce reste à côté de son texte | `style.css:665-687` | FP-09 |
| 1.8 | WhatsApp dans la barre collante ; pastille masquée tant que la barre est affichée | `functions.php:587`, `style.css` | FP-12 |
| 1.9 | Flèches de galerie de 48 px, `prevText` / `nextText` traduits | `inc/product.php:675` | FP-16 |
| 1.10 | Accordéons fermés sur mobile (ou description tronquée) ; H2 « Description » retiré | `woocommerce/single-product/tabs/tabs.php:80`, filtre `woocommerce_product_description_heading` | FP-10 |
| 1.11 | Téléphone : indication « XX XXX XXX », validation à la sortie du champ, erreur reliée par `aria-describedby` | `famma-core/includes/express-order.php:215-246` + JS du formulaire | FP-08 |

**Résultat attendu** : desktop réparé, H1 dans le premier écran, prix vers 690 px au lieu de 973, barre collante complète. Note visée pour la fiche : **environ 6,5/10**.

### Lot 2 — Un seul tunnel sur la fiche (développeur + décisions du propriétaire, 2 à 3 jours)

| # | Action | Constat | Décision préalable |
|---|---|---|---|
| 2.1 | Formulaire express comme seul geste principal ; « Ajouter au panier » en lien secondaire, sans sa quantité ; favori à 48 px ou retiré | FP-07 | Garder ou non le panier sur la fiche. |
| 2.2 | Formulaire à 4 champs : Nom complet, Téléphone, Gouvernorat, Adresse (Ville facultative) ; quantité − / + ; pas vertical d'environ 88 px | FP-08 | Compatibilité avec la confirmation téléphonique. |
| 2.3 | Une seule liste de réassurance sous le prix, avec le délai réel ; bloc « 4 bénéfices » déplacé ou retiré sur mobile ; texte « Rien de plus à remplir » aligné sur le vrai formulaire | FP-09 | Délai fourni (0.5). |
| 2.4 | Libellés uniques : « Commander / أطلب توا », « Confirmer ma commande / أكّد طلبيتك » | FP-15 | — |
| 2.5 | Bloc « L'essentiel » : 3 puces tirées de la fiche technique existante, affiché seulement si elle est renseignée | Opportunité §4.2 | — |
| 2.6 | Accordéon « Avis » masqué tant qu'il n'y a aucun avis vérifié | FP-13 | — |
| 2.7 | Desktop : galerie collante à 55 %, résumé à 45 % | FP-01 | — |

**Résultat attendu** : un visiteur venu d'une publicité voit le produit, le prix, les conditions et le formulaire sans chercher, et il remplit 4 champs au lieu de 7. Note visée : **environ 7,5/10**.

### Lot 3 — Arabe, preuves et vitesse (2 à 4 jours, en partie propriétaire)

| # | Action | Constat |
|---|---|---|
| 3.1 | TranslatePress : titres produits, rayons, fil d'Ariane et slug `produit` en arabe | FP-14 |
| 3.2 | Harmonisation en derja (case CGV, doublons derja / arabe standard) | FP-14 |
| 3.3 | Vidéos réelles dans la section vidéo, avec un contrôle au rendu : elle ne doit pas repousser le prix | FP-21 |
| 3.4 | Produits similaires : 4 produits, 2 colonnes, cartes compactes | FP-17 |
| 3.5 | LiteSpeed Cache (fiche cachable), désactivation de `wp-emoji`, image pleine taille chargée à l'ouverture du zoom | FP-19 |

### Validation de chaque lot (ClaudeMemo §11)

1. `php -l` et `phpcs` (WordPress) sans erreur sur les fichiers touchés.
2. `WP_DEBUG` actif en local : `debug.log` sans nouveau warning, notice ni deprecated.
3. Recette au rendu, **FR et AR**, à 375 px et à 1 366 px : positions du H1, du prix et du formulaire mesurées de nouveau (tableau du §3), barre collante, appui sur la barre, puis le même contrôle sur un vrai téléphone Android d'entrée de gamme et sur un iPhone.
4. Parcours COD de bout en bout en local : commande express, puis statuts confirmé → expédié → livré, et « livré » marque la commande payée et déclenche l'événement serveur une seule fois.
5. Déploiement précédé d'une sauvegarde DirectAdmin, comme le 23/09.

---

## 8. Mesurer l'effet

Aucun chiffre de gain n'est promis ici : il se mesure sur les vraies commandes.

| Indicateur | Définition | Ce qu'il dit |
|---|---|---|
| Taux de démarrage | premier champ du formulaire touché ÷ vues de fiche | Le premier écran et la barre collante font-ils leur travail ? |
| Taux d'envoi | formulaires envoyés ÷ formulaires démarrés | Le formulaire est-il trop long ou trop flou ? |
| Erreurs téléphone | envois refusés pour numéro invalide ÷ envois | Effet de la validation en direct (1.11). |
| Taux de refus | commandes créées − commandes livrées, ÷ commandes créées | Qualité des commandes : bon numéro, bonne adresse, délai compris. |
| Part « Ajouter au panier » | ajouts au panier depuis la fiche ÷ commandes | Faut-il garder ce second tunnel (2.1) ? |

Relevé de départ à faire **avant** le lot 1 (deux semaines de données si possible), puis après chaque lot. Les événements du tunnel existent déjà (`famma-core/includes/tracking-events.php`) : vérifier qu'ils couvrent le démarrage du formulaire et les erreurs, et les compléter si besoin.

---

## 9. Décisions à prendre par le propriétaire

1. **Formulaire à 4 champs** (Nom complet, Ville facultative) : compatible avec la confirmation par téléphone et avec le transporteur ?
2. **« Ajouter au panier » sur la fiche** : le garder en lien secondaire ou le retirer ?
3. **Case CGV obligatoire** tant que les CGV ne sont pas rédigées (UX-03).
4. **Symbole monétaire** sur la version FR : « DT » (recommandé) ou « TND ».
5. **Délai de livraison** à afficher, par zone si nécessaire.
6. **Numéro WhatsApp** définitif : 56261412 ou 26767491.
7. **Badge « Nouveau »** : sur la photo, et sur combien de jours ?

---

## Annexe A — Correctifs proposés

Ce sont des propositions à reprendre au moment d'écrire le code. Chaque extrait est à vérifier au rendu, en LTR et en RTL, et les API WooCommerce et FlexSlider sont à confirmer dans leur documentation à jour (ClaudeMemo §1).

**A.1 — Galerie et résumé (FP-01, FP-02).** À spécificité égale, le thème enfant, chargé après Kadence, l'emporte. Vérifier la dépendance d'enqueue.

```css
/* (0,3,2) comme Kadence : la largeur de 48 % et le flottement disparaissent. */
.famma-product div.product div.images,
.famma-product div.product div.summary {
	float: none;
	inline-size: auto;
}

/* (0,4,2) et (0,5,2) pour la variante RTL de Kadence. */
.famma-product div.product div.images .flex-control-thumbs,
.rtl .famma-product div.product div.images .flex-control-thumbs {
	max-inline-size: none;
	margin: var(--sp-3) 0 0;
	inset-inline: auto;
	transform: none;
}

.famma-product div.product div.images .flex-control-thumbs li img {
	opacity: 1;
}
```

**A.2 — Un seul rembourrage sur mobile (FP-03).**

```css
@media (max-width: 767px) {
	.single-product .content-container.site-container {
		padding-inline: 0;
	}

	.famma-container.famma-product {
		padding-inline: 16px;
	}
}
```

**A.3 — Arrivée sur le formulaire (FP-05).** Dans `sticky-cta.js`, défiler vers la section (`.famma-express`, qui contient le titre) plutôt que vers `.famma-express__form`, puis :

```css
.famma-express {
	/* 77 px d'en-tête collant mesurés + 16 px d'air ; utiliser le jeton de
	   hauteur d'en-tête s'il existe. */
	scroll-margin-block-start: calc(77px + var(--sp-4));
}
```

**A.4 — Prix (FP-06).**

```css
.famma-product div.product .summary p.price,
.famma-sticky-cta__price {
	color: var(--famma-blue);
}

/* Le symbole devient un bloc neutre : le nombre garde sa place en LTR. */
.woocommerce-Price-currencySymbol {
	unicode-bidi: isolate;
}
```

Pour « DT » sur la version FR : filtre `woocommerce_currency_symbol` dans `famma-core`, selon la langue active de TranslatePress.

**A.5 — Réassurance (FP-09).**

```css
.famma-reassure__item {
	display: grid;
	grid-template-columns: 8px minmax(0, 1fr);
	column-gap: var(--sp-2);
	align-items: baseline;
}

.famma-reassure__item::before {
	align-self: center;
}

.famma-reassure__ar {
	grid-column: 2;
}
```

**A.6 — Direction du texte (FP-11).** Principe : compter les lettres arabes (`\p{Arabic}`) et latines (`\p{Latin}`) du texte, et envelopper la description courte dans `<div lang="ar" dir="rtl">` quand l'arabe domine. Un simple `dir="auto"` choisirait LTR à cause du premier mot, « Visseuse ».

**A.7 — Flèches de galerie (FP-16).** Dans `famma_child_gallery_carousel_options()`, ajouter `prevText` et `nextText` traduits (« Photo précédente », « Photo suivante »), puis donner aux liens `.flex-prev` et `.flex-next` une zone de 48 × 48 px.
