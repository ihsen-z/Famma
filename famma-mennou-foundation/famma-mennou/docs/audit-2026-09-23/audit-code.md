# Audit de code et d'architecture — FAMMA (fammaminnou.tn)

- **Date** : 2026-09-23 · **Mode** : lecture seule (aucun fichier du dépôt modifié, aucun commit)
- **Dépôt** : `E:\Ecom\famma-mennou-foundation\famma-mennou`, branche `feat/maquette-fidelite-fiche-produit` @ `2e40acc`
- **Grille** : `E:\Ecom\ClaudeMemo.md` (règles 1 à 13 + règles non négociables)
- **Référence** : WooCommerce 11.0.1 (installé sur le site Local), WordPress ≥ 6.9, PHP 8.3.29 (LocalWP)

## 0. Méthode et limites

- **Graphe** : `graphify query` lancé depuis `E:\Ecom` sur l'architecture de famma-core. Le graphe (build du 2026-08-25) répond, mais il est en retard : numéros de ligne décalés (`boot()` L2244 dans le graphe, L2247 en réalité) et `includes/product-video.php` (ajouté le 27/08) absent. Pour un audit complet, les fichiers ont ensuite été lus avec Grep/Read (c'est permis après la requête graphe). « graph miss » sur les recherches ciblées : e-mails de commande, cookie UTM, oEmbed, `@version` des gabarits.
- **Exécution** : `php -l` et `phpcs` lancés avec le PHP de LocalWP. Le site Local n'était pas démarré (MySQL injoignable sur 10005 et 10010) : aucun contrôle à l'exécution n'a été possible. Les points marqués « à vérifier » en dépendent.
- **État de la production** (fourni par le coordinateur) : aucun redéploiement depuis le 2026-08-25. **Non déployés** : `7d4d0d7` (vidéo produit, `includes/product-video.php` et `inc/product-video.php`) et `2e40acc` (quantité 48 px, `product.css`). Extensions en prod : famma-core, WooCommerce, TranslatePress, **Google Site Kit**. Aucune extension de cache, de sécurité, de SMTP ni de sauvegarde. mu-plugins en prod : `famma-env.php` et `famma-legacy-urls.php`. `main` date du 18/08. La colonne « Prod » du tableau indique si le code visé est en ligne.

---

## 1. Note globale : **6,5 / 10**

**Ce qui tire la note vers le haut.** L'hygiène de sécurité est rare pour un projet de cette taille : nonce, capacité, assainissement et échappement sont appliqués partout. `phpcs` (WPCS + PHPCompatibility) ne remonte **aucune** erreur ni aucun avertissement. Toutes les commandes passent par les CRUD WooCommerce, et la compatibilité HPOS est déclarée. Le CSS est entièrement en propriétés logiques, avec **zéro** `left/right` physique. Le token CAPI ne quitte jamais le serveur. Enfin, chaque décision est documentée dans le code et dans les ADR.

**Ce qui la tire vers le bas.** Plusieurs règles métier sont cassées ou contournables en silence :
- aucun e-mail « Nouvelle commande » ;
- la capture UTM dépend de la présence d'un pixel ;
- le `Purchase` CAPI n'a ni reprise ni horodatage fiable ;
- le statut « Terminée » contourne la règle « payé à la livraison ».

S'y ajoutent un script de déploiement capable de supprimer des fichiers distants hors de notre code et l'absence de sauvegarde en production. La dette structurelle s'aggrave aussi : 17 fichiers dépassent 250 lignes, la réassurance est dupliquée dans 8 endroits et l'i18n est incomplète avec une langue source mixte. **Environ la moitié des constats « Moyens » de l'audit du 18/08 sont encore ouverts.**

---

## 2. Points forts (à ne pas casser)

