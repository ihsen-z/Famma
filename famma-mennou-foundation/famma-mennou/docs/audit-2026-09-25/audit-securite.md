# Audit de sécurité — FAMMA (fammaminnou.tn), contre-audit du 25/09/2026

- **Date** : 25/09/2026, contrôles en ligne entre 17:54 et 17:59 (heure de Tunis, UTC+1).
- **Référence** : `docs/audit-2026-09-23/audit-securite.md` (note 5/10 : code 8,5, exploitation 3).
- **Nature** : audit en **lecture seule, strictement passif**. Aucune connexion tentée, aucun formulaire soumis, aucune commande, aucun compte créé, aucune charge malveillante, aucun scanner, aucun contournement testé en ligne.
- **Périmètre A (code)** : dépôt `E:\Ecom\famma-mennou-foundation\famma-mennou`, branche `perf/optimisation-prod` (dernier commit `e08e55a`, 25/09) : `famma-core` (fichier principal + 15 fichiers `includes/`), `mu-plugins`, `scripts/deploy/`. Lecture statique seulement : `phpcs` et `php -l` **n'ont pas été relancés** (consigne : aucune exécution locale).
- **Périmètre B (site)** : https://fammaminnou.tn (LiteSpeed, OXA Host, DirectAdmin). **43 requêtes HTTP** espacées de 4 à 10 s avec un User-Agent Chrome récent, 2 poignées de main TLS, 20 requêtes DNS (Google DNS et serveur de noms autoritaire). **Aucune page de défi Imunify360** reçue (aucun corps < 2 Ko sans `<title>`, aucune mention imunify/captcha) : tous les résultats ci-dessous comptent comme preuves.
- **Faits relevés dans le panneau le 25/09 par l'orchestrateur** (DirectAdmin et wp-admin, lecture seule ; intégrés tels quels, avec la mention « relevé dans le panneau ») :
  - Extensions : famma-core 0.2.0, LiteSpeed Cache 7.9.1 (installé le 24/09), Site Kit 1.187.0 (1.188.0 disponible), TranslatePress 3.3.6, WooCommerce 11.1.2. **Mises à jour automatiques désactivées pour les 5.**
  - Comptes WordPress : un seul, identifiant « admin », administrateur.
  - « Tout le monde peut s'enregistrer » **coché** (rôle par défaut Abonné). WooCommerce : création de compte sur « Mon compte » **activée**, création de compte au checkout activée, commande invité activée.
  - DirectAdmin : **2FA désactivée**, aucun code de secours. Une seule sauvegarde, **manuelle**, `backup-Sep-23-2026-1.tar.zst` (95,75 Mo) dans `/backups` **sur le même serveur**, aucune planification. Cron système `*/5` vers `wp-cron.php` actif. E-mails envoyés : 5 aujourd'hui sur 40 maximum. Aucun plugin SMTP.
  - `.htaccess` (25/09 00:37) : bloc LiteSpeed Cache + redirection HTTPS + bloc WordPress. **Aucun en-tête de sécurité, aucun blocage de `xmlrpc.php` ni de `readme.html`.** `readme.html` présent. `wp-config.php` en **644**. `/wp-content/famma-deploy-1.zip` n'existe plus.
  - Code déployé le 23/09 (`famma-core/includes/hardening.php`) : filtre `xmlrpc_enabled` → false, pingback retiré, en-tête `X-Pingback` retiré, `/wp/v2/users` en 401 sauf utilisateur connecté avec `list_users` ou `edit_posts`.

> **Méthode graph-first** : 14 requêtes Graphify (graphe reconstruit le 25/09 à 17:44, donc à jour). `explain` a localisé `Express_Order` et `.send_nonce()` (`express-order.php` L62, L151), `Hardening` (`hardening.php` L25), `Newsletter` (`newsletter.php` L29), `boot()` (`famma-core.php` L2418), `.handle_request()` (`returns-content.php` L303), `famma_local_mail_from()` (`famma-local-mail.php` L56) et `famma-cron.php`. Seules les lignes citées ont été lues. **Graph miss** : « anti-abus / honeypot / rate limit » de la commande express (aucun nœud), « hooks `woocommerce_store_api_*` » (aucun nœud), « cookie `famma_utm` / champ du token CAPI » (nœuds de documentation seulement). Ces trois points ont été vérifiés par Grep ciblé sur `wp-content/`. La règle de routage REST du cœur a été vérifiée dans la copie locale de WordPress (`Local Sites/famma/app/public/wp-includes/rest-api/class-wp-rest-server.php`).

---

## 1. Note globale : **5,8 / 10** (5 le 23/09)

| Axe | 23/09 | 25/09 | Justification |
|---|---|---|---|
| Code écrit par FAMMA | 8,5 | **8,5** | Progrès réels : SEC-12 et SEC-18 corrigés, nouveau module `Hardening`, et les deux nouveaux formulaires publics sont bien conçus. La newsletter a un nonce, un honeypot, une limite par IP et un export CSV protégé ; le formulaire Retours n'écrit rien. Mais les **deux lacunes métier qui pesaient sur la note restent ouvertes** : aucun anti-abus sur la commande express (SEC-01), API Store non validée (SEC-08). Le nouveau filtre `/wp/v2/users` est aussi **contournable par la casse** (SEC-21), et la fuite de l'identifiant par oEmbed n'est pas couverte (SEC-20). La note ne bouge donc pas. |
| Exploitation (prod) | 3 | **4,5** | Les deux failles publiques **non authentifiées** sont fermées : WooCommerce 11.1.2 et TranslatePress 3.3.6. LiteSpeed Cache a été installé directement en version corrigée (7.9.1). Le cron serveur tourne, l'archive de déploiement publique a disparu, et `/wp/v2/users` répond 401. En revanche, rien n'a bougé sur l'authentification : pas de 2FA DirectAdmin, compte `admin` unique et toujours lisible (oEmbed), aucune limite de tentatives, XML-RPC toujours servi par PHP. Même constat sur la résilience et la configuration : une seule sauvegarde, sur le même serveur ; mises à jour automatiques coupées ; aucun en-tête de sécurité ; ni DKIM ni DMARC ; inscriptions ouvertes. |

Même pondération que le 23/09 (environ 1/3 code, 2/3 exploitation) : 8,5 × 1/3 + 4,5 × 2/3 ≈ **5,8**.

