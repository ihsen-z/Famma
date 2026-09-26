# Audit UX/UI et CRO de fammaminnou.tn (FAMMA / فمّا), 2ᵉ passage

25 septembre 2026, audit en lecture seule du site en production. On regarde d'abord le mobile (390 × 844), puis le desktop (1 366 × 768). Point de départ : `docs/audit-2026-09-23/audit-ux.md` (note 5,2/10, confiance 3,5/10).

---

## 0. Méthode, périmètre et limites

| Point | Détail |
|---|---|
| Outil | Chrome 129 en mode headless, piloté par `puppeteer-core`, avec un **profil vierge** (visiteur anonyme, sans session wp-admin). Le mobile est émulé en 390 × 844 (tactile, UA Android). Positions, contrastes (formule WCAG), cibles, recouvrements (`elementFromPoint`) et directions ont été mesurés dans le DOM. Les **chiffres viennent du DOM**, les captures servent de preuve visuelle. |
| Comparabilité | Le 23/09, la mesure se faisait en 375 × 812. Les « écrans » ci-dessous sont calculés sur **844 px**. Pour comparer, la valeur ramenée à 812 px est donnée entre parenthèses. Les positions en pixels changent peu entre 375 et 390 px de large. |
| Pages parcourues | Accueil, `/boutique/`, rayon Cuisine, fiches Visseuse 21V (en détail) et Boîte à salade, panier, commande, Livraison, CGV, Retours, Contact, À propos, FAQ, Confidentialité, Cookies, `/mentions-legales/`, puis `/ar/` (accueil, fiche, commande) et la fiche en desktop. Au total, environ 25 chargements espacés de 8 à 10 s. **Aucune page anti-bot n'a été rencontrée.** |
| Traceurs | Pixel Meta, GA4 et TikTok ont été bloqués dans le navigateur de test pour ne pas fausser les statistiques du propriétaire. Cela n'a aucun effet sur la mise en page. |
| Actions faites | Un produit (Boîte à salade) a été ajouté au panier pour voir le panier et la commande, puis le **panier a été vidé**. Dans le formulaire express, le champ téléphone a reçu la valeur factice « 12 », a perdu le focus, puis a été effacé. Rien n'a été envoyé. |
| Interdits respectés | Aucune commande, aucun formulaire envoyé (contact, newsletter, Retours, avis), aucun compte créé, aucune modification du site. Aucun code n'a été lu : la seule lecture de fichiers concerne les documents (2 requêtes graphify). |
| Non testé | La page de confirmation de commande (il faudrait commander), la validation en direct du téléphone sur le **checkout classique** (seul le formulaire express a été testé) et le rendu sur un vrai téléphone (clavier virtuel, 4G). |

---

## 1. Note globale : **6,7 / 10** (5,2 le 23/09)

Les défauts de confiance qui bloquaient la vente le 23/09 sont corrigés : WhatsApp fonctionne, les notes de chantier ont disparu, il n'y a plus de produit test ni de fausse remise, « Satisfait ou remboursé » est retiré et le délai (24 h à 48 h) est annoncé près du prix. La fiche produit a été refaite : le prix apparaît dans le premier écran, les marges font 16 px, le desktop est réparé, la barre collante n'est plus masquée et le formulaire express est réduit à 4 champs avec validation en direct. Le panier et la commande ont désormais une vraie mise en page. Il reste trois gros freins : le **checkout garde ses frictions WooCommerce** (compte, code promo, autre adresse, notes, bouton à 3,3 écrans), la **grille mobile est toujours en une colonne**, et le **contraste du bouton qui encaisse a régressé** (blanc sur orange à 2,61:1). Aucun texte légal définitif n'est publié.

