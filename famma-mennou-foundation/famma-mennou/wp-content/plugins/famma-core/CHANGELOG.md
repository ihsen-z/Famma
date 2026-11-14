# Changelog — FAMMA Core

## 0.3.40 — 2026-11-14 (fiche produit de la maquette, lot 50) · thème famma-child 0.2.23

Lot 50 : volet « Les tailles » de la maquette. Une nouvelle case, livrée
décochée. Pas mis en ligne.

- **`pdp_sizes` — volet « Les tailles »** : un onglet WooCommerce de plus
  (`famma_sizes`, juste après la description) qui devient un volet avec
  `pdp_details`, sinon un onglet comme les autres. Contenu tiré du produit :
  tailles disponibles (texte `size_avail`, « Disponible en {tailles}. »),
  lien « autre taille » du lot 49 si `pdp_altsize` est cochée, tableau des
  mesures. Un produit sans taille ni tableau n'a pas d'onglet. Textes
  `size_tab` et `size_avail` dans FAMMA → Nouveau look → Fiche produit.
- **Tableau des mesures** saisi produit par produit, en français et en
  derja, dans l'onglet « Fiche FAMMA », bloc « Tableau des mesures »
  (`Product_Size_Chart_Admin`, sur `famma_story_panel_end` après « Autre
  taille ») : une ligne par rangée, colonnes séparées par « | », première
  ligne = titres ; 14 lignes et 6 colonnes au plus ; langue de la page, sinon
  l'autre. Lecture `Product_Size_Chart`, méta `_famma_size_chart_<fr|ar>`.
  Nonce, capacité `edit_product`, témoin de présence, saisie assainie, champ
  vidé = méta supprimée. Rien d'inventé : sans saisie, pas de tableau.
- Thème 0.2.23 : `inc/new-look-pdp-sizes.php` ; tableau large qui défile
  dans son cadre (atteignable au clavier), jamais la page.
- Corrections trouvées au banc sur les lots précédents (non en prod) :
  - volets « Tout savoir » (lot 45) : toute la largeur (860 px au plus) ;
    centrés par des marges auto dans une grille, ils prenaient la largeur de
    leur contenu (238 px sur 328 en arabe) ;
  - barre du bas (lot 47) : à 360 px, « Choisir ma taille » était écrasé
    sur 48 px et 3 lignes ; le bouton garde sa largeur, le nom se coupe.
- Poids : feuille 8,2 Ko compressés (l'ancienne de 7,5 Ko n'est plus
  chargée sur la fiche) ; aucun script de plus.
- Banc `famma-qa-fiche` : volet en français et en arabe (« القياسات »,
  tailles séparées par « ، ») à 360 et 1 280 px, sans défilement de la page ;
  écran d'administration (sans nonce, sans témoin, abonné : refusés ;
  balises retirées ; champ vidé supprimé) ; commande COD 737 (taille L,
  offre après achat affichée) payée seulement à « livrée », annulée + test ;
  cases décochées : fiches identiques octet pour octet au lot 49.

## 0.3.39 — 2026-11-14 (fiche produit de la maquette, lot 49) · thème famma-child 0.2.22

Lot 49 : finitions de la boîte d'achat, ce qui restait du rapport « Fiche
produit en prod vs maquette » après les lots 44 à 47. Deux nouvelles cases,
livrées décochées. Pas mis en ligne.

- **`pdp_altsize` — lien « autre taille »** sous les tailles (exemple de la
  maquette : « Vous portez S ou M ? Voir la Ceinture dorsale Pro »). Produit
  proposé (recherche de produits WooCommerce) et phrase en français et en
  derja, saisis dans l'onglet « Fiche FAMMA », bloc « Autre taille »
  (`Product_Alt_Size_Admin`, branché sur la nouvelle action
  `famma_story_panel_end`). Lecture `Product_Alt_Size` : le produit proposé
  doit exister, être publié et visible, et ne pas être le produit lui-même ;
  sinon, ou sans phrase, pas de lien. Méta `_famma_alt_size_*`. Nonce,
  capacité `edit_product`, champs assainis, témoin de présence des champs
  (rien n'est effacé si le bloc n'était pas dans le formulaire). Lien de
  48 px, rangé sous les tailles par le script ; sans script, au-dessus du
  formulaire.
- **`pdp_labels` — formulaire dans la langue de la page (n° 9)** :
  « Téléphone / الهاتف » devient « Téléphone » (ou « الهاتف » en arabe) pour
  la taille, la quantité, le total, le téléphone, le nom et l'adresse ;
  astérisques masqués (les champs restent obligatoires) ; sur la page
  française, la ligne en derja de « Confirmer » et de la barre du bas est
  masquée. À cocher seulement après le bilan du lot 23.
- **Animations de la maquette** : étincelles quand une taille est choisie
  (avec `pdp_buy`), éclair orange sur le gouvernorat choisi (avec
  `pdp_form`). Rien avec moins d'animations demandé.
- Le bouton « Confirmer » tournait déjà pendant l'envoi : rien à faire.
- Non fait, à valider : la détection du gouvernorat à partir de l'adresse
  (la maquette reconnaît la ville), qui demanderait une liste de villes par
  gouvernorat à vérifier et changerait la valeur envoyée.
- Thème 0.2.22 : `famma_child_pdp_altsize()`, script de la boîte d'achat et
  feuille complétés ; script de la boîte d'achat chargé aussi avec
  `pdp_altsize` ou `pdp_labels`. Poids, dix cases et ces deux cochées :
  scripts 7,9 Ko compressés (copies allégées), feuille 8,0 Ko (l'ancienne
  feuille de 7,5 Ko n'est plus chargée sur la fiche).
- Banc `famma-qa-fiche` : lien rangé sous les tailles, en derja sur la page
  arabe ; intitulés réduits en français et en arabe ; écran d'administration
  (affichage, sans nonce, sans témoin, sans droit, produit lui-même ou
  inexistant refusés, phrase assainie) ; commande COD 736 payée seulement à
  « livrée », annulée + test ; cases décochées : fiches identiques octet pour
  octet à la 0.3.38.
- Corrections de la relecture :
  - produit proposé en rupture : pas de lien, et un avertissement dans
    l'onglet « Fiche FAMMA » (`Product_Alt_Size::usable()` : publié,
    visible, en stock) ; la phrase est lue avant de charger le produit lié ;
  - champs vidés : méta supprimées au lieu de lignes vides ;
  - lien traduit par TranslatePress (vers /ar/) : l'exclusion de traduction
    ne porte plus que sur la phrase, et toujours avec une valeur
    (`data-no-translation=""`, aussi pour le récit du lot 46) ;
  - `pdp_labels` côté serveur (filtre `gettext_famma-core` du thème) : plus
    de saut à l'affichage, messages d'erreur compris ; le script ne traite
    plus que le titre et un intitulé de taille saisi à la main, et seulement
    un vrai couple « français / derja ».

## 0.3.38 — 2026-11-14 (fiche produit de la maquette, lot 47) · thème famma-child 0.2.21

Lot 47 du rapport « Fiche produit en prod vs maquette » (14/11) : boîte
d'achat de la maquette. Quatre nouvelles cases, livrées décochées, valables
pour toutes les fiches. Pas mis en ligne. La décision n° 8 (« taille
d'abord ») a sa case à part (`pdp_buy`), à trancher à la revue du 18/11.

- **`pdp_buy` — taille d'abord (n° 8)** : taille, quantité et total en
  premier, barre d'avancement (« Produit choisi. Il reste votre taille. » →
  « Taille L. Il reste vos coordonnées. » → « Encore 2 à remplir. » → « Tout
  est prêt. »), bouton « Commander » qui déplie téléphone, nom et adresse.
  Taille oubliée : message sous les tailles, les pastilles tremblent, le
  curseur va sur la première. Pastille choisie soulignée par un indicateur
  qui glisse. Un seul reflet sur le bouton après 10 s sans action. Les
  boutons « Commander » de la barre du bas et de « Quand payez-vous ? » font
  le même geste. Sans script, le formulaire reste ouvert comme aujourd'hui.
- **`pdp_form` — formulaire (n° 9 et 10)** : coche verte sur chaque champ bien
  rempli (téléphone, chiffres arabes compris ; nom ; gouvernorat), total qui
  rebondit quand la quantité change, bouton panier retiré (le cœur reste).
- **`pdp_points` — points forts (n° 7)** : la description courte en entier
  sous la boîte d'achat, puces orange, sans « Lire la suite ». Rien n'est
  réécrit : c'est l'accroche du produit.
- **`pdp_dock` — barre du bas (n° 18)** : photo du produit, « Choisir ma
  taille » tant que la taille manque, puis « Taille L » et le total à jour.
- 8 nouveaux textes dans FAMMA → Nouveau look → « Fiche produit » :
  `buy_pg0` à `buy_pg3` (barre d'avancement, `{choix}` et `{n}`),
  `buy_chosen`, `buy_after`, `buy_size_err`, `buy_pick`. Derja à relire.
- Le formulaire express (`express-order.js`) et la passerelle Upsell One
  Click (`uoc-bridge.php`) ne sont pas touchés : ses nœuds sont seulement
  rangés autrement par le thème ; contrôles, envoi et offre après achat
  inchangés. Les règles du script ne servent qu'à l'affichage.
- Thème 0.2.21 : `inc/new-look-pdp-buy.php`, `assets/js/new-look-pdp-buy.js`
  (3,6 Ko compressés, chargé seulement si `pdp_buy`, `pdp_form` ou
  `pdp_dock` est servie), feuille `new-look-pdp.css` complétée (7,6 Ko
  compressés au total), `famma_child_sticky_cta()` enrichie quand `pdp_dock`
  est servie (sinon identique). Aucune requête de plus. Moins d'animations
  demandé : ni tremblement, ni reflet, ni rebond.
- Banc `famma-qa-fiche` : commande COD 733 (quantité 2, taille L, 44 DT,
  offre après achat affichée), payée seulement à « livrée » ;
  arabe (sens, indicateur, textes) ; `pdp_buy` décochée avec les
  autres cochées. Cases décochées : fiches identiques octet pour octet à la
  0.3.37.
- Relecture indépendante des lots 46 et 47, corrigée : boîte fermée, la touche
  Entrée ou « OK » du clavier ouvre au lieu d'envoyer vers des champs cachés ;
  commande en suspens reprise après rechargement : le formulaire reste ouvert
  (« Vérifier ma commande en cours » visible) ; « Commander » et la barre du
  bas ouvrent dans le geste même, pour que le clavier s'ouvre sur iPhone ;
  ligne derja de la barre cachée tant qu'elle dit « Choisir ma taille » ;
  `aria-expanded` sur « Commander » ; points forts déplacés seulement sous un
  formulaire express (pas sur un produit variable) ; défilement du récit et de
  l'anatomie : mesures groupées, anatomie ignorée hors de l'écran. Banc :
  commande 734 payée seulement à « livrée », annulée + test.
- Poids : le thème sert des copies allégées des 4 scripts de la fiche
  (`assets/js/min/`, sans commentaires, générées par
  `scripts/build-pdp-min.py`, sources commentées servies avec `SCRIPT_DEBUG`).
  Les 10 cases cochées : 7,2 Ko de scripts compressés au lieu de 10,4 Ko,
  dans le budget de +8 Ko. Banc : commande 735 payée seulement à « livrée ».

## 0.3.37 — 2026-11-14 (fiche produit de la maquette, lot 46) · thème famma-child 0.2.20

Lot 46 du rapport « Fiche produit en prod vs maquette » (14/11) : récit et
« Ce qu'il y a dedans ». Deux nouvelles cases, livrées décochées, valables
pour toutes les fiches, qui ne s'affichent que sur les produits remplis.
Pas mis en ligne.

- **Onglet « Fiche FAMMA »** dans Produits → Modifier (`Product_Story_Admin`,
  chargé seulement dans l'administration) : récit, boutons de situation
  (« icône | bouton | phrase », 6 au plus, icônes écran, téléphone, voiture,
  lecture, maison), phrase sous les boutons et phrase après un choix
  (`{situation}`), en français et en derja ; photo choisie parmi celles du
  produit, repères posés en cliquant sur la photo, pièces une par ligne.
  Nonce, capacité `edit_product`, champs assainis ; photo étrangère au
  produit refusée ; repères bornés à 0-100 %. Méta `_famma_story_*`.
- **Lecture** (`Product_Story`) : la langue de la page si elle est remplie,
  sinon l'autre, avec sa langue et son sens (comme la maquette, qui montre le
  récit en derja sur la page française). Le passage entre deux * passe en
  orange. Au moins deux pièces placées pour l'anatomie.
- **`pdp_story` — récit en bande marine** : sous la boîte d'achat, le récit
  en grand, mots qui s'allument au défilement (mot par mot, jamais lettre par
  lettre : ligatures arabes), boutons de situation (48 px) ; toucher un
  bouton écrit sa phrase dessous, en orange, par du texte seul.
