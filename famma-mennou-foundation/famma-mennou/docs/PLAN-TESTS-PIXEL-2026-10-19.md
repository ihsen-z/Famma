# Plan d'audit et de tests du pixel — FAMMA MINNOU

> Rédigé le 19/10/2026 après une exploration en lecture seule par 4 experts (paramétrage Meta, production WordPress, revue du code, infrastructure de test), en plus de l'audit du 18/10.
> **Rien n'a encore été testé ni modifié.** Ce plan attend la validation du propriétaire.
> Version auditée : famma-core 0.3.1 (identique en prod et dans la branche `claude/pixel-plugin-refactor-cec7c4`).
> **Mises à jour du 19/10 :** la veille Meta (S5) et la résilience aux futures mises à jour de Meta (S6) sont intégrées, et la stratégie retenue (section 0) intègre trois décisions : exclure les visites internes, rendre la version Graph réglable, partager les identifiants. Elle prévoit aussi la voie catalogue. Ces décisions sont mises en œuvre au lot 5, entre les tests de référence et les tests en prod.

---

## 0. Stratégie retenue

On **garde famma-core** comme seule source du suivi : pas de plugin officiel Meta, puisque ces plugins envoient Purchase dès la commande et non à la livraison. On lui ajoute les trois bonnes idées des plugins officiels.

### S1 — Exclure les visites internes (admin et équipe)

- **Une seule règle, `Tracking::is_internal_visitor()`** : est interne un utilisateur connecté qui a `manage_options` ou `manage_woocommerce`. C'est la même règle que le mu-plugin `famma-google-tags.php`, donc GA4 et Meta excluent les mêmes personnes. La règle reste réglable par le filtre `famma_tracking_exclude_visitor`.
- **Ce qui est coupé pour un visiteur interne :**
  - le `fbq('init')` et tous les événements du navigateur (Tracking, Tracking_Events) ;
  - le bloc `tracking` de la commande express, renvoyé vide ;
  - TikTok ;
  - le script `famma-fbc`.
- **Ce qui n'est jamais coupé :**
  - les envois serveur OrderPlaced et Purchase, que l'admin déclenche pour le compte d'un client (par exemple en passant une commande à « Livrée ») ;
  - les données du client enregistrées sur sa commande.
- **Dérogation pour les tests** : un réglage « Suivre mes visites (mode test) », ou la constante `FAMMA_TRACK_INTERNAL`, permet au propriétaire d'apparaître dans Test Events (scénario T6.1).
- **Point de vigilance** : LiteSpeed met en cache les pages des utilisateurs connectés (variante privée, 1 800 s). Une variante « admin sans pixel » ne doit jamais être servie à un visiteur.

### S2 — Version de l'API Graph réglable

- **Un seul accesseur, `Config::meta_graph_version()`**, qui cherche dans cet ordre :
  1. la constante `FAMMA_META_GRAPH_VERSION` ;
  2. l'option `famma_meta_graph_version`, avec un champ dans l'écran « Mesure » qui n'accepte que le format `vNN.N` ;
  3. la valeur par défaut du code.
- Le filtre `famma_capi_graph_version` est conservé. Les deux `'v21.0'` écrits en dur disparaissent (famma-core.php:1936 et tracking-settings.php:429) : les vrais envois et le bouton « Test CAPI » utilisent la même version.
- **Valeur par défaut : v26.0**, la dernière version, sortie le 29/07/2026, à revérifier le jour du correctif.
  - L'API Conversions suit le calendrier de l'**API Graph**, et non celui de la Marketing API. La doc Meta le dit : « Our release cycle is aligned with the Graph API, so every version is supported for at least two years ».
  - **v21.0 prend fin le 21/01/2027.** Après cette date, un appel en v21.0 risque une erreur franche plutôt qu'une mise à niveau automatique : en 2021, Meta écrivait que la CAPI renvoie une erreur sans mise à niveau, et le comportement de 2027 n'est pas documenté. OrderPlaced et Purchase seraient alors rejetés. **C'est une échéance ferme.**
- **Surveillance** : on journalise l'en-tête `facebook-api-version` renvoyé par Meta, et le chien de garde alerte s'il diffère de la version demandée.

### S3 — Identifiants partagés

**a) Identifiants d'événement (event_id)**, fabriqués par une seule classe PHP `Event_Ids` et transmis au JavaScript :

| Événement | Source de l'identifiant | Côtés |
|---|---|---|
| PageView, ViewContent | Généré **dans le navigateur**, ce qui règle le problème du cache LiteSpeed. | navigateur |
| AddToCart / InitiateCheckout (express) | `famma_add_<prep>` / `famma_checkout_<prep>`, créés côté serveur dans une réponse AJAX qui n'est pas mise en cache. Inchangé. | navigateur |
| OrderPlaced | `famma_orderplaced_<id>`. **Serveur par défaut.** L'envoi navigateur avec le même identifiant est codé, mais reste **coupé par défaut** derrière un interrupteur. La raison : depuis la doc du 12/02/2026, quand les deux versions arrivent à moins de 5 minutes d'écart, Meta garde celle du navigateur. Or elle est moins riche (pas de téléphone haché, de gouvernorat, etc.) et pourrait faire baisser l'EMQ. On ne l'active que si le test T6.6 prouve le contraire. | serveur (+ navigateur en option) |
| Purchase | `famma_purchase_<id>`. **Reste côté serveur seulement** : à la livraison, le client n'est plus sur le site. C'est voulu et documenté. | serveur |

- Le format `famma_<événement>_<n° de commande>` est **conservé**, car c'est un invariant (registre d'envoi, commandes déjà en file).
- La protection contre les copies locales (H-S9) passe par une **garde d'environnement** plutôt que par un nouveau format : si `wp_get_environment_type()` n'est pas `production`, aucun envoi réel ne part vers Meta.

**b) Identifiant produit (`content_id`)** : une seule fonction, utilisée par le navigateur, l'express, la CAPI et le futur flux catalogue. Règle proposée, à valider :
1. le SKU s'il existe ;
2. sinon l'ID de la variation (la taille) ;
3. sinon l'ID du produit.

Les tailles sont regroupées par `item_group_id` (ID du produit parent).

### S4 — Pubs catalogue : plus tard, hors de cette campagne

- **Voie retenue : un flux produit.** famma-core génère un fichier CSV ou XML dans `uploads/`, régénéré chaque jour et à chaque enregistrement de produit, que Meta relit à intervalles fixes (flux planifié dans Commerce Manager). Champs : id (= le même `content_id` que S3-b), titre, description, disponibilité, état, prix en TND, lien, image, marque, `item_group_id`.
- **Prérequis** : S3-b. Sans le même identifiant entre le pixel et le catalogue, les pubs catalogue ne peuvent pas relier les visiteurs aux produits.
- **Plan B, vérifié dans son code** : Meta for WooCommerce peut servir au catalogue seul. Le filtre `facebook_for_woocommerce_integration_pixel_enabled`, mis à `false` depuis famma-core, coupe tout son suivi d'événements, Purchase compris. Les envois « magasin physique » (offline purchase) doivent alors rester désactivés.

  Je le déconseille quand même :
  - charge processeur à la synchronisation ;
  - note de 2,1/5 ;
  - le plugin peut être désactivé sans prévenir, comme Site Kit.
- **Point d'attention** : la catégorie « santé » de Meta (H-M1) peut aussi restreindre les pubs catalogue. À vérifier avant de construire le flux.

### S5 — Évolutions de Meta et effets sur notre code (veille du 19/10/2026)

Deux experts ont relu la doc et les annonces de Meta. Chaque point a ensuite été recoupé avec notre code.

**Légende de la colonne « Certitude »** :
- **V** : vérifié dans la doc Meta, ou dans notre code / celui du pixel ;
- **D** : déduit ;
- **T** : source tierce.

Les rapports complets, avec leurs sources, sont dans `audit-preuves/2026-10-19-pixel/veille/`.

