#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — clone QA pixel, lot 2, T2.1
# Lance les 8 suites d'intégration existantes de scripts/ sur le clone
# famma-qa-pixel et archive chaque sortie (JSON ou PASS/FAIL) avec son code
# de sortie.
#
# Depuis famma-mennou/ (Git Bash) :
#   QA_L2_EVIDENCE=/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1 \
#     bash scripts/qa/qa-l2-run-suites.sh [suite...]
#   suites : capi fbc registre meta-value express woo sizes report (défaut : toutes)
#   QA_L2_SUFFIX : suffixe des preuves des suites capi et woo (relance sans
#   écraser la preuve d'origine).
#   suite facultative capi-relance : relance documentée de test-capi-integration
#   (option famma_meta_orderplaced_enabled supprimée avant la suite, puis
#   « token on ») — contourne l'artefact « options-restored » du harnais,
#   expliqué dans passe-1/t2.1-capi-integration-artefact-options.txt.
#
# Différence avec le mode d'emploi du lot 0 : le jeton factice n'est PAS
# vidé pour tout le clone (« token off ») — un autre agent utilise le clone
# en parallèle. Chaque processus de suite charge
# scripts/qa/qa-l2-mask-qa-token.php (--require), qui lui fait voir une
# option vide tant que la valeur stockée est le jeton factice du clone.
# Après chaque suite qui réécrit les options de suivi, « token on » remet
# l'état QA (pixel factice, jeton factice, OrderPlaced actif, ni code ni
# mode test).
#
# Ne touche ni à HPOS, ni aux volumes, ni aux autres projets Docker.
# ============================================================
set -u

cd "$(dirname "$0")/../.." || exit 1
export MSYS_NO_PATHCONV=1

EVIDENCE="${QA_L2_EVIDENCE:-/e/Ecom/audit-preuves/2026-10-19-pixel/lot-2/passe-1}"
mkdir -p "${EVIDENCE}"
COMPOSE=(docker compose -p famma-qa-pixel -f scripts/compose-integration.yml -f scripts/qa/compose-qa-pixel.yml)
BASE_ENV=(-e FAMMA_TEST_DB_NAME=famma_integration -e FAMMA_TEST_DB_HOST=db)
MASK=(--require=/tests/qa/qa-l2-mask-qa-token.php)
SUMMARY="${EVIDENCE}/t2.1-resume.txt"

# Exécute une suite ; $1 = fichier de preuve, $2... = options « run » puis
# arguments wp. Le code de sortie est ajouté à la preuve et au résumé.
run_suite() {
	local out="$1"
	shift
	local started rc
	started="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
	{
		echo "# debut ${started}"
		echo "# commande : ${COMPOSE[*]} run --rm -T $*"
	} >"${out}"
	"${COMPOSE[@]}" run --rm -T "$@" </dev/null >>"${out}" 2>&1
	rc=$?
	echo "# fin $(date -u +%Y-%m-%dT%H:%M:%SZ) code_sortie=${rc}" >>"${out}"
	printf '%s\t%s\t%s\n' "$(basename "${out}")" "${rc}" "${started}" >>"${SUMMARY}"
	return 0
}

restore_tracking() {
	bash scripts/qa/qa-pixel-setup.sh token on >>"${EVIDENCE}/t2.1-restauration-options.txt" 2>&1
}

uuid() { cat /proc/sys/kernel/random/uuid 2>/dev/null || python -c 'import uuid;print(uuid.uuid4())'; }

suite_capi() {
	run_suite "${EVIDENCE}/t2.1-capi-integration${QA_L2_SUFFIX:-}.txt" "${BASE_ENV[@]}" -e FAMMA_INTEGRATION_TEST=1 cli "${MASK[@]}" eval-file /tests/test-capi-integration.php
	restore_tracking
}

