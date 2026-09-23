<?php
/**
 * Plugin Name: FAMMA — WP-Cron piloté par le serveur
 * Description: En production, confie les tâches planifiées à une tâche cron du serveur au lieu de les déclencher au passage des visiteurs.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * Audit du 23/09 (PERF-14 / SEC-17) : sans tâche cron serveur, WP-Cron ne
 * tournait qu'au passage d'un visiteur, et c'est ce visiteur qui en payait le
 * coût dans son temps de réponse. Avec un cache de page, ce serait pire : une
 * page servie depuis le cache n'exécute plus PHP, donc les tâches planifiées
 * de WooCommerce (Action Scheduler, nettoyage des sessions) prendraient un
 * retard imprévisible.
 *
 * PRÉREQUIS, à ne pas oublier : une tâche cron DirectAdmin toutes les 5 minutes
 * sur `wp-cron.php`. Sans elle, plus aucune tâche planifiée ne s'exécute.
 *
 * Réservé à la production : le site Local (`WP_ENVIRONMENT_TYPE` = `local`)
 * n'a pas de cron système et garde le déclenchement par les visites.
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISABLE_WP_CRON' ) && 'production' === wp_get_environment_type() ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante du cœur WordPress, lue par wp_cron().
	define( 'DISABLE_WP_CRON', true );
}
