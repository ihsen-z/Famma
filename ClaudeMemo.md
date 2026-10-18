# ClaudeMemo — Source de vérité du projet FAMMA

## 1. Always check docs with Context7 first

Before writing or editing any code that touches Next.js App Router,
Supabase (client, Auth, or RLS policies), or any other external library
used in this project, call Context7 to fetch the current official docs for
that library first.

Do this even if the pattern feels familiar — library APIs change, and this
project must never use deprecated patterns (e.g. old Supabase auth helpers,
outdated Next.js data-fetching patterns). If Context7 is unavailable for
some reason, say so explicitly before proceeding instead of silently
falling back to memory.

## 2. Graph-first reading — query before Read (token discipline)

Before reading ANY existing file, you MUST consult the Graphify graph
first. Reading a whole source file when the graph could answer is a
violation. The goal is token reduction: one graph query replaces several
full-file reads.

Order of operations for every "understand existing code" need:

1. Ensure the graph is fresh. If files changed since the last build, run
   `/graphify . --update` first. A stale graph is worse than none.
2. Locate via the graph, not Read:
   - Concept / "where is X" / "what connects X":
     `graphify query "<question>"`
   - Specific symbol: `graphify explain "<SymbolName>"`
   - How A reaches B: `graphify path "<A>" "<B>"`
   These return nodes with `source_location` (file:line).
3. Read ONLY the cited lines — use Read with offset/limit on the exact
   file:line the graph returned. Never a full-file Read, never a broad
   Grep, unless the graph returned nothing.
4. Fallback (allowed only after step 2 returns empty/irrelevant): say
   "graph miss: <query>" explicitly, then Grep/Read directly. Do not
   silently skip the graph.

Hard rules:
- No full-file Read of an existing file without a prior graph query in
  the same task. New files you are creating are exempt.
- Prefer `explain`/`path` (exact-symbol) over free-text `query`
  (free-text BFS misses on vocabulary mismatch).

If no graph exists yet for the project, run `/graphify .` once first, then
proceed.

---

# Bonnes pratiques de code — FAMMA MENNOU (WordPress / WooCommerce)

Ces règles complètent les règles métier ; elles concernent la QUALITÉ et la SÉCURITÉ du code. Elles sont non négociables.

## 3. PHP & standards WordPress : tolérance zéro

* Respecter les WordPress Coding Standards (WPCS via PHP_CodeSniffer). Lancer `phpcs` avec le ruleset WordPress avant de considérer une tâche terminée, et corriger les erreurs — ne rien laisser « pour plus tard ».
* Cibler PHP 8.1+. Typer explicitement paramètres et valeurs de retour (`string`, `int`, `bool`, `array`, `void`, `?Type`). Pas de type flou quand un type précis est possible.
* Chaque fichier PHP commence par la garde `defined( 'ABSPATH' ) || exit;`. Aucun accès direct au fichier ne doit exécuter du code.
* Vérifier la syntaxe (`php -l`) de tout fichier modifié avant de finir.

## 4. Sécurité : ne jamais faire confiance à une entrée (séquence obligatoire)

Cette séquence s'applique à toute entrée utilisateur (checkout, admin, AJAX, REST) et à toute sortie. Non négociable :

1. **Nonce** : vérifier `wp_verify_nonce()` / `check_admin_referer()` / `check_ajax_referer()` avant toute action qui écrit ou modifie.
2. **Capacité** : vérifier `current_user_can( ... )` avant toute action privilégiée (ex. `edit_product`, `manage_woocommerce`). Jamais se fier au fait que « la page est dans l'admin ».
3. **Assainir en entrée** : `wp_unslash()` puis `sanitize_text_field()`, `absint()`, `sanitize_email()`, `wc_clean()`, etc. — jamais utiliser `$_POST` / `$_GET` bruts.
4. **Échapper en sortie** (échappement tardif, au moment de l'affichage) : `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Aucune variable n'est imprimée sans échappement.
5. **SQL** : jamais de requête concaténée. Utiliser les APIs WP/WooCommerce ; si `$wpdb` est inévitable, passer par `$wpdb->prepare()`.

Quand un prompt dit « sécurise les entrées selon les standards du projet », c'est cette séquence qui est référencée — l'appliquer en entier même si le prompt ne détaille pas chaque étape.

## 5. Base de données & WooCommerce : passer par les APIs, jamais en direct

* Utiliser les objets CRUD de WooCommerce (`wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()` + `$order->save()`, `wc_get_product()`…). Ne jamais lire/écrire les commandes directement dans `postmeta` : le projet déclare la compatibilité HPOS, donc l'ordre peut ne pas vivre dans les tables `posts`/`postmeta`.
* Toute nouvelle fonctionnalité Woo passe par des hooks (`add_action`, `add_filter`), jamais par une modification du cœur WordPress/WooCommerce ni du thème parent.
* Préfixer toutes les méta et options avec `_famma_` / `famma_`.

## 6. Architecture : logique concentrée, fichiers courts, zéro doublon

* Toute la logique métier vit dans le plugin `famma-core`. Le thème ne fait que de la présentation. WooCommerce et WordPress ne sont jamais édités.
* Le plugin est actuellement un fichier consolidé. S'il faut le découper, le faire par responsabilité (un fichier = une classe/un domaine : statuts, financials, checkout Tunisie, WhatsApp, tracking…), avec un chargement propre. Ne jamais définir deux fois la même classe/fonction (cause de fatal error à l'activation). Un fichier qui dépasse ~200–250 lignes est un signal pour le scinder.
* Un `add_action`/`add_filter` par responsabilité, avec des méthodes nommées clairement — pas de grosses closures fourre-tout.