1. **Sécurité des entrées/sorties** : séquence nonce → capacité → assainissement refaite même là où WooCommerce l'a déjà faite (`famma-core.php:630-648`, `includes/product-video.php:156-189`, `includes/seo-admin.php:369-406`). La sortie est échappée tardivement. Aucune requête SQL concaténée sur des données d'entrée.
2. **HPOS** : aucun accès `postmeta` aux commandes. Uniquement `wc_get_order()`, `get_meta()`, `update_meta_data()` et `save()`. Compatibilité déclarée (`famma-core.php:2289-2293`). Aucune fonction dépréciée WP/Woo trouvée.
3. **Workflow COD** : les trois filtres de paiement, la priorité 99 contre `WC_Gateway_COD` et le garde d'idempotence `_famma_cod_paid` sont justes et commentés (`famma-core.php:241-426`). Le stock est réservé à « confirmé » et rendu sur annulation, refus, retour et échec (`famma-core.php:459-522`).
4. **Secrets** : `.env` n'est pas suivi par Git, `backups/` contient seulement `.gitkeep`, et `.env.deploy` est ignoré. Le token CAPI n'est jamais réaffiché (empreinte des 4 derniers caractères), il est stocké en `autoload=no` et voyage dans le corps de la requête, jamais dans l'URL (`tracking-settings.php`, `famma-core.php:1786`).
5. **RTL** : 115 propriétés logiques, 0 `margin/padding/border-left/right`, 4 `!important` seulement. Polices auto-hébergées présentes, avec préchargement ciblé.
6. **Assets** : tout passe par `wp_enqueue_*` avec `filemtime()`, et le chargement est conditionnel par page (boutique, fiche, checkout). Les scripts inline passent par `wp_add_inline_script`, sauf JSON-LD et préchargement de polices.
7. **Architecture** : aucun doublon de classe ni de fonction (vérifié). Namespace `Famma\Core`, classes `final` en singleton, un hook par responsabilité, code d'administration chargé sous `is_admin()` (`famma-core.php:2274-2281`).
8. **Local** : `famma-core` et `famma-child` sont montés par jonction dans le site Local, donc aucune dérive possible entre dépôt et site.

---

## 3. Constats

Sévérité : **Critique / Élevée / Moyenne / Faible**. Effort : **S** (< ½ j), **M** (1-3 j), **L** (> 3 j). Prod : **oui** = code en ligne, **non** = non déployé.

