#!/usr/bin/env bash
# ============================================================
# FAMMA MENNOU — transfert du code vers la production
#
#   1. Porte qualité : php -l + phpcs sur ce que nous écrivons
#   2. Contrôle de l'arbre Git : rien de non commité ne partira
#   3. Transfert de TROIS répertoires, et rien d'autre :
#        wp-content/plugins/famma-core
#        wp-content/themes/famma-child
#        wp-content/mu-plugins
#
# CE SCRIPT NE TOUCHE JAMAIS À LA BASE DE PRODUCTION. Écraser une base est
# irréversible : la migration reste un geste séparé et délibéré
# (docs/DEPLOY-OXAHOST.md, Phase 6).
#
# Usage:  bash scripts/deploy/deploy.sh --dry-run   # simule, n'écrit rien
#         bash scripts/deploy/deploy.sh             # transfère, avec confirmation
#         bash scripts/deploy/deploy.sh --yes       # transfère sans confirmation
#         bash scripts/deploy/deploy.sh --skip-preflight
# ============================================================
set -euo pipefail

cd "$(dirname "$0")/../.."

DRY_RUN=0
ASSUME_YES=0
SKIP_PREFLIGHT=0

for arg in "$@"; do
	case "$arg" in
		--dry-run) DRY_RUN=1 ;;
		--yes|-y) ASSUME_YES=1 ;;
		--skip-preflight) SKIP_PREFLIGHT=1 ;;
		-h|--help) sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "Argument inconnu : $arg" >&2; exit 2 ;;
	esac
done

# --- Configuration ------------------------------------------------------

ENV_FILE="scripts/deploy/.env.deploy"

if [ ! -f "$ENV_FILE" ]; then
	cat >&2 <<-MSG
	Configuration absente : $ENV_FILE

	  cp scripts/deploy/.env.deploy.example $ENV_FILE

	Puis remplir l'hôte, l'identifiant et le chemin distant.
	MSG
	exit 1
fi

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

: "${DEPLOY_MODE:=ssh}"
: "${DEPLOY_PORT:=22}"
: "${DEPLOY_SSH_KEY:=}"
: "${DEPLOY_FTP_PASSWORD:=}"

for required in DEPLOY_HOST DEPLOY_USER DEPLOY_WP_PATH; do
	if [ -z "${!required:-}" ]; then
		echo "Variable manquante dans $ENV_FILE : $required" >&2
		exit 1
	fi
done

case "$DEPLOY_WP_PATH" in
	*CHANGEME*)
		echo "DEPLOY_WP_PATH contient encore CHANGEME. Vérifier le chemin réel dans le" >&2
		echo "gestionnaire de fichiers du panneau avant de transférer." >&2
		exit 1
		;;
esac

# Les trois répertoires transférés. Toute autre chose sur le serveur —
# WooCommerce, Kadence, TranslatePress, les médias, wp-config.php — est hors
# de portée de ce script et ne peut donc pas être écrasée par erreur.
#
# Les mu-plugins sont listés un par un, jamais le dossier : avec `--delete`,
# transférer `wp-content/mu-plugins` effacerait tout mu-plugin posé par
# l'hébergeur, et enverrait l'outil de développement `famma-local-mail.php`
# en production (audit du 23/09, CODE-05 / SEC-12).
PAYLOAD=(
	"wp-content/plugins/famma-core"
	"wp-content/themes/famma-child"
	"wp-content/mu-plugins/famma-env.php"
	"wp-content/mu-plugins/famma-legacy-urls.php"
	"wp-content/mu-plugins/famma-cron.php"
)

# --- 1. Porte qualité ---------------------------------------------------

find_php() {
	if command -v php >/dev/null 2>&1; then
		command -v php
		return 0
	fi
	# Local by Flywheel embarque un PHP utilisable, l'hôte n'en a pas toujours.
	local candidate
	candidate="$(find "${APPDATA:-$HOME}/Local/lightning-services" -maxdepth 4 \
		-name 'php.exe' -path '*win64*' 2>/dev/null | sort | tail -1)"
	[ -n "$candidate" ] && printf '%s\n' "$candidate"
}

if [ "$SKIP_PREFLIGHT" -eq 1 ]; then
	echo "==> Porte qualité IGNORÉE (--skip-preflight)"