- **`pdp_anatomy` — « Ce qu'il y a dedans »** : photo à repères numérotés qui
  reste en place (au-dessus de la liste sur téléphone, à côté sur
  ordinateur) et zoome sur la pièce que l'on lit. Repères en coordonnées de la
  photo, identiques en arabe.
- 2 nouveaux textes dans FAMMA → Nouveau look → « Fiche produit » :
  `story_chips` (nom du groupe de boutons, lu par les lecteurs d'écran) et
  `ana_title` (titre ; vide, le bloc disparaît). Derja à relire.
- Thème 0.2.20 : `inc/new-look-pdp-story.php`, `assets/js/new-look-pdp-story.js`
  (2,1 Ko compressés, chargé seulement sur une fiche remplie), feuille
  `new-look-pdp.css` complétée (+1,1 Ko compressés), icônes `monitor`,
  `mobile`, `car`, `book`. Aucune requête de plus : méta et photos du produit
  déjà chargées. Moins d'animations demandé : texte entier, sans zoom.
- Cases décochées : fiches identiques octet pour octet à la 0.3.36 (banc
  `famma-qa-fiche`).

## 0.3.36 — 2026-11-14 (fiche produit de la maquette, lots 44 et 45) · thème famma-child 0.2.19

Prévus en 0.3.31 (lot 44, thème 0.2.18) et 0.3.32 (lot 45), jamais mis en
ligne : regroupés en 0.3.36 après le lot 48 (0.3.35). Les quatre cases sont
déclarées dans `New_Look_Bricks_Product` (registre du lot 48), après
`ledger` ; leurs textes sont dans FAMMA → Nouveau look → « Fiche produit ».

### Lot 45 — « Quand payez-vous ? » et volets

Lot 45 du rapport « Fiche produit en prod vs maquette » (14/11). Deux
nouvelles cases, livrées décochées, valables pour toutes les fiches.
Le thème dessine (`inc/new-look-pdp-pay.php`, `inc/new-look-pdp-details.php`,
`assets/js/new-look-pdp-sections.js`, feuille `new-look-pdp.css` du lot 44).

- **`pdp_pay` — « Quand payez-vous ? » et zéros animés** : sous la boîte
  d'achat, une bande marine avec le compteur « Déjà payé 0 DT », un colis qui
  roule sur une route le long des étapes « Comment commander » (textes de la
  onglet Boutique), les étapes qui se cochent au défilement, et le compteur
  qui passe au prix du produit à la dernière étape. Bouton « Commander » qui
  ramène au formulaire. Remplace les quatre bénéfices (décision n° 16).
  Cachée si le paiement à la livraison n'est pas activé, ou si son titre est
  vidé. Avec la bande « 0 DT » cochée : ses zéros s'impriment à l'arrivée, et
  l'encadré de réassurance de la fiche ne garde que le délai de livraison
  (décision n° 6) ; panier et commande inchangés. Produit variable à prix
  différents : la fourchette s'affiche, sans compteur animé. Sans script ou
  avec moins d'animations : tout est affiché d'un coup.
- **`pdp_details` — « Tout savoir avant de commander » en volets** : les
  onglets deviennent des volets `<details>`. La description est découpée à
  ses intertitres de niveau 3 (un volet chacun, le début reste visible
  au-dessus) ; sans intertitre, ou si les intertitres sont dans un bloc
  imbriqué, elle forme un seul volet ouvert. Une question de la FAQ par
  volet, puis les autres onglets avec leur titre. Rien n'est réécrit. Les
  volets gardent l'ancre `#tab-<clé>` ; une ancre ouvre son volet.
- 4 nouveaux textes dans FAMMA → Nouveau look → « Fiche produit » : `pay_title`,
  `pay_meter`, `pay_paid` (« {montant} payé »), `det_title` (la derja reste
  à relire).
- Après relecture et banc (14/11), avant toute mise en ligne :
  - bande impossible (rupture, produit non commandable, paiement à la
    livraison coupé, titre vidé) : les quatre bénéfices reviennent ;
  - réassurance réduite au délai seulement si la bande « 0 DT » est
    réellement affichée pour ce produit (`famma_child_ledger_shown_rows()`,
    même règle que la bande elle-même), sinon complète ;
  - compteur au format de prix de la boutique (décimales, séparateur) ; écoute
    du défilement sans observateur de visibilité ; `data-no-translation` sur
    les compteurs (TranslatePress) ;
  - ancres : le volet visé s'ouvre et défile à l'écran (Safari, second clic) ;
    `#tab-famma_faq` sur la première question, `#tab-description` toujours
    présent ; un volet avec seulement une vidéo, un tableau ou une
    intégration est gardé ; action `woocommerce_product_after_tabs`
    conservée ;
  - lot 44 : fil d'Ariane sur une ligne avec points de suspension,
    observateur de la galerie arrêté au chargement, survols et pression des
    flèches sous « moins d'animations » ; tampons rognés dans leur cadre.

### Lot 44 — galerie et cadre

Lot 44 du rapport « Fiche produit en prod vs maquette » (14/11). Deux
nouvelles cases, livrées décochées, valables pour TOUTES les fiches (aucun
produit n'est nommé). Le thème dessine
(`inc/new-look-pdp.php`, `assets/css/new-look-pdp.css`,
`assets/js/new-look-pdp.js`) ; aucun texte nouveau, aucune requête de plus.

- **`pdp_gallery` — « Galerie de la maquette »** : cadre
  arrondi qui s'ouvre à l'arrivée, compteur « 2 / 5 » sur la photo, cadre
  orange qui glisse d'une vignette à l'autre, et avec une souris des flèches
  posées sur la photo et un zoom ×1,7 qui suit le pointeur. Les infos de
  droite montent l'une après l'autre. La galerie reste celle de WooCommerce
  (glisser, loupe plein écran, variantes). Une seule photo : ni compteur ni
  cadre. Moins d'animations demandé : tout est immobile, pas de zoom.
- **`pdp_frame` — « Cadre de la maquette »** : barre orange
  d'avancement de la lecture, logo qui cligne de l'œil, fil d'Ariane sans le
  nom du produit sur téléphone (chaque étape dans sa balise, via les
  arguments de WooCommerce), titres de section entre deux traits orange,
  carte WhatsApp verte sans flèche, trois produits similaires (le premier en
  grand sur téléphone, trois colonnes à partir de 760 px) qui apparaissent
  au défilement.
- La grande feuille de l'accueil et de la boutique (`new-look.css`) n'est
  plus chargée sur la fiche quand seules des cases de la fiche y sont
  actives.

## 0.3.35 — 2026-11-14 (écran FAMMA → Nouveau look) · thème famma-child inchangé

Lot 48. Un seul écran rassemble tout le paramétrage du nouveau look, rangé
par page du site. Aucune nouvelle case, aucun changement côté visiteur :
le mode, chaque case et chaque texte gardent leur valeur à l'envoi.

- **Menu FAMMA → Nouveau look** (capacité `manage_woocommerce`), juste sous
  le tableau de bord. Onglets Partout, Accueil, Boutique, Fiche produit, Mon
  compte. Pour chaque brique : interrupteur, titre court, une phrase, « En
  savoir plus » (le détail complet d'avant), cases requises avec un lien, état
  réel (Visible par tous, Seulement les administrateurs, Prête mais personne
  ne la voit, Sans effet : il manque une case, Cachée tant que le texte est
  vide, Désactivée) recalculé en direct, et ses textes FR + derja juste en
  dessous. « Autres textes de la page » pour ceux qui s'affichent toujours.
  Recherche sur tous les onglets (réglages et textes), lien « Voir » par
  page, barre « Enregistrer » avec les modifications non enregistrées.
- **Modes renommés** : « Personne », « Seulement les administrateurs »,
  « Tout le monde » (valeurs `off` / `admins` / `all` inchangées).
- **Registre** (`new-look-registry.php` + un fichier de briques par page,
  `new-look-bricks-*.php`) : une brique = une entrée (`title`, `summary`,
  `help`, `needs`, `texts`, `wait`). `New_Look::parts()` en est tiré ;
  `New_Look::part()`, l'option `famma_new_look` et `famma_child_new_look()`
  ne changent pas.
- **Enregistrement** par `admin-post.php` (jeton + capacité), qui fusionne
  dans les options existantes : seuls les textes modifiés sont envoyés, un
  texte absent de la requête garde sa valeur ; une requête sans mode ne
  touche pas aux cases. Cache LiteSpeed vidé après un changement.
- **FAMMA → Pages** garde À propos, Contact, Livraison, Retours,
  coordonnées, bandeau du haut et style du héros, avec un renvoi vers le
  nouvel écran. Les options `famma_new_look`, `famma_page_home`,
  `famma_page_shop`, `famma_page_product` et `famma_page_account` changent de
  groupe de réglages : enregistrer FAMMA → Pages ne peut plus les vider.
- Feuille et script chargés sur ce seul écran ; rien côté visiteur.

## 0.3.30 — 2026-11-13 (finitions de l'espace client) · thème famma-child 0.2.17

Lot 43, écarts 3, 4, 5, 8 (initiale), 11, 13 et 14 du rapport « Espace
client en prod vs maquette ». Nouvelle case `acct_finish` (« Mon compte :
finitions de la maquette »), livrée décochée, avec `acct_look`. Classe
`Account_Finish` (logique et textes) ; le thème dessine
(`inc/new-look-account-finish.php`).

- **« Mes informations »** : un seul champ « Nom et prénom » (« الاسم
  واللقب ») à la place de Prénom, Nom et Nom affiché de WooCommerce, qui
  restent dans la page mais cachés. Le nom saisi est découpé avant
  l'enregistrement de WooCommerce (premier mot = prénom, le reste = nom, le
  tout = nom affiché) ; un nom d'un seul mot est accepté. Ordre de la
  maquette : nom, téléphone, e-mail. Libellé « E-mail » (« الإيميل ») ; pour
  un compte créé par numéro, sans astérisque, avec « (facultatif) » et
  l'aide « Pour recevoir le récapitulatif de vos commandes. ».
- **« Mes commandes » sans commande** (case `acct_track`) : titre, mascotte,
  phrase et bouton « Voir la boutique », dans le style de la sélection vide,
  au lieu de l'encadré de WooCommerce. L'encart « Confirmez votre adresse
  e-mail » de WooCommerce reste au-dessus quand il s'applique.
- **Inscription et connexion** : « Mot de passe » (« كلمة السر ») sans
  astérisque ; « Obligatoire » reste lu à voix haute, jamais montré.
- **Déconnexion** : retour sur « Mon compte » avec « Vous êtes
  déconnecté. » (« خرجت من حسابك. ») pendant 2,6 s ; la marque `famma_out`
  quitte l'adresse.
- **Icône compte** : pastille orange avec l'initiale du client (en-tête à
  partir de 1 000 px, lien du panneau mobile en dessous). Aucune pastille
  pour un compte sans nom.
- **Téléphone** : le bas du compte garde 80 px libres, pour que la fin de
  chaque onglet passe au-dessus du bouton WhatsApp.
- 8 nouveaux textes dans FAMMA → Pages → « Mon compte » (la derja reste à
  relire, décision D6).

## 0.3.29 — 2026-11-13 (correctifs de l'espace client après le test en prod) · thème famma-child 0.2.16

Lot 42, suite au test complet de l'espace client en prod comparé à la
maquette. Aucune nouvelle case.

- **Langue arabe → français** (case `acct_prefs`) : sur la page arabe de
  « Mes informations », le bouton « FR » enregistrait le français mais
  laissait la page en arabe. TranslatePress ajoute `trp-form-language=ar`
  au formulaire et réécrit toute redirection vers cette langue ; ce champ
  est désormais ignoré pour le seul changement de langue.
- **Compteur de commandes** : la carte et l'onglet comptaient aussi les
  brouillons de commande (`checkout-draft`) que la liste « Mes commandes »
  ne montre pas (« 1 commande » au-dessus de « Aucune commande »). Le
  compteur passe par le même filtre que la liste
  (`woocommerce_my_account_my_orders_query`).
- **Fil d'Ariane** : « Mon compte » restait en français sur la version
  arabe. Nouveau texte « Fil d'Ariane — Mon compte » dans FAMMA → Pages →
  « Mon compte » (« حسابي » en arabe).
- **Onglet « Mes adresses »** : compteur à côté du nom, comme dans la
  maquette (toutes les adresses avec la case `acct_addrs`, sinon 0 ou 1).
- **Notice PHP** (vue sur le banc avec TranslatePress et `WP_DEBUG`) : la
  colonne « Préférences » de l'écran Comptes lisait sa case au chargement
  de l'extension, ce qui chargeait les traductions de famma-core avant
  `init` à chaque appel admin-ajax. Lecture déplacée sur `admin_init`.

## 0.3.28 — 2026-11-13 (écran « Bienvenue » après l'inscription) · thème famma-child 0.2.15

Nouvelle case `acct_welcome` (« Mon compte : écran « Bienvenue » après
l'inscription »), **livrée décochée**. Avec la case « présentation de la
maquette », comme l'écran #welcome de la maquette espace client :

- Un compte créé depuis le formulaire d'inscription de « Mon compte » (pas
  depuis la commande) est marqué (méta `_famma_welcome`). La première page
  du compte qui suit montre la mascotte, « Bienvenue, {prénom} ! » et « Votre
  compte est prêt. On vous emmène dans votre espace. », puis le compte. La
  marque est retirée dès l'affichage (une seule fois, valable 24 h).
- Avec script : l'écran arrive (480 ms, ressort), la mascotte saute et
  cligne avec des étincelles (260 ms), puis à 2,3 s l'écran s'efface et
  l'en-tête, les onglets et le contenu apparaissent avec leurs animations
  d'arrivée (compteurs relancés, soulignement replacé : événement
  `famma:acct-welcomed`). Mouvement réduit : rien ne saute, le compte
  apparaît à 0,9 s. Sans script : l'écran reste en haut du compte.
