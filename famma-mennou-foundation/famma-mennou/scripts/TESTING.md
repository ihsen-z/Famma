# Tests de famma-core

Deux familles, qu'il ne faut pas mélanger.

## 1. Suites unitaires (sans WordPress) : lancées par la CI et par `lint.sh`

```
php scripts/run-unit-tests.php            # tout
php scripts/run-unit-tests.php --list     # ce qui est découvert
php scripts/run-unit-tests.php capi-retry # filtre par morceau de nom
php scripts/run-unit-tests.php --php-only | --js-only | --verbose
```

Découverte par motif de nom, jamais par liste : tout fichier `scripts/tests-unit/test-*.php|js`
et tout `scripts/test-*.php|js` qui ne mentionne ni `WP_CLI` ni `wp eval-file` est lancé.
Une nouvelle suite déposée là est donc exécutée sans toucher au lanceur.

Une suite est rouge si : code de sortie non nul, aucune sortie (rien n'a été testé), une ligne `FAIL`,
un `RESULT x/y` avec x différent de y, ou un warning/notice/deprecated/fatal PHP.
Le lanceur renvoie 1 au moindre rouge, ou s'il ne trouve aucune suite.

Prérequis : PHP 8.1+ avec `mbstring` et `sodium` ; node pour les suites `.js` (qui appellent `php`).
Sous Windows sans ces extensions dans le php.ini :

```
FAMMA_PHP_ARGS="-n -d extension_dir=C:/chemin/php/ext -d extension=mbstring -d extension=sodium" \
PHPRC=<dossier avec un php.ini chargeant les mêmes extensions> \
php -n -d extension_dir=... -d extension=mbstring -d extension=sodium scripts/run-unit-tests.php
```

`scripts/tests-quarantine.txt` liste les suites déjà rouges avant les réformes du pixel : elles tournent,
s'affichent `KNOWN` et ne bloquent pas la CI. Ajouter une ligne cache une suite en échec : décision à relire.
Le lanceur signale quand une suite quarantainée redevient verte (retirer alors la ligne).

Horloge : le code du namespace `Famma\Core` appelle `time()`. `scripts/tests-unit/double-clock.php`
(chargé par `bootstrap.php`) le redirige vers `$GLOBALS['famma_test_now']` si défini, sinon l'heure réelle.
Les jeux d'essai se construisent relatifs à `time()` ; seul un test à dates historiques figées
(`test-capi-match-keys.php`) fige l'horloge. Ne jamais coder une date absolue proche de « maintenant ».

## 2. Suites d'intégration (WordPress + WooCommerce réels) : jamais dans la CI

Elles exigent un clone WordPress/WooCommerce avec base de données ; elles tournent sous Docker,
dans le clone isolé (`scripts/compose-integration.yml`), jamais sur la production :

| Suite | Lancement (dans le conteneur WP-CLI du clone) |
|---|---|
| `test-capi-integration.php`, `test-capi-fbc-integration.php`, `test-capi-registre-integration.php`, `test-meta-value-integration.php` | `FAMMA_INTEGRATION_TEST=1 FAMMA_TEST_DB_NAME=famma_integration FAMMA_TEST_DB_HOST=db wp eval-file scripts/<suite>.php` |
| `test-woocommerce-integration.php` | `FAMMA_WOO_INTEGRATION=1 wp eval-file scripts/test-woocommerce-integration.php` |
| `test-express-integration.php`, `test-sizes-integration.php`, `test-report-statuses.php` | `wp eval-file scripts/<suite>.php` (voir l'en-tête de chaque fichier) |
| `scripts/qa/test-qa-*.php` | `bash scripts/qa/qa-l2-run-suites.sh` puis `bash scripts/qa/qa-l5-integration-run.sh` (clone `famma-qa-pixel`) |

Lancées sans WordPress, ces suites sortent sans rien tester : c'est pourquoi le lanceur unitaire les écarte
(mention de `WP_CLI`) et refuse une suite silencieuse.