| Sous-note | 23/09 | 25/09 | Justification |
|---|---|---|---|
| Clarté de l'offre | 7 | **7,5** | Le triptyque COD, les 4 promesses (dont le délai) et aucune promesse non tenue. Le H1 reste la marque, et le carrousel montre des noms de produits sans prix ni bouton. |
| Navigation | 5 | **7** | 5 rayons tous remplis, accents corrigés, menu mobile passé de 17 à 11 entrées, plus aucun lien `http://`, « Promotions » masqué. En revanche : pas de loupe dans l'en-tête mobile, aucun texte d'introduction, « Tri par popularité » sans ventes et « Rupture de stock (0) ». |
| Fiche produit | 6 | **8** | Prix à 0,88 écran, 16 px de marge, vignettes sur une seule rangée, délai sous le prix, onglets honnêtes (Avis masqué à 0), carte WhatsApp, colonnes desktop de 636 et 530 px. En revanche, « Confirmer ma commande » est passé en blanc sur orange (2,61:1) et aucune vidéo n'est en ligne. |
| Checkout | 4,5 | **6** | Formulaire express à 4 champs obligatoires avec validation au `blur`, panier et commande remis au dessin de la marque (étapes, cartes, récapitulatif, textes en français). Mais le checkout commence encore par un code promo et un choix de compte, garde « autre adresse » et « Notes », pré-remplit le gouvernorat à « Tunis », et son bouton est à 3,3 écrans. |
| Confiance | 3,5 | **6** | WhatsApp réparé (`wa.me/21626767491`), plus aucune note interne publique, plus de Prd 1 ni de doublon, plus de fausse garantie, délai affiché partout, Retours honnête (procédure par WhatsApp), page Cookies. Manquent toujours : une vraie politique de retour, des CGV définitives, les mentions légales (404) et les réseaux sociaux. Deux numéros différents sont affichés. |
| Mobile | 5 | **6,5** | TTFB de 0,15 à 0,22 s sur les pages en cache (2 à 10 s avant), sélecteur flottant supprimé, cibles à 48 px dans le panier. En revanche, une seule colonne (13,1 écrans pour 14 produits), panier et commande à 4 à 6 s, et le WhatsApp flottant recouvre le contenu du tunnel. |
| RTL / bilingue | 5,5 | **7** | Menus, pied de page, titres, fil d'Ariane et onglet traduits. Le derja s'affiche désormais de droite à gauche en FR (19 paragraphes sur 19), le drapeau saoudien a disparu et « التوصيل فابور » est rétabli. Restent : flèche du héros non inversée, confidentialité en français sur `/ar/commande/`, arabe standard au checkout, liens de langue de 23 et 36 px de large. |
| Accessibilité | 5,5 | **6** | Prix à 16,5:1, `aria-invalid` et `aria-describedby` sur le téléphone, onglets natifs au clavier. Mais les CTA restent à 2,61:1 (avec une régression sur le formulaire express), le badge est à 3,3:1 en 11,5 px, le slogan en 9 px, le carrousel sans pause avec des boutons « Visuel 1…4 », la case CGV à 24 px et son lien à 16 px de haut. |
| Esthétique | 7 | **8** | Panier, commande, Livraison, CGV et Retours suivent les maquettes, fiche desktop équilibrée, onglets propres. Seul reste un blanc d'environ 100 px sous l'en-tête desktop de la fiche. |

Calcul : même pondération que le 23/09, la confiance et le checkout comptant double. (7,5 + 7 + 8 + 2×6 + 2×6 + 6,5 + 7 + 6 + 8) / 11 = 74 / 11 = **6,7**. **Sous-note confiance : 6 / 10** (3,5 le 23/09).

---

## 2. Mesures avant / après