- Sans prénom (inscription par e-mail), le titre devient « Bienvenue ! ».
- La ligne « commandes rattachées » de la maquette attend le rattachement
  par code (lot 4) : pas reprise.
- Classe `Account_Welcome`, textes `wel_t`, `wel_p` dans FAMMA → Pages →
  « Mon compte ».

## 0.3.27 — 2026-11-13 (boîte « Préférences », lot 6 de l'espace client) · thème famma-child 0.2.14

Nouvelle case `acct_prefs` (« Mon compte : préférences (langue des messages,
nouveautés WhatsApp) »), **livrée décochée**. Avec les cases « présentation
de la maquette » et « adresses et informations de la maquette », « Mes
informations » ouvre la colonne de droite par la boîte « Préférences » de la
maquette.

- « Langue de mes messages » (FR / عربي, « Appels, SMS et WhatsApp. ») : la
  pastille glisse, le choix est noté sur le compte (méta `_famma_msg_lang`)
  et la page se recharge dans la langue choisie (adresse TranslatePress).
  Sans choix enregistré, la langue de la page est montrée.
- « Nouveautés sur WhatsApp » : refusé par défaut, enregistré sans recharger
  la page (sans script : formulaire ordinaire). **Caché tant que son texte de
  consentement est vide** : ce texte revient au propriétaire (décision D5).
  À l'inscription, la date et le texte accepté sont gardés (méta
  `_famma_wa_optin`) ; à la désinscription, ils sont effacés. Le site
  n'envoie aucun message.
- Jeton `famma_prefs`, compte connecté, cases cochées : sinon rien n'est
  enregistré. Classes `Account_Prefs`, `Account_Prefs_Texts`.
- Admin (`Account_Prefs_Admin`) : sous l'adresse de facturation d'une
  commande, « Préférences du client » (langue, inscription et sa date) quand
  le client en a choisi ; avec la case cochée, colonne « Préférences » et vue
  « Nouveautés WhatsApp (n) » dans Comptes.
- L'effaceur « FAMMA — compte client » (lot 8) efface aussi ces deux méta.
- Textes dans FAMMA → Pages → « Mon compte » (`pf_*`).

## 0.3.26 — 2026-11-13 (mot de passe oublié et suppression du compte, lot 8 de l'espace client) · thème famma-child 0.2.13

Nouvelle case `acct_privacy` (« Mon compte : mot de passe oublié et
suppression du compte »), **livrée décochée**. Décision D3 du propriétaire :
mot de passe oublié par WhatsApp, traité à la main, en attendant un code SMS.

- Sous « Me connecter » (avec la case « présentation de la maquette ») : le
  lien « Mot de passe perdu ? » de WooCommerce laisse la place au bouton
  « Mot de passe oublié ? » de la maquette, qui se déplie : explication,
  « Demander sur WhatsApp » (message préparé, avec le numéro tapé dans
  « Téléphone » s'il en est un), puis « Recevoir plutôt un lien par e-mail »
  (page WooCommerce, pour les comptes qui ont une adresse). Sans numéro
  WhatsApp réglé, seul le lien e-mail reste. Sans script, le dépliant est
  ouvert. Le propriétaire change le mot de passe à la main (Comptes →
  Modifier).
- « Mes informations » (avec la case « adresses et informations de la
  maquette ») se termine par la boîte rouge « Supprimer mon compte » de la
  maquette, avec la confirmation dans la page (« Oui, supprimer » /
  « Garder mon compte ») ; sur ordinateur elle suit « Connexion » dans la
  colonne de droite. **Elle reste cachée tant que son explication est
  vide** : ce texte revient au propriétaire.
- « Oui, supprimer » crée une demande WordPress « Effacer les données
  personnelles », déjà confirmée (client connecté, jeton `famma_del`) :
  aucun e-mail, rien n'est effacé avant la validation du propriétaire. En
  attendant, la boîte montre la date de la demande et « Annuler ma
  demande ». Les commandes suivent les réglages de confidentialité de
  WooCommerce. Classe `Account_Privacy`, méta `_famma_del_request`.
- Admin (`Account_Privacy_Admin`, actif même case décochée, n'agit que sur
  action du propriétaire) : effaceur « FAMMA — compte client » ajouté à
  ceux de WordPress et WooCommerce (adresses supplémentaires, étiquette,
  corbeille d'adresse, « Ma sélection », numéro de connexion) ; rappel en
  haut de l'admin tant qu'une demande attend ; Comptes → Rechercher accepte
  un numéro tunisien (avec ou sans +216, avec espaces).
- 15 textes dans FAMMA → Pages → « Mon compte » (`Account_Privacy_Texts`) ;
  ceux qui ne sont pas dans la maquette sont marqués : derja à relire en
  priorité. `del_s` (explication de la suppression) est vide.

## 0.3.25 — 2026-11-13 (plusieurs adresses, lot 7 bis de l'espace client) · thème famma-child 0.2.12

Nouvelle case `acct_addrs` (« Mon compte : plusieurs adresses (Maison,
Travail, Famille) »), **livrée décochée**, active seulement avec les cases
« présentation de la maquette » et « adresses et informations de la
maquette ». Décision D4 du propriétaire : plusieurs adresses.

- « Mes adresses » comme la maquette : une carte par adresse (étiquette avec
  son icône, « Par défaut », nom, téléphone, gouvernorat et adresse),
  « Modifier », « Utiliser par défaut », « Supprimer » en rouge, puis la
  carte « Ajouter une adresse ». Dix adresses au plus.
- Feuille de la maquette : « Nom de l'adresse » en trois pastilles (Maison,
  Travail, Famille), Gouvernorat (obligatoire, contrôlé avant l'envoi avec
  secousse), « Ville, cité, rue » avec le repère pour le livreur, « Utiliser
  par défaut pour mes commandes », Enregistrer.
- Messages « Adresse enregistrée. » et « Adresse supprimée. · Annuler »
  (la dernière adresse supprimée revient à sa place). Cartes qui montent une
  à une, halo orange sur la carte enregistrée, carte qui s'efface à la
  suppression ; coupés si le visiteur demande moins d'animations.
- L'adresse par défaut reste l'adresse de facturation de WooCommerce : la
  commande la préremplit comme avant, rien ne change dans le tunnel. Les
  autres adresses sont gardées dans la méta `_famma_addresses`, l'étiquette
  de celle par défaut dans `_famma_addr_lbl`. Supprimer celle par défaut
  fait passer la suivante par défaut.
- Actions par formulaires (jeton `famma_addr`, client connecté, case
  cochée), puis rechargement de la page : tout marche sans script.
  Classes `Account_Addresses` et `Account_Addresses_Actions`.
- Le téléphone quitte la feuille d'adresse et passe dans « Mes
  informations » (+216, « XX XXX XXX »), comme la maquette : numéro
  tunisien obligatoire, refusé s'il appartient à un autre compte ; la méta
  `_famma_phone` d'un compte créé par numéro suit (`Account_Identity_Phone`).
- 15 textes dans FAMMA → Pages → « Mon compte »
  (`Account_Addresses_Texts`) ; `ad_full` et `id_tel_taken` ne sont pas dans
  la maquette : derja à relire en priorité.

Écart restant : la commande ne propose pas encore de choisir une autre
adresse que celle par défaut.

## 0.3.24 — 2026-11-13 (inscription et connexion par numéro, lot 2 de l'espace client) · thème famma-child 0.2.11

Nouvelle case `acct_phone` (« Mon compte : inscription et connexion par
numéro »), **livrée décochée**, active seulement avec la case « présentation
de la maquette ». Décision D1 du propriétaire : e-mail facultatif, le numéro
sert d'identifiant.

- « Créer mon compte » comme la maquette : Téléphone (+216, « XX XXX XXX »),
  Nom et prénom, Mot de passe avec la jauge à 3 barres, puis « Ajouter mon
  adresse maintenant (facultatif) » qui déplie gouvernorat et « Ville, cité,
  rue ». Le champ e-mail de WooCommerce est masqué ; le mot de passe est
  choisi à l'inscription (aucun lien envoyé par e-mail) et l'identifiant est
  fabriqué par WooCommerce. Contrôles de la maquette dans le navigateur
  (8 chiffres, numéro tunisien, deux mots, 8 caractères) avec coche verte,
  résumé des erreurs en haut et secousse ; les mêmes contrôles sont refaits
  par famma-core, plus le numéro déjà inscrit, un champ piège et 6 essais
  par adresse IP en 10 minutes. La case « recevoir les nouveautés sur
  WhatsApp » de la maquette n'est **pas** posée : son texte (D5) reste à
  fournir par le propriétaire.
- Compte créé sans e-mail : adresse **technique**
  `216XXXXXXXX@tel.famma.invalid` (domaine `.invalid`, jamais joignable),
  numéro dans la méta `_famma_phone`, nom, téléphone, pays TN et, s'ils sont
  donnés, gouvernorat et adresse dans la facturation. Aucun événement de
  mesure à l'inscription.
- Protections de l'adresse technique (`Account_Phone_Guard`, actives même
  case décochée) : aucun e-mail ne part vers elle (`pre_wp_mail`), champ
  e-mail vide et facultatif au paiement, commande enregistrée sans e-mail,
  « Mes informations » l'affiche vide et la garde si le client n'en donne
  pas, pas d'encart « Confirmez votre adresse e-mail » de WooCommerce 11.
  Meta (CAPI) ne reçoit jamais cette adresse ; l'anneau « profil complété »
  ne la compte pas comme un e-mail.
- Connexion (`Account_Phone_Login`, active même case décochée) : « Téléphone
  ou e-mail » accepte le numéro sous toutes ses formes (espaces, +216,
  00216, chiffres arabes), et aussi celui de la facturation d'un ancien
  compte s'il est seul à l'avoir. Mauvais mot de passe : « Numéro ou mot de
  passe incorrect. » ; 20 essais par numéro par adresse IP en 10 minutes.
- 23 textes dans FAMMA → Pages → « Mon compte » (`Account_Phone_Texts`),
  français et derja de la maquette ; `err_taken`, `err_later`, `err_login`
  et `login_label` n'y sont pas : derja à relire en priorité.
- Mot de passe oublié : par WhatsApp, à la main, pour le moment (D3).

Le thème porte l'affichage (`inc/new-look-account-phone.php`,
`assets/css/new-look-account-phone.css`, `assets/js/new-look-account-phone.js`),
sans copie de modèle WooCommerce ; la jauge zxcvbn de WooCommerce cède la place
à celle de la maquette sur cette page.

## 0.3.23 — 2026-11-13 (« Ma sélection » gardée dans le compte, lot 5 de l'espace client) · thème famma-child 0.2.10

Nouvelle case `acct_favs` (« Mon compte : « Ma sélection » gardée dans le
compte »), **livrée décochée**. Case décochée : rien ne change, les favoris
restent dans le navigateur seulement.

- Client connecté : les produits mis de côté avec le cœur sont aussi gardés
  dans son compte (méta `_famma_favs`, 100 produits au plus, seulement des
  produits publiés). La première fois sur un navigateur, tout ce qui y est
  rejoint le compte ; ensuite seuls les ajouts et retraits partent, donc un
  produit retiré sur un téléphone ne revient pas par un autre.
- Route `POST famma/v1/favs` : client connecté, jeton `wp_rest`, case cochée
  pour lui ; identifiants nettoyés. Script `assets/js/favs-sync.js` chargé
  seulement pour un client connecté : les pages des visiteurs ne changent pas
  et restent en cache.
