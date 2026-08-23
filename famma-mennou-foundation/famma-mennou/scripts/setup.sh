#!/usr/bin/env bash
# ============================================================
# FAMMA MENNOU — provisionnement local (§57 phases 3-4, §11, §12, §58)
#
# IDEMPOTENT : sûr à relancer autant de fois que nécessaire.
# Aucune étape n'écrase de contenu réel :
#   - WordPress n'est installé que s'il ne l'est pas déjà ;
#   - seuls les produits d'exemple WooCommerce (SKU `woo-*`) sont
#     supprimés, jamais un produit du propriétaire (§58, §60).
#
# Prérequis : Docker Desktop démarré, `docker compose up -d` effectué.
# ============================================================
set -euo pipefail

# Git Bash (MSYS) réécrit tout argument ressemblant à un chemin POSIX en
# chemin Windows avant de le passer à docker.exe : `/var/www/html` devient
# `C:/Program Files (x86)/Git/var/www/html`. Ces deux variables désactivent
# la conversion ; elles sont sans effet sous Linux et macOS.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
	echo "ERREUR: .env introuvable. Faites: cp .env.example .env" >&2
	exit 1
fi
set -a
. ./.env
set +a

# Pas de --path : le répertoire de travail de l'image wordpress:cli est
# déjà /var/www/html, et un argument de chemin serait une cible de plus
# pour la réécriture MSYS.
# ------------------------------------------------------------
# Portabilite de l'environnement d'execution.
#
# Par defaut, tout passe par la stack Docker de ce depot. Les quatre
# variables ci-dessous permettent de rejouer EXACTEMENT le meme
# provisionnement ailleurs — notamment sous Local (Local by Flywheel), ou il
# n'y a ni conteneur ni chemin /var/www/html. Un seul script, donc une seule
# verite : pas de copie divergente a maintenir.
#
#   FAMMA_WP_CLI      commande WP-CLI complete (ex. "php wp-cli.phar --path=...")
#   FAMMA_SCRIPTS_DIR dossier contenant ces scripts, vu par WP-CLI
#   FAMMA_LANG_DIR    dossier des traductions de famma-core, vu par WP-CLI
#   FAMMA_URL         URL du site, si elle differe de WP_HOME du .env
#   FAMMA_ADMIN       compte administrateur, s'il differe de WP_ADMIN_USER
#   FAMMA_REWRITE_HARD  passer une valeur vide sous nginx : --hard ne
#                     concerne qu'Apache et son .htaccess
#   FAMMA_SKIP_OG=1   saute la generation de l'image de partage (GD + polices
#                     URW absentes hors du conteneur ; le PNG est deja
#                     versionne dans le theme)
# ------------------------------------------------------------
WP="${FAMMA_WP_CLI:-docker compose exec -T wpcli wp}"
SCRIPTS_DIR="${FAMMA_SCRIPTS_DIR:-/scripts}"
LANG_DIR="${FAMMA_LANG_DIR:-/var/www/html/wp-content/plugins/famma-core/languages}"
WP_HOME="${FAMMA_URL:-${WP_HOME}}"
ADMIN="${FAMMA_ADMIN:-${WP_ADMIN_USER:-famma_admin}}"
REWRITE_HARD="${FAMMA_REWRITE_HARD---hard}"

log()  { printf '\n==> %s\n' "$1"; }
step() { printf '    - %s\n' "$1"; }

# ------------------------------------------------------------
# 0. Attendre WP-CLI
# ------------------------------------------------------------
log "Attente du conteneur wpcli"
for i in $(seq 1 30); do
	if $WP --info >/dev/null 2>&1; then break; fi
	if [ "$i" -eq 30 ]; then
		echo "ERREUR: wpcli ne répond pas. Vérifiez 'docker compose ps'." >&2
		exit 1
	fi
	sleep 2
done

# ------------------------------------------------------------
# 1. WordPress
# ------------------------------------------------------------
if $WP core is-installed >/dev/null 2>&1; then
	log "WordPress déjà installé — étape ignorée"
else
	log "Installation de WordPress"
	$WP core install \
		--url="${WP_HOME}" \
		--title="${WP_TITLE:-FAMMA MENNOU}" \
		--admin_user="${ADMIN}" \
		--admin_password="${WP_ADMIN_PASSWORD}" \
		--admin_email="${WP_ADMIN_EMAIL}" \
		--skip-email