# Relance documentée : la suite mémorise l'option famma_meta_orderplaced_enabled
# ('1'), la passe à false puis true, puis la restaure à '1' ; update_option()
# ne rafraîchit pas le cache quand la base contient déjà '1', d'où un faux
# échec « options-restored ». Option supprimée avant : la suite mémorise null
# et la supprime à la fin ; « token on » la remet à 1.
suite_capi_relance() {
	local out="${EVIDENCE}/t2.1-capi-integration-relance-documentee.txt"
	{
		echo "# T2.1 — relance documentée de test-capi-integration ($(date -u +%Y-%m-%dT%H:%M:%SZ))"
		echo "# Cause : artefact du harnais (options-restored), voir passe-1/t2.1-capi-integration-artefact-options.txt."
		echo "## avant : famma_meta_orderplaced_enabled = $("${COMPOSE[@]}" run --rm -T cli option get famma_meta_orderplaced_enabled </dev/null 2>/dev/null | tr -d '\r')"
		echo "## commande : wp option delete famma_meta_orderplaced_enabled"
		"${COMPOSE[@]}" run --rm -T cli option delete famma_meta_orderplaced_enabled </dev/null 2>&1 | grep -v '^Warning'
	} >"${out}"
	run_suite "${out}.suite" "${BASE_ENV[@]}" -e FAMMA_INTEGRATION_TEST=1 cli "${MASK[@]}" eval-file /tests/test-capi-integration.php
	cat "${out}.suite" >>"${out}" && rm -f "${out}.suite"
	{
		echo "## commande : bash scripts/qa/qa-pixel-setup.sh token on"
		bash scripts/qa/qa-pixel-setup.sh token on 2>&1 | grep -v '^Warning'
		echo "## apres : famma_meta_orderplaced_enabled = $("${COMPOSE[@]}" run --rm -T cli option get famma_meta_orderplaced_enabled </dev/null 2>/dev/null | tr -d '\r')"
	} >>"${out}"
}

suite_fbc() {
	run_suite "${EVIDENCE}/t2.1-capi-fbc-integration.txt" "${BASE_ENV[@]}" -e FAMMA_INTEGRATION_TEST=1 cli "${MASK[@]}" eval-file /tests/test-capi-fbc-integration.php
	restore_tracking
}

suite_registre() {
	local event run
	for event in Purchase OrderPlaced; do
		# Séquentiel (rôle s) : succès, rejet, délai, table absente...
		run="$(uuid)"
		run_suite "${EVIDENCE}/t2.1-registre-sequentiel-${event}.txt" "${BASE_ENV[@]}" -e FAMMA_CAPI_REGISTRE_TEST=1 -e "FAMMA_CAPI_REGISTRE_RUN=${run}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=sequential -e "FAMMA_CAPI_REGISTRE_EVENT=${event}" cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
		# Concurrence réelle : deux processus a et b derrière une barrière.
		run="$(uuid)"
		local env=("${BASE_ENV[@]}" -e FAMMA_CAPI_REGISTRE_TEST=1 -e "FAMMA_CAPI_REGISTRE_RUN=${run}" -e "FAMMA_CAPI_REGISTRE_EVENT=${event}")
		run_suite "${EVIDENCE}/t2.1-registre-concurrence-${event}-setup.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=setup cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
		run_suite "${EVIDENCE}/t2.1-registre-concurrence-${event}-worker-a.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=a -e FAMMA_CAPI_REGISTRE_MODE=worker cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php &
		run_suite "${EVIDENCE}/t2.1-registre-concurrence-${event}-worker-b.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=b -e FAMMA_CAPI_REGISTRE_MODE=worker cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php &
		wait
		run_suite "${EVIDENCE}/t2.1-registre-concurrence-${event}-verify.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=verify cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
		run_suite "${EVIDENCE}/t2.1-registre-concurrence-${event}-cleanup.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=cleanup cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
		# Interruptions de processus (exit 77 attendu au point d'injection), puis reprise.
		local crash
		for crash in claimed transport acknowledged; do
			run="$(uuid)"
			env=("${BASE_ENV[@]}" -e FAMMA_CAPI_REGISTRE_TEST=1 -e "FAMMA_CAPI_REGISTRE_RUN=${run}" -e "FAMMA_CAPI_REGISTRE_EVENT=${event}")
			run_suite "${EVIDENCE}/t2.1-registre-crash-${event}-${crash}-setup.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=setup cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
			run_suite "${EVIDENCE}/t2.1-registre-crash-${event}-${crash}-crash.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=a -e FAMMA_CAPI_REGISTRE_MODE=crash -e "FAMMA_CAPI_REGISTRE_CRASH=${crash}" cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
			run_suite "${EVIDENCE}/t2.1-registre-crash-${event}-${crash}-resume.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=resume cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
			run_suite "${EVIDENCE}/t2.1-registre-crash-${event}-${crash}-cleanup.txt" "${env[@]}" -e FAMMA_CAPI_REGISTRE_ROLE=s -e FAMMA_CAPI_REGISTRE_MODE=cleanup cli "${MASK[@]}" eval-file /tests/test-capi-registre-integration.php
		done
	done
}

suite_meta_value() {
	run_suite "${EVIDENCE}/t2.1-meta-value-integration.txt" "${BASE_ENV[@]}" -e FAMMA_META_VALUE_TEST=1 cli "${MASK[@]}" eval-file /tests/test-meta-value-integration.php
}

