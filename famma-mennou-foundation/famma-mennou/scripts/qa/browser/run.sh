#!/usr/bin/env bash
# ============================================================
# FAMMA MINNOU — lanceur des scénarios navigateur du lot 3 (plan pixel 19/10)
#
# Lance un script Node/Puppeteer de ce dossier dans l'image locale
# zenika/alpine-chrome:with-puppeteer (Chromium 124, Puppeteer 22).
#
#   bash scripts/qa/browser/run.sh <script.js> [--prod] [--admin]
#
# - Par défaut : conteneur relié au réseau du clone (famma-qa-pixel_default),
#   Chrome résout localhost:8980 vers wordpress:80, et tout ce qui sort du
#   clone est interrompu par le script (voir qa-lib.js).
# - --prod : réseau Docker par défaut (accès Internet), pour T4.5 seulement.
# - --admin : monte en lecture seule le fichier d'identifiants de l'admin QA
#   (lot 0) sur /secrets/qa-admin.txt. Son contenu n'est jamais imprimé.
#
# Preuves écrites dans QA_EVIDENCE_DIR (défaut : lot-3 des preuves du 19/10).
# QA_PREFIX (facultatif) : préfixe des données fictives saisies par les
# scénarios (défaut QA-L3 ; QA-L5N pour le testeur navigateur du lot 5).
# QA_IP (facultatif) : adresse fixe du navigateur sur le réseau du clone
# (ex. 172.23.0.201). Chaque visiteur simulé peut ainsi avoir sa propre
# connexion : le garde-fou de famma-core compte 5 commandes par IP et par
# heure (Anti_Abuse), et le navigateur de test garderait sinon la même IP.
# Aucun conteneur n'est laissé derrière (--rm). Aucun autre projet touché.
# ============================================================
set -euo pipefail
export MSYS_NO_PATHCONV=1

here="$(cd "$(dirname "$0")" && pwd -W 2>/dev/null || pwd)"
script="${1:?usage: run.sh <script.js> [--prod] [--admin]}"
shift
evidence="${QA_EVIDENCE_DIR:-E:/Ecom/audit-preuves/2026-10-19-pixel/lot-3}"
admin_file="${QA_ADMIN_FILE:-E:/Ecom/audit-preuves/2026-10-19-pixel/lot-0/qa-admin.txt}"

network="famma-qa-pixel_default"
extra=()
for flag in "$@"; do
	case "$flag" in
		--prod) network="bridge" ;;
		--admin) extra+=( -v "${admin_file}:/secrets/qa-admin.txt:ro" ) ;;
		*) extra+=( -e "QA_ARG=${flag}" ) ;;
	esac
done
if [ -n "${QA_PREFIX:-}" ]; then
	extra+=( -e "QA_PREFIX=${QA_PREFIX}" )
fi
if [ -n "${QA_IP:-}" ] && [ "$network" != "bridge" ]; then
	extra+=( --ip "${QA_IP}" )
fi

docker run --rm --init --shm-size=512m \
	--network "$network" \
	-v "${here}:/usr/src/app/qa:ro" \
	-v "${evidence}:/out" \
	-e QA_OUT=/out \
	"${extra[@]}" \
	--entrypoint node \
	zenika/alpine-chrome:with-puppeteer "qa/${script}"
