#!/usr/bin/env bash
# ============================================================
# FAMMA — sauvegarde locale : base de données + téléversements (§36)
#
# À lancer AVANT toute opération sensible : BACKUP → CHANGE → TEST
# → VALIDATE.
#
# Le script échoue bruyamment plutôt que d'écrire une sauvegarde vide.
# Une sauvegarde silencieusement corrompue est pire que pas de
# sauvegarde du tout : on croit être couvert alors qu'on ne l'est pas.
#
# Rétention : les sauvegardes de plus de FAMMA_BACKUP_KEEP_DAYS jours
# (14 par défaut) sont supprimées. Mettre 0 pour tout conserver.
# ============================================================
set -euo pipefail

# Voir setup.sh : empêche Git Bash de réécrire les chemins POSIX passés à docker.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
	echo "ERREUR: .env introuvable — impossible de connaître les accès à la base." >&2
	exit 1
fi
set -a
. ./.env
set +a

KEEP_DAYS="${FAMMA_BACKUP_KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="backups/${STAMP}"

fail() {
	echo "ERREUR: $1" >&2
	echo "        Sauvegarde INCOMPLÈTE dans ${DEST} — ne pas s'y fier." >&2
	exit 1
}

mkdir -p "${DEST}"

# ------------------------------------------------------------
# Base de données
# ------------------------------------------------------------
echo "==> Export de la base de données"
if ! docker compose exec -T db sh -c \
	"exec mariadb-dump -u\"${DB_USER}\" -p\"${DB_PASSWORD}\" --single-transaction --quick \"${DB_NAME}\"" \
	> "${DEST}/db.sql"; then
	fail "l'export de la base a échoué."
fi

DB_BYTES="$(wc -c < "${DEST}/db.sql" | tr -d ' ')"
if [ "${DB_BYTES}" -lt 1024 ]; then
	fail "l'export ne fait que ${DB_BYTES} octets — la base n'a pas été lue."
fi

if ! grep -q "CREATE TABLE" "${DEST}/db.sql"; then
	fail "l'export ne contient aucune table."
fi

# Les commandes vivent en HPOS, pas dans posts : on vérifie explicitement.
if ! grep -q "wc_orders" "${DEST}/db.sql"; then
	echo "    ATTENTION: aucune table wc_orders dans l'export." >&2
fi

# Les traductions vivent dans les tables propres à TranslatePress. Les
# perdre revient à perdre tout le contenu arabe (voir DEPLOYMENT.md).
if grep -q "trp_dictionary" "${DEST}/db.sql"; then
	echo "    traductions TranslatePress incluses"
else
	echo "    ATTENTION: aucune table TranslatePress dans l'export." >&2
fi

echo "    base : $(( DB_BYTES / 1024 )) Ko"

# ------------------------------------------------------------
# Téléversements
# ------------------------------------------------------------
echo "==> Archivage des téléversements"
if docker compose exec -T -u 33:33 wpcli sh -c \
	"cd /var/www/html/wp-content && tar czf - uploads 2>/dev/null" \
	> "${DEST}/uploads.tar.gz"; then
	UP_BYTES="$(wc -c < "${DEST}/uploads.tar.gz" | tr -d ' ')"
	echo "    téléversements : $(( UP_BYTES / 1024 )) Ko"
else
	# Un dossier uploads absent est normal sur une boutique neuve.
	rm -f "${DEST}/uploads.tar.gz"
	echo "    aucun téléversement à archiver"
fi

# ------------------------------------------------------------
# Rétention
# ------------------------------------------------------------
if [ "${KEEP_DAYS}" -gt 0 ]; then
	REMOVED=0
	while IFS= read -r old; do
		[ -z "${old}" ] && continue
		rm -rf "${old}"
		REMOVED=$(( REMOVED + 1 ))
	done < <(find backups -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" 2>/dev/null || true)

	if [ "${REMOVED}" -gt 0 ]; then
		echo "==> Rétention : ${REMOVED} sauvegarde(s) de plus de ${KEEP_DAYS} jours supprimée(s)"
	fi
fi

echo
echo "Sauvegarde complète : ${DEST}"
echo "  Ces fichiers contiennent des données clients — ne jamais les committer."
