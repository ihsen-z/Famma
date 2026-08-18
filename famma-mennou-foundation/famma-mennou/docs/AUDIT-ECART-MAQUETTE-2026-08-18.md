# Audit d'écart maquette ↔ site — Accueil, Boutique, Fiche produit

18 août 2026 · maquettes lues depuis le projet Claude Design `Logo Famma e-commerce`
· site mesuré sur `http://localhost:8080` · commit `0f8e36a`

Objectif de la mission : **rendre le site identique au pixel aux trois maquettes
fournies**. Ce rapport dit où on en est, ce qui bloque réellement, et dans quel
ordre fermer l'écart.

---

## 1. Ce que j'ai lu

Importé depuis le MCP design (projet `90370726-36bf-4144-824a-394e2db3f56d`) :

| Fichier | Rôle | Traitement |
|---|---|---|
| `FAMMA Accueil.dc.html` | maquette accueil | lue via son handoff (spécification complète) |
| `FAMMA Boutique.dc.html` | maquette boutique | **lue intégralement**, 57 Ko, structure + styles inline extraits |
| `FAMMA Produit.dc.html` | maquette fiche produit | **lue intégralement**, 56 Ko, idem |
| `design_handoff_famma_accueil/README.md` | handoff | spécification exhaustive des jetons et des sections |
| `image-slot.js` | composant d'emplacement image | lu — **placeholder de design, à ne pas embarquer** (le handoff l'indique) |
| `support.js` | moteur de rendu `.dc.html` | **non porté** — le handoff dit explicitement « référence, à ne pas porter » |

Deux points du handoff qui cadrent toute la mission :

> « Les fichiers de ce paquet sont des **références de design réalisées en
> HTML** […] **pas du code de production à copier tel quel**. La tâche est de
> recréer ces designs dans l'environnement existant du projet cible. »

> « Seule exception : les **photos produits sont absentes** (le client ne les a
> pas fournies). »

Côté site, j'ai mesuré les valeurs calculées dans le navigateur
(`getComputedStyle`), pas lu le CSS : c'est la seule façon de comparer à une
maquette. Le projet dispose déjà d'un journal d'intégration de 1 042 lignes
([DESIGN-INTEGRATION-PLAN.md](DESIGN-INTEGRATION-PLAN.md)) que j'ai relu pour ne
pas signaler comme défaut un arbitrage déjà tranché.

---

## 2. La question de fond : « pixel perfect » est-il atteignable ?

**Sur la géométrie, oui — et c'est déjà largement fait.** Sur le contenu et les
couleurs de texte, non, et il faut le dire avant de planifier quoi que ce soit.
Trois verrous, dont deux ne se règlent pas en code.

### Verrou 1 — La maquette est peuplée de données inventées

La maquette n'est pas un gabarit vide : c'est un prototype rempli pour être beau.
Reproduire son rendu au pixel oblige à reproduire son contenu. Or ce contenu est
de la fiction :

| Dans la maquette | Nature |
|---|---|
| « **+ 2 500** Clients satisfaits » | chiffre inventé |
| « **4.8/5** — Basé sur **538 avis** » | note et volume inventés |
| Témoignages signés, avec avatars et badge « Achat vérifié » | avis inventés |
| Filtre **Marques** avec des noms de marques et leurs compteurs | marques inventées |
| Onglet **Spécifications** rempli (couples clé/valeur) | specs inventées |
| Onglet **FAQ** avec questions et réponses | contenu inventé |
| **5 photos produit** par fiche, 5 miniatures | non fournies (le handoff le dit) |
| Prix « 89 DT / 109 DT », remises | prix inventés |

Le §58/§60 du projet l'interdit sans arbitrage possible : *« Aucune donnée
inventée. Jamais de faux avis, faux témoignages, fausses statistiques. »* C'est
la règle la plus dure du projet, et c'est celle qui rend une part du pixel
inaccessible **tant que les vraies données n'existent pas**.