- Onglet « Ma sélection » (`/mon-compte/ma-selection/`, point d'accès
  WooCommerce déclaré par `woocommerce_get_query_vars`, règles de réécriture
  rafraîchies une seule fois) entre « Mes adresses » et « Mes informations »,
  avec la case « présentation de la maquette » : une carte par produit (photo,
  nom, prix, « Ajouter » au panier sans quitter la page, ou « Choisir » vers
  la fiche pour un produit à options), cœur pour retirer avec « Annuler »
  pendant 5 secondes, mascotte quand la sélection est vide. Le compteur
  « dans ma sélection » mène à l'onglet ; l'onglet porte le nombre.
- Nouvelle classe `Famma\Core\Account_Favs` (`includes/account-favs.php`) :
  8 textes dans FAMMA → Pages → « Mon compte », en français et en derja (à
  relire ; « Choisir » / « اختار » n'est pas dans la maquette).

Le thème porte l'affichage (`inc/new-look-account-favs.php`,
`assets/css/new-look-account-favs.css`, `assets/js/new-look-account-favs.js`) ;
`favourites.js` annonce chaque clic et suit la liste accordée.

## 0.3.22 — 2026-11-12 (« Mon compte » aligné sur la maquette, étape C : adresses et informations) · thème famma-child 0.2.9

Nouvelle case `acct_forms` (« Mon compte : adresses et informations de la
maquette »), **livrée décochée**, qui agit seulement avec `acct_look`. Case
décochée : « Mes adresses » et « Mes informations » restent ceux de
WooCommerce.

- « Mes adresses » : l'adresse du client dans une carte (icône maison,
  « Par défaut », nom, téléphone, gouvernorat et adresse) ou une carte
  « Ajouter une adresse ». « Modifier » ouvre une feuille qui monte du bas sur
  téléphone et glisse du côté sur ordinateur (à droite, à gauche en arabe) ;
  elle contient le formulaire d'adresse de WooCommerce, envoyé à WooCommerce
  comme sur sa propre page. Sans script, le lien mène à cette page.
- Sur la page du compte seulement (pas au paiement), le formulaire d'adresse
  ne garde que nom, prénom, gouvernorat, « Ville, cité, rue » et téléphone
  (nouvelle classe `Famma\Core\Account_Address`, filtres
  `woocommerce_billing_fields` et `woocommerce_address_to_edit`).
- « Mes informations » : boîtes « Identité » et « Connexion » ; le
  changement de mot de passe se déplie dans « Connexion », au-dessus de
  « Me déconnecter » (avec script ; sans script il reste dans le formulaire).
  Les deux boutons de WooCommerce deviennent « Enregistrer ».
- Nouvelle classe `Famma\Core\Account_Panels` (`includes/account-panels.php`) :
  15 textes dans FAMMA → Pages → « Mon compte », en français et en derja (à
  relire).

Écarts voulus avec la maquette, en attendant les lots concernés : une seule
adresse (titre « Mon adresse », pas « Maison » ; ni suppression ni « Utiliser
par défaut ») ; l'adresse de livraison séparée n'est plus listée ; la
feuille garde nom et téléphone (la maquette les prend du profil) ;
préférences et suppression du compte non faites (décisions D1-D6 et textes du
propriétaire).

Le thème porte l'affichage (`inc/new-look-account-forms.php`,
`assets/css/new-look-account-forms.css`, `assets/js/new-look-account-forms.js`).

## 0.3.21 — 2026-11-12 (« Mon compte » aligné sur la maquette, étape B : connexion) · thème famma-child 0.2.8

Toujours derrière la case `acct_look` (décochée en production).

- Carte marine « Votre espace FAMMA » à côté du formulaire, comme la
  maquette : mascotte qui cligne, phrase d'accroche, quatre avantages qui
  apparaissent en cascade, encadré « Pas besoin de compte pour commander »
  avec le lien « Commander sans compte » vers la boutique. Côte à côte à
  partir de 900 px, l'une sous l'autre sur téléphone.
- Onglets dans l'ordre de la maquette : « Créer un compte » d'abord et ouvert
  d'office, « Me connecter » ensuite (ouvert si l'adresse finit par #login ou
  après un refus de connexion).
- Titres et boutons de la maquette : « Créer mon compte » (titre et bouton),
  « Content de vous revoir », « Me connecter ». Seuls ces trois textes de
  WooCommerce changent, et seulement sur cette page.
- Nouvelle classe `Famma\Core\Account_Auth` (`includes/account-auth.php`) :
  20 textes dans FAMMA → Pages → « Mon compte », en français et en derja (à
  relire). Écarts voulus avec la maquette, en attendant les lots concernés :
  « Gardez votre adresse » (au singulier : une seule adresse pour l'instant)
  et pas de phrase « Trois champs… » sous « Créer mon compte » (l'inscription
  par téléphone n'existe pas encore).

Le thème porte l'affichage (`inc/new-look-account-auth.php`, nouvelle icône
`heart`).

## 0.3.20 — 2026-11-12 (« Mon compte » aligné sur la maquette, étape A) · thème famma-child 0.2.7

Aucune nouvelle case : `acct_look` et `acct_track` (toujours décochées en
production) suivent maintenant la maquette espace client à l'identique pour
le compte connecté. Cases décochées : « Mon compte » reste celui de
WooCommerce.

- `acct_look` : fil d'Ariane ; bandeau de titre de Kadence retiré ; carte
  marine (initiales, nom, téléphone, « Membre depuis », anneau « profil
  complété » 50/+30/+20 % et rappels) ; compteurs (commandes, en route,
  favoris lus dans le navigateur) ; onglets Mes commandes · Mes adresses ·
  Mes informations à toutes les largeurs, soulignement qui glisse d'un onglet
  à l'autre, panneau qui arrive du bon côté ; le compte s'ouvre sur les
  commandes ; « Me déconnecter » en bas de « Mes informations ».
- `acct_track` : commandes en cartes (photos, pastille « En route » /
  « Livrée », total et paiement, frise avec l'heure de chaque étape qui se
  remplit à l'écran, rappel pour le livreur, détail dépliable, « Recommander »
  et « Une question » sur WhatsApp) ; la page d'une commande montre la carte
  ouverte au lieu du tableau de WooCommerce. Même requête et même pagination
  que WooCommerce.
- Nouvelle classe `Famma\Core\Account_Profile` (`includes/account-profile.php`)
  et heure de chaque étape gardée dans la méta `_famma_track_times` (à partir
  de cette version ; les anciennes commandes montrent la date de réception et,
  si elle est connue, celle du paiement à la livraison).
- Section FAMMA → Pages « Mon compte » : en-tête, compteurs, onglets,
  déconnexion et textes des cartes, en français et en derja (à relire).

## 0.3.19 — 2026-11-12 (suivi de commande, lot 3 de l'espace client) · thème famma-child 0.2.6

Une nouvelle case dans FAMMA → Pages → Nouveau look, **livrée décochée** :
tant qu'elle n'est pas cochée, « Mon compte » est identique à la 0.3.18.

- `acct_track` : dans la liste des commandes, une barre de 4 segments sous le
  statut ; en tête du détail d'une commande, la frise Reçue → Confirmée →
  Expédiée → Livrée (date de réception, date de livraison), l'étape en cours
  qui bat doucement (coupé si le visiteur demande moins d'animations) et la
  ligne « À payer au livreur : {total} » tant que la commande n'est pas
  livrée. Une commande refusée, retournée, annulée, échouée ou remboursée
  montre son statut à la place de la frise.
- « Recommander » : les commandes **livrées** gagnent le lien natif de
  WooCommerce (`order_again`, signé, réservé au client de la commande) dans
  la liste, et son bouton dans le détail ; le panier est rempli aux prix du
  jour.
- Nouvelle classe `Famma\Core\Order_Track` (`includes/order-track.php`) :
  correspondance statut → étape (filtre `famma_order_track_step`) et textes.
- Nouvelle section **« Mon compte — suivi de commande (nouveau look) »** dans
  FAMMA → Pages (option `famma_page_account`) : les 4 étapes, la ligne du
  livreur et le bouton, en français et en derja (derja de la maquette, à
  relire).

Le thème porte l'affichage (`inc/new-look-account-track.php`,
`assets/css/new-look-account-track.css`, icônes `box` et `home`).

## 0.3.18 — 2026-11-12 (« Mon compte », lot 1 de l'espace client) · thème famma-child 0.2.5

Une nouvelle case dans FAMMA → Pages → Nouveau look, **livrée décochée** :
tant qu'elle n'est pas cochée, la page « Mon compte » est identique à la
0.3.17.

- `acct_look` : la page « Mon compte » prend la présentation de la maquette
  espace client — connexion et création de compte en deux onglets (sans
  script : les deux formulaires l'un sous l'autre, comme aujourd'hui), champs
  de 48 px et boutons de la charte, carte marine avec le nom du client, menu
  à icônes (en ligne sur téléphone, en colonne sur ordinateur), commandes en
  cartes sur téléphone avec le statut COD en couleur, détail de commande,
  adresses et « Mes informations » en cartes, apparitions douces (coupées si
  le visiteur demande moins d'animations).
- Aucun champ, e-mail, réglage ou modèle WooCommerce ne change ; seul le lien
  « Téléchargements » quitte le menu (la page reste servie).

Le thème porte le code (`inc/new-look-account.php`,
`assets/css/new-look-account.css`, `assets/js/new-look-account.js`) ; le
plugin ne fait que déclarer la case. Contient aussi la 0.3.17 (passerelle
Upsell One Click), fusionnée dans le nouveau look.

## 0.3.17 — 2026-11-12 (en production depuis le 12/11 ~17:15, lot 33b)

Passerelle avec le plugin indépendant **Upsell One Click** (offre après achat
en un clic, futur produit vendu).

### Ajouts

- `includes/uoc-bridge.php` : quand Upsell One Click est actif, l'offre après
  achat de famma-core **se met en veille** (bloc de remerciement et onglet
  produit) ; désactiver le plugin lui rend aussitôt la main.
- **Reprise unique** des réglages `_famma_offer_*` des produits, de
  l'historique des 60 derniers jours et des textes FAMMA (français + derja,
  statut « En attente de confirmation », paiement à la livraison). Copie
  seulement : rien n'est effacé, rien n'est écrasé.
- Produits à tailles exclus et commandes d'essai hors des chiffres, comme
  avant ; chaque étape est recopiée dans les méta `_famma_offer_*` et l'ajout
  met toujours en file l'événement Meta OfferAccepted (si la case est
  cochée).

Sans Upsell One Click installé, cette version se comporte exactement comme
la 0.3.16.

## 0.3.16 — 2026-11-12 (accueil, lot 3 de la maquette) · thème famma-child 0.2.3

Six nouvelles cases dans FAMMA → Pages → Nouveau look, **toutes livrées
décochées** : tant qu'aucune n'est cochée, les pages sont identiques à la
0.3.15 (vérifié sur le banc : accueil, boutique, rayon, fiche, panier).

### Ajouts

- **« Accueil : chiffres « 24 gouvernorats · 0 DT · 0 compte » »**
  (`home_facts`) : trois chiffres sous les étapes du bloc « Vous payez à la
  livraison », comptés à l'arrivée (de 0, ou de 9 vers 0). Chiffres et
  légendes modifiables en FR et en derja dans FAMMA → Pages → Accueil,
  champs « Chiffres » (`Home_Content::facts_schema()`) ; un chiffre vide
  disparaît. Valeur finale écrite par le serveur : rien ne se compte pour un
  visiteur qui demande moins d'animations.
- **« Accueil : « Pourquoi FAMMA » juste après le paiement à la
  livraison »** (`home_why`) : le contenu de la page d'accueil est coupé
  après ce bloc et les cartes s'y insèrent. Sans le bloc dans la page, elles
  restent à leur place.
- **« Accueil : retirer le bloc « Une question sur un produit ? » »**
  (`hide_band`) : l'accueil seulement ; la boutique le garde, la carte
  WhatsApp des questions et le bouton vert flottant restent.
- **« Accueil : icônes des rayons »** (`cat_icons`) et champ **« Icône sur
  l'accueil »** dans Produits → Catégories (`includes/cat-icons.php`, méta
  de terme `_famma_cat_icon`, jeton + droit `edit_term`, valeur limitée à
  6 icônes). Ordre : miniature de la catégorie, puis icône au trait dessinée
  au défilement, puis l'étoile orange.
- **« Accueil : anneau sur le bouton WhatsApp »** (`wa_ring`) : trois ondes
  à l'ouverture, puis plus rien.
- **« Partout : icône « Mon compte » dans l'en-tête du téléphone »**
  (`acct_mobile`) : l'icône rejoint le panier ; le lien du menu se range
  (le compte n'existe toujours qu'une fois).

### Thème famma-child 0.2.3