| ID | Sév. | Constat | Preuve | Impact | Correctif recommandé | Effort | Prod |
|---|---|---|---|---|---|---|---|
| CODE-01 | **Élevée** | **Aucun e-mail « Nouvelle commande » (admin) ni e-mail de confirmation client.** Une commande COD démarre en `pending-confirm`. Or WooCommerce ne notifie que les transitions `pending→processing/completed/on-hold` (et `failed/cancelled→…`). Rien ne rattache `pending→pending-confirm` à un e-mail. | `famma-core.php:275`, `:283-285` ; WC 11.0.1 `includes/emails/class-wc-email-new-order.php:43-51`, `class-wc-email-customer-processing-order.php:43-46`, `class-wc-emails.php:98-109` ; aucun `woocommerce_email_actions` dans le dépôt | Le marchand n'est pas alerté des nouvelles commandes et le client ne reçoit rien. Sur du COD, l'appel de confirmation rapide réduit les refus : c'est donc un coût direct. En prod, l'absence de SMTP aggrave le problème. | Ajouter `woocommerce_order_status_pending_to_pending-confirm` au filtre `woocommerce_email_actions`, puis accrocher `WC_Email_New_Order::trigger` (et un e-mail client) sur `…_pending_to_pending-confirm_notification`. Installer un SMTP en prod. | S | oui |
| CODE-02 | **Élevée** | **Capture UTM inopérante tant qu'aucun pixel Meta/TikTok n'est configuré.** Le script UTM est attaché au handle `famma-pixels`, qui n'est jamais enregistré si les deux identifiants sont vides (retour anticipé). Le commentaire affirme l'inverse. Le cookie est aussi posé sans `Secure`. | `famma-core.php:1430-1437` (retour anticipé) puis `:1453-1457` (« independent of any pixel ») | La règle « UTM → commande » (§20) est cassée en silence. Le tableau « Acquisition » affiche 100 % « Direct ». | Handle dédié `famma-utm` toujours enregistré, ou capture serveur au premier hit. Ajouter `;Secure` au cookie. | S | oui |
| CODE-03 | **Élevée** | **`Purchase` CAPI fragile.** (a) `event_time` = date de création : Meta rejette au-delà de 7 jours. (b) Aucune reprise : `MAX_ATTEMPTS = 5` est du code mort. (c) Envoi synchrone de 8 s dans la requête admin. (Constats M4 et M5 du 18/08, **ouverts**.) | `famma-core.php:1751`, `:1596`, `:1744-1747`, `:1775-1788`, `:1387` | Des ventes livrées manquent chez Meta (optimisation faussée), et l'écran admin se fige à chaque « livré ». | Passer par Action Scheduler (`as_enqueue_async_action` + reprise avec délai croissant). `event_time` = heure de livraison, ou `max(created, now-6j)`. | M | oui |
| CODE-04 | **Élevée** | **Le statut « Terminée » (et les statuts natifs) contourne la règle COD.** Passer directement à `completed` ne pose pas `_famma_cod_paid`, n'écrit pas de note, n'envoie pas de `Purchase`, et le tableau de bord ignore la vente. Tous les statuts natifs restent sélectionnables. (M6, **ouvert**.) | `famma-core.php:244`, `:1387`, `:2020` ; `add_to_wc_list()` n'ôte aucun statut (`:325-343`) | Écart silencieux entre chiffre réel et chiffre affiché, et conversions perdues. | Soit traiter `completed` comme alias (mêmes callbacks, idempotents), soit retirer du sélecteur les statuts inutiles (`on-hold`, `pending`, `failed`). | S | oui |
| CODE-05 | **Élevée** | **Déploiement destructeur et non atomique.** `rsync --delete` s'applique à **tout** `wp-content/mu-plugins`, ce qui supprimerait tout mu-plugin posé par l'hébergeur. `famma-local-mail.php` (outil de développement) serait envoyé en prod. Aucun instantané distant, aucun retour arrière, et le transfert se fait fichier par fichier (fatal possible si `famma-core.php` arrive avant `includes/`). `--yes` saute à la fois la confirmation et le contrôle Git. | `scripts/deploy/deploy.sh:80-84`, `:185-195`, `:142`, `:155` | Suppression irréversible hors de notre code, et site cassé pendant le transfert. | Lister explicitement `mu-plugins/famma-env.php` et `famma-legacy-urls.php`. Faire un `tar` distant avant transfert. Déployer dans `releases/<sha>` puis basculer. Séparer `--yes` de `--allow-dirty`. | M | n/a |
| CODE-06 | **Élevée** | **Aucune sauvegarde utilisable en prod ni en local.** `backup.sh` ne fonctionne qu'avec Docker (`docker compose exec`), alors que le quotidien est Local (ADR-006) et la prod LiteSpeed/DirectAdmin, sans extension de sauvegarde. | `scripts/backup.sh:44-48`, `:80-83` ; liste des extensions en prod | Aucune restauration possible des commandes (données clients) ni des traductions TranslatePress. | Sauvegardes de l'hébergeur (DirectAdmin) quotidiennes et vérifiées, plus un script agnostique (`wp db export` ou `mysqldump` avec fichier `.my.cnf`). | S-M | n/a |
| CODE-07 | Moyenne | **oEmbed non mis en cache** : `wp_oembed_get()` fait un appel HTTP sortant (YouTube/Vimeo/Facebook) à **chaque** affichage de fiche avec vidéo. L'iframe YouTube dépose des cookies sans consentement. | `includes/product-video.php:243` ; appelé par `themes/famma-child/inc/product-video.php:46` | TTFB dégradé sur les pages d'atterrissage publicitaires, et dépendance réseau à chaque vue. | `WP_Embed::shortcode()` avec contexte de post (cache `_oembed_*`), ou transient par `md5(url)`. Façade « clic pour lire », `youtube-nocookie`. | S | **non** |
| CODE-08 | Moyenne | **Tableau de bord : marges fausses et requête lourde.** Coût d'acquisition « par commande » additionné **par ligne**. Coûts lus sur le produit **actuel**, pas figés. Agrégat indexé par nom. 3 × `wc_get_orders(limit 1000)` qui chargent des objets complets alors que le mois contient la semaine et le jour. (Y2, Y3, D5, **ouverts**.) | `famma-core.php:2038-2040`, `:1968-1977`, `:2064-2070`, `:2035-2037` | Décisions de prix prises sur une marge sous-estimée, et risque de délai dépassé en admin quand le volume augmente. | Figer coût et manutention sur l'item (`woocommerce_checkout_create_order_line_item`). Une seule requête paginée (mois) dont on dérive jour et semaine. Clé `product_id`. Acquisition hors de la boucle. | M | oui |
| CODE-09 | Moyenne | **Règle coût×3 incomplète.** Seule la sauvegarde admin la déclenche (import, REST, modification rapide et variations ignorés, Y5 ouvert). Une fois posé, le prix « auto » devient indiscernable d'un prix manuel : changer le coût ne le recalcule plus. Le multiplicateur n'est validé que côté HTML (`min=1`). | `famma-core.php:556-559`, `:650-653`, `:943-951`, `:186-188` | Prix incohérents avec le coût réel. Multiplicateur 0 ou négatif possible par l'API des réglages. | Drapeau `_famma_price_auto`. Hooks `woocommerce_product_import_inserted_product_object`, `woocommerce_rest_insert_product_object`, `woocommerce_save_product_variation`. Assainisseur `woocommerce_admin_settings_sanitize_option_famma_price_multiplier`. | M | oui |
| CODE-10 | Moyenne | **Données personnelles et consentement.** IP, user-agent, `_fbp` et `_fbc` sont stockés sur chaque commande, sans exporteur ni effaceur (Y8 ouvert). Pixels Meta, TikTok et GA4 chargés sans recueil de consentement. | `famma-core.php:1499-1522`, `:1412-1458` | Impossible de répondre à une demande d'accès ou d'effacement (loi tunisienne 2004-63, RGPD pour les visiteurs UE). | `woocommerce_privacy_export_order_personal_data_props` et l'équivalent pour l'effacement. Bannière de consentement et Consent Mode GA4. | M | oui |
| CODE-11 | Moyenne | **Taille des fichiers et duplication** (règle 6). 17 fichiers dépassent 250 lignes : `famma-core.php` 2313, `seo.php` 1048, `functions.php` 808, `shop-widgets.php` 733, `pages-content.php` 688, `product.php` 680, `header-parts.php` 655… La réassurance est écrite dans **au moins 8 endroits** (AUDIT-TEXTES-EN-DUR §2.A **ouvert**). Le parseur FAQ est dupliqué. Deux fonctions du thème sont du code mort (neutralisées par `Seo`), et `FAMMA_CHILD_VERSION` n'est jamais utilisée. | Réassurance : `functions.php:242-297`, `home.php:124-129`, `:403-404`, `product.php:245-251`, `footer-parts.php:217`, `header-parts.php:124`, `topbar-content.php:202`, `pages-content.php:153-188`, `seo.php:670-673`, `:829`. FAQ : `famma-core.php:808-836` vs `product.php:561-589`. Code mort : `functions.php:176-201`, `:665-728`, `:14` | Une offre qui change oblige à retrouver 8 textes, et en oublier un fait mentir le site. Revue et maintenance coûteuses. | Source unique de réassurance (option famma-core avec texte court et texte long). Découper `famma-core.php` par domaine (statuts, stock, financials, tracking, CAPI, dashboard) avec `require_once` explicites. Supprimer le code mort. | L | oui |
| CODE-12 | Moyenne | **Logique hors présentation dans le thème** (règles 6 et 13 : « le site reste fonctionnel après changement de thème »). Filtres de requête catalogue `?on_sale` et stock. Correctif 404 des plans de site. Compteurs de filtres avec purge SQL directe. | `inc/shop.php:557-576`, `inc/shop-widgets.php:733`, `functions.php:642-649`, `inc/shop-widgets.php:41-83` | Changer de thème casse le lien « Promotions » du menu et renvoie les sitemaps en 404. | Déplacer dans famma-core (`Catalog_Filters`, `Seo`). | M | oui |
| CODE-13 | Moyenne | **i18n incomplète et langue source mixte.** famma-core compte 190 chaînes : 121 sources anglaises et 69 françaises dans le même domaine. `fr_FR.po` manque 43 chaînes anglaises (admin : « Product FAQ », « Advertising video », notes CAPI). `ar.po` en couvre 24 sur 190. `famma-child-ar.po` en manque 26 sur 143 (dont les aria « Diminuer/Augmenter la quantité »). `.pot` figé au 2026-08-13 (68 entrées). Gouvernorats en dur en français (Y14 **ouvert**). Littéraux arabes hors gettext (D4 **ouvert**). Deux text domains alors que la règle 7 dit « unique `famma-core` ». | Gouvernorats : `famma-core.php:1064-1089`. Littéraux arabes : `functions.php:96,246,250,286,290,403,429,511,515,519,591`, `includes/express-order.php:385`, `home.php:125,161`, `footer-parts.php:224`, `loop/no-products-found.php:54` | Administration à moitié en anglais pour un propriétaire francophone. Derja non modifiable sans développeur. Sélecteur de gouvernorats en français sur `/ar/`. | Unifier la langue source (anglais, par convention WP). Régénérer le `.pot` (`wp i18n make-pot`) et compléter `fr_FR` et `ar`. Libellés de gouvernorats via `__()`. Acter le second domaine `famma-child` dans un ADR, ou mettre la règle 7 à jour. | M | oui |
| CODE-14 | Moyenne | **Scripts non idempotents vis-à-vis de l'admin.** `seed-seo-settings.php` **écrase** toute l'option SEO (y compris `same_as => ''`) et les méta de pages. `setup.sh`, à chaque exécution, réécrit `blogname`, le réglage COD et remet `review_rating_verification_required = no` (Y7 **ouvert**). Incohérence de marque : « FAMMA MINNOU » vs « FAMMA MENNOU ». | `scripts/deploy/seed-seo-settings.php:95`, `:111`, `:128` ; `scripts/setup.sh:108-109`, `:192`, `:195` | Rejouer un script efface les saisies du propriétaire (contraire à « tout paramétrable depuis l'admin »). N'importe qui peut noter un produit. | Fusion non destructive (ne remplir que les clés vides) avec une option `--force`. Mettre `review_rating_verification_required` à `yes`. Un seul nom de marque. | S | partiel |
| CODE-15 | Moyenne | **Double balisage Google possible.** Google Site Kit est actif en prod, et `Tracking` émet aussi gtag GA4 et `google-site-verification`. Les événements e-commerce GA4 (`view_item`, `add_to_cart`, `begin_checkout`, `purchase`) sont absents (M3 à moitié ouvert). | `famma-core.php:1383`, `:1395-1401`, `:1415-1428` | Pages vues comptées deux fois et rapports e-commerce GA4 vides. | Un seul propriétaire : se taire si `GOOGLESITEKIT_VERSION` est défini, ou retirer Site Kit. Ajouter les événements GA4. | S-M | oui |
| CODE-16 | Moyenne *(à vérifier)* | `famma_child_is_derja()` **mémorise** la locale au premier appel gettext sur le domaine `woocommerce`. Or le code documente que TranslatePress ne fixe la langue qu'après `init` (commentaires de chargement dans `famma-core.php` et `i18n.php`). Si ce premier appel a lieu pendant `init`, la carte derja ne s'applique jamais sur `/ar/`. | `themes/famma-child/inc/i18n.php:247-259` vs `:35-39` et `famma-core.php:2215-2227` | Chaînes WooCommerce en anglais sur la version arabe. | Ne mémoriser qu'après `did_action( 'wp' )`. Vérifier sur `/ar/boutique/` (site Local arrêté pendant l'audit). | S | oui |
| CODE-17 | Faible | Surcharges WooCommerce **sans en-tête `@version`** : l'état système WooCommerce ne peut pas signaler qu'elles sont obsolètes. | `woocommerce/archive-product.php` (cœur 8.6.0), `single-product.php` (cœur 1.6.4), `loop/no-products-found.php` (cœur 7.8.0) ; `single-product/tabs/tabs.php` 9.8.0 = cœur 9.8.0, à jour | Dette de resynchronisation invisible. | Ajouter `@version` égal à la version du gabarit copié, et vérifier à chaque mise à jour de WooCommerce. | S | oui |
| CODE-18 | Faible | `! get_option( 'woocommerce_enable_myaccount_registration' )` : la valeur `'no'` est vraie en PHP, donc l'offre de compte s'affiche même si l'inscription est désactivée (lien mort). | `themes/famma-child/inc/checkout.php:177` | Lien vers une inscription impossible. | `'yes' !== get_option(...)`. | S | oui |
| CODE-19 | Faible | Redirection des anciennes URL : `sanitize_text_field()` sur `REQUEST_URI` retire les octets `%xx`, donc les UTM encodées (espaces, arabe) sont corrompues dans la 301, contrairement au commentaire. `/ar/shop/` n'est pas couvert. | `mu-plugins/famma-legacy-urls.php:41-43`, `:89-94` | Attribution faussée sur les anciens liens publicitaires. | Lire `QUERY_STRING` brut, le valider (`wp_parse_str` puis `add_query_arg`) et gérer le préfixe de langue. | S | oui |
| CODE-20 | Faible | Livraison à 0 TND garantie **par la configuration seulement** (`free_shipping` créé par `setup.sh`, Docker). Aucune garde dans le code. Le coût interne posé sur la commande vient de l'option globale, pas du `_famma_shipping_cost` du produit, alors que `margin()` utilise ce dernier. | `scripts/setup.sh:259-260` ; `famma-core.php:979` vs `:674-675` | Un tarif ajouté par erreur facture le client. Marge commande incohérente avec la marge produit. | Garde `woocommerce_package_rates` (forcer 0 ou avertissement admin). Poser le coût produit sur la ligne de commande. | S | oui |
| CODE-21 | Faible | Cas limites COD : (a) une commande à total 0 (coupon à 100 %) passe par `payment_complete()` au checkout, donc payée avant livraison ; (b) livré → retourné conserve `_famma_cod_paid` et le `Purchase` (aucun événement correctif), et un nouveau passage à « livré » ne redécrémente plus le stock (garde) ; (c) `refunded` ne rend pas le stock. | `famma-core.php:367-374`, `:473` ; COD `process_payment` du cœur | Stock et reporting Meta faux dans des cas rares. | Refuser le total 0 ou forcer `pending-confirm`. Remettre `_famma_cod_paid` à zéro sur retour. Ajouter `refunded` aux statuts qui libèrent le stock. | S | oui |
| CODE-22 | Faible | Callbacks de hooks typés strictement (`array $s`, `string $content`) : si un filtre tiers renvoie `null`, c'est une `TypeError` fatale sur tout le site. | `famma-core.php:325`, `:933`, `:1051`, `:1063`, `:1099` ; `inc/editorial-guards.php` (`the_content`) | Fatal déclenché par une extension tierce. | Accepter `mixed` et convertir (`(array)`, `(string)`) dans les callbacks de filtres. | S | oui |
| CODE-23 | Faible | JSON-LD encodé avec `JSON_UNESCAPED_SLASHES` sans `JSON_HEX_TAG` : un texte contenant `</script>` fermerait la balise. Les sources sont éditées en admin et en partie débarrassées de leurs balises : risque faible. | `includes/seo.php:789-797` | Casse ou injection du `<head>` dans un cas limite. | Ajouter `JSON_HEX_TAG \| JSON_HEX_AMP`. | S | oui |
| CODE-24 | Faible | Purge des transients par `DELETE` SQL direct : sans effet avec un cache d'objets persistant, et exécutée 3 fois par sauvegarde produit. Compteur `wc_get_products(limit -1)` quand le cache est vide. Agrégat des avis : 500 lectures `get_comment_meta` sans cache préchargé. | `inc/shop-widgets.php:52-65`, `:79-83` ; `inc/home.php:454-471` | Compteurs périmés si un cache d'objets est ajouté, et pics de requêtes. | Clé de version (`famma_filter_gen`) au lieu d'une purge. `update_meta_cache` ou requête d'agrégat. | S | oui |
| CODE-25 | Faible | `@phpcs:disable` placé dans un docblock sans `enable` : le sniff est désactivé jusqu'à la fin du fichier. 28 directives `phpcs:ignore/disable` au total (9 dans `shop-widgets.php`). | `includes/tracking-events.php:176` | Des violations futures passent inaperçues. | `phpcs:ignore` ciblé sur la ligne de signature. | S | oui |
| CODE-26 | Faible | 7 scripts CLI sans garde `defined( 'ABSPATH' ) \|\| exit;` (règle 3 : « chaque fichier PHP »). Tous les fichiers de `wp-content/` l'ont. | `scripts/configure-translatepress.php`, `create-homepage.php`, `create-menus.php`, `create-pages.php`, `generate-og-image.php`, `po-to-mo.php`, `deploy/seed-seo-settings.php` | Écart à la règle, sans risque réel (hors webroot). | Ajouter la garde pour les scripts lancés par `wp eval-file`, ou acter l'exception dans ClaudeMemo. | S | n/a |
| CODE-27 | Faible | **Hygiène du dépôt.** Ancien thème `famma-mennou-woocommerce-theme-v1` (40 Ko, non suivi, absent de prod) : code mort qui contient `woocommerce.php` et des coordonnées fictives « +216 XX XXX XXX ». Dossiers `zinger product/` (62 Mo) et `zinger product optimises/` (10 Mo) non ignorés à la racine d'`E:\Ecom`. `main` a 30 commits de retard, la branche a 3 commits non poussés. | `wp-content/themes/famma-mennou-woocommerce-theme-v1/footer.php:6` ; `git status` ; `git rev-list main..HEAD` | Un `git add -A` committerait 72 Mo de médias. Source de vérité ambiguë (main vs branche). | Supprimer l'ancien thème (après accord). Ajouter les médias au `.gitignore` ou les déplacer. Fusionner la branche dans `main` avant tout déploiement. | S | n/a |
| CODE-28 | Faible | API Graph `v21.0` figée. La v21 a été publiée en octobre 2024 et Meta garantit environ 2 ans, donc la fin de vie est imminente (D6 **ouvert**, date exacte à confirmer sur le journal des modifications de Meta). | `famma-core.php:1591` | Rejet des `Purchase` le jour du retrait. | Passer à la version courante (filtre `famma_capi_graph_version` déjà présent). | S | oui |
| CODE-29 | Faible | Métadonnées incohérentes : en-tête du plugin `Requires at least: 6.9` vs `minimum_wp_version 6.6` dans phpcs. Version `0.2.0` jamais incrémentée malgré de nombreuses fonctionnalités, et aucun mécanisme de migration. Menu FAMMA en `manage_woocommerce` mais sous-pages en `manage_options` (le gérant voit le menu sans ses écrans). | `famma-core.php:5-6`, `:30`, `:1912` ; `phpcs.xml.dist` ; `tracking-settings.php:301`, `pages-content.php:669` | Confusion à la mise à jour et pour les rôles. | Aligner les versions minimales. Versionnement sémantique avec `famma_core_db_version`. Décider d'une capacité unique. | S | oui |
| CODE-30 | Faible | `backup.sh` passe le mot de passe de la base en argument (`-p"${DB_PASSWORD}"`, visible dans la liste des processus du conteneur et fragile avec un `"`), et la rétention supprime sans confirmation. | `scripts/backup.sh:46-47`, `:92-105` | Fuite locale mineure, et perte possible d'anciennes sauvegardes. | `MYSQL_PWD` en variable ou fichier `.my.cnf`. Conserver au minimum les N dernières sauvegardes. | S | n/a |

