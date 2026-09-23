# Audit UX/UI et CRO — fammaminnou.tn (FAMMA / فمّا)

23 septembre 2026 · audit en lecture seule du site en production · mobile d'abord (375 × 812), puis desktop (1366 × 900)

---

## 0. Méthode, périmètre et limites

| Point | Détail |
|---|---|
| Outil | Navigateur intégré (onglet dédié), en FR (`/`) et en AR (`/ar/`). Positions, hauteurs, couleurs, colonnes et recouvrements mesurés dans le DOM (`getBoundingClientRect`, `getComputedStyle`, `elementFromPoint`). Les captures du navigateur intégré étaient parfois rognées ou blanches (volet masqué, onglet partagé avec un autre agent) : **les chiffres du rapport viennent des mesures DOM, pas des captures**. |
| Temps de réponse | `curl` depuis le poste du propriétaire (TTFB). C'est une mesure indicative : elle dépend de l'endroit d'où part la requête. |
| Pages parcourues | Accueil, Boutique, catégories (une pleine, une vide), 16 fiches produit (1 en détail, 15 relevées par script), panier, checkout, À propos, Contact, Livraison, Retours, FAQ, CGV, Confidentialité, `/mentions-legales/`, puis accueil, fiche et checkout en arabe. |
| Panier | 2 produits ajoutés pour voir le panier et le checkout, puis **panier vidé** (état final : « Votre panier est actuellement vide »). |
| Interdits respectés | Aucune commande envoyée, aucun compte créé, aucun formulaire envoyé, aucune donnée personnelle saisie. Par souci de transparence : un « 12 » est parti dans le champ Prénom du formulaire express quand un clic est tombé à côté de sa cible. Rien n'a été envoyé et la page a été rechargée. La validation du téléphone a été testée avec la valeur factice « 12 », effacée juste après. |
| Déploiement | La production **n'a pas été redéployée depuis le 25/08/2026**. La **section vidéo publicitaire** de la fiche produit et le **sélecteur de quantité à 48 px** (commits des 27 et 28/08) sont dans le dépôt mais **pas en ligne**. Ils ne sont donc ni évalués ni reprochés ici. En ligne, le champ quantité mesure 48 px de haut, sans boutons + / −, et aucune fiche n'a de vidéo. |
| Non testé | L'écran de confirmation de commande (il faudrait commander) et le rendu réel sur un iPhone ou un Android d'entrée de gamme. |

---

## 1. Note globale : **5,2 / 10**

La base est solide : charte cohérente, promesses COD bien écrites, 16 vrais produits avec 5 à 10 photos au format 1:1, descriptions riches en derja, **formulaire de commande express sur la fiche**, RTL propre, aucun faux avis et aucune fausse urgence. Mais la conversion est freinée par des **défauts de confiance bloquants** : WhatsApp ne fonctionne pas, les pages Retours et CGV affichent des notes de chantier, un produit test porte une fausse remise, et la promesse « satisfait ou remboursé » ne repose sur aucune politique écrite. S'y ajoutent un **serveur lent** et un **tunnel panier → checkout resté au rendu WooCommerce par défaut**.