fi

log "Réglages WordPress"
step "locale FR + fuseau Africa/Tunis"
$WP language core install fr_FR >/dev/null 2>&1 || true
$WP site switch-language fr_FR >/dev/null 2>&1 || true
# Le pack du coeur ne traduit pas les extensions : sans cette ligne, tout le
# checkout WooCommerce s'affiche en anglais sur un site declare en francais.
$WP language plugin install --all fr_FR >/dev/null 2>&1 || true
$WP option update timezone_string "Africa/Tunis" >/dev/null
$WP option update date_format "d/m/Y" >/dev/null
$WP option update start_of_week 1 >/dev/null
step "titre et slogan de marque"
# blogname n'est pose que par `core install`. Si WordPress a ete installe
# ailleurs (Local, hebergeur, import), le titre reste celui de cette
# installation — constate a l'execution : « famma » au lieu de « FAMMA MENNOU ».
$WP option update blogname "${WP_TITLE:-FAMMA MENNOU}" >/dev/null
$WP option update blogdescription "فمّا ديما حاجة جديدة" >/dev/null
step "permaliens propres (§21)"
$WP rewrite structure '/%postname%/' ${REWRITE_HARD} >/dev/null

step "retrait du contenu de démonstration WordPress (§58)"
# Les slugs dependent de la langue choisie A L'INSTALLATION, pas de la langue
# reglee ensuite : une installation francaise cree « bonjour-tout-le-monde » et
# « page-d-exemple ». Ne chercher que les slugs anglais laissait donc l'article
# et la page de demonstration en ligne, sans que rien ne le signale — constate
# a l'execution sur une installation fr_FR.
for slug in hello-world bonjour-tout-le-monde; do
	ID="$($WP post list --post_type=post --name="${slug}" --format=ids 2>/dev/null || true)"
	if [ -n "${ID}" ]; then $WP post delete ${ID} --force >/dev/null || true; fi
done
for slug in sample-page page-d-exemple; do
	ID="$($WP post list --post_type=page --name="${slug}" --format=ids 2>/dev/null || true)"
	if [ -n "${ID}" ]; then $WP post delete ${ID} --force >/dev/null || true; fi
done

# ------------------------------------------------------------
# 2. WooCommerce
# ------------------------------------------------------------
if $WP plugin is-installed woocommerce >/dev/null 2>&1; then
	log "WooCommerce déjà présent"
else
	log "Installation de WooCommerce"
	$WP plugin install woocommerce >/dev/null
fi
$WP plugin activate woocommerce >/dev/null 2>&1 || true

log "Configuration WooCommerce — Tunisie / TND / COD (§11, §12)"
step "boutique en Tunisie, vente à la Tunisie uniquement"
# L'adresse exacte de la boutique est laissée vide : le propriétaire la
# renseignera. Rien n'est inventé (§60).
$WP option update woocommerce_default_country "TN:TN-11" >/dev/null
$WP option update woocommerce_allowed_countries "specific" >/dev/null
$WP option update woocommerce_specific_allowed_countries '["TN"]' --format=json >/dev/null
$WP option update woocommerce_ship_to_countries "" >/dev/null

step "boutique visible (pas de mode « bientôt disponible »)"
# WooCommerce active ce mode par defaut sur une installation neuve : boutique,
# fiches produit, panier et checkout deviennent invisibles aux visiteurs, sans
# que rien ne le signale. Choix explicite du proprietaire : la boutique reste
# ouverte. Le catalogue vide est traite par l'accueil dedie (§9), pas en
# masquant le site.
$WP option update woocommerce_coming_soon "no" >/dev/null

step "devise TND, 3 décimales (millimes)"
$WP option update woocommerce_currency "TND" >/dev/null
$WP option update woocommerce_currency_pos "right_space" >/dev/null
$WP option update woocommerce_price_thousand_sep " " >/dev/null
$WP option update woocommerce_price_decimal_sep "," >/dev/null
$WP option update woocommerce_price_num_decimals 3 >/dev/null

step "taxes désactivées tant que le propriétaire n'a pas tranché (§11)"
$WP option update woocommerce_calc_taxes "no" >/dev/null