- `inc/new-look-home-extras.php` et `assets/css/new-look-home-extras.css`
  (nouveaux, chargés sur l'accueil seulement si une case les sert) ;
  `front-page.php`, `inc/home.php`, `inc/new-look.php`, `functions.php`,
  `assets/js/new-look.js` (comptage des chiffres).

## 0.3.15 — 2026-11-12 (en production depuis le 12/11 ~02:55, lot 30)

Toute la galerie du produit proposé dans la fiche rapide « Découvrir le
produit ».

### Modifications

- **Toutes les photos** du produit proposé (principale + galerie) dans la
  fiche rapide, au lieu de 5 : plafond de sécurité porté à 12
  (`Offer_Settings::MAX_PHOTOS`). Seules les vraies images comptent : une
  pièce jointe supprimée ou qui n'est pas une image est écartée.
- **Flèches précédent / suivant** sur la photo (44 px, masquées au début et
  à la fin, inversées en arabe), **compteur « 2 / 6 »** annoncé aux
  lecteurs d'écran, et **points cliquables** pour aller à une photo.
  Le glissement au doigt reste possible.
- Défilement calculé sur la position réelle de la photo : juste en
  français comme en arabe ; un double toucher rapide ne saute pas de photo.
- Au-delà des deux premières, les photos se chargent à l'approche
  (`loading=lazy`).

## 0.3.14 — 2026-11-12 (en prod depuis le 12/11, lot 28)

Fiche rapide « Découvrir le produit » dans l'offre après achat, pour la
cliente qui ne connaît pas l'article proposé.

### Ajouts

- **Photo cliquable et lien « Découvrir le produit · شوف المنتج »** dans le
  bloc de l'offre : ils ouvrent une fiche rapide **sans quitter la page de
  remerciement** (`includes/offer-thankyou.php`, `assets/js/offer-sheet.js`).
  Sur la fiche produit, la cliente perdait le prix spécial et pouvait passer
  une deuxième commande.
- **Fiche rapide** (`<dialog>` natif) : photos à faire glisser (principale +
  galerie, 5 au maximum, chargées seulement à l'ouverture), nom, prix barré
  et prix spécial, 3 points forts (FR + derja), « Comment l'utiliser »,
  rappels vrais (paiement à la livraison, même colis, livraison gratuite,
  changement d'avis possible à l'appel). **Barre fixe en bas** avec
  « Oui, ajouter · +22 DT » (même formulaire, mêmes gardes) et « Retour ».
  Bas d'écran sur téléphone, fenêtre centrée à partir de 768 px.
- **Fermeture facile** : croix, « Retour », Échap, toucher le fond, glisser
  la poignée vers le bas, et **bouton retour du téléphone** (entrée
  d'historique), qui ferme la fiche au lieu de quitter la page.
- **Onglet « Offre après achat »** (`includes/offer-admin.php`) : case
  « Fiche « Découvrir le produit » » (cochée par défaut), points forts (un
  par ligne, derja après « | », 3 au maximum ; vide = description courte de
  l'article), mode d'emploi facultatif, et nouveau chiffre « fiche ouverte X
  fois, dont Y suivies d'un ajout ».
- **Mesure d'ouverture** : envoi discret (`sendBeacon`) vers la page de
  remerciement, action `view` vérifiée par clé de commande + nonce, méta
  `_famma_offer_viewed_at`, réponse 204 sans redirection.

### Comportement inchangé

- Sans JavaScript, le lien mène à la fiche produit ; le bouton d'ajout du
  bloc fonctionne toujours sans script.
- Script différé (`strategy => defer`), chargé seulement sur la page de
  remerciement ; aucune bibliothèque ajoutée.

## 0.3.14 (accueil) — 2026-11-12 (en production depuis le 12/11 ~02:27, lot 29)

Accueil animé, lot 2 de la maquette. Construite sur la 0.3.13 en
production. Envoyée une minute après le lot 28 (fiche rapide), qui porte
le même numéro : `famma-core.php` identique dans les deux lots. Thème FAMMA Child 0.2.2.

### Ajouts

- **Case « Accueil : animations de la maquette »** (brique `home_motion`,
  FAMMA → Pages → Nouveau look), **livrée décochée**. Page d'accueil
  seulement :
  - barre d'avancement de la lecture en haut de l'écran ;
  - mascotte du logo qui cligne, antenne et étincelles ; clin d'œil à
    chaque ajout au panier ; mascotte à la place du cadeau dans
    l'infolettre, clin d'œil à son arrivée ;
  - entrée du héros (nom, A, slogan, accroche, avantages, bouton), pile en
    ressort sans fondu (l'image LCP reste peinte d'emblée), bulle fantôme
    et deux étincelles ;
  - carrousel : bouton pause (`aria-pressed`) et minuteur ; la barre pleine
    fait avancer, se fige au survol, au clavier, hors écran et sur pause ;
  - parcours « Vous payez à la livraison » (avec la case du parcours) :
    bande qui s'ouvre, colis qui roule sur la route, route horizontale dès
    800 px, 24 gouvernorats (états WooCommerce) en deux bandes décoratives ;
  - apparition des sections au défilement.
- Tout mouvement est coupé pour « moins d'animations » ; les effets liés au
  défilement sont en CSS (`animation-timeline`), sans écouteur de
  défilement. Feuille `new-look-home.css` (4,2 Ko compressés), chargée
  seulement quand la case est cochée.

### Contrôles (banc Docker, 12/11)

- Case décochée : accueil, boutique, fiche et panier identiques au code de
  la 0.3.13 (hors identifiants aléatoires WooCommerce).
- Case cochée : balisage attendu, aucun débordement à 375 et 1024 px,
  console propre, `debug.log` sans ligne ajoutée, parcours COD conforme
  (payée seulement à « livrée », pas de double encaissement).

## 0.3.13 — 2026-11-12 (en production depuis le 12/11, lot 27)

### Changements

- **Bloc de l'offre déplacé tout en haut** de la page de remerciement
  (`woocommerce_before_thankyou`), sous le titre « Commande reçue » : le
  bouton est visible sans défiler à 360 × 740, 390 × 844, 768 × 1024 et
  1366 × 768 (mesuré sur une copie de la prod).
- **Carte compacte** : en-tête « Offre spéciale · عرض خاص » avec « Non
  merci », photo avec pastille de remise, titre « Ajoutez-le à votre
  colis », prix barré et prix spécial (montants `dir="ltr"` pour l'arabe),
  bouton « Oui, ajouter · +22 DT » ; un seul formulaire.
- `offer.css` réécrite, toutes les règles préfixées `.famma-offer`.
- Feuille redatée le 12/11 (lot 27b) pour forcer un nouveau `?ver=`.

## 0.3.12 — 2026-11-12 (en production depuis le 11/11, lot 26)
Offre après achat en un clic. Construite sur la 0.3.11 (non envoyée) :
les deux partent ensemble ou la 0.3.11 d'abord.

### Ajouts

- **Onglet produit « Offre après achat »** (`includes/offer-admin.php`) :
  case « Proposer une offre », article proposé (produits simples publiés
  sans taille), prix spécial, titre et texte facultatifs, et résultats
  sur 30 jours (vues, acceptations, taux, refus, montant ajouté ; commandes
  d'essai exclues, cache 10 min).
- **Bloc sur la page de remerciement** (`includes/offer-thankyou.php`,
  `assets/css/offer.css`) : photo, prix de la fiche barré, prix spécial,
  bouton « Ajouter à ma commande » (56 px, FR + derja) et « Non merci ».
  Formulaire renvoyé à la page elle-même, sans JavaScript ; gardes : clé
  de commande, nonce, statut « En attente de confirmation », verrou contre
  le double clic. L'article est ajouté à la **même commande** au prix
  spécial (ligne marquée `_famma_offer_item`), total recalculé, note
  ajoutée. Offre fermée dès la confirmation, après acceptation ou refus.
- **Événement Meta `OfferAccepted`** (classe `Capi`) : même file d'attente,
  registre, relances et journal qu'OrderPlaced ; valeur = montant ajouté
  seulement ; commandes d'essai écartées. Interrupteur dans les réglages de
  mesure, **désactivé par défaut**. Le Purchase de la livraison inclut déjà
  l'article ajouté.

### Règles partagées

- `includes/offer-settings.php` : lecture des réglages, éligibilité
  d'une commande, statistiques.

### Vérifié

- `php -l` et `phpcs` (ruleset du projet) propres sur les fichiers touchés.
- Banc sur le site Local, plugin du worktree chargé : 31/31 (ajout,
  total 22 → 44 DT, double clic, commande confirmée, refus, article déjà
  commandé, Meta coupé par défaut, valeur OfferAccepted 22 / Purchase 44,
  statistiques, nettoyage).
- Non vérifié : rendu réel de la page de remerciement et clic dans un
  navigateur (la jonction du site Local pointe sur la copie principale).

## 0.3.11 — 2026-11-11 (en production depuis le 11/11/2026, lot 26)

Accueil rapproché de la maquette (« lot 1 », mise au propre) et bande
« 0 DT » de la fiche. Trois nouvelles cases dans FAMMA → Pages → « Nouveau
look animé », livrées **décochées** : tant qu'elles le restent, les
visiteurs reçoivent le même rendu qu'en 0.3.10. Rien ne change dans le
pixel, CAPI, GA4, le formulaire express, le panier ni le tunnel.

### Briques (plugin)

- `site_polish` — « Partout : police Poppins, bandeau qui défile, pied de
  page de la maquette ».
- `home_polish` — « Accueil : mise en page de la maquette ».
- `ledger` — « Fiche produit : bande Livraison 0 DT · Avant réception
  0 DT » (zéros affichés seulement s'ils sont vrais dans WooCommerce).

### Thème famma-child 0.2.1

- `assets/css/new-look-site.css`, posée dans la page derrière
  `famma-layout` (2 Ko compressés, aucune requête de plus) : Poppins pour
  tout le texte (la pile système du thème parent l'emportait), bandeau des
  promesses qui défile sous 1000 px (copie muette `aria-hidden`, arrêté si
  le visiteur demande moins d'animations), pied de page en colonnes,
  en-tête resserré entre 1000 et 1279 px (la page débordait de 150 px à
  1024 px, déjà en 0.3.10).
- `assets/css/new-look.css`, partie accueil : bouton sous le nom sur
  téléphone, rayons en grille, nouveautés sur deux colonnes, titres et FAQ
  de la maquette, carte WhatsApp verte, cartes « Pourquoi », infolettre
  claire. Aucun texte ajouté ni retiré.
- `famma_child_topbar()` : chaque promesse passe par
  `famma_child_topbar_promise()` ; la fenêtre et la copie ne sont posées
  que si la brique est cochée.
- `assets/css/new-look-ledger.css` : bande « 0 DT » sous le prix de la
  fiche.

## 0.3.10 — 2026-11-11 (en production depuis le 11/11/2026)

Nouveau look animé (maquettes du 04-06/11), livré **éteint** : interrupteur
FAMMA → Pages → « Nouveau look animé » sur *Désactivé* par défaut. Tant qu'il
reste désactivé, les visiteurs reçoivent le même HTML qu'en 0.3.9, à deux
exceptions près, voulues : l'image du héros de l'accueil est choisie à la
bonne taille, et le thème passe en 0.2.0. Rien ne change dans le pixel,
CAPI, GA4, le formulaire express, le panier ni le tunnel.

### Interrupteur (plugin)

- Option `famma_new_look` : mode *Désactivé* / *Administrateurs seulement* /
  *Tout le monde*, et une case par brique. En mode administrateurs, les
  pages des connectés ne sont jamais mises en cache ; tout changement de
  réglage purge le cache de page.
- Briques : texte marine sur orange, apparitions et vol vers le panier,
  seconde photo au survol, transitions entre pages, puces rayons et budget
  (boutique, mobile), bloc « Comment commander » (boutique), parcours
  « Vous payez à la livraison » (accueil), barre du panier « Aujourd'hui
  0 DT ».

### Textes modifiables (plugin)

- Accueil : héros (titre, slogan, phrase, 3 avantages, bouton) et
  « Pourquoi FAMMA » (titre, 4 cartes), en FR et AR. Défaut = texte affiché
  jusqu'ici. Champ vidé = élément masqué.
- Nouvelle section « Boutique » (`Shop_Content`, option `famma_page_shop`) :
  bloc « Comment commander », titre et 3 étapes, en FR et AR.

### Thème famma-child 0.2.0

- Couche `inc/new-look*.php`, `assets/css/new-look*.css`,
  `assets/js/new-look.js` (5,4 Ko de JS et 4,9 Ko de CSS compressés, chargés
  seulement sur l'accueil, la boutique et les rayons, et seulement si une
  brique est active). Le script est différé et n'appelle ni `fbq` ni `gtag`.
- Puces : vrais liens GET (mêmes paramètres que la colonne « Filtres »), pages
  filtrées toujours en `noindex`. Budgets tirés des vrais prix publiés, gardés
  dans l'option `famma_shop_price_steps` (effacée à chaque enregistrement de
  produit).
- Héros : attribut `sizes` corrigé (600 px au lieu de 768 px sur les
  téléphones de densité 1,75 : −18 Ko).
- Deux points d'accroche ajoutés au gabarit de la boutique :
  `famma_child_after_shop_head`, `famma_child_after_shop_results`.

## 0.3.9 — 2026-11-11 (en production depuis le 11/11/2026)

Formulaire de commande express repensé pour le navigateur intégré de
Facebook (lot 23). Objectif : faire passer les formulaires commencés qui
deviennent des commandes de 33 % à 60 %.

### Adresse de livraison

- Le gouvernorat devient une liste simple (liste native du téléphone) : plus
  de menu à recherche dont le texte tapé « non choisi » bloquait l'envoi.
- À côté, dans le même cadre, un champ facultatif « Ville, cité, rue… »
  envoyé dans `billing_address_1`.
- L'ancien menu à recherche reste dans le script tant que le cache peut
  servir l'ancien gabarit.

### La ville tapée propose le gouvernorat

- Nouvelle classe `Governorate_Places` : les 264 délégations officielles, en
  français et en arabe (Ministère de l'Intérieur, données ouvertes
  « Délégations par gouvernorats de la République », 25/06/2013 ; l'INS ne
  publie pas de liste des délégations). Source citée en tête du fichier,
  filtre `famma_governorate_places`.
- « Hammamet » choisit Nabeul, « جربة » Médenine. La liste s'allume
  brièvement et l'aide annonce « Gouvernorat ajouté : … Vous pouvez le
  changer. ». Un gouvernorat choisi à la main n'est jamais remplacé ; rien
  n'est rempli quand deux gouvernorats sont possibles.
- Seuls les noms officiels sont reconnus (noms courants refusés par le
  propriétaire le 11/11). Écartés : « La Médina » (nom commun) et
  « Azzouhour » (Tunis et Kasserine).
- 572 noms transmis en script en ligne (`JSON_HEX_TAG`), 3,6 Ko compressés.

### Téléphone en premier

- Ordre : téléphone, nom, adresse, taille, bouton.
- « +216 » affiché fixe devant le champ ; le numéro se groupe
  « XX XXX XXX » à la frappe ; +216, 00216, espaces et chiffres arabes collés
  restent acceptés (même règle que le serveur).
- Saisie mobile : `tel-national`, `inputmode="numeric"`, `enterkeyhint`
  (« Suivant » passe au champ à taper suivant, « Envoyer » sur la ville),
  majuscules aux mots sur le nom ; le champ caché par le clavier remonte
  dans la zone visible.
- Message serveur du téléphone en français + derja sur le site français.

### Phrase sous le téléphone

Nouveau texte « Formulaire — phrase sous le téléphone » dans FAMMA → Pages →
Fiche produit (français et derja). Vide par défaut, sur décision du
propriétaire du 11/11 : rien ne s'affiche sous le téléphone tant qu'une
phrase n'y est pas écrite.

### Mesure

- Journal : événement `form_error` à chaque envoi refusé, avec des codes de
  cause seulement (téléphone, nom, gouvernorat, taille, navigateur,
  stockage, réseau, serveur, panier changé), jamais une valeur tapée.
- FAMMA → Visiteurs, Synthèse : bloc « Formulaire express : de commencé à
  commandé », par produit, avec les causes de refus (mis en cache 5 min).
- La commande express emporte l'identifiant de visite dans deux champs
  cachés : le serveur s'en sert seulement si ses cookies manquent, et
  seulement pour un UUID v4 reçu pendant un checkout au jeton vérifié.
  Le Pixel et CAPI ne changent pas.

## 0.3.8 — 2026-11-10 (en production depuis le 10/11)

### Remarque des commandes

WooCommerce → Commandes : colonne « Remarque » (avant les actions),
modifiable sur place sans ouvrir la commande (Ctrl+Entrée enregistre, Échap
annule), et encadré « Remarque » dans la fiche commande. Note interne de
l'équipe (méta `_famma_order_note`), distincte de la note du client ; rien
n'est envoyé à Meta.

### Saisies à relancer : page de travail

- Compteurs cliquables par statut et taux de récupération ; recherche (nom,
  téléphone, adresse, ville, campagne, remarque), filtres produit et période.
- Statut et remarque modifiables sur place (AJAX, sans recharger) ; alerte
  « nouvelles saisies » toutes les 60 s ; affichage mobile en fiches.
- Deux statuts en plus : « Injoignable » (encore vivant : une commande du
  même client le fait passer à « Commandé ») et « Pas intéressé » (reste tel
  quel). « Commandé » ne se pose plus à la main.
- Colonne `note` ajoutée à `wp_famma_visitor_inputs` (schéma 2, `dbDelta`).

### Export Excel complet

Le bouton « Exporter en Excel » produit un vrai fichier .xlsx avec les 24
champs de chaque saisie, pour les filtres en vigueur (en-tête figé, filtres
automatiques, textes jamais lus comme formules). Générateur sans dépendance
(`Xlsx_Writer`, ZipArchive ou PclZip de WordPress). L'ancien CSV reste
disponible sous « Audience Meta (CSV) » : c'est le seul format accepté par le
Gestionnaire de publicités.

### « Créer la commande » depuis une saisie

Un clic crée la commande WooCommerce avec chaque donnée dans son champ :
prénom, téléphone, gouvernorat, adresse et ville (facturation et
livraison), paiement à la livraison, produit, quantité et taille, ligne de
livraison de la zone (0 DT au client), coût interne de livraison, campagne
d'origine, visite reliée et remarque. Statut « En attente de confirmation »,
comme une commande du site (même e-mail « Nouvelle commande ») ; sans
produit, la commande reste « Attente paiement » pour qu'on l'ajoute.

- Canal réservé « Panier récupéré », posé par ce seul bouton et non
  modifiable ensuite ; une commande finie par le client lui-même sur le site
  reste « Site ».
- `created_via = admin` : aucun OrderPlaced vers Meta, ni IP, ni navigateur,
  ni cookie Meta de l'administrateur sur la commande. Seule une vraie
  livraison enverra le Purchase.
- Verrou contre le double clic ; une saisie déjà reliée renvoie vers sa
  commande.

## 0.3.7 — 2026-11-09 (en production depuis le 09/11)

### Type de chaque session (humain ou robot probable)

FAMMA → Parcours visiteurs : nouvelle colonne « Type » après « Source » et
tableau « Qui sont ces sessions ? » (répartition de la période, avec le sens
de chaque type). Le type vient du comportement mesuré par le navigateur :
action ou ≥ 10 s ou ¼ de page défilé (intéressé), 3 à 10 s (lecture rapide),
moins de 3 s sans défiler (rebond éclair), page jamais affichée (non
affichée), plusieurs pages en 2 s sans affichage (robot probable). Les robots
déclarés et l'équipe connectée n'étaient déjà jamais enregistrés.

- Une seule règle, en SQL (`Visitor_Session_Type`), pour la liste et le
  décompte ; décompte gardé cinq minutes en transient.
- Durée d'une session d'un seul instant : « moins d'une seconde » au lieu de
  « 57 ans » (`human_time_diff( 0, 0 )` comptait depuis 1970).

## 0.3.6 — 2026-11-09 (en production depuis le 09/11)

### Saisies à relancer

Le journal visiteurs ne gardait que le nom des champs touchés. Ce que les
visiteurs tapent dans le formulaire express et dans le checkout (nom,
téléphone, gouvernorat, adresse, ville, taille, quantité) est maintenant
gardé aussi, une ligne par session, pour les rappeler ou les recibler.

- Table dédiée `wp_famma_visitor_inputs`, conservation 30 jours par défaut
  (réglable de 7 à 180), purge quotidienne.
- Route publique `famma/v1/inputs` : même protection que `famma/v1/collect`
  (corps plafonné, UUID, robots et équipe écartés, limite de débit, liste
  blanche des champs, gouvernorat vérifié contre la liste de WooCommerce).
- Une ligne passe toute seule à « Commandé » quand une commande arrive avec
  la même session, le même visiteur ou le même téléphone (commande du site,
  ou commande saisie à la main après un rappel).
- FAMMA → Saisies à relancer : téléphone cliquable et lien WhatsApp, statut
  (à relancer, appelé, commandé, clos), lien vers le parcours de la session,
  export CSV « liste de clients » pour une audience Meta, réglages
  (interrupteur, durée de conservation).
- Chronologie d'une session : encadré « Saisies de cette session ».
- Outils → Effacer les données personnelles : efface aussi ces saisies.
- Rien ne change côté client (aucun texte ajouté au formulaire) ni dans ce
  qui part vers Meta (pixel, CAPI).
- Refactoring : nettoyage du texte, chemin d'URL et limite de débit mis en
  commun dans `Visitor_Context` ; liste des gouvernorats lue à un seul
  endroit (`Visitor_Inputs::states()`) ; envoi navigateur factorisé
  (`send()`) dans `visitor-journal.js`.

## 0.3.5 — 2026-11-09 (en production depuis le 09/11)

### Canal de commande (Messenger, WhatsApp, Instagram, téléphone…)

Une commande reçue par message ou par téléphone est saisie à la main dans
l'admin : rien ne disait plus d'où elle venait. Donnée interne uniquement :
rien ne change dans ce qui part vers Meta (ni OrderPlaced, ni Purchase, ni
`action_source`), ni dans la provenance UTM `_famma_order_source`.

- Encadré « Canal de la commande » sur la fiche commande (HPOS et ancien
  stockage) : canal et « pub ou produit d'origine » (100 caractères au plus),
  méta `_famma_order_channel` et `_famma_order_channel_ref`. Une commande du
  site (`created_via = checkout`) est « Site » d'office, sans écriture, et
  son canal ne se modifie pas. Nonce, capacité `manage_woocommerce`, liste
  blanche des canaux (`includes/order-channel.php`).
- FAMMA → Canaux de commande : liste des canaux manuels, un par ligne, 15 au
  plus ; « Site » réservé ; par défaut Messenger, WhatsApp, Instagram,
  Téléphone, Autre (option `famma_order_channels`,
  `includes/order-channel-admin.php`).
- Liste des commandes : colonne « Canal » (« À préciser » en orange) et
  filtre par canal (`includes/order-channel-list.php`).
- Tableau de bord : « Ventes par canal — 30 jours » (commandes, livrées, taux
  de livraison sur les seules commandes clôturées).

Tests (site Local, HPOS, 09/11/2026) : 21/21 en ligne de commande, puis
parcours réel dans l'admin (réglage, création d'une commande, colonne,
filtres WhatsApp et Site, tableau de bord) ; aucun appel à Meta, debug.log
inchangé ; données de test supprimées.

## 0.3.4 — 2026-10-21 (déployée le 21/10/2026)

### Qualité de correspondance : noms, code postal, ville et fbc de secours

Corrige les défauts D1, D2, D4, D5, D6 et D7 du rapport sur la qualité de
correspondance du 21/10/2026 (`audit-preuves/2026-10-19-pixel/emq/`), ceux
qui ne demandent aucune décision du propriétaire. Rien ne change dans les
données enregistrées sur la commande : seul le bloc `user_data` envoyé à
Meta (OrderPlaced et Purchase) est concerné.

- Prénom et nom (D1) : les espaces sont retirés en plus de la ponctuation et
  des symboles, comme le font le pixel (fbevents.js 2.9.426,
  `whitespace_and_punctuation`) et le Parameter Builder officiel
  (`getNormalizedName`). « Ben Salah » est haché en `bensalah` (et non plus
  `ben salah`), « بن صالح » en `بنصالح` ; minuscules UTF-8 après composition
  NFC, aucune translittération. Ces deux outils ne retirent que la
  ponctuation ASCII : FAMMA retire aussi la ponctuation et les symboles
  Unicode (’, « », ، , emoji), ainsi que les caractères invisibles (marques
  de direction, espaces et liants de largeur nulle, sélecteurs de variante
  d'emoji) ; pour ces caractères, l'empreinte diffère de celle du pixel.
- Civilités (D2, F1, F2) : si le premier mot du prénom est une civilité
  d'une courte liste (M, Mr, Mme, Mlle, Melle, Mrs, Ms, Dr, السيد, السيدة,
  السيده, الآنسة, الانسة ; casse et point ou virgule final ignorés, virgule
  arabe « ، » comprise, marque de direction en tête comprise) et qu'un autre
  mot suit, elle est retirée du bloc envoyé ; une ponctuation isolée
  (« M . Ali ») ne compte pas comme un mot. Le découpage du nom complet à la
  commande faisait de « Mme Leila Ben Ali » le prénom `mme` : le prénom
  envoyé devient `leila`, le nom `benali`. Suivie d'un seul mot
  (« Mme Trabelsi », « M. Ali », ou prénom « Mme » et nom « Trabelsi »), la
  civilité est supposée précéder le nom de famille (convention retenue le
  21/10) : ce mot part en `ln` et aucun `fn` n'est envoyé (F1 ; la 0.3.3
  envoyait `fn` = `mme`) ; un prénom tapé seul après une civilité
  (« Mme Leila ») part donc en `ln`. Une civilité seule reste inchangée ;
  aucune abréviation n'est développée. Limite connue : un nom de famille
  composé après une civilité (« M. » / « Ben Ali ») part en `fn` = `ben`,
  `ln` = `ali`, comme avant.
- Code postal (D4) : nouvelle clé `zp`, hachée, depuis le code postal de
  facturation, sinon d'expédition, seulement s'il a été saisi (minuscules,
  lettres et chiffres seuls, sans espace ni tiret ; un code américain au
  format 12345-6789 ne garde que ses 5 premiers chiffres). Un code contenant
  une lettre ou un chiffre hors ASCII (chiffres arabes) est écarté entier,
  jamais tronqué ni converti ; moins de 2 caractères, rien n'est envoyé,
  comme pour le pixel et le Parameter Builder. Jamais déduit de la ville ni
  du gouvernorat. Le checkout ne propose pas ce champ : `zp` ne partira que
  pour un code saisi ailleurs (administration, formulaire express).
- Ville (D5) : si la ville de facturation ne donne rien une fois normalisée
  (vide, tiret, espace insécable), la ville d'expédition est lue. Même règle
  pour le code postal. Normalisation de `ct` inchangée (les accents restent,
  à trancher par un test).
- fbc de secours (D6, nouveau fichier `includes/capi-click-id.php`) : sans
  cookie `_fbc` enregistré à la commande, `fbc` est reconstruit depuis
  l'attribution des commandes de WooCommerce si l'URL d'entrée de la session
  (`_wc_order_attribution_session_entry`) porte un fbclid valide :
  `fb.1.<début de session en ms>.<fbclid exact>`. Le début de session
  (`_wc_order_attribution_session_start_time`) est lu en UTC, format
  `AAAA-MM-JJ HH:MM:SS` de WooCommerce, quel que soit le fuseau de PHP ou du
  site ; c'est l'instant où WooCommerce a observé l'URL d'entrée, ce que Meta
  demande pour `creationTime` faute de cookie. Le fbclid suit les règles du
  cookie (`[A-Za-z0-9_-]`, 500 caractères au plus), casse conservée ; si
  l'URL en porte plusieurs, le premier est retenu, comme dans le navigateur.
  Le début de session vient de l'horloge du téléphone : s'il est postérieur
  à la création de la commande de 24 h au plus (horloge en avance), la date
  de création de la commande sert de `creationTime`, instant où le serveur
  a observé ce fbclid au plus tard (F3) ; au-delà de 24 h, ou plus de 90
  jours avant la commande (durée de vie du cookie), pas de `fbc`. Une
  métadonnée absente ou invalide : pas de `fbc`. Le cookie reste
  prioritaire ; OrderPlaced et Purchase portent la même valeur, tant que la
  date de création de la commande n'est pas modifiée dans l'administration.
  Le journal `famma-capi` n'écrit toujours que `fbc=oui` ou `fbc=non`, et la
  colonne « fbc présent » de l'écran de rapprochement suit désormais le
  `fbc` réellement envoyé (cookie ou attribution), sans jamais afficher la
  valeur.
- Tests (D7) : `test-capi-normalisation.php` n'accepte plus que `bensalah` ;
  nouveau `scripts/tests-unit/test-capi-match-keys.php` (D1, D2, D4, D5, D6 :
  noms d'un et trois mots, arabe, tiret, civilité seule ou suivie d'un nom,
  codes postaux, ville d'expédition, fbclid absent, invalide ou valide,
  cookie prioritaire, fuseau du début de session, OrderPlaced puis Purchase
  par la vraie classe Capi), complété après relecture : caractères
  invisibles, codes postaux mixtes ou d'un caractère, repli d'expédition
  après normalisation, fbclid en double, colonne fbc du rapprochement ;
  puis civilité suivie d'un mot, variantes de civilité (virgule arabe,
  ponctuation isolée comprises) et horloge en avance (F1 à F3).
- Découpage (F4, nouveau fichier `includes/capi-person-name.php`) : la
  normalisation des prénoms et noms et le retrait de la civilité passent
  dans la classe `Capi_Person_Name`, chargée avant `capi-user-data.php`.
  `Capi_User_Data::name()` et `Capi_User_Data::without_civility()` restent
  disponibles et lui délèguent. Aucun autre résultat ne change (sonde sur
  45 paires prénom et nom, avec et sans intl).
- Retour arrière vers la 0.3.3 : rien à reprendre ; aucune donnée n'est
  écrite par la 0.3.4, les envois reprennent l'ancienne normalisation.

## 0.3.3 — 2026-10-20

### Santé de la mesure : la sonde vérifie le jeton, plus l'objet Pixel

Constaté en production le 20/10/2026 : la sonde de la 0.3.2 lisait
`GET /v26.0/{pixel}?fields=id` avec le jeton de l'API Conversions. Meta
répondait HTTP 400 « (#100) Missing Permission » (version servie v26.0,
jeton valide : aucune erreur 190) ; ce jeton envoie des événements mais ne
peut pas lire l'objet Pixel. Le bandeau de santé restait orange en
permanence (« Sonde indisponible (code 100) »), ce qui rendait l'alerte
inutile.

- La sonde lit désormais `GET /{version}/me?fields=id` : le jeton d'un
  utilisateur système renvoie l'identifiant de cet utilisateur (doc Meta,
  « Facebook Login for Business », requête `/me` avec un jeton d'utilisateur
  système). Elle vérifie le jeton et la version servie (en-tête
  `facebook-api-version`, toujours lu et comparé) ; l'accès au jeu de
  données reste prouvé par les vrais envois (transport, rapprochement,
  santé). Toujours aucun POST /events, aucune requête hors production, jeton
  dans l'en-tête `Authorization` et jamais dans le journal `famma-capi`.
- Verdict de la sonde (`Capi_Probe_Verdict`) : jeton invalide ou expiré
  (codes 190 et 102, sous-codes 463 et 467) → rouge `probe_token` ; droits
  refusés sur `/me` (codes 10, 200 à 299 dont 294, 368, ou « Missing
  Permission ») → orange `probe_permission` « droits du jeton à vérifier » ;
  transitoire, réponse inattendue ou sans identifiant → orange
  `probe_unavailable` « sonde indisponible » ; identifiant reçu → vert.
- Une sonde valide n'efface plus qu'un refus d'envoi dû au jeton (190, 102) :
  un refus de droits sur le jeu de données attend un envoi accepté.
- Signal de sonde laissé par la 0.3.2 : seul son refus de jeton (190) compte
  encore ; il est rejoué sur `/me` par Action Scheduler à la prochaine
  vérification quotidienne de la planification (ou « Vérifier maintenant »).
- Textes de l'écran « Mesure » et raisons de santé revus (« jeton valide,
  version servie vérifiée » au lieu de « connexion valide »).
- Retour arrière vers la 0.3.2 : rien à reprendre ; elle ignore le champ
  `verdict` et relira l'objet Pixel (bandeau orange de nouveau).

## 0.3.2 — 2026-10-19 (déployée le 20/10/2026)

Lot 5 du plan `docs/PLAN-TESTS-PIXEL-2026-10-19.md` : stratégie S1 à S3,
résilience aux évolutions de Meta (S6, mécanismes P0) et correctifs des
défauts prouvés par les tests des lots 0 à 4 (D1 à D7).

### API Conversions (serveur)

- Version de l'API Meta réglable : constante `FAMMA_META_GRAPH_VERSION`, puis
  réglage « Mesure », puis `v26.0` par défaut. Fin des deux `v21.0` en dur
  (v21.0 prend fin le 21/01/2027). Avertissement à J-60, bandeau critique si
  une version expirée reste réglée.
- Transport unique vers Meta (`Capi_Transport`), partagé par les vrais envois
  et le bouton « Test CAPI » ; chaque réponse est classée (accepté, avec
  avertissement, transitoire, accès, contrat) et journalisée avec la version
  demandée et la version servie.
- Envois incertains (coupure réseau, délai, 5xx) relancés par Action
  Scheduler (`famma_capi_retry` : 5 min, 30 min, 2 h, 6 h) avec le même
  event_id, sur 6 jours au plus : un Purchase n'est plus perdu (D1).
- Garde d'environnement : hors production, aucun envoi réel vers Meta
  (dérogation : `FAMMA_META_ALLOW_NON_PRODUCTION`) (D4).
- « Terminée » vaut « Livrée » pour une commande en paiement à la livraison :
  encaissement (priorité 10) puis un seul Purchase (priorité 20). Commande
  créée dans l'admin : `action_source` = `other` (D5).
- Une ligne de journal `famma-capi` par issue (9 sur 9) ; jeton chiffré au
  repos (seuls ses 4 derniers caractères sont affichés) ; code de test
  validé ; normalisation `user_data` conforme à la doc Meta (accents et arabe
  conservés, `st` sans tiret) (D7).
- Profil de données « standard » (sans `famma_stage`, `famma_product` ni
  `content_name`) ou « minimal ».
- Santé de la mesure : sonde hebdomadaire sans événement, état
  vert / orange / rouge (Mesure, commandes, tableau de bord), e-mail au rouge
  au plus une fois par 24 h, écran de rapprochement sur 14 jours avec renvoi
  borné ; mu-plugin `famma-measurement-watchdog.php` indépendant de famma-core.
- Alerte jamais aveuglée par un rouge ancien (relecture du lot 5, R-CACHE-2) :
  e-mail pour toute raison rouge pas encore annoncée, y compris une nouvelle
  panne (jeton révoqué) sur un état déjà rouge, toujours au plus un par 24 h
  (`Capi_Health_Alert`, option `famma_capi_health_mail_codes`) ; Purchase
  manquant que plus aucun renvoi ne peut rattraper (mêmes bornes que
  « Renvoyer ») classé orange `purchase_lost` au lieu de rouge pendant
  14 jours ; « Acquitter l'alerte » par commande dans le rapprochement (méta
  `_famma_purchase_ack`, nonce, `manage_woocommerce`, note de commande).

### Retour arrière vers la 0.3.1 : le jeton CAPI d'abord

La 0.3.2 chiffre le jeton enregistré dans « Mesure » dès sa première lecture
(premier envoi CAPI ou première page d'administration). La 0.3.1 lit cette
option sans la déchiffrer : remise en place telle quelle, elle enverrait
« famma-enc:v1:… » comme jeton, Meta refuserait chaque OrderPlaced et chaque
Purchase, et rien ne l'alerterait (relecture du lot 5, SEC-1 / R-CACHE-1).

1. **Juste avant** de remettre les fichiers de la 0.3.1 : FAMMA → Mesure →
   « Retour à la version 0.3.1 » → « Remettre le jeton en clair » (droits
   `manage_options` et `manage_woocommerce`). Sans administration :
   `wp eval 'var_export( Famma\Core\Capi_Token_Vault::unseal() );'` doit
   afficher `true`. Le rechiffrement est alors suspendu 7 jours
   (option `famma_meta_access_token_hold`), y compris pour un jeton ressaisi.
2. Étape oubliée, 0.3.1 déjà en place : ressaisir le jeton dans « Mesure »
   (0.3.1), ou le définir en constante `FAMMA_META_ACCESS_TOKEN` sur le
   serveur, prioritaire dans les deux versions et jamais chiffrée.
3. Retour arrière annulé, ou 0.3.2 remise : « Rechiffrer maintenant », sinon
   le jeton est rechiffré automatiquement à la première lecture après 7 jours.

### Navigateur

- Visites de l'équipe (`manage_options`, `manage_woocommerce`) exclues du
  pixel, de TikTok et de GA4 famma-core ; réglage « Suivre mes visites (mode
  test) » (S1).
- eventID de PageView et ViewContent générés dans le navigateur : fin de
  l'identifiant figé par le cache LiteSpeed ; InitiateCheckout une seule fois
  par panier et par session ; OrderPlaced navigateur optionnel (coupé par
  défaut, à évaluer au test T6.6 avant toute activation) avec l'event_id du
  serveur (S3-a).
- Identifiant produit unique (`Meta_Ids`) pour le navigateur, l'express et la
  CAPI : toujours l'ID WooCommerce, jamais le SKU (décision du 20/10/2026 :
  un SKU ajouté plus tard changerait l'identifiant en silence) ; les tailles
  famma-core partagent l'ID du produit (S3-b).
- Pixel chargé immédiatement (asynchrone) par défaut ; réglage « différé »
  pour l'ancien comportement (D6).
- Commande express : champs d'attribution WooCommerce transmis (origine des
  commandes renseignée) ; capture UTM indépendante du pixel et méta de
  synthèse `_famma_order_source` (D7, R12).

### Tunnel et cache

- Redirections (ancien slug, URL héritées, canoniques) : chaîne de requête du
  visiteur conservée et réponse jamais mise en cache par LiteSpeed (D2).
- Checkout classique : verrou atomique par session et panier ; une double
  soumission ne crée plus deux commandes (D3). La clé inclut l'empreinte des
  coordonnées saisies (facturation, livraison, note, paiement ; ni le jeton
  ni les champs de mesure) : une re-commande corrigée (autre téléphone, autre
  adresse) ou la commande d'un autre client saisie depuis la même session crée
  bien une nouvelle commande, seule une soumission identique est renvoyée vers
  la commande existante (relecture du lot 5, C1).

## 0.3.1 — 2026-10-17

### Parcours visiteurs (FAMMA → Parcours visiteurs)

- Collecte propre au site, sans tiers : `assets/js/visitor-journal.js` envoie
  les événements par lots (`sendBeacon`) à `famma/v1/collect`, une requête
  POST jamais servie par le cache LiteSpeed. Pas de nonce (page en cache) :
  liste blanche d'événements, lots et corps plafonnés, robots et équipe
  écartés, limite de débit par empreinte d'IP, IP jamais stockée en clair.
- Événements : pages vues, sortie (temps actif, défilement), galerie, vidéo,
  variation, formulaires express et checkout (nom du champ seulement, jamais
  la valeur), clics WhatsApp / téléphone / sortants, temps de chargement
  réels (LCP, INP, CLS, TTFB) et erreurs JavaScript. Côté serveur : ajout au
  panier et commande passée.
- Table `{prefix}famma_visitor_events` (option `famma_visitor_schema_version`),
  purge quotidienne par paquets, conservation réglable (180 jours par défaut).
- Commande : méta `_famma_visitor_id` / `_famma_visitor_session` et encadré
  « Parcours avant commande » (HPOS).
- Vue en quatre onglets : Synthèse (entonnoir en sessions, champs du
  formulaire, sessions et chronologie), Sources et campagnes (UTM, CA
  commandé non encaissé), Produits, Performance (75e centile, erreurs).
- Export / effacement des parcours rattachés aux commandes via les outils de
  confidentialité WordPress (par e-mail de facturation ; les commandes
  express sans e-mail ne sont pas retrouvées).

### Commande express

- Un cookie de session WooCommerce présent mais invalide (sels changés,
  valeur corrompue, session expirée) est désormais remplacé par
  `famma_express_nonce` : le jeton ne repartait plus jamais en
  `famma_token: expired` en boucle.

## 0.3.0 — 2026-10-16

### Choix de taille dans le formulaire express

- Réglage par produit (produit simple) : Produits → modifier → Données
  produit → onglet **Général** → « Tailles proposées » (ex. `S, M`) et
  « Intitulé du choix de taille » (facultatif, « Taille / المقاس » par
  défaut), posés par `Product_Sizes_Admin` (`includes/product-sizes-admin.php`).
  Méta `_famma_size_options` / `_famma_size_label`. Vide : aucun
  choix, le produit est inchangé. Le produit reste simple : le formulaire
  express ne s'affiche pas sur un produit variable.
- Fiche produit : boutons radio obligatoires juste avant la quantité, sans
  valeur présélectionnée, pastilles de 48 px, clavier natif, propriétés CSS
  logiques (RTL). Rien ne dépend du visiteur : le HTML reste cachable.
- Contrôle serveur unique (`Product_Sizes`, `includes/product-sizes.php`)
  dans `woocommerce_add_cart_item_data` : taille absente ou hors liste =
  ajout refusé avec un message FR + derja, pour la commande express comme
  pour « Ajouter au panier », l'AJAX des listes et `?add-to-cart=`. Sur les
  listes, le bouton d'un produit à tailles devient « Choisir la taille » et
  mène à la fiche. « Commander à nouveau » reprend la taille.
- La taille est enregistrée sur la ligne de commande (méta `famma_size`,
  affichée « Taille : M » dans l'administration, les e-mails WooCommerce et
  la page « commande reçue ») et visible au panier / checkout classiques.
- Commande express : la taille entre dans la signature du devis, et le
  panier isolé ne retire que la ligne de la taille commandée.
- Script : compatible avec l'ancien HTML encore en cache (bloc taille absent
  = comportement 0.2.9) ; « Ajouter au panier » transmet la taille au
  formulaire WooCommerce et est bloqué sans taille.

### Messages d'erreur

- Message générique d'échec en français + derja (texte source bilingue,
  affiché même sans traduction chargée), y compris dans les réponses du
  serveur.
