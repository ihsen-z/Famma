# Tâches : formulaire de commande express optimisé pour le navigateur de Facebook

Plan : `tasks/plan.md`. Chemins relatifs à `famma-mennou-foundation/famma-mennou/` ; `P` = `wp-content/plugins/famma-core`.

Contrôles communs à chaque tâche (ClaudeMemo § 3 et § 11) :
- Context7 consulté avant d'écrire le code ;
- graphe interrogé avant de lire un fichier ;
- `php -l` sur chaque fichier PHP modifié ;
- `scripts/lint.sh` (phpcs WordPress) sans erreur ;
- `node scripts/test-famma-express-v2.js` vert.

---

## Phase 1 : mesurer les blocages

### Tâche 1 : Enregistrer chaque envoi refusé et le champ en cause

**Description :** chaque fois que le formulaire refuse un envoi, le journal des visites reçoit un événement interne `form_error` avec la liste des champs en cause. Cela couvre le contrôle local, la réponse de WooCommerce, le stockage indisponible et le réseau coupé. Les valeurs saisies ne sont jamais envoyées. Rien ne part vers Meta.

**Critères d'acceptation :**
- [x] Un envoi sans gouvernorat crée `form_error` avec `fields: ["state"]` ; un téléphone invalide donne `phone`, un stockage refusé `storage`, une coupure réseau `network`.
- [x] Le serveur n'accepte que les codes de la liste blanche (`phone`, `name`, `state`, `size`, `form`, `storage`, `network`, `server`, `changed`) ; toute autre valeur est ignorée. Ajoutés à l'implémentation : `form` (bulle native du navigateur) et `changed` (panier ou prix changé entre devis et commande).
- [x] Aucune valeur tapée par le client ne figure dans l'événement ni dans la base.

**Vérification :**
- [x] `node scripts/test-famma-express-v2.js` : 80/80, dont 11 nouveaux cas « Journal »
- [x] `node scripts/test-visitor-journal.js` (nouveau) : 4/4
- [x] `php scripts/test-visitor-collector-isolated.php` (nouveau) : 20/20, un code inconnu est rejeté et un lot sans code valable n'écrit rien
- [ ] Contrôle manuel en local : la chronologie d'une visite affiche le blocage (au point de contrôle 1)

**Dépendances :** aucune.

**Fichiers probables :**
- `P/assets/js/express-order.js` (émet un événement DOM `famma:express-error`)
- `P/assets/js/visitor-journal.js` (l'écoute et l'envoie)
- `P/includes/visitor-collector.php` (liste blanche)
- `scripts/test-famma-express-v2.js`

**Taille :** M.

### Tâche 2 : Entonnoir du formulaire dans le tableau de bord visiteurs

**Description :** un bloc « Formulaire de commande » dans le rapport des visites affiche, sur 7 et 30 jours et par produit : les visites qui ont commencé le formulaire, celles qui l'ont envoyé, les commandes, le pourcentage à chaque étape et les blocages par champ.

**Critères d'acceptation :**
- [x] Les chiffres correspondent au comptage direct du journal sur un jeu de données de test.
- [x] Le bloc n'est visible qu'avec la capacité `manage_woocommerce`, et toutes les sorties sont échappées.
- [x] Le calcul est mis en cache : aucune requête lourde à chaque affichage. Transient de 5 minutes, comme les autres agrégats du rapport (au lieu des 10 minutes prévues).

Fait : bloc « Formulaire express : de commencé à commandé » dans l'onglet Synthèse (`includes/visitor-form-funnel.php` et `-views.php`). Par produit : commencés, envoyés, commandes, sessions bloquées, envois refusés, bloquées puis commandées. Puis les refus par cause, avec les sessions restées sans commande. La période vient des boutons existants (1, 7, 30, 90, 180 jours).

**Vérification :**
- [x] `php scripts/test-visitor-form-funnel-isolated.php` (nouveau) : 18/18 sur des événements fictifs marqués « test »
- [ ] Requête SQL lancée sur la base MySQL locale : non faite, le site Local était arrêté (point de contrôle 1)
- [ ] Contrôle manuel dans l'admin locale avec `WP_DEBUG` actif, `debug.log` propre (point de contrôle 1)

**Dépendances :** tâche 1.

