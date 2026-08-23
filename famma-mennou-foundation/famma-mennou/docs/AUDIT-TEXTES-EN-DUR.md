# Audit — textes en dur du thème

**Date** : 2026-08-23 · **Demandé par** : le propriétaire, après la règle « tout doit être paramétrable, pas de hard coding »
**Statut** : recensement. **Aucun code modifié par cet audit.**

Ce document répond à une question : que reste-t-il d'écrit dans le code, et que coûterait de le rendre éditable ? Il ne décide rien — l'arbitrage revient au propriétaire.

Périmètre : le thème `famma-child`. Déjà traités et donc hors liste — les pages À propos et Contact (ADR-007) et la barre de promesses de l'en-tête.

---

## 1. Le constat qui commande tout le reste

Le message de réassurance de FAMMA — paiement à la livraison, livraison offerte, commande sans compte, équipe joignable — n'est pas écrit une fois. Il est écrit **cinq fois**, dans cinq fichiers, avec des formulations proches mais différentes :

| Où | Formulation de « Livraison offerte » |
|---|---|
| `inc/home.php` (section « Pourquoi choisir FAMMA ? ») | « Partout en Tunisie, dans les 24 gouvernorats, sans frais ajoutés. » |
| `inc/home.php` (héros) | « partout en Tunisie » |
| `inc/product.php` (réassurance fiche produit) | « Partout en Tunisie, dans les 24 gouvernorats. » |
| `functions.php` (réassurance checkout) | « Livraison offerte, partout en Tunisie » |
| `inc/footer-parts.php` (pied de page) | « Vous payez uniquement à la réception de votre commande. » |

**Pourquoi ça compte** : le jour où l'offre change — une zone non couverte, un seuil de gratuité — il faut retrouver cinq endroits. En oublier un fait mentir le site à cet endroit précis, et c'est celui-là que le client lira.

**Conséquence pour la suite** : rendre ces textes éditables sans d'abord les **unifier** ne ferait que déplacer le problème — on aurait cinq champs d'administration à maintenir au lieu de cinq bouts de code. La bonne séquence est : une source unique, puis un champ.

---

## 2. Ce qui mérite de devenir éditable

Classé par valeur décroissante. « Coût » = effort de développement, pas nombre de champs à remplir.

### A. Bloc de réassurance unifié — **valeur : forte · coût : moyen**

Les quatre promesses (titre + texte), servies à cinq endroits depuis une seule source. Un champ édité se répercute partout.

- Fichiers concernés : `inc/home.php`, `inc/product.php`, `functions.php`, `inc/footer-parts.php`
- Piège à éviter : les cinq emplacements n'ont pas la même place disponible. Le pied de page tient une phrase, la fiche produit deux lignes. Il faut donc un texte court **et** un texte long par promesse, pas un seul.

### B. Pied de page — **valeur : forte · coût : faible**

Titre et texte du bloc « Aide & support », bloc « Paiement à la livraison », mention de copyright. Contenu institutionnel, changé rarement mais qu'il faut pouvoir changer sans dev.

- Fichier : `inc/footer-parts.php`
- Les libellés de réseaux sociaux (Facebook, Instagram, TikTok, YouTube) sont des **noms propres** : les rendre éditables n'a pas de sens.

### C. Carte et bandeau WhatsApp de la boutique — **valeur : moyenne · coût : faible**

« Besoin d'aide ? », « Notre équipe vous répond sur WhatsApp et vous aide à choisir. », « Une question sur un produit ? ». Ce sont des arguments commerciaux, donc du contenu.

- Fichiers : `inc/shop.php`, `inc/product.php`

### D. Titres de sections de l'accueil — **valeur : moyenne · coût : faible**

« Parcourez nos catégories », « Nos nouveautés », « Pourquoi choisir FAMMA ? », « Ils nous font confiance », « Découvrir la boutique ».

- Fichier : `inc/home.php`

### E. Parcours de commande — **valeur : moyenne · coût : moyen · ⚠ sensible**

« Commande enregistrée. Nous vous contactons pour la confirmer. », « Le colis part une fois la commande confirmée. », « Vous payez en espèces au livreur, à la réception. », « À préparer en espèces ».

- Fichier : `functions.php`
- **Pourquoi c'est sensible** : ces phrases décrivent le fonctionnement réel du COD. Un texte mal réécrit ne casse pas la page — il fait attendre au client autre chose que ce qui va se passer, et c'est un litige à la livraison. Si ce bloc devient éditable, il faut un rappel explicite à l'écran : « ce texte décrit le déroulement réel de la commande ».

---

## 3. Ce qui doit rester dans le code

Recommandation ferme. Rendre ces chaînes éditables ajouterait du risque sans bénéfice.

| Catégorie | Exemples | Pourquoi |
|---|---|---|
| Libellés d'interface | « Filtres », « Réinitialiser », « Commander », « Nouveau », « En stock », « Ajouter aux favoris » | Ce sont des commandes, pas du discours. Un libellé de bouton réécrit à côté casse la compréhension du parcours. Ils sont déjà traduisibles par `.po`. |
| Textes d'accessibilité | « Catégories précédentes », « Visuel %d », « Commander : %s » | Lus par les lecteurs d'écran. Ils suivent des conventions ; les improviser dégrade l'accessibilité sans que personne ne le voie. |
| Noms propres | Facebook, Instagram, TikTok, YouTube, WhatsApp | Rien à personnaliser. |
| Libellés d'administration | « Boutique — filtres », « Sections de filtre affichées dans la carte… » | Jamais vus par un client. |

Autrement dit : **le discours devient éditable, la mécanique reste en code.** C'est la ligne que je recommande de tenir — « tout paramétrable » appliqué aux libellés de boutons produirait une boutique qu'un clic malheureux rend incompréhensible.

---

## 4. Séquence proposée

Chaque étape est livrable seule et vérifiable sur Local.

1. **Unifier la réassurance** (§2.A) — une source unique, cinq points de lecture, zéro champ admin pour l'instant. Rien ne change pour le client ; le site devient réparable en un endroit.
2. **Champs admin pour la réassurance** — s'appuie sur l'étape 1.
3. **Pied de page** (§2.B) et **titres de l'accueil** (§2.D) — les deux plus rentables au regard de l'effort.
4. **WhatsApp boutique et produit** (§2.C).
5. **Parcours de commande** (§2.E) — en dernier, avec le garde-fou décrit ci-dessus.

Les étapes 1 et 2 forment un tout : livrer la 1 sans la 2 laisse le code plus propre mais le propriétaire toujours dépendant d'un dev.

---

## 5. Ce que cet audit ne couvre pas

- **`famma-core`** : les libellés de statuts de commande, les colonnes d'administration et les intitulés du tableau de bord. Ils sont vus par le propriétaire, pas par le client — priorité faible, mais non recensés ici.
- **Les gabarits WooCommerce surchargés** (`woocommerce/`) : ils rejouent les hooks du cœur et leurs chaînes viennent souvent de WooCommerce lui-même. À traiter au cas par cas, pas en lot.
- **Les textes légaux** (CGV, mentions, retours) : ce sont des pages WordPress, déjà éditables. Ils restent à écrire par le propriétaire (§58 — aucun texte légal inventé).