Ce n'est pas un obstacle définitif : dès que vous fournissez 3 vrais produits
avec 5 photos chacun, des specs réelles et de vraies réponses de FAQ, ces
sections deviennent identiques à la maquette. **Le pixel manquant est du
contenu, pas du code.**

### Verrou 2 — La maquette échoue à WCAG AA, et l'arbitrage a déjà été rendu

Le journal d'intégration l'avait relevé (écart **C-1**, neuf endroits). Le
16/08/2026 vous avez demandé la **fidélité stricte**. Deux jetons ont donc été
basculés :

| Jeton | Conforme | Maquette (actuel) | Contraste |
|---|---|---|---|
| `--famma-on-orange` | `#0B1F3A` | `#FFFFFF` | 6,32:1 → **2,61:1** |
| `--famma-accent-text` | `#B85300` | `#FF7A00` | 4,91:1 → **2,61:1** |

Coût mesuré et assumé : **14 échecs de contraste sur 116 éléments** en boutique,
**14 sur 105** en fiche produit, dont **le prix à 2,46:1** — l'information la
plus lue de la page.

Je ne rouvre pas la décision : elle est prise, documentée, et vous l'avez
confirmée. Je note seulement que la conformité tient à **un seul jeton**, donc
qu'elle reste récupérable à tout moment sans toucher une règle CSS.

### Verrou 3 — Un catalogue de 4 produits ne peut pas remplir une maquette dessinée pour un catalogue plein

La maquette boutique montre 5 produits, une **pagination** à plusieurs pages, 6
blocs de filtres avec des compteurs fournis. Avec 4 produits, dont 3 dans
« Sans rayon », la pagination ne s'affiche pas (elle est pourtant stylée), les
compteurs sont à 1, et le filtre par note ne trie rien.

Ce n'est pas un défaut d'intégration : c'est la maquette qui suppose un état que
la boutique n'a pas encore.

---

## 3. Ce qui est DÉJÀ au pixel — vérifié, pas supposé

À dire d'abord, parce que ça change l'ampleur du travail restant.

**Les jetons de la maquette sont déjà dans le thème, à la valeur exacte.**
`#0B1F3A`, `#FF7A00`, `#E86E00`, `#16A34A`, `#25D366`, `#E7EAF0`, `#EDEFF3`,
`#DFE3EA`, `#FBFCFD`, `#F4F6F9`, `#F1F4F9`, `#56617A`, `#46536B`, `#FFF1E3` —
tous présents dans [tokens.css](../wp-content/themes/famma-child/assets/css/tokens.css),
avec leur rôle documenté. Le conteneur (1240 px / 20 px de gouttière), l'échelle
d'espacement et les rayons (5 · 7 · 8 · 9 · 10 · 12 · 14 · 16) y sont aussi.

**Le journal d'intégration a mesuré et atteint :**

- Boutique : **22 des 22 valeurs** identiques à la maquette (fil d'Ariane, titre,
  tri, bascule 38×38, colonne 260 px, gouttière 24 px, cartes de filtre, cœur
  favori 30×30…)
- Fiche produit : passée de **8/25 à 20/20**

**Ma propre mesure indépendante sur la fiche produit confirme** :

| Élément | Maquette | Site mesuré | |
|---|---|---|---|
| Badge remise | 15px · 7/14px · radius 8 · `#FF7A00` | 15px · 7px 14px · 8px · `rgb(255,122,0)` | ✅ |
| `<h1>` | `clamp(26px,3.2vw,34px)` · 700 · lh 1,22 · ls −0,01em | 26px · 700 · 31,72px (=1,22) · −0,26px (=−0,01em) | ✅ |
| **Prix** | `clamp(30px,3.6vw,38px)` · 700 · **`#FF7A00`** | 30px · 700 · `rgb(255,122,0)` | ✅ |
| Galerie | radius 14 · fond `#F4F6F9` · ratio 1/1 | radius 14px · `rgb(244,246,249)` · 353×353 | ✅ |
| CTA principal | radius 9 · padding 16/24 · 16px | radius 9px · 16px 24px · 16px | ✅ |
| Bouton secondaire | 56×56 · radius 9 | 56×56 · radius 9px | ✅ |
| Onglets : marge | 15/22px | 15px 22px | ✅ |
| Carte : nom · prix barré | min-h 39px · 19px | 39px · 19px | ✅ |

