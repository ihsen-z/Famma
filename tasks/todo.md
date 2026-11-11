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
> - **Noms courants refusés par le propriétaire (11/11)** : la proposition (Sahloul, Khezama, Chott Meriem, Kantaoui → Sousse ; Skanès → Monastir ; Mrezga → Nabeul ; Ennasr → Ariana ; Lac 1, Lac 2, El Manar → Tunis) n'est pas acceptée. Aucun nom hors liste officielle n'est ajouté ; ne pas la reproposer.
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
- Téléphone comme la maquette v3 : « +216 » affiché fixe hors de la valeur, numéro groupé « XX XXX XXX » à la frappe, +216 ou 00216 collé retiré, `inputmode="numeric"`, même règle que le serveur (`Tunisia::normalize_phone()`, `^[2-9]\d{7}$`).
- Attributs de saisie : `autocomplete` correct (`tel-national`, `name`, `address-level1`, `address-line1`), `enterkeyhint` (« suivant », puis « envoyer » sur le dernier champ), `autocapitalize="words"` sur le nom.
- La touche « Suivant » du clavier passe au champ suivant.
- Le champ actif défile au-dessus du clavier dans le navigateur intégré de Facebook.
- Les messages d'erreur du téléphone passent en FR + derja.

**Critères d'acceptation :**
- [x] L'ordre de tabulation est téléphone → nom → gouvernorat → ville → taille → bouton, et « Saisies à relancer » reçoit le téléphone même si le client s'arrête ensuite.
- [ ] En émulation 390 px avec l'agent utilisateur du navigateur Facebook, le champ actif reste visible quand le clavier est ouvert. *(code et test unitaire faits ; le clavier ne s'émule pas dans un navigateur de bureau : à voir sur un vrai téléphone au point de contrôle 2)*
- [x] Un numéro collé avec +216, des espaces ou des chiffres arabes est accepté (comportement actuel conservé).

**Vérification :**
- [x] `node scripts/test-famma-express-v2.js` vert (104/104), ordre des champs testé ; `node scripts/test-visitor-journal.js` 5/5
- [ ] Contrôle manuel en émulation mobile *(aperçu statique 390 px FR et arabe fait le 11/11 ; clavier réel au point de contrôle 2)*

> Fait le 11/11 :
> - Gabarit : `Express_Order::phone_field()` (cadre `dir="ltr"`, « +216 » en `aria-hidden`, `tel-national`, `inputmode="numeric"`, `enterkeyhint="next"`), nom avec `autocapitalize="words"` et `enterkeyhint="next"`, ville avec `enterkeyhint="send"`. Tous les champs en pleine largeur.
> - Script : `formatPhone()` groupe « XX XXX XXX » (curseur conservé, espace effacée = chiffre d'avant effacé, +216/00216 collé retiré, plus de 8 chiffres laissés tels quels) ; touche « Suivant » vers le champ à taper suivant, la liste des gouvernorats est sautée (la mettre au point ferme le clavier sans l'ouvrir) ; `keepFieldVisible()` remonte au tiers de la zone visible un champ caché par le clavier (`visualViewport`).
> - Messages : celui du formulaire était déjà FR + derja ; celui du serveur sur le site français passe de l'arabe classique à la derja du site arabe (`famma-core-fr_FR.po`, .mo à recompiler en T8).

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
- [x] Texte vide : rien n'est affiché sous le téléphone.
- [x] Texte saisi : il est affiché échappé, relié au champ par `aria-describedby`, correct en arabe (propriétés CSS logiques).
- [x] Le texte se modifie depuis l'admin sans toucher au code.

**Vérification :**
- [ ] Contrôle manuel dans l'admin et sur la fiche, en FR et en RTL *(aperçu statique 390 px FR et arabe fait le 11/11 ; écran d'admin à voir sur le site local au point de contrôle 2)*
- [x] phpcs propre

