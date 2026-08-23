# Décisions d'architecture (ADR)

Chaque décision est datée, argumentée, et déclarée **réversible ou non**. Une décision non documentée ici n'a pas été prise.

---

## ADR-001 — Racine Git et dépôt distant
**Date** : 2026-08-13 · **Statut** : ACTÉE (propriétaire) · **Réversible** : oui

**Décision** : racine Git = `E:\Ecom`. Dépôt distant **privé**.

**Conséquences**
- Le dépôt englobe `MASTER-PROMPT.md`, `ClaudeMemo.md`, `CLAUDE.md` **et** le projet imbriqué `famma-mennou-foundation/famma-mennou/`.
- Le `.gitignore` existant du projet reste valide : ses motifs à barre oblique initiale (`/wp-content/plugins/*`) sont relatifs au dossier qui le contient, pas à la racine Git. Un second `.gitignore` racine couvre les artefacts de niveau supérieur.
- **`gh` (GitHub CLI) n'est pas installé** (voir `ENVIRONMENT-AUDIT.md`). L'agent ne peut donc pas créer le dépôt distant, et le §40 interdit de demander un mot de passe. Deux chemins possibles, au choix du propriétaire :
  1. installer GitHub CLI puis `gh auth login` (flux d'authentification sécurisé), l'agent crée ensuite `famma-mennou` en privé ;
  2. créer le dépôt privé à la main sur github.com et fournir l'URL — l'agent configure le `remote`.
- **Aucun `git push` ne sera exécuté sans feu vert explicite**, conformément à `ClaudeMemo.md`.

---

## ADR-002 — Spécification de référence
**Date** : 2026-08-13 · **Statut** : ACTÉE (propriétaire) · **Réversible** : non

**Décision** : `MASTER-PROMPT.md` (64 sections) est versionné à la racine du dépôt et devient la source de vérité des `§n` cités par toute la documentation.

**Hiérarchie des règles en cas de conflit** : `MASTER-PROMPT.md` (le *quoi*) → `ClaudeMemo.md` (le *comment*, qualité et sécurité) → les docs de `docs/` (l'*état réel implémenté*).

---

## ADR-003 — Type de checkout WooCommerce
**Date** : 2026-08-13 · **Statut** : ACTÉE (agent, §52) · **Réversible** : oui, coût contenu

**Décision** : **checkout classique (shortcode `[woocommerce_checkout]`)** pour le MVP, et non le checkout en blocs.

**Options évaluées**

| Critère (§10, §13, §23) | Checkout blocs | Checkout classique |
|---|---|---|
| Retirer les champs inutiles (société, adresse 2, code postal, **pays**) | Impossible pour les champs d'adresse du cœur ; l'API `woocommerce_register_additional_checkout_field` **ajoute** des champs, elle n'en retire pas | `woocommerce_checkout_fields` : contrôle total |
| Sélecteur de gouvernorat | via `woocommerce_states` (fonctionne), mais le champ **Pays** reste imposé | `woocommerce_states` + suppression du pays |
| Validation du téléphone tunisien | callbacks Store API, plus indirects | `woocommerce_after_checkout_validation`, direct |
| Poids JS sur connexion mobile lente | bundle React du checkout | jQuery + script Woo, nettement plus léger |
| Code déjà écrit dans `famma-core` | à réécrire intégralement | **fonctionne tel quel** |
| Trajectoire WooCommerce | défaut des nouvelles installations | toujours pris en charge, aucune date de retrait annoncée |

**Justification** : le checkout est le point de friction n°1 d'une boutique COD tunisienne mobile-first sur réseau lent (§10, §47). Le checkout classique donne le contrôle total des champs exigé par le §13 *et* la charge JS la plus faible. Le checkout en blocs impose le champ Pays — inutile pour une boutique qui ne vend qu'en Tunisie — et n'apporte aucun bénéfice ici puisqu'il n'y a pas de passerelle de paiement en ligne à intégrer (§2).

**Risque assumé** : WooCommerce fait des blocs le défaut des nouvelles installations. Le classique reste livré et supporté, sans retrait annoncé. **Non vérifié contre la documentation officielle** — Context7 n'est pas connecté (§53). À revalider dès la Phase 1 sur la version réellement installée.

**Plan de sortie** : la bascule vers les blocs ne toucherait que la classe `Tunisia` de `famma-core`. Rien d'autre n'en dépend.

---

## ADR-004 — Thème
**Date** : 2026-08-13 · **Statut** : ACTÉE (agent, §52) · **Réversible** : oui

**Décision** : **Kadence** (gratuit, GPL) + thème enfant **`famma-child`** versionné dans le dépôt.

**Options évaluées (§24)**

| Thème | Perf. | Woo (version gratuite) | RTL | Éditeur de blocs | Verdict |
|---|---|---|---|---|---|
| Storefront | bonne | référence officielle | oui | limité | **Rejeté** — UX commerciale mobile datée, peu de contrôle sans code |
| Astra | très bonne | contrôle Woo largement réservé à Astra Pro | oui | correct | **Rejeté** — trop de fonctions derrière le payant (§24 : gratuit uniquement) |
| Blocksy | excellente | offre gratuite très généreuse | oui | correct | **Retenu en repli** — second choix assumé |
| **Kadence** | excellente | meilleur contrôle des gabarits Woo en version gratuite | oui | bon, sans constructeur de pages | **CHOISI** |

**Justification** : Kadence offre le meilleur rapport contrôle-des-gabarits-WooCommerce / poids en version gratuite, un RTL natif, et fonctionne avec Gutenberg **sans constructeur de pages** — ce qu'exige le §25. Sa base documentaire est la plus large des quatre, ce qui compte pour la maintenabilité à un seul développeur (§26 q.5).

**Garde-fous**
- Toute la logique métier reste dans `famma-core` ; `famma-child` ne fait que de la présentation (`ClaudeMemo.md` règle 6). Le thème est donc **remplaçable sans toucher au métier**.
- Le CTA collant mobile (§13, §30) est implémenté dans le CSS de `famma-child`, pas via une fonction du thème : il ne dépend d'aucun palier gratuit/Pro.
- La répartition exacte gratuit/Pro des fonctions Kadence sera **vérifiée à l'installation** (Context7 indisponible).

---

## ADR-005 — Multilingue et RTL
**Date** : 2026-08-13 · **Statut** : ACTÉE (propriétaire : « plugin i18n gratuit, dialecte tunisien ») · **Réversible** : oui, coût élevé après saisie du contenu

**Décision** : **TranslatePress (version gratuite)**. Langue par défaut **Français**, langue additionnelle unique **Arabe (Tunisie)**. Traduction **humaine** uniquement.

**Options évaluées**

| Solution | Coût | Produits WooCommerce | Verdict |
|---|---|---|---|
| Polylang gratuit | gratuit | **l'extension WooCommerce est payante** | **Rejeté** — inutilisable sur une boutique |
| WPML / Weglot | payant | oui | **Rejeté** — §24/§26 : gratuit uniquement |
| **TranslatePress gratuit** | gratuit | traduit tout le front, y compris les chaînes Woo | **CHOISI** |

**Justification** : la version gratuite de TranslatePress autorise **exactement une langue additionnelle** — soit précisément le besoin (FR + Derja). Elle traduit le rendu final, ce qui couvre WooCommerce sans extension payante.

**Contraintes imposées par ce choix**
- **Traduction automatique désactivée**. Le §5 interdit la traduction machine de la copie commerciale : la Derja est rédigée par un humain.
- Les traductions vivent dans les tables propres à TranslatePress → elles doivent entrer dans le périmètre des sauvegardes (§36) et du plan de migration (§43).
- Les chaînes d'interface de `famma-core` restent traduites par `.po`/`.mo` sous `famma-core/languages/`, **pas** par TranslatePress — le domaine `famma-core` reste la seule source pour le code.
- RTL : piloté par la locale `ar`, avec des propriétés logiques CSS (`margin-inline`, `padding-inline`) pour une feuille de style unique (`DESIGN-SYSTEM.md`).
- Sélecteur de langue : dans l'en-tête, cible tactile ≥ 48 px (§10).

**À inscrire dans `PLUGIN-REGISTER.md`** à l'installation (§26).

---

## ADR-006 — Environnement de développement local
**Date** : 2026-08-22 · **Statut** : ACTÉE (propriétaire : « adapter et installer le site sur Local ») · **Réversible** : oui, coût faible

**Décision** : le développement quotidien se fait sur **Local (Local by Flywheel)**, site `famma` / `famma.local`. La stack **Docker du dépôt est conservée**, inchangée, comme environnement de référence reproductible.

**Options évaluées**

| Option | Verdict |
|---|---|
| Docker seul (état antérieur) | **Rejeté** comme unique environnement — démarrage lourd, dépend de Docker Desktop |
| Local seul, Docker supprimé | **Rejeté** — Local ne fige pas les versions dans le dépôt : plus rien ne garantirait la reproductibilité (§28, §54) |
| **Local pour le quotidien + Docker conservé** | **CHOISI** |

**Justification** : Local démarre en quelques secondes et fournit PHP 8.3.29 — la version visée en production (Oxahost, `DEPLOY-OXAHOST.md`). Docker reste la référence versionnée : `docker-compose.yml` épingle WordPress, MariaDB et PHP, ce que `sites.json` de Local ne fait pas.

**Contraintes imposées par ce choix**

- **Le code n'est pas copié.** `famma-core`, `famma-child` et `mu-plugins` sont montés dans le site Local par des **jonctions Windows** (`mklink /J`) vers le dépôt. Le dépôt reste la source unique ; une copie divergerait en silence.
- **Aucune donnée migrée.** Produits, clients et commandes de la base Docker étaient des données de test : la base de Local est neuve, le catalogue vide (§58). Seuls le paramétrage, la structure et le design sont reproduits — par les scripts de `scripts/`, donc rejouables.
- **Le pont `.env` → constantes change de forme.** Local n'a pas d'environnement de conteneur : les constantes `FAMMA_*` sont définies dans le `wp-config.php` du site Local, hors dépôt. Le mu-plugin `famma-env` ignore toute constante déjà définie, donc aucun code n'a été modifié pour cela.
- **Mailpit est par site.** Le mu-plugin `famma-local-mail` visait `mailpit:1025` en dur ; il lit désormais `FAMMA_LOCAL_SMTP_HOST` / `FAMMA_LOCAL_SMTP_PORT`, avec les valeurs Docker par défaut.
- **`setup.sh` est devenu portable** (`FAMMA_WP_CLI`, `FAMMA_SCRIPTS_DIR`, `FAMMA_LANG_DIR`, `FAMMA_URL`, `FAMMA_ADMIN`, `FAMMA_REWRITE_HARD`, `FAMMA_SKIP_OG`) plutôt que dupliqué. Un seul script, donc une seule vérité.
- **Écart de version assumé** : Local sert MySQL 8.0.35 là où Docker sert MariaDB 11.4. Sans import de base, la différence est sans effet ; elle redeviendrait un sujet le jour où l'on importera un dump.
- Toute validation destinée à la production reste à rejouer sous Docker, seul environnement aligné sur les versions épinglées.

---

## ADR-007 — Contenu des pages institutionnelles : tout paramétrable, rien en dur
**Date** : 2026-08-23 · **Statut** : ACTÉE (propriétaire : « tous doit etre parametrable ne pas utilisé le hard coding ») · **Réversible** : oui, coût faible

**Décision** : le contenu des pages **À propos** et **Contact** vit dans des options WordPress éditables depuis **FAMMA → Pages**. Les gabarits du thème enfant ne portent aucun texte : ils lisent le plugin et se taisent quand un champ est vide. La règle vaut pour les pages à venir, pas seulement pour ces deux-là.

**Options évaluées**

| Option | Verdict |
|---|---|
| Textes en dur dans les gabarits, traduits par `__()` | **Rejeté** — changer une phrase exigerait un dev et un déploiement |
| Contenu laissé à l'éditeur de blocs | **Rejeté** comme mécanisme principal — le design n'est plus verrouillé, et la structure FR/Derja n'est pas garantie |
| **Champs d'options dans `famma-core`, gabarit en présentation seule** | **CHOISI** |

**Justification** : le propriétaire doit rester autonome sur son contenu. Un texte qu'il ne peut pas corriger seul finit périmé.

**Conséquences**

- **Un schéma unique** décrit les champs ; l'écran d'administration, l'assainissement et la lecture côté thème en découlent tous les trois. Ajouter un bloc = ajouter une ligne au schéma.
- **Bilingue par champ** : chaque bloc existe en français et en derja. L'arabe n'est servi qu'en contexte RTL, et retombe sur le français s'il est vide.
- **Exception au « fichier unique »** (règle 7) : la classe vit dans `famma-core/includes/pages-content.php`. `famma-core.php` passait 2 100 lignes, ce que la règle 6 signale comme un appel à scinder. L'exception préserve les deux garanties du fichier unique — un `require_once` explicite plutôt qu'un autoloader, et une seule classe par fichier inclus.
- **Les coordonnées deviennent éditables**, y compris le **numéro WhatsApp** qui n'était lisible que depuis une constante. La précédence reste celle du pied de page : constante `.env` du serveur d'abord, champ d'administration en repli. Un champ neutralisé par une constante le dit à l'écran.
- **Aucune donnée inventée** (§58) : les seuls faits affirmés sont ceux que le checkout applique — paiement à la livraison, livraison offerte, 24 gouvernorats. Les **horaires n'ont aucune valeur par défaut** : les inventer promettrait une disponibilité que personne n'a confirmée.
- **Pas de formulaire de contact** : WhatsApp porte l'appel à l'action, le téléphone et l'e-mail suivent. Un formulaire aurait ajouté une dépendance, une surface de spam et une attente de réponse par e-mail que personne ne surveille.
- **Un bloc vide disparaît** côté client, mais laisse un rappel visible du seul propriétaire (`manage_options`) : masquer en silence ferait qu'un champ oublié ne se remarque jamais.
