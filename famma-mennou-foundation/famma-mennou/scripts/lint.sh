#!/usr/bin/env bash
# ============================================================
# FAMMA MENNOU — porte qualité PHP (ClaudeMemo règle 11)
#
#   1. php -l  sur tout fichier PHP que nous écrivons
#   2. phpcs   avec le ruleset WordPress (phpcs.xml.dist)
#
# Aucune installation sur l'hôte : tout tourne dans un conteneur
# jetable, ce qui garde la machine propre et le lint reproductible
# (l'hôte n'a ni PHP, ni Composer, ni phpcs — voir ENVIRONMENT-AUDIT.md).
#
# Usage:  bash scripts/lint.sh          # analyse
#         bash scripts/lint.sh --fix    # corrige ce qui est automatisable
# ============================================================
set -euo pipefail

cd "$(dirname "$0")/.."

# Docker Desktop attend un chemin Windows ; Git Bash fournit un chemin
# POSIX. cygpath fait la conversion quand il est disponible.
HOST_PWD="$(pwd)"
if command -v cygpath >/dev/null 2>&1; then
	HOST_PWD="$(cygpath -w "$(pwd)")"
fi

PHP_IMAGE="composer:2"
RUN=(docker run --rm -v "${HOST_PWD}:/app" -w /app "${PHP_IMAGE}")

MODE="check"
if [ "${1:-}" = "--fix" ]; then
	MODE="fix"
fi

echo "==> Installation de l'outillage (mise en cache dans ./vendor)"
MSYS_NO_PATHCONV=1 "${RUN[@]}" composer install --no-interaction --no-progress --quiet

echo
echo "==> php -l (syntaxe)"
MSYS_NO_PATHCONV=1 "${RUN[@]}" sh -c '
	fail=0
	for f in $(find wp-content/plugins/famma-core wp-content/themes/famma-child -name "*.php" 2>/dev/null); do
		if ! php -l "$f" > /dev/null 2>&1; then
			echo "  ERREUR de syntaxe: $f"
			php -l "$f" || true
			fail=1
		else
			echo "  OK  $f"
		fi
	done
	exit $fail
'

echo
if [ "${MODE}" = "fix" ]; then
	echo "==> phpcbf (correction automatique)"
	# phpcbf renvoie 1 quand il a corrigé quelque chose : ce n'est pas une erreur.
	MSYS_NO_PATHCONV=1 "${RUN[@]}" vendor/bin/phpcbf || true
	echo
	echo "==> phpcs (nouvelle analyse après correction)"
fi

echo "==> phpcs (standards WordPress)"
MSYS_NO_PATHCONV=1 "${RUN[@]}" vendor/bin/phpcs

echo
echo "Porte qualité franchie : aucune erreur PHP, aucune violation WPCS."
