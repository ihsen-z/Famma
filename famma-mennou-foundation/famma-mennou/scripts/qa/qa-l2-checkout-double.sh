#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel, lot 2, T2.6 (partie parallèle)
# Double soumission SIMULTANÉE du checkout classique, par de vraies requêtes
# HTTP (curl) vers le serveur web du clone, comme un double clic qui envoie
# deux POST /?wc-ajax=checkout avec la même session et le même nonce.
#
# Depuis famma-mennou/ (Git Bash) :
#   QA_L2_EVIDENCE=/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1 \
#     bash scripts/qa/qa-l2-checkout-double.sh [manches]
#   manches : nombre de doubles soumissions (défaut 2 ; 2 au plus, car
#   famma-core limite à 5 commandes par IP et par heure).
#   QA_L2_PHONE_PREFIX : début du téléphone synthétique (défaut « 20 000 02 »,
#   suivi du numéro de manche). famma-core limite à 3 commandes par téléphone
#   et par jour (compteur famma_ab_phone_*, 24 h) : une nouvelle passe le même
#   jour doit prendre un autre préfixe (passe 2 : « 20 000 03 »).
#
# Étapes :
#   1. checkout-setup  : produit QA-L2 propre au passage (test-qa-pixel-integration.php).
#   2. client curl dans le conteneur cli (IP distincte de celle du navigateur
#      du lot 3) : panier, page /commande/ (nonce), puis 2 POST en parallèle.
#   3. checkout-verify : exécute dans un processus WP-CLI, HTTP intercepté
#      (pre_http_request), les tâches OrderPlaced de ces commandes et compte
#      les envois.
#   4. checkout-cleanup : supprime les commandes et le produit du passage.
#
# Lot 5 (D3, famma-core 0.3.2) : attendu désormais 1 commande et 1 OrderPlaced
# par manche (0.3.1 : 2 et 2). Après la 1re manche, une re-commande corrigée
# (même session, panier refait, autre téléphone) doit créer une NOUVELLE
# commande (C1, non-régression du verrou).
# QA_L2_CLIENT_IP : adresse fixe du client curl sur le réseau Docker du clone
# (ex. 172.23.5.51), pour que le compteur anti-abus par IP (5 commandes par
# heure) ne soit partagé avec aucun autre agent ; sinon le client tourne
# dans un conteneur cli ordinaire, comme au lot 2.
# QA_L2_LABEL : préfixe des noms de facturation (défaut « QA-L2 »).
#
# Aucune requête ne sort du réseau Docker : les tâches OrderPlaced mises en
# file par le serveur web ne partent pas seules (rebouclage bloqué) et sont
# exécutées à l'étape 3 avec la réponse de Meta simulée.
# Ne touche ni à HPOS, ni aux options, ni aux volumes.
# ============================================================
set -u

cd "$(dirname "$0")/../.." || exit 1
export MSYS_NO_PATHCONV=1

