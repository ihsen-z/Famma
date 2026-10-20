<?php
/**
 * Clone QA pixel, lot 5 : bac à sable d'options pour UN processus WP-CLI.
 *
 * Le plugin famma-core 0.3.2 écrit son état de santé (famma_capi_health, signaux,
 * battement de cœur, limites d'e-mail, compteur fbc) dans des options. Le
 * clone est partagé avec le testeur navigateur : un test qui fait passer la
 * santé au rouge ne doit pas changer ce que voit l'autre agent. Les options
 * déclarées ici sont lues et écrites en mémoire, dans ce seul processus :
 *
 *  - lecture : `pre_option_{nom}` renvoie la valeur mémorisée (initialisée
 *    avec la valeur stockée, ou la valeur par défaut fournie) ;
 *  - écriture : `pre_update_option_{nom}` mémorise la nouvelle valeur et
 *    renvoie l'ancienne, si bien que update_option() ne touche pas la base.
 *
 * Les valeurs par défaut ne doivent jamais valoir false : un filtre
 * `pre_option_*` qui renvoie false laisse WordPress lire la base.
 *
 * Les e-mails sont comptés (`pre_wp_mail`) et jamais envoyés.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- QA helper loaded by eval-file scripts, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Famma_Qa_L5_Sandbox' ) ) {

	/** In-memory options and mail counter for one WP-CLI process. */
	final class Famma_Qa_L5_Sandbox {

		/**
		 * Options de santé de famma-core 0.3.2, avec leur valeur par défaut.
		 */
		public const HEALTH_OPTIONS = array(
			'famma_capi_health'                   => array(),
			'famma_capi_health_signals'           => array(),
			'famma_capi_heartbeat'                => 0,
			'famma_capi_health_mail'              => 0,
			'famma_capi_health_mail_codes'        => array(),
			'famma_capi_fbc_rejected'             => array(),
			'famma_meta_version_notice_dismissed' => '',
			'famma_watchdog_mail'                 => 0,
		);

		/**
		 * Valeurs mémorisées, par nom d'option.
		 *
		 * @var array<string, mixed>
		 */
		private static array $store = array();

		/**
		 * E-mails interceptés (sujet seulement).
		 *
		 * @var array<int, string>
		 */
		public static array $mails = array();

		/**
		 * Place des options dans le bac à sable.
		 *
		 * @param array<string, mixed> $defaults Nom => valeur par défaut (jamais false).
		 * @return void
		 */
		public static function options( array $defaults ): void {
			global $wpdb;
			foreach ( $defaults as $name => $default ) {
				if ( array_key_exists( $name, self::$store ) ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- stored value read once, without the option cache.
				$raw                  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
				self::$store[ $name ] = null === $raw ? $default : maybe_unserialize( $raw );
				add_filter( 'pre_option_' . $name, array( self::class, 'read' ), PHP_INT_MAX, 2 );
				add_filter( 'pre_update_option_' . $name, array( self::class, 'write' ), PHP_INT_MAX, 3 );
			}
		}

		/**
		 * Pose une valeur mémorisée (préparation d'un scénario).
		 *
		 * @param string $name  Option.
		 * @param mixed  $value Valeur (jamais false).
		 * @return void
		 */
		public static function set( string $name, $value ): void {
			self::$store[ $name ] = $value;
		}

		/**
		 * Valeur mémorisée.
		 *
		 * @param string $name Option.
		 * @return mixed
		 */
		public static function get( string $name ) {
			return self::$store[ $name ] ?? null;
		}

		/**
		 * Lecture filtrée.
		 *
		 * @param mixed  $pre    Valeur précédente du filtre.
		 * @param string $option Option.
		 * @return mixed
		 */
		public static function read( $pre, $option ) {
			unset( $pre );
			return self::$store[ $option ];
		}

		/**
		 * Écriture filtrée : mémorisée, jamais en base.
		 *
		 * @param mixed  $value  Nouvelle valeur.
		 * @param mixed  $old    Ancienne valeur.
		 * @param string $option Option.
		 * @return mixed L'ancienne valeur, pour que update_option() n'écrive rien.
		 */
		public static function write( $value, $old, $option ) {
			self::$store[ $option ] = $value;
			return $old;
		}

		/**
		 * Compte les e-mails sans les envoyer.
		 *
		 * @return void
		 */
		public static function mails(): void {
			add_filter(
				'pre_wp_mail',
				static function ( $pre, $atts ) {
					unset( $pre );
					self::$mails[] = (string) ( $atts['subject'] ?? '' );
					return true;
				},
				PHP_INT_MAX - 1,
				2
			);
		}
	}
}