| Indicateur (mobile sauf mention) | 23/09 (375 × 812) | 25/09 (390 × 844) | Évolution |
|---|---|---|---|
| Prix de la fiche (Visseuse) | 973 px = **1,2 écran** | 743 px = **0,88 écran** (0,92) | ✅ objectif « < 0,9 écran » tenu à 844 px |
| H1 de la fiche | 752 px | 664 px | ✅ |
| Marge latérale de la fiche | 44 px de chaque côté | 16 px | ✅ |
| Bouton de validation du formulaire express | 2 406 px ≈ 3,0 écrans | 2 047 px = **2,4 écrans** | ✅ |
| Hauteur du formulaire express | 1 191 px, 7 saisies obligatoires | 959 px, **4 obligatoires** + ville facultative + quantité + case CGV | ✅ |
| Hauteur totale de la fiche | 8 906 px | 6 817 px (8,1 écrans) | ✅ −23 % |
| Barre collante après appui | champ caché sous l'en-tête (40–88 px contre 0–77 px) | titre à 112 px, champ à 194–242 px, en-tête à 77 px | ✅ |
| Prix de la barre collante | masqué par le sélecteur TranslatePress | visible (`elementFromPoint` = le prix) | ✅ |
| Colonnes desktop galerie / résumé | 280 / 280 px | **636 / 530 px** | ✅ |
| Colonnes de la grille mobile | 1 | **1** (carte de 350 × 597 px) | ❌ inchangé |
| Hauteur de la boutique | 12 166 px = 15 écrans (16 produits) | 11 058 px = 13,1 écrans (13,6) pour 14 produits | ≈ densité identique (≈ 790 px par produit) |
| Premier bouton d'achat de l'accueil | 1 938 px = 2,4 écrans | 1 950 px = 2,3 écrans (2,4) | ❌ inchangé |
| Premier champ du checkout | 995 px = 1,2 écran | 945 px = 1,1 écran (1,16) | ≈ |
| Bouton « Commander » / « Confirmer ma commande » du checkout | 2 894 px = **3,6 écrans** | 2 791 px = **3,3 écrans** (3,4) | ⚠️ à peine mieux |
| CTA du panier | 1 155 px = 1,4 écran | 1 056 px = 1,25 écran (1,3) | ⚠️ toujours hors du premier écran |
| Champs du checkout classique | Prénom + Nom, ville obligatoire, choix de compte, coupon, autre adresse, notes | 4 obligatoires (Nom complet, Téléphone, Adresse, Gouvernorat) + e-mail et ville facultatifs, **mais** choix de compte, coupon, autre adresse et notes toujours là (11 contrôles) | ⚠️ |
| Contraste des CTA blanc sur orange (héros, cartes) | 2,61:1 | 2,61:1 | ❌ inchangé |
| Contraste de « Confirmer ma commande » (formulaire express) | bleu nuit sur orange, 6,32:1 | **blanc sur orange, 2,61:1** | 🔻 **régression** |
| Contraste du prix de la fiche | 2,46:1 (orange) | 16,52:1 (bleu nuit) | ✅ |
| Contraste du badge « Nouveau » | 3,3:1 en 11,5 px | 3,3:1 en 11,5 px | ❌ |
| Liens `http://` (menus, pied de page) | 4 | 0, et `http://` renvoie en **301 direct** vers https | ✅ |
| Lien WhatsApp | `wa.me/56261412` (Chili) | `wa.me/21626767491` | ✅ |
| Rayons vides mis en avant | 4 | 0 | ✅ |
| « Satisfait ou remboursé » | bandeau desktop + boutique | absent partout | ✅ |
| Notes de chantier publiques | Retours, CGV | 0 (textes d'attente factuels) | ✅ |
| Délai de livraison affiché | nulle part | fiche, boutique, panier, commande, Livraison (« 24 h à 48 h ») | ✅ |
| Liens vers les réseaux sociaux | 0 | 0 | ❌ |
| Entrées du menu mobile | 17 | 11 | ✅ |
| TTFB anonyme (curl) | 2,0 à 10,6 s | accueil 0,15 s · fiche 0,15 s · boutique 0,22 s (cache LiteSpeed) | ✅ ; panier 6,4 s et commande 4,4 s au chargement complet |

---

## 3. Évolution depuis le 23/09

Statuts : **Corrigé** · **Partiel** · **Inchangé** · **Régression** · **Non revérifiable**.

| ID | Constat du 23/09 | Statut | Preuve (25/09) |
|---|---|---|---|
| UX-01 | WhatsApp `wa.me` sans 216 | **Corrigé** | Tous les liens pointent vers `https://wa.me/21626767491`, le numéro choisi le 23/09. `tel:+21656261412`. Deux numéros différents restent affichés (UX-42). |
| UX-02 | Retours = notes « Points à trancher » | **Partiel** | Plus aucune note interne. La page est refaite : 3 étapes, formulaire vers WhatsApp, 2 questions fréquentes. Mais elle annonce « Notre politique de retour et de remboursement est en cours de rédaction ». |
| UX-03 | CGV squelette + case obligatoire | **Partiel** | 5 articles factuels (Prix, Commande, Livraison, Paiement, Contact), « Dernière mise à jour : 25/09/2026 », mention « en cours de rédaction ». Aucune information légale. La case CGV reste obligatoire (express et checkout) et renvoie vers ce texte provisoire. |
| UX-04 | « Satisfait ou remboursé » sans politique | **Corrigé** | Absent des 20 pages parcourues. La 4ᵉ promesse est devenue « Livraison en 24 h à 48 h ». |
| UX-05 | Produit test Prd 1 (−46 %) | **Corrigé** | 14 produits, aucun prix barré, « Promotions » absent du menu et des filtres. |
| UX-06 | Doublon Pince de Câblage Pro | **Corrigé** | Une seule fiche (`/produit/pince-cablage-pro/`). |
| UX-07 | Liens `http://` et page anti-bot | **Corrigé** | 0 lien `http://`. `curl -I http://…/boutique/` renvoie 301 vers https sans page de vérification. |
| UX-08 | TTFB de 2 à 10 s | **Partiel** | 0,15 à 0,22 s sur les pages en cache. Le panier (6,4 s) et la commande (4,4 s au chargement complet en headless) ne sont pas en cache. |
| UX-09 | 4 rayons vides en avant | **Corrigé** | Bande de l'accueil, menu et filtres : 5 rayons, tous remplis (2 à 4 produits). |
| UX-10 | « Les premiers produits arrivent » | **Corrigé** | Section absente en FR comme en AR. |
| UX-11 | Grille mobile à 1 colonne | **Inchangé** | 1 colonne, cartes de 350 × 597 px, boutique de 13,1 écrans pour 14 produits. |
| UX-12 | Prix à 1,2 écran, marges de 44 px | **Corrigé** | Prix à 743 px (0,88 écran), marges de 16 px, galerie de 358 px, 6 vignettes sur 1 rangée. |
| UX-13 | Sélecteur flottant TranslatePress sur le prix | **Corrigé** | Aucun `#trp-floater-ls`. Le prix de la barre n'est pas recouvert, et le drapeau a disparu. |
| UX-14 | Formulaire caché sous l'en-tête | **Corrigé** | Après appui sur la barre : titre à 112 px, focus sur « Nom complet » à 194–242 px, en-tête qui s'arrête à 77 px ([capture](captures/mobile-fiche-visseuse-formulaire.png)). |
| UX-15 | Fiche desktop en 2 colonnes de 280 px | **Corrigé** | Galerie de 636 px, résumé de 530 px, prix à 302 px du haut ([capture](captures/desktop-fiche-visseuse.png)). |
| UX-16 | Panier WooCommerce par défaut | **Partiel** | Étapes 1-2-3, cartes, « Supprimer » à 80 × 48 px, − / + à 48 px, plus de calculateur de livraison, réassurance et encart WhatsApp. Mais le bloc « Code promo » reste ouvert, avec un bouton plein, **avant** le récapitulatif, et « Passer la commande » est à 1 056 px ([capture](captures/mobile-panier.png)). |
| UX-17 | Checkout : friction, bouton à 3,6 écrans | **Partiel** | Bandeau Kadence retiré, cartes 1-2-3, récapitulatif avec vignette. Mais le premier écran ne contient qu'un code promo et le choix « Commander sans compte / Créer un compte » ([capture](captures/mobile-commande.png)). « Expédier à une adresse différente ? » et « Notes de commande » sont toujours là, et le bouton est à 3,3 écrans. |
| UX-18 | Anglais au checkout, doublons, AR mêlé | **Partiel** | Texte de confidentialité en français, un seul « (facultatif) ». Sur `/ar/commande/`, la confidentialité reste en **français**, la case CGV est en arabe standard (« لقد قرأتُ الشروط والأحكام… ») et le paiement est libellé « الدفع عند التوصيل » au lieu de « الدفع كي يوصلك ». |
| UX-19 | Téléphone non validé en direct | **Corrigé** (formulaire express) | « 12 » puis sortie du champ : `aria-invalid="true"` et message « Vérifiez le numéro : 8 chiffres (XX XXX XXX). » relié par `aria-describedby`. Indication « XX XXX XXX ». Sur le checkout classique, la validation n'a pas été testée en direct. |
| UX-20 | 7 champs obligatoires | **Corrigé** | Nom complet, Téléphone, Gouvernorat, Adresse, plus Ville facultative. |
| UX-21 | Libellés changeants, deux tunnels | **Partiel** | « Ajouter au panier » devenu un bouton secondaire (icône seule sur mobile). Restent 4 libellés : « Commander » (barre, cartes), « Confirmer ma commande » (express, checkout), « Passer la commande » (panier). |
| UX-22 | Blanc sur orange à 2,61:1, prix à 2,46:1 | **Partiel + régression** | Prix corrigé (16,52:1), barre collante, panier, checkout et newsletter à 6,32:1. Héros et cartes toujours à 2,61:1, et le bouton du formulaire express est **passé** à 2,61:1 (UX-41). |
| UX-23 | Derja en `direction: ltr` | **Corrigé** | Ligne bénéfice des cartes en `dir="rtl"`. Fiche : 19 paragraphes en arabe sur 19 en RTL. |
| UX-24 | Carrousel sans légende ni pause | **Partiel** | Diapos nommées et reliées aux fiches, première image en `fetchpriority="high"` et suivantes en `lazy`. Toujours ni prix ni bouton, pas de pause, boutons « Visuel 1…4 ». |
| UX-25 | H1 = marque + slogan | **Inchangé** | « FAMMA — Il y a toujours quelque chose de nouveau. » |
| UX-26 | Filtres et tri de la boutique | **Partiel** | Accents corrigés, rayons à 0 masqués, « En promotion » et tri par note retirés. Restent : « Rupture de stock (0) », « Tri par popularité », compteurs globaux dans un rayon (« En stock (14) » dans Cuisine qui compte 4 produits), aucun texte d'introduction. |
| UX-27 | Accents du menu, 17 entrées | **Corrigé** | « Catégories », « Nouveautés », 11 entrées. |
| UX-28 | Recherche absente sur mobile | **Partiel** | Champ « Rechercher un produit… » visible dans le premier écran du tiroir (menu plus court), mais toujours aucune loupe dans l'en-tête. |
| UX-29 | Aucun délai de livraison | **Partiel** | « Livraison en 24 h à 48 h » sous le prix, dans la bande de la boutique, au panier, au checkout (« Mode de livraison ») et sur la page Livraison, dont la FAQ compte 5 questions. La page `/faq/` est restée à 4 questions, sans délai, échange, ouverture du colis ni refus. |
| UX-30 | Aucun lien Instagram / TikTok / Facebook | **Inchangé** | 0 lien social sur les 20 pages. |
| UX-31 | « Avis (0) » et formulaire d'avis ouvert | **Corrigé** | Onglet Avis masqué sans avis, aucun `#review_form`. |
| UX-32 | Mentions légales en 404 | **Inchangé** | `/mentions-legales/` renvoie 404. Ni raison sociale ni matricule fiscal. |
| UX-33 | Favori plus grand que le CTA | **Corrigé** | CTA de 65 px de haut contre un favori de 56 px (la taille du favori ne change pas, mais il ne domine plus). |
| UX-34 | Pas de « Commander maintenant » après ajout | **Partiel** | Depuis la fiche : rechargement, bandeau « … a été ajouté à votre panier » avec « Voir le panier » à 48 px. Il pousse la galerie d'environ 310 px et ne propose pas d'aller directement à la commande. |
| UX-35 | Panier vide brut | **Partiel** | Carte « Votre panier est vide · Parcourez la boutique… · Aller à la boutique ». Aucun produit suggéré, pas de WhatsApp, et « Votre panier est vide » s'affiche deux fois après une suppression. |
| UX-36 | Finitions RTL | **Partiel** | « التوصيل فابور » rétabli, drapeau retiré. La flèche de « شوف الحانوت » pointe toujours vers la droite ([capture](captures/mobile-ar-accueil.png)), et les liens FR / عربي font 23 et 36 px de large. |
| UX-37 | Arabe traduit en partie | **Partiel** | Menus, pied de page, H1, fil d'Ariane, onglet, formulaire et onglets de la fiche en derja. Restent en français : les noms des produits, des bouts de fiche technique (« Couple réglable et LED »), la confidentialité au checkout et le corps de plusieurs pages légales. |
| UX-38 | Confidentialité écrite pour un développeur | **Inchangé** | « telles qu'elles sont implémentées dans le code » toujours présent. La nouvelle page Cookies liste bien Meta, TikTok et Google Analytics, mais il n'y a aucun bandeau. |
| UX-39 | Textes en 9 à 12 px | **Inchangé** | Slogan en 9 px, badge « Nouveau » en 11,5 px, « Catégorie : » en 11,9 px. |
| UX-40 | Vidéo et quantité non déployées | **Partiel** | Sélecteur − / + à 48 px en ligne, une seule quantité. Aucune vidéo sur les 2 fiches testées. |

**Bilan sur 40 constats : 17 corrigés, 17 partiels, 6 inchangés.** UX-22, compté parmi les partiels, contient aussi une régression : le bouton du formulaire express est repassé à 2,61:1 (détaillé en UX-41). Les 5 critiques du 23/09 (UX-01 à UX-05) sont soit corrigées, soit ramenées à un manque de contenu du propriétaire, sans rien de trompeur à l'écran.

---

## 4. Analyse page par page (25/09)

### 4.1 Accueil (mobile, [capture](captures/mobile-accueil.png))
- 8 331 px (9,9 écrans). H1 à 207 px, CTA « Découvrir la boutique » à 581 px (blanc sur orange, 2,61:1), carrousel à 674 px, rayons à 1 079 px, première carte à 1 418 px, premier « Commander » à 1 950 px (2,3 écrans).
- Positif : 5 rayons tous remplis, « Vous payez à la livraison », FAQ courte, « Pourquoi choisir FAMMA ? », newsletter (bouton bleu nuit sur orange à 6,32:1, pot de miel bien hors écran) et « Ils nous font confiance » **masqué tant qu'il n'y a pas de vraies données** (§58).
- Freins : une colonne de cartes de 597 px, H1 qui n'est que la marque, carrousel sans prix ni pause, badge « Nouveau » sur tous les produits (3,3:1), slogan en 9 px.

### 4.2 Boutique et rayon ([capture](captures/mobile-boutique.png))
- 14 produits, tous à prix plein, H1 « Boutique », tri, bouton « Filtres » de 48 px, bande de 4 promesses (dont « Livraison en 24 h à 48 h »), premier « Commander » à 1 194 px (1,4 écran).
- La bande de promesses (≈ 355 px) répète « Livraison gratuite 100 % » et « Livraison partout en Tunisie » : première carte à 662 px.
- Le rayon Cuisine a son H1 et ses 4 produits, mais pas de texte d'introduction, et ses filtres montrent les compteurs de toute la boutique.

### 4.3 Fiche produit : Visseuse 21V ([capture](captures/mobile-fiche-visseuse.png))

| Élément | Position | Écrans (844) |
|---|---|---|
| Fil d'Ariane (1 ligne) | 159 px | — |
| Galerie 358 × 358 px + 6 vignettes sur 1 rangée | 196–648 px | 0,2–0,77 |
| H1 (26 px) | 664 px | 0,79 |
| **Prix** « 79,000 DT », bleu nuit, 16,5:1 | **743 px** | **0,88** |
| Réassurance : paiement, livraison offerte, **24 h à 48 h** | 823–935 px | 1,0–1,1 |
| Formulaire express (959 px) | 1 171 px | 1,4 |
| « Confirmer ma commande » (254 × 65 px, **2,61:1**) + panier en icône (56 × 65 px) | 2 047 px | 2,4 |
| Carte WhatsApp « Une question sur ce produit ? » | 2 242 px | 2,7 |
| Onglets Description · FAQ · Livraison & Retours | 2 412 px | 2,9 |
| Produits similaires (2) | 3 909 px | 4,6 |
| Barre collante (69 px) : prix, WhatsApp 48 px, « Commander » 206 × 52 px à 6,32:1 | fixe | d'emblée |

- **Très bien** : premier écran utile (photo, nom et prix sans défiler), délai sous le prix, formulaire court (`autocomplete`, 16 px, 48 px), validation du téléphone en direct, barre collante qui amène au bon endroit, onglets qui n'affichent que du vrai (pas d'onglet Avis à 0, pas de caractéristiques inventées), le WhatsApp flottant laisse sa place à celui de la barre.
- **À reprendre** : le bouton qui encaisse est retombé à 2,61:1 (UX-41). Les « Commander » des produits similaires font 48 × 34 px (UX-44). La case CGV fait 24 px et son lien 16 px de haut (UX-47). Pas de vidéo.
- Boîte à salade : même gabarit (prix à 712 px, bouton à 1 991 px).

