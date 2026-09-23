# Audit de sécurité — FAMMA (fammaminnou.tn)

- **Date** : 23/09/2026, contrôles en ligne entre 02:00 et 02:15 UTC
- **Nature** : audit en **lecture seule, strictement passif**, autorisé par le propriétaire. Aucune connexion tentée, aucun formulaire soumis, aucune charge malveillante, aucun scanner.
- **Périmètre A (code)** : dépôt `E:\Ecom\famma-mennou-foundation\famma-mennou`, branche `feat/maquette-fidelite-fiche-produit` (dernier commit `2e40acc`, 28/08) : `famma-core` (fichier principal + 8 fichiers `includes/`), `famma-child`, `mu-plugins`, `scripts/`, `scripts/deploy/`.
- **Périmètre B (site)** : https://fammaminnou.tn (LiteSpeed, OXA Host, DirectAdmin). Environ 60 requêtes HTTP espacées, 5 poignées de main TLS, 7 requêtes DNS.
- **Faits relevés par le coordinateur dans DirectAdmin** (intégrés tels quels, non re-testés) : 2FA DirectAdmin désactivée ; ModSecurity actif sans exclusion ; certificat `fammaminnou.tn` + `*.fammaminnou.tn` en renouvellement automatique ; en prod, seulement les extensions famma-core, woocommerce, translatepress-multilingual et google-site-kit (aucune extension de sécurité, de sauvegarde ni SMTP) ; `wp-config.php` en 644 ; aucune tâche cron ; aucune sauvegarde planifiée visible ; second domaine `comin.tn` sur le même compte ; envoi limité à 40 e-mails/jour ; aucun dump dans `public_html` ; mu-plugins en prod : `famma-env.php` et `famma-legacy-urls.php` ; aucun redéploiement depuis le 25/08.

> Méthode graph-first : `graphify query "nonce ajax rest endpoint handler"` a localisé `Express_Order`, `send_nonce()`, `Tracking_Events`. Le graphe (25/08) était antérieur à `product-video.php` et à la grille de sécurité complète, d'où un **graph miss partiel** : le reste a été cherché par Grep ciblé (superglobales, hooks AJAX/REST/admin-post, nonces, capacités, redirections, `wp_remote_*`, `$wpdb`, `unserialize`), puis lu fichier par fichier. `phpcs` (ruleset WordPress du projet) a aussi tourné sur les 46 fichiers PHP.

---

## 1. Note globale : **5 / 10**

| Axe | Note | Justification |
|---|---|---|
| Code écrit par FAMMA | **8,5 / 10** | La séquence du §4 (nonce, capacité, assainissement, échappement, pas de SQL concaténé) est appliquée partout. `phpcs` WordPress, sniffs de sécurité compris, ne signale **aucune erreur** sur 46 fichiers. Le token CAPI est bien protégé. Deux lacunes de logique métier : la commande express ne se défend pas contre les bots, et la validation tunisienne ne s'applique pas à l'API Store. |
| Exploitation (prod) | **3 / 10** | WooCommerce et TranslatePress ont des failles publiques **non authentifiées** non corrigées. Pas de sauvegarde, pas de 2FA sur le panneau, aucune protection anti-bruteforce alors que l'identifiant `admin` est exposé. Aucun en-tête de sécurité, pas de DMARC ni de DKIM, inscriptions ouvertes avec un quota de 40 e-mails/jour. |

Le code applicatif est nettement au-dessus de la moyenne des boutiques WooCommerce. **Le risque réel vient de l'exploitation** (mises à jour, authentification, sauvegardes, e-mail) **et de l'abus métier** (fausses commandes COD). Ces deux fronts se corrigent en quelques heures pour l'essentiel (voir § 5, quick wins).

---

## 2. Points forts