**Fichiers probables :**
- `P/includes/visitor-report.php`
- `P/includes/visitor-admin-views.php`
- `P/assets/css/visitor-admin.css`

**Taille :** M.

### Point de contrôle 1 : après les tâches 1 et 2
- [ ] Tous les tests verts, phpcs propre
- [ ] En local, un envoi sans gouvernorat apparaît dans le rapport avec le code `state`

---

## Phase 2 : corriger le formulaire

> **Mise à jour du 11/11 : champ ville** (décision 5 de `tasks/plan.md`, maquette fiche produit v3). Tâches 3 à 5 réécrites après les réponses du propriétaire : **liste simple** pour le gouvernorat, **ville facultative**. Phrases en derja à relire par le propriétaire au point de contrôle 2.

### Tâche 3 : Adresse de livraison : liste des gouvernorats et ville facultative, dans un même cadre

**Description :** le menu à recherche cède la place à la liste native des gouvernorats (option vide « Gouvernorat » / « الولاية »). Elle partage un cadre avec un texte facultatif « Ville, cité, rue… » / « المدينة، الحومة، النهج… », envoyé dans `billing_address_1`, sous un seul libellé « Adresse de livraison » / « عنوان التوصيل ». Plus aucun texte tapé ne peut rester « non choisi » et bloquer l'envoi. Message d'erreur précis en FR + derja : « Choisissez votre gouvernorat. »