**Le risque le plus grave de l'audit précédent est levé** : un anonyme ne peut plus exploiter une version vulnérable connue (XSS stockées TranslatePress, DoS WooCommerce). Le risque dominant est désormais **l'accès au panneau et à l'administration** : sans 2FA, avec un login connu, sans limite de tentatives, et avec une seule sauvegarde qui disparaîtrait avec le serveur. Viennent ensuite **les fausses commandes COD**, puisque la commande express et l'API Store sont toujours sans défense. Rien n'empêche non plus la prochaine faille d'extension de rester ouverte des jours, faute de mises à jour automatiques. L'essentiel se corrige en moins de 2 heures (§ 6).

---

## 2. Évolution depuis le 23/09

| ID | Constat du 23/09 | Statut au 25/09 | Preuve |
|---|---|---|---|
| **SEC-01** | Commande express sans anti-bot + jeton de checkout public | **Inchangé** | `express-order.php:98` (`wc_ajax_famma_express_nonce`) et `:151-155` (`send_nonce()`) identiques. Grep `honeypot|rate_limit|turnstile|captcha|add_to_cart_validation` dans `famma-core` : seule `newsletter.php` a une limite. En ligne : `GET /?wc-ajax=famma_express_nonce` → 200 `{"success":true,"data":{"nonce":"…"}}` (non caché : `no-store, private`). Le formulaire de `/produit/coupe-legumes-4-en-1/` n'a ni piège ni captcha (champs `famma_product_id`, `famma_quantity`…). Plafond de quantité uniquement côté client (`express-order.php:301-314`). |
| **SEC-02** | TranslatePress 3.3.3 : 2 XSS stockées non authentifiées | **Corrigé** | TranslatePress 3.3.6 (relevé dans le panneau) ; assets `translatepress-multilingual/…?ver=3.3.6` dans le HTML. La 3.3.6 corrige CVE-2026-89412 (≤ 3.3.5, CVSS 7,2) et la « Comment Noise-Key » (< 3.3.4). |
| **SEC-03** | WooCommerce 11.0.1 : DoS non authentifié | **Corrigé** | `<meta name="generator" content="WooCommerce 11.1.2">`, assets `?ver=11.1.2` ; 11.1.2 relevé dans le panneau. C'est la dernière version publiée (22/09, mise à jour de sécurité). |
| **SEC-04** | 2FA DirectAdmin désactivée | **Inchangé** | Relevé dans le panneau : 2FA désactivée, aucun code de secours. |
| **SEC-05** | Aucune sauvegarde planifiée ou externe | **Partiel** | Relevé dans le panneau : **une** sauvegarde manuelle du 23/09 (95,75 Mo) dans `/backups` sur le même serveur, aucune planification, aucune copie hors serveur. Elle contient la base clients et `wp-config.php` : elle disparaît ou fuit avec le compte. |
| **SEC-06** | `admin` exposé par `/wp/v2/users`, XML-RPC actif, pas de limite de tentatives | **Partiel** | Corrigé : `/wp-json/wp/v2/users`, `/?rest_route=/wp/v2/users` et `/wp-json/wp/v2/users/1` → **401** `rest_user_cannot_view` ; pingback absent de `system.listMethods` ; `hardening.php:54-57`. Encore ouvert : compte unique `admin` (relevé dans le panneau), **toujours lisible** via oEmbed (SEC-20) ; `POST /xmlrpc.php` `system.listMethods` → **200**, 78 méthodes dont `system.multicall`, donc PHP démarre à chaque appel (les méthodes authentifiées sont refusées par le code, ce qui n'a pas été testé en ligne) ; aucun blocage `.htaccess` (relevé dans le panneau) ; aucune limite de tentatives ni 2FA WordPress ; garde REST contournable par la casse (SEC-21). |
| **SEC-07** | Inscriptions ouvertes (WordPress + Mon compte) sans anti-bot | **Inchangé** | Relevé dans le panneau : « Tout le monde peut s'enregistrer » coché, création de compte sur Mon compte activée. En ligne : lien « Inscription » sur `wp-login.php`, `GET /wp-login.php?action=register` → 200 `registerform`, `/mon-compte/` → formulaire `woocommerce-form-register`. Point rassurant : rôle par défaut **Abonné** (relevé dans le panneau), l'hypothèse « critique » du 23/09 est écartée. |
| **SEC-08** | API Store `/wc/store/v1/checkout` contourne la validation tunisienne | **Inchangé** | Aucun hook `woocommerce_store_api_*` ni filtre `rest_endpoints` dans le dépôt (Grep). Validation uniquement classique : `famma-core.php:1107-1108`. En ligne : `GET /wp-json/wc/store/v1/checkout` → 401 `woocommerce_rest_missing_nonce`, donc la route est **active** (elle attend seulement le nonce Store API, public). L'index expose `/wc/store/v1/checkout` **et** l'alias `/wc/store/checkout` (GET, POST, PUT/PATCH). |
| **SEC-09** | Aucun en-tête de sécurité | **Inchangé** | `/`, `/produit/…`, `/retours/` : ni HSTS, ni CSP, ni `X-Frame-Options`, ni `nosniff`, ni `Referrer-Policy`, ni `Permissions-Policy`. Seuls `wp-login.php` et `/mon-compte/` envoient `X-Frame-Options: SAMEORIGIN` (cœur WordPress/WooCommerce). `.htaccess` sans en-tête (relevé dans le panneau). |
| **SEC-10** | SPF `~all`, pas de DKIM ni de DMARC | **Inchangé** | `v=spf1 a mx ip4:102.204.204.69 ~all` ; `_dmarc`, `x._domainkey`, `default._domainkey`, `dkim._domainkey`, `mail._domainkey` → NXDOMAIN, sur Google DNS **et** sur le serveur autoritaire `ns1.server-102-204-204-69.da.direct`. |
| **SEC-11** | `comin.tn` sur le même compte, `wp-config.php` en 644 | **Inchangé** | `wp-config.php` en 644 (relevé dans le panneau). Séparation de `comin.tn` non revérifiée. |
| **SEC-12** | `deploy.sh` enverrait `famma-local-mail.php` (filtre `wp_mail_from` inconditionnel) | **Corrigé** (code) | `famma-local-mail.php:56-62` : garde `'local' !== wp_get_environment_type()`. `deploy.sh:81-90` liste les mu-plugins un par un ; `:180` exclut `famma-local-mail.php` ; `--delete` reste borné à nos dossiers (`:196-198`). |
| **SEC-13** | Avis produit ouverts aux anonymes | **Non revérifiable** (partiel) | Le formulaire d'avis **n'est plus affiché** : l'onglet « Avis clients » n'apparaît qu'à partir d'un avis (`review_count` 0 sur l'API Store). Mais `GET /wp-json/wp/v2/product/208` → `"comment_status":"open"`. Le réglage « avis réservés aux propriétaires vérifiés » n'a pas été relevé dans le panneau, et `wp-comments-post.php` n'a pas été sollicité (ce serait un envoi de formulaire). |
| **SEC-14** | Versions divulguées, `readme.html`, `.po` lisibles | **Inchangé** (surface élargie) | Balises generator WordPress 7.1.2, WooCommerce 11.1.2, Site Kit 1.187.0, et `<generator>` du flux RSS ; `/readme.html` 200, `/license.txt` 200, `famma-core.pot` et `famma-core-ar.po` 200. **Nouveaux** : `themes/famma-child/languages/famma-child-ar.po` 200, `themes/famma-child/assets/fonts/README.md` 200 (déployé le 25/09), `plugins/litespeed-cache/readme.txt` 200 (« Stable tag: 7.9.1 »). |
| **SEC-15** | Token CAPI possible en base, champ `type="text"` | **Inchangé** (code) | `tracking-settings.php:247` toujours `type="text"` (fichier non modifié depuis le 24/08). |
| **SEC-16** | Cookie `famma_utm` sans `Secure` | **Inchangé** (code) | `famma-core.php:1638` : `…;path=/;max-age=…;SameSite=Lax`, sans `;Secure`. |
| **SEC-17** | WP-Cron déclenché par le trafic ; index `/wp-json/` de 1,4 Mo | **Partiel** (cron corrigé) | Cron système `*/5` actif (relevé dans le panneau) ; mu-plugin `famma-cron.php:26-29` (`DISABLE_WP_CRON` en production) ; `/wp-cron.php` → 200, 0 octet. L'index `/wp-json/` pèse toujours **1 447 162 octets, 731 routes**, mais LiteSpeed le met désormais en cache (`X-LiteSpeed-Cache-Control: public,max-age=604800`), ce qui atténue l'amplification. |
| **SEC-18** | oEmbed vidéo sans cache | **Corrigé** (code) | `product-video.php:251-256` : `get_transient()` / `set_transient()` (1 jour, 1 h si vide). Fichier déployé (accès direct → 200, 0 octet : garde ABSPATH). |
| **SEC-19** | `sanitize_text_field()` sur `REQUEST_URI` tronque les UTM | **Inchangé** (code) | `famma-legacy-urls.php:41-42` inchangé. Aucun impact sécurité. |
| **HEB-01** | 40 e-mails/jour sans SMTP | **Inchangé** | Relevé dans le panneau : 5 envoyés sur 40 aujourd'hui, aucun plugin SMTP. Toujours un point unique de défaillance face à SEC-01 et SEC-07. |

**Bilan (20 constats)** : 4 corrigés (SEC-02, 03, 12, 18), 3 partiels (SEC-05, 06, 17, dont le cron est corrigé), 1 non revérifiable (SEC-13), 12 inchangés, aucune régression. La surface a pourtant grandi : LiteSpeed Cache, newsletter, formulaire Retours et nouveaux fichiers lisibles (voir SEC-20 à SEC-25).

---

## 3. Points forts

**Code (partie A)**
- **Nouveau module `Hardening`** (`hardening.php`, chargé par `boot()` en `famma-core.php:2445`) : `xmlrpc_enabled` à false, méthodes pingback et en-tête `X-Pingback` retirés, `/wp/v2/users` refusé aux anonymes dans `rest_pre_dispatch`, donc après l'authentification, ce qui laisse fonctionner l'éditeur de blocs. Le docblock rappelle que ce code complète le blocage serveur sans le remplacer.
- **Newsletter exemplaire pour un formulaire public** (`newsletter.php`) : nonce vérifié (`:164-168`), honeypot `famma_nl_website` présent en ligne (`:171-173`), `sanitize_email` + `is_email` (`:175-179`), limite de 5 inscriptions par heure et par IP, où l'IP n'est stockée qu'en empreinte salée (`:241-254`). La réponse est la même pour un inscrit connu et un nouveau, donc pas d'énumération (`:187-192`), et **aucun e-mail n'est envoyé**, ce qui épargne le quota de 40/jour. Le type de contenu est réservé à `manage_options`, avec `create_posts => do_not_allow` et `show_in_rest => false`. L'export CSV passe par une capacité et `check_admin_referer`, et les formules de tableur sont neutralisées (`:338-343`, `:391-393`). La redirection de retour utilise `wp_validate_redirect` + `wp_safe_redirect`.
- **Formulaire Retours sans écriture** (`returns-content.php:303-358`) : motif et souhait sont transmis comme **rangs** d'une liste saisie par le propriétaire, les textes libres sont assainis et tronqués, et la cible est vérifiée (`https://wa.me/` obligatoire, sinon `wp_safe_redirect`). L'en-tête `Location` est posé à la main, mais sur une URL construite par `rawurlencode`, donc sans injection d'en-tête possible. L'absence de nonce est justifiée dans le docblock (aucune écriture).
- **SEC-12 corrigé proprement** (garde d'environnement + liste explicite des mu-plugins dans `deploy.sh`) ; **SEC-18 corrigé** (cache oEmbed en transient) ; **SEC-17** traité par un mu-plugin réservé à la production.
- Acquis du 23/09 toujours valables : séquence nonce, capacité, assainissement, échappement ; token CAPI jamais dans le HTML ni dans les journaux ; stock réservé seulement à la confirmation ; Purchase côté serveur, une seule fois.

**Site en ligne (partie B)**
- **Aucune version vulnérable publique connue** parmi les composants visibles : WordPress 7.1.2, WooCommerce 11.1.2, TranslatePress 3.3.6, LiteSpeed Cache 7.9.1 (corrige la SSRF non authentifiée CVE-2026-84761 et la XSS réfléchie CVE-2026-76579, toutes deux ≤ 7.9), Kadence 1.5.2.
- `/wp/v2/users` → 401 sous les trois formes testées ; `/?author=1` → 404 ; `/wp-sitemap-users-1.xml` → 404.
- `/.env`, `/.git/HEAD`, `/wp-content/debug.log` → 403 ; `/wp-content/uploads/`, `/wp-content/litespeed/`, `/wp-content/litespeed/debug/` → 403 (pas de listing). Les archives `famma-deploy-1.zip`, `famma-deploy-retours.zip`, `famma-deploy-retours-2.zip` et `famma-deploy-produit-onglets.zip` sous `/wp-content/` → **404**.
- Garde ABSPATH effective sur `includes/hardening.php` et `includes/product-video.php` (200, 0 octet).
- TLS inchangé et sain : Let's Encrypt YE1, `fammaminnou.tn` + `*.fammaminnou.tn`, valide jusqu'au **22/11/2026** (renouvellement automatique relevé le 23/09), TLS 1.3 (`TLS_AES_256_GCM_SHA384`) et TLS 1.2 (`ECDHE-ECDSA-AES128-GCM-SHA256`). `http://` → 301 vers `https://`.
- Cookies : `wordpress_test_cookie` en `secure; HttpOnly`. Aucun cookie posé sur les pages publiques en cache. Le point d'entrée du jeton express est servi en `no-store, private` : aucun jeton partagé par le cache.
- Le cache de page LiteSpeed (`X-LiteSpeed-Cache: hit`) absorbe désormais les pages publiques, ce qui rend un DoS applicatif plus difficile. Aucun défi anti-bot n'a été déclenché à cette cadence.

---

## 4. Tableau des constats (état au 25/09)

Sévérités : Critique / Élevée / Moyenne / Faible / Info. CVSS 3.1 approximatif lorsqu'il a un sens. Effort : S (< 1 h), M (demi-journée), L (> 1 jour). Les constats corrigés (SEC-02, SEC-03, SEC-12, SEC-18) ne sont plus détaillés : voir § 2.

### 4.1 Constats encore ouverts

| ID | Sévérité (CVSS) | Constat | Preuve (25/09) | Scénario d'exploitation | Correctif précis | Effort |
|---|---|---|---|---|---|---|
| **SEC-04** | **Élevée** (8,1 si les identifiants fuient) | 2FA DirectAdmin désactivée, sans code de secours. Le panneau contrôle les fichiers, les bases, les e-mails, le DNS **et l'unique sauvegarde** des deux domaines. | Relevé dans le panneau. | Mot de passe hameçonné ou réutilisé : lecture de la base clients, modification du DNS, suppression de la sauvegarde. | DirectAdmin > Two-Step Authentication (TOTP), codes de secours imprimés, mot de passe unique de 16 caractères ou plus. | S |
| **SEC-05** | **Élevée** | Une seule sauvegarde, manuelle, **sur le même serveur** ; aucune planification ni copie externe. | Relevé dans le panneau : `/backups/backup-Sep-23-2026-1.tar.zst` (95,75 Mo). | Compromission du compte, erreur humaine ou panne : la sauvegarde disparaît avec le site. Et si le compte fuit, elle offre la base clients complète en un seul fichier. | (1) Télécharger dès maintenant cette archive hors serveur (stockage chiffré), puis la retirer de `/backups`. (2) DirectAdmin > Backup > planification **quotidienne** vers une destination distante (FTP/SFTP distant ou stockage objet), avec rétention de 7 à 14 jours. (3) Demander à OXA Host ses propres sauvegardes (JetBackup ?) et leur rétention. (4) **Tester une restauration** sur le site Local. | M |
| **SEC-22** (nouveau) | **Élevée** | Mises à jour automatiques **désactivées sur les 5 extensions**, et Site Kit a déjà une version de retard. | Relevé dans le panneau. WooCommerce a publié **deux** versions de sécurité en quatre jours (11.1.1 le 18/09, 11.1.2 le 22/09). LiteSpeed Cache ≤ 7.9 avait une SSRF non authentifiée (corrigée le 02/09). | La prochaine faille non authentifiée restera ouverte jusqu'à ce que quelqu'un pense à la mise à jour. Le 23/09 montrait déjà 1 à 4 semaines de retard (SEC-02, SEC-03). | Activer les mises à jour auto de **TranslatePress, LiteSpeed Cache et Site Kit** tout de suite. Pour WooCommerce, dont les statuts COD dépendent : mises à jour auto **et** test du parcours COD après chaque version (ou, à défaut, alerte e-mail de sécurité WPScan/Patchstack et mise à jour sous 48 h). Passer Site Kit en 1.188.0. | S |
| **SEC-06** | **Moyenne** (6,5, en baisse depuis 7,3) | Surface d'authentification encore exposée : compte unique `admin` ; XML-RPC toujours servi par PHP ; ni limite de tentatives ni 2FA WordPress. | `POST /xmlrpc.php` `system.listMethods` → 200, 78 méthodes (dont `system.multicall`, `demo.sayHello`). `.htaccess` sans blocage (relevé dans le panneau). `wp-login.php` et `/mon-compte/` sans captcha ni limite. Login lisible (SEC-20, SEC-21). | Bruteforce sur `wp-login.php` et le formulaire Mon compte, un mot de passe par requête (le multicall authentifié est désormais refusé par le code). Appels anonymes non authentifiés à XML-RPC en rafale pour consommer PHP. | (1) Créer un administrateur au login imprévisible, **supprimer `admin`** en réattribuant ses contenus. (2) Coller le bloc `.htaccess` déjà prêt (`DEPLOIEMENT-2026-09-23.md` § 4.1) : `<Files xmlrpc.php> Require all denied </Files>`. (3) Limitation des tentatives (Limit Login Attempts Reloaded, ou protection WordPress d'Imunify360 / `WordPressProtect` de LiteSpeed si OXA Host la fournit). (4) Extension « Two-Factor » pour l'administrateur. | S |
| **SEC-01** | **Élevée** (6,5), risque business majeur | La commande express n'a toujours aucune défense anti-bot, et son jeton de checkout est servi à tout visiteur. | `express-order.php:98`, `:151-155` ; aucun honeypot, horodatage, limite IP ou téléphone ; `GET /?wc-ajax=famma_express_nonce` → 200 avec jeton. | 3 requêtes suffisent pour générer des centaines de fausses commandes COD : appels de confirmation perdus, quota de 40 e-mails épuisé (HEB-01), statistiques faussées. | Nouveau fichier `includes/anti-abuse.php` : honeypot + horodatage signé (`hash_hmac( 'sha256', $ts, wp_salt() )`, refus sous 3 s), limite IP par transient (3 commandes/heure), limite par téléphone normalisé (2 commandes ouvertes sur 24 h), plafond de quantité serveur (`woocommerce_add_to_cart_validation`, réglable). Le même motif que `Newsletter::within_rate_limit()` est réutilisable tel quel. Limite IP aussi sur `famma_express_nonce`. | M |
| **SEC-08** | **Moyenne** (5,3) | L'API Store (`/wc/store/v1/checkout` et l'alias `/wc/store/checkout`) contourne toujours la validation tunisienne, le coût interne, les UTM et tout futur anti-abus. | Aucun hook Store API dans le dépôt ; `GET /wp-json/wc/store/v1/checkout` → 401 « nonce manquant » (route active). | Commande COD par l'API avec un téléphone fantaisiste ou un pays étranger, qui échappe à SEC-01. | Le site utilise le checkout classique : retirer `/wc/store/v1/checkout`, `/wc/store/checkout` et leurs variantes `(?P<id>…)` via le filtre `rest_endpoints` pour les requêtes non authentifiées. Activer aussi `woocommerce_store_api_rate_limit_options` (`enabled => true, limit => 5, seconds => 60`). | S-M |
| **SEC-20** (nouveau) | **Moyenne** (5,3 : AV:N/AC:L/PR:N/UI:N/C:L) | **L'identifiant `admin` reste public via oEmbed**, ce qui annule le masquage de `/wp/v2/users`. | `GET /wp-json/oembed/1.0/embed?url=https://fammaminnou.tn/` → 200, `"author_name":"admin"`, `"author_url":"https://fammaminnou.tn/author/admin/"`. Lien de découverte `application/json+oembed` présent dans chaque page. | Un robot lit le login en une requête, puis passe au bruteforce (SEC-06). | Dans `Hardening` : filtre `oembed_response_data` qui retire `author_name` et `author_url` (ou les remplace par le nom du site). Mais le vrai correctif est le remplacement du compte `admin` (SEC-06 point 1). | S |
| **SEC-21** (nouveau, code) | **Moyenne** (5,3, latent) | **Le filtre `/wp/v2/users` se contourne par la casse** : `Hardening::guard_users_route()` compare la route avec `str_starts_with()` (sensible à la casse), alors que le routeur REST de WordPress accepte les routes **sans tenir compte de la casse**. | `hardening.php:105` ; cœur : `class-wp-rest-server.php:1079` (`rest_pre_dispatch` avant l'appariement) et `:1172` (`preg_match( '@^' . $route . '$@i' … )`). `WP_REST_Request::get_route()` renvoie le chemin brut. **Non testé en ligne, volontairement** : ce serait exploiter le contournement. | Une requête sur la route écrite avec une autre casse (par exemple `/wp/v2/Users`) échappe au filtre et atteint le contrôleur Utilisateurs, qui liste les auteurs de contenus publiés, dont `admin`. | Comparer `strtolower( $request->get_route() )`, ou mieux, filtrer sur la route **appariée** : `rest_dispatch_request` reçoit le motif (`$route`) réellement retenu. Autre option : retirer `/wp/v2/users*` de `rest_endpoints` pour les visiteurs non connectés. Ajouter un test (casse, barre finale, `?rest_route=`). | S |
| **SEC-07** | **Moyenne** | Inscriptions ouvertes sur WordPress **et** sur Mon compte, sans anti-bot, avec 40 e-mails par jour. | Relevé dans le panneau ; `wp-login.php?action=register` → 200 `registerform` ; `/mon-compte/` → `woocommerce-form-register`. | Inscriptions de robots : chaque compte envoie des e-mails, le quota s'épuise et **les confirmations de commande réelles ne partent plus**. | Réglages > Général : décocher « Tout le monde peut s'enregistrer ». WooCommerce > Réglages > Comptes : désactiver la création sur « Mon compte ». On peut garder la création au checkout, que `famma-core` conditionne déjà à un e-mail (`famma-core.php:1108`, `require_email_for_account`). Purger les comptes Abonné inconnus. | S |
| **SEC-09** | **Moyenne** (4,3) | Aucun en-tête de sécurité sur les pages publiques. | En-têtes de `/`, `/produit/…`, `/retours/` ; `.htaccess` (relevé dans le panneau). | Clickjacking du formulaire express, SSL strip au premier accès, fuite du référent. | Bloc `.htaccess` prêt (`DEPLOIEMENT-2026-09-23.md` § 4.1), à placer **avant** `# BEGIN LSCACHE` pour que les réponses en cache le portent aussi : HSTS (`max-age=300` puis `31536000`), `nosniff`, `X-Frame-Options SAMEORIGIN`, `Referrer-Policy strict-origin-when-cross-origin`, `Permissions-Policy`. CSP d'abord en `Report-Only`. Purger le cache LiteSpeed ensuite. | S |
| **SEC-10** | **Moyenne** | SPF `~all`, ni DKIM ni DMARC. | nslookup (Google DNS + serveur autoritaire). | Hameçonnage « confirmez / payez votre commande » au nom de `@fammaminnou.tn` ; e-mails de la boutique classés en spam. | DirectAdmin > E-mail Manager > DKIM (sélecteur `x`) ; `_dmarc` TXT `v=DMARC1; p=none; rua=mailto:contact@fammaminnou.tn`, puis `quarantine` ; SPF `-all` ensuite. | S |
| **SEC-11** | **Moyenne** | `wp-config.php` en 644 ; `comin.tn` sur le même compte (non revérifié). | Relevé dans le panneau (644). | Une faille sur `comin.tn` ou un autre processus du compte lit les identifiants de base et les sels. | `wp-config.php` en 600 (ou 440). Séparer `comin.tn` dans un autre utilisateur DirectAdmin. | S-M |
| **HEB-01** | **Moyenne** | Quota de 40 e-mails/jour, sans SMTP transactionnel. | Relevé dans le panneau : 5/40, aucun plugin SMTP. | SEC-01 ou SEC-07 épuisent le quota en quelques minutes ; les clients ne reçoivent plus rien. | SMTP transactionnel authentifié (Brevo, Mailjet, SES…) via une extension SMTP, avec DKIM du fournisseur ; ou demander le relèvement du quota à OXA Host. | M |
| **SEC-13** | **Moyenne** (non revérifiable) | Les avis restent ouverts au niveau du produit (`comment_status: open`), et le réglage « propriétaires vérifiés » n'est pas confirmé. | `GET /wp-json/wp/v2/product/208` → `"comment_status":"open"` ; formulaire non affiché tant qu'il n'y a aucun avis. | Faux avis ou spam déposés directement sur `wp-comments-post.php`, même sans formulaire visible. Contraire à la règle §58/§60. | WooCommerce > Produits : « avis réservés aux propriétaires vérifiés » ; Réglages > Discussion : modération manuelle. Vérifier les deux dans le panneau. | S |
| **SEC-14** | **Faible** | Versions divulguées et fichiers internes lisibles, en nombre croissant. | Generator ×3 + RSS ; `readme.html`, `license.txt`, `.pot`, `famma-core-ar.po`, `famma-child-ar.po`, `assets/fonts/README.md`, `litespeed-cache/readme.txt` → 200. | Ciblage automatique des versions par les robots dès la prochaine faille (voir SEC-22). | Dans `Hardening` : filtre `the_generator` renvoyant `''` (couvre la balise WordPress, celle que WooCommerce y ajoute et le `<generator>` du RSS), plus le retrait de la balise de Site Kit (qu'il imprime lui-même sur `wp_head`). `.htaccess` : `<FilesMatch "\.(po|pot|md|txt)$|^readme\.html$"> Require all denied </FilesMatch>`, en excluant `robots.txt` et `llms.txt` s'ils existent. **Exclure les `.md` des archives de déploiement.** | S |
| **SEC-17** | **Info** | Index `/wp-json/` de 1,45 Mo et 731 routes, servi aux anonymes (désormais en cache). | `GET /wp-json/` → 200, 1 447 162 octets, `public,max-age=604800`. | Cartographie complète des extensions (`litespeed/v1`, `litespeed/v3`, `wc/pos/v1`, `wp-abilities/v1`…). | Restreindre l'index racine aux connectés (`rest_index` ou `rest_pre_dispatch` sur `/`) si rien de public n'en dépend. | S |
| **SEC-15** | **Faible** | Le champ du token CAPI est en `type="text"` et le token peut vivre en base. | `tracking-settings.php:247`. | Le token fuit par une sauvegarde de base (SEC-05) ou un export. | Constante serveur seulement, option vidée, champ en `type="password"`. | S |
| **SEC-16** | **Faible** | Cookie `famma_utm` sans `Secure`. | `famma-core.php:1638`. | Faible impact (attribution). | Ajouter `;Secure`. | S |
| **SEC-23** (nouveau) | **Faible** | **Le nonce de la newsletter est imprimé dans une page en cache 7 jours**, alors qu'un nonce WordPress ne vit que 12 à 24 h. | Accueil `X-LiteSpeed-Cache: hit` avec `famma_nl_nonce` dans le HTML ; TTL LiteSpeed public de 604 800 s. `newsletter.php:166-168` renvoie `expired` si le nonce est périmé. Déjà signalé dans `DEPLOIEMENT-2026-09-23.md` (ligne 246), mais non traité. | Passé 24 h, les vrais visiteurs reçoivent « expiré » et l'inscription échoue. La tentation serait alors de retirer le nonce, comme sur le formulaire Retours, où c'était justifié parce qu'il n'écrit rien. | Servir le nonce par un appel non caché (même principe que `famma_express_nonce`), ou déclarer l'action dans LiteSpeed Cache > ESI > « ESI Nonces » (`famma_newsletter*`), ou purger l'accueil toutes les 12 h. Garder le honeypot et la limite IP. | S |
| **SEC-24** (nouveau) | **Faible** | Newsletter **sans double opt-in ni preuve de consentement** : on peut inscrire l'adresse d'un tiers. | `newsletter.php:195-203` n'enregistre que l'adresse, la langue et la date (ni source ni case de consentement). | Liste polluée ; en cas de plainte (loi tunisienne 2004-63, INPDP), aucune preuve de consentement. Envois futurs vers des adresses jamais consenties. | Enregistrer une méta de consentement (horodatage + page d'origine, sans IP en clair). Mettre le double opt-in en place une fois le SMTP (HEB-01) en service, pour ne pas consommer le quota de 40 e-mails. | M |
| **SEC-25** (nouveau) | **Info** | **Nouvelle surface LiteSpeed Cache** : 11 routes REST publiques (`litespeed/v1/guest/sync`, `tool/check_ip`, `v3/ping`, `v3/wp_rest_echo`, `notify_img`…) et un historique de failles fréquent. | Index `/wp-json/` ; `readme.txt` « Stable tag: 7.9.1 ». Version courante corrigée (CVE-2026-84761 SSRF non authentifiée, CVE-2026-76579 XSS, ≤ 7.9). | Rien d'exploitable à ce jour, mais c'est l'extension la plus exposée du site, et elle n'a pas de mise à jour automatique (SEC-22). | Mises à jour auto de LiteSpeed Cache (SEC-22). Désactiver dans ses réglages les services QUIC.cloud non utilisés (CDN, optimisation d'images en ligne, Guest Mode), pour réduire la surface. Vérifier ensuite dans l'index `/wp-json/` quelles routes restent. | S |
| **SEC-19** | **Info** | `sanitize_text_field()` sur `REQUEST_URI` tronque les UTM encodés. | `famma-legacy-urls.php:41-42`. | Perte d'attribution, sans impact sécurité. | `esc_url_raw( wp_unslash( … ) )` ou reconstruction avec `rawurlencode`. | S |

**Aucun constat critique** n'a été observé passivement. SEC-04 + SEC-06 + SEC-20 forment toutefois une **chaîne** (login connu, aucune limite, aucune 2FA, sauvegarde sur place) qui deviendrait critique à la première fuite de mot de passe.

### 4.2 Constats corrigés depuis le 23/09

| ID | Correction constatée |
|---|---|
| SEC-02 | TranslatePress 3.3.6 |
| SEC-03 | WooCommerce 11.1.2 |
| SEC-12 | Garde d'environnement + liste explicite des mu-plugins dans `deploy.sh` |
| SEC-18 | Cache oEmbed en transient, déployé |
| SEC-17 (cron) | Cron système `*/5` + `DISABLE_WP_CRON` en production |

---

## 5. Checklist de durcissement (mise à jour du 25/09)

### Hébergement / DirectAdmin
- [ ] 2FA TOTP sur DirectAdmin + codes de secours + mot de passe unique (SEC-04).
- [ ] Sauvegardes quotidiennes **hors serveur**, chiffrées, restauration testée ; sortir l'archive du 23/09 de `/backups` (SEC-05). *Partiel : 1 sauvegarde manuelle sur place.*
- [ ] `wp-config.php` en 600 ; séparer `comin.tn` (SEC-11).
- [x] Tâche cron serveur toutes les 5 min + `DISABLE_WP_CRON` (SEC-17).
- [ ] DKIM, DMARC `p=none`, puis SPF `-all` (SEC-10) ; SMTP transactionnel ou relèvement du quota de 40/jour (HEB-01).
- [ ] Demander à OXA Host si la protection anti-bruteforce WordPress d'Imunify360 ou `WordPressProtect` de LiteSpeed est active sur le compte (SEC-06).

### Serveur web (`.htaccess` LiteSpeed)
- [ ] Bloc « FAMMA securite » de `DEPLOIEMENT-2026-09-23.md` § 4.1 (en-têtes, `xmlrpc.php`, `readme.html`), placé avant `# BEGIN LSCACHE`, puis cache purgé (SEC-06, SEC-09).
- [ ] Refuser aussi `license.txt`, `*.po`, `*.pot`, `*.md` et les `readme.txt` d'extensions (SEC-14).
- [ ] PHP interdit dans `wp-content/uploads/` (`uploads/.htaccess`).
- [x] Archive de déploiement publique `famma-deploy-1.zip` supprimée (404) ; les archives du 25/09 ne sont pas accessibles sous `/wp-content/` (404). Vider la corbeille DirectAdmin.

### WordPress (`wp-config.php`)
- [ ] `DISALLOW_FILE_EDIT` et `FORCE_SSL_ADMIN` à `true` (à confirmer dans le panneau).
- [ ] `WP_DEBUG` à `false` en prod (le `debug.log` reste en 403).
- [ ] `WP_ENVIRONMENT_TYPE` à `'production'` : **prérequis de `famma-cron.php`** (sinon `DISABLE_WP_CRON` n'est pas posé, sauf si la constante est déjà définie ailleurs).
- [x] WooCommerce ≥ 11.1.0 et TranslatePress ≥ 3.3.6 (SEC-02, SEC-03).
- [ ] **Mises à jour automatiques des extensions** : TranslatePress, LiteSpeed Cache, Site Kit tout de suite ; WooCommerce avec test COD (SEC-22). Site Kit en 1.188.0.

### Comptes et authentification
- [ ] Remplacer `admin` par un login imprévisible (SEC-06, SEC-20).
- [ ] Limitation des tentatives + 2FA WordPress (SEC-06).
- [x] `/wp/v2/users` masqué aux anonymes, **sous réserve de SEC-21** (casse).
- [ ] Retirer `author_name` et `author_url` d'oEmbed (SEC-20).
- [ ] Inscriptions WordPress fermées, pas d'inscription sur Mon compte (SEC-07). *Rôle par défaut Abonné confirmé dans le panneau.*
- [ ] Mots de passe d'application désactivés s'ils sont inutilisés (`wp_is_application_passwords_available`).

### WooCommerce / FAMMA
- [ ] Anti-abus du checkout et de la commande express (SEC-01).
- [ ] API Store : routes checkout retirées pour les anonymes + limitation native (SEC-08).
- [ ] Avis réservés aux propriétaires vérifiés + modération (SEC-13).
- [x] `famma-local-mail.php` et `deploy.sh` corrigés (SEC-12).
- [ ] Token CAPI en constante seulement, champ en `password` (SEC-15) ; cookie UTM `Secure` (SEC-16).
- [ ] Nonce de la newsletter servi hors cache (SEC-23) ; consentement enregistré (SEC-24).

---

## 6. Quick wins (dans l'ordre, environ 1 h 45)

1. **Mises à jour automatiques** de TranslatePress, LiteSpeed Cache et Site Kit, puis Site Kit en 1.188.0. Décider pour WooCommerce (auto + test COD, ou alerte de sécurité). *(10 min, SEC-22)*
2. **2FA DirectAdmin** + codes de secours. *(5 min, SEC-04)*
3. **Fermer les inscriptions** (Réglages > Général, WooCommerce > Comptes) et passer les avis en « propriétaires vérifiés ». *(10 min, SEC-07, SEC-13)*
4. **Coller le bloc `.htaccess` déjà rédigé** (§ 4.1 du journal du 23/09), étendu aux `*.po`, `*.pot`, `*.md` et `license.txt`, puis purger LiteSpeed. Contrôle : accueil avec `x-frame-options`, `/xmlrpc.php` et `/readme.html` en 403. *(15 min, SEC-06, SEC-09, SEC-14)*
5. **Remplacer le compte `admin`** (nouvel administrateur, suppression d'`admin` avec réattribution), puis poser une limitation des tentatives. *(20 min, SEC-06, SEC-20)*
6. **Sauvegarde** : télécharger l'archive du 23/09 hors serveur, la retirer de `/backups`, planifier une sauvegarde quotidienne distante. *(30 min, SEC-05)*
7. **DNS** : DKIM + DMARC `p=none`. *(10 min, SEC-10)*
8. **`wp-config.php` en 600.** *(2 min, SEC-11)*
9. **Code, prochain lot** : SEC-21 (casse) + SEC-20 (oEmbed) + generator (SEC-14) dans `Hardening` *(S)* ; puis SEC-08 (retrait des routes checkout de l'API Store) et SEC-01 (anti-abus, en réutilisant `Newsletter::within_rate_limit()`) *(M)* ; puis SEC-23. Validation du §11 : `php -l`, `phpcs`, `debug.log`, parcours COD complet.

---

## Annexe — détails des contrôles en ligne (25/09, 17:54-17:59)

| Requête | Résultat |
|---|---|
| `/` (en cache LiteSpeed, `hit`) | 200 ; aucun en-tête de sécurité ; generator WP 7.1.2, WooCommerce 11.1.2, Site Kit 1.187.0 ; `?ver=` TranslatePress 3.3.6, Kadence 1.5.2 ; formulaire newsletter avec `famma_nl_nonce` + piège `famma_nl_website` |
| `/produit/coupe-legumes-4-en-1/` (`hit`) | 200 ; formulaire express sans piège ni captcha ; `nonceUrl: /?wc-ajax=famma_express_nonce` (aucun jeton de checkout dans le HTML) ; aucun formulaire d'avis affiché |
| `GET /xmlrpc.php` | 405 « POST requests only » |
| `POST /xmlrpc.php` `system.listMethods` (sans identifiant) | 200, 78 méthodes, `system.multicall` présent, **aucune méthode `pingback.*`** |
| `/wp-json/wp/v2/users`, `/?rest_route=/wp/v2/users`, `/wp-json/wp/v2/users/1` | **401** `rest_user_cannot_view` (non caché) |
| `/wp-json/oembed/1.0/embed?url=https://fammaminnou.tn/` | 200, `author_name: admin`, `author_url: /author/admin/` |
| `/?author=1`, `/wp-sitemap-users-1.xml` | 404 |
| `/wp-json/` | 200, 1 447 162 octets, 731 routes, 21 espaces de noms (dont `litespeed/v1`, `litespeed/v3`, `wc/pos/v1/catalog`, `wp-abilities/v1`, `jetpack/v4`) ; en cache public 7 jours |
| `/wp-json/wc/store/v1/` | 200 ; routes `/checkout` et alias `/wc/store/checkout` (GET, POST, PUT/PATCH) |
| `GET /wp-json/wc/store/v1/checkout` | 401 `woocommerce_rest_missing_nonce` (route active) |
| `GET /?wc-ajax=famma_express_nonce` | 200 `{"success":true,"data":{"nonce":"…"}}`, `Cache-Control: no-store, private` |
| `/wp-json/wp/v2/product/208?_fields=…` | 200 `comment_status: open`, `ping_status: closed` |
| `/wp-json/wc/store/v1/products?per_page=1` | `review_count: 0` |
| `/wp-login.php` | 200 ; `X-Frame-Options`, `CSP frame-ancestors`, `Referrer-Policy` ; cookie de test `secure; HttpOnly` ; lien « Inscription » |
| `/wp-login.php?action=register` | 200, `registerform` actif |
| `/mon-compte/` | 200 ; formulaires connexion + inscription (nonces WooCommerce), aucun anti-bot ; `X-Frame-Options` |
| `/wp-admin/` | 302 → `wp-login.php?…&reauth=1` |
| `/retours/` (`hit`) | 200 ; formulaire `famma_return[…]` sans nonce (voulu, aucune écriture) |
| `/feed/` | 200, `<generator>https://wordpress.org/?v=7.1.2` |
| `/readme.html`, `/license.txt` | 200 |
| `famma-core.pot`, `famma-core-ar.po`, `famma-child-ar.po`, `famma-child/assets/fonts/README.md`, `litespeed-cache/readme.txt` | 200 (lisibles) |
| `/wp-content/debug.log`, `/.env`, `/.git/HEAD` | 403 |
| `/wp-content/uploads/`, `/wp-content/litespeed/`, `/wp-content/litespeed/debug/` | 403 (pas de listing) |
| `/wp-content/famma-deploy-1.zip`, `famma-deploy-retours.zip`, `famma-deploy-retours-2.zip`, `famma-deploy-produit-onglets.zip` | 404 |
| `/wp-cron.php` | 200, 0 octet |
| `includes/product-video.php`, `includes/hardening.php` (accès direct) | 200, 0 octet (garde ABSPATH) |
| `http://fammaminnou.tn/` | 301 → `https://fammaminnou.tn/` |
| TLS | Let's Encrypt YE1, SAN `fammaminnou.tn` + `*.fammaminnou.tn`, du 24/08 au 22/11/2026 ; TLS 1.3 `TLS_AES_256_GCM_SHA384`, TLS 1.2 `ECDHE-ECDSA-AES128-GCM-SHA256` |
| DNS | SPF `v=spf1 a mx ip4:102.204.204.69 ~all` ; `_dmarc`, `x`/`default`/`dkim`/`mail._domainkey` NXDOMAIN (Google DNS et `ns1.server-102-204-204-69.da.direct`) ; MX `mail.fammaminnou.tn` |

**Anti-bot.** 43 requêtes espacées de 4 à 10 s : aucune page de défi (corps < 2 Ko sans `<title>`, ou mention imunify/captcha). Les requêtes non cachées (REST, XML-RPC, 404) ont pris de 2 à 5,8 s, contre 0,1 à 0,3 s pour les pages et fichiers statiques en cache.

**Versions et vulnérabilités publiques** (consultées le 25/09/2026) :
- [WooCommerce 11.1.2 — mise à jour de sécurité du 22/09](https://developer.woocommerce.com/2026/09/22/woocommerce-11-1-2/) ; [11.1.1 — mise à jour de sécurité du 18/09](https://developer.woocommerce.com/2026/09/18/woocommerce-11-1-1/)
- [TranslatePress — CVE-2026-89412, XSS stockée non authentifiée ≤ 3.3.5](https://www.strix.ai/cve/CVE-2026-89412) ; [historique WPScan](https://wpscan.com/plugin/translatepress-multilingual/)
- [LiteSpeed Cache — correctif CVE-2026-84761 (SSRF ≤ 7.9), 02/09](https://blog.litespeedtech.com/2026/09/02/security-update-for-lscwp-cve-2026-84761/) ; [CVE-2026-76579 (XSS réfléchie ≤ 7.9)](https://www.strix.ai/cve/CVE-2026-76579) ; [historique WPScan](https://wpscan.com/plugin/litespeed-cache/)
- Site Kit 1.188.0 : contenu sécurité de la version non trouvé (recherche non exhaustive).