Autrement dit : **l'habillage, la géométrie et la palette sont faits.** Ce qui
reste tient en une liste courte.

---

## 4. L'écart restant, page par page

### 4.1 Transversal (barre supérieure, en-tête, pied de page, flottant)

Tout existe en code. Ce qui manque est **une valeur dans `.env`**.

| Écart | Maquette | Site | Cause |
|---|---|---|---|
| Promesses en barre supérieure | 3 | **2** | `FAMMA_WHATSAPP_NUMBER` vide → la 3ᵉ (« Support WhatsApp ») est conditionnée |
| Colonnes de pied de page | 5 | **4** | colonne « Aide & support » sans `FAMMA_CONTACT_*` |
| Icônes sociales | 4 ronds 32px | **0** | `FAMMA_SOCIAL_*` vides |
| Bouton WhatsApp flottant 56px `#25D366` | présent | **absent** | même cause |
| Liens légaux en barre basse | 4 | **3** | page « Mentions légales » inexistante |

Renseignez `.env`, `docker compose up -d`, et les cinq lignes se ferment sans
toucher au code. C'est écrit tel quel dans le journal d'intégration, et je l'ai
vérifié dans le code (`famma_child_topbar_promises()` ajoute bien la 3ᵉ promesse
sous condition).

**Un écart transversal qui n'est pas une donnée** : les libellés du menu portent
« Categories » et « Nouveautes » **sans accents**, et le sous-menu expose
« Uncategorized » en anglais. La maquette écrit « Catégories » et « Nouveautés ».
Sources : [create-menus.php:159](../scripts/create-menus.php),
[create-shop-filters.php:41](../scripts/create-shop-filters.php).

### 4.2 Accueil

| Écart | Maquette | Site | Type |
|---|---|---|---|
| Bande **newsletter** `#0B1F3A` radius 14, champ + bouton orange | présente | **remplacée** par une bande WhatsApp | **arbitrage C-5 déjà tranché** — aucun service d'emailing configuré ; et la bande de remplacement est elle-même conditionnée au numéro, donc rien ne s'affiche |
| Bloc **avis** : « + 2 500 clients », « 4.8/5 · 538 avis », témoignages signés | présent | **1 avis réel, signé `famma_admin`** | **verrou 1** (§58) |
| Carrousel héros : 4 slides, phrase différente par slide | 4 | à recâbler sur de vrais produits | données |
| Catégories : 6 entrées dont « Nouveautés » (disque orange + étoile) | 6 | **3 non vides** | données |
| Grille produits `minmax(196px)` gouttière 18 | conforme | **conforme** | ✅ |
| Bloc éditorial « le catalogue n'est pas encore ouvert » | absent de la maquette | **présent, sous 4 produits** | contenu à réécrire |

⚠ **Correction à mon rapport UX de ce matin.** J'y recommandais de baisser
`minmax(12.25rem)` → `10.25rem` pour obtenir deux colonnes sur mobile. **Cette
recommandation contredit la maquette**, qui spécifie `minmax(196px, 1fr)` — donc
une colonne à 375 px. Le site est fidèle ; c'est la maquette qui produit une
colonne sur téléphone. À trancher explicitement (voir §7, décision 2) :
fidélité au dessin, ou densité marchande. On ne peut pas avoir les deux.

### 4.3 Boutique

**Manque réellement en code** (2 écarts) :

| # | Écart | Détail de la maquette |
|---|---|---|
| B-1 | **Filtre « Marques » absent** | 6ᵉ bloc de la colonne : cases 17px radius 4, libellé 13.5px, compteur 12.5px `#98A2B3`. Le thème a 5 filtres (catégories, prix, stock, note, promo) ; le 6ᵉ n'existe pas |
| B-2 | **Pagination invisible** | prev/next 36×36 radius 8 + numéros `min-width 36px`. Le CSS existe ([shop.css:566-592](../wp-content/themes/famma-child/assets/css/shop.css)) mais 4 produits < produits par page |