> Fait le 11/11 :
> - Champ `reassure_phone` dans FAMMA → Pages → Fiche produit (schéma de `Product_Content`, écran et assainissement de `Pages_Content`).
> - Proposition par défaut, à valider par le propriétaire : « Nous vous appelons sur ce numéro pour confirmer la commande avant l’envoi. » / « نكلموك على النمرة هذي باش نأكدو الطلبية قبل ما نبعثوها. ». Même promesse que « Nos engagements » de la page À propos (commande confirmée par téléphone avant l'expédition).
> - Affichage : sous l'erreur du téléphone ; derja en seconde ligne sur le site français. À 390 px : 4 lignes sur le site français, 2 sur le site arabe.
> - `aria-describedby` = erreur puis phrase ; le script ne lit plus que le premier identifiant pour trouver l'erreur.
> - Tests : `scripts/test-express-phone-note-isolated.php` 12/12, front 105/105.

**Dépendances :** tâche 5.

**Fichiers probables :**
- `P/includes/product-content.php`
- `P/includes/express-order.php`
- `P/assets/css/express-order.css`

**Taille :** S.

### Point de contrôle 2 : après les tâches 3 à 6
- [ ] Parcours complet en local, en émulation mobile 390 px avec l'agent utilisateur du navigateur Facebook : téléphone, nom, « Hammamet », taille, commande créée
- [ ] Revue du propriétaire : ordre des champs, texte de la phrase (noms courants des villes : refusés le 11/11, liste officielle seule)

---

## Phase 3 : fiabiliser la mesure et livrer

### Tâche 7 : Chaque commande express est rattachée à sa visite, même sans cookie

**Description :** 8 des 40 commandes du site depuis le 17/10 n'ont pas de session de visite, probablement à cause des cookies du navigateur de Facebook. Le formulaire transmet l'identifiant de session du journal dans un champ caché. Le serveur ne l'utilise que si le cookie manque, et après en avoir contrôlé le format.

**Critères d'acceptation :**
- [x] Une commande passée sans cookie de journal, mais avec le champ, reçoit `_famma_visitor_session`.
- [x] Un identifiant mal formé est ignoré ; le cookie, quand il existe, reste prioritaire.
- [x] Aucun changement dans les événements Pixel ou CAPI (aucun fichier de suivi touché).

**Vérification :**
- [ ] `FAMMA_EXPRESS_INTEGRATION=1` : `scripts/test-express-integration.php` dans la base d'intégration *(non lancé : il exige le clone Docker `famma_integration` ; remplacé pour l'instant par `scripts/test-visitor-commerce-isolated.php`, 13/13, sur la vraie classe)*
- [x] `node scripts/test-famma-express-v2.js` (106/106) et `node scripts/test-visitor-journal.js` (7/7)

> Fait le 11/11 :
> - Gabarit : champs cachés `famma_vj_visitor` / `famma_vj_session` (constantes `Visitor_Commerce::FIELD_*`), vides dans la page en cache.
> - `visitor-journal.js` : `stampForm()` les remplit au chargement et au premier champ touché, avec les mêmes identifiants que ses envois.
> - `Visitor_Commerce::ids()` : cookie d'abord ; un cookie absent est remplacé par le champ, lu seulement après `woocommerce_checkout_process` (jeton vérifié par WooCommerce) et gardé seulement s'il a la forme d'un UUID v4. `order_placed` entre donc aussi dans la session : l'entonnoir de la tâche 2 compte ces commandes.
> - Les champs partent avec l'envoi final (figés dans le registre anti-double commande), pas avec le devis.

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
- [ ] `php -l` et phpcs WordPress propres ; `WP_DEBUG` actif et `debug.log` sans notice, warning ni deprecated. *(`php -l` 16 fichiers et phpcs 11 fichiers du plugin : propres ; `debug.log` : site local arrêté le 11/11, à faire)*
- [ ] Parcours COD complet en local : commande express, puis confirmée, expédiée, livrée ; « livrée » marque la commande payée et envoie le Purchase une seule fois. *(site local arrêté, à faire)*
- [x] Le paquet contient exactement les fichiers modifiés, avec leurs SHA, et un retour arrière vers 0.3.8.

**Vérification :**
- [ ] Tous les tests Node, PHP et d'intégration *(Node et PHP isolés verts : 106, 7, 20, 18, 21, 12, 13, CAPI 54 ; intégration Docker non lancée)*
- [x] Liste des fichiers du zip comparée à `git diff --name-only 3e34084` (= origin/main = prod 0.3.8) : identique, contrôlée par `build.py`

> Fait le 11/11 :
> - Version 0.3.9, CHANGELOG, `.mo` recompilés (23a5881). Les `.mo` de la 0.3.8 correspondaient exactement à leurs `.po` : seules les chaînes du lot changent.
> - Paquet `deploy-lot23-formulaire/` (ignoré par git) : zip de 19 fichiers (SHA 9e1c003b…), retour arrière de 15 fichiers tirés de 3e34084 (SHA 02551c3e…), `CONTROLE.txt`, `famma-core.php.diff.txt`, `build.py`, `MARCHE-A-SUIVRE.md` (purge LiteSpeed obligatoire ; commande d'essai = décision du propriétaire, car elle envoie un OrderPlaced).
> - Aucune modification de base : `form_error` tient dans `event varchar(32)`.

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