---

## 4. Statut des constats des audits précédents

| Source | Constat | Statut au 2026-09-23 | Preuve |
|---|---|---|---|
| AUDIT 18/08 B1 | Token Meta en base via `official-facebook-pixel` | **Corrigé côté code et installation** : extension absente de Local et de la prod. Révocation du jeton non vérifiable. | liste des extensions |
| B2 | Deux `Purchase` par commande | **Corrigé** (émetteur unique : famma-core) | idem |
| B3 / B4 | Produits, avis et texte de test | **Non vérifiable** (base locale arrêtée) | — |
| B5 | Parcours COD jamais exécuté | **Non vérifiable ici**. Les commentaires du code citent des constats à l'exécution. | — |
| M3 | Aucun événement de milieu de tunnel | **Corrigé pour Meta** (`tracking-events.php`, commande express) ; **ouvert pour GA4** | CODE-15 |
| M4 | `event_time` = date de création | **Ouvert** | CODE-03 |
| M5 | Pas de reprise CAPI, envoi synchrone de 8 s | **Ouvert** | CODE-03 |
| M6 | Statuts natifs cliquables (« Terminée ») | **Ouvert** | CODE-04 |
| M7 | Polices absentes | **Corrigé** (5 fichiers woff2 versionnés) | `assets/fonts/` |
| M8 | WhatsApp éteint | **Code corrigé** (numéro saisissable dans FAMMA → Pages) ; valeur à fournir par le propriétaire | `famma-core.php:87-95` |
| Y1 | 19 erreurs phpcs | **Corrigé** (0) | §5 |
| Y2 / Y3 | Acquisition par ligne, coûts non figés | **Ouverts** | CODE-08 |
| Y5 | coût×3 hors écran admin | **Ouvert** | CODE-09 |
| Y7 | Avis sans achat vérifié | **Ouvert dans `setup.sh`** (état de la base non vérifié) | CODE-14 |
| Y8 | Méta personnelles sans exporteur ni effaceur | **Ouvert** | CODE-10 |
| Y9 | Cookie UTM sans `Secure` | **Ouvert** | CODE-02 |
| Y14 | Gouvernorats en dur en français | **Ouvert** | CODE-13 |
| D1 | Tailles de fichiers | **Ouvert, aggravé** (plugin 1972 → 2313 l. + 3326 l. dans `includes/`) | CODE-11 |
| D4 | Arabe en dur | **Ouvert** | CODE-13 |
| D5 | Dashboard collecté 3 fois, indexé par nom | **Ouvert** | CODE-08 |
| D6 | Graph `v21.0` | **Ouvert, devient urgent** | CODE-28 |
| D7 | `scripts/tmp-capi-check.php` | **Corrigé** (supprimé) | `ls scripts/` |
| RAPPORT-ARCHI 21/09 §3.2 et §5 | « Stock : décrément à la livraison », « réservation : décision non prise » | **Document obsolète** : le code réserve à « confirmé » (`famma-core.php:459-522`) | à mettre à jour |
| RAPPORT-ARCHI §5 | Sélecteur de langue attendu dans l'en-tête | **Code présent dans l'en-tête** (`header-parts.php:188`, `:208`), rendu non vérifié | — |
| AUDIT-TEXTES-EN-DUR §2.A | Unifier la réassurance | **Ouvert** (8 copies, voir CODE-11) | — |
| AUDIT-TEXTES-EN-DUR §2.B à §2.E | Pied de page, WhatsApp, titres d'accueil, parcours de commande éditables | **Ouverts** (barre supérieure, pages À propos/Contact et coordonnées : faits) | `topbar-content.php`, `pages-content.php` |

