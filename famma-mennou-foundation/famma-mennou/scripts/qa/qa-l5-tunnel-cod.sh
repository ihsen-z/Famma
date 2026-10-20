#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel, lot 5 : tunnel COD de bout en bout
# (ClaudeMemo §11) sur famma-core 0.3.2.
#
#   1. HTTP réel vers le serveur web du clone (client curl à adresse fixe sur
#      le réseau Docker, compteur anti-abus isolé) : arrivée avec fbclid et
#      UTM, ajout au panier, page /commande/, checkout COD (wc-ajax=checkout).
#      Les cookies _fbp, _fbc et famma_utm sont posés comme le feraient le
#      Pixel et le script UTM (curl n'exécute pas de JavaScript).
#   2. WP-CLI (scripts/qa/qa-l5-tunnel-cod.php, mode run) : tâche OrderPlaced
#      mise en file par le serveur web, puis confirmée → expédiée → livrée en
#      administrateur, puis une seconde livraison. Dérogation D4 dans ce seul
#      processus ; le garde QA du clone répond à graph.facebook.com.
#   3. WP-CLI, mode cleanup : suppression de la commande du tunnel.
#
# Depuis famma-mennou/ (Git Bash) :
#   QA_L5_EVIDENCE=<dossier> QA_L5_CLIENT_IP=172.23.5.52 QA_L5_PHONE="29 515 021" \
#     bash scripts/qa/qa-l5-tunnel-cod.sh
# Ne touche ni à HPOS, ni aux options, ni aux volumes.
# ============================================================
set -u

cd "$(dirname "$0")/../.." || exit 1
export MSYS_NO_PATHCONV=1