## 7. Internationalisation & RTL

* Aucune chaîne visible en dur. Toute chaîne passe par `__()`, `esc_html__()`, `esc_attr__()`, `_e()`… avec le text domain unique `famma-core`.
* Prévoir le bilingue FR / Derja et le RTL : styliser avec des propriétés logiques (`margin-inline-start`, `padding-inline-end`…) plutôt que `left`/`right`, pour que l'arabe s'affiche correctement sans CSS dupliqué.

## 8. Secrets & configuration

* Aucun secret en dur. Tout vient de `.env` → constantes → plugin.
* Le token Meta (CAPI) reste strictement côté serveur : jamais imprimé dans le HTML, jamais envoyé au navigateur, jamais journalisé.
* Ne jamais committer `.env` (uniquement `.env.example`).

## 9. Assets & front-end

* Charger CSS/JS via `wp_enqueue_style()` / `wp_enqueue_script()` avec une version (pour le cache-busting) et les bonnes dépendances. Pas de `<script>` ni `<style>` en dur dans le HTML.
* Mobile-first : cibles tactiles ≥ 48px, CTA « Commander / أطلب توا » accessible et visible sur mobile.

## 10. Performance

* Éviter les requêtes lourdes à chaque chargement de page. Pour les agrégats (ex. tableau de bord), paginer ou mettre en cache via des transients (`get_transient()` / `set_transient()`) plutôt que `wc_get_orders( limit=-1 )` dès que le volume de commandes grandit.
* Ne charger le code d'admin que dans l'admin (`is_admin()`), et le code front que sur le front.

## 11. Validation avant de considérer une tâche « terminée »

Une tâche n'est pas finie tant que ces points ne sont pas verts :

1. `php -l` OK sur les fichiers modifiés ; `phpcs` (WordPress) sans erreur.
2. Plugin activé avec `WP_DEBUG = true` et `wp-content/debug.log` vérifié : aucun warning/notice/deprecated introduit.
3. Le parcours réel testé de bout en bout : ajout au panier → checkout COD → transitions de statut (confirmé → expédié → livré) → « livré » marque bien la commande payée et déclenche le tracking serveur une seule fois.
4. Résumer brièvement ce qui a été touché et pourquoi.

## 12. Git & intégrité des données

* Commits clairs, un par étape logique. Jamais `.env`, secrets, ou `backups/` (données clients) dans un commit.
* Aucune donnée fictive : pas de faux produits, faux avis, fausses commandes, faux textes légaux. Le catalogue démarre vide. Si des données de test sont nécessaires en local, les marquer clairement et les supprimer avant toute remise/mise en production.

## 13. Personnalisation des thèmes & plugins WordPress : jamais dans le code d'origine

