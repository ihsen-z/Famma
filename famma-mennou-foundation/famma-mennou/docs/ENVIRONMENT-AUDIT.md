# Audit d'environnement (§57 PHASE 0, §64)

Audit exécuté le **2026-08-13** sur la machine du propriétaire. Aucune valeur n'est estimée : chaque ligne provient d'une commande réelle.

## Système

| Élément | Valeur | Verdict |
|---|---|---|
| OS | Windows 11 Pro 10.0.26200 | PASS |
| Shell | PowerShell 5.1 + Git Bash | PASS |
| Racine projet | `E:\Ecom` | PASS |

## Conteneurisation (§37)

| Élément | Valeur | Verdict |
|---|---|---|
| Docker Engine (CLI) | 27.1.1 | PASS |
| Docker Compose | v2.29.1-desktop.1 | PASS |
| Docker Desktop (binaire) | présent (`C:\Program Files\Docker\Docker\Docker Desktop.exe`) | PASS |
| Docker Engine (daemon) | **non démarré** — `dockerDesktopLinuxEngine` injoignable | **FAIL** |
| WSL | présent, distribution par défaut `docker-desktop-data`, version 2 | PASS |

> **Action propriétaire** — démarrer Docker Desktop manuellement. Le lancement automatique est refusé par la politique de sandbox de l'agent (§52, exception 7).

## Ports

| Port | Usage prévu | État |
|---|---|---|
| 8080 | WordPress | libre |
| 8081 | phpMyAdmin | libre |
| 8025 | Mailpit | libre |
| 3306 | MariaDB (non exposé) | libre |

## Outils de développement

| Outil | Version | Verdict | Conséquence |
|---|---|---|---|
| Git | 2.47.1.windows.1 | PASS | — |
| Node | v24.12.0 | PASS | non requis pour le MVP |
| PHP (hôte) | **absent** | WARNING | `php -l` doit passer par un conteneur |
| Composer (hôte) | **absent** | WARNING | idem |
| PHP_CodeSniffer / WPCS | **absent** | WARNING | la porte qualité (règle 11) exige un service outillé |
| GitHub CLI (`gh`) | **absent** | WARNING | dépôt distant privé impossible à créer par l'agent (§40) |

> **Décision** — la chaîne de validation PHP est conteneurisée en Phase 2 plutôt qu'installée sur l'hôte : cela garde la machine propre et rend le lint reproductible (§28 « keep the project reproducible »).

## Outillage d'analyse

| Élément | État |
|---|---|
| Graphify CLI | présent (`~/AppData/Roaming/Python/Python314/Scripts/graphify`) |
| Graphe du projet | **exploitable depuis le 2026-08-15** — 253 nœuds / 340 arêtes, santé OK. Le PHP *est* indexé par l'AST : le corpus était amputé, pas l'extracteur (voir ci-dessous) |
| Context7 MCP | **connecté le 2026-08-15** — serveur HTTP de portée projet dans `.mcp.json`, sans clé API. Reste à approuver au premier lancement interactif |

### Pourquoi le graphe ne voyait pas le code (résolu)

`.gitignore` exclut `/wp-content/plugins/*` et `/wp-content/themes/*` puis ré-inclut
`famma-core` et `famma-child` par négation. Git descend dans un dossier ré-inclus ;
graphify applique à la lettre la règle d'exclusion parente de la spec gitignore
(`detect.py`) et laissait donc hors corpus **les deux fichiers les plus importants du
projet**. `detect()` remontait 28 fichiers, dont 0 de `famma-core`/`famma-child`.

Correctif : `famma-mennou/.graphifyignore`, lu après `.gitignore` et prioritaire, qui
ré-inclut explicitement les deux arborescences. Le corpus passe à 36 fichiers ; l'AST
extrait 105 nœuds et 181 arêtes des 3 fichiers de code concernés. Les visuels de marque
restent exclus : une extraction vision par fichier, pour un contenu déjà décrit dans
`DESIGN-SYSTEM.md`.

**Limite subsistante** : `.css` n'est pas un type de code reconnu par graphify —
`tokens.css` et `style.css` n'entrent pas dans le graphe. Le design system n'y est
présent qu'à travers sa documentation.

## Dépôt

| Élément | État avant Phase 0 |
|---|---|
| Dépôt Git | **aucun** (`.git` absent) — FAIL |
| `.env` | absent (seul `.env.example` existe) — FAIL |
| `MASTER-PROMPT.md` | absent — **corrigé** : fourni par le propriétaire et versionné à la racine |
| Fichiers projet | 11 fichiers, 687 lignes |

## Versions réellement installées (après Phase 1)

| Composant | Version | Note |
|---|---|---|
| WordPress | 7.0.4 | l'image était épinglée à 6.8 ; WooCommerce exige **6.9 minimum** et refusait de s'installer. Image repassée à `wordpress:7.0-php8.3-apache` |
| PHP | 8.3.33 | conforme au §54 |
| MariaDB | 11.4 | conforme au §54 |
| WooCommerce | 11.0.1 | — |
| Kadence | 1.5.2 | thème parent (ADR-004) |
| FAMMA Child | 0.1.0 | thème actif |
| FAMMA Core | 0.1.0 | actif |

> **Piège à connaître** : l'image officielle WordPress ne copie ses fichiers
> que si `/var/www/html` est vide. Changer le tag de l'image ne met **pas** à
> jour un site déjà installé — il faut `wp core update`. Volume et image
> peuvent donc diverger silencieusement.

## Particularités Windows rencontrées

| Problème | Effet | Correctif |
|---|---|---|
| Git Bash (MSYS) réécrit les chemins POSIX passés à `docker.exe` | `--path=/var/www/html` devenait `C:\Program Files (x86)\Git\var\www\html` | `MSYS_NO_PATHCONV=1` + `MSYS2_ARG_CONV_EXCL='*'` en tête des scripts |
| Fins de ligne CRLF | scripts `.sh` inexécutables en conteneur (`\r: command not found`) | `.gitattributes` avec `eol=lf` |
| Valeur non quotée dans `.env` | `WP_TITLE=FAMMA MENNOU` : `. ./.env` exécutait `MENNOU` | guillemets, dans `.env` et `.env.example` |

## Conclusion

Trois blocages avant la Phase 1, deux résolus par l'agent :

1. ~~Absence de spécification de référence~~ → `MASTER-PROMPT.md` versionné.
2. ~~Absence de versionnage~~ → dépôt initialisé en Phase 0.
3. **Docker Engine arrêté** → action propriétaire, seul point restant.
