# Changelog — FAMMA Core

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
