#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel, lot 2, T2.12 (partie HTML et JS)
# Cherche le jeton factice du clone (QA-FAKE-TOKEN-NOT-REAL) et la chaîne
# « access_token » dans le HTML et le JS servis par le clone : pages
# publiques (visiteur anonyme), écrans d'administration (session admin QA),
# API REST, fichiers JS locaux référencés par ces pages.
#
# Depuis famma-mennou/ (Git Bash) :
#   QA_L2_EVIDENCE=/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1 \
#     bash scripts/qa/qa-l2-token-scan.sh
#
# Déroulé :
#   1. main avec FAMMA_QA_PIXEL_KEEP=1 : commandes QA-L2 gardées (notes Meta,
#      registre, journal famma-capi), pour scanner aussi leurs écrans admin ;
#   2. scan dans le conteneur cli (curl) ;
#   3. run-cleanup : suppression des commandes et du produit du passage.
#
# Session admin : un cookie d'authentification est fabriqué par WP-CLI
# (wp_generate_auth_cookie) pour le compte de test qa-admin du clone, avec
# un jeton de session créé puis détruit dans le même conteneur. Aucun mot
# de passe n'est saisi ; le cookie et le jeton ne sont jamais affichés.
# Le résultat ne contient que des comptes d'occurrences, jamais d'extrait.
# Ne touche ni à HPOS, ni aux options, ni aux volumes.
# ============================================================
set -u

cd "$(dirname "$0")/../.." || exit 1
export MSYS_NO_PATHCONV=1