else
	PHP_BIN="$(find_php || true)"

	if [ -z "$PHP_BIN" ]; then
		echo "Aucun binaire PHP trouvé : impossible de vérifier le code avant de l'envoyer." >&2
		echo "Lancer 'bash scripts/lint.sh' (Docker), ou relancer avec --skip-preflight" >&2
		echo "en assumant de déployer du code non vérifié." >&2
		exit 1
	fi

	echo "==> php -l (syntaxe) — $PHP_BIN"
	fail=0
	while IFS= read -r file; do
		if ! "$PHP_BIN" -l "$file" >/dev/null 2>&1; then
			echo "  ERREUR de syntaxe : $file"
			"$PHP_BIN" -l "$file" || true
			fail=1
		fi
	done < <(find "${PAYLOAD[@]}" -name '*.php' 2>/dev/null)
	[ "$fail" -eq 0 ] || { echo "Transfert annulé : le code ne compile pas." >&2; exit 1; }
	echo "  aucune erreur de syntaxe"

	if [ -x vendor/bin/phpcs ]; then
		echo "==> phpcs (standards WordPress)"
		"$PHP_BIN" vendor/bin/phpcs --standard=phpcs.xml.dist
	else
		echo "==> phpcs absent (composer install non joué) — étape sautée"
	fi
fi

# --- 2. Contrôle de l'arbre Git ----------------------------------------

if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
	if [ -n "$(git status --porcelain -- "${PAYLOAD[@]}")" ]; then
		echo
		echo "Attention : des modifications non commitées vont être transférées." >&2
		git status --short -- "${PAYLOAD[@]}" >&2
		echo >&2
		echo "Un déploiement qui ne correspond à aucun commit ne peut pas être rejoué" >&2
		echo "ni annulé proprement. Committer d'abord, ou assumer avec --yes." >&2
		[ "$ASSUME_YES" -eq 1 ] || exit 1
	fi
fi

# --- 3. Transfert -------------------------------------------------------

echo
echo "Mode      : $DEPLOY_MODE"
echo "Cible     : ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_WP_PATH}"
echo "Contenu   : ${PAYLOAD[*]}"
[ "$DRY_RUN" -eq 1 ] && echo "Simulation : rien ne sera écrit"
echo

if [ "$DRY_RUN" -eq 0 ] && [ "$ASSUME_YES" -eq 0 ]; then
	printf 'Transférer vers la PRODUCTION ? [oui/non] '
	read -r answer
	case "$answer" in
		oui|OUI|o|O|yes|y) ;;
		*) echo "Annulé."; exit 0 ;;
	esac
fi

# Fichiers de travail qui n'ont rien à faire en production. `vendor/` en fait
# partie : c'est l'outillage de qualité, pas du code exécuté par le site.
EXCLUDES=(
	--exclude '.git*'
	--exclude 'node_modules'
	--exclude 'vendor'
	--exclude '*.log'
	--exclude '.DS_Store'
	--exclude 'Thumbs.db'
	--exclude 'famma-local-mail.php'
)