| Sous-note | /10 | Justification |
|---|---|---|
| Clarté de l'offre | **7** | Le triptyque « paiement à la livraison · livraison offerte · sans compte » se lit en 5 s, en FR et en derja. En revanche, le H1 est la marque et le slogan, le héros ne montre ni prix ni produit nommé, et la bande « Satisfait ou remboursé » promet ce qui n'est écrit nulle part. |
| Navigation | **5** | 4 catégories vides sont mises en avant (en tête de la bande de l'accueil et du menu), « Promotions » mène au seul produit test, la recherche est invisible sur mobile, les accents manquent dans le menu, et les liens `http://` du pied de page déclenchent la page anti-bot de l'hébergeur. |
| Fiche produit | **6** | Galerie riche, description et fiche technique complètes, formulaire express, accordéons, onglet Livraison honnête. Mais le prix n'arrive qu'à 1,2 écran, le texte en derja est rendu de gauche à droite, le prix de la barre collante est masqué, la mise en page desktop est cassée et aucun délai n'est annoncé. |
| Checkout | **4,5** | Le formulaire express est bien construit (type `tel`, `autocomplete`, 48 px, 16 px). En revanche, le panier et le checkout sont restés au rendu WooCommerce par défaut : choix de compte, code promo, « autre adresse », notes, bouton « Commander » à 3,6 écrans, texte en anglais, aucune validation du téléphone en direct, et une case CGV obligatoire qui renvoie à un squelette. |
| Confiance | **3,5** | Liens WhatsApp cassés, Retours et CGV en notes internes, produit test à −46 %, garantie non étayée, aucun lien vers les réseaux sociaux, pas de mentions légales, aucun délai. Points positifs : téléphone, e-mail et ville visibles. |
| Mobile | **5** | Cibles à 48 px presque partout, barre d'achat collante, champs en 16 px. Mais la grille n'a qu'une colonne (15 écrans pour la boutique), le TTFB va de 2 à 10 s, le sélecteur de langue flottant recouvre des contenus, et la fiche garde 44 px de marge de chaque côté. |
| RTL / bilingue | **5,5** | Miroir RTL réussi grâce aux propriétés logiques. Mais l'arabe n'est traduit qu'en partie (bloc éditorial, FAQ, pied de page, checkout), la flèche du héros n'est pas inversée, l'arabe est représenté par le drapeau saoudien, et le derja est cassé par la direction LTR sur la version FR. |
| Accessibilité | **5,5** | Présents : lien d'évitement, `alt` sur toutes les images, vrais `label`, `autocomplete`, 48 px. Mais les CTA blanc sur orange sont à 2,61:1, le prix de la fiche à 2,46:1, le badge « Nouveau » à 3,3:1 en 11,5 px, le carrousel n'a pas de pause, et on trouve du texte en 9 px et des cibles de 24 px dans le panier. |
| Esthétique | **7** | Identité bleu nuit + orange maîtrisée, héros soigné, photos cohérentes. Mais le panier et le checkout gardent le bandeau gris Kadence, les pastilles du carrousel sont confuses, et les colonnes desktop de la fiche sont écrasées à 280 px. |

Calcul : moyenne pondérée, avec la confiance et le checkout comptés double parce qu'ils pèsent le plus en COD : (7 + 5 + 6 + 2×4,5 + 2×3,5 + 5 + 5,5 + 5,5 + 7) / 11 = **5,2**.

---

## 2. Analyse page par page

### 2.1 Accueil (mobile)

Page de 8 220 px, soit **10,1 écrans**.

| Élément | Position | En écrans |
|---|---|---|
| H1 « FAMMA — Il y a toujours quelque chose de nouveau. » | haut | 0,3 |
| CTA « Découvrir la boutique » (blanc sur orange, 2,61:1) | ~600 px | 0,7 |
| Carrousel (4 photos produit sans légende) | ~700 px | 0,9 |
| « Parcourez nos catégories » | 1 078 px | 1,3 |
| **Première carte produit** | **1 418 px** | **1,75** |
| **Premier bouton d'achat** | **1 938 px** | **2,4** |
| « Les premiers produits arrivent / استنّاو الجديد » (section vide) | 4 463 px | 5,5 |
| FAQ (3 questions), « Une question ? », « Pourquoi choisir FAMMA ? », pied de page | 5 059 px et au-delà | 6,2 et au-delà |

**Ce qui marche**
- La proposition de valeur COD est claire dès le premier écran, et bilingue.
- Les photos sont au format 1:1 en webp 600 px, homogènes.
- Le bloc « Vous payez à la livraison » en 3 étapes et « Pourquoi choisir FAMMA ? » sont justes et vérifiables.
- Aucune preuve sociale inventée : la section avis est masquée tant qu'il n'y a pas d'avis.

**Ce qui freine**
- **Bande catégories** : elle s'ouvre sur « Maison & Cuisine » et « Électronique », deux catégories **vides**. Deux autres le sont aussi (Auto & Moto, Beauté & Santé). Le premier clic d'exploration mène donc à « Aucun produit ne correspond ».
- **Section « Les premiers produits arrivent »** : un reste de l'état « catalogue vide », qui contredit les 5 produits affichés juste au-dessus.
- **Carrousel** : photos sans nom, sans prix et sans bouton. Les pastilles sont des carrés de 48 px posés sur un anneau décoratif orange, et leur nom accessible est « Visuel 1…4 ». Pas de bouton pause, et aucune des 4 images de 600 px n'a de `fetchpriority` ni de `loading`.
- **Cartes produit** en une colonne, 585 px de haut chacune. La ligne « bénéfice » est en derja mais rendue de gauche à droite : `Visseuse 21 برو 21V…`.
- Le badge « Nouveau » est sur tous les produits, et on trouve du texte en 9 px (slogan sous le logo) et en 12 px (sous-titres des promesses).
- Le sélecteur de langue flottant « Français » (en bas à gauche) recouvre les contenus en permanence, alors que le bandeau propose déjà FR | عربي.

### 2.2 Boutique et catégories

- **16 produits** sur une colonne, cartes de 585 px : **12 166 px, soit 15 écrans** sur mobile. Le premier bouton d'achat arrive à 1 181 px (1,45 écran). Sur desktop, 4 colonnes et barre latérale : correct.
- En-tête bien construit : fil d'Ariane, H1, tri, bouton Filtres de 48 px, bande de 4 promesses, compteur de résultats. Aucun texte d'introduction.
- **Produit test « Prd 1 »** : image générée par ChatGPT, prix barré 140 → 75 TND (−46 %), description vide, catégorie « Sans rayon ». C'est le **seul** produit en promotion, donc le seul résultat du menu « Promotions ».
- **Doublon** : deux fiches « Pince de Câblage Pro » (`/pince-de-cablage-pro/`, rangée dans Accessoires avec une ancienne image `06.jpg`, et `/pince-cablage-pro/`, rangée dans Outils).
- **Filtres** : accordéon inline qui repousse la grille, pas de panneau coulissant. On y lit « Categories » et « Disponibilite » sans accent, 4 catégories à « (0) », « Rupture de stock (0) », « En promotion (1) » (qui est Prd 1), et aucune puce de filtre actif. Le filtre par note est bien masqué.
- **Tri** : « popularité » et « notes moyennes » sont proposés alors qu'il n'existe aucun avis.
- **Catégorie pleine** (« Cuisine ») : le H1 et le fil d'Ariane changent, c'est un bon repère. **Catégorie vide** : l'état vide est soigné, mais son message (« moins de filtres, fourchette de prix plus large ») ne va pas à un visiteur qui n'a filtré sur rien.
- La bande « Satisfait ou remboursé » est affichée ici **et** dans le bandeau desktop, alors que la page Retours ne contient aucune politique.

### 2.3 Fiche produit — Visseuse Perceuse Sans Fil 21V (mobile, en détail)

| Élément | Position | En écrans |
|---|---|---|
| Galerie (287 × 410 px, 6 miniatures sur 2 rangées, flèches) | 248 px | 0,3 |
| H1 | 752 px | 0,93 |
| **Prix** (30 px, orange `#FF7A00`, **2,46:1**) | **973 px** | **1,2** |
| Réassurance (3 lignes FR + derja) | ~1 050 px | 1,3 |
| **Formulaire express** « Commander maintenant / أطلب توا » (1 191 px de haut) | 1 288 px | 1,6 |
| Quantité, « Ajouter au panier » (blanc sur orange), favori de 56 px | 2 520–2 640 px | 3,1–3,2 |
| Accordéons Description · Avis (0) · Livraison | après 3 000 px | — |
| Produits similaires (2) | 6 041 px | 7,4 |
| **Barre collante** (68 px, « Commander / أطلب توا », bleu sur orange, conforme) | fixée en bas | d'emblée |

**Excellent**
- **5 à 10 photos par produit**, miniatures et flèches (constat P4 corrigé).
- Descriptions de 1 000 à 2 000 caractères, avec une fiche technique (`المواصفات التقنية`) et le contenu du coffret.
- **Formulaire express** : 7 champs à 48 px et 16 px, téléphone en `type="tel"` + `inputmode="tel"` + `autocomplete="tel"`, 24 gouvernorats, total « à payer à la livraison », bouton de 56 px bleu sur orange. C'est l'option C du plan UX, qui correspond à la pratique dominante du COD au Maghreb.
- L'onglet Livraison **ne montre plus la note interne** : il ne dit que du vrai (24 gouvernorats, 0 TND, espèces).
- **Aucune urgence factice** (ni minuteur ni « plus que N ») et **aucun faux avis**.

**Ce qui coûte**
1. Le **prix n'arrive qu'à 1,2 écran**, et la galerie avec ses 2 rangées de miniatures occupe le premier écran. Le contenu est large de 287 px sur 375 : **44 px de marge de chaque côté**, soit 23 % de la largeur perdus.
2. Le **sélecteur de langue flottant de TranslatePress** (`z-index: 99999`) **recouvre le prix dans la barre collante**, confirmé par `elementFromPoint`. En FR il est à gauche ; en AR, à droite, sur le prix là aussi.
3. **La barre collante fonctionne**, elle défile jusqu'au formulaire et donne le focus au champ Prénom. Mais ce champ s'arrête à y = 40–88 px, **sous l'en-tête collant (0–77 px)** : le titre du formulaire et le libellé du champ sont cachés au moment où le clavier s'ouvre.
4. **Deux tunnels concurrents** sur la même page : le formulaire express (« Je confirme ma commande ») et, plus bas, « Ajouter au panier » avec sa quantité et son favori, sans hiérarchie visuelle entre les deux. En FR, le bouton du panier dit « Ajouter au panier » alors que tout le reste dit « Commander ».
5. Le **téléphone n'est pas validé en direct** : « 12 » est accepté par le navigateur (pas de `pattern` ni de `maxlength`), et l'erreur n'arrive qu'après l'envoi au serveur. L'indication « 2X XXX XXX » laisse croire que seuls les 2X sont acceptés, alors que le propre numéro de la boutique commence par 5.
6. La description courte et la description longue sont en derja mais rendues en **`direction: ltr`** sur la version FR : la ponctuation et les mots latins se retrouvent déplacés.
7. L'accordéon « **Avis (0)** » affiche l'absence d'acheteurs. Son formulaire est ouvert à tous et demande nom, e-mail et site : c'est une porte d'entrée pour le spam.
8. **Aucun délai de livraison** n'est annoncé, ni sur la fiche, ni dans la FAQ, ni sur la page Livraison. C'est pourtant la première question d'un client COD.
9. **Desktop** : la galerie (280 px) et le résumé (280 px) sont **deux colonnes étroites sur un écran de 1 366 px**. Les photos sont minuscules, le H1 fait 124 px de haut et le formulaire express 1 131 px.

**Les 15 autres fiches** (relevé par script) ont toutes de 5 à 10 images, une description, le formulaire express, « Avis (0) » et aucune vidéo (vidéo non déployée). Aucune n'affiche de note de chantier. Seule exception : **Prd 1**, avec 1 image, 11 caractères de description et la catégorie « Sans rayon ».

### 2.4 Panier

- Rendu **WooCommerce et Kadence par défaut** : bandeau gris de 200 px « Panier », puis un second titre « Panier », un tableau empilé, un « × » rouge de **24 px**, une quantité de **40 px**, un champ code promo de **38 px** et un bouton « Appliquer le code promo » dont le libellé passe sur 2 lignes.
- « **Livraison à Tunis. Modifier l'adresse** » : le calculateur de livraison ne sert à rien quand la livraison est gratuite partout, et il sème le doute.
- Le CTA « Valider la commande » (bleu nuit, 50 px) n'arrive qu'à **1 155 px (1,4 écran)**.
- **Panier vide** : texte WooCommerce brut et un lien « Retour à la boutique », sans suggestion ni réassurance.
- Retour après ajout : sur la fiche, la page se recharge avec le bandeau WooCommerce « … a été ajouté à votre panier · Voir le panier ». Depuis la grille, une coche Kadence apparaît dans le bouton, avec un petit lien « Voir le panier » de 35 px. Aucun raccourci « Commander maintenant ».

### 2.5 Checkout (`/commande/`)

| Élément | Position | En écrans |
|---|---|---|
| Bandeau titre « Commande » (200 px gris) | 150 px | 0,2 |
| « Avez-vous un code promo ? » | ~400 px | 0,5 |
| Choix « Commander sans compte / Créer un compte » (2 cartes de 105 et 126 px) | 654 px | 0,8 |
| **Premier champ** (Prénom) | **995 px** | **1,2** |
| Récapitulatif de commande | 1 895 px | 2,3 |
| Réassurance | 2 620 px | 3,2 |
| **Bouton « Commander »** (bleu sur orange, 48 px) | **2 894 px** | **3,6** |

- Champs corrects : 48 px, 16 px, `autocomplete`, 24 gouvernorats, e-mail facultatif, « Livraison gratuite » et total clairs.
- **Friction** : choix de compte, code promo en tête de page, « Expédier à une adresse différente ? », « Notes de commande ».
- **Textes non traduits** :
  - le texte de confidentialité est **en anglais** en FR comme en AR (« Your personal data will be used to process your order… ») ;
  - « E-mail (facultatif) / البريد الإلكتروني (facultatif) » et « (اختياري) (اختياري) » sont doublés.
- **Case CGV obligatoire** : son lien déplie dans la page le squelette « Informations légales à fournir / Clauses à faire rédiger ».

### 2.6 Pages de confiance

Les liens sont tous présents dans le pied de page (colonnes Navigation, Informations, Aide & support, Paiement à la livraison).

| Page | État | Constat |
|---|---|---|
| À propos | ✅ | Bien écrite, vraie, engagements vérifiables. |
| Contact | ⚠️ | WhatsApp, téléphone 56261412, e-mail et Sousse. **Liens WhatsApp cassés** (voir UX-01). Pas d'horaires. |
| Livraison | ⚠️ | Vraie, mais **sans délai**, sans transporteur, sans suivi ni procédure en cas d'absence. |
| **Retours** | ❌ | Affiche publiquement : « **Points à trancher** : délai de rétractation accordé, état dans lequel le produit doit être renvoyé, qui paie le retour… ». |
| **CGV** | ❌ | Squelette public : « Informations légales à fournir… Clauses à faire rédiger… ». |
| FAQ | ⚠️ | 4 questions (paiement, coût, zone, compte). Manquent : délai, échange, ouverture du colis avant de payer, que faire en cas de refus. |
| Confidentialité | ⚠️ | Écrite pour un développeur (« telles qu'elles sont implémentées dans le code », « ne le sont que si les identifiants sont configurés ») alors que **le pixel Meta et GA4 sont bien chargés**. Aucune information sur les cookies. |
| Mentions légales | ❌ | **404**. Ni raison sociale ni matricule fiscal nulle part. |
| Réseaux sociaux | ❌ | **Aucun lien** Facebook, Instagram ou TikTok, alors que le trafic vient de ces publicités. |

### 2.7 Bascule FR ↔ arabe

- **Bon** : `lang="ar"` et `dir="rtl"`, en-tête, héros, icônes et formulaires miroirs. CTA des cartes « أطلب », barre collante « أطلب توا », accordéons « الوصف · التقييمات (0) · التوصيل », « منتجات تشبهلو ». Prix en chiffres occidentaux.
- **Traduction partielle** sur `/ar/` :
  - bloc « Vous payez à la livraison… », réponses de la FAQ, « Écrire sur WhatsApp », « Les premiers produits arrivent » ;
  - **pied de page entier** (Accueil, Boutique, Categories, Promotions…) ;
  - au checkout : H1 « Commande », « Livraison gratuite », « Paiement à la livraison — … », texte de confidentialité en anglais.
- Au checkout, **l'arabe standard** (« هل تودّ الشحن لعنوان مختلف؟ », « هل لديك قسيمة؟ ») côtoie le derja (« أطلب بلا كونت »).
- **Flèche du CTA du héros non inversée** en RTL (`transform: none`).
- Le **drapeau saoudien** (`ar.svg`) représente l'arabe dans le sélecteur flottant d'une marque tunisienne.
- Liens FR (23 × 44 px) et عربي (36 × 44 px) : sous les 48 px de large.
- La promesse « Livraison offerte » devient « التوصيل للدار » (« livraison à domicile ») dans la réassurance : l'argument de la gratuité se perd en arabe.

### 2.8 Performance (ressentie)

| Page | TTFB à froid | TTFB à chaud |
|---|---|---|
| Accueil | 7,6 s | 2,0–2,1 s |
| Fiche produit | 10,6 s | 2,1–3,6 s |
| Boutique | 2,3 s | 4,6 s |

- Relevées en navigateur, les 15 fiches ont mis de **2,8 à 7,6 s** chacune.
- Le budget du plan UX (LCP ≤ 2,5 s) est **impossible à tenir** avec un TTFB de 2 s. L'accueil charge 31 scripts et 13 feuilles de style.
- L'en-tête `Server: LiteSpeed` laisse penser qu'un cache de page LiteSpeed est envisageable, à confirmer avec OXA Host.
- **Page anti-bot** : l'accès en `http://` fait passer par une page « **Votre demande est en cours de vérification, veuillez patienter…** » avant d'arriver en https. Des requêtes répétées ont aussi reçu « One moment, please… ». Il faut vérifier avec l'hébergeur que le trafic publicitaire, notamment les navigateurs intégrés de Facebook, Instagram et TikTok, n'est jamais soumis à cette page.

---

## 3. Tableau des constats

Sévérité : **Critique** = casse la confiance ou la vente · **Haute** = coûte des commandes · **Moyenne** = friction mesurable · **Basse** = finition.
Effort : **S** < ½ journée · **M** 1 à 3 jours · **L** plus. « Propriétaire » = la donnée doit venir du propriétaire (règle §58/§60 : rien d'inventé).

| ID | Sév. | Page | Observation précise | Impact conversion | Recommandation concrète | Effort |
|---|---|---|---|---|---|---|
| UX-01 | **Critique** | Toutes (flottant, accueil, contact, fiche, boutique, pied de page) | Tous les liens WhatsApp pointent vers `https://wa.me/56261412`, **sans l'indicatif 216**. wa.me lit alors +56 (Chili) : le message n'arrive pas à la boutique. `tel:56261412` sans +216 aussi. Autre point : ce numéro diffère de celui consigné dans la mémoire du projet (26767491). **À confirmer par le propriétaire.** | Le canal préféré des acheteurs COD tunisiens est coupé, alors que 6 CTA y renvoient. Perte directe des commandes et questions par WhatsApp. | Stocker le numéro au format international dans le réglage admin (`21656261412`), générer `wa.me/216…` et `tel:+216…`, puis tester depuis un téléphone. | S |
| UX-02 | **Critique** | Retours | La page publique affiche la note interne « Points à trancher : délai de rétractation… colis refusé ». | Annonce au client que la boutique n'a pas de politique de retour, juste avant l'achat. | Dépublier la page (et retirer son lien) jusqu'au texte fourni par le propriétaire, ou n'y publier que ce qui est décidé. Garde-fou : aucun contenu « à compléter » visible hors `manage_woocommerce`. | S + propriétaire |
| UX-03 | **Critique** | CGV, checkout, formulaire express | CGV = squelette « Informations légales à fournir / Clauses à faire rédiger ». La case « J'ai lu et j'accepte les conditions générales » est **obligatoire** et déplie ce squelette dans le checkout. | Le client doit accepter des conditions qui n'existent pas : doute et risque juridique. | Faire rédiger les CGV réelles (propriétaire ou juriste) en priorité absolue. Tant qu'elles n'existent pas, ne pas publier de squelette. Décision propriétaire sur la case. | M (propriétaire) |
| UX-04 | **Critique** | Bandeau desktop, bande boutique et catégories | « **Satisfait ou remboursé** » est promis sans aucune politique de retour ou de remboursement. | Promesse non étayée, potentiellement trompeuse, source de litiges et de refus à la livraison. | Retirer la promesse tant que la politique n'est pas écrite. La remettre avec un lien vers la page Retours une fois celle-ci rédigée. | S |
| UX-05 | **Critique** | Boutique, Promotions | Produit test « **Prd 1** » publié : image ChatGPT, 140 → 75 TND (−46 %), description vide, « Sans rayon ». C'est le seul résultat du menu « Promotions ». | Fausse remise et donnée de test en production (règle 12), qui décrédibilise tout le catalogue. | Dépublier ou supprimer. Masquer « Promotions » tant qu'aucune vraie promo n'existe. | S |
| UX-06 | Haute | Boutique, fiches | Doublon « Pince de Câblage Pro » : 2 fiches, 2 catégories, une ancienne image `06.jpg`. | Confusion (« lequel acheter ? ») et impression d'un catalogue négligé. | Garder la meilleure fiche, dépublier l'autre avec une redirection 301. | S |
| UX-07 | Haute | Pied de page et menu FR/AR | Boutique, Catégories, Promotions et Nouveautés pointent en `http://`. L'accès http passe par la page anti-bot « Votre demande est en cours de vérification… » avant https. | Plusieurs secondes d'écran d'attente anxiogène et possible abandon ; risque identique pour le trafic publicitaire. | Remplacer ces URL par des URL relatives ou https dans les menus, forcer une redirection 301 http → https au niveau serveur, et faire exempter les navigateurs intégrés Meta et TikTok de la page anti-bot. | S |
| UX-08 | Haute | Tout le site | TTFB de 2 à 4,6 s à chaud, 7,6 à 10,6 s à froid ; fiches de 2,8 à 7,6 s ; 31 scripts sur l'accueil. | Sur du trafic publicitaire mobile en 4G, chaque seconde fait fuir des visiteurs avant même que la page s'affiche. | Cache de page (LiteSpeed Cache si l'hébergeur le permet), panier et commande exclus ; cache objet ; `fetchpriority="high"` sur la première image du héros et `loading="lazy"` sur les suivantes ; audit des scripts. | M |
| UX-09 | Haute | Accueil, menu mobile, filtres | 4 catégories **vides** (Maison & Cuisine, Électronique, Auto & Moto, Beauté & Santé) mises en avant en tête. Au clic : « Aucun produit ne correspond… moins de filtres ». | Premier geste d'exploration mène à une impasse, avec un message qui accuse le visiteur. | Masquer partout les catégories à 0 produit (`hide_empty`) et prévoir un message propre aux catégories vides. | S |
| UX-10 | Haute | Accueil | Section « Les premiers produits arrivent / استنّاو الجديد » vide, sous 5 produits en vente. | Contradiction : « la boutique n'est pas ouverte ? » | Retirer la section, héritée de l'état « catalogue vide ». | S |
| UX-11 | Haute | Accueil, boutique, catégories (mobile) | Grille en 1 colonne de 335 px, cartes de 585 px : boutique de 15 écrans pour 16 produits, accueil de 10 écrans, premier bouton d'achat de l'accueil à 2,4 écrans. | Fatigue de défilement et comparaison impossible entre produits : moins de fiches vues. | Passer à **2 colonnes** sous 480 px (arbitrage fidélité / densité déjà posé dans l'audit d'écart), avec une carte compacte : image, nom sur 2 lignes, prix, CTA. Masquer la ligne bénéfice sur mobile. | S–M |
| UX-12 | Haute | Fiche (mobile) | Prix à 973 px (1,2 écran), galerie de 410 px + 2 rangées de miniatures, **44 px de marge de chaque côté**. | Le visiteur venu d'une publicité ne voit ni le nom ni le prix sans défiler. | Marges à 16 px, miniatures sur **une** rangée défilante, et H1 + prix + badge dans le premier écran (objectif : prix sous 0,9 écran). | S–M |
| UX-13 | Haute | Tout le site, surtout la fiche | Sélecteur flottant de TranslatePress (`z-index: 99999`) **qui masque le prix de la barre collante** (FR et AR). Il recouvre aussi les contenus, fait doublon avec FR \| عربي du bandeau, et montre le drapeau saoudien. | Le prix disparaît de la barre qui fait la vente, et le pouce trouve un obstacle permanent. | Désactiver le sélecteur flottant de TranslatePress (garder celui du bandeau), retirer les drapeaux et agrandir les liens du bandeau à 48 px de large. | S |
| UX-14 | Haute | Fiche (mobile) | Après un appui sur la barre collante, le champ Prénom s'arrête à y = 40–88 px, **sous l'en-tête collant (0–77 px)** : titre du formulaire et libellé cachés. | Le client tape sans voir le formulaire, au moment clé. | `scroll-margin-top: calc(hauteur de l'en-tête + 16px)` sur `.famma-express` et ses champs, ou défilement vers le titre du formulaire. | S |
| UX-15 | Haute | Fiche (desktop) | Galerie de 280 px et résumé de 280 px sur un écran de 1 366 px ; formulaire express de 1 131 px de haut. | Vitrine desktop dégradée (trafic Facebook desktop et partages). | Corriger la grille de la fiche (galerie ~55 %, résumé ~45 % du conteneur de 1 240 px). | S–M |
| UX-16 | Haute | Panier | Rendu par défaut : bandeau gris de 200 px, double titre « Panier », « × » de 24 px, quantité de 40 px, code promo de 38 px, bouton promo sur 2 lignes, « Livraison à Tunis / Modifier l'adresse », CTA à 1,4 écran. | Étape de trop, peu rassurante, qui invite à chercher un code promo. | Passer directement de l'ajout au panier au checkout (ou tiroir de panier), masquer le calculateur de livraison, replier le code promo, cibles à 48 px, CTA « Commander » visible sans défiler. | M |
| UX-17 | Haute | Checkout | Premier champ à 1,2 écran, bouton « Commander » à **3,6 écrans** ; choix de compte (231 px), code promo, « autre adresse », notes ; aucun récapitulatif en haut. | Friction maximale au dernier mètre : abandons de checkout. | Retirer le choix de compte (l'offre après commande existe déjà), replier coupon, « autre adresse » et notes, supprimer le bandeau gris, mettre un récapitulatif avec le total à payer en haut. | M |
| UX-18 | Moyenne | Checkout FR/AR | Texte de confidentialité **en anglais** ; en AR, « Commande », « Livraison gratuite » et la description du paiement restent en FR ; arabe standard et derja mélangés ; « (facultatif) » et « (اختياري) » doublés. | Rupture de confiance au moment de valider, et impression de travail inachevé. | Traduire ces chaînes (réglages WooCommerce et TranslatePress), harmoniser en derja, retirer les doublons. | S |
| UX-19 | Moyenne | Formulaire express, checkout | Téléphone sans validation en direct (« 12 » accepté, erreur seulement après envoi), sans `maxlength` ni `pattern` ; l'indication « 2X XXX XXX » exclut les 4X, 5X et 9X. | Numéros faux, donc commandes injoignables, donc refus : c'est la cause n°1 de pertes en COD. | Validation au `blur` (8 chiffres commençant par 2, 4, 5 ou 9, espaces et +216 tolérés), message d'erreur lié par `aria-describedby`, indication « XX XXX XXX », rappel du numéro avant validation. | S |
| UX-20 | Moyenne | Formulaire express | 7 champs obligatoires + case CGV (Prénom **et** Nom, Ville **et** Adresse). | Chaque champ coûte des commandes ; la norme COD au Maghreb tient en 4 champs. | Fusionner Prénom et Nom en « Nom complet », rendre Ville/Délégation facultative ou la fondre dans l'adresse (le gouvernorat suffit à la répartition). À valider avec le process de confirmation téléphonique. | M |
| UX-21 | Moyenne | Fiche, panier, checkout | Libellés qui changent d'une étape à l'autre : « Commander », « Ajouter au panier » (FR), « Je confirme ma commande », « Valider la commande », « Commander ». Deux tunnels sur la même fiche. | Hésitation (« quel bouton ? »), clics perdus. | Un seul geste principal (formulaire express) ; « Ajouter au panier » en lien secondaire discret ; libellé unique « Commander / أطلب توا ». | S |
| UX-22 | Moyenne | Accueil, cartes, fiche | Blanc sur orange **2,61:1** (Découvrir la boutique, Commander des cartes, Ajouter au panier) ; prix de la fiche en orange **2,46:1** ; badge « Nouveau » blanc sur vert 3,3:1 en 11,5 px ; « استنّاو الجديد » à 2,61:1. Contraire au DESIGN-SYSTEM (§ CTA principal : texte bleu). | Lisibilité au soleil sur un téléphone d'entrée de gamme ; le bouton qui encaisse est le moins lisible. | `--famma-on-orange: var(--famma-blue)` (6,32:1, déjà sur la barre collante et le checkout) ; prix en bleu nuit ou `-700` ; badge en vert `-700`. | S |
| UX-23 | Moyenne | Cartes, fiche (version FR) | Ligne bénéfice, description courte et description longue en derja, rendues en `direction: ltr` : mots latins et chiffres réordonnés, ponctuation déplacée. | Texte de vente difficile à lire, impression d'erreur. | `dir="auto"` (ou `lang="ar" dir="rtl"`) sur les blocs de contenu produit. | S |
| UX-24 | Moyenne | Carrousel de l'accueil | 4 photos sans nom, prix ni CTA ; pastilles « Visuel 1…4 » en carrés de 48 px sur un anneau décoratif ; pas de pause (WCAG 2.2.2) ; 4 images de 600 px sans `fetchpriority` ni `lazy`. | Décor sans pouvoir de vente ; poids de page et LCP. | Un seul produit vedette légendé (nom, prix, « Commander ») ou des vignettes légendées ; bouton pause ; première image en `fetchpriority="high"`. | S–M |
| UX-25 | Moyenne | Accueil | H1 = « FAMMA — Il y a toujours quelque chose de nouveau. » (le `<title>`, lui, est bien orienté requête). | Le signal principal de la page ne dit pas ce qu'on vend (lecteurs d'écran, SEO). | H1 porteur de l'offre (« Boutique en ligne en Tunisie — payez à la livraison »), mot-symbole en élément décoratif. | S |
| UX-26 | Moyenne | Boutique | Filtres : « Categories » et « Disponibilite » sans accent, compteurs « (0) », « Rupture de stock (0) », pas de puces actives ; tri par popularité et par note sans avis ; pas de texte d'introduction. | Bruit et doute ; perte de repère après filtrage. | Masquer les options à 0, corriger les accents, puces de filtres actifs visibles tiroir fermé, tri réduit (nouveautés, prix), 2 ou 3 phrases d'introduction. | S |
| UX-27 | Moyenne | Menu, pied de page | « Categories » et « Nouveautes » sans accent (FR et AR) ; menu mobile de 17 entrées dont 4 catégories vides. | Crédibilité. | Corriger les libellés et alléger le menu (catégories non vides uniquement). | S |
| UX-28 | Moyenne | En-tête mobile | Recherche absente de l'en-tête ; seulement dans le tiroir du menu, sous 17 entrées (y ≈ 969 px, hors écran). | Découverte difficile dès que le catalogue grossit. | Icône loupe dans l'en-tête mobile, ouvrant le champ existant. | S |
| UX-29 | Moyenne | Livraison, fiche, FAQ | Aucun délai de livraison nulle part ; pas de transporteur ni de suivi ; FAQ réduite à 4 questions. | « Quand est-ce que je reçois ? » est la première objection COD : sans réponse, moins de commandes et plus de refus. | Délai réel par zone (fourni par le propriétaire) affiché près du prix et dans l'onglet Livraison ; FAQ enrichie de vraies réponses (délai, échange, ouverture du colis, refus). | S + propriétaire |
| UX-30 | Moyenne | Pied de page, contact | Aucun lien Facebook, Instagram ou TikTok. | Un prospect venu d'une publicité ne peut pas vérifier la page de la marque (commentaires, ancienneté) : c'est le réflexe tunisien avant un achat COD. | Renseigner les URL sociales dans le réglage prévu (`FAMMA_SOCIAL_*`, fournies par le propriétaire). | S |
| UX-31 | Moyenne | Fiches | Accordéon « Avis (0) » visible ; formulaire d'avis ouvert à tous (nom, e-mail, site). | Affiche « personne n'a acheté » et ouvre la porte au spam ou aux faux avis. | Masquer l'accordéon sous 1 avis vérifié ; réglage WooCommerce « avis réservés aux acheteurs vérifiés ». | S |
| UX-32 | Basse | Toutes | `/mentions-legales/` en 404 ; ni raison sociale ni matricule fiscal nulle part. | Signal de sérieux manquant. | Page fournie par le propriétaire, liée dans la barre légale. | M (propriétaire) |
| UX-33 | Basse | Fiche | Favori à 56 px contre 48 px pour « Ajouter au panier » (P6) ; favoris sans compte d'une utilité douteuse. | L'action secondaire domine visuellement. | Favori à 48 px ou icône seule, voire le retirer de la fiche. | S |
| UX-34 | Basse | Grille, fiche | Retour après ajout : coche Kadence + lien « Voir le panier » de 35 px (grille) ; rechargement + bandeau (fiche). Pas de « Commander maintenant ». | Double-clic et doute après l'ajout. | Bandeau `role="status"` avec « Commander maintenant » (vers le checkout) et « Continuer ». | S |
| UX-35 | Basse | Panier vide | Texte WooCommerce brut. | Occasion de relance perdue. | État vide avec 2 à 4 produits, les promesses COD et le lien WhatsApp. | S |
| UX-36 | Basse | AR | Flèche du héros non inversée ; « التوصيل للدار » au lieu de « livraison gratuite » ; liens de langue sous 48 px de large. | Finition RTL, argument de gratuité perdu en arabe. | Classe `icon--dir` avec `scaleX(-1)` en RTL ; « التوصيل بلاش » ; cibles de 48 px. | S |
| UX-37 | Basse | AR | Traduction partielle : bloc éditorial, FAQ, pied de page entier, checkout. | Le visiteur arabophone retombe sur du français à chaque section. | Passe de traduction TranslatePress sur les blocs et menus, en derja cohérent. | M |
| UX-38 | Basse | Confidentialité | Texte écrit pour un développeur ; pixel Meta et GA4 chargés sans information cookies. | Ton peu rassurant ; conformité à préciser. | Réécrire en langage client, lister les outils actifs, décider d'un bandeau d'information (propriétaire). | S |
| UX-39 | Basse | En-tête, héros | Slogan sous le logo en 9 px, sous-titres des promesses en 12 px, badge en 11,5 px. | Illisible sur un petit écran. | Minimum 12 px pour du texte décoratif, 14 px pour du texte informatif. | S |
| UX-40 | Info | Fiche | Vidéo publicitaire et sélecteur de quantité à 48 px **prêts dans le dépôt mais non déployés** (production figée au 25/08). | Levier vidéo (fort en COD) pas encore actif. | Déployer après recette mobile (vérifier que la vidéo ne repousse pas encore le prix). | S |

---

## 4. Statut des constats des audits précédents

### 4.1 `AUDIT-UX-2026-08-18.md`

| Constat | Statut | Détail |
|---|---|---|
| T1 — CTA blanc sur orange 2,61:1 | **Ouvert** | Toujours blanc sur les cartes, le bouton de la fiche et le héros. Conformes : barre collante, formulaire express, checkout (bleu sur orange). |
| T2 — 3 libellés pour une action | **Partiel** | La fiche FR dit toujours « Ajouter au panier » (l'AR dit « أطلب ») ; d'autres libellés sont apparus (UX-21). |
| T3 — Aucun signal après « Commander » | **Partiel** | Coche Kadence + lien « Voir le panier » de 35 px + zone `aria-live` pour lecteurs d'écran ; pas de bandeau visible ni de « Commander maintenant ». |
| T4 — SEO (meta, canonical, H1) | **Largement corrigé** | Meta description et `<title>` orientés requête ; le H1 de l'accueil reste la marque. |
| T5 — Fautes et « Sans rayon » | **Partiel** | « Uncategorized » et « Sans rayon » ne sont plus mis en avant (sauf Prd 1) ; « Categories », « Nouveautes » et « Disponibilite » toujours sans accent. |
| T6 — Visuels hors système | **Corrigé** | 1:1, webp, 5 à 10 par produit ; exception : Prd 1 (image ChatGPT). |
| A1 — H1 de l'accueil | **Ouvert** | UX-25. |
| A2 — 1,81 écran avant le 1er produit | **Ouvert** | 1,75 écran ; premier bouton d'achat à 2,4 écrans. |
| A3 — Carrousel sans légende ni pause | **Ouvert** | UX-24. |
| A4 — Bloc éditorial contradictoire | **Partiel** | La phrase « le catalogue n'est pas ouvert » a disparu, mais « Les premiers produits arrivent » reste (UX-10). |
| A5 — Section WhatsApp sans bouton | **Corrigé en apparence** | Le bouton existe, mais le lien est cassé (UX-01). |
| A6 — Pas de `fetchpriority` sur le héros | **Ouvert** | Aucune image n'en porte. |
| A7 / B1 — 1 colonne à 375 px | **Ouvert** | Arbitrage fidélité / densité non tranché (UX-11). |
| A8 — Avis signé `famma_admin` | **Corrigé** | Section masquée, aucun avis. |
| B2 — CTA de carte à 40 px | **Corrigé** | 48 px (le corps reste à 13,5 px). |
| B3 — Puces de filtres actifs | **Ouvert** | Le H1 et le fil d'Ariane de catégorie aident en partie. |
| B4 — Filtres sans seuil | **Partiel** | Filtre par note masqué ; compteurs à 0 toujours affichés. |
| B5 — Ligne bénéfice vide | **Corrigé** | Remplie partout sauf Prd 1, mais cassée par la direction LTR (UX-23). |
| B6 — Aucun texte sur la boutique | **Ouvert** | — |
| P1 — Note interne dans l'onglet Livraison | **Corrigé sur la fiche** | **Même défaut réapparu** sur les pages Retours et CGV (UX-02, UX-03). |
| P2 — Avis admin | **Corrigé** | — |
| P3 — Description vide | **Corrigé** | 1 000 à 2 000 caractères, fiche technique incluse. |
| P4 — 1 image, zoom inutile | **Corrigé** | Miniatures et flèches (mise en page à revoir : UX-12). |
| P5 — Barre collante | **Maintenue, dégradée** | Prix masqué par le sélecteur flottant (UX-13) ; défilement sous l'en-tête (UX-14). |
| P6 — Favori plus grand que le CTA | **Ouvert** | 56 px contre 48 px. |
| P7 — Délai, stock réel, commande WhatsApp | **Ouvert / partiel** | Délai absent ; carte « Une question sur ce produit ? » présente mais WhatsApp cassé. |
| P8 — Produits similaires trop longs | **Corrigé** | 2 produits. |

### 4.2 `AUDIT-ECART-MAQUETTE-2026-08-18.md`

| Écart | Statut |
|---|---|
| Barre supérieure à 3 promesses | **Corrigé sur desktop** (4 promesses) ; 1 seule sur mobile. |
| Colonne « Aide & support » du pied de page | **Corrigé**. |
| Bouton WhatsApp flottant | **Présent**, lien cassé (UX-01). |
| Icônes sociales | **Ouvert** (UX-30). |
| Lien « Mentions légales » | **Ouvert** (404). |
| P-2 — Onglets Spécifications et FAQ | **Partiel** : spécifications intégrées à la description, pas de FAQ produit ; accordéons en place. |
| P-3 — Miniatures et flèches | **Corrigé**. |
| P-1 — Pastille « Vous économisez » | **Non vérifiable** (seule promo : Prd 1). |
| B-1 — Filtre Marques | **Ouvert, par choix**. |
| B-2 — Pagination | **Sans objet** (16 produits sur une page). |
| Accents du menu | **Ouvert**. |
| Décision n°1 : contraste | **Non tranchée**, toujours 2,61:1. |
| Décision n°2 : 1 ou 2 colonnes | **Non tranchée**. |

### 4.3 `UX-PLAN.md` (écarts E-xx et D-xx)

| Écart | Statut |
|---|---|
| E-01 / D-1 — Contraste | **Partiel** (voir T1). |
| E-07 / D-8 — Confirmation de commande | **Non testé** (impose de commander). |
| E-10 — Navigation mobile basse | **Ouvert**. |
| E-11 — Recherche | **Partiel** : présente dans l'en-tête desktop, enfouie sur mobile (UX-28). |
| E-17 — Pied de page complet | **Corrigé**. |
| E-18 / D-6 — Sélecteur de langue flottant | **Ouvert et aggravé** (UX-13). |
| E-25 — Encarts `.famma-todo` | **Réapparu** sous forme de contenu de page (Retours, CGV). |
| E-34 — Titres de page en anglais | **Corrigé** en FR ; le H1 AR du checkout reste « Commande ». |
| E-35 — Devise « د.ت » sous locale FR | **Ouvert**. |
| E-36 — Produit test publié | **Ouvert** sous le nom « Prd 1 » (UX-05). |
| E-37 — Slugs anglais | **Corrigé** (`/boutique/`, `/panier/`, `/commande/`). |
| Parcours d'achat A / B / C (§7) | **Option C mise en place** (formulaire express sur la fiche). |

---

## 5. Top 10 des actions CRO (impact ÷ effort)

| # | Action | Constats | Impact | Effort |
|---|---|---|---|---|
| 1 | **Réparer WhatsApp et le téléphone** : `wa.me/216XXXXXXXX` et `tel:+216…`, depuis le réglage admin ; faire confirmer le bon numéro par le propriétaire | UX-01 | Très fort | S |
| 2 | **Nettoyer les signaux de fausse confiance** : supprimer Prd 1 et le doublon Pince, retirer « Satisfait ou remboursé » et masquer « Promotions » tant qu'ils ne sont pas vrais | UX-04, 05, 06 | Très fort | S |
| 3 | **Retirer les pages squelettes** (Retours, CGV) jusqu'aux textes réels du propriétaire, et trancher sur la case CGV obligatoire | UX-02, 03 | Très fort | S + propriétaire |
| 4 | **Barre collante réparée** : désactiver le sélecteur flottant de TranslatePress, ajouter `scroll-margin-top` au formulaire express | UX-13, 14 | Fort | S |
| 5 | **Liens https dans les menus, redirection 301, exemption anti-bot** pour les navigateurs intégrés Meta et TikTok ; masquer les catégories vides ; retirer « Les premiers produits arrivent » | UX-07, 09, 10 | Fort | S |
| 6 | **Cache de page et allègement** : TTFB sous 0,8 s, `fetchpriority` sur le héros | UX-08, 24 | Fort | M |
| 7 | **Fiche mobile au premier écran** : marges à 16 px, miniatures sur une rangée, H1 et prix visibles, un seul CTA principal ; corriger la grille desktop | UX-12, 15, 21 | Fort | S–M |
| 8 | **Formulaire express plus court et plus sûr** : Nom complet, Téléphone validé en direct, Gouvernorat, Adresse ; rappel du numéro avant validation | UX-19, 20 | Fort (moins de refus) | S–M |
| 9 | **Grille mobile à 2 colonnes** avec carte compacte, et `dir="auto"` sur les textes en derja | UX-11, 23 | Moyen à fort | S |
| 10 | **Contraste et cohérence du tunnel** : CTA bleu sur orange, prix lisible, libellé unique « Commander / أطلب توا » ; passer directement de l'ajout au checkout ; checkout allégé et traduit ; délai de livraison affiché dès que le propriétaire le fournit | UX-16, 17, 18, 22, 29 | Moyen à fort | S–M |

**À mesurer ensuite** (le pixel Meta et GA4 sont chargés) : vue de fiche → envoi du formulaire express, ajout au panier → checkout, envoi → commande confirmée par téléphone → livrée. Le taux de refus à la livraison reste le juge final des corrections de confiance (actions 1 à 3 et 8).

---

*Audit en lecture seule, 23/09/2026. Aucun fichier du dépôt modifié, aucune commande ni aucun formulaire envoyé, panier vidé en fin de session.*
