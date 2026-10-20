#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel, lot 5 : passe d'intégration complète sur
# famma-core 0.3.2 (hors 8 suites existantes, lancées par qa-l2-run-suites.sh).
#
#   1. test-qa-pixel-integration.php, modes main puis express (T2.2 à T2.13
#      mis à jour pour la 0.3.2) ;
#   2. test-qa-l5-acceptance.php, modes sans-derogation puis serveur (D1, D4,
#      D5, D7, A3, G1 à G4, V1 à V9 côté serveur) ;
#   3. qa-l2-checkout-double.sh (D3 : 2 POST simultanés, re-commande C1) ;
#   4. qa-l5-tunnel-cod.sh (tunnel COD de bout en bout, ClaudeMemo §11).
#
# Depuis famma-mennou/ (Git Bash) :
#   QA_L5_EVIDENCE=<dossier> QA_L5_D3_IP=172.23.5.54 QA_L5_D3_PHONE="29 515 03" \
#   QA_L5_TUNNEL_IP=172.23.5.52 QA_L5_TUNNEL_PHONE="29 515 023" \
#     bash scripts/qa/qa-l5-integration-run.sh
# Ne bascule pas HPOS, ne touche ni aux volumes ni aux options partagées.
# ============================================================
set -u

cd "$(dirname "$0")/../.." || exit 1
export MSYS_NO_PATHCONV=1

EVIDENCE="${QA_L5_EVIDENCE:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-5/integration/passe-1}"
mkdir -p "${EVIDENCE}/main" "${EVIDENCE}/acceptance" "${EVIDENCE}/tunnel-cod"
COMPOSE=(docker compose -p famma-qa-pixel -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
DB=(-e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db)
WIN_PWD="$(pwd)"
if command -v cygpath >/dev/null 2>&1; then WIN_PWD="$(cygpath -w "$(pwd)")"; fi
WATCHDOG=(-v "${WIN_PWD}/wp-content/mu-plugins/famma-measurement-watchdog.php:/qa-watchdog/famma-measurement-watchdog.php:ro")

step() { echo "# $(date -u +%Y-%m-%dT%H:%M:%SZ) $*"; }

step "main"
"${COMPOSE[@]}" run --rm -T -e FAMMA_QA_PIXEL_TEST=1 "${DB[@]}" -e FAMMA_QA_PIXEL_MODE=main cli eval-file /tests/qa/test-qa-pixel-integration.php >"${EVIDENCE}/main/t2.2-t2.13-main.json" 2>&1 </dev/null
step "express"
"${COMPOSE[@]}" run --rm -T -e FAMMA_QA_PIXEL_TEST=1 "${DB[@]}" -e FAMMA_QA_PIXEL_MODE=express cli eval-file /tests/qa/test-qa-pixel-integration.php >"${EVIDENCE}/main/t2.6-t2.10-express.json" 2>&1 </dev/null
step "acceptation sans dérogation"
"${COMPOSE[@]}" run --rm -T -e FAMMA_QA_L5_TEST=1 "${DB[@]}" -e FAMMA_QA_L5_MODE=sans-derogation cli eval-file /tests/qa/test-qa-l5-acceptance.php >"${EVIDENCE}/acceptance/l5-sans-derogation.json" 2>&1 </dev/null
step "acceptation serveur"
"${COMPOSE[@]}" run --rm -T "${WATCHDOG[@]}" -e FAMMA_QA_L5_TEST=1 "${DB[@]}" -e FAMMA_QA_L5_MODE=serveur cli eval-file /tests/qa/test-qa-l5-acceptance.php >"${EVIDENCE}/acceptance/l5-serveur.json" 2>&1 </dev/null
step "D3"
QA_L2_EVIDENCE="${EVIDENCE}/acceptance" QA_L2_SUFFIX=-d3 QA_L2_CLIENT_IP="${QA_L5_D3_IP:-172.23.5.54}" QA_L2_LABEL=QA-L5I QA_L2_PHONE_PREFIX="${QA_L5_D3_PHONE:-29 515 03}" bash scripts/qa/qa-l2-checkout-double.sh 2
step "tunnel COD"
QA_L5_EVIDENCE="${EVIDENCE}/tunnel-cod" QA_L5_CLIENT_IP="${QA_L5_TUNNEL_IP:-172.23.5.52}" QA_L5_PHONE="${QA_L5_TUNNEL_PHONE:-29 515 023}" bash scripts/qa/qa-l5-tunnel-cod.sh
for f in main/t2.2-t2.13-main main/t2.6-t2.10-express acceptance/l5-sans-derogation acceptance/l5-serveur acceptance/t2.6-checkout-parallele-d3-verify; do
	# Python Windows : chemin Windows (cygpath) sous Git Bash.
	py_in="${EVIDENCE}/${f}.json"
	if command -v cygpath >/dev/null 2>&1; then py_in="$(cygpath -w "${py_in}")"; fi
	python -I scripts/qa/qa-l2-resume.py "${py_in}" >"${EVIDENCE}/${f}-resume.txt" 2>&1
	echo "${f} : $(tail -n 1 "${EVIDENCE}/${f}-resume.txt")"
done
step "fin"