### 4.4 Panier ([capture](captures/mobile-panier.png))
- 2 876 px (3,4 écrans). Étapes, « Mon panier · 1 article », carte produit (stock, prix unitaire), − / + et « Supprimer » à 48 px, « Continuer mes achats ».
- Le **bloc « Code promo » ouvert** (champ de 48 px et bouton plein bleu nuit) passe avant le récapitulatif, alors qu'aucun code n'existe. « Passer la commande » (bleu nuit sur orange, 6,32:1) n'arrive qu'à 1 056 px.
- Panier vide : carte propre, mais aucune suggestion de produits ni aucun lien WhatsApp.

### 4.5 Commande (`/commande/`, [capture](captures/mobile-commande.png))
- 4 611 px (5,5 écrans). H1 « Finaliser ma commande » et une phrase juste : « nous vous appelons pour confirmer ».
- **Premier écran** : code promo, puis « Comment souhaitez-vous commander ? » (2 cartes). Le premier champ est à 945 px.
- Cartes : 1 Vos coordonnées (Nom complet, Téléphone, E-mail facultatif), 2 Adresse (Adresse, Ville facultative, **Gouvernorat pré-rempli « Tunis »**, « Expédier à une adresse différente ? », « Notes de commande »), 3 Mode de livraison (« Livraison en 24 h à 48 h »), puis récapitulatif, paiement COD, confidentialité en français, case CGV, et « Confirmer ma commande » à 2 791 px (6,32:1).
- Le WhatsApp flottant (56 px) recouvre le bord des cartes, alors que l'encart WhatsApp figure déjà sous le bouton.