---

## 5. Résultats `php -l` et `phpcs`

- **Binaire** : `C:\Users\DELL\AppData\Roaming\Local\lightning-services\php-8.3.29+1\bin\win64\php.exe` (PHP 8.3.29 NTS).
- **`php -l`** : 46 fichiers PHP suivis par Git (hors `vendor/`), **0 erreur de syntaxe**.
- **`phpcs --standard=phpcs.xml.dist`** (WPCS 3 + PHPCompatibilityWP, `testVersion 8.1-`) : 46 fichiers analysés en 17,8 s, **0 erreur, 0 avertissement**, code de sortie 0.
  - Réserves : 28 directives `phpcs:ignore/disable`, dont une `disable` sans `enable` (CODE-25). Trois sniffs sont exclus par choix documenté (FileName, OneObjectStructurePerFile, SeparateFunctionsFromOO). Les scripts sont exemptés d'EscapeOutput et d'AlternativeFunctions.
- **Typage** : signatures et retours typés presque partout, propriétés typées `?self`. Aucun `declare(strict_types=1)` dans `wp-content/`. C'est un choix raisonnable pour des callbacks WordPress, mais il laisse le risque de TypeError décrit en CODE-22.
- **Garde ABSPATH** : présente dans les 39 fichiers de `wp-content/`, absente de 7 scripts CLI (CODE-26).
- **HPOS** : aucun `get_post_meta` ni `update_post_meta` sur une commande, aucun `$wpdb` sur les commandes. Seul `$wpdb` du projet : la purge des transients (CODE-24).
- **Doublons** : aucune fonction globale ni classe déclarée deux fois.