step "commande sans compte obligatoire (§13)"
$WP option update woocommerce_enable_guest_checkout "yes" >/dev/null
$WP option update woocommerce_enable_checkout_login_reminder "no" >/dev/null
$WP option update woocommerce_enable_signup_and_login_from_checkout "no" >/dev/null

step "avis clients activés, aucun avis factice (§31, §48)"
$WP option update woocommerce_enable_reviews "yes" >/dev/null
$WP option update woocommerce_review_rating_verification_required "no" >/dev/null

step "paiement à la livraison comme unique moyen de paiement"
$WP option update woocommerce_cod_settings --format=json '{"enabled":"yes","title":"Paiement à la livraison — الدفع عند التوصيل","description":"Vous payez en espèces au livreur, au moment de la réception de votre commande.","instructions":"Préparez si possible le montant exact.","enable_for_methods":[],"enable_for_virtual":"no"}' >/dev/null
for gw in bacs cheque paypal; do
	$WP option patch update "woocommerce_${gw}_settings" enabled no >/dev/null 2>&1 || true
done

step "masquage de l'assistant d'installation WooCommerce"
$WP option update woocommerce_task_list_hidden "yes" >/dev/null 2>&1 || true
$WP option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1 || true

log "Traductions et pages WooCommerce en français"
# Le premier `language plugin install --all` de la section 1 ne pouvait rien
# traduire : WooCommerce n'etait pas encore installe a ce moment-la. Sans ce
# second passage, tout le checkout reste en anglais (E-28 du plan UX) et les
# quatre pages creees a l'activation gardent leurs titres anglais.
$WP language plugin install --all fr_FR >/dev/null 2>&1 || true
step "paquets de langue des extensions installés"

# Renomme une page WooCommerce si elle porte encore son titre anglais par
# defaut. Une page deja renommee par le proprietaire n'est jamais ecrasee.
rename_wc_page() {
	page_option="$1"
	page_english="$2"
	page_french="$3"
	page_id="$($WP option get "${page_option}" 2>/dev/null || echo 0)"
	if [ "${page_id:-0}" -le 0 ] 2>/dev/null; then return 0; fi
	page_title="$($WP post get "${page_id}" --field=post_title 2>/dev/null || true)"
	if [ "${page_title}" = "${page_english}" ]; then
		$WP post update "${page_id}" --post_title="${page_french}" >/dev/null
		step "« ${page_english} » renommée en « ${page_french} »"
	fi
}
rename_wc_page woocommerce_shop_page_id      "Shop"       "Boutique"
rename_wc_page woocommerce_cart_page_id      "Cart"       "Panier"
rename_wc_page woocommerce_checkout_page_id  "Checkout"   "Commande"
rename_wc_page woocommerce_myaccount_page_id "My account" "Mon compte"

# ------------------------------------------------------------
# 3. Plugin métier
# ------------------------------------------------------------
log "Activation de FAMMA Core"
$WP plugin activate famma-core >/dev/null

# ------------------------------------------------------------
# 3b. Multilingue FR + Derja (ADR-005, §5)
# ------------------------------------------------------------
log "Multilingue : TranslatePress (français par défaut + arabe)"
if ! $WP plugin is-installed translatepress-multilingual >/dev/null 2>&1; then
	$WP plugin install translatepress-multilingual >/dev/null
	step "TranslatePress installé"
fi
$WP plugin activate translatepress-multilingual >/dev/null 2>&1 || true
$WP language core install ar >/dev/null 2>&1 || true
$WP i18n make-mo "${LANG_DIR}" >/dev/null 2>&1 || true
$WP eval-file "${SCRIPTS_DIR}/configure-translatepress.php"
step "traduction automatique désactivée — la Derja est écrite par un humain (§5)"

# ------------------------------------------------------------
# 4. Livraison — 0 TND côté client (§3, §4)
# ------------------------------------------------------------
log "Livraison : 0 TND affiché au client"
step "le coût réel de 7 TND reste interne, suivi par famma-core"
# La zone 0 (« régions non couvertes ») suffit ici : la boutique ne vend
# qu'en Tunisie, elle couvre donc 100 % des commandes. Le propriétaire
# pourra créer des zones par gouvernorat plus tard (§4).
if ! $WP wc shipping_zone_method list 0 --user="${ADMIN}" --format=csv 2>/dev/null | grep -q "free_shipping"; then
	$WP wc shipping_zone_method create 0 --method_id=free_shipping --user="${ADMIN}" >/dev/null
	step "méthode « livraison offerte » créée"