**Code (partie A)**
- **Écritures admin toutes protégées par nonce + capacité** : champs financiers `famma-core.php:631-639`, FAQ produit `famma-core.php:781-789`, vidéo produit `includes/product-video.php:157-165`, méta SEO `includes/seo-admin.php:378-384`, rafraîchissement du tableau de bord (`admin-post`) `famma-core.php:1951-1953`. Les écrans de réglages passent par la Settings API (`options.php` : nonce + `manage_options`).
- **Entrées** : `wp_unslash()` puis `sanitize_*` / `wc_clean` partout, y compris sur les cookies (`famma-core.php:1471`, `1509`) et `$_SERVER` (`famma-core.php:1513-1518`, IP validée par `FILTER_VALIDATE_IP`). Les champs du cookie UTM sont vérifiés un par un et tronqués (`famma-core.php:1476-1485`).
- **Sorties** : échappement tardif systématique. Les deux `phpcs:ignore` d'échappement sont justifiés : `seo.php:648` (text/plain) et `themes/famma-child/inc/product-video.php:75` (balisage `<video>` construit avec `esc_url` ou oEmbed WordPress d'un fournisseur autorisé).
- **SQL** : une seule requête directe (`inc/shop-widgets.php:79`), statique, sans aucune entrée utilisateur. Aucun `unserialize`, `eval`, `exec`, upload de fichier ni route REST maison.
- **Redirections** : uniquement `wp_safe_redirect()`. La redirection 301 des anciennes URL (`mu-plugins/famma-legacy-urls.php:75-96`) vise une page WooCommerce du même hôte : **pas de redirection ouverte**.
- **Token Meta CAPI** : il circule dans le **corps** de la requête et jamais dans l'URL (`famma-core.php:1786`). Il n'est jamais journalisé (les notes de commande ne reprennent que le message de Meta, `1829-1840`) et jamais réaffiché (seuls ses 4 derniers caractères le sont, `tracking-settings.php:250`, `266`). Un champ vide conserve la valeur ; l'option est en `autoload = no`. **Vérifié en ligne** : 0 occurrence de `access_token`, de motif `EAA…` ou de code de test dans le HTML public.
- **Logique COD saine** : la commande n'est payée qu'à « livré » ; le stock n'est réservé **qu'à la confirmation** (`famma-core.php:494`, `509-511`), donc une fausse commande ne bloque pas le stock. Purchase part côté serveur, une seule fois (`_famma_purchase_sent`).
- **Archives d'auteur en 404** (`seo.php:487-504`) et fournisseur « utilisateurs » retiré du plan de site (`seo.php:515-517`).
- **Git** : ni `.env` ni dump SQL, clé ou sauvegarde dans l'historique (`backups/` ne contient que `.gitkeep`). `.env.deploy` est ignoré. Le mode FTP de `deploy.sh` impose FTPS et passe le mot de passe par stdin, pas en argument (`deploy.sh:250-260`).

**Site en ligne (partie B)**
- TLS : certificat Let's Encrypt (émetteur YE1) couvrant `fammaminnou.tn` + `*.fammaminnou.tn`, valide du 24/08 au **22/11/2026**, TLS 1.2 et 1.3 (ECDHE-ECDSA-AES128-GCM, TLS_AES_256_GCM_SHA384). `http→https` et `www→apex` en 301.
- `/.env`, `/.git/HEAD`, `/wp-content/debug.log`, `/wp-config.php.bak`, `/wp-config.php~` → **403**. Pas de listing de répertoires (`/wp-content/uploads/`, `/wp-includes/`, `uploads/wc-logs/`, `uploads/woocommerce_uploads/` → 403 ; `/wp-content/plugins/` → 200 avec 0 octet).
- La garde `ABSPATH` fonctionne : `famma-core.php`, `includes/express-order.php` et `mu-plugins/famma-env.php` en accès direct renvoient 200 avec **0 octet**.
- `famma-local-mail.php` est **absent de la prod** (404), comme il se doit.
- WordPress **7.1.2** à jour (correctif LFI→RCE CVE-2026-87902 sorti le 22/09, appliqué automatiquement). Site Kit 1.187.0 inclut le correctif CSRF.
- Pas de `X-Powered-By`, `Server: LiteSpeed` sans version. ModSecurity actif (fait DirectAdmin). `wp-login.php` et `/mon-compte/` envoient `X-Frame-Options: SAMEORIGIN`.

---

## 3. Tableau des constats

Sévérités : Critique / Élevée / Moyenne / Faible / Info. CVSS 3.1 approximatif lorsqu'il a un sens. Effort : S (< 1 h), M (demi-journée), L (> 1 jour).