### 4.6 Pages de confiance

| Page | 23/09 | 25/09 |
|---|---|---|
| Retours ([capture](captures/mobile-retours.png)) | ❌ notes internes | ⚠️ page propre et honnête, procédure par WhatsApp. Politique « en cours de rédaction » ; bouton « Envoyer sur WhatsApp » blanc sur orange (2,61:1). |
| CGV | ❌ squelette | ⚠️ 5 articles factuels, sommaire, date. Texte provisoire, aucune information légale. |
| Livraison | ⚠️ sans délai | ✅ délai, 7 régions et 24 gouvernorats, 4 étapes, 5 questions, encart WhatsApp. |
| Contact | ⚠️ WhatsApp cassé, sans horaires | ✅ WhatsApp fonctionnel, « De 9 h à 18 h », Sousse, e-mail. ⚠️ Téléphone 56261412 différent du WhatsApp 26767491. |
| À propos | ✅ | ✅ (« On cherche, on sélectionne »). |
| FAQ | ⚠️ 4 questions | ⚠️ inchangée : 4 questions, « 0 TND » alors que le reste du site dit « DT ». |
| Confidentialité | ⚠️ ton développeur | ⚠️ inchangée. La nouvelle page Cookies est claire, mais aucun bandeau. |
| Mentions légales | ❌ 404 | ❌ 404. |
| Réseaux sociaux | ❌ | ❌ |