* **Thème** : ne jamais éditer un thème tiers (Kadence / Blocksy / Astra) directement — une mise à jour écraserait tout. Passer par un **thème enfant** (`style.css` + `functions.php` avec `Template:` renseigné) ou par les hooks/filtres du thème parent. Surcharger un template en le copiant dans le thème enfant en respectant la hiérarchie de templates WordPress, et n'y garder que le strict nécessaire.
* **WooCommerce** : surcharger un template via `wp-content/themes/<enfant>/woocommerce/...` uniquement si aucun hook ne suffit. Toujours préférer `add_action`/`add_filter` (`woocommerce_before_add_to_cart_button`, `woocommerce_get_price_html`…) à la copie d'un template — un template copié devient une dette à resynchroniser à chaque version de Woo.
* **Plugins tiers** : ne jamais modifier le code d'un plugin installé. Étendre via ses hooks, ses filtres ou son API depuis `famma-core`. Si un plugin n'expose pas de hook, ouvrir un ticket amont ou encapsuler le besoin dans `famma-core` — pas de patch local silencieux.
* **Logique métier FAMMA** : elle reste dans le plugin `famma-core`. Le thème enfant ne contient que présentation (CSS, gabarits, `wp_enqueue_*`). Un site doit rester fonctionnel après changement de thème.
* **Mises à jour** : garder WordPress, WooCommerce, thèmes et plugins à jour ; vérifier après chaque mise à jour que les surcharges du thème enfant et les hooks de `famma-core` tiennent toujours (`debug.log` propre, parcours COD testé).
* **Ajouts « éditeur »** : blocs, patterns, `theme.json`, options du Customizer — préférer ces mécanismes natifs au CSS forcé en `!important`. Toute valeur éditable par le propriétaire doit l'être depuis l'admin (cf. règle « tout paramétrable depuis l'admin »).


---

## Contexte

* Marque : FAMMA (فمّا). Slogan : فمّا ديما حاجة جديدة.
* Boutique e-commerce Tunisie, mobile-first, paiement à la livraison (COD).
* Stack : WordPress + WooCommerce, orchestré via Docker (`docker-compose.yml` déjà présent).
* Production : fammaminnou.tn, hébergée chez OXA Host. Panneau serveur DirectAdmin : https://da5.tn.oxa.host:2222/evo/ (le propriétaire ouvre lui-même la session ; ne jamais y saisir d'identifiants ; tout envoi de fichiers en prod exige son accord explicite).
* Langues : Français + Derja tunisienne, support RTL.
* Spécification complète : voir `MASTER-PROMPT.md` (64 sections).

## Règles NON négociables

1. Aucune donnée inventée. Jamais de faux produits, faux avis, faux témoignages, faux textes légaux, fausses statistiques. Le catalogue démarre vide. (§58 / §60)
2. Prix = coût × 3 par défaut, avec override manuel qui gagne toujours. (§3)
3. Livraison : 7 TND coût interne, 0 TND affiché au client. (§4)
4. COD : une commande n'est marquée « payée » qu'au statut « livré ». (§2)
5. Aucun secret en dur dans le code. Tout passe par `.env` / constantes. Ne JAMAIS committer `.env` (uniquement `.env.example`). Le token Meta reste côté serveur et n'est jamais envoyé au navigateur. (§17 / §39 / §41)
6. Ne jamais modifier le core WordPress/WooCommerce. Toute la logique métier vit dans le plugin `famma-core`.
7. Le plugin actuel est un fichier unique consolidé : `wp-content/plugins/famma-core/famma-core.php` (namespace `Famma\Core`, pattern singleton, pas d'autoloader). Construire dessus. Ne pas le remplacer par une architecture multi-fichiers sans raison, et ne jamais introduire de doublons de classes (source de fatal error à l'activation).

## Architecture existante — à préserver

* Plugin : `wp-content/plugins/famma-core/famma-core.php` (fichier unique).
* Déjà implémenté : statuts COD personnalisés, champs financiers produit (coût×3 auto), split livraison 0/7 TND, 24 gouvernorats + validation téléphone tunisien, bouton WhatsApp piloté par env, tracking GA4/Meta/TikTok piloté par env (Purchase déclenché côté serveur à « livré »), capture UTM → commande.
* Docs de référence dans `docs/` (ARCHITECTURE, ORDER-WORKFLOW, PLUGIN-REGISTER, DESIGN-SYSTEM).

## Ce que l'agent NE fait PAS seul — demander confirmation d'abord

* Acheter un domaine, effectuer un paiement réel, saisir de vrais identifiants/secrets.
* Toute action irréversible : suppression de données/BDD, `git push`, `docker compose down -v` (efface les volumes → perte de données).
* Choix propriétaire : thème (Kadence / Blocksy / Astra, §24), approche multilingue / RTL (§5), numéro WhatsApp réel, IDs Pixel/GA4 réels, 3 premiers vrais produits, textes légaux.

## Ce que le propriétaire fournit (ne pas inventer, demander)

* Numéro WhatsApp réel.
* `META_PIXEL_ID`, `META_ACCESS_TOKEN`, `META_TEST_EVENT_CODE`, `TIKTOK_PIXEL_ID`, `GA4_MEASUREMENT_ID`, `GOOGLE_SITE_VERIFICATION`.
* Vrais produits, prix de revient, textes légaux, logo / visuels.

## Workflow attendu

* Docker Desktop doit tourner avant tout `docker compose up`.
* Versionner avec Git à chaque étape logique ; messages de commit clairs ; jamais `.env` dans un commit.
* Après activation du plugin, valider avec `WP_DEBUG=true` et vérifier l'absence d'erreur PHP.
* Travailler phase par phase ; montrer le résultat et attendre le feu vert avant les étapes sensibles.
