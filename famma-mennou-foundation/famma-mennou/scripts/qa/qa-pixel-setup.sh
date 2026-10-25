#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel isolé « famma-qa-pixel »
# (docs/PLAN-TESTS-PIXEL-2026-10-19.md, lot 0, scénario T0.3)
#
# IDEMPOTENT : relançable à volonté. WordPress n'est installé que s'il ne
# l'est pas ; les produits QA ne sont touchés que s'ils portent le marqueur
# _famma_qa_fixture ; le mot de passe admin n'est généré qu'une fois.
# Aucun secret réel : pixel factice 000000000000001, jeton factice
# QA-FAKE-TOKEN-NOT-REAL. Tout HTTP sortant est intercepté et journalisé par
# scripts/qa/qa-pixel-guard.php (wp-content/uploads/qa-http-log.jsonl).
#
# Toutes les commandes se lancent depuis famma-mennou/ (Git Bash) :
#
# DÉMARRAGE + installation complète (WooCommerce 11.1.2, Kadence 1.5.2,
# famma-core et famma-child en lecture seule, TND/3 décimales, TN, COD,
# permaliens jolis, HPOS activé, options famma factices, fixtures QA) :
#   bash scripts/qa/qa-pixel-setup.sh            # (= install)
#   Site : http://localhost:8980 — admin : http://localhost:8980/wp-admin
#   Identifiant/mot de passe : fichier $QA_EVIDENCE_DIR/qa-admin.txt (jamais
#   affiché par ce script).
#
# COMMANDE CLI (WP-CLI dans le clone) :
#   docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
#     -f scripts/qa/compose-qa-pixel.yml run --rm cli <commande wp>
#   ex. ... run --rm cli plugin list
#   Suites d'intégration et scripts QA : ajouter, avant « cli »,
#     -e FAMMA_INTEGRATION_TEST=1 -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db
#   puis « eval-file /tests/<script>.php » (scripts/ est monté sur /tests).
#   Action Scheduler / WP-Cron ne partent pas seuls (le rebouclage HTTP est
#   bloqué) : « ... run --rm cli action-scheduler run » pour vider la file.
#
# BASCULE HPOS (lot 2 : chaque scénario HPOS activé puis désactivé) :
#   bash scripts/qa/qa-pixel-setup.sh hpos off     # wp wc hpos sync, puis wp wc hpos disable
#   bash scripts/qa/qa-pixel-setup.sh hpos on      # wp wc hpos sync, puis wp wc hpos enable
#   bash scripts/qa/qa-pixel-setup.sh hpos status  # wp wc hpos status
#   Équivalent bas niveau : option woocommerce_custom_orders_table_enabled
#   (yes = HPOS, no = posts), à ne changer qu'après synchronisation
#   (woocommerce_custom_orders_table_data_sync_enabled ou « wp wc hpos sync »).
#
# JETON FACTICE : les suites d'intégration existantes refusent de tourner si
# l'option famma_meta_access_token n'est pas vide (elles posent la leur) :
#   bash scripts/qa/qa-pixel-setup.sh token off    # avant les 8 suites (T2.1)
#   bash scripts/qa/qa-pixel-setup.sh token on     # remet le jeton factice
#
# VÉRIFICATION : bash scripts/qa/qa-pixel-setup.sh verify
# ÉTAT        : bash scripts/qa/qa-pixel-setup.sh status
#
# ARRÊT (SANS -v : les volumes portent l'état du clone) :
#   bash scripts/qa/qa-pixel-setup.sh stop
#   = docker compose -p famma-qa-pixel -f scripts/compose-integration.yml \
#       -f scripts/qa/compose-qa-pixel.yml stop
#   Ne JAMAIS lancer « down -v » sans accord du propriétaire.
#
# Variables facultatives :
#   QA_EVIDENCE_DIR   dossier des preuves (défaut /e/Ecom/audit-preuves/2026-10-19-pixel/lot-0)
#   QA_SOURCE_VOLUME  volume Docker source de WooCommerce/Kadence
#                     (défaut famma-integration-20261010_integration_wp, lu en lecture seule)
#   QA_SOURCE_DIR     sinon, dossier wp-content local de même version (ex. site Local)
# ============================================================
set -euo pipefail

