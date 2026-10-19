<?php
/**
 * Contrôle de l'interception HTTP du clone QA pixel (plan pixel, lot 0, T0.3).
 * Run with FAMMA_INTEGRATION_TEST=1, FAMMA_TEST_DB_NAME=famma_integration,
 * FAMMA_TEST_DB_HOST=db, then wp eval-file /tests/qa/qa-pixel-http-check.php.
 *
 * Envoie un wp_remote_post factice vers graph.facebook.com et un
 * wp_remote_get vers une autre destination, puis vérifie que
 * qa-pixel-guard.php a simulé le premier (HTTP 200, events_received 1,
 * en-tête facebook-api-version), bloqué le second, et journalisé les deux
 * dans wp-content/uploads/qa-http-log.jsonl sans le jeton factice.
 * Aucune requête ne quitte le conteneur : le filtre répond avant le réseau.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- WP-CLI check entry point, not an autoloaded plugin class file.

defined( 'ABSPATH' ) || exit;

if ( '1' !== getenv( 'FAMMA_INTEGRATION_TEST' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'This check requires WP-CLI and explicit isolated-clone opt-in.' );
}
$famma_expected_db      = (string) getenv( 'FAMMA_TEST_DB_NAME' );
$famma_expected_db_host = (string) getenv( 'FAMMA_TEST_DB_HOST' );
if ( 'local' !== wp_get_environment_type() || 'famma_integration' !== $famma_expected_db || 'db' !== $famma_expected_db_host || ! defined( 'DB_NAME' ) || DB_NAME !== $famma_expected_db || ! defined( 'DB_HOST' ) || DB_HOST !== $famma_expected_db_host ) {
	throw new RuntimeException( 'Refusing: expected disposable local database identity is not explicitly confirmed.' );
}
if ( ! class_exists( 'Famma_Qa_Pixel_Guard' ) || ! Famma_Qa_Pixel_Guard::active() ) {
	throw new RuntimeException( 'Refusing: qa-pixel-guard.php is not active (clone must answer on http://localhost:8980).' );
}

/** Prove that outgoing HTTP is intercepted and logged without the token. */
final class Famma_Qa_Pixel_Http_Check {

	/** Jeton factice du clone, jamais un vrai secret. */
	private const FAKE_TOKEN = 'QA-FAKE-TOKEN-NOT-REAL';

	/** Pixel factice du clone. */
	private const FAKE_PIXEL = '000000000000001';

	/** Version Graph demandée par le contrôle. */
	private const VERSION = 'v21.0';

	/** @var array<string, bool> Named assertion outcomes. */
	private static array $assertions = array();

	/**
	 * Exécute le contrôle et imprime le résultat JSON.
	 *
	 * @return void
	 */
	public static function run(): void {
		$file   = WP_CONTENT_DIR . '/uploads/qa-http-log.jsonl';
		$before = is_readable( $file ) ? count( (array) file( $file, FILE_IGNORE_NEW_LINES ) ) : 0;

		$graph_url = 'https://graph.facebook.com/' . self::VERSION . '/' . self::FAKE_PIXEL . '/events';
		$response  = wp_remote_post(
			$graph_url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode(
					array(
						'data'         => array(
							array(
								'event_name'    => 'QAInterceptCheck',
								'event_time'    => time(),
								'event_id'      => 'qa_intercept_check',
								'action_source' => 'website',
							),
						),
						'access_token' => self::FAKE_TOKEN,
					)
				),
			)
		);
		$other     = wp_remote_get( 'https://example.com/qa-pixel-check', array( 'timeout' => 3 ) );

		$decoded = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
		self::check( 'graph_not_wp_error', ! is_wp_error( $response ) );
		self::check( 'graph_http_200', 200 === (int) wp_remote_retrieve_response_code( $response ) );
		self::check( 'graph_events_received_1', is_array( $decoded ) && 1 === ( $decoded['events_received'] ?? null ) );
		self::check( 'graph_fbtrace_qa', is_array( $decoded ) && 'QA' === ( $decoded['fbtrace_id'] ?? null ) );
		self::check( 'graph_header_version', self::VERSION === wp_remote_retrieve_header( $response, 'facebook-api-version' ) );
		self::check( 'other_destination_wp_error', is_wp_error( $other ) && 'famma_qa_pixel_blocked' === $other->get_error_code() );

		$lines   = is_readable( $file ) ? (array) file( $file, FILE_IGNORE_NEW_LINES ) : array();
		$new     = array_slice( $lines, $before );
		$entries = array_map(
			static function ( $line ) {
				return json_decode( (string) $line, true );
			},
			$new
		);
		$graph   = null;
		$blocked = null;
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && $graph_url === ( $entry['url'] ?? '' ) ) {
				$graph = $entry;
			}
			if ( is_array( $entry ) && 'example.com' === ( $entry['host'] ?? '' ) ) {
				$blocked = $entry;
			}
		}
		$raw_log = is_readable( $file ) ? (string) file_get_contents( $file ) : '';

		self::check( 'log_new_lines_at_least_two', count( $new ) >= 2 );
		self::check( 'log_graph_entry', is_array( $graph ) && 'POST' === $graph['method'] && 'simulated_200' === $graph['outcome'] && self::VERSION === $graph['graph_version'] );
		self::check( 'log_graph_body_decoded', is_array( $graph ) && 'json' === $graph['body_format'] && 'QAInterceptCheck' === ( $graph['body']['data'][0]['event_name'] ?? '' ) );
		self::check( 'log_graph_body_without_access_token', is_array( $graph ) && is_array( $graph['body'] ) && ! array_key_exists( 'access_token', $graph['body'] ) );
		self::check( 'log_blocked_entry', is_array( $blocked ) && 'blocked' === $blocked['outcome'] );
		self::check( 'log_never_contains_fake_token', '' !== $raw_log && false === strpos( $raw_log, self::FAKE_TOKEN ) );
		self::check( 'log_never_contains_access_token_key', '' !== $raw_log && false === strpos( $raw_log, 'access_token' ) );

		$callbacks = array();
		global $wp_filter;
		if ( isset( $wp_filter['pre_http_request'] ) && $wp_filter['pre_http_request'] instanceof WP_Hook ) {
			foreach ( $wp_filter['pre_http_request']->callbacks as $priority => $hooks ) {
				foreach ( $hooks as $name => $hook ) {
					$callbacks[] = ( PHP_INT_MAX === $priority ? 'PHP_INT_MAX' : (string) $priority ) . ' ' . $name;
				}
			}
		}

		$failed = array_keys( array_filter( self::$assertions, static fn( bool $ok ): bool => ! $ok ) );
		echo wp_json_encode(
			array(
				'check'                     => 'qa-pixel-http-intercept',
				'log_file'                  => 'wp-content/uploads/qa-http-log.jsonl',
				'assertions'                => self::$assertions,
				'failed'                    => $failed,
				'pre_http_request_callbacks' => $callbacks,
				'new_log_entries'           => $entries,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "\n";

		if ( array() !== $failed ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Enregistre une assertion nommée.
	 *
	 * @param string $name Nom.
	 * @param bool   $ok   Résultat.
	 * @return void
	 */
	private static function check( string $name, bool $ok ): void {
		self::$assertions[ $name ] = $ok;
	}
}

Famma_Qa_Pixel_Http_Check::run();