suite_express() {
	local run env mode
	run="$(uuid)"
	run_suite "${EVIDENCE}/t2.1-express-sequentiel.txt" "${BASE_ENV[@]}" -e FAMMA_EXPRESS_INTEGRATION=1 -e "FAMMA_EXPRESS_TEST_RUN=${run}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=sequential cli "${MASK[@]}" eval-file /tests/test-express-integration.php
	restore_tracking
	# Concurrence : deux processus soumettent la même commande express.
	run="$(uuid)"
	env=("${BASE_ENV[@]}" -e FAMMA_EXPRESS_INTEGRATION=1 -e "FAMMA_EXPRESS_TEST_RUN=${run}")
	run_suite "${EVIDENCE}/t2.1-express-concurrence-setup.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=setup cli "${MASK[@]}" eval-file /tests/test-express-integration.php
	run_suite "${EVIDENCE}/t2.1-express-concurrence-worker-a.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=a -e FAMMA_EXPRESS_TEST_MODE=worker cli "${MASK[@]}" eval-file /tests/test-express-integration.php &
	run_suite "${EVIDENCE}/t2.1-express-concurrence-worker-b.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=b -e FAMMA_EXPRESS_TEST_MODE=worker cli "${MASK[@]}" eval-file /tests/test-express-integration.php &
	wait
	run_suite "${EVIDENCE}/t2.1-express-concurrence-verify.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=verify cli "${MASK[@]}" eval-file /tests/test-express-integration.php
	run_suite "${EVIDENCE}/t2.1-express-concurrence-cleanup.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=cleanup cli "${MASK[@]}" eval-file /tests/test-express-integration.php
	restore_tracking
	# Interruption après liaison de la commande (crash), puis après acceptation (crash-accepted).
	for mode in crash crash-accepted; do
		run="$(uuid)"
		env=("${BASE_ENV[@]}" -e FAMMA_EXPRESS_INTEGRATION=1 -e "FAMMA_EXPRESS_TEST_RUN=${run}")
		run_suite "${EVIDENCE}/t2.1-express-${mode}-setup.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=setup-crash cli "${MASK[@]}" eval-file /tests/test-express-integration.php
		run_suite "${EVIDENCE}/t2.1-express-${mode}-crash.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=a -e "FAMMA_EXPRESS_TEST_MODE=${mode}" cli "${MASK[@]}" eval-file /tests/test-express-integration.php
		run_suite "${EVIDENCE}/t2.1-express-${mode}-resume.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e "FAMMA_EXPRESS_TEST_MODE=${mode/crash/resume}" cli "${MASK[@]}" eval-file /tests/test-express-integration.php
		run_suite "${EVIDENCE}/t2.1-express-${mode}-cleanup.txt" "${env[@]}" -e FAMMA_EXPRESS_TEST_ROLE=s -e FAMMA_EXPRESS_TEST_MODE=cleanup cli "${MASK[@]}" eval-file /tests/test-express-integration.php
		restore_tracking
	done
}

suite_woo() {
	run_suite "${EVIDENCE}/t2.1-woocommerce-integration${QA_L2_SUFFIX:-}.txt" "${BASE_ENV[@]}" -e FAMMA_WOO_INTEGRATION=1 cli "${MASK[@]}" eval-file /tests/test-woocommerce-integration.php
	restore_tracking
}

suite_sizes() {
	run_suite "${EVIDENCE}/t2.1-sizes-integration.txt" "${BASE_ENV[@]}" -e FAMMA_SIZES_INTEGRATION=1 cli "${MASK[@]}" eval-file /tests/test-sizes-integration.php
}

suite_report() {
	run_suite "${EVIDENCE}/t2.1-report-statuses.txt" cli eval-file /tests/test-report-statuses.php
}

SUITES=("$@")
if [ "${#SUITES[@]}" -eq 0 ]; then
	SUITES=(capi fbc registre meta-value express woo sizes report)
fi
for s in "${SUITES[@]}"; do
	case "${s}" in
	capi) suite_capi ;;
	capi-relance) suite_capi_relance ;;
	fbc) suite_fbc ;;
	registre) suite_registre ;;
	meta-value) suite_meta_value ;;
	express) suite_express ;;
	woo) suite_woo ;;
	sizes) suite_sizes ;;
	report) suite_report ;;
	*) echo "suite inconnue : ${s}" >&2 ;;
	esac
done
echo "Résumé : ${SUMMARY}"
