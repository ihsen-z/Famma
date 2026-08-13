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
| Graphe du projet | **inexploitable** — `extract --code-only` produit 2 nœuds (`backup.sh`), le PHP n'est pas indexé par l'AST local |
| Context7 MCP | **non connecté** — aucune vérification possible contre la documentation officielle en direct (§53) |

## Dépôt

| Élément | État avant Phase 0 |
|---|---|
| Dépôt Git | **aucun** (`.git` absent) — FAIL |
| `.env` | absent (seul `.env.example` existe) — FAIL |
| `MASTER-PROMPT.md` | absent — **corrigé** : fourni par le propriétaire et versionné à la racine |
| Fichiers projet | 11 fichiers, 687 lignes |

## Conclusion

Trois blocages avant la Phase 1, deux résolus par l'agent :

1. ~~Absence de spécification de référence~~ → `MASTER-PROMPT.md` versionné.
2. ~~Absence de versionnage~~ → dépôt initialisé en Phase 0.
3. **Docker Engine arrêté** → action propriétaire, seul point restant.
