# FAMMA (فمّا) — E-Commerce Platform Tunisia

> **فمّا ديما حاجة جديدة** — *Il y a toujours quelque chose de nouveau.*

[![CI & Quality Gate](https://github.com/ihsen-z/Famma/actions/workflows/ci.yml/badge.svg)](https://github.com/ihsen-z/Famma/actions/workflows/ci.yml)
[![PHP Target](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.x-21759B?logo=wordpress)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS-96588A?logo=woocommerce)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red)](#)

FAMMA est une plateforme e-commerce moderne **mobile-first**, nativement **RTL (Derja tunisienne & Français)** et optimisée pour le **Paiement à la Livraison (COD - Cash on Delivery)** sur le marché tunisien.

---

## 📌 Sommaire

- [Architecture & Stack Technique](#-architecture--stack-technique)
- [Fonctionnalités Métier & Innovations](#-fonctionnalités-métier--innovations)
- [Structure du Dépôt](#-structure-du-dépôt)
- [Guide de Démarrage Rapide (Local)](#-guide-de-démarrage-rapide-local)
- [Assurance Qualité & CI/CD](#-assurance-qualité--cicd)
- [Déploiement & Sauvegardes](#-déploiement--sauvegardes)
- [Index de la Documentation](#-index-de-la-documentation)

---

## 🛠 Architecture & Stack Technique

Le projet repose sur une séparation stricte entre le cœur WordPress, la logique métier isolée et la couche de présentation.

```
                  ┌─────────────────────────────────────────┐
                  │          Navigateur Client              │
                  │   (Mobile-First, RTL, Fast UX)          │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────▼────────────────────┐
                  │        Thème Enfant famma-child         │
                  │    (Style, Layouts, Design System)      │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────▼────────────────────┐
                  │         Plugin famma-core               │
                  │   (Règles métiers, COD, CAPI, Pricing)  │
                  └────────────────────┬────────────────────┘
                                       │
        ┌──────────────────────────────┼──────────────────────────────┐
        │                              │                              │
┌───────▼─────────┐          ┌─────────▼─────────┐          ┌─────────▼─────────┐
│   WordPress     │          │    WooCommerce    │          │  Meta CAPI / GA4  │
│   (Core WP)     │          │  (Tables HPOS)    │          │  (Server Tracking)│
└─────────────────┘          └───────────────────┘          └───────────────────┘
```

* **Core Engine** : WordPress 6.x + WooCommerce avec support complet **HPOS (High-Performance Order Storage)**.
* **Plugin Métier Isolée (`famma-core`)** : Fichier unique consolidé (`wp-content/plugins/famma-core/famma-core.php`), zéro modification au cœur WordPress/WooCommerce.
* **Thème Sur-Mesure (`famma-child`)** : Thème enfant basé sur Kadence avec jetons de design CSS variables (`tokens.css`), cartes produits optimisées, drawer navigation et checkout réassurant.
* **Environnement Conteneurisé** : Stack Docker orchestrée (`docker-compose.yml`) intégrant PHP 8.2 (FPM), MySQL 8.0, Mailpit (capturation SMTP locale) et phpMyAdmin.

---

## ⚡ Fonctionnalités Métier & Innovations

### 1. Paiement à la Livraison (COD Tunisien)
- **Workflow de Statuts Personnalisés** :
  `En attente de confirmation` ➔ `Confirmé` ➔ `En préparation` ➔ `Expédié` ➔ `Livré` ➔ `Terminé` (+ statuts d'échec : *Refusé*, *Retourné*, *Échec de livraison*).
- **Règle d'Encaissement Strict** : Une commande n'est marquée **payée** qu'au statut **Livré** (`Delivered`).

### 2. Tarification Automatique & Marges Intégrées (Cost × 3)
- Panneau financier privé dans l'admin produit (*FAMMA Financials*) : saisie du prix de revient, frais d'expédition interne, manutention et coût d'acquisition.
- Calcule automatiquement le prix de vente conseillé (**Prix de revient × 3**). Toute saisie manuelle surcharge le calcul automatique.

### 3. Logistique & Données Tunisiennes
- **Livraison Intelligente** : 0 TND affiché au client sur la boutique, coût réel interne de 7 TND répercuté automatiquement dans l'analyse financière.
- **Validation Téléphone** : Format tunisien strict à 8 chiffres (préfixé automatiquement avec `+216`).
- **Gouvernorats** : Liste déroulante des 24 gouvernorats tunisiens intégrée dans le checkout.

### 4. Tracking Serveur & Reassurance
- **Meta Conversions API (CAPI)** : Envoi d'événements serveur `Purchase` lors de la livraison avec hachage SHA-256 (email, téléphone, nom) et déduplication d'événements.
- **Tracking Multi-canal** : GA4, TikTok Pixel, Facebook Pixel pilotés dynamiquement par variables d'environnement.
- **Bouton WhatsApp Flottant & Reassurance** : Directement lié au numéro marchand configuré.

---

## 📂 Structure du Dépôt

```
.
├── .github/
│   └── workflows/
│       └── ci.yml             # Workflow GitHub Actions (Syntaxe PHP, WPCS & Asset Check)
├── famma-mennou-foundation/
│   └── famma-mennou/
│       ├── .env.example       # Modèle des variables d'environnement (secrets ignorés)
│       ├── docker-compose.yml # Orchestration Docker (WordPress, MySQL, Mailpit, phpMyAdmin)
│       ├── phpcs.xml.dist     # Configuration des règles WordPress Coding Standards
│       ├── docs/              # Documentation technique et audits
│       │   ├── ARCHITECTURE.md
│       │   ├── DEPLOYMENT.md
│       │   ├── DESIGN-SYSTEM.md
│       │   ├── ORDER-WORKFLOW.md
│       │   ├── PLUGIN-REGISTER.md
│       │   └── AUDIT-UX-2026-08-18.md
│       ├── scripts/           # Scripts d'automatisation
│       │   ├── backup.sh      # Sauvegarde DB + Uploads
│       │   ├── lint.sh        # Exécution du linter WPCS en conteneur
│       │   ├── setup.sh       # Initialisation de l'environnement local
│       │   ├── create-homepage.php
│       │   └── create-shop-categories.php
│       └── wp-content/
│           ├── mu-plugins/    # Extensions système obligatoires (famma-env.php)
│           ├── plugins/
│           │   └── famma-core/ # Plugin métier FAMMA (Règles COD, CAPI, Pricing)
│           └── themes/
│               └── famma-child/ # Thème enfant (Design tokens, styles, templates Woo)
├── CLAUDE.md                  # Pointeur de configuration agent
├── ClaudeMemo.md              # Source de vérité & règles d'ingénierie
└── README.md                  # Documentation principale du projet
```

---

## 🚀 Guide de Démarrage Rapide (Local)

### Pre-requis
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (avec le plugin Docker Compose)
- [Git](https://git-scm.com/)

### 1. Démarrage de l'Environnement Docker
À la racine de `famma-mennou-foundation/famma-mennou` :
```bash
# Copier le fichier de configuration
cp .env.example .env

# Démarrer les conteneurs
docker compose up -d
```

### 2. Accès aux Services Locaux
| Service | URL | Usage |
| :--- | :--- | :--- |
| **Site WordPress** | [http://localhost:8080](http://localhost:8080) | Installation initiale & boutique |
| **Mailpit** | [http://localhost:8025](http://localhost:8025) | Capture des e-mails sortants (SMTP local) |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | Gestion de la base de données MySQL |

### 3. Activation des Extensions & Thèmes
1. Effectuer l'installation standard de WordPress sur `http://localhost:8080`.
2. Accéder à **Extensions → Extensions installées** :
   - Activer **WooCommerce**.
   - Activer **FAMMA Core**.
3. Dans **Apparence → Thèmes** : activer **FAMMA Child**.
4. Exécuter l'assistant WooCommerce (Devise: **TND / د.ت**, Zone: **Tunisie**, Paiement: **COD**).

---

## ✅ Assurance Qualité & CI/CD

Le projet respecte les normes **WordPress Coding Standards (WPCS)** avec une tolérance zéro pour les avertissements ou erreurs de syntaxe.

### 1. Linter Local (via Docker)
Pour vérifier la conformité du code sans installer d'outils sur votre machine hôte :
```bash
# Exécution dans le conteneur Docker composer:2
docker run --rm -v "%cd%/famma-mennou-foundation/famma-mennou:/app" -w /app composer:2 vendor/bin/phpcs
```

### 2. GitHub Actions (Quality Gate)
À chaque `push` ou `pull_request` sur la branche `main`, le pipeline automatisé [`.github/workflows/ci.yml`](file:///.github/workflows/ci.yml) vérifie :
- Syntaxe PHP (`php -l`) sur 100% des fichiers custom.
- Respect strict des conventions WPCS (`vendor/bin/phpcs`).
- Validation de la structure `composer.json` (`composer validate --strict`).

---

## 🔒 Déploiement & Sauvegardes

### Sauvegarde Locale & Planifiée
Un script dédié permet d'exporter la base de données MySQL et le dossier des téléversements (`uploads/`) :
```bash
# Lancer une sauvegarde manuelle
bash famma-mennou-foundation/famma-mennou/scripts/backup.sh
```
> *Les sauvegardes sont stockées dans `backups/` et ignorées par Git pour préserver les données clients.*

### Checklist Pré-Production
- [ ] Positionner `WP_ENVIRONMENT_TYPE=production` et `WP_DEBUG=false`.
- [ ] Renseigner les identifiants réels (`FAMMA_WHATSAPP_NUMBER`, `FAMMA_META_PIXEL_ID`, `FAMMA_META_ACCESS_TOKEN`).
- [ ] Activer le HTTPS et régénérer les permaliens.
- [ ] Vérifier qu'aucun secret ne fuite dans le HTML (`META_ACCESS_TOKEN` reste strictement serveur).

---

## 📚 Index de la Documentation

Pour en savoir plus sur les spécifications techniques et fonctionnelles :

- 📐 [Architecture Système & Stack](famma-mennou-foundation/famma-mennou/docs/ARCHITECTURE.md)
- 🔄 [Workflow des Commandes & Statuts COD](famma-mennou-foundation/famma-mennou/docs/ORDER-WORKFLOW.md)
- 🎨 [Charte Graphique & Jetons de Design](famma-mennou-foundation/famma-mennou/docs/DESIGN-SYSTEM.md)
- 🚢 [Guide de Déploiement & Production](famma-mennou-foundation/famma-mennou/docs/DEPLOYMENT.md)
- 📋 [Registre des Extensions Mu-Plugins](famma-mennou-foundation/famma-mennou/docs/PLUGIN-REGISTER.md)
- 🔍 [Rapport d'Audit UX & Écarts Maquettes](famma-mennou-foundation/famma-mennou/docs/AUDIT-UX-2026-08-18.md)

---

### 📄 Licence & Crédits
© 2026 **FAMMA**. Tous droits réservés.