- Erreur de téléphone du formulaire : ajout de la derja.
- Nom complet : au moins 3 caractères, contrôlé dans le navigateur et par le
  serveur (`Tunisia::validate_full_name()`, checkout classique compris),
  message FR + derja.
- Traductions `fr_FR` et `ar` complétées, `.mo` régénérés.

## 0.2.9 — 2026-10-16

### Meta Conversions API : identifier le produit de chaque commande

Deux produits tournent en publicité (trancheuse, ceinture dorsale PRO) et
Meta ne pouvait pas séparer leurs commandes. `custom_data` de **OrderPlaced
et de Purchase** porte désormais :

- `content_ids` : tableau des identifiants produit, identiques à
  `contents[].id` (SKU, sinon ID du produit ou de la variation), sans doublon ;
- `content_name` : noms des lignes de commande joints par « | » (texte brut,
  sans balise ni entité, borné à 500 caractères) ;
- `num_items` : somme des quantités ;
- `famma_product` : slugs produit joints par « , » — le slug du produit
  parent pour une variation, donc un seul slug pour une commande
  mono-produit, quelles que soient la taille ou la quantité
  (ex. `ceinture-dorsale-pro`). Utilisable dans une conversion
  personnalisée Meta (« famma_product contient ceinture-dorsale-pro »).