# Git Bash (MSYS) réécrit les chemins POSIX passés à docker.exe ; ces deux
# variables désactivent la conversion (voir scripts/setup.sh).
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

cd "$(dirname "$0")/../.."

PROJECT="famma-qa-pixel"
COMPOSE=(docker compose -p "${PROJECT}" -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
TEST_ENV=(-e FAMMA_INTEGRATION_TEST=1 -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db)
URL="http://localhost:8980"
PORT="8980"
WC_VERSION="11.1.2"
KADENCE_VERSION="1.5.2"
TARGET_VOLUME="${PROJECT}_integration_wp"
SOURCE_VOLUME="${QA_SOURCE_VOLUME:-famma-integration-20261010_integration_wp}"
SOURCE_DIR="${QA_SOURCE_DIR:-}"
EVIDENCE_DIR="${QA_EVIDENCE_DIR:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-0}"
ADMIN_FILE="${EVIDENCE_DIR}/qa-admin.txt"
ADMIN_USER="qa-admin"
FAKE_PIXEL="000000000000001"
FAKE_TOKEN="QA-FAKE-TOKEN-NOT-REAL"

log() { printf '\n==> %s\n' "$*"; }
step() { printf '    - %s\n' "$*"; }
die() { printf 'ERREUR: %s\n' "$*" >&2; exit 1; }

# WP-CLI dans le clone (conteneur jetable, utilisateur www-data). L'entrée
# standard est fermée : aucune commande ne doit lire celle du script.
wp() { "${COMPOSE[@]}" run --rm -T cli "$@" </dev/null; }
wp_test() { "${COMPOSE[@]}" run --rm -T "${TEST_ENV[@]}" cli "$@" </dev/null; }

# Série de commandes dans UN conteneur cli (sh -c, pas sh -s : une commande
# WP-CLI enfant lirait sinon la suite du script sur l'entrée standard).
# Usage : cli_sh "<script>" [options docker compose run, ex. -e VAR=x]
cli_sh() {
	local script="$1"
	shift
	"${COMPOSE[@]}" run --rm -T "$@" --entrypoint sh cli -c "${script}" </dev/null
}

# Lit l'en-tête « Version » d'un fichier du volume du clone.
volume_version() {
	docker run --rm -v "${TARGET_VOLUME}:/dst:ro" alpine sh -c \
		"grep -m1 -iE '^[[:space:]*]*Version:' '$1' 2>/dev/null | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r'" || true
}

guard_port() {
	local holders
	holders="$(docker ps --filter "publish=${PORT}" --format '{{.Label "com.docker.compose.project"}}' | grep -v "^${PROJECT}\$" || true)"
	if [ -n "${holders}" ]; then
		die "le port ${PORT} est tenu par un autre projet démarré (${holders}). Rien n'est arrêté automatiquement."
	fi
}

start_stack() {
	log "Démarrage du clone ${PROJECT} sur ${URL}"
	guard_port
	"${COMPOSE[@]}" up -d db wordpress
	step "attente des fichiers WordPress et de la base"
	local i
	for i in $(seq 1 60); do
		# Ping depuis le conteneur db, avec ses propres identifiants de fixture :
		# aucune dépendance au client mysql de l'image cli.
		if "${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null \
			&& "${COMPOSE[@]}" exec -T db sh -c 'mariadb-admin ping --silent -u"$MARIADB_USER" -p"$MARIADB_PASSWORD"' >/dev/null 2>&1; then
			step "WordPress et MariaDB prêts"
			return 0
		fi
		sleep 3
	done
	die "WordPress ou MariaDB ne répond pas après 3 minutes."
}

# Copie un dossier wp-content/<rel> depuis la source locale si la version
# attendue n'est pas déjà dans le volume du clone.
copy_component() {
	local rel="$1" header="$2" want="$3" have src_have
	have="$(volume_version "/dst/wp-content/${rel}/${header}")"
	if [ "${have}" = "${want}" ]; then
		step "${rel} ${want} déjà présent"
		return 0
	fi
	if [ -n "${SOURCE_DIR}" ]; then
		src_have="$(grep -m1 -iE '^[[:space:]*]*Version:' "${SOURCE_DIR}/${rel}/${header}" 2>/dev/null | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r' || true)"
		[ "${src_have}" = "${want}" ] || die "${SOURCE_DIR}/${rel} est en version « ${src_have} », ${want} attendu."
		local win_src="${SOURCE_DIR}"
		if command -v cygpath >/dev/null 2>&1; then win_src="$(cygpath -w "${SOURCE_DIR}")"; fi
		docker run --rm -v "${win_src}:/src:ro" -v "${TARGET_VOLUME}:/dst" alpine sh -c \
			"rm -rf '/dst/wp-content/${rel}' && mkdir -p '/dst/wp-content/${rel}' && cp -a '/src/${rel}/.' '/dst/wp-content/${rel}/' && chown -R 33:33 '/dst/wp-content/${rel}'"
	else
		docker volume inspect "${SOURCE_VOLUME}" >/dev/null 2>&1 \
			|| die "volume source ${SOURCE_VOLUME} introuvable : fournir QA_SOURCE_VOLUME ou QA_SOURCE_DIR (copie locale de ${rel} ${want})."
		src_have="$(docker run --rm -v "${SOURCE_VOLUME}:/src:ro" alpine sh -c \
			"grep -m1 -iE '^[[:space:]*]*Version:' '/src/wp-content/${rel}/${header}' 2>/dev/null | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r'" || true)"
		[ "${src_have}" = "${want}" ] || die "${SOURCE_VOLUME} contient ${rel} « ${src_have} », ${want} attendu."
		# Volume source monté en lecture seule ; copie par tar vers le volume du clone.
		docker run --rm -v "${SOURCE_VOLUME}:/src:ro" -v "${TARGET_VOLUME}:/dst" alpine sh -c \
			"rm -rf '/dst/wp-content/${rel}' && mkdir -p '/dst/wp-content/${rel}' && tar -C '/src/wp-content/${rel}' -cf - . | tar -C '/dst/wp-content/${rel}' -xf - && chown -R 33:33 '/dst/wp-content/${rel}'"
	fi
	have="$(volume_version "/dst/wp-content/${rel}/${header}")"
	[ "${have}" = "${want}" ] || die "copie de ${rel} incomplète (version lue : « ${have} »)."
	step "${rel} ${want} copié depuis la source locale"
}

install_core() {
	log "WordPress (installation unique)"
	mkdir -p "${EVIDENCE_DIR}"
	local password=""
	if [ -f "${ADMIN_FILE}" ]; then
		password="$(sed -n 's/^password=//p' "${ADMIN_FILE}")"
	fi
	if wp core is-installed >/dev/null 2>&1; then
		step "déjà installé"
		if ! wp user get "${ADMIN_USER}" --field=ID >/dev/null 2>&1; then
			die "l'utilisateur ${ADMIN_USER} est absent d'un site déjà installé : clone inattendu, arrêt."
		fi
		if [ -z "${password}" ]; then
			# Fichier perdu : nouveau mot de passe, écrit seulement dans le fichier.
			password="$(new_password)"
			wp user update "${ADMIN_USER}" --user_pass="${password}" --skip-email >/dev/null
			write_admin_file "${password}"
			step "mot de passe admin régénéré (fichier preuves, jamais affiché)"
		fi
		return 0
	fi
	if [ -z "${password}" ]; then
		password="$(new_password)"
	fi
	wp core install --url="${URL}" --title="FAMMA QA pixel (clone local)" \
		--admin_user="${ADMIN_USER}" --admin_password="${password}" \
		--admin_email="qa-admin@example.test" --skip-email >/dev/null
	write_admin_file "${password}"
	step "installé ; identifiants dans le fichier preuves (jamais affichés)"
}

# 24 caractères alphanumériques. Lecture finie de /dev/urandom : pas de
# SIGPIPE sous « set -o pipefail ».
new_password() {
	local pw=""
	while [ "${#pw}" -lt 24 ]; do
		pw="${pw}$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
	done
	printf '%s' "${pw:0:24}"
}

write_admin_file() {
	(
		umask 077
		{
			echo "# Clone QA pixel famma-qa-pixel — LOCAL UNIQUEMENT (${URL}/wp-admin)"
			echo "# Généré par scripts/qa/qa-pixel-setup.sh. Ne jamais réutiliser ailleurs."
			echo "user=${ADMIN_USER}"
			echo "password=$1"
		} >"${ADMIN_FILE}"
	)
}

configure() {
	log "Extensions, thème et réglages (WooCommerce TN / TND / COD)"
	wp plugin is-active woocommerce || wp plugin activate woocommerce >/dev/null
	step "WooCommerce actif"
	wp plugin is-active famma-core || wp plugin activate famma-core >/dev/null
	step "famma-core actif (lecture seule depuis le worktree)"
	wp theme is-active famma-child || wp theme activate famma-child >/dev/null
	step "famma-child actif (parent kadence)"
	wp plugin deactivate akismet hello >/dev/null 2>&1 || true

	# Un seul conteneur pour la série d'options : beaucoup plus rapide.
	local script
	read -r -d '' script <<'EOF' || true
set -e
q() { wp "$@" >/dev/null; }
q option update blogdescription "Clone local de test QA pixel — données fictives"
q option update blog_public 0
q option update woocommerce_default_country "TN:TN-11"
q option update woocommerce_allowed_countries "specific"
q option update woocommerce_specific_allowed_countries '["TN"]' --format=json
q option update woocommerce_ship_to_countries ""
q option update woocommerce_coming_soon "no"
q option update woocommerce_store_pages_only "no"
q option update woocommerce_currency "TND"
q option update woocommerce_currency_pos "right_space"
q option update woocommerce_price_thousand_sep " "
q option update woocommerce_price_decimal_sep ","
q option update woocommerce_price_num_decimals 3
q option update woocommerce_calc_taxes "no"
q option update woocommerce_enable_guest_checkout "yes"
q option update woocommerce_enable_checkout_login_reminder "no"
q option update woocommerce_cod_settings --format=json '{"enabled":"yes","title":"Paiement à la livraison — الدفع عند التوصيل","description":"Vous payez en espèces au livreur, au moment de la réception de votre commande.","instructions":"Préparez si possible le montant exact.","enable_for_methods":[],"enable_for_virtual":"no"}'
for gw in bacs cheque paypal; do wp option patch update "woocommerce_${gw}_settings" enabled no >/dev/null 2>&1 || true; done
q option update woocommerce_allow_tracking "no"
q option update woocommerce_show_marketplace_suggestions "no"
wp option update woocommerce_task_list_hidden "yes" >/dev/null 2>&1 || true
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1 || true
wp transient delete _wc_activation_redirect >/dev/null 2>&1 || true
echo "    - boutique TN, TND 3 décimales, COD seul, mode « bientôt » coupé"

# Pages WooCommerce (créées à l'activation, sinon par l'outil d'installation).
if [ "$(wp option get woocommerce_checkout_page_id 2>/dev/null || echo 0)" -le 0 ] 2>/dev/null; then
	wp wc tool run install_pages --user=qa-admin >/dev/null
fi
for spec in "woocommerce_shop_page_id:Boutique" "woocommerce_cart_page_id:Panier:[woocommerce_cart]" "woocommerce_checkout_page_id:Commande:[woocommerce_checkout]" "woocommerce_myaccount_page_id:Mon compte"; do
	opt="${spec%%:*}"; rest="${spec#*:}"; title="${rest%%:*}"; content=""
	case "${rest}" in *:*) content="${rest#*:}";; esac
	id="$(wp option get "${opt}" 2>/dev/null || echo 0)"
	if [ "${id:-0}" -gt 0 ] 2>/dev/null; then
		wp post update "${id}" --post_title="${title}" >/dev/null
		if [ -n "${content}" ]; then wp post update "${id}" --post_content="${content}" >/dev/null; fi
	fi
done
echo "    - pages Boutique / Panier / Commande / Mon compte (checkout classique par shortcode, comme la prod)"

# Livraison offerte sur la zone 0 (0 TND affiché, comme scripts/setup.sh).
if ! wp wc shipping_zone_method list 0 --user=qa-admin --format=csv 2>/dev/null | grep free_shipping >/dev/null; then
	wp wc shipping_zone_method create 0 --method_id=free_shipping --enabled=true --user=qa-admin >/dev/null
fi
echo "    - livraison offerte (zone 0)"

# Permaliens jolis, puis URL françaises de la prod (/produit/, /panier/, /commande/).
q rewrite structure '/%postname%/'
wp eval-file /tests/francise-slugs.php >/dev/null
q rewrite flush --hard
echo "    - permaliens /%postname%/ + slugs français (scripts/francise-slugs.php)"

# HPOS activé (passe 1 du lot 2).
if ! wp wc hpos status 2>/dev/null | grep -i 'HPOS enabled?: yes' >/dev/null; then
	wp wc hpos sync >/dev/null 2>&1 || true
	wp wc hpos enable >/dev/null
fi
echo "    - HPOS : $(wp wc hpos status 2>/dev/null | grep -i 'HPOS enabled' | tr -d '\r')"
EOF
	cli_sh "${script}"

	log "Options famma (valeurs factices uniquement)"
	tracking_options on
}