EVIDENCE="${QA_L5_EVIDENCE:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-5/integration/passe-1/tunnel-cod}"
CLIENT_IP="${QA_L5_CLIENT_IP:-172.23.5.52}"
PHONE="${QA_L5_PHONE:-29 515 021}"
case "${CLIENT_IP}" in 172.23.[0-9]*.[0-9]*) ;; *) echo "QA_L5_CLIENT_IP : adresse du réseau du clone (172.23.x.y)" >&2; exit 2 ;; esac
case "${PHONE}" in *[!0-9\ ]*) echo "QA_L5_PHONE : chiffres et espaces seulement" >&2; exit 2 ;; esac
mkdir -p "${EVIDENCE}"
COMPOSE=(docker compose -p famma-qa-pixel -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
OUT="${EVIDENCE}/tunnel-cod"

PRODUCT="$("${COMPOSE[@]}" run --rm -T cli eval 'echo (int) wc_get_product_id_by_sku( "QA-SKU-1" );' 2>/dev/null </dev/null | tr -d '\r')"
if [ -z "${PRODUCT}" ] || [ "${PRODUCT}" = "0" ]; then
	echo "produit QA-SKU-1 introuvable" >&2
	exit 1
fi

echo "# tunnel COD $(date -u +%Y-%m-%dT%H:%M:%SZ) produit=${PRODUCT} ip_client=${CLIENT_IP} telephone=${PHONE}" >"${OUT}-client.txt"
docker run --rm -i --network famma-qa-pixel_default --ip "${CLIENT_IP}" --entrypoint sh wordpress:cli-php8.3 -s -- "${PRODUCT}" "${PHONE}" >>"${OUT}-client.txt" 2>&1 <<'CLIENT'
set -u
PRODUCT="$1"
PHONE="$2"
BASE="http://wordpress"
HOSTH="Host: localhost:8980"
UA="Mozilla/5.0 (Linux; Android 14; QA-L5I tunnel COD)"
J=/tmp/qa-l5-tunnel-jar
ms="$(date +%s)000"
# Cookies que poseraient le Pixel (_fbp), famma-fbc (_fbc depuis ?fbclid=) et le script UTM (famma_utm).
# Domaine « localhost » : avec un en-tête Host imposé, curl associe les cookies à cet hôte.
printf '# Netscape HTTP Cookie File\n' >"${J}"
printf 'localhost\tFALSE\t/\tFALSE\t0\t_fbp\tfb.1.%s.1234567890\n' "${ms}" >>"${J}"
printf 'localhost\tFALSE\t/\tFALSE\t0\t_fbc\tfb.1.%s.QAL5ITUNNEL\n' "${ms}" >>"${J}"
printf 'localhost\tFALSE\t/\tFALSE\t0\tfamma_utm\t%s\n' '%7B%22utm_source%22%3A%22qa-l5i-tunnel%22%2C%22utm_medium%22%3A%22cpc%22%2C%22fbclid%22%3A1%7D' >>"${J}"
echo "# ip_client=$(hostname -i)"
curl -s -o /dev/null -w "arrivee ?fbclid http=%{http_code}\n" -A "${UA}" -c "${J}" -b "${J}" -H "${HOSTH}" "${BASE}/?fbclid=QAL5ITUNNEL&utm_source=qa-l5i-tunnel&utm_medium=cpc"
curl -s -o /dev/null -w "ajout au panier http=%{http_code}\n" -A "${UA}" -c "${J}" -b "${J}" -H "${HOSTH}" "${BASE}/?add-to-cart=${PRODUCT}"
cart="$(curl -s -A "${UA}" -c "${J}" -b "${J}" -H "${HOSTH}" "${BASE}/panier/")"
echo "page panier octets=${#cart} produit_present=$(printf '%s' "${cart}" | grep -c 'QA')"
page="$(curl -s -A "${UA}" -c "${J}" -b "${J}" -H "${HOSTH}" "${BASE}/commande/")"
nonce="$(printf '%s' "${page}" | tr '>' '\n' | grep 'name="woocommerce-process-checkout-nonce"' | sed -n 's/.*value="\([0-9a-f]*\)".*/\1/p' | head -n 1)"
echo "page commande octets=${#page} nonce_trouve=$([ -n "${nonce}" ] && echo oui || echo non) element_attribution=$(printf '%s' "${page}" | grep -c 'wc-order-attribution-inputs')"
curl -s -A "${UA}" -b "${J}" -c "${J}" -H "${HOSTH}" -H "X-Requested-With: XMLHttpRequest" \
	-w "\nhttp=%{http_code}\n" \
	--data-urlencode "woocommerce-process-checkout-nonce=${nonce}" \
	--data-urlencode "_wp_http_referer=/commande/" \
	--data-urlencode "billing_first_name=QA-L5I Tunnel" \
	--data-urlencode "billing_last_name=COD" \
	--data-urlencode "billing_phone=${PHONE}" \
	--data-urlencode "billing_state=TN-51" \
	--data-urlencode "billing_country=TN" \
	--data-urlencode "billing_city=Sousse" \
	--data-urlencode "billing_address_1=QA-L5I adresse fictive du tunnel" \
	--data-urlencode "billing_email=" \
	--data-urlencode "payment_method=cod" \
	--data-urlencode "ship_to_different_address=0" \
	--data-urlencode "famma_hp_site=" \
	--data-urlencode "wc_order_attribution_source_type=utm" \
	--data-urlencode "wc_order_attribution_utm_source=qa-l5i-tunnel" \
	--data-urlencode "wc_order_attribution_utm_medium=cpc" \
	--data-urlencode "wc_order_attribution_user_agent=${UA}" \
	"${BASE}/?wc-ajax=checkout" >/tmp/qa-l5-tunnel-resp 2>&1
echo "checkout reponse=$(sed -e 's/key=wc_order_[A-Za-z0-9]*/key=[masque]/g' /tmp/qa-l5-tunnel-resp | tr '\n' ' ' | cut -c1-500)"
CLIENT
echo "# fin client $(date -u +%Y-%m-%dT%H:%M:%SZ)" >>"${OUT}-client.txt"

ORDER="$(sed -n 's/.*"order_id":\([0-9][0-9]*\).*/\1/p' "${OUT}-client.txt" | head -n 1)"
if [ -z "${ORDER}" ]; then
	echo "aucune commande créée, voir ${OUT}-client.txt" >&2
	exit 1
fi
ENV=(-e FAMMA_QA_L5_TEST=1 -e "FAMMA_QA_L5_ORDER=${ORDER}")
"${COMPOSE[@]}" run --rm -T "${ENV[@]}" -e FAMMA_QA_L5_TUNNEL_MODE=run cli eval-file /tests/qa/qa-l5-tunnel-cod.php >"${OUT}-serveur.json" 2>&1 </dev/null
if [ "${QA_L5_KEEP:-0}" != "1" ]; then
	"${COMPOSE[@]}" run --rm -T "${ENV[@]}" -e FAMMA_QA_L5_TUNNEL_MODE=cleanup cli eval-file /tests/qa/qa-l5-tunnel-cod.php >"${OUT}-cleanup.json" 2>&1 </dev/null
fi
echo "Preuves : ${OUT}-{client.txt,serveur.json,cleanup.json} (commande ${ORDER})"
