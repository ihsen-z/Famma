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

> **Mise à jour du 11/11 : champ ville** (décision 5 de `tasks/plan.md`, maquette fiche produit v3). Les tâches 3 à 5 seront réécrites après deux réponses du propriétaire : forme du gouvernorat (menu à recherche ou liste simple) et ville obligatoire ou non. Ce qui change déjà :
> - T3 : le gouvernorat et le texte « Ville, cité, rue… » partagent un cadre et un libellé « Adresse de livraison » ; le texte part dans `billing_address_1`.
> - T4 : la reconnaissance des villes (nomenclature INS) se fait dans le texte de l'adresse ; elle remplit le gouvernorat (flash + message « Gouvernorat ajouté : … Vous pouvez le changer. ») sans jamais remplacer un choix manuel.
> - T5 : téléphone avec « +216 » fixe, groupage « XX XXX XXX », `inputmode="numeric"`, `autocomplete="tel-national"` ; texte en 16 px, cibles de 48 px.
> - Messages d'erreur en FR + derja : « Choisissez le gouvernorat et écrivez la ville ou la cité. », « Choisissez votre gouvernorat. », « Écrivez la ville ou la cité. » (derja à relire par le propriétaire).
> - `form_error` reçoit un code de plus, `address`, pour le nouveau champ.

### Tâche 3 : Le gouvernorat tapé est reconnu à l'envoi et ne bloque plus

**Description :** au moment de l'envoi (bouton ou touche « Aller » du clavier) et à la sortie du champ, le texte tapé est reconnu :
- correspondance exacte en français ou en arabe ;
- variantes sans accents ni espaces en trop, avec « gouvernorat de » ou « ولاية » ;
- un seul candidat possible.

Si rien ne correspond, la liste s'ouvre avec les gouvernorats proches et un message court en FR et en derja, au lieu d'un refus sec. Le menu à recherche est conservé, comme décidé par le propriétaire.

**Critères d'acceptation :**
- [ ] « sousse », « Sousse », « سوسة » et « gouvernorat de Sousse », suivis directement de la touche « Aller » ou du bouton, donnent une commande envoyée.
- [ ] « Sous » suivi du bouton est reconnu (candidat unique) ; « xyz » ouvre la liste avec le message, sans envoi, et crée `form_error` `state`.
- [ ] Sans script ou avec l'ancien HTML en cache, le `<select>` natif fonctionne toujours.

**Vérification :**
- [ ] `node scripts/test-famma-express-v2.js`, avec un cas par critère
- [ ] Contrôle manuel en émulation mobile 390 px

**Dépendances :** tâche 1.

**Fichiers probables :**
- `P/assets/js/express-order.js` (`initCombo`, `validate`, gestion de l'envoi)
- `P/includes/express-order.php` (message `stateError` en FR + derja)
- `scripts/test-famma-express-v2.js`

**Taille :** S.

### Tâche 4 : Les villes et délégations mènent au bon gouvernorat

**Description :** les délégations de la nomenclature officielle de l'INS deviennent des mots-clés de recherche, en français et en arabe. Taper « Hammamet » propose « Nabeul (Hammamet) ». Les données vivent dans un fichier dédié, avec la source citée en tête, filtrable par `famma_governorate_places`. Les noms courants hors liste officielle sont proposés au propriétaire au point de contrôle 2, avant d'être ajoutés.

**Critères d'acceptation :**
- [ ] « Hammamet » → Nabeul, « Msaken » → Sousse, « جربة » → Médenine : chaque recherche montre la ville et son gouvernorat.
- [ ] Chaque entrée du fichier vient de la nomenclature officielle (source et date citées) ou d'une liste validée par le propriétaire.
- [ ] La fiche produit ne s'alourdit pas de plus de 15 Ko compressés.

**Vérification :**
- [ ] Tests Node sur un échantillon de délégations de chaque gouvernorat
- [ ] Contrôle croisé de la liste avec la source officielle

**Dépendances :** tâche 3.

**Fichiers probables :**
- `P/includes/governorate-places.php` (nouveau)
- `P/includes/express-order.php` (transmission des mots-clés au script)
- `P/assets/js/express-order.js` (recherche)
- `scripts/test-famma-express-v2.js`

**Taille :** M.

### Tâche 5 : Téléphone en premier et saisie adaptée au navigateur de Facebook

**Description :**
- Ordre des champs : téléphone, nom, gouvernorat.
- Attributs de saisie : `autocomplete` correct (`tel`, `name`), `enterkeyhint` (« suivant », puis « envoyer » sur le dernier champ), `autocapitalize="words"` sur le nom.
- La touche « Suivant » du clavier passe au champ suivant.
- Le champ actif défile au-dessus du clavier dans le navigateur intégré de Facebook.
- Les messages d'erreur du téléphone passent en FR + derja.

**Critères d'acceptation :**
- [ ] L'ordre de tabulation est téléphone → nom → gouvernorat → taille → bouton, et « Saisies à relancer » reçoit le téléphone même si le client s'arrête ensuite.
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