# Pixel factice, jeton factice (on) ou vidé (off), OrderPlaced activé, aucun code de test.
tracking_options() {
	local mode="$1" script
	read -r -d '' script <<'EOF' || true
set -e
wp option update famma_meta_pixel_id "${QA_FAKE_PIXEL}" >/dev/null
want=""
if [ "${QA_TOKEN_MODE}" = "on" ]; then want="${QA_FAKE_TOKEN}"; fi
# Mise à jour seulement si la valeur diffère : « option update --autoload »
# échoue sur une valeur inchangée. Comparaison en PHP, rien n'est affiché.
if ! QA_WANT="${want}" wp eval 'exit( getenv( "QA_WANT" ) === (string) get_option( "famma_meta_access_token", "" ) ? 0 : 1 );' >/dev/null 2>&1; then
	wp option update famma_meta_access_token "${want}" --autoload=no >/dev/null
fi
wp option update famma_meta_orderplaced_enabled 1 >/dev/null
for o in famma_meta_test_event_code famma_meta_capi_test_mode famma_ga4_measurement_id famma_tiktok_pixel_id; do
	wp option delete "${o}" >/dev/null 2>&1 || true
done
echo "    - famma_meta_pixel_id = $(wp option get famma_meta_pixel_id)"
echo "    - famma_meta_access_token : longueur $(wp eval 'echo strlen( (string) get_option( "famma_meta_access_token", "" ) );') (valeur jamais affichée ; factice si 22)"
echo "    - famma_meta_orderplaced_enabled = $(wp option get famma_meta_orderplaced_enabled)"
echo "    - code de test / mode test / GA4 / TikTok : absents"
EOF
	cli_sh "${script}" -e QA_TOKEN_MODE="${mode}" -e QA_FAKE_PIXEL="${FAKE_PIXEL}" -e QA_FAKE_TOKEN="${FAKE_TOKEN}"
}