EVIDENCE="${QA_L2_EVIDENCE:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1}"
SUFFIX="${QA_L2_SUFFIX:-}"
ROUNDS="${1:-2}"
PHONE_PREFIX="${QA_L2_PHONE_PREFIX:-20 000 02}"
case "${PHONE_PREFIX}" in *[!0-9\ ]*) echo "QA_L2_PHONE_PREFIX : chiffres et espaces seulement" >&2; exit 2 ;; esac
case "${ROUNDS}" in 1 | 2) ;; *) echo "manches : 1 ou 2" >&2; exit 2 ;; esac
CLIENT_IP="${QA_L2_CLIENT_IP:-}"
LABEL="${QA_L2_LABEL:-QA-L2}"
case "${CLIENT_IP}" in '' | 172.23.[0-9]*.[0-9]*) ;; *) echo "QA_L2_CLIENT_IP : adresse du réseau du clone (172.23.x.y)" >&2; exit 2 ;; esac
case "${LABEL}" in *[!A-Za-z0-9-]*) echo "QA_L2_LABEL : lettres, chiffres et tirets" >&2; exit 2 ;; esac
mkdir -p "${EVIDENCE}"
COMPOSE=(docker compose -p famma-qa-pixel -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
SUITE_ENV=(-e FAMMA_QA_PIXEL_TEST=1 -e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db)
RUN="$(cat /proc/sys/kernel/random/uuid 2>/dev/null || python -c 'import uuid;print(uuid.uuid4())')"
OUT="${EVIDENCE}/t2.6-checkout-parallele${SUFFIX}"

suite() {
	"${COMPOSE[@]}" run --rm -T "${SUITE_ENV[@]}" -e "FAMMA_QA_PIXEL_RUN=${RUN}" -e "FAMMA_QA_PIXEL_MODE=$1" cli eval-file /tests/qa/test-qa-pixel-integration.php </dev/null
}

echo "# run=${RUN} manches=${ROUNDS} prefixe_tel=${PHONE_PREFIX} ip_client=${CLIENT_IP:-conteneur-cli} libelle=${LABEL} debut=$(date -u +%Y-%m-%dT%H:%M:%SZ)" >"${OUT}-client.txt"
suite checkout-setup >"${OUT}-setup.json" 2>&1
PRODUCT="$(sed -n 's/.*"product_id": *\([0-9][0-9]*\).*/\1/p' "${OUT}-setup.json" | head -n 1)"
if [ -z "${PRODUCT}" ]; then
	echo "checkout-setup sans produit, voir ${OUT}-setup.json" >&2
	exit 1
fi

# Client HTTP (sh POSIX + curl) : conteneur cli, ou image cli à adresse fixe.
if [ -n "${CLIENT_IP}" ]; then
	CLIENT_CMD=(docker run --rm -i --network famma-qa-pixel_default --ip "${CLIENT_IP}" --entrypoint sh wordpress:cli-php8.3)
else
	CLIENT_CMD=("${COMPOSE[@]}" run --rm -T --entrypoint sh cli)
fi
"${CLIENT_CMD[@]}" -s -- "${PRODUCT}" "${ROUNDS}" "${PHONE_PREFIX}" "${LABEL}" >>"${OUT}-client.txt" 2>&1 <<'CLIENT'
set -u
PRODUCT="$1"
ROUNDS="$2"
PHONE_PREFIX="$3"
LABEL="$4"
BASE="http://wordpress"
HOSTH="Host: localhost:8980"
UA="Mozilla/5.0 (QA-L2 synthetic double submit)"
echo "# ip_client=$(hostname -i) produit=${PRODUCT}"
round=1
while [ "${round}" -le "${ROUNDS}" ]; do
	jar="/tmp/qa-l2-jar-${round}"
	rm -f "${jar}"
	curl -s -o /dev/null -w "panier http=%{http_code}\n" -A "${UA}" -c "${jar}" -b "${jar}" -H "${HOSTH}" "${BASE}/?add-to-cart=${PRODUCT}"
	page="$(curl -s -A "${UA}" -c "${jar}" -b "${jar}" -H "${HOSTH}" "${BASE}/commande/")"
	nonce="$(printf '%s' "${page}" | tr '>' '\n' | grep 'name="woocommerce-process-checkout-nonce"' | sed -n 's/.*value="\([0-9a-f]*\)".*/\1/p' | head -n 1)"
	echo "manche=${round} nonce_trouve=$([ -n "${nonce}" ] && echo oui || echo non) page_commande_octets=${#page}"
	for worker in a b; do
		curl -s -A "${UA}" -b "${jar}" -c "/tmp/qa-l2-jar-${round}-${worker}" -H "${HOSTH}" \
			-H "X-Requested-With: XMLHttpRequest" \
			-w "\nhttp=%{http_code} debut=%{time_starttransfer} total=%{time_total}\n" \
			--data-urlencode "woocommerce-process-checkout-nonce=${nonce}" \
			--data-urlencode "_wp_http_referer=/commande/" \
			--data-urlencode "billing_first_name=${LABEL} Parallele" \
			--data-urlencode "billing_last_name=manche ${round}" \
			--data-urlencode "billing_phone=${PHONE_PREFIX}${round}" \
			--data-urlencode "billing_state=TN-51" \
			--data-urlencode "billing_country=TN" \
			--data-urlencode "billing_city=Sousse" \
			--data-urlencode "billing_address_1=${LABEL} adresse fictive" \
			--data-urlencode "billing_email=" \
			--data-urlencode "payment_method=cod" \
			--data-urlencode "ship_to_different_address=0" \
			--data-urlencode "famma_hp_site=" \
			--data-urlencode "wc_order_attribution_source_type=utm" \
			--data-urlencode "wc_order_attribution_utm_source=qa-l2-facebook" \
			--data-urlencode "wc_order_attribution_utm_medium=qa-l2-paid" \
			--data-urlencode "wc_order_attribution_user_agent=${UA}" \
			"${BASE}/?wc-ajax=checkout" >"/tmp/qa-l2-resp-${round}-${worker}" 2>&1 &
	done
	wait
	for worker in a b; do
		# Réponse JSON de WooCommerce ; la clé de commande de l'URL de retour est masquée.
		echo "manche=${round} soumission=${worker} reponse=$(sed -e 's/key=wc_order_[A-Za-z0-9]*/key=[masque]/g' "/tmp/qa-l2-resp-${round}-${worker}" | tr '\n' ' ' | cut -c1-600)"
	done
	if [ "${round}" -eq 1 ]; then
		# Re-commande corrigée (C1) : même session, panier refait, autre téléphone -> nouvelle commande.
		curl -s -o /dev/null -w "re-commande panier http=%{http_code}\n" -A "${UA}" -c "${jar}" -b "${jar}" -H "${HOSTH}" "${BASE}/?add-to-cart=${PRODUCT}"
		page="$(curl -s -A "${UA}" -c "${jar}" -b "${jar}" -H "${HOSTH}" "${BASE}/commande/")"
		nonce="$(printf '%s' "${page}" | tr '>' '\n' | grep 'name="woocommerce-process-checkout-nonce"' | sed -n 's/.*value="\([0-9a-f]*\)".*/\1/p' | head -n 1)"
		curl -s -A "${UA}" -b "${jar}" -c "${jar}" -H "${HOSTH}" -H "X-Requested-With: XMLHttpRequest" \
			--data-urlencode "woocommerce-process-checkout-nonce=${nonce}" \
			--data-urlencode "_wp_http_referer=/commande/" \
			--data-urlencode "billing_first_name=${LABEL} Parallele" \
			--data-urlencode "billing_last_name=re-commande" \
			--data-urlencode "billing_phone=${PHONE_PREFIX}9" \
			--data-urlencode "billing_state=TN-51" \
			--data-urlencode "billing_country=TN" \
			--data-urlencode "billing_city=Sousse" \
			--data-urlencode "billing_address_1=${LABEL} adresse fictive" \
			--data-urlencode "billing_email=" \
			--data-urlencode "payment_method=cod" \
			--data-urlencode "ship_to_different_address=0" \
			--data-urlencode "famma_hp_site=" \
			"${BASE}/?wc-ajax=checkout" >/tmp/qa-l2-resp-reorder 2>&1
		echo "re-commande (C1) telephone=${PHONE_PREFIX}9 reponse=$(sed -e 's/key=wc_order_[A-Za-z0-9]*/key=[masque]/g' /tmp/qa-l2-resp-reorder | tr '\n' ' ' | cut -c1-600)"
	fi
	round=$((round + 1))
done
CLIENT
echo "# fin client $(date -u +%Y-%m-%dT%H:%M:%SZ)" >>"${OUT}-client.txt"

suite checkout-verify >"${OUT}-verify.json" 2>&1
suite checkout-cleanup >"${OUT}-cleanup.json" 2>&1
echo "Preuves : ${OUT}-{client.txt,setup.json,verify.json,cleanup.json}"