**Existe en code, ne s'affiche pas** (données) :

- Carte d'aide WhatsApp en bas de colonne (46px `#25D366` + titre + CTA) —
  `famma_child_shop_help_card()` existe, conditionnée au numéro
- Ligne « bénéfice » sur la carte — nourrie par la description courte, absente
  sur 3 produits sur 4
- Bande newsletter → arbitrage C-5

**Conforme et vérifié** : fil d'Ariane 13px, `<h1>` `clamp(28px,3.4vw,36px)`,
sous-titre 14px, tri 13.5px/150px/radius 8, bascule 38×38, colonne 260px,
gouttière 24px, cartes de filtre 20px/radius 12, promesses 16/18px, compte de
résultats 13.5px, grille gouttière 18px, badge 11.5px/radius 5, bouton
13.5px/radius 7, cœur 30×30.

### 4.4 Fiche produit — c'est ici que l'écart est le plus large

**Manque réellement en code** (5 écarts, mesurés) :

| # | Écart | Maquette | Site mesuré |
|---|---|---|---|
| **P-1** | **Pastille « Vous économisez » non conforme** | fond `#FFF1E3`, texte `#C25E00`, 13.5px/500, radius 8, padding 7/14 | **fond transparent, texte vert `#157A45`, 14px, radius 0, padding 0** — c'est un paragraphe, pas une pastille. Les jetons `--famma-saving-bg` / `--famma-saving-text` existent pourtant déjà et ne sont pas utilisés |
| **P-2** | **Onglets « Spécifications » et « FAQ » absents** | 5 onglets : Description · Spécifications · Avis · FAQ · Livraison | **3 onglets** (Description · Livraison · Avis). L'accordéon FAQ et le tableau de specs n'existent pas |
| **P-3** | **Miniatures et flèches de galerie absentes** | 5 miniatures (grille 5 colonnes, radius 9, bordure 2px active) + 2 flèches rondes 32px | **0 miniature, 0 flèche** — le CSS `flex-control` existe, il manque les images |
| **P-4** | **Carte « Produits similaires » = carte boutique** | image **168px**, nom **13.5px**/min-h **38px**, prix **17px**, **bouton carré 34×34 orange radius 7 avec icône seule** | image **178px**, nom **14px**/min-h **39px**, prix **19px**, **bouton pleine largeur 306×40 avec libellé** |
| **P-5** | **Score d'avis à la mauvaise échelle** | **44px**/700 + « /5 » 17px | **28px** (c'est la valeur de la maquette *accueil*, pas *produit*) |

**Écarts fins** (2) :

| Écart | Maquette | Site |
|---|---|---|
| Libellé d'onglet | 14.5px | **15,3px** — `.wc-tabs` hérite 17px, le `a` en tire 0,9em. Corriger la taille sur le `ul`, pas sur le `a` |
| Graisse du CTA | 600 | **700** |
| Prix barré | `#98A2B3` | `#626976` — assombrissement C-1 conservé sur ce seul jeton |

**Existe en code, ne s'affiche pas** : carte « Commander sur WhatsApp »
(bordure `#D8EFDF`, fond `#F3FBF5`, radius 10, rond vert 38px) —
`famma_child_product_whatsapp_card()` existe, conditionnée au numéro.