**Critères d'acceptation :**
- [x] Gouvernorat choisi, ville vide → commande envoyée ; ville saisie → elle figure dans l'adresse de la commande (`billing_address_1`).
- [x] Gouvernorat vide → « Choisissez votre gouvernorat. », aucun appel réseau, `form_error` `state` ; la ville n'est jamais exigée.
- [x] Ancien HTML en cache (menu à recherche) avec le nouveau script, et nouveau HTML sans script : la commande passe dans les deux cas (`initCombo` gardé tant que le cache peut servir l'ancien gabarit).
- [x] Texte en 16 px, cibles de 48 px, propriétés CSS logiques : correct en FR et en arabe à 390 px.

Fait : `Express_Order::address_field()` remplace `governorate_field()`. L'aide sous le cadre est en une seule langue par site (français, ou derja par `famma-core-ar.po`) : mêlées, les deux écritures s'entrecroisaient sur 3 lignes. Traductions arabes ajoutées au `.po` ; le `.mo` sera recompilé à la tâche 8. À 416 px et plus, le téléphone reste seul sur sa ligne jusqu'à la tâche 5 (ordre des champs).

**Vérification :**
- [x] `node scripts/test-famma-express-v2.js` : 85/85, dont 5 nouveaux cas « Adresse » et « Gabarit »
- [x] Aperçu statique avec la vraie feuille de style à 390 px, FR et arabe (cadre, erreur, liste à droite en arabe)
- [ ] Contrôle sur la vraie fiche produit en local (point de contrôle 2)

**Dépendances :** tâche 1.

**Fichiers probables :**
- `P/includes/express-order.php` (`governorate_field()`, `render()`, messages)
- `P/assets/js/express-order.js` (`validate`, compatibilité ancien gabarit)
- `P/assets/css/express-order.css`
- `scripts/test-famma-express-v2.js`

**Taille :** M.

### Tâche 4 : La ville tapée remplit le gouvernorat

**Description :** les délégations de la liste officielle du Ministère de l'Intérieur (l'INS n'en publie pas), en français et en arabe, sont reconnues dans le texte de l'adresse. « Hammamet » choisit Nabeul dans la liste, avec un flash orange et « Gouvernorat ajouté : Nabeul. Vous pouvez le changer. » (FR + derja). Un gouvernorat choisi à la main n'est jamais remplacé. Les données vivent dans un fichier dédié, avec la source citée en tête, filtrable par `famma_governorate_places`. Les noms courants hors liste officielle sont proposés au propriétaire au point de contrôle 2, avant d'être ajoutés. La table de la maquette n'est pas reprise.

**Critères d'acceptation :**
- [x] « Hammamet » → Nabeul, « Msaken » → Sousse, « جربة » → Médenine, avec le message ; un choix manuel antérieur reste en place.
- [x] Chaque entrée du fichier vient de la liste officielle (source et date citées) ou d'une liste validée par le propriétaire.
- [x] La fiche produit ne s'alourdit pas de plus de 15 Ko compressés (3,6 Ko : 574 noms, 9,2 Ko non compressés).

**Vérification :**
- [x] Tests Node : chacun des 574 noms, tapé seul, donne son gouvernorat ou rien (97/97), plus orthographes, arabe, cas ambigus
- [x] Contrôle croisé de la liste avec la source officielle (264 paires FR/AR relues, nom arabe de chaque gouvernorat contrôlé)

> Fait le 11/11 :
> - Source : Ministère de l'Intérieur, données ouvertes « Délégations par gouvernorats de la République » (25/06/2013), 24 gouvernorats, 264 délégations. L'INS ne publie pas de liste des délégations. Les délégations créées depuis 2013 manquent ; elles s'ajoutent par le filtre `famma_governorate_places`.
> - Formes tirées des seuls noms officiels : sans « Nord/Sud/Ville… », premier mot propre à un seul gouvernorat (« Jerba »), localités des délégations composées (Sayada, Lamta, Bouhjar, Thrayat, Boumhel), article facultatif à partir de 5 lettres (« Marsa »).
> - Écartés : « La Médina / المدينة » (nom commun), « Azzouhour / الزهور » (Tunis et Kasserine), tout texte où deux gouvernorats arrivent au même rang (« rue de Tunis, Sfax »).
> - Coquille de la source gardée : « Zazis » pour Zarzis (جرجيس), à corriger par le filtre si le propriétaire le valide.
> - Message « Gouvernorat ajouté : %s. Vous pouvez le changer. », derja « زدنا الولاية: %s. تنجم تبدّلها. » à relire au point de contrôle 2.

**Dépendances :** tâche 3.

**Fichiers probables :**
- `P/includes/governorate-places.php` (nouveau)
- `P/includes/express-order.php` (transmission des mots-clés au script)
- `P/assets/js/express-order.js` (recherche)
- `scripts/test-famma-express-v2.js`

**Taille :** M.

### Tâche 5 : Téléphone en premier et saisie adaptée au navigateur de Facebook

**Description :**
- Ordre des champs : téléphone, nom, adresse de livraison (gouvernorat et ville).
- Téléphone comme la maquette v3 : « +216 » affiché fixe hors de la valeur, numéro groupé « XX XXX XXX » à la frappe, +216 ou 00216 collé retiré, `inputmode="numeric"`, même règle `^[2-579]\d{7}$`.
- Attributs de saisie : `autocomplete` correct (`tel-national`, `name`, `address-level1`, `address-line1`), `enterkeyhint` (« suivant », puis « envoyer » sur le dernier champ), `autocapitalize="words"` sur le nom.
- La touche « Suivant » du clavier passe au champ suivant.
- Le champ actif défile au-dessus du clavier dans le navigateur intégré de Facebook.
- Les messages d'erreur du téléphone passent en FR + derja.

**Critères d'acceptation :**
- [ ] L'ordre de tabulation est téléphone → nom → gouvernorat → ville → taille → bouton, et « Saisies à relancer » reçoit le téléphone même si le client s'arrête ensuite.
- [ ] En émulation 390 px avec l'agent utilisateur du navigateur Facebook, le champ actif reste visible quand le clavier est ouvert.
- [ ] Un numéro collé avec +216, des espaces ou des chiffres arabes est accepté (comportement actuel conservé).

**Vérification :**
- [ ] `node scripts/test-famma-express-v2.js` vert, ordre des champs testé
- [ ] Contrôle manuel en émulation mobile

**Dépendances :** tâche 3 (mêmes fichiers).

**Fichiers probables :**
- `P/includes/express-order.php`
- `P/assets/js/express-order.js`
- `P/assets/css/express-order.css`
- `scripts/test-famma-express-v2.js`

**Taille :** M.

### Tâche 6 : Phrase rassurante sous le téléphone, modifiable dans l'admin

**Description :** un nouveau texte en FR et en derja dans FAMMA → Pages → Fiche produit (`Product_Content`), affiché sous le téléphone s'il n'est pas vide. Claude propose une version ; le propriétaire la valide ou la réécrit.

**Critères d'acceptation :**
- [ ] Texte vide : rien n'est affiché sous le téléphone.
- [ ] Texte saisi : il est affiché échappé, relié au champ par `aria-describedby`, correct en arabe (propriétés CSS logiques).
- [ ] Le texte se modifie depuis l'admin sans toucher au code.

**Vérification :**
- [ ] Contrôle manuel dans l'admin et sur la fiche, en FR et en RTL
- [ ] phpcs propre

**Dépendances :** tâche 5.

**Fichiers probables :**
- `P/includes/product-content.php`
- `P/includes/express-order.php`
- `P/assets/css/express-order.css`

**Taille :** S.

### Point de contrôle 2 : après les tâches 3 à 6
- [ ] Parcours complet en local, en émulation mobile 390 px avec l'agent utilisateur du navigateur Facebook : téléphone, nom, « Hammamet », taille, commande créée
- [ ] Revue du propriétaire : ordre des champs, texte de la phrase, noms courants des villes

---

## Phase 3 : fiabiliser la mesure et livrer

### Tâche 7 : Chaque commande express est rattachée à sa visite, même sans cookie

**Description :** 8 des 40 commandes du site depuis le 17/10 n'ont pas de session de visite, probablement à cause des cookies du navigateur de Facebook. Le formulaire transmet l'identifiant de session du journal dans un champ caché. Le serveur ne l'utilise que si le cookie manque, et après en avoir contrôlé le format.

**Critères d'acceptation :**
- [ ] Une commande passée sans cookie de journal, mais avec le champ, reçoit `_famma_visitor_session`.
- [ ] Un identifiant mal formé est ignoré ; le cookie, quand il existe, reste prioritaire.
- [ ] Aucun changement dans les événements Pixel ou CAPI.

**Vérification :**
- [ ] `FAMMA_EXPRESS_INTEGRATION=1` : `scripts/test-express-integration.php` dans la base d'intégration
- [ ] `node scripts/test-famma-express-v2.js`

**Dépendances :** tâche 1.

**Fichiers probables :**
- `P/assets/js/visitor-journal.js`
- `P/assets/js/express-order.js`
- `P/includes/visitor-commerce.php`
- `scripts/test-express-integration.php`

**Taille :** M.

### Tâche 8 : Version 0.3.9, traductions, contrôles du projet et paquet de mise en ligne

**Description :** cette tâche prépare la livraison, sans rien mettre en ligne :
- passer en version 0.3.9 et compléter le CHANGELOG ;
- traduire les nouvelles chaînes en FR et en arabe (`.po`, puis `.mo` par `scripts/po-to-mo.php`) ;
- passer tous les contrôles du § 11 ;
- préparer le paquet de mise en ligne : zip sans entrées de dossier, zip de retour arrière, `CONTROLE.txt` avec les SHA, `MARCHE-A-SUIVRE.md`.

Rien ne part en production sans le feu vert explicite du propriétaire.

**Critères d'acceptation :**
- [ ] `php -l` et phpcs WordPress propres ; `WP_DEBUG` actif et `debug.log` sans notice, warning ni deprecated.
- [ ] Parcours COD complet en local : commande express, puis confirmée, expédiée, livrée ; « livrée » marque la commande payée et envoie le Purchase une seule fois.
- [ ] Le paquet contient exactement les fichiers modifiés, avec leurs SHA, et un retour arrière vers 0.3.8.

**Vérification :**
- [ ] Tous les tests Node, PHP et d'intégration
- [ ] Liste des fichiers du zip comparée à `git diff --name-only origin/main`

**Dépendances :** tâches 1 à 7.

**Fichiers probables :**
- `P/famma-core.php` (version)
- `P/CHANGELOG.md`
- `P/languages/famma-core-fr_FR.po`, `famma-core-ar.po` (et leurs `.mo`)
- `deploy-lot23-formulaire/` (ignoré par git)

**Taille :** M.

### Point de contrôle 3 : prêt à mettre en ligne
- [ ] Tous les critères des tâches 1 à 8 remplis
- [ ] Feu vert du propriétaire, puis mise en ligne : SHA vérifiés en production avant et après, `~/.lsphp_restart.txt`, purge LiteSpeed, une commande test depuis l'appli Facebook du propriétaire, annulée ensuite
- [ ] À J+7 : formulaire commencé → commande ≥ 60 %, blocages `state` proches de 0, visite → commande ≥ 2,5 %
