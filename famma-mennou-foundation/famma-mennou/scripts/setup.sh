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
WP="docker compose exec -T wpcli wp"
ADMIN="${WP_ADMIN_USER:-famma_admin}"

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
$WP option update timezone_string "Africa/Tunis" >/dev/null
$WP option update date_format "d/m/Y" >/dev/null
$WP option update start_of_week 1 >/dev/null
step "slogan de marque"
$WP option update blogdescription "فمّا ديما حاجة جديدة" >/dev/null
step "permaliens propres (§21)"
$WP rewrite structure '/%postname%/' --hard >/dev/null

step "retrait du contenu de démonstration WordPress (§58)"
HELLO="$($WP post list --post_type=post --name=hello-world --format=ids 2>/dev/null || true)"
if [ -n "${HELLO}" ]; then $WP post delete ${HELLO} --force >/dev/null || true; fi
SAMPLE="$($WP post list --post_type=page --name=sample-page --format=ids 2>/dev/null || true)"
if [ -n "${SAMPLE}" ]; then $WP post delete ${SAMPLE} --force >/dev/null || true; fi

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
$WP i18n make-mo /var/www/html/wp-content/plugins/famma-core/languages >/dev/null 2>&1 || true
$WP eval-file /scripts/configure-translatepress.php
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
$WP eval-file /scripts/create-homepage.php

log "Pages d'information et légales"
$WP eval-file /scripts/create-pages.php

log "Navigation"
$WP eval-file /scripts/create-menus.php

log "Image de partage social (§16)"
# Dans le conteneur `wordpress`, pas celui de WP-CLI : lui seul embarque GD
# avec les polices URW nécessaires au rendu du texte.
docker compose exec -T wordpress php /scripts/generate-og-image.php

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

$WP rewrite flush --hard >/dev/null

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