fixtures() {
	log "Fixtures QA (produits marqués _famma_qa_fixture)"
	wp_test eval-file /tests/qa/qa-pixel-fixtures.php
}

hpos() {
	case "${1:-status}" in
	on)
		wp wc hpos sync || true
		wp wc hpos enable
		;;
	off)
		wp wc hpos sync || true
		wp wc hpos disable
		;;
	status) ;;
	*) die "usage : hpos on|off|status" ;;
	esac
	wp wc hpos status
	printf 'woocommerce_custom_orders_table_enabled = %s\n' "$(wp option get woocommerce_custom_orders_table_enabled 2>/dev/null | tr -d '\r')"
	printf 'woocommerce_custom_orders_table_data_sync_enabled = %s\n' "$(wp option get woocommerce_custom_orders_table_data_sync_enabled 2>/dev/null | tr -d '\r')"
}

# curl.exe natif sous Git Bash ne connaît pas /dev/null (conversion MSYS coupée).
NULL_DEVICE="/dev/null"
if command -v cygpath >/dev/null 2>&1; then NULL_DEVICE="NUL"; fi
http_code() { curl -s -o "${NULL_DEVICE}" -w '%{http_code}' "$@"; }

verify() {
	log "Vérification du clone"
	local jar product_url
	jar="$(mktemp)"
	# curl.exe natif sous Git Bash : chemin Windows (MSYS_NO_PATHCONV est actif).
	if command -v cygpath >/dev/null 2>&1; then jar="$(cygpath -w "${jar}")"; fi
	product_url="$(wp post list --post_type=product --name=qa-simple-sku --field=url 2>/dev/null | tr -d '\r' | head -n1)"
	printf 'accueil          %s  %s/\n' "$(http_code "${URL}/")" "${URL}"
	printf 'fiche produit    %s  %s\n' "$(http_code "${product_url}")" "${product_url}"
	local pid
	pid="$(wp post list --post_type=product --name=qa-simple-sku --field=ID 2>/dev/null | tr -d '\r' | head -n1)"
	http_code -c "${jar}" -b "${jar}" "${URL}/?add-to-cart=${pid}" >/dev/null
	printf 'panier           %s  %s/panier/\n' "$(http_code -c "${jar}" -b "${jar}" "${URL}/panier/")" "${URL}"
	printf 'commande         %s  %s/commande/ (panier non vide)\n' "$(http_code -c "${jar}" -b "${jar}" "${URL}/commande/")" "${URL}"
	rm -f "${jar}"
	log "Interception HTTP (wp_remote_post factice vers graph.facebook.com)"
	wp_test eval-file /tests/qa/qa-pixel-http-check.php
}

status() {
	"${COMPOSE[@]}" ps
	wp plugin list
	wp theme list --status=active,parent
	hpos status
}

case "${1:-install}" in
install)
	start_stack
	log "WooCommerce ${WC_VERSION} et Kadence ${KADENCE_VERSION} depuis une copie locale"
	copy_component "plugins/woocommerce" "woocommerce.php" "${WC_VERSION}"
	copy_component "themes/kadence" "style.css" "${KADENCE_VERSION}"
	install_core
	configure
	fixtures
	log "Clone prêt : ${URL} (laisser démarré ; arrêt : $0 stop)"
	;;
start) start_stack ;;
stop) "${COMPOSE[@]}" stop ;;
hpos) hpos "${2:-status}" ;;
token)
	case "${2:-}" in
	on | off) tracking_options "$2" ;;
	*) die "usage : token on|off" ;;
	esac
	;;
fixtures) fixtures ;;
verify) verify ;;
status) status ;;
*) die "usage : $0 [install|start|stop|status|verify|fixtures|hpos on|off|status|token on|off]" ;;
esac