Le tout est construit par une seule classe, `Capi_Product_Data`
(`includes/capi-product-data.php`), qui remplace l'ancienne méthode privée
`Capi::contents()` : `contents` est inchangé (même id, quantité, prix
unitaire). Inchangés : noms d'événements, `event_id`, moment d'envoi,
`user_data` (fbc/fbp de la 0.2.6), `value`, `currency`, `order_id`,
`famma_stage`.

## 0.2.8 — 2026-10-16

### Correctif

- **Rapports WooCommerce (admin)** : plus d'erreur fatale sur le rapport
  « Ventes par date ». WooCommerce passe `false` au filtre
  `woocommerce_reports_order_statuses` pour ses requêtes de remboursements
  (« aucun filtre de statut ») ; `Order_Statuses::reporting_statuses()`
  exigeait un tableau et plantait (`TypeError`, production du 13/10/2026).
  Une valeur qui n'est pas un tableau est désormais renvoyée telle quelle ;
  sinon la logique est inchangée (sans `processing` / `on-hold`, avec
  `delivered` / `completed`). Même garde sur `paid_statuses()`.
- Test de non-régression en lecture seule : `scripts/test-report-statuses.php`.

## 0.2.7 — 2026-10-16

### Commande express en un clic (fiche produit, mobile)