| ID | Sévérité (CVSS) | Constat | Preuve | Scénario d'exploitation | Correctif précis | Effort |
|---|---|---|---|---|---|---|
| **SEC-01** | **Élevée** (6,5 : AV:N/AC:L/PR:N/UI:N/I:L/A:L), risque business majeur | **Commande express sans aucune défense anti-bot** : pas de honeypot, captcha, limitation de débit, détection de doublon (même téléphone), ni plafond de quantité. Le jeton de checkout est distribué à tout visiteur par un point d'entrée public. | `includes/express-order.php:97` (`wc_ajax_famma_express_nonce`), `:130-134` (`send_nonce`), `:343-396` (formulaire sans piège), `:356-364` (quantité sans `max`). **En ligne** : formulaire présent sur `/produit/tapis-pique-nique-xl/`, `GET /?wc-ajax=famma_express_nonce` → 200 `{"success":true,"data":{"nonce":"…"}}`, aucune trace de captcha, Turnstile ou honeypot. | Un script de 3 requêtes (add_to_cart → nonce → checkout) injecte des centaines de fausses commandes COD : appels de confirmation perdus, quota de 40 e-mails/jour épuisé par les notifications « Nouvelle commande », tableau de bord et attribution publicitaire faussés. | Ajouter dans `famma-core` un fichier `includes/anti-abuse.php` branché sur `woocommerce_after_checkout_validation` **et** sur l'API Store (voir SEC-08). Il doit poser : (1) un champ honeypot + un horodatage signé `hash_hmac( 'sha256', $ts, wp_salt() )` refusé sous 3 s ; (2) une limite par IP via transient (`famma_rl_{md5(ip)}`, ex. 3 commandes/heure) ; (3) une limite par téléphone normalisé (ex. 2 commandes ouvertes sur 24 h) ; (4) un plafond de quantité réglable depuis l'admin (`woocommerce_add_to_cart_validation`, ex. 5). Appliquer la même limite IP au point d'entrée `famma_express_nonce`. Option : Cloudflare Turnstile sur le formulaire express et le checkout. | M |
| **SEC-02** | **Élevée** (7,2 ×2) | **TranslatePress 3.3.3 est vulnérable à deux XSS stockées non authentifiées** : « Comment Noise-Key Injection » (< 3.3.4, publiée le 27/08) et « Translation Memory Suggestion Panel » (< 3.3.6, publiée le 21/09). | HTML public : `translatepress-multilingual/...?ver=3.3.3`. Base WPScan. | Un anonyme dépose une charge dans un commentaire ou un avis (le formulaire d'avis est ouvert, cf. SEC-13). Elle s'exécute quand l'administrateur ouvre l'éditeur de traduction, ce qui permet de créer un administrateur ou d'installer une extension (le site entier est alors compromis). | Mettre à jour TranslatePress en **≥ 3.3.6** immédiatement. Activer les mises à jour automatiques de cette extension. | S |
| **SEC-03** | **Élevée** (7,5) | **WooCommerce 11.0.1 est exposé au déni de service non authentifié CVE-2026-48888** (corrigé en 11.1.0, le 07/09). | `<meta name="generator" content="WooCommerce 11.0.1">`, assets `?ver=11.0.1`. Base WPScan. | Une requête forgée sans compte rend la boutique indisponible, par exemple pendant une campagne Meta. | Mettre à jour WooCommerce en **≥ 11.1.0** (tester d'abord le parcours COD en local, puisque les statuts personnalisés dépendent des filtres de paiement), puis activer les mises à jour automatiques mineures. | S |
| **SEC-04** | **Élevée** (8,1 si les identifiants fuient) | **Double authentification DirectAdmin désactivée.** Le panneau donne accès aux fichiers, aux bases, aux e-mails et au DNS des **deux** domaines. | Fait DirectAdmin n°1. | Un mot de passe hameçonné ou réutilisé donne la prise de contrôle totale : lecture de la base clients, modification du DNS, défiguration. | Activer la 2FA (TOTP) dans DirectAdmin, choisir un mot de passe unique de 16 caractères ou plus dans un gestionnaire, et vérifier l'e-mail de récupération. | S |
| **SEC-05** | **Élevée** | **Aucune sauvegarde planifiée visible**, ni extension de sauvegarde. | Faits DirectAdmin n°4 et n°8. | Une compromission (SEC-02/03/06), une erreur humaine ou une panne détruit commandes et clients de façon définitive. Il n'y a aucun retour arrière possible. | Confirmer auprès d'OXA Host l'existence de sauvegardes côté hébergeur (JetBackup) et leur rétention. En plus, poser une sauvegarde quotidienne base + `wp-content` **hors serveur**, chiffrée (elle contient des données clients), et **tester une restauration**. | M |
| **SEC-06** | **Élevée** (7,3 : AV:N/AC:L/PR:N/UI:N/C:L/I:L/A:L) | **Surface d'authentification exposée** : l'identifiant administrateur `admin` est divulgué par l'API REST ; XML-RPC est actif ; `wp-login.php` et le formulaire de connexion de `/mon-compte/` n'ont ni captcha, ni limitation, ni 2FA ; aucune extension de sécurité en prod. | `GET /wp-json/wp/v2/users` → 200, 1 utilisateur (id 1, slug et nom = `admin`, lien `/author/admin/`), idem via `?rest_route=/wp/v2/users`. `GET /xmlrpc.php` → **405** « POST requests only » (actif). `wp-login.php` : aucun marqueur captcha ou limitation dans le HTML. | Bruteforce distribué sur `admin`, amplifié par `system.multicall` de XML-RPC (des centaines de mots de passe par requête). Un mot de passe faible suffit à tout prendre. | (1) Créer un nouvel administrateur au login imprévisible, puis supprimer `admin` en réattribuant ses contenus (geste à faire par le propriétaire). (2) Masquer `/wp/v2/users` aux non-connectés (filtre `rest_endpoints` dans `famma-core`). (3) Bloquer `xmlrpc.php` au niveau serveur (`.htaccess` : `<Files xmlrpc.php> Require all denied </Files>`), Jetpack n'étant pas installé. (4) Activer une limitation des tentatives : directive LiteSpeed `WordPressProtect` si l'hébergeur la permet, sinon une extension légère (Limit Login Attempts Reloaded). (5) Activer la 2FA WordPress (extension « Two-Factor ») pour les administrateurs. | S-M |
| **SEC-07** | **Moyenne** (Critique si le rôle par défaut est élevé) | **Inscriptions ouvertes** à la fois sur WordPress (`users_can_register`) et sur WooCommerce (`/mon-compte/`), **sans anti-bot**, avec un quota de **40 e-mails/jour**. | `wp-login.php` affiche le lien « Inscription ». `GET /wp-login.php?action=register` → 200, formulaire `registerform` (`user_login`, `user_email`), aucun captcha. `/mon-compte/` → formulaire `woocommerce-form-register` (email + mot de passe). Fait DirectAdmin n°10. | Des bots créent des comptes en masse. Chaque inscription envoie des e-mails (utilisateur + admin), le quota tombe à zéro et **les confirmations de commande réelles ne partent plus**. Si le rôle par défaut n'est pas « Abonné » ou « Client », c'est une élévation de privilège immédiate. | Dans **Réglages > Général**, décocher « Tout le monde peut s'inscrire » et **vérifier que le rôle par défaut est « Abonné »**. Dans **WooCommerce > Réglages > Comptes**, désactiver la création de compte sur « Mon compte » (garder la commande invité et, si voulu, la création de compte au checkout). Supprimer les comptes indésirables déjà créés. | S |
| **SEC-08** | **Moyenne** (5,3 : AV:N/AC:L/PR:N/UI:N/I:L) | **L'API Store (`/wc/store/v1/checkout`) contourne les règles tunisiennes** : validation du téléphone, pays forcé, coût de livraison interne 7 TND, UTM et signaux CAPI ne sont branchés que sur le checkout classique. | Hooks uniquement classiques : `famma-core.php:1019-1020` (`woocommerce_after_checkout_validation`), `:1033` (`woocommerce_checkout_posted_data`), `:924` et `:1385-1386` (`woocommerce_checkout_create_order`). Aucun hook `woocommerce_store_api_*` dans le dépôt. **En ligne** : `/wp-json/wc/store/v1/` expose `/checkout` (POST) et `/cart/add-item` (POST). | Un bot passe commande COD par l'API Store avec un téléphone fantaisiste ou un pays étranger. Il échappe à la validation et à toute protection posée seulement sur le formulaire (SEC-01), et la commande arrive sans coût interne ni attribution. | Si le checkout du site est classique (aucun bloc checkout détecté sur l'accueil ; à confirmer sur `/commande/`), **désactiver la route** `/wc/store/v1/checkout` pour les requêtes publiques via le filtre `rest_endpoints`. Sinon, brancher les mêmes contrôles sur `woocommerce_store_api_checkout_update_order_from_request` (lever `RouteException`) et le coût interne et les UTM sur `woocommerce_store_api_checkout_order_processed`. Dans tous les cas, activer la limitation native de l'API Store : filtre `woocommerce_store_api_rate_limit_options` → `enabled => true, limit => 5, seconds => 60`. | M |
| **SEC-09** | **Moyenne** (4,3) | **Aucun en-tête de sécurité sur les pages publiques** : ni HSTS, ni `X-Content-Type-Options`, ni `X-Frame-Options`/`frame-ancestors`, ni `Referrer-Policy`, ni `Permissions-Policy`, ni CSP. | En-têtes de `/`, `/boutique/`, `/produit/…` : seulement `Content-Type`, `Link`, `Server`, `Vary`, `alt-svc`. | Clickjacking du formulaire express (iframe invisible), rétrogradation SSL au premier accès sans HSTS, fuite du référent vers des tiers. | En-têtes au niveau serveur (`.htaccess` LiteSpeed, `mod_headers`) : `Strict-Transport-Security "max-age=31536000; includeSubDomains"` (commencer à `max-age=300`, sans preload) ; `X-Content-Type-Options "nosniff"` ; `X-Frame-Options "SAMEORIGIN"` ; `Referrer-Policy "strict-origin-when-cross-origin"` ; `Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"`. CSP d'abord en `Content-Security-Policy-Report-Only` (GA4, Meta, TikTok, gravatar), puis en mode bloquant. | S |
| **SEC-10** | **Moyenne** | **Authentification e-mail faible** : SPF en `~all` (échec souple), **aucun DMARC**, **aucun DKIM** (sélecteurs `x`, `default` et `mail` absents). | `nslookup` : `v=spf1 a mx ip4:102.204.204.69 ~all` ; `_dmarc.fammaminnou.tn` → NXDOMAIN ; `x._domainkey`, `default._domainkey`, `mail._domainkey` → NXDOMAIN. MX : `mail.fammaminnou.tn`. | Hameçonnage de clients COD au nom de `@fammaminnou.tn` (« confirmez votre commande, payez ici »). Les vrais e-mails de la boutique finissent aussi en spam. | Activer DKIM dans DirectAdmin (E-mail Manager > DKIM) et publier l'enregistrement. Publier `_dmarc` : `v=DMARC1; p=none; rua=mailto:contact@fammaminnou.tn` puis passer à `quarantine` après 2 à 4 semaines. Passer SPF en `-all` une fois les expéditeurs vérifiés. | S |
| **SEC-11** | **Moyenne** | **Contamination croisée possible** : `comin.tn` est hébergé sous le même utilisateur Unix, et `wp-config.php` est en 644. | Faits DirectAdmin n°5 et n°9. | Une faille sur `comin.tn` (autre CMS, autres extensions) donne lecture et écriture sur `fammaminnou.tn` : identifiants de base, sels, token CAPI s'il est défini en constante, données clients. | Séparer `comin.tn` dans un autre compte ou utilisateur DirectAdmin, ou au minimum le tenir à jour avec la même discipline. Passer `wp-config.php` en **600** (ou 440). | M |
| **SEC-12** | **Moyenne** (latent) | **`deploy.sh` enverrait `famma-local-mail.php` en prod** et supprimerait les mu-plugins propres au serveur. Or le filtre `wp_mail_from` de ce fichier est **inconditionnel**. | `scripts/deploy/deploy.sh:80-84` (dossier `mu-plugins` entier), `:191` (`--delete`) ; `mu-plugins/famma-local-mail.php:50-53` (`famma_local_mail_from()` renvoie `no-reply@famma.local` **sans** garde d'environnement, contrairement à la fonction SMTP `:31`). | Au prochain déploiement scripté, **tous les e-mails de commande** partiraient de `no-reply@famma.local` (domaine invalide, rejet SPF/DMARC). `--delete` effacerait aussi un mu-plugin posé par l'hébergeur. | Ajouter la garde `'local' === wp_get_environment_type()` dans `famma_local_mail_from()` (ou ne poser le filtre que dans ce cas). Exclure `famma-local-mail.php` dans `EXCLUDES` de `deploy.sh` et retirer `--delete` sur `mu-plugins` (ou lister les fichiers explicitement). | S |
| **SEC-13** | **Moyenne** | **Avis produit ouverts aux anonymes** (nom + e-mail), ce qui fournit aussi le vecteur de la XSS TranslatePress « Comment Noise-Key ». | `/produit/tapis-pique-nique-xl/` : `form#commentform` vers `wp-comments-post.php`, champs `author` et `email`. | Spam, **faux avis** (contraire à la règle §58/§60 du projet) et vecteur d'exploitation de SEC-02. | WooCommerce > Réglages > Produits : « Les avis ne peuvent être laissés que par les propriétaires vérifiés ». Réglages > Discussion : modération manuelle. | S |
| **SEC-14** | **Faible** (5,3 en info-disclosure) | **Divulgation de versions et de fichiers internes.** | `meta generator` : WordPress 7.1.2, WooCommerce 11.0.1, Site Kit 1.187.0 ; `?ver=` Kadence 1.5.2, TranslatePress 3.3.3 ; `/readme.html` 200, `/license.txt` 200 ; `/wp-content/plugins/famma-core/languages/famma-core.pot` 200 et `…-ar.po` 200. | Ciblage automatique des versions vulnérables (SEC-02, SEC-03) par les robots. | Retirer les balises generator (`remove_action( 'wp_head', 'wp_generator' )` + filtre `woocommerce_generator_tag` / `the_generator`). Refuser `readme.html`, `license.txt`, `*.po`, `*.pot` et `*.md` en `.htaccess` (`<FilesMatch "\.(po|pot|md)$|^(readme\.html|license\.txt)$"> Require all denied`), ce qui survit aux mises à jour du cœur qui recréent ces fichiers. | S |
| **SEC-15** | **Faible** | Le **token CAPI peut vivre en base** (`wp_options`) ; le champ est en `type="text"` ; la valeur reste lisible en clair par un administrateur sur `/wp-admin/options.php`. | `includes/tracking-settings.php:57`, `:143`, `:246-255` ; `famma-core.php:108-116` (repli sur l'option). | Le token se retrouve dans une sauvegarde de base, un export ou une extension lisant les options : n'importe qui peut alors envoyer de faux événements à Meta au nom du Pixel. | Préférer la constante serveur (déjà prioritaire) et vider l'option (« supprimer »). Passer le champ en `type="password"`. Le documenter comme secret dans la procédure de sauvegarde. | S |
| **SEC-16** | **Faible** | Le cookie `famma_utm` est posé en JavaScript **sans attribut `Secure`**. | `famma-core.php:1456` (`…;path=/;max-age=…;SameSite=Lax`). | Faible impact : seulement de l'attribution, relue et assainie côté serveur. | Ajouter `;Secure` à la chaîne du cookie. | S |
| **SEC-17** | **Faible** | WP-Cron déclenché par le trafic (aucune tâche cron serveur) ; index `/wp-json/` de **1,4 Mo** servi aux anonymes. | Fait DirectAdmin n°7 ; `GET /wp-cron.php` → 200 ; `GET /wp-json/` → 200, 1 438 539 octets, 717 routes. | Tâches planifiées (Action Scheduler de WooCommerce, nettoyage des sessions) retardées faute de trafic ; petite amplification de charge. | `define( 'DISABLE_WP_CRON', true );` + une tâche cron DirectAdmin toutes les 5 min sur `wp-cron.php`. Mettre l'index REST en cache côté serveur ou le restreindre aux utilisateurs connectés si rien de public n'en dépend. | S |
| **SEC-18** | **Info** (pas encore en prod) | `wp_oembed_get()` sans cache à chaque rendu d'une fiche avec vidéo : une requête sortante vers YouTube ou Vimeo à chaque affichage non mis en cache. | `includes/product-video.php:243` (le fichier est absent de la prod, 404, commit du 27/08). | Lenteur et dépendance externe sur les pages d'atterrissage publicitaires. | Mettre le HTML oEmbed en cache dans une méta ou un transient (`_famma_video_embed_cache`) invalidé à l'enregistrement du produit. | S |
| **SEC-19** | **Info** | `sanitize_text_field()` sur `REQUEST_URI` supprime les octets `%XX`, ce qui tronque les UTM encodés lors de la redirection 301. | `mu-plugins/famma-legacy-urls.php:41-43`, `:91-94`. | Aucun impact sécurité (la cible reste `wp_safe_redirect` sur le même hôte). Perte d'attribution. | Utiliser `esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )` ou ne garder que `wp_parse_url` puis `wp_parse_str` et `add_query_arg` avec des valeurs `rawurlencode`. | S |

**Aucun constat critique** n'a été observé passivement. Deux cas le deviendraient : un rôle par défaut élevé à l'inscription (SEC-07, à vérifier dans l'admin), ou une exploitation effective de SEC-02 ou SEC-06.

---

## 4. Checklist de durcissement recommandé

### Hébergement / DirectAdmin
- [ ] 2FA TOTP sur DirectAdmin + mot de passe unique (SEC-04).
- [ ] Sauvegardes quotidiennes hors serveur, chiffrées, **restauration testée** (SEC-05).
- [ ] `wp-config.php` en 600 ; séparer `comin.tn` dans un autre compte ou utilisateur (SEC-11).
- [ ] Tâche cron serveur toutes les 5 min + `DISABLE_WP_CRON` (SEC-17).
- [ ] DKIM activé, DMARC publié, SPF `-all` (SEC-10). Vérifier avec OXA Host si le quota de 40 e-mails/jour peut être relevé, ou passer à un SMTP transactionnel authentifié (le quota est un point unique de défaillance pour les confirmations de commande).
- [ ] Garder ModSecurity actif, sans exclusion générique ; demander à OXA Host si la protection anti-bruteforce WordPress de LiteSpeed (`WordPressProtect`) est disponible.

### Serveur web (`.htaccess` LiteSpeed)
- [ ] En-têtes de sécurité (SEC-09).
- [ ] Refuser `xmlrpc.php`, `readme.html`, `license.txt`, `*.po`, `*.pot`, `*.md` (SEC-06, SEC-14).
- [ ] Interdire l'exécution PHP dans `wp-content/uploads/` : `<FilesMatch "\.(php|phtml|phar)$"> Require all denied </FilesMatch>` dans `uploads/.htaccess`.

### WordPress (`wp-config.php`)
- [ ] `DISALLOW_FILE_EDIT` à `true` (posé le 22/08, à confirmer) ; `FORCE_SSL_ADMIN` à `true` (commenté au déploiement, le certificat est maintenant en place).
- [ ] `WP_DEBUG` à `false` et `WP_DEBUG_LOG` désactivé en prod (le `debug.log` est bien en 403 aujourd'hui).
- [ ] `WP_ENVIRONMENT_TYPE` à `'production'`.
- [ ] Sels uniques (régénérer si un doute existe sur leur origine).
- [ ] Mises à jour automatiques des **extensions** activées (WooCommerce, TranslatePress, Site Kit) : le cœur se met déjà à jour tout seul, pas les extensions (SEC-02, SEC-03).

### Comptes et authentification
- [ ] Remplacer le compte `admin` par un login imprévisible ; un seul administrateur ; rôle « Gestionnaire de boutique » pour l'opérationnel (SEC-06).
- [ ] Limitation des tentatives + 2FA WordPress pour les administrateurs (SEC-06).
- [ ] Masquer `/wp/v2/users` aux anonymes (SEC-06).
- [ ] Inscriptions WordPress fermées, rôle par défaut « Abonné », pas d'inscription sur « Mon compte » (SEC-07).
- [ ] Désactiver les mots de passe d'application si inutilisés (filtre `wp_is_application_passwords_available`).

### WooCommerce / FAMMA
- [ ] Anti-abus du checkout et de la commande express : honeypot, horodatage, limite IP/téléphone, plafond de quantité (SEC-01).
- [ ] API Store : route checkout désactivée ou validée, limitation native activée (SEC-08).
- [ ] Avis réservés aux acheteurs vérifiés, modération manuelle (SEC-13).
- [ ] Corriger le filtre `wp_mail_from` de `famma-local-mail.php` et les exclusions de `deploy.sh` **avant** le prochain déploiement scripté (SEC-12).
- [ ] Token CAPI uniquement en constante serveur (SEC-15).

---

## 5. Quick wins (à faire dans l'ordre, environ 2 h au total)

1. **Mettre à jour TranslatePress (≥ 3.3.6) et WooCommerce (≥ 11.1.0)** après un test rapide du parcours COD en local, puis activer leurs mises à jour automatiques. Cela ferme deux XSS non authentifiées et un DoS. *(20 min)*
2. **Activer la 2FA DirectAdmin.** *(5 min)*
3. **Fermer les inscriptions** (Réglages > Général, WooCommerce > Comptes), **vérifier le rôle par défaut** et passer les avis en « propriétaires vérifiés ». *(10 min)*
4. **Remplacer le compte `admin`**, puis bloquer `xmlrpc.php` et poser une limitation des tentatives de connexion. *(20 min)*
5. **Sauvegarde** : confirmer celles d'OXA Host et ajouter une sauvegarde externe quotidienne. *(30 min)*
6. **`.htaccess`** : en-têtes de sécurité + refus de `readme.html`, `license.txt`, `*.po`, `*.pot`, `xmlrpc.php` + PHP interdit dans `uploads/`. *(15 min)*
7. **DNS** : DKIM + DMARC `p=none`. *(10 min)*
8. **`wp-config.php` en 600**, `FORCE_SSL_ADMIN` à `true`. *(5 min)*
9. **Côté code, avant le prochain déploiement** : SEC-12 (garde sur `wp_mail_from`, exclusions de `deploy.sh`), puis SEC-01 et SEC-08 (anti-abus + API Store) en priorité suivante, avec la validation du §11 (php -l, phpcs, `debug.log`, parcours COD de bout en bout).

---

## Annexe — détails des contrôles en ligne

| Requête | Résultat |
|---|---|
| `http://fammaminnou.tn/`, `http://www…`, `https://www…` | 301 → `https://fammaminnou.tn/` (www en deux sauts) |
| `/`, `/boutique/`, `/produit/tapis-pique-nique-xl/` | 200, aucun en-tête de sécurité, aucun cookie posé |
| `/wp-login.php` | 200, `X-Frame-Options: SAMEORIGIN`, `CSP: frame-ancestors 'self'`, `Referrer-Policy`, cookie de test `secure; HttpOnly` ; lien « Inscription » ; langues : English, العربية, Français |
| `/wp-login.php?action=register` | 200, formulaire d'inscription actif, aucun captcha |
| `/wp-admin/` | 302 → `wp-login.php?…&reauth=1` |
| `/mon-compte/` | 200, formulaires connexion + inscription (nonces WooCommerce présents), aucun anti-bot |
| `/commande/` | 302 → `/panier/` (panier vide) |
| `/readme.html`, `/license.txt` | 200 |
| `/wp-config.php` | 200, 0 octet (exécuté, rien ne fuit) |
| `/wp-config.php.bak`, `/wp-config.php~`, `/.env`, `/.git/HEAD`, `/wp-content/debug.log` | 403 |
| `/xmlrpc.php` | 405 (XML-RPC actif) |
| `/wp-cron.php` | 200, 0 octet |
| `/wp-content/uploads/`, `/wp-includes/`, `uploads/wc-logs/`, `uploads/woocommerce_uploads/` | 403 (pas de listing) |
| `/wp-content/plugins/` | 200, 0 octet |
| `/backup/`, `/phpinfo.php`, chemin inexistant | 404 |
| `famma-core.php`, `includes/express-order.php`, `mu-plugins/famma-env.php` (accès direct) | 200, 0 octet (garde ABSPATH OK) |
| `includes/product-video.php`, `mu-plugins/famma-local-mail.php` | 404 (non déployés) |
| `languages/famma-core.pot`, `famma-core-ar.po` | 200 (lisibles) |
| `/wp-json/` | 200, 1,4 Mo, espaces de noms `wp/v2`, `wc/store/v1`, `google-site-kit/v1`, `jetpack/v4` (paquet embarqué par WooCommerce)… |
| `/wp-json/wp/v2/users`, `/?rest_route=/wp/v2/users` | 200, 1 utilisateur, slug `admin` |
| `/?author=1` | 404 (blocage FAMMA effectif) |
| `/wp-json/wc/store/v1/` | 200, 31 routes dont `/checkout` et `/cart/add-item` |
| `/?wc-ajax=famma_express_nonce` (GET, lecture seule) | 200, jeton distribué sans session |
| TLS | Let's Encrypt YE1, SAN `fammaminnou.tn`, `*.fammaminnou.tn`, expire le 22/11/2026 ; TLS 1.2 et 1.3 OK ; TLS 1.0/1.1 non testables avec l'OpenSSL local |
| DNS | SPF `~all` ; pas de DMARC ; pas de DKIM (`x`, `default`, `mail`) ; MX `mail.fammaminnou.tn` |

**Observation annexe.** Lors d'une première salve à 1 requête par seconde, une série de chemins (`/.env`, `/.git/HEAD`, `/xmlrpc.php`, `/phpinfo.php`…) a renvoyé des réponses uniformes « 200, environ 12 Ko, text/html ». Refaits à 1,5–2 s d'intervalle, les mêmes chemins ont donné les codes attendus (403/404/405) et des pages 404 d'environ 81 Ko. Ce comportement ressemble à une page de défi anti-bot de LiteSpeed ou ModSecurity déclenchée par la cadence ; ce n'est pas confirmé, car les corps n'ont pas été conservés. **Aucun contenu de fichier sensible n'a été servi** : les réponses étaient du HTML, pas du texte brut.

**Versions et vulnérabilités publiques** (WPScan, consulté le 23/09/2026) :
- [WooCommerce < 11.1.0 – Unauthenticated DoS, CVE-2026-48888](https://wpscan.com/vulnerability/6e0eb3d6-4571-4e99-b201-dd8527c67de2/)
- [TranslatePress (XSS stockées < 3.3.4 et < 3.3.6)](https://wpscan.com/plugin/translatepress-multilingual/)
- [WordPress 7.1.2 (LFI→RCE corrigée, CVE-2026-87902)](https://wordpress.org/news/2026/09/wordpress-7-1-2-release/)
- [Site Kit (CSRF corrigé en 1.187.0)](https://wpscan.com/plugin/google-site-kit/)
- Kadence 1.5.2 : aucune vulnérabilité publique trouvée (recherche non exhaustive).