EVIDENCE="${QA_L2_EVIDENCE:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1}"
SUFFIX="${QA_L2_SUFFIX:-}"
mkdir -p "${EVIDENCE}"
COMPOSE=(docker compose -p famma-qa-pixel -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
SUITE_ENV=(-e FAMMA_QA_PIXEL_TEST=1 -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db)
RUN="$(cat /proc/sys/kernel/random/uuid 2>/dev/null || python -c 'import uuid;print(uuid.uuid4())')"
OUT="${EVIDENCE}/t2.12-scan-jeton${SUFFIX}"

suite() {
	"${COMPOSE[@]}" run --rm -T "${SUITE_ENV[@]}" -e "FAMMA_QA_PIXEL_RUN=${RUN}" -e "FAMMA_QA_PIXEL_MODE=$1" "${@:2}" cli eval-file /tests/qa/test-qa-pixel-integration.php </dev/null
}

suite main -e FAMMA_QA_PIXEL_KEEP=1 >"${OUT}-main-garde.json" 2>&1
suite run-list >"${OUT}-run-list.json" 2>&1
ORDERS="$(python -I -c 'import json,sys
t=sys.stdin.buffer.read().decode("utf-8")
d=json.loads(t[t.index("{\n"):])
print(" ".join(str(i) for i in d["scenarios"]["run-list"]["orders"][:6]))' <"${OUT}-run-list.json")"

"${COMPOSE[@]}" run --rm -T --entrypoint sh cli -s -- ${ORDERS} >"${OUT}.txt" 2>&1 <<'SCAN'
set -u
BASE="http://wordpress"
HOSTH="Host: localhost:8980"
NEEDLE="QA-FAKE-TOKEN-NOT-REAL"
UA="Mozilla/5.0 (QA-L2 token scan)"
J=/tmp/qa-l2-scan-jar
W=/tmp/qa-l2-scan
mkdir -p "${W}"
echo "# scan $(date -u +%Y-%m-%dT%H:%M:%SZ) commandes=$*"
echo "# colonnes : contexte http octets occurrences_jeton occurrences_access_token url"

hpos="$(wp option get woocommerce_custom_orders_table_enabled 2>/dev/null)"
echo "# hpos=${hpos}"

scan() {
	ctx="$1"
	url="$2"
	shift 2
	f="${W}/page-$(printf '%s' "${ctx}${url}" | md5sum | cut -c1-12)"
	code="$(curl -s -o "${f}" -w '%{http_code}' -A "${UA}" -H "${HOSTH}" "$@" "${BASE}${url}")"
	bytes="$(wc -c <"${f}" | tr -d ' ')"
	tok="$(grep -o "${NEEDLE}" "${f}" | wc -l | tr -d ' ')"
	acc="$(grep -o 'access_token' "${f}" | wc -l | tr -d ' ')"
	printf '%s\t%s\t%s\t%s\t%s\t%s\n' "${ctx}" "${code}" "${bytes}" "${tok}" "${acc}" "${url}"
	# Scripts locaux référencés (src="http://localhost:8980/...js...").
	grep -o 'src="http://localhost:8980/[^"]*\.js[^"]*"' "${f}" 2>/dev/null | sed -e 's/^src="http:\/\/localhost:8980//' -e 's/"$//' -e 's/&#038;/\&/g' >>"${W}/scripts.txt"
}

# 1. Visiteur anonyme (panier rempli pour /panier/ et /commande/).
rm -f "${J}"
pid="$(wp eval 'echo (int) wc_get_product_id_by_sku( "QA-SKU-1" );' 2>/dev/null)"
curl -s -o /dev/null -A "${UA}" -c "${J}" -b "${J}" -H "${HOSTH}" "${BASE}/?add-to-cart=${pid}"
for u in / /produit/qa-simple-sku/ /produit/qa-simple-sans-sku/ /produit/qa-tailles/ /produit/qa-tailles-famma/ /boutique/ /panier/ /commande/ \
	"/?wc-ajax=get_refreshed_fragments" /wp-json/ /wp-json/wp/v2/settings /wp-json/wc/store/v1/cart /feed/ /wp-sitemap.xml "/?s=qa"; do
	scan anonyme "${u}" -c "${J}" -b "${J}"
done

# 2. Session admin QA fabriquée par WP-CLI (jamais affichée).
wp eval '
$u = get_user_by( "login", "qa-admin" );
if ( ! $u ) { fwrite( STDERR, "qa-admin absent\n" ); exit( 1 ); }
$exp   = time() + 900;
$token = WP_Session_Tokens::get_instance( $u->ID )->create( $exp );
$auth  = wp_generate_auth_cookie( $u->ID, $exp, "auth", $token );
$li    = wp_generate_auth_cookie( $u->ID, $exp, "logged_in", $token );
$_COOKIE[ LOGGED_IN_COOKIE ] = $li;
wp_set_current_user( $u->ID );
$nonce = wp_create_nonce( "wp_rest" );
file_put_contents( "/tmp/qa-l2-admin-cookie", AUTH_COOKIE . "=" . $auth . "; " . LOGGED_IN_COOKIE . "=" . $li );
file_put_contents( "/tmp/qa-l2-admin-nonce", $nonce );
file_put_contents( "/tmp/qa-l2-admin-token", $token );
echo "session admin QA creee\n";
' 2>/dev/null
cookie="$(cat /tmp/qa-l2-admin-cookie 2>/dev/null)"
nonce="$(cat /tmp/qa-l2-admin-nonce 2>/dev/null)"
for u in /wp-admin/ "/wp-admin/admin.php?page=famma-tracking" "/wp-admin/admin.php?page=famma-dashboard" /wp-admin/options.php \
	"/wp-admin/admin.php?page=wc-status" "/wp-admin/admin.php?page=wc-status&tab=logs" "/wp-admin/admin.php?page=wc-settings" \
	"/wp-admin/admin.php?page=wc-orders" "/wp-admin/edit.php?post_type=shop_order" "/wp-admin/tools.php?page=action-scheduler&status=complete" \
	"/wp-admin/tools.php?page=action-scheduler&s=famma"; do
	scan admin "${u}" -H "Cookie: ${cookie}"
done
for id in "$@"; do
	if [ "${hpos}" = "yes" ]; then
		scan admin "/wp-admin/admin.php?page=wc-orders&action=edit&id=${id}" -H "Cookie: ${cookie}"
	else
		scan admin "/wp-admin/post.php?post=${id}&action=edit" -H "Cookie: ${cookie}"
	fi
	scan admin-rest "/wp-json/wc/v3/orders/${id}" -H "Cookie: ${cookie}" -H "X-WP-Nonce: ${nonce}"
	scan admin-rest "/wp-json/wc/v3/orders/${id}/notes" -H "Cookie: ${cookie}" -H "X-WP-Nonce: ${nonce}"
done
scan admin-rest /wp-json/wp/v2/settings -H "Cookie: ${cookie}" -H "X-WP-Nonce: ${nonce}"
scan admin-rest "/wp-json/wc/v3/system_status" -H "Cookie: ${cookie}" -H "X-WP-Nonce: ${nonce}"

# 3. Fichiers JS locaux référencés par toutes ces pages.
sort -u "${W}/scripts.txt" >"${W}/scripts-uniques.txt"
echo "# scripts_locaux_uniques=$(wc -l <"${W}/scripts-uniques.txt" | tr -d ' ')"
while IFS= read -r u; do
	scan js "${u}"
done <"${W}/scripts-uniques.txt"

# 4. Fin de session admin : jeton de session détruit.
wp eval '
$u = get_user_by( "login", "qa-admin" );
$t = (string) file_get_contents( "/tmp/qa-l2-admin-token" );
if ( $u && "" !== $t ) { WP_Session_Tokens::get_instance( $u->ID )->destroy( $t ); echo "session admin QA detruite\n"; }
' 2>/dev/null
rm -f /tmp/qa-l2-admin-cookie /tmp/qa-l2-admin-nonce /tmp/qa-l2-admin-token
echo "# fin $(date -u +%Y-%m-%dT%H:%M:%SZ)"
SCAN

suite run-cleanup >"${OUT}-run-cleanup.json" 2>&1
echo "Preuves : ${OUT}.txt et ${OUT}-{main-garde,run-list,run-cleanup}.json"