## 6. Gabarits WooCommerce surchargés (thème enfant)

| Surcharge | `@version` enfant | Cœur WC 11.0.1 | Écart |
|---|---|---|---|
| `woocommerce/archive-product.php` | *(absent)* | 8.6.0 | non traçable |
| `woocommerce/single-product.php` | *(absent)* | 1.6.4 | non traçable |
| `woocommerce/loop/no-products-found.php` | *(absent)* | 7.8.0 | non traçable |
| `woocommerce/single-product/tabs/tabs.php` | 9.8.0 | 9.8.0 | à jour |

Le thème remplace aussi `header.php` et `footer.php` de Kadence 1.5.2 : cette dette est documentée, à revérifier à chaque mise à jour de Kadence.

## 7. Actifs (poids mesurés, non minifiés)

- CSS du thème : `product.css` 30 Ko, `style.css` 28 Ko, `layout.css` 23,7 Ko, `shop.css` 18,5 Ko, `home.css` 15,7 Ko, `tokens.css` 13,6 Ko, `product-card.css` 12,6 Ko. Une fiche produit charge environ 120 Ko de CSS maison **non minifié**, en plus de Kadence et WooCommerce.
- JS maison : environ 45 Ko au total, sans dépendance jQuery (sauf l'écouteur `added_to_cart`, qui en dépend par nature).
- Polices : Poppins 4 × environ 8 Ko, Noto Kufi Arabic variable **124 Ko** (préchargée sur les pages RTL : un sous-ensemble arabe la réduirait fortement).
- **Prod sans extension de cache** (LiteSpeed sans LSCache) : aucun cache de page, aucune minification ni concaténation. `deploy.sh:278` demande de « vider le cache LiteSpeed » alors qu'aucun n'est installé.

---

## 8. Quick wins (≤ ½ journée chacun, par gain décroissant)

1. **E-mails de commande** pour `pending→pending-confirm`, plus un SMTP en prod (CODE-01).
2. **Handle UTM indépendant des pixels** et `;Secure` sur le cookie (CODE-02).
3. **Alias `completed` → callbacks de « livré »**, ou retrait des statuts natifs du sélecteur (CODE-04).
4. **`deploy.sh`** : lister les deux mu-plugins au lieu du dossier et faire un `tar` distant avant transfert (CODE-05, première moitié).
5. **Sauvegardes quotidiennes de l'hébergeur**, activées et testées par une restauration (CODE-06).
6. **Cache oEmbed** avant de déployer la vidéo produit (CODE-07, code non déployé).
7. `'yes' !== get_option(...)` dans `checkout.php:177` (CODE-18).
8. `@version` sur les 3 surcharges WooCommerce (CODE-17).
9. `JSON_HEX_TAG` dans le JSON-LD (CODE-23), `phpcs:disable` corrigé (CODE-25).
10. **Site Kit ou Tracking GA4 : en garder un seul** (CODE-15).
11. Mettre à jour `RAPPORT-ARCHITECTURE-2026-09-21.md` (stock réservé à « confirmé »), ignorer `zinger product*/`, fusionner la branche dans `main` avant le prochain déploiement (CODE-27).

## 9. Chantiers structurants (après les quick wins)

- Envoi CAPI par Action Scheduler avec reprise et horodatage de livraison (CODE-03).
- Coûts figés sur la ligne de commande et tableau de bord en une seule requête paginée (CODE-08).
- Source unique de réassurance, puis découpage de `famma-core.php` et `functions.php` par domaine (CODE-11).
- Logique catalogue et 404 sitemap déplacées du thème vers famma-core (CODE-12).
- Passe i18n complète : langue source unique, `.pot` régénéré, `fr_FR` et `ar` complets, gouvernorats traduisibles (CODE-13).
- Consentement et exporteur/effaceur de données personnelles (CODE-10).
