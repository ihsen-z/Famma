#!/usr/bin/env bash
# ============================================================
# FAMMA — plan de tests pixel du 19/10/2026, lot 4 : production PASSIVE
# (T4.1, T4.2, T4.3, T4.7, T4.9)
#
# Lecture seule, sans exception :
#   - uniquement des GET et des HEAD, sans cookie, sans formulaire ;
#   - aucun endpoint qui pose une session ou crée une commande
#     (jamais famma_express_nonce, famma_express_prepare, famma_express_cart) ;
#   - pas de suivi des redirections (on lit l'en-tête Location) ;
#   - plafond dur de 20 requêtes vers la prod : le script s'arrête avant
#     la 21e plutôt que de la dépasser.
#
# La configuration publique du pixel (connect.facebook.net/signals/config)
# est téléchargée comme un fichier, jamais exécutée : elle sert de
# référence « avant » des activations Meta du 12/11.
#
# Les valeurs des cookies et du jeton anti-CSRF renvoyés par la prod sont
# masquées avant d'être écrites dans les preuves.
#
# Usage:  bash scripts/qa/lot4-prod-passive.sh <dossier-de-preuves>
# ============================================================
set -euo pipefail

OUT="${1:?Usage : lot4-prod-passive.sh <dossier-de-preuves>}"
SITE='https://fammaminnou.tn'
PIXEL='1061419273284333'
CONFIG_URL="https://connect.facebook.net/signals/config/${PIXEL}?v=2.9.419&r=stable"
UA='Mozilla/5.0 (Linux; Android 14; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36'
MAX_PROD=20
COUNT=0

mkdir -p "$OUT/raw"
LOG="$OUT/requetes.tsv"
printf 'n\tid\theure_utc\tmethode\turl\tcode\tlocation\tx-litespeed-cache\tcache-control\tetag\tset-cookie(noms)\tduree_s\n' > "$LOG"

# Masque la valeur des cookies : seuls leurs noms restent lisibles.
redact_headers() {
	sed -E 's/^([Ss][Ee][Tt]-[Cc][Oo][Oo][Kk][Ii][Ee]: *[^=]+=)[^;]*/\1<masque>/'
}

# Valeur d'un en-tête (insensible à la casse), vide s'il manque.
header() {
	grep -i "^$1:" "$2" | head -1 | cut -d: -f2- | tr -d '\r' | sed -E 's/^ +//' || true
}

# req <id> <GET|HEAD> <chemin> : une requête vers la prod, sans cookie.
req() {
	local id="$1" method="$2" path="$3"
	COUNT=$((COUNT + 1))
	if [ "$COUNT" -gt "$MAX_PROD" ]; then
		echo "ARRET : plafond de ${MAX_PROD} requetes vers la prod atteint." >&2
		exit 2
	fi

	local hdr="$OUT/raw/${id}.headers.tmp" body="$OUT/raw/${id}.body"
	local opts=(-s -S --compressed -A "$UA" -H 'Accept-Language: fr-FR,fr;q=0.9' -D "$hdr" -w '%{http_code} %{time_total}')
	if [ 'HEAD' = "$method" ]; then
		opts+=(-I -o /dev/null)
	else
		opts+=(-H 'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8' -o "$body")
	fi

	local when result
	when="$(date -u +%FT%TZ)"
	result="$(curl "${opts[@]}" "${SITE}${path}")"
	redact_headers < "$hdr" > "$OUT/raw/${id}.headers.txt"
	rm -f "$hdr"

	local h="$OUT/raw/${id}.headers.txt" cookies
	cookies="$(grep -i '^set-cookie:' "$h" | sed -E 's/^[^:]+: *([^=]+)=.*/\1/' | tr -d '\r' | paste -sd, - || true)"
	printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
		"$COUNT" "$id" "$when" "$method" "${SITE}${path}" "${result% *}" \
		"$(header location "$h")" "$(header x-litespeed-cache "$h")" \
		"$(header cache-control "$h")" "$(header etag "$h")" "$cookies" "${result#* }" >> "$LOG"
}

# --- T4.1 : deux chargements sans cookie de la même fiche ----------------
req T4.1-a GET '/produit/ceinture-dorsale-pro/'
sleep 2
req T4.1-b GET '/produit/ceinture-dorsale-pro/'

# --- T4.2 : anciens chemins + fbclid/utm, Location conservée ? ------------
# Ancien préfixe produit (avant francise-slugs.php : /product/ -> /produit/).
req T4.2-a HEAD '/product/ceinture-dorsale-pro/?fbclid=TESTX&utm_source=test'
# Même chemin, autres valeurs : un 301 mis en cache rendrait TESTX ici.
req T4.2-b HEAD '/product/ceinture-dorsale-pro/?fbclid=TESTY&utm_source=test2'
# Navigateur réel : GET, toujours sans suivre la redirection.
req T4.2-c GET '/product/ceinture-dorsale-pro/?fbclid=TESTZ&utm_source=test3'
# Slug incomplet sous le préfixe actuel (devinette 404 de WordPress).
req T4.2-d HEAD '/produit/ceinture-dorsale/?fbclid=TESTX&utm_source=test'
# URL héritée gérée par le mu-plugin famma-legacy-urls (/shop/ -> /boutique/).
req T4.2-e HEAD '/shop/?fbclid=TESTX&utm_source=test'
req T4.2-f HEAD '/shop/?fbclid=TESTY&utm_source=test2'

# --- T4.3 : une réponse dynamique est-elle servie depuis le cache ? -------
# La route n'accepte que POST : un GET ne déclenche pas la collecte.
req T4.3-a GET '/wp-json/famma/v1/collect'
sleep 2
req T4.3-b GET '/wp-json/famma/v1/collect'
# Jeton du formulaire newsletter : wp_create_nonce() seul, rien n'est écrit.
req T4.3-c GET '/?wc-ajax=famma_newsletter_nonce'
sleep 2
req T4.3-d GET '/?wc-ajax=famma_newsletter_nonce'

# Le jeton anti-CSRF ne va pas tel quel dans les preuves : empreinte seule.
for id in T4.3-c T4.3-d; do
	f="$OUT/raw/${id}.body"
	if [ -f "$f" ]; then
		sha="$(grep -o '"nonce":"[^"]*"' "$f" | sha256sum | cut -c1-16 || true)"
		sed -E "s/\"nonce\":\"[^\"]*\"/\"nonce\":\"<masque sha256:${sha}>\"/" "$f" > "$f.redacted"
		mv "$f.redacted" "$f"
	fi
done

# --- T4.7 / T4.9 : configuration publique du pixel (fichier, non exécuté) --
CONFIG="$OUT/signals-config-${PIXEL}.js"
curl -s -S --compressed -A "$UA" -D "$OUT/raw/signals-config.headers.txt" \
	-o "$CONFIG" -w '%{http_code}\n' "$CONFIG_URL" > "$OUT/raw/signals-config.status.txt"
{
	echo "url: $CONFIG_URL"
	echo "telecharge_utc: $(date -u +%FT%TZ)"
	echo "http: $(cat "$OUT/raw/signals-config.status.txt")"
	echo "octets: $(wc -c < "$CONFIG")"
	echo "sha256: $(sha256sum "$CONFIG" | cut -d' ' -f1)"
} > "$OUT/signals-config-${PIXEL}.sha256.txt"

echo "Requetes vers la prod : ${COUNT}/${MAX_PROD}. Journal : ${LOG}"