**Bloqué par le verrou 1** : accroche produit 14.5px (aucune description),
onglet Spécifications (aucune spec réelle), FAQ (aucune réponse rédigée), avis
avec avatars et badges « Achat vérifié » (un seul avis, écrit par l'admin), et
la note de chantier « À compléter par le propriétaire » qui s'affiche
**aux clients** dans l'onglet Livraison.

---

## 5. Synthèse : où va vraiment l'écart

| Catégorie | Nombre d'écarts | Se ferme par |
|---|---|---|
| **Données manquantes** (photos, descriptions, specs, FAQ, avis, numéro WhatsApp, réseaux, contacts, marques) | **~18** | vous — pas une ligne de code |
| **Arbitrages déjà tranchés** (C-1 contraste, C-2 données inventées, C-3 polices, C-5 newsletter) | 4 | rien à faire, sauf revenir sur une décision |
| **Code réellement manquant ou non conforme** | **9** | B-1, B-2, P-1 à P-5, + 2 écarts fins |

**Conclusion : sur ~31 écarts constatés, 9 relèvent du développement.** Le reste
est du contenu et des décisions. C'est une bonne nouvelle : l'intégration a été
faite sérieusement et mesurée ; ce qui reste est court et cerné.

---

## 6. Plan d'action pixel-perfect

### Lot P1 — Fermer les 9 écarts de code (1 à 2 jours)

| # | Action | Détail |
|---|---|---|
| 1 | **P-1 — Pastille « Vous économisez »** | Utiliser `--famma-saving-bg` / `--famma-saving-text` (déjà définis) : fond `#FFF1E3`, radius 8, padding 7/14, 13.5px/500. Retirer le vert `--famma-success-700`. *Le plus visible des neuf, et le moins coûteux.* |
| 2 | **P-5 — Score d'avis à 44px** | + « /5 » à 17px `#8A94A6` |
| 3 | **Onglets à 14.5px** | Poser la taille sur `.wc-tabs`, pas sur le `a` (Kadence hérite 17px) |
| 4 | **Graisse du CTA à 600** | Un seul déclarateur |
| 5 | **P-4 — Carte « Produits similaires » dédiée** | Feuille propre : image 168px, nom 13.5px/min-h 38px, prix 17px + barré 12.5px, **bouton carré 34×34 orange radius 7, icône seule**. Ne pas toucher la carte boutique |
| 6 | **P-2 — Onglet « Spécifications »** | Tableau clé/valeur alimenté par les **attributs produit WooCommerce** : grille `minmax(280px)`, lignes `padding 13px 0`, séparateur `#EEF1F5`, clé `#56617A`, valeur 500 alignée à la fin. Vide → onglet masqué |
| 7 | **P-2 — Onglet « FAQ »** | Accordéon : bouton pleine largeur `padding 17px 0`, 14.5px/500, séparateur `#EEF1F5`, réponse 13.5px lh 1,65 `#46536B` max-width 760px. Source : un champ répétable ou un bloc réutilisable. Vide → onglet masqué |
| 8 | **B-1 — Filtre « Marques »** | 6ᵉ widget sur le modèle des cinq existants, adossé à un attribut `pa_marque`. Masqué si l'attribut n'existe pas |
| 9 | **P-3 — Flèches de galerie** | 2 boutons ronds 32px bordure `#DFE3EA` de part et d'autre des miniatures |

Deux garde-fous non négociables sur ce lot : **chaque section vide se masque**
(un onglet « Spécifications » vide est pire qu'absent — cf. la note de chantier
actuellement visible sur la fiche), et **aucune valeur d'exemple n'est écrite en
dur**.

### Lot P2 — Débloquer par `.env` (30 minutes, dès que vous avez les valeurs)

`FAMMA_WHATSAPP_NUMBER`, `FAMMA_SOCIAL_*`, `FAMMA_CONTACT_*` → ferme d'un coup :
3ᵉ promesse de la barre supérieure, 5ᵉ colonne de pied de page, 4 icônes
sociales, bouton flottant 56px, carte d'aide de la boutique, carte « Commander
sur WhatsApp » de la fiche, bande WhatsApp de bas de page.

**Sept écarts fermés par un fichier de configuration.**

### Lot P3 — Le contenu (vous ; c'est le vrai chemin critique)

| Besoin | Format attendu par la maquette |
|---|---|
| **5 photos par produit** | ratio 1:1, fond uniforme — la galerie et les 5 miniatures en dépendent |
| Description courte | 1 phrase → ligne « bénéfice » des cartes |
| Accroche produit | 14.5px, ~2 lignes, max 520px |
| Description longue | onglet Description + liste à puces + encart cadeau `#FFF7EF` |
| **Spécifications réelles** | couples clé/valeur, en attributs WooCommerce |
| **FAQ réelle** | 4 à 6 questions/réponses |
| Contenu de l'onglet Livraison | transporteur, délai par gouvernorat, procédure en cas d'absence |
| Marques | si le catalogue en a — sinon le filtre reste masqué |

Tant que les photos manquent, **la fiche produit ne peut pas ressembler à la
maquette**, quel que soit le code : son écran principal est une galerie.

### Lot P4 — Ce qui n'arrivera pas au pixel, et il faut l'assumer

- « + 2 500 clients satisfaits », « 4.8/5 sur 538 avis », témoignages signés avec
  avatars : **jamais** avant d'avoir de vrais avis. Le thème masque déjà la
  section sous zéro avis — c'est le bon comportement.
- La pagination n'apparaîtra qu'au-delà du nombre de produits par page.
- Les compteurs de filtres afficheront les vrais chiffres, pas ceux de la maquette.

---

## 7. Décisions à trancher

1. **Contraste (C-1).** La décision du 16/08 tient-elle ? Coût mesuré : 14 échecs
   AA par page, prix de la fiche à **2,46:1**. Retour à la conformité = **un
   jeton**, aucune règle CSS touchée. *Ma recommandation d'expert : revenir au
   bleu sur orange (6,32:1) — c'est déjà ce que fait la barre d'achat collante,
   et personne n'a trouvé qu'elle dépareillait.*
2. **Une ou deux colonnes de produits sur mobile.** La maquette impose
   `minmax(196px)`, donc une colonne à 375 px. La conversion plaide pour deux.
   **Fidélité ou densité — il faut choisir.** *Ma recommandation : deux colonnes,
   et acter l'écart au dessin par écrit.*
3. **Newsletter (C-5).** On rétablit la bande de la maquette avec un vrai service
   d'emailing, ou on garde la bande WhatsApp ?
4. **Onglets vides.** Masqués (ma proposition) ou visibles avec un message ?
5. **Marques.** Le catalogue aura-t-il des marques ? Sinon, le 6ᵉ filtre ne sera
   jamais construit et l'écart reste ouvert par choix.
6. **Prix barré.** `#98A2B3` de la maquette (2,58:1) ou `#626976` actuel
   (5,52:1) ? Même arbitrage que 1, sur un élément mineur.

---

## 8. Ce que je n'ai pas fait, et pourquoi

- **Aucune modification du code ni des données** pendant ce passage : la mission
  était d'auditer et de proposer un plan.
- **`support.js` non porté** — le handoff l'interdit explicitement.
- **`image-slot.js` non embarqué** — composant de placeholder de design ; les
  emplacements `famma-hero-1..4`, `famma-cat-1..5`, `famma-p1..p5`, `famma-cod`
  doivent devenir des champs média WordPress.
- **Aucune donnée d'exemple de la maquette recopiée** — c'est le §58, et c'est
  aussi ce qui vous protège juridiquement sur les avis et les statistiques.
- **Pas de comparaison d'images pixel à pixel** : les maquettes PNG existent
  (`maquettes/01-accueil.png`, `02-boutique.png`, `03-produit.png`) mais un
  diff d'image sur un site sans photos produit ne mesurerait que l'absence de
  photos. La comparaison a donc porté sur les **valeurs calculées**, qui sont la
  seule mesure exploitable à ce stade. Dès que les photos seront en place, un
  diff visuel des trois pages sera la bonne recette finale.

---

*Audit conduit le 18 août 2026. Valeurs du site relevées par `getComputedStyle`
sur le rendu réel, conteneur 375 px. Valeurs de la maquette extraites des styles
inline des fichiers `.dc.html` et du handoff.*