- **Un clic = une commande** : « Confirmer ma commande / أكد طلبيتك » enchaîne
  `famma_express_prepare` puis `famma_express_checkout` et redirige vers la page
  de remerciement WooCommerce (order-received). Plus de bloc « Vérifiez votre
  commande » inséré au-dessus du bouton.
- **Commande isolée** : la commande ne contient que le produit de la fiche, à la
  quantité choisie (− / +). Les autres articles du panier ne sont ni commandés,
  ni supprimés, ni modifiés ; codes promo mis de côté puis remis. Nouvelle classe
  `Express_Cart_Isolation`.
- **Modale de contrôle** uniquement si le total calculé par le serveur diffère du
  total affiché : fenêtre centrée, bouton final « Valider ma commande – XX DT /
  أكّد نهائيا » visible sans défiler.
- **Pendant l'envoi** : bouton désactivé, « Envoi en cours… / قاعد نبعث… »,
  indicateur de chargement. Double clic = une seule commande ; reprise par
  `famma_express_status` conservée ; aucun renvoi automatique après erreur
  (un refus avant réservation ramène au repos avec son message).
- **Erreurs visibles** : message WooCommerce juste au-dessus du bouton,
  défilement jusqu'à lui, champ fautif en rouge (`aria-invalid`). Jamais
  d'échec sans message.
- **Conditions générales** : case obligatoire supprimée, remplacée par « En
  commandant, vous acceptez nos conditions générales » (lien) sous le bouton.
  Côté serveur, `woocommerce_checkout_show_terms` est à false pour la seule
  requête express ; le checkout classique est inchangé.
- **Page de remerciement** d'une commande express : numéro de commande et « On
  vous appelle pour confirmer / باش نكلموك باش نأكدو ». Le panier restant n'y est
  jamais vidé.
- **Bouton collant** (thème enfant, `sticky-cta.js`) : défile jusqu'au formulaire
  et place le curseur dans le champ Nom.
- **Script et CSS du formulaire** marqués `data-no-optimize`, `data-no-defer`,
  `data-cfasync="false"` et exclus des optimisations LiteSpeed.
- **Compatibilité cache** : le nouveau script reste sûr face à l'ancien HTML
  servi par LiteSpeed avant purge (case CGV rendue facultative, contrôle forcé).

### Téléphone tunisien

- `Tunisia::normalize_phone()` : 20123456, « 20 123 456 », « +216 20123456 »,
  0021620123456, « 216 20 123 456 », « (+216) … », chiffres arabes et espaces
  insécables sont acceptés et **enregistrés sur 8 chiffres**. Les numéros
  216xxxxx (ex. 21612345) ne sont plus refusés à tort. Même règle côté
  navigateur, anti-abus et hash Meta.

### Meta

- **Journal `famma-capi`** (WooCommerce > État > Journaux) : une ligne par envoi
  OrderPlaced / Purchase avec event_id, valeur, devise, présence fbc/fbp/ph,
  code HTTP, `events_received`, `fbtrace_id` et message d'erreur Meta. Jamais le
  token ni d'identifiant en clair.
- **`_fbc` mis à jour** quand un nouveau `fbclid` arrive (règle Meta) ; inchangé
  pour le même `fbclid`.
- InitiateCheckout (navigateur) et AddToCart émis une seule fois par intention.
- OrderPlaced : value = total réel de la commande isolée (77 TND pour une
  trancheuse). Purchase : inchangé, au statut « Livrée », value = total réel.

## 0.2.6 — 2026-10-13

### Meta Conversions API : meilleure correspondance (fbc / fbp)

- **Cookie `_fbc` créé dès l'arrivée** : quand l'URL contient `?fbclid=` et
  qu'aucun `_fbc` n'existe, un petit script pose `fb.1.<horodatage ms>.<fbclid>`
  pour 90 jours sur le domaine principal. Il ne dépend plus du chargement
  différé du Pixel Meta et fonctionne sur les pages servies par le cache
  LiteSpeed (aucun PHP n'y est exécuté).
- **Cookie `_fbp` créé s'il manque** (même script) : `fb.1.<horodatage ms>.<10 chiffres
  aléatoires>`, 90 jours, domaine principal, jamais d'écrasement d'un `_fbp` existant.
- **Script exécuté immédiatement, sans interaction** : handle séparé `famma-fbc`,
  placé dans `<head>` (et non plus en pied de page avec le Pixel), balise marquée
  `data-no-optimize="1"`, `data-no-defer="1"` et `data-cfasync="false"`, et ajoutée
  aux exclusions LiteSpeed Cache (`litespeed_optimize_js_excludes`,
  `litespeed_optm_js_defer_exc`) : ni combiné, ni différé, ni retardé.
- **IP client** : `REMOTE_ADDR` conservé. Le site est servi directement par
  LiteSpeed chez OXA Host, sans CDN ni proxy (ni Cloudflare ni QUIC.cloud,
  vérifié le 13/10/2026) : aucun en-tête de CDN n'est lu.
- **Validation à la commande** : `_fbc` et `_fbp` ne sont enregistrés que s'ils
  respectent le format Meta (`fb.<chiffre>.<horodatage>.<valeur>[.<appendice>]`) ;
  l'IP est validée, le user agent assaini et borné à 512 caractères. Mêmes
  métadonnées qu'avant (`_famma_fbc`, `_famma_fbp`, `_famma_client_ip`,
  `_famma_client_ua`).
- **OrderPlaced et Purchase** lisent ces métadonnées (revalidées à l'envoi).
  Purchase, envoyé depuis l'admin, n'utilise jamais l'IP ni le navigateur de
  l'admin. Les commandes antérieures sans métadonnées partent sans ces champs.
- **Mode test** : constante `FAMMA_META_CAPI_TEST_MODE` (true) ou option
  `famma_meta_capi_test_mode` (1), éteint par défaut. Activé, les événements
  réels portent le `test_event_code` configuré (format `TEST…`) et un avis
  s'affiche dans l'admin.
- Inchangés : noms d'événements, moment d'envoi, `event_id`, reste de
  `user_data`, envoi non bloquant.