case "$DEPLOY_MODE" in
	ssh)
		command -v rsync >/dev/null 2>&1 || {
			echo "rsync est introuvable. L'installer, ou passer en DEPLOY_MODE=ftp." >&2
			exit 1
		}

		SSH_CMD="ssh -p ${DEPLOY_PORT}"
		[ -n "$DEPLOY_SSH_KEY" ] && SSH_CMD="$SSH_CMD -i $DEPLOY_SSH_KEY"

		RSYNC=(rsync -az --human-readable --itemize-changes
			-e "$SSH_CMD"
			"${EXCLUDES[@]}"
			# --delete reste borné à l'intérieur de nos trois dossiers, parce
			# que ce sont eux seuls que rsync reçoit comme source.
			--delete
			--relative)
		[ "$DRY_RUN" -eq 1 ] && RSYNC+=(--dry-run)

		"${RSYNC[@]}" "${PAYLOAD[@]}" \
			"${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_WP_PATH}/"
		;;

	tar)
		# Repli sans rsync : `tar` sur un tunnel SSH. Sur Windows, ni rsync ni
		# lftp ne sont fournis, alors que ssh et tar le sont — ce mode est donc
		# le seul qui fonctionne d'emblée depuis la machine de développement.
		#
		# Contrepartie assumée : le transfert est complet et non incrémentiel,
		# et il ne SUPPRIME rien à distance. Un fichier retiré du dépôt survivra
		# donc en production ; il faut l'effacer à la main. Pour un déploiement
		# qui supprime, utiliser le mode ssh avec rsync installé.
		command -v tar >/dev/null 2>&1 || {
			echo "tar est introuvable." >&2
			exit 1
		}

		SSH_CMD=(ssh -p "${DEPLOY_PORT}")
		[ -n "$DEPLOY_SSH_KEY" ] && SSH_CMD+=(-i "$DEPLOY_SSH_KEY")

		TAR_FLAGS=(
			--exclude-vcs
			--exclude 'node_modules'
			--exclude 'vendor'
			--exclude '*.log'
			--exclude '.DS_Store'
			--exclude 'Thumbs.db'
		)

		if [ "$DRY_RUN" -eq 1 ]; then
			echo "Fichiers qui seraient transférés :"
			tar "${TAR_FLAGS[@]}" -cvf /dev/null "${PAYLOAD[@]}"
			echo
			echo "Aucune suppression distante dans ce mode."
		else
			tar "${TAR_FLAGS[@]}" -czf - "${PAYLOAD[@]}" \
				| "${SSH_CMD[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" \
					"mkdir -p '${DEPLOY_WP_PATH}' && tar xzf - -C '${DEPLOY_WP_PATH}'"
		fi
		;;

	ftp)
		command -v lftp >/dev/null 2>&1 || {
			echo "lftp est introuvable : il est nécessaire pour le mode FTPS." >&2
			exit 1
		}
		[ -n "$DEPLOY_FTP_PASSWORD" ] || {
			echo "DEPLOY_FTP_PASSWORD est vide alors que DEPLOY_MODE=ftp." >&2
			exit 1
		}

		MIRROR_FLAGS="--delete --verbose --exclude-glob .git* --exclude-glob node_modules/ --exclude-glob vendor/ --exclude-glob *.log"
		[ "$DRY_RUN" -eq 1 ] && MIRROR_FLAGS="$MIRROR_FLAGS --dry-run"

		# FTPS explicite exigé : en FTP clair, le mot de passe traverserait le
		# réseau en clair.
		{
			echo "set ftp:ssl-force true"
			echo "set ftp:ssl-protect-data true"
			echo "set ssl:verify-certificate true"
			echo "open -u ${DEPLOY_USER},${DEPLOY_FTP_PASSWORD} ${DEPLOY_HOST}"
			for item in "${PAYLOAD[@]}"; do
				if [ -d "$item" ]; then
					echo "mirror ${MIRROR_FLAGS} ./${item} ${DEPLOY_WP_PATH}/${item}"
				elif [ "$DRY_RUN" -eq 1 ]; then
					echo "!echo 'serait envoyé : ${item}'"
				else
					# Fichier isolé (mu-plugin) : `mirror` n'accepte que des dossiers.
					echo "put -O ${DEPLOY_WP_PATH}/$(dirname "$item") ./${item}"
				fi
			done
			echo "bye"
		} | lftp -f /dev/stdin
		;;

	*)
		echo "DEPLOY_MODE inconnu : $DEPLOY_MODE (attendu : ssh, tar ou ftp)" >&2
		exit 1
		;;
esac

echo
if [ "$DRY_RUN" -eq 1 ]; then
	echo "Simulation terminée. Relire la liste ci-dessus : elle ne doit contenir"
	echo "que nos trois répertoires. Si autre chose apparaît, corriger DEPLOY_WP_PATH."
else
	echo "Transfert terminé. À vérifier maintenant, dans cet ordre :"
	echo "  1. L'admin répond et n'affiche aucune erreur PHP."
	echo "  2. Thème FAMMA Child et extension FAMMA Core toujours actifs."
	echo "  3. FAMMA → Pages : les champs et les coordonnées sont intacts."
	echo "  4. Vider le cache LiteSpeed, puis recharger l'accueil."
	echo
	echo "La base n'a pas été touchée : le contenu saisi en production reste le sien."
fi