else
	step "méthode déjà en place"
fi

# ------------------------------------------------------------
# 5. Checkout classique (ADR-003)
# ------------------------------------------------------------
log "Checkout classique par shortcode (voir docs/DECISIONS.md, ADR-003)"
CHECKOUT_ID="$($WP option get woocommerce_checkout_page_id 2>/dev/null || echo 0)"
if [ "${CHECKOUT_ID:-0}" -gt 0 ] 2>/dev/null; then
	$WP post update "${CHECKOUT_ID}" --post_content='[woocommerce_checkout]' >/dev/null
	step "page Commande basculée en shortcode"
fi
CART_ID="$($WP option get woocommerce_cart_page_id 2>/dev/null || echo 0)"
if [ "${CART_ID:-0}" -gt 0 ] 2>/dev/null; then
	$WP post update "${CART_ID}" --post_content='[woocommerce_cart]' >/dev/null
	step "page Panier basculée en shortcode"
fi

# ------------------------------------------------------------
# 6. Thème (ADR-004)
# ------------------------------------------------------------
log "Thème : Kadence + enfant famma-child"
if ! $WP theme is-installed kadence >/dev/null 2>&1; then
	$WP theme install kadence >/dev/null
	step "Kadence installé"
fi
$WP theme activate famma-child >/dev/null
step "famma-child actif"

# ------------------------------------------------------------
# 6b. Contenu : accueil, pages d'information, navigation (§8, §9, §45)
# ------------------------------------------------------------
log "Page d'accueil"
$WP eval-file "${SCRIPTS_DIR}/create-homepage.php"

log "Pages d'information et légales"
$WP eval-file "${SCRIPTS_DIR}/create-pages.php"

log "Navigation"
$WP eval-file "${SCRIPTS_DIR}/create-menus.php"

log "Image de partage social (§16)"
# Dans le conteneur `wordpress`, pas celui de WP-CLI : lui seul embarque GD
# avec les polices URW nécessaires au rendu du texte.
if [ -n "${FAMMA_SKIP_OG:-}" ]; then
	step "ignoree (FAMMA_SKIP_OG) — assets/img/famma-og.png est deja versionne"
else
	docker compose exec -T wordpress php /scripts/generate-og-image.php
fi

# ------------------------------------------------------------
# 7. Catalogue vide (§58)
# ------------------------------------------------------------
log "Vérification du catalogue"
REMOVED=0
for sku in woo-album woo-beanie woo-beanie-with-logo woo-belt woo-cap \
	woo-hoodie woo-hoodie-with-logo woo-hoodie-with-pocket \
	woo-hoodie-with-zipper woo-long-sleeve-tee woo-polo woo-single \
	woo-sunglasses woo-tshirt woo-tshirt-with-logo woo-vneck-tee; do
	ID="$($WP post list --post_type=product --meta_key=_sku --meta_value="${sku}" --format=ids 2>/dev/null || true)"
	if [ -n "${ID}" ]; then
		$WP post delete ${ID} --force >/dev/null || true
		REMOVED=$((REMOVED + 1))
	fi
done
if [ "${REMOVED}" -gt 0 ]; then
	step "${REMOVED} produit(s) d'exemple WooCommerce supprimé(s)"
else
	step "aucun produit d'exemple — catalogue vide, conforme au §58"
fi

$WP rewrite flush ${REWRITE_HARD} >/dev/null

# ------------------------------------------------------------
# Résumé
# ------------------------------------------------------------
cat <<EOF

============================================================
 FAMMA MENNOU — provisionnement terminé
============================================================
 Boutique      ${WP_HOME}
 Admin         ${WP_HOME}/wp-admin  (utilisateur: ${ADMIN})
 phpMyAdmin    http://localhost:${PMA_PORT:-8081}   (profil « tools »)
 Mailpit       http://localhost:${MAILPIT_UI_PORT:-8025} (profil « tools »)

 Reste à faire par le propriétaire :
   - renseigner l'adresse de la boutique (WooCommerce > Réglages)
   - fournir FAMMA_WHATSAPP_NUMBER et les identifiants de tracking
     dans .env, puis relancer: docker compose up -d
   - fournir le logo FAMMA (§7) et les 3 premiers produits réels (§58)
============================================================
EOF