### 4.7 Version arabe ([accueil](captures/mobile-ar-accueil.png), [fiche](captures/mobile-ar-fiche.png))
- `lang="ar"`, `dir="rtl"`, aucun débordement. Menus, titres, fil d'Ariane, formulaire (« الاسم واللقب », « التليفون »), boutons (« أكّد طلبيتك », « زيد للسلّة »), onglets (« الوصف · أسئلة متكرّرة · التوصيل والإرجاع ») et délai (« التوصيل في 24 لـ 48 ساعة ») sont traduits. Le prix s'affiche « 79,000 د.ت ».
- Résidus : noms de produits en français, flèche du héros non inversée, confidentialité en français au checkout, CGV en arabe standard. En-tête de 91 px (77 px en FR) : **le prix de la fiche (759–794 px) est à moitié caché par la barre collante (779 px)** au premier écran (UX-46).

### 4.8 Performance ressentie
- Pages en cache LiteSpeed : TTFB de 0,15 à 0,22 s (curl anonyme), chargement complet de 1,3 à 1,8 s en headless (jusqu'à 3,9 s pour une fiche au premier passage).
- Panier : 6,4 s, commande : 4,4 s (chargement complet, non mis en cache par nature). C'est désormais le point lent du tunnel, voir `audit-performance.md` (PHP 8.3, OPcache, *faults* LVE).

---

## 5. Nouveaux constats (UX-41 et suivants)

| ID | Sév. | Page | Observation | Impact | Recommandation | Qui | Effort |
|---|---|---|---|---|---|---|---|
| UX-41 | **Haute** | Fiche (formulaire express), Retours, produits similaires | **Régression** : « Confirmer ma commande » est passé de bleu nuit sur orange (6,32:1) à **blanc sur orange (2,61:1)** avec la maquette du 25/09. Même défaut sur « Envoyer sur WhatsApp » (Retours) et sur les « Commander » des cartes. | Le bouton qui encaisse devient le moins lisible au soleil, sur un écran d'entrée de gamme. | Texte bleu nuit (`--famma-blue`) sur tous les fonds orange, comme la barre collante et le checkout. Une seule variable `--famma-on-orange` règle le héros, les cartes, l'express et Retours. | Code | S |
| UX-42 | Moyenne | Contact, pied de page, tous les `tel:` | **Deux numéros** : WhatsApp 26 767 491, téléphone 56 261 412. Le pied de page aligne « WhatsApp » et « 56261412 » sans préciser lequel sert à quoi. | Le client qui appelle le numéro affiché pour WhatsApp, ou l'inverse, croit à une erreur, alors que le numéro est un signal de confiance majeur en COD. | Trancher : un seul numéro, ou des libellés explicites (« WhatsApp : 26 767 491 · Appel : 56 261 412 ») dans FAMMA → Pages → Contact. | Toi + Admin | S |
| UX-43 | Moyenne | Checkout | **Gouvernorat pré-rempli « Tunis »** pour un visiteur anonyme, alors que le formulaire express affiche « Choisir… / اختار ». | Un client pressé hors de Tunis valide avec le mauvais gouvernorat : appel de correction, retard, refus. | WooCommerce → Réglages → Général → « Emplacement du client par défaut » : « Aucun emplacement par défaut » (ou vider l'état par défaut via un filtre dans famma-core). | Admin | S |
| UX-44 | Basse | Fiche, produits similaires | Les « Commander » des cartes similaires font **48 × 34 px** : icône seule (texte à 0 px), blanc sur orange. | Cible sous 48 px et libellé invisible, en bas de fiche où l'on relance l'achat. | 48 px de haut, bleu nuit sur orange, icône et libellé (« Commander / أطلب »). | Code | S |
| UX-45 | Basse | `/faq/` | La FAQ générale reste à 4 questions en « 0 TND », alors que la page Livraison a sa propre FAQ de 5 questions (délai compris) en « DT ». | Deux sources qui divergent, devise incohérente, questions COD clés sans réponse (délai, échange, ouverture du colis, refus). | Alimenter `/faq/` depuis FAMMA → Pages (même source que Livraison), en « DT », et y ajouter les vraies réponses du propriétaire. | Admin + Toi | S |
| UX-46 | Basse | Fiche AR (et FR à la limite) | Au premier écran en AR, le prix (759–794 px) est à moitié caché par la barre collante (779 px). En FR, il ne dépasse que de 3 px (743–778 contre 775). | Le prix reste lisible dans la barre, mais le bloc prix de la page paraît coupé. | Réduire l'espace au-dessus du H1 (en-tête AR de 91 px), ou n'afficher la barre qu'une fois le prix de la page passé (`IntersectionObserver` sur `.price`). | Code | S |
| UX-47 | Basse | Formulaire express, checkout | Case CGV de 24 × 24 px, lien « conditions générales » de 16 px de haut, radios de compte de 20 × 20 px (dans des cartes cliquables). | Case obligatoire difficile à cocher au pouce, et un échec de validation en fin de parcours. | Zone cliquable (label) d'au moins 48 px de haut autour de la case ; lien CGV hors de la zone de la case. | Code | S |
| UX-48 | Basse | Boutique, rayons | La bande de 4 promesses (≈ 355 px) répète « Livraison gratuite 100 % » et « Livraison partout en Tunisie » ; la première carte arrive à 662 px. | Défilement de plus avant le premier produit, avec une grille déjà en une colonne. | 3 promesses sur une ligne compacte (ou 2 × 2 de 48 px), sans doublon. | Code / Admin | S |
| UX-49 | Basse | Panier, commande | Le WhatsApp flottant (56 px, bas droite en FR, bas gauche en AR) recouvre les cartes du tunnel alors qu'un encart WhatsApp est déjà dans la page. | Obstacle au pouce sur les champs et les cartes, au dernier mètre. | Masquer le flottant sur `is_cart()` et `is_checkout()`, comme sur la fiche. | Code | S |

---

## 6. Top 10 CRO mis à jour (impact ÷ effort)

| # | Action | Constats | Impact | Qui | Effort |
|---|---|---|---|---|---|
| 1 | **Texte bleu nuit sur tous les boutons orange**, à commencer par « Confirmer ma commande » | UX-41, UX-22 | Fort | Code | S |
| 2 | **Checkout sans friction** : masquer le code promo tant qu'aucun code n'existe, retirer le choix de compte (l'offre après commande existe), retirer « autre adresse » et « Notes », gouvernorat vide par défaut, objectif bouton sous 2 écrans | UX-17, UX-43 | Fort | Code + Admin | S–M |
| 3 | **Panier** : code promo replié ou masqué, « Passer la commande » dans le premier écran ; après « Ajouter au panier », proposer « Commander maintenant » | UX-16, UX-34 | Fort | Code | S |
| 4 | **Textes légaux réels** : politique de retour (délai, frais, remboursement), CGV définitives, mentions légales (raison sociale, matricule fiscal) | UX-02, UX-03, UX-32 | Très fort (confiance, refus) | Toi | M |
| 5 | **Réseaux sociaux** dans le pied de page et la page Contact (la page Facebook, Instagram ou TikTok d'où vient la publicité) | UX-30 | Fort | Toi + Admin | S |
| 6 | **Grille mobile à 2 colonnes** avec carte compacte (image, nom sur 2 lignes, prix, CTA) | UX-11, UX-48 | Moyen à fort | Code | S–M |
| 7 | **Un numéro clair** (ou deux numéros étiquetés) | UX-42 | Moyen | Toi | S |
| 8 | **Vitesse du panier et de la commande** (4 à 6 s) : PHP 8.3, OPcache, *faults* LVE | UX-08 | Moyen à fort | Hébergeur | M |
| 9 | **FAQ unique et complète** (délai, échange, ouverture du colis, refus), en « DT », et H1 de l'accueil porteur de l'offre | UX-29, UX-45, UX-25 | Moyen | Admin + Toi | S |
| 10 | **Finitions RTL et accessibilité** : flèche inversée, liens de langue à 48 px, pause et vrais noms sur le carrousel, 9 px → 12 px, badge vert foncé, cibles CGV, WhatsApp flottant masqué dans le tunnel, vidéo produit | UX-24, UX-36, UX-39, UX-44, UX-47, UX-49, UX-40 | Moyen | Code | S |

**À mesurer ensuite** (pixel Meta et GA4 chargés) : vue de fiche → envoi du formulaire express, et ajout au panier → checkout → commande, pour vérifier que les actions 2 et 3 font baisser l'abandon. Le **taux de refus à la livraison** reste le juge final des corrections de confiance (actions 4, 5 et 7).

---

*Audit en lecture seule du 25/09/2026, visiteur anonyme en Chrome headless avec un profil vierge. Aucune commande ni aucun formulaire envoyé, aucune donnée personnelle saisie, panier vidé en fin de session. Aucun fichier du dépôt modifié, hormis ce rapport et le dossier `captures/`.*