| # | Évolution Meta | Date | Certitude | Effet sur FAMMA | Réponse | Urgence |
|---|---|---|---|---|---|---|
| E1 | L'API Conversions suit le calendrier de l'API Graph ; **v21.0 prend fin le 21/01/2027** (v26.0 est la dernière version) | 21/01/2027 | V | Nos 2 appels en `v21.0` écrit en dur risquent l'erreur, donc OrderPlaced et Purchase perdus | **S2**, valeur v26.0 ; en-tête de version journalisé, chien de garde | **Haute (échéance ferme)** |
| E2 | « Configuration principale » (Core Setup) : supprime **tous les paramètres perso** et **tout ce qui suit le domaine dans les URL**, y compris dans l'outil de test. Elle peut être imposée par Meta (catégorie de domaine, paramètres bloqués répétés : au moins 90 jours) | En vigueur | V (mécanisme), D (cas FAMMA) | Explique `famma_stage` absent, la bannière « _removed_ » et la conversion perso morte. Les audiences fondées sur une URL (`/produit/…`) ne marchent pas | Plus **aucun signal ne doit dépendre d'un paramètre perso ni d'un chemin d'URL**. `famma_stage` est gardé mais déclaré inutile. Audiences fondées sur les événements standards. Propriétaire : conversion perso refaite sur le **nom d'événement** OrderPlaced | **Critique** |
| E3 | Catégories sensibles « santé et bien-être » : 3 niveaux (Core Setup, restriction des événements standards du milieu et du bas de tunnel, restriction totale) | Depuis 2025 | V (mécanisme), D (ceinture lombaire) | Si Meta classe le domaine, Purchase, AddToCart et InitiateCheckout peuvent devenir inutilisables pour optimiser | Pas de vocabulaire médical dans les noms et paramètres envoyés (`content_name`, `content_category`) : filtre `famma_meta_content_name` pour neutraliser ou omettre. Propriétaire : vérifier la catégorie attribuée et demander un examen | Haute |
| E4 | Un événement perso n'est utilisable par les campagnes que s'il est **confirmé** (« Gérer le blocage des événements ») | En vigueur | V | Les 2 ensembles OLYMPE qui optimisent sur OrderPlaced en dépendent | Propriétaire : vérifier ou confirmer OrderPlaced. Garder le nom `OrderPlaced`, sans aucun mot de santé | Haute |
| E5 | **Les événements envoyés avec `test_event_code` ne sont PAS écartés : ils comptent** pour le ciblage et la mesure (doc Using the API) | En vigueur | V | La commande d'essai du lot 6 comptera comme une vraie conversion. Le « mode test » n'isole rien, il rend seulement visible | Lot 6 corrigé : ensembles OLYMPE en pause pendant l'essai, une seule commande, notée « ESSAI » dans le rapport | Haute |
| E6 | Déduplication précisée : clé `event_name` + `event_id` sur 48 h ; **à moins de 5 minutes d'écart, l'événement navigateur est préféré** | Doc du 12/02/2026 | V | Un OrderPlaced envoyé aussi par le navigateur pourrait remplacer la version serveur, plus riche | **S3-a révisé** : OrderPlaced côté navigateur coupé par défaut, testé en T6.6 | Moyenne |
| E7 | `event_time` limité à 7 jours, sinon **toute la requête est rejetée** | En vigueur | V | Purchase daté de la livraison : il passe. Un rejeu tardif (H-S6) serait rejeté. Une livraison plus de 7 jours après le clic sort de la fenêtre « 7 jours clic » | Tests T2.8 et T1.5 ; pas de rejeu au-delà de 6 jours, avec une ligne de journal explicite | Moyenne |
| E8 | Config du pixel servie : `IABPCMAEBridge enableAutoEventId:true`. Dans le navigateur intégré de Facebook et d'Instagram, Meta ajoute un `eid=pcm_plugin-set_…` à tout événement sans eventID | Constaté le 19/10 | V | **Explique les 55 % d'event_id sur PageView** (visites depuis l'app Facebook) : il n'y a pas de second émetteur. H-M4 est réfutée côté émetteur | Nos propres eventID navigateur (S3-a) sont prioritaires ; à vérifier en T4.7 | Basse |
| E9 | Pixel « IA » (Détails de page automatiques), activé 30 jours après notification ; `automaticMatching`, `microdata` et `inferredevents` actifs | 15/04/2026 | V | Meta lit lui-même le contenu de la page : il peut remonter du vocabulaire santé (aggravant E3) et des paramètres que nous ne maîtrisons pas | Propriétaire : envisager de couper « Détails de page automatiques (IA) ». Test T4.7 : quels paramètres partent dans `/tr` | Moyenne |
| E10 | CAPI « Meta-enabled » en un clic | 15/04/2026 | V | L'activer **en plus** de notre CAPI doublerait les événements serveur | Propriétaire : **ne jamais l'activer** ; vérifier que « Connexion en attente » n'est pas une configuration de ce type laissée en cours | Haute |
| E11 | `external_id` recommandé (Meta annonce +17 % d'EMQ), même valeur côté pixel et côté serveur | En vigueur | V | Nous n'en envoyons pas, alors que le parcours visiteurs a déjà un identifiant first-party (`famma_vid`, méta `_famma_visitor_id`) | Candidat S6 : `external_id` = hachage de l'identifiant visiteur, **après** la décision sur le consentement (E13) | Moyenne |
| E12 | Navigateurs : iOS 17+ (Link Tracking Protection) et Firefox strict retirent `fbclid` ; ITP limite à 24 h un cookie posé en JavaScript après une arrivée « décorée » ; les cookies tiers de Chrome n'ont pas d'effet | 2019-2025 | V/T | `_fbc` absent ou court sur iPhone. Notre `_fbc` est posé en JavaScript (`capi-signals.php:150`). Il est bien recopié sur la commande, donc Purchase n'est pas touché (vérifié : `famma-core.php:2104-2131` lit la méta, pas la session admin) | Mesurer d'abord (T6.7, iPhone). Option ensuite : poser `_fbc` par le serveur via un appel non mis en cache (LiteSpeed ignore `fbclid` dans sa clé de cache, donc PHP ne voit pas le fbclid sur une page en cache) | Moyenne |
| E13 | Conditions des outils professionnels Meta (03/11/2025) : §3.c avis sur chaque page où tourne le pixel, avec moyen de refus ; §3.d consentement là où la loi l'exige. **Loi tunisienne 2004-63** : consentement exprès (art. 27), déclaration à l'INPDP (art. 7), autorisation pour un transfert à l'étranger (art. 52) | 03/11/2025 ; 2004 | V (textes), D (application à FAMMA) | Aucun avis ni bandeau aujourd'hui : non conforme au §3.c, et probablement à la loi tunisienne | **Décision du propriétaire, avec avis juridique.** Côté code, préparer `fbq('consent','revoke'/'grant')` et le respect du refus dans la CAPI. Textes fournis ou validés par le propriétaire | Haute (décision) |
| E14 | Attribution : fenêtres « vue 7 et 28 jours » retirées (12/01/2026) ; le « clic » ne compte plus que les clics sur lien ; « engagement 1 jour » ajouté | 12/01/2026, 03/2026 | V | Aucun effet sur le code. Lecture OLYMPE : un Purchase livré à J+2 ou J+3 remonte tard | Décider sur OrderPlaced et juger sur Purchase après le délai de livraison (méthode OLYMPE) | Basse |
| E15 | Apprentissage : ~50 événements d'optimisation par semaine et par ensemble ; l'optimisation de la valeur demande 100 conversions avec 5 valeurs distinctes sur 14 jours | En vigueur | V | 17 OrderPlaced sur 28 jours : « apprentissage limité » probable | Côté code : déjà `value` et `currency` sur OrderPlaced. Côté campagnes : regrouper les ensembles (méthode OLYMPE) | Moyenne |
| E16 | Fin de la mesure agrégée (AEM) : plus de priorisation, l'écran a disparu ; la vérification de domaine n'est plus requise pour configurer les événements, mais `event_source_url` « should match the verified domain » | 2025 | V | Aucun effet sur le code | Propriétaire : vérifier quand même fammaminnou.tn (TXT DNS, comme pour Google) | Moyenne |
| E17 | Paramètres `debug`, `pretty`, `date_format`, `?ids=` et ETag supprimés sur toutes les versions | 27/10/2026 | V | **Aucun effet** : vérifié, notre code n'en utilise aucun (seul le `debug` de la liste des méthodes TikTok apparaît) | Rien | — |
| E18 | Dataset Quality API (EMQ, ACR, couverture, déduplication, fraîcheur) | 28/05/2025 | V | Permet une surveillance automatique de la qualité | Chien de garde : lecture périodique de la qualité, avec alerte si l'EMQ baisse ou si un diagnostic apparaît | Basse |

**Ce que la veille change dans la stratégie :**
1. **S2 devient la priorité n° 1 du lot 5**, à cause de l'échéance ferme du 21/01/2027.
2. **S3-a est révisé** : OrderPlaced reste serveur par défaut, à cause de la préférence de Meta pour l'événement navigateur.
3. **Le principe « jamais de signal dans un paramètre perso ou une URL »** devient un invariant (E2).
4. **Le lot 6 est corrigé** : les événements de test comptent (E5).
5. **Nouvelles décisions pour le propriétaire** : consentement (E13), catégorie de domaine et confirmation d'OrderPlaced (E3, E4), CAPI « Meta-enabled » jamais activée (E10), pixel « IA » (E9).
6. **Candidats pour après la campagne** : S6 (`external_id`, cookie `_fbc` posé par le serveur) et surveillance de la qualité dans le chien de garde.

### S6 — Résilience aux futures mises à jour de Meta

**Principe.** Fixer une version de l'API ne suffit pas. La plupart des changements récents de Meta (E2, E3, E5, E6, E9, E10) sont arrivés hors du calendrier des versions, et aucun n'a produit d'erreur dans les réponses CAPI. On ajoute donc à famma-core, sans 2e plugin, quatre couches légères : anticiper, absorber par un réglage, détecter, limiter l'impact. Coût : environ 6 jours de développement et moins de 30 min par mois pour le propriétaire.

**Révisé après la red team :**
- retirés : le rapport de ventes (R14), l'option « date de fin » avec 4 paliers d'avis, le champ permanent « version candidate », le profil « complet » dans l'admin, les 11 classes de réponse, les compteurs d'e-mails, la sonde quotidienne, la règle « InitiateCheckout ≥ 8 », qui ne se déclenchait jamais, et la règle « 2 administrateurs » ;
- ajoutés : un rapprochement fondé sur une règle indépendante de l'émetteur, la surveillance des pages de doc, le profil appliqué aussi au navigateur, et la vérification du domaine dès maintenant.

#### Couche 1 — Anticiper

| ID | Mécanisme | Protège contre | Où | Effort | Prio |
|---|---|---|---|---|---|
| R1 | **Échéancier de version.** Config connaît la date de fin publiée de chaque version : un seul avertissement à J-60. Un filet codé affiche un bandeau critique permanent si la version vaut v21.0 ou moins après le 01/12/2026. Les dates d'agenda sont dans le calendrier ci-dessous. | E1, oubli d'une échéance | Config, écran « Mesure », agenda | 0,15 j | P0 |
| R2 | **Routine Claude en lecture seule.** **Chaque mois**, avec un rapport toujours notifié, même au vert : changelogs Graph et Marketing ; date « Updated » de 5 pages de doc CAPI ; configuration publique du pixel (toute clé nouvelle) ; réglages et qualité du jeu de données ; conformité de l'app ; conditions des outils professionnels ; loi tunisienne ; attribution des ensembles OLYMPE ; Purchase reçus comparés aux commandes livrées. **Chaque semaine, seulement quand un ensemble dépense**, alerte si : aucun OrderPlaced serveur sur 14 jours ; PageView navigateur en baisse de plus de 60 % ; événement serveur inattendu ; OrderPlaced restreint ou non confirmé ; compte publicitaire bloqué. Une source indisponible déclenche une notification. | Changements hors cycle, restrictions, CAPI morte, routine devenue muette | Tâche planifiée (dans le cloud si possible), `docs/meta-dependances.json`, `audit-preuves/veille-meta/` | 0,75 j | P0 |
| R13 | Test CI qui échoue si un motif `vNN.N` apparaît hors de Config | Retour d'une version écrite en dur | `scripts/tests` | 0,1 j | P2 |

#### Couche 2 — Absorber par un réglage

| ID | Mécanisme | Protège contre | Où | Effort | Prio |
|---|---|---|---|---|---|
| R3 | **Profil de données**, à deux valeurs :<br>• « standard », par défaut : sans `famma_stage`, `famma_product` ni `content_name`, côté serveur ET navigateur ;<br>• « minimal », pour l'urgence.<br>L'ancien comportement reste accessible par un filtre de code seulement. Le profil minimal absorbe un champ **retiré** par Meta, jamais un champ devenu **obligatoire**. | E2, E3, champ retiré par Meta | Capi, Capi_Product_Data, Tracking_Events, « Mesure » | 0,6 j | P1 |
| R4 | **Procédure de montée de version**, en 5 étapes :<br>1. tester la version candidate avec la sonde, par une saisie ponctuelle qui n'est pas enregistrée ;<br>2. basculer pendant une semaine creuse ;<br>3. valider sur le prochain **vrai** OrderPlaced, jamais avec un événement de test ;<br>4. revenir en arrière, si besoin, par l'option S2 ;<br>5. reporter la nouvelle date de fin dans R1. | Bascule ratée, E5 | `docs/EXPLOITATION-META.md` | 0,15 j | P1 |

#### Couche 3 — Détecter

| ID | Mécanisme | Protège contre | Où | Effort | Prio |
|---|---|---|---|---|---|
| R5 | **Transport unique**, partagé par les vrais envois et le bouton « Test CAPI ». Il lit l'en-tête `facebook-api-version` (s'il est absent : « inconnue », sans alerte), `messages[]`, le code et le sous-code d'erreur. Il classe chaque réponse en 5 catégories : accepté, accepté avec avertissement, transitoire, accès (190, 10, 200, 294, 368), contrat ou inconnu. | E1, jeton mort, champ refusé, avertissements ignorés | `includes/capi-transport.php` | 0,6 j | P0 |
| R6 | **Sonde hebdomadaire sans événement** : `GET /{version}/{pixel}?fields=id`. Elle se lance aussi à la demande et après tout changement de jeton, de pixel ou de version. Son échec propre donne de l'orange, jamais du rouge. | Jeton ou version morts entre deux commandes ; validation de v26.0 sans événement qui compte | `includes/capi-health.php`, Action Scheduler | 0,4 j | P0 |
| R7 | **État de santé unique** : bandeau vert, orange ou rouge dans « Mesure », Commandes et Tableau de bord. Un e-mail au passage au rouge, au plus un par 24 h. Le chien de garde **lit** cet état et ajoute ces règles : battement de cœur de plus de 36 h (contrôlé sur `admin_init`, sans cron) ; action en attente depuis plus de 2 h ; commande « Livrée » sans Purchase depuis plus de 24 h ; version servie différente de la version demandée ; profil minimal actif ; fbc rejeté localement. | Panne silencieuse, cron arrêté, Purchase perdu | `capi-health.php`, mu-plugin chien de garde | 0,85 j | P0 |
| R8 | **Rapprochement sur 14 jours.** Toute commande non test, selon une règle large et **indépendante** de l'émetteur, doit avoir un OrderPlaced accepté ; toute commande livrée, un Purchase. L'écran ajoute une ligne « inéligibles : N (motif) » et une colonne « fbc présent ». Le bouton « Renvoyer » est borné : envoi rejeté de moins de 6 jours, envoi incertain de moins de 24 h ; ces délais sont regroupés dans Config. | Perte partielle, changement de WooCommerce, fbc qui disparaît | `includes/capi-reconcile.php` | 1,1 j | P0 |
| R9 | **Revue guidée chaque trimestre** (Claude dans Chrome, en lecture seule) et **contrôle avant toute relance OLYMPE**, qui conclut GO ou NON-GO | E2, E3, E4, E10, E16, tout ce qu'aucune API n'expose | `EXPLOITATION-META.md`, MCP meta-ads | 0,25 j | P1 |

#### Couche 4 — Limiter l'impact

| ID | Mécanisme | Protège contre | Où | Effort | Prio |
|---|---|---|---|---|---|
| R10 | **Séance unique**, faite par le propriétaire :<br>• vérifier fammaminnou.tn par TXT DNS ;<br>• double authentification par application, avec codes imprimés ;<br>• second e-mail et second téléphone de récupération ;<br>• pièce d'identité prête ;<br>• jeton sans date d'expiration ;<br>• notifications Meta envoyées vers une boîte lue. | Compte désactivé, accès perdu, E16 | Réglages Meta | 0 j | P0 |
| R11 | **Fiches réflexes**, une page par panne : jeton, version, champ retiré ou ajouté, OrderPlaced bloqué, catégorie santé, compte désactivé, paiement refusé, accès perdu, contrôle après une mise à jour. S'y ajoute la matrice « sur quel événement optimiser », décidée à l'avance. | Fausse manœuvre, contournement interdit | `EXPLOITATION-META.md` et copie dans Drive | 0,4 j | P1 |
| R12 | **Attribution first-party** : UTM à identifiants dynamiques sur chaque nouvelle pub, script UTM indépendant du pixel, une seule méta `_famma_order_source` | E2, E12, E14, compte désactivé, H-A2 | Ads Manager, Tracking, Visitor_Commerce | 0,5 j | P1, avant la relance OLYMPE |

**Budget**
- Développement : P0 ≈ 3,85 j, P1 ≈ 1,9 j, P2 ≈ 0,1 j.
- Si le lot 5 prend du retard, déployer **S2 seul** (avec R1, R5 et R6) avant le 15/12/2026.

#### Calendrier des échéances Meta

| Date | Échéance | Action |
|---|---|---|
| 27/10/2026 | `debug`/`pretty` supprimés (E17) | Aucune : déjà vérifié |
| avant le 10/11/2026 | — | Capture R2 de la configuration du pixel (« avant ») |
| 05/11/2026 | Décision sur le pixel IA et la mesure sur l'appareil, activés le 12/11 | Propriétaire |
| 19/11/2026 | — | Capture R2 « après » |
| **15/12/2026** | **Date limite interne** | v26.0 en prod, en-tête constaté |
| 07/01/2027 | Alerte rouge si v26.0 n'est pas en prod | — |
| **21/01/2027** | **Fin de v21.0** | — |
| 22/01/2027 | Contrôle | Après la première vraie commande, vérifier qu'un OrderPlaced serveur est arrivé |
| 2027-2028 | Fins de v22 (20/05/2027), v23 (08/10/2027), v24 (18/02/2028), v25 (29/07/2028) | Aucune, une fois en v26.0 |
| v26.0 | Date non publiée : pas avant le 29/07/2028, probablement en 2029 | Basculer vers la dernière version au plus tard 6 mois avant la fin |

#### Registre des dépendances FAMMA (`docs/meta-dependances.json`)

- **Appels :**
  - `POST graph.facebook.com/{v}/{pixel}/events` ;
  - sonde `GET /{v}/{pixel}?fields=id` ;
  - script `connect.facebook.net/…/fbevents.js` ;
  - configuration publique `signals/config/1061419273284333`.
- **Événements :**
  - navigateur : PageView, ViewContent, AddToCart, InitiateCheckout ;
  - serveur : OrderPlaced (navigateur en option, coupé), Purchase à « Livrée ».
- **Format :** `event_id` = `famma_<événement>_<n°>`, `action_source` = website.
- **Champs envoyés :**
  - `user_data` : ph, fn, st, fbp, fbc, IP, user-agent ;
  - `custom_data` : value, currency, content_ids, content_type.
- **Lu en retour :** l'en-tête `facebook-api-version`, `messages[]`, le code et le sous-code d'erreur.
- **Cookies :** `_fbp`, `_fbc` (au format `fb.1.<ms>.<fbclid>`), `famma_utm`.
- **Règles Meta dont nous dépendons :**
  - déduplication sur 48 h, avec préférence pour l'événement navigateur à moins de 5 min ;
  - `event_time` limité à 7 jours.
- **État attendu chez Meta :**
  - CAPI « Meta-enabled » désactivée, passerelle non activée ;
  - OrderPlaced confirmé ;
  - domaine vérifié ;
  - état de la Configuration principale relevé.
- **Identifiants :** pixel 1061419273284333, portefeuille 1339755868270781, app 1381637394076726, comptes publicitaires.
- **Réglages FAMMA :**
  - constantes `FAMMA_META_*` (alimentées par `.env`) ;
  - options `famma_meta_graph_version`, `famma_meta_data_profile` et `famma_capi_health`.
- **Tiers :** WooCommerce, Action Scheduler, LiteSpeed Cache.

#### Routine du propriétaire

**Chaque mois, environ 10 min :**
- lire les 3 lignes du rapport R2 ;
- ouvrir « Mesure » : bandeau et écran R8, comparer le nombre d'envois acceptés avec le nombre donné par Meta ;
- valider les statuts E1 à E18 proposés ;
- coller la ligne UTM sur chaque nouvelle pub.

**Chaque trimestre, environ 45 min (≈ 15 min par mois) :**
- revue R9, avec Claude ;
- contrôle des accès R10 ;
- une fois par an, contrôle du fbc sur iPhone (T6.7).

**Après chaque zip, mise à jour de WooCommerce ou purge LiteSpeed, environ 3 min :**
- « Vérifier maintenant » ;
- ouvrir une fiche produit en navigation privée et vérifier qu'un PageView puis un ViewContent partent.

#### Volontairement écarté

- **Contrat déclaratif, repli automatique, golden files en CI :** disproportionnés pour un champ qui change environ tous les 2 ans.
- **Dans le code de prod :** seuils d'EMQ, empreinte quotidienne du pixel, lecture de /stats. Les droits du jeton sur ces accès ne sont pas documentés ; R2 s'en charge.
- **Canari navigateur, route « health-ping » publique, bascule automatique de version, SDK Composer.**
- **Dans l'admin :** profil « complet », champ « version candidate » permanent, 4 paliers d'avis, compteurs d'e-mails, sonde quotidienne. Retirés après la red team.
- **Bascule d'OrderPlaced vers Lead, ou renommage pour échapper à un blocage :** interdits, puisqu'un compte a déjà été désactivé une fois.
- **Préparation de TikTok, rapport de ventes par e-mail ou dans le tableau de bord (R14), lecture des e-mails Meta par la routine :** hors sujet ou inutiles à ce volume.
- **Contrôle mensuel de l'expiration du jeton (debug_token) :** R10 garantit un jeton sans expiration, et R5, R6 et R8 rattrapent une coupure.
- **Consentement (E13) :** ce n'est pas un mécanisme de résilience. Il reste la décision du §5.5. R2 ne fait que surveiller les conditions de Meta et la loi tunisienne.

**Vérifications faites sur notre code après le workflow (19/10) :**
- `content_name` part bien côté navigateur. Constaté dans le HTML de prod : `"content_name":"Ceinture dorsale Pro"`. R3 doit donc couvrir `Tracking_Events`.
- **Correction de la red team retirée** : il n’est pas nécessaire d’assouplir le motif fbc. `capi-signals.php:32` accepte déjà un fbclid jusqu’à 500 caractères, ainsi que le 5e segment (« appendix ») de Parameter Builder. On garde seulement un compteur `fbc_rejected`, qui permet de distinguer un fbc absent d’un fbc refusé localement, sans jamais journaliser la valeur.
- Le portefeuille complet (14 mécanismes, 18 écartés), les 36 scénarios de la red team et les 4 propositions d’origine sont archivés dans `audit-preuves/2026-10-19-pixel/resilience/`.

---

## 1. Ce que l'exploration a établi

### 1.1 Faits vérifiés

| Sujet | Constat | Source |
|---|---|---|
| Émetteurs en prod | Un seul pixel Meta (1061419273284333) posé par famma-core, GA4 posé par le mu-plugin `famma-google-tags.php`, aucun TikTok. Aucun autre plugin de pixel. | HTML prod, liste des extensions |
| Purchase « muet » depuis le 10/10 | **Ce n'est pas une panne** : aucune commande n'est passée en « Livrée » depuis le 10/10. Une seule commande vivante (#296, en attente). Les 9 Purchase des 09-10/10 venaient de commandes de test, depuis supprimées définitivement. | wp-admin, Action Scheduler, journal `famma-capi` |
| OrderPlaced | Envoyé correctement (HTTP 200, `events_received: 1` le 18/10). EMQ 6,4. Absents : em, fbc, external_id, ln, ct, zp. | Journal, Events Manager |
| fbc | Le journal dit « fbc oui » pour 3 envois sur 5, alors que Meta affiche « votre serveur n'envoie pas de fbc ». | Journal `famma-capi`, Events Manager |
| `famma_stage` | Le code l'ajoute à OrderPlaced (famma-core.php:2232), mais Meta ne le liste pas dans les paramètres reçus. | Code + Events Manager |
| Conversion perso « FAMMA \| Commande passée » | Inactive, jamais déclenchée (règle « tout le trafic où famma_stage = order_placed »). | Events Manager |
| eventID de ViewContent | Généré en PHP et figé par le cache LiteSpeed (7 jours) : tous les visiteurs reçoivent le même. | 2 chargements identiques en cache *hit* |
| Visites du propriétaire | Le pixel Meta part aussi pour l'administrateur connecté (PageView, ViewContent), alors que GA4 l'exclut. | Chrome connecté |
| PageView | event_id présent à 55 % chez Meta alors que le code n'en pose aucun ; Meta détecte un partenaire « WordPress ». | Events Manager |
| AddToCart | Meta ne détecte aucun paramètre (value, content_ids…), alors qu'InitiateCheckout en a 6. | Events Manager |
| Domaine | fammaminnou.tn **n'est pas ajouté ni vérifié** dans le portefeuille Meta ; aucune liste d'autorisation de trafic ; un « autre domaine » a envoyé des données (diagnostic du 19/10). | Paramètres de l'entreprise |
| Origine des commandes | « Inconnu » sur toutes les commandes (attribution WooCommerce). | wp-admin |
| Tests | Aucun test pixel n'est exécuté en CI. Les deux tests isolés sont probablement cassés depuis la 0.2.4. Aucune preuve d'exécution archivée depuis la 0.2.6. | Dépôt, `audit-preuves/` |
| Environnements | Docker Desktop prêt (images présentes). Le site Local « famma » est arrêté et pointe vers la copie principale en **0.2.5**, pas vers la 0.3.1. | `docker info`, jonction Local |
| Consentement | Aucun bandeau : `_fbp` et `_ga` sont posés dès la première page. | HTML prod |

### 1.2 Hypothèses à challenger

**Paramétrage Meta**
- **H-M1 (critique)** — Meta retire les paramètres personnalisés (catégorie « santé » probable à cause de la ceinture dorsale, mode « Configuration principale »). Effet : `famma_stage` est supprimé, la conversion perso ne pourra jamais se déclencher, et des URL sont tronquées.
- **H-M2** — La règle de la conversion perso est de toute façon mal posée (elle vise le trafic URL, pas l'événement OrderPlaced).
- **H-M3** — Le domaine non vérifié et l'absence de liste d'autorisation laissent n'importe quel site (copie locale, clone) envoyer des événements dans le pixel de prod. Il faut aussi identifier l'« autre domaine » du 19/10.
- **H-M4** — Le partenaire « WordPress » et l'event_id à 55 % sur PageView révèlent un second émetteur, ou un comportement automatique de fbevents.js à expliquer.
- **H-M5** — « API Conversions : connexion en attente » est un simple libellé, ou bien les événements serveur ne sont pas rattachés à l'intégration.

**Navigateur**
- **H-N1** — PageView est émis plus d'une fois par page.
- **H-N2 (prouvée)** — eventID de ViewContent figé par le cache → risque que Meta fusionne des visiteurs différents.
- **H-N3** — AddToCart part sans paramètres (au moins sur l'un des 3 parcours : fiche, liste AJAX, express).
- **H-N4** — `content_ids` incohérents : ID WooCommerce côté navigateur, SKU côté express et CAPI.
- **H-N5 (prouvée)** — Les visites de l'administrateur polluent les données Meta.
- **H-N6** — Le chargement différé (après `load`, jusqu'à 3 s) perd des événements quand le visiteur quitte vite la page ou est sur un réseau lent.
- **H-N7** — Une pub qui pointe vers un ancien slug perd `fbclid` et les `utm_*` dans la redirection 301.

**Serveur (CAPI)**
- **H-S1 (critique)** — Le fbc envoyé est mal formé, ou supprimé par Meta (lié à H-M1).
- **H-S2** — Normalisation non conforme à la doc Meta : `st` = « tn-51 », accents (« Médenine » → « mdenine »), villes en arabe omises, pas d'`external_id`.
- **H-S3** — Purchase : un seul envoi à « Livrée », valeur réelle, après l'encaissement COD. À vérifier aussi : livré → retourné → livré, statut « Terminée », commande créée dans l'admin (sans user-agent ni IP alors que l'événement est déclaré « website »).
- **H-S4 (critique)** — Le registre d'envoi perd des événements sans rien signaler : états `uncertain`, `sending` orphelin, table absente et `blocked` ne sont jamais relancés ni journalisés. Purchase est synchrone (8 s) dans la requête admin.
- **H-S5** — OrderPlaced peut partir deux fois depuis le checkout classique (double clic). Certains envois ne laissent aucune trace (#288 et #289 : action terminée, aucune ligne de journal).
- **H-S6** — Un rejeu avec l'`event_time` d'origine au-delà de 7 jours est rejeté par Meta.
- **H-S7** — La version Graph v21.0 est codée en dur à deux endroits ; Meta la retire le 21/01/2027.
- **H-S8** — Le jeton ne doit jamais atteindre le navigateur ni les journaux (le hook `http_api_debug` voit le corps de la requête).
- **H-S9 (critique)** — Une copie locale de la base embarque le vrai jeton et envoie de vrais événements au pixel de prod. Comme l'event_id ne dépend que du numéro de commande, une commande locale peut faire écarter la vraie commande de même numéro pendant 48 h.

**Attribution et données**
- **H-A1** — Le formulaire express ne transmet pas les champs `wc_order_attribution_*`, d'où l'origine « Inconnu ».
- **H-A2 (latent)** — La capture UTM s'arrête dès que le pixel n'est plus configuré, et deux sources UTM coexistent (cookie `famma_utm` et parcours visiteurs).

**Outillage**
- **H-T1** — Les tests isolés `test-capi-isolated.php` et `test-pixel-scenarios.php` sont cassés (il manque les remplaçants de 4 classes).

---

## 2. Règles de sécurité pendant toute la campagne

1. **Aucune modification de famma-core pendant les lots 0 à 4** : on mesure la 0.3.1 telle qu'elle tourne en prod, pour disposer d'un « avant ». Seul le lot 5 modifie famma-core (décisions S1 à S3, version 0.3.2). Les autres correctifs viendront ensuite, dans un chantier séparé.
2. Les seuls fichiers créés vont dans `scripts/` (outillage de test) et dans `E:\Ecom\audit-preuves\2026-10-19-pixel\` (preuves). Des commits locaux sont faits à chaque étape, **sans push** sans ton accord.
3. **Local et Docker :** tout HTTP sortant est intercepté (`pre_http_request`), avec un pixel factice et un jeton factice. **Aucun événement ne part vers Meta depuis un environnement local.**
4. **Prod passive :** lecture seule (curl, navigateur, wp-admin, Events Manager). Aucun clic qui enregistre, aucun formulaire.
5. **Prod active (lot 6), et déploiement de la 0.3.2 :** chaque étape est soumise à ton accord explicite au moment de la faire. Le code de test Meta `TEST27940` rend les événements **visibles** dans « Test Events », mais **ne les isole pas** : d'après la doc Meta, ils comptent réellement dans les statistiques et l'optimisation (E5). D'où trois règles : une seule commande d'essai, les ensembles OLYMPE en pause pendant l'essai, et chaque réglage modifié remis à son état d'origine juste après.
6. Aucune donnée client dans les rapports. Aucune suppression définitive : la commande d'essai finit « Annulée » puis à la corbeille.
7. `docker compose down -v` (qui efface les volumes) est interdit sans ton accord. En fin de campagne, on fait seulement `stop`.

---

## 3. Scénarios de test

Légende :
- **Env.** : U = unitaire sans WordPress, D = clone Docker WP + WooCommerce, N = navigateur sur le clone Docker, P = prod passive, A = prod active (sur accord).
- **Succès** : ce qui doit être observé pour que le comportement soit déclaré correct. Sinon l'hypothèse est confirmée.

### Lot 0 — Préparation et point de référence

| ID | Scénario | Env. | Succès / livrable |
|---|---|---|---|
| T0.1 | Mettre la branche de travail à jour avec `origin/main` (mu-plugin `famma-google-tags.php`). | — | La branche contient le mu-plugin ; `php -l` passe. |
| T0.2 | Relevé de référence Meta : volumes 7 et 28 jours par événement et par source, EMQ, diagnostics, paramètres reçus. | P | Fichier `reference-meta.md` archivé. |
| T0.3 | Monter un clone Docker isolé `famma-qa-pixel` (port libre, ex. 8981) : WordPress, WooCommerce 11.1.2, COD, famma-core de la branche, 2 produits marqués « TEST » (dont 1 avec tailles et SKU, 1 sans SKU), pixel et jeton factices, HTTP intercepté. Écrire le script d'installation, qui n'existe pas. | D | `scripts/qa-pixel-setup.sh` reproductible ; la page d'accueil du clone répond. |
| T0.4 | (Optionnel, si tu démarres Local) Vérifier si la base Local contient un jeton Meta, en lisant seulement sa longueur (H-S9). | — | Longueur = 0, sinon H-S9 confirmée côté Local. |

### Lot 1 — Tests unitaires (aucun réseau)

| ID | Scénario | Hyp. | Succès |
|---|---|---|---|
| T1.1 | Lancer `test-capi-isolated.php` et `test-pixel-scenarios.php` tels quels. | H-T1 | Échec attendu : confirme la cassure. |
| T1.2 | Ajouter les 4 remplaçants manquants (registre, signaux, données produit, journal) **dans les scripts de test uniquement**, puis relancer. | H-T1 | 36 + 75 contrôles verts ; les 3 « FINDING » connus sont listés. |
| T1.3 | `node scripts/test-famma-express-v2.js` (vrai `express-order.js` : InitiateCheckout une seule fois, eventID serveur). | — | Tout vert. |
| T1.4 | Normalisation `user_data` sur un jeu d'entrées. Téléphones : 22 123 456, +216 22123456, 0021622123456, 216 22 123 456. Noms : « Médenine », « É », nom en arabe. Gouvernorat → `st`. Absence d'e-mail. Comparer aux hachages attendus selon la doc Meta. | H-S2 | Chaque champ haché conforme à la doc. Les écarts sont listés : st, accents, arabe, external_id. |
| T1.5 | Registre d'envoi : simuler `WP_Error`, HTTP 500, délai dépassé, réponse 200 sans `events_received`, table absente. Relever l'état final, la note de commande, la ligne de journal et la relance planifiée. | H-S4 | Chaque échec laisse une trace et une relance. Toute issue muette confirme H-S4. |
| T1.6 | Masquage du jeton : forcer chaque chemin d'erreur et inspecter le journal `famma-capi` et `http_api_debug`. | H-S8 | Jeton absent partout, sauf ses 4 derniers caractères en admin. |
| T1.7 | `content_ids` : même produit (simple avec SKU, simple sans SKU, variable avec taille) passé dans les 3 générateurs (navigateur, express, CAPI). | H-N4 | Même identifiant partout. Sinon, tableau des écarts. |

### Lot 2 — Intégration WordPress + WooCommerce (clone Docker, HTTP intercepté)

Chaque scénario est joué **deux fois** : HPOS activé, puis HPOS désactivé.

| ID | Scénario | Hyp. | Succès |
|---|---|---|---|
| T2.1 | Lancer les 8 suites d'intégration existantes (CAPI, fbc, registre concurrent, valeur Meta, express, woocommerce, tailles, statuts). | — | Toutes vertes en 0.3.1. Sortie JSON archivée. |
| T2.2 | Cycle Purchase : commande → confirmée → expédiée → livrée. | H-S3 | 1 seul Purchase intercepté, event_id `famma_purchase_<id>`, value = total réel en TND, méta COD payée **avant** l'envoi. |
| T2.3 | livrée → retournée → livrée à nouveau. | H-S3 | Aucun second Purchase. |
| T2.4 | Statut « Terminée » au lieu de « Livrée ». | H-S3 | Comportement constaté et documenté (Purchase attendu ou non : à trancher). |
| T2.5 | Commande créée dans l'admin (sans cookies, sans user-agent), puis livrée. | H-S3 | Charge utile conforme à la doc pour `action_source=website`. Sinon, l'écart est relevé. |
| T2.6 | Double soumission du checkout classique et double clic sur la commande express. | H-S5 | Un seul OrderPlaced. |
| T2.7 | Chaque issue d'OrderPlaced (envoyé, ignoré, déjà envoyé, désactivé, inéligible) écrit une ligne de journal. | H-S5 | Une ligne par issue (le cas des commandes #288/#289 est reproduit ou expliqué). |
| T2.8 | Action Scheduler indisponible : repli sur `wp_schedule_single_event`, puis rejeu tardif (plus de 7 jours). | H-S6 | L'événement part ; au-delà de 7 jours, le comportement face au rejet de Meta est documenté. |
| T2.9 | Pixel vidé : le cookie `famma_utm` est-il encore posé, et la commande reçoit-elle ses UTM ? | H-A2 | Constat (échec attendu tant que le défaut existe). |
| T2.10 | Commande express arrivant avec des UTM : origine WooCommerce (`_wc_order_attribution_*`) renseignée ? | H-A1 | L'origine est renseignée, sinon H-A1 confirmée. |
| T2.11 | Base clonée avec un jeton présent : passer une commande en « Livrée » et regarder la requête interceptée (destination, présence de `test_event_code`). | H-S9 | Démontre ce qu'une copie locale enverrait réellement. |
| T2.12 | Rechercher le jeton factice dans le HTML et le JS de toutes les pages, et dans les journaux. | H-S8 | Introuvable. |

### Lot 3 — Navigateur sur le clone Docker

Pixel factice ; `fbq` enregistré au lieu d'être envoyé ; `connect.facebook.net` bloqué ou simulé.

| ID | Scénario | Hyp. | Succès |
|---|---|---|---|
| T3.1 | Accueil, fiche, panier, commande : nombre de `fbq('init')` et de PageView par page. | H-N1 | 1 init et 1 PageView par page. |
| T3.2 | AddToCart depuis la fiche, depuis la liste (AJAX) et par la commande express ; puis InitiateCheckout. | H-N3 | Chaque AddToCart porte value, currency, content_ids et eventID ; InitiateCheckout part une seule fois. |
| T3.3 | Arrivée avec `?fbclid=FAMMATEST123` → cookie `_fbc` au format `fb.1.<ms>.FAMMATEST123`. Puis commande (express **et** classique) : la méta `_famma_fbc` doit être égale au cookie et figurer dans la requête CAPI interceptée. | H-S1 | Format exact et valeur transmise dans les deux parcours. |
| T3.4 | Navigation en tant qu'administrateur connecté. | H-N5 | Constat (événements émis aujourd'hui), qui sert de référence « avant » pour S1 (test A1 du lot 5). |
| T3.5 | Réseau lent (3G simulée) et départ rapide (moins de 2 s) depuis la fiche et depuis l'ajout au panier. | H-N6 | Mesurer la part d'événements perdus. |
| T3.6 | Ancien slug avec `?fbclid=…&utm_source=…`. | H-N7 | Les paramètres survivent à la redirection 301, sinon H-N7 confirmée. |

### Lot 4 — Prod passive (aucune écriture)

| ID | Scénario | Hyp. | Succès |
|---|---|---|---|
| T4.1 | Deux chargements sans cookie de la même fiche : même eventID ViewContent ? | H-N2 | Référence avant correctif (attendu : identique). |
| T4.2 | `curl -sI` sur un ancien slug produit avec `?fbclid=TESTX&utm_source=test`. | H-N7 | `Location` conserve les paramètres, sinon H-N7 confirmée en prod. |
| T4.3 | En-têtes de cache sur la route REST `famma/v1/collect` et sur les routes `wc-ajax` de l'express. | — | Aucune réponse dynamique mise en cache. |
| T4.4 | Lire le détail du diagnostic « autre domaine » du 19/10 pour savoir quel domaine a envoyé des données. | H-M3 | Domaine identifié. |
| T4.5 | Navigateur intégré (visiteur non connecté) : `fbq.getState()` sur 4 pages (nombre d'événements, file d'attente) et liste des cookies. | H-N1 | 1 pixel ; événements attendus seulement. |
| T4.6 | Events Manager, en lecture : section « Restrictions des données ». Relever le texte exact du bouton de la Configuration principale (« Désactiver » grisé = imposée par Meta), la présence de « Gérer les catégories de sources de données » (catégorie attribuée), et le statut d'OrderPlaced dans « Gérer le blocage des événements ». Chercher aussi les diagnostics « paramètres bloqués », et dans la répartition par URL, vérifier si seul `https://fammaminnou.tn/` apparaît. | E2, E3, E4 | État de restriction établi (imposée, choisie ou absente) et statut d'OrderPlaced connu. |
| T4.7 | Config du pixel servie (`connect.facebook.net/signals/config/1061419273284333`), en lecture : plugins actifs (IABPCMAEBridge, automaticMatching, microdata, inferredevents, IA). Dans le navigateur intégré, les paramètres réellement ajoutés aux requêtes `/tr`. | E8, E9 | Liste de ce que Meta collecte de lui-même ; explication des 55 % confirmée. |
| T4.8 | Events Manager, en lecture : nature de « API Conversions : connexion en attente » (configuration guidée ou « Meta-enabled » laissée en cours ?) et état de l'interrupteur « Meta-enabled ». | E10, H-M5 | Origine du libellé identifiée ; « Meta-enabled » inactif. |
| T4.9 | Empreinte de référence de la configuration servie `signals/config/1061419273284333` : opt-ins, `restrictedEventNames`, `unverifiedEventNames`, `ProtectedDataMode`, `automaticMatching`. Archivée dans `audit-preuves`. | S6-R2 | Référence « avant » des activations Meta du 12/11. |
| T4.10 | Outils Meta pour développeurs, en lecture (`devtools_compliance`, `devtools_app`) sur l'app de la CAPI : un contrôle d'utilisation des données, une vérification d'entreprise ou un App Review est-il en attente ? | S6-R2 | Liste des échéances administratives Meta. |
| T4.11 | Relever `attribution_spec` et `optimization_goal` des ensembles OLYMPE, ainsi que l'état du compte publicitaire et ses erreurs (MCP meta-ads, lecture). | S6-R2, E14 | Référence pour la routine R2. |
| T4.12 | Le connecteur meta-ads est-il utilisable par une routine Claude dans le cloud (sans PC allumé) ? | S6-R2 | Oui ou non, ce qui décide où R2 tourne. |
| T4.13 | Relever la date « Updated » des 5 pages de doc CAPI : Parameters, Deduplicate, fbp et fbc, Customer Information, Using the API. | S6-R2 | Référence de la veille documentaire. |

### Lot 5 — Mise en œuvre des décisions S1 à S3 (famma-core 0.3.2)

Le code est écrit dans la branche, validé en local (Docker et navigateur), puis déployé en prod seulement avec ton accord.

**Validation technique (exigée par ClaudeMemo §11) :**
- `php -l` et phpcs (règles WordPress) sans erreur ;
- `WP_DEBUG` activé et `debug.log` propre dans le clone ;
- parcours COD complet de bout en bout ;
- relance des lots 1 à 3, qui doivent rester verts hors constats corrigés.

| ID | Décision | Test d'acceptation | Env. | Succès |
|---|---|---|---|---|
| A1 | S1 | Admin connecté : fiche, panier, commande express. | N | 0 `fbq('init')`, 0 événement navigateur, bloc `tracking` express vide. |
| A2 | S1 | Visiteur non connecté juste après une visite admin, avec le cache LiteSpeed simulé sur le clone (sur la prod au lot 6). | N | Le pixel est présent et complet : aucune variante admin servie. |
| A3 | S1 | L'admin passe une commande client à « Livrée ». | D | Purchase part quand même, une seule fois. |
| A4 | S1 | Dérogation « Suivre mes visites (mode test) » activée. | N | Les événements de l'admin réapparaissent. |
| G1 | S2 | Priorité constante > option > valeur par défaut. | U | La bonne version est retenue dans les 3 cas. |
| G2 | S2 | Valeur invalide dans le champ « Mesure » (`21`, `v21`, `abc`). | D | Refusée avec un message ; l'ancienne valeur est gardée. |
| G3 | S2 | Le bouton « Test CAPI » et un vrai envoi (intercepté) visent la même URL `graph.facebook.com/<version>/`. | D | Même version des deux côtés. |
| G4 | S2 | L'en-tête de réponse `facebook-api-version` est journalisé (réponse simulée). | D | Ligne dans le journal `famma-capi` ; alerte du chien de garde s'il diffère. |
| I1 | S3-a | Deux visiteurs sur la même fiche servie en cache. | N | Deux eventID ViewContent différents. |
| I2 | S3-a | Interrupteur « OrderPlaced navigateur » activé puis coupé : comparer l’OrderPlaced du navigateur (fbq enregistré) et celui de la CAPI (interceptée). | N + D | Le même `famma_orderplaced_<id>` des deux côtés. |
| I3 | S3-a | Rechargement de la page de confirmation, puis retour arrière. | N | L'OrderPlaced du navigateur ne repart pas une 2e fois. |
| I4 | S3-a | Clone avec un jeton présent et l'environnement ≠ `production`. | D | Aucune requête réelle vers Meta, ligne de journal « envoi bloqué : environnement ». |
| I5 | S3-b | Produit simple avec SKU, simple sans SKU, et variable avec taille, passés dans les 4 générateurs (navigateur, express, CAPI, aperçu du flux). | U | Même `content_id` partout ; `item_group_id` = parent pour les tailles. |

**Périmètre élargi à l'arrêt n° 1 (19/10)** : le propriétaire ajoute au lot 5 les défauts graves prouvés par les lots 0 à 4.

| ID | Défaut prouvé | Correctif | Test d'acceptation |
|---|---|---|---|
| D1 | Registre : l'état « incertain » (WP_Error, délai dépassé, 5xx, 200 vide) est définitif, donc le Purchase est perdu (T2.13) | Relance bornée par Action Scheduler (5 min, 30 min, 2 h, 6 h), même event_id, dans la limite de 6 jours ; un état « envoi en cours » de plus de 15 min est repris comme incertain ; une table absente donne une trace et un état rouge | T2.13 repasse au vert : la relance est planifiée, puis l'envoi est accepté, une seule fois |
| D2 | LiteSpeed met en cache pendant 1 h les 301 des anciennes URL, et sert le fbclid et les UTM du premier visiteur, ou aucun (T4.2, T3.6) | Les redirections (ancien slug, URL héritées, canoniques) gardent la chaîne de requête du visiteur et ne sont **jamais mises en cache** (API LiteSpeed « no-cache » + en-têtes sans cache) | Clone : la redirection garde `fbclid`/`utm_*` et porte des en-têtes sans cache. Prod, après déploiement : deux GET avec des paramètres différents → deux `Location` différentes |
| D3 | Checkout classique : deux POST simultanés donnent 2 commandes et 2 OrderPlaced (T2.6) | Verrou atomique par session et panier, inspiré d'`Express_Idempotency` ; la 2e requête reçoit un message clair | 2 POST simultanés → 1 commande, 1 OrderPlaced ; le parcours normal et l'express sont inchangés |
| D4 | Aucune garde d'environnement : une copie envoie de vraies conversions (T2.11) | Si `wp_get_environment_type()` n'est pas `production`, aucun envoi réel, avec une ligne de journal ; dérogation explicite par la constante `FAMMA_META_ALLOW_NON_PRODUCTION` | Clone (local) → 0 requête réelle et 1 ligne « bloqué : environnement » ; en production simulée → envoi |
| D5 | « Terminée » marque la commande payée sans encaissement COD ni Purchase (T2.4) | **Décision prise par défaut, réversible** : pour une commande en paiement à la livraison, « Terminée » vaut « Livrée » (encaissement, puis un seul Purchase) ; Purchase à priorité 20, après l'encaissement à 10. Commande créée dans l'admin : `action_source` conforme à la doc Meta, au lieu de `website` (T2.5) | Terminée → 1 Purchase et `_famma_cod_paid` ; puis Livrée → aucun second envoi ; commande admin → `action_source` non-website |
| D6 | Chargement différé : 100 % des événements perdus en Fast 3G si le visiteur part en 1,5 s (T3.5) | **Décision prise par défaut, réversible** : chargement immédiat asynchrone (comme le code standard de Meta) ; réglage « différé » dans l'écran « Mesure » | Fast 3G avec départ en 1,5 s : la majorité des événements sont capturés (mesure comparée à T3.5) ; réglage « différé » → comportement de la 0.3.1 |
| D7 | Autres défauts prouvés : 7 issues d'OrderPlaced sur 9 ne laissent aucune trace (T2.7) ; jeton visible en clair sur `options.php` (T2.12) ; code de test invalide omis sans bruit ; l'express ne remplit pas l'attribution WooCommerce (T2.10) ; accents et villes arabes mal normalisés, `st` avec tiret (T1.4) ; InitiateCheckout renvoyé à chaque rechargement (T3.2) | Une ligne de journal par issue ; jeton chiffré au repos (seuls les 4 derniers caractères sont affichés, la constante reste prioritaire) ; code de test validé, sinon envoi bloqué et signalé ; champs d'attribution WooCommerce dans le formulaire express ; normalisation conforme à la doc Meta ; InitiateCheckout une seule fois par panier et par session | T2.7 : 9 issues sur 9 tracées ; T2.12 : jeton illisible dans `options.php` ; T2.10 : origine renseignée ; T1.4 : écarts corrigés ; T3.2 : InitiateCheckout envoyé une seule fois |

**Règle S3-b retenue (décision par défaut, réversible)** : SKU propre de la variation (sans héritage du parent), sinon ID de la variation, sinon SKU du produit, sinon ID du produit ; `item_group_id` = ID du produit parent. Le filtre `famma_meta_content_id` s'applique partout, serveur compris.

**Tests d'acceptation de la résilience (S6)**, joués sur des réponses Meta simulées :

| ID | Mécanisme | Test | Succès |
|---|---|---|---|
| V1 | R1 | Version forcée à v21.0 et date simulée au 02/12/2026 ; puis date de fin connue à J-59 ; puis date de fin inconnue. | Bandeau critique ; puis un avertissement unique ; puis rien. |
| V2 | R5 | Réponses : 200 avec `messages[]` non vide ; 190 ; 100 ; 500 ou délai dépassé ; en-tête `facebook-api-version` absent ; en-tête différent de la version demandée. | Classées dans l'ordre : « accepté avec avertissement », accès (rouge), contrat, transitoire avec relance planifiée, « inconnue » sans alerte, orange. Jeton absent du journal et de `http_api_debug` (rejouer T1.6 et T2.12). |
| V3 | R5 + S2 | `grep` dans famma-core. | Un seul `wp_remote_post` vers graph.facebook.com, partagé par le bouton de test et les vrais envois. |
| V4 | R6 | Sonde : 200 ; puis 190 ; puis erreur propre à la sonde ; puis environnement autre que production. | Vert ; rouge ; orange « sonde indisponible » ; aucune requête. Jamais de `POST /events`. |
| V5 | R3 | Profil « standard », puis « minimal ». | En standard, ni `famma_stage`, ni `famma_product`, ni `content_name`, côté CAPI comme navigateur. En minimal, seule la liste prévue part. Valeur hors liste refusée, nonce et droits vérifiés. |
| V6 | R7 | Battement de cœur vieux de 36 h ; deux passages au rouge en 24 h ; commande « Livrée » sans Purchase depuis 24 h. | Avis sur `admin_init`, sans cron ; un seul e-mail ; état rouge. |
| V7 | R8 | Commande inéligible ; renvoi d'un envoi incertain à 25 h ; renvoi d'un envoi rejeté à 5 jours ; renvoi d'un envoi accepté. Joué avec HPOS activé puis désactivé. | Ligne « inéligibles : N (motif) » ; renvoi refusé ; renvoi accepté avec le même event_id ; jamais de renvoi pour un accepté. Commandes d'essai exclues mais signalées. |
| V8 | R12 | Pixel vidé. | Le cookie `famma_utm` est quand même posé et `_famma_order_source` est renseigné (T2.9 passe au vert). |
| V9 | R7 + R13 | Un cookie `_fbc` invalide incrémente `fbc_rejected` sans journaliser sa valeur. Un `v21.0` ajouté hors de Config. | Compteur incrémenté ; la CI passe au rouge. |

### Lot 6 — Prod active, sur la 0.3.2 déployée (chaque étape sur ton accord, code de test `TEST27940`)

Le lot n'est joué qu'**une seule fois**, sur la version corrigée, pour ne passer qu'une commande d'essai.
- Avant le déploiement, T6.2 (bouton « Test CAPI ») peut être joué sur la 0.3.1 pour garder une référence « avant ».
- Après le déploiement, on rejoue A2 et I1 en prod passive : deux visiteurs, la même fiche en cache, des eventID différents.

| ID | Scénario | Hyp. | Ce que cela touche | Succès |
|---|---|---|---|---|
| T6.1 | **Test des événements navigateur** dans Events Manager (« Tester les événements » → ouvrir fammaminnou.tn avec `?fbclid=FAMMATEST…`). Parcours : fiche → ajout au panier → page commande, **sans commander**. | H-N1, H-N3, H-M1, H-M4 | Rien en base. Les événements sont visibles dans Test Events, mais comptent quand même (E5) : leur nombre est négligeable. Dérogation S1 « Suivre mes visites (mode test) » activée le temps du test, ou passage par le navigateur intégré en visiteur. | 1 PageView par page ; AddToCart avec paramètres ; paramètres perso conservés ou « _removed_ » ; origine de l'agent « WordPress ». |
| T6.2 | **Bouton « Test CAPI »** (FAMMA > Mesure). Il faut saisir temporairement `TEST27940` dans le champ du code test, puis le vider. | H-M1, H-S1, H-M5 | 1 réglage modifié puis remis. | Événement visible dans Test Events : `famma_stage` présent ou retiré, fbc accepté ou rejeté, intégration rattachée. |
| T6.3 | **Commande « ESSAI » unique** depuis le navigateur intégré (visiteur), par la commande express, arrivée avec `?fbclid=FAMMATEST…&utm_source=essai`, **mode test CAPI activé** pendant une fenêtre de 30 minutes au plus, aucune vraie commande en cours. Coordonnées d'essai fournies par toi. | H-S1, H-S2, H-A1, H-M1 | 1 commande réelle (stock, e-mails), réglage mode test. | OrderPlaced dans Test Events avec fbc, fbp, ph, fn, st et value = total réel ; origine WooCommerce renseignée ; UTM sur la commande. |
| T6.4 | Sur cette commande : confirmée → expédiée → **livrée**, puis retournée → livrée. | H-S3 | Statuts de la commande d'essai. | 1 seul Purchase dans Test Events, valeur réelle, après l'encaissement COD ; aucun second envoi. |
| T6.5 | Remise en état : commande « Annulée » puis corbeille (jamais de suppression définitive), stock remis, mode test désactivé, code test vidé. Relire « Mesure ». | — | Réglages et stock. | Réglages identiques au relevé T0.2 ; diagnostics Meta inchangés. |

| T6.6 | **OrderPlaced navigateur + serveur** (interrupteur S3-a activé le temps d'une seconde commande d'essai, **seulement si tu l'acceptes**). | E6 | 1 commande d'essai de plus. | Dans Test Events : laquelle des deux versions Meta garde-t-elle, et l'EMQ baisse-t-elle ? Si l'EMQ baisse, l'interrupteur reste coupé. |
| T6.7 | **fbc sur iPhone** : ouvrir le lien d'une pub depuis l'app Facebook, puis le même lien depuis Messages vers Safari. Relever le cookie `_fbc` (présence, expiration) sans commander. | E12 | Rien (simple visite depuis ton téléphone). | Mesure de la perte de fbc sur iOS, qui décide de l'option « `_fbc` posé par le serveur » (S6). |
| T6.8 | **Version de l'API, sans événement qui compte** : d'abord la sonde R6 en v26.0, puis l'en-tête servi par le **prochain vrai** OrderPlaced, journalisé par R5. Une sonde au vert ne valide pas `/events` à elle seule. | E1, S2, R6 | Rien. | Sonde au vert, puis en-tête v26.0 sur le prochain vrai envoi. |
| T6.9 | Archiver une vraie réponse `/events` avec ses en-têtes, nettoyée de tout jeton : en-tête `facebook-api-version` présent ou non, forme de `messages[]`, présence de `x-ad-api-version-warning`. | R5 | Rien. | Format réel connu, qui sert de réponse enregistrée aux tests V2. |
| T6.10 | Après le zip et la purge LiteSpeed : « Vérifier maintenant », puis contrôle navigateur de 2 minutes (PageView et ViewContent en navigation privée). | R7 | Rien. | Bandeau vert, événements visibles. |
| T6.11 | Séance R10 du propriétaire, si elle n'a pas déjà eu lieu, avant de réactiver OLYMPE. | R10 | Réglages de sécurité Meta (faits par toi). | Domaine vérifié, double authentification, récupération, jeton sans expiration, notifications. |

> **Pendant l'essai (T6.3 à T6.6)** : les événements de test **comptent** (E5). Les ensembles OLYMPE doivent être en pause et la liste des commandes vérifiée avant et après. Dans le rapport, la ou les commandes d'essai sont signalées par leur numéro, pour qu'on puisse les écarter de la lecture des résultats.

### Lot 7 — Synthèse et décision

| ID | Livrable |
|---|---|
| T7.1 | Rapport des résultats : chaque hypothèse marquée **confirmée / réfutée / non concluante**, avec la preuve (fichier, capture, ligne de journal) archivée dans `audit-preuves/2026-10-19-pixel/`. |
| T7.2 | Contre-vérification indépendante par un agent qui n'a pas exécuté les tests (relit les preuves et rejoue 3 tests au hasard). |
| T7.3 | Liste priorisée des correctifs restants (code hors S1 à S3, et réglages Meta), **non exécutés** dans cette campagne. |
| T7.4 | Mise à jour du rapport « Évolution FAMMA MINNOU ». |
| T7.5 | **Te reposer la question d'architecture** : module isolé dans famma-core (option B) ou plugin séparé. |
| T7.6 | Rédiger `docs/EXPLOITATION-META.md` (procédure de montée de version R4, revue trimestrielle R9, fiches réflexes R11 avec la matrice « sur quel événement optimiser ») et `docs/meta-dependances.json` (registre des dépendances, qui alimente R2 et R13). |
| T7.7 | Mettre à jour le tableau S5 avec la couverture par R1 à R13. |
| T7.8 | Mettre en service la routine R2 et créer les dates d'agenda de R1, **avec ton accord explicite**. |

---

## 4. Déroulé, durée et points d'arrêt

| Étape | Équipe d'agents | Durée estimée | Point d'arrêt |
|---|---|---|---|
| Lot 0 | 1 agent infrastructure + moi | ~1 h | — |
| Lot 1 | 1 agent tests unitaires | ~2 h | — |
| Lot 2 | 1 agent intégration (2 passes HPOS) | ~3 h | — |
| Lot 3 | 1 agent navigateur (navigateur intégré) | ~1 h 30 | — |
| Lot 4 | 1 agent prod passive + 1 agent Meta (lecture) | ~45 min | **Arrêt n° 1 : je te présente les résultats des lots 0 à 4 (la référence « avant »).** |
| Lot 5 | 1 agent développeur (S1 à S3, puis R1, R3, R5 à R8) + 1 agent testeur (tests A, G, I et V, relance des lots 1 à 3) + 1 agent relecteur de code | ~5 à 6 jours (S1 à S3 : ~2 j ; résilience P0 : ~3,9 j) | **Arrêt n° 2 : je te montre la 0.3.2 validée en local et je demande ton accord pour la déployer** (zip DirectAdmin, sans entrée de dossier, puis purge LiteSpeed). |
| Lot 6 | moi, avec toi présent (Chrome + navigateur intégré) | ~1 h | **Accord demandé à chaque étape T6.x.** |
| Lot 7 | 1 agent auditeur indépendant + moi | ~1 h | Question d'architecture. |

Les lots 1 et 3 peuvent tourner en parallèle une fois le lot 0 terminé. Le lot 2 a besoin du clone Docker du lot 0. Au lot 5, S2 et S3-b sont indépendants, tandis que S1 et S3-a touchent les mêmes fichiers (Tracking, Tracking_Events, express) : un seul développeur à la fois sur ces fichiers.

**Ordre de mise en œuvre au lot 5** :
1. **S2 (v26.0), avec R1, R5 et R6**, sur les mêmes lignes de code et par le même développeur : priorité n° 1, à cause de l'échéance ferme du 21/01/2027 ;
2. S1, l'exclusion interne ;
3. S3-b, l'identifiant produit, avec R3 (profil de données, qui remplace le filtre `famma_meta_content_name`, côté serveur et navigateur) ;
4. S3-a, les identifiants d'événement. L'OrderPlaced navigateur est codé mais **coupé par défaut** (E6) ;
5. R7 (état de santé et chien de garde) et R8 (rapprochement), avec les correctifs H-S4 ;
6. R12 (UTM indépendant du pixel) et R13 (test CI).

**Date limite interne : 15/12/2026** pour avoir v26.0 en prod. Si le lot 5 glisse, on déploie **S2, R1, R5 et R6 seuls** dans un zip 0.3.2 avant cette date. Le reste suit en 0.3.3.

Chaque décision fait l'objet de son propre commit, sans push sans ton accord.

## 5. Ce qui demande ton accord

1. **Maintenant :** valider ce plan et lancer les lots 0 à 4, sans aucune écriture en prod.
2. **À l'arrêt n° 2 :**
   - valider la règle de l'identifiant produit (S3-b) ;
   - choisir le comportement du statut « Terminée » (Purchase ou non, scénario T2.4) ;
   - autoriser le déploiement de la 0.3.2 en prod.
3. **Au lot 6, étape par étape :**
   - saisir puis vider le code test ;
   - activer puis désactiver le mode test ;
   - passer la commande d'essai avec les coordonnées que tu fourniras ;
   - changer ses statuts ;
   - l'annuler et la mettre à la corbeille.
4. **Toi seul, dans Meta** (je ne les ferai pas à ta place) :
   - **Dès maintenant, sans attendre les tests :**
     - vérifier ou confirmer OrderPlaced dans « Gérer le blocage des événements » (E4) ;
     - **ne jamais activer** la CAPI « Meta-enabled » (E10) ;
     - **séance R10** (environ 1 h, je te guide sans rien toucher) :
       - vérifier le domaine par TXT DNS, avancé par rapport à la version précédente du plan ;
       - double authentification par application, avec codes de récupération imprimés ;
       - second e-mail et second téléphone de récupération ;
       - jeton de l'utilisateur système sans expiration ;
       - notifications Meta envoyées vers une boîte que tu lis ;
     - **avant le 05/11/2026** : décider pour le pixel « IA » et la mesure sur l'appareil, que Meta active le 12/11 (E9).
   - **Après les résultats du lot 4 :**
     - refaire la conversion perso sur le nom d'événement OrderPlaced (E2) ;
     - ajouter une liste d'autorisation du trafic ;
     - si Meta a attribué une catégorie, demander un examen (E3) ;
     - décider pour le pixel « IA » (E9).
5. **Décision de fond, avec avis juridique : le consentement (E13).** Bandeau, avis sur chaque page, déclaration à l'INPDP. Selon ta décision, le code respectera le refus côté navigateur (`fbq('consent','revoke')`) et côté serveur. Les textes viennent de toi ou sont validés par toi.
6. **Plus tard (S4) :** le lancement du flux catalogue, quand tu voudras des pubs catalogue.
7. **Résilience (S6), avec ton accord explicite au moment voulu :**
   - créer la routine Claude mensuelle R2 (première exécution avant le 10/11/2026) ;
   - inscrire les dates d'agenda : 05/11/2026, 15/12/2026, 07/01/2027, 22/01/2027, puis un rappel chaque trimestre.
8. **En fin de campagne :** le nettoyage des volumes Docker (`down -v`), seulement si tu le souhaites.
