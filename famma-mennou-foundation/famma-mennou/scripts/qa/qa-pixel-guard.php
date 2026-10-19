<?php
/**
 * Garde du clone QA pixel « famma-qa-pixel » (plan pixel, lot 0, T0.3).
 *
 * Monté en lecture seule comme mu-plugin par scripts/qa/compose-qa-pixel.yml.
 * Inerte partout ailleurs : il ne s'active que si home_url() vaut
 * http://localhost:8980. Trois rôles :
 *
 *  a) Dernier filet HTTP : pre_http_request à PHP_INT_MAX, et seulement si
 *     aucune simulation de suite n'a déjà répondu ($preempt encore false).
 *     Chaque requête est ajoutée à wp-content/uploads/qa-http-log.jsonl, sans
 *     aucun champ access_token. graph.facebook.com reçoit une réponse simulée
 *     HTTP 200 (en-tête facebook-api-version = version demandée), toute autre
 *     destination un WP_Error.
 *  b) Balises Google neutralisées (filtre famma_google_tags_load à false), et
 *     preconnect vers connect.facebook.net retiré (wp_resource_hints).
 *  c) Navigateur : un fbq minimal est imprimé en tête de <head>. Le chargeur
 *     de famma-core s'arrête sur « if(f.fbq)return; » : fbevents.js n'est
 *     jamais demandé et chaque appel est poussé dans window.__qaFbq (copie
 *     dans sessionStorage « __qaFbqLog », qui survit aux changements de page).
 *
 * Aucun secret réel : le clone n'utilise que le pixel 000000000000001 et le
 * jeton QA-FAKE-TOKEN-NOT-REAL.
 *
 * @package Famma_Integration_Tests
 */

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName -- mu-plugin de test, pas un fichier de classe chargé automatiquement.

defined( 'ABSPATH' ) || exit;

/** Garde du clone QA pixel : HTTP intercepté et journalisé, fbq enregistré. */
final class Famma_Qa_Pixel_Guard {

	/** Hôte et port exigés : ceux du clone et des gardes des suites de test. */
	private const HOST = 'localhost';

	/** Port publié par scripts/compose-integration.yml. */
	private const PORT = 8980;

	/** Journal des requêtes interceptées, relatif à WP_CONTENT_DIR. */
	private const LOG = '/uploads/qa-http-log.jsonl';

	/** Réponse simulée de la Conversions API. */
	private const GRAPH_BODY = '{"events_received":1,"messages":[],"fbtrace_id":"QA"}';

	/**
	 * Le site courant est-il le clone QA ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		$parts = wp_parse_url( home_url( '/' ) );

		return is_array( $parts )
			&& self::HOST === strtolower( (string) ( $parts['host'] ?? '' ) )
			&& self::PORT === (int) ( $parts['port'] ?? 0 );
	}

	/**
	 * Pose les hooks.
	 *
	 * Le garde du clone d'intégration (integration-browser-guard.php) filtre
	 * aussi pre_http_request à PHP_INT_MAX et, chargé avant nous (ordre
	 * alphabétique des mu-plugins), répondrait WP_Error à notre place. Il est
	 * donc retiré puis ré-inscrit juste après notre filtre : il reste le
	 * dernier filet, et les simulations des suites (priorités plus basses)
	 * gardent la main.
	 *
	 * @return void
	 */
	public static function boot(): void {
		$guard    = 'famma_integration_browser_http';
		$priority = has_filter( 'pre_http_request', $guard );
		if ( false !== $priority ) {
			remove_filter( 'pre_http_request', $guard, $priority );
		}
		add_filter( 'pre_http_request', array( self::class, 'http' ), PHP_INT_MAX, 3 );
		if ( false !== $priority ) {
			add_filter( 'pre_http_request', $guard, $priority );
		}

		add_filter( 'famma_google_tags_load', '__return_false', PHP_INT_MAX );
		add_action( 'wp_head', array( self::class, 'fbq_recorder' ), 0 );
		add_filter( 'wp_resource_hints', array( self::class, 'drop_meta_hints' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Retire les indications de ressources vers Meta (preconnect de famma-core).
	 *
	 * Un preconnect ouvre une connexion DNS + TLS vers connect.facebook.net
	 * depuis le navigateur de test : aucun contact avec Meta n'est voulu ici.
	 *
	 * @param array<int, string|array<string, string>> $urls          URLs de l'indication.
	 * @param string                                   $relation_type Type d'indication.
	 * @return array<int, string|array<string, string>>
	 */
	public static function drop_meta_hints( $urls, $relation_type ): array {
		unset( $relation_type );

		return array_values(
			array_filter(
				(array) $urls,
				static function ( $url ): bool {
					$href = is_array( $url ) ? (string) ( $url['href'] ?? '' ) : (string) $url;
					$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );

					return ! preg_match( '/(^|\.)(facebook\.net|facebook\.com)$/', $host );
				}
			)
		);
	}

	/**
	 * Intercepte une requête sortante qu'aucune simulation n'a traitée.
	 *
	 * @param false|array|WP_Error $preempt     Réponse déjà fournie, ou false.
	 * @param array                $parsed_args Arguments de la requête.
	 * @param string               $url         URL demandée.
	 * @return false|array|WP_Error
	 */
	public static function http( $preempt, $parsed_args, $url ) {
		if ( false !== $preempt ) {
			return $preempt;
		}

		$url     = (string) $url;
		$args    = is_array( $parsed_args ) ? $parsed_args : array();
		$host    = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
		$graph   = 'graph.facebook.com' === $host;
		$version = '';
		if ( $graph && 1 === preg_match( '#^/(v\d+\.\d+)/#', $path, $match ) ) {
			$version = $match[1];
		}

		self::log( $url, $host, $args, $graph ? 'simulated_200' : 'blocked', $version );

		if ( ! $graph ) {
			return new WP_Error( 'famma_qa_pixel_blocked', sprintf( 'QA pixel clone: outgoing HTTP to %s blocked.', $host ) );
		}

		return self::graph_response( $version );
	}

	/**
	 * Réponse simulée de graph.facebook.com.
	 *
	 * @param string $version Version Graph lue dans l'URL, par exemple v21.0.
	 * @return array<string, mixed>
	 */
	private static function graph_response( string $version ): array {
		$headers = array(
			'content-type'         => 'application/json; charset=UTF-8',
			'facebook-api-version' => $version,
		);
		if ( class_exists( '\WpOrg\Requests\Utility\CaseInsensitiveDictionary' ) ) {
			$headers = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( $headers );
		}

		return array(
			'headers'  => $headers,
			'body'     => self::GRAPH_BODY,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Ajoute une ligne JSON au journal des requêtes interceptées.
	 *
	 * @param string $url     URL demandée.
	 * @param string $host    Hôte demandé.
	 * @param array  $args    Arguments de la requête.
	 * @param string $outcome simulated_200 ou blocked.
	 * @param string $version Version Graph, vide hors Meta.
	 * @return void
	 */
	private static function log( string $url, string $host, array $args, string $outcome, string $version ): void {
		$entry = array(
			'time'          => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'microtime'     => microtime( true ),
			'context'       => self::context(),
			'trigger_uri'   => self::trigger_uri(),
			'method'        => strtoupper( (string) ( $args['method'] ?? 'GET' ) ),
			'url'           => remove_query_arg( 'access_token', $url ),
			'host'          => $host,
			'graph_version' => $version,
			'timeout'       => $args['timeout'] ?? null,
			'blocking'      => $args['blocking'] ?? true,
			'outcome'       => $outcome,
		) + self::body_fields( $args['body'] ?? null );

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $line ) ) {
			$line = wp_json_encode(
				array(
					'time'    => $entry['time'],
					'url'     => $entry['url'],
					'outcome' => $outcome,
					'error'   => 'unencodable_body',
				)
			);
		}

		$file = WP_CONTENT_DIR . self::LOG;
		if ( ! is_dir( dirname( $file ) ) ) {
			wp_mkdir_p( dirname( $file ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- journal local en ajout seul ; WP_Filesystem n'offre pas d'ajout atomique.
		file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX );
	}

	/**
	 * Corps de requête décodé, sans aucun champ access_token.
	 *
	 * Un corps texte qui n'est pas du JSON n'est jamais recopié (il pourrait
	 * porter un jeton encodé en formulaire) : seule sa longueur est gardée.
	 *
	 * @param mixed $body Corps transmis à WP_Http.
	 * @return array<string, mixed>
	 */
	private static function body_fields( $body ): array {
		if ( null === $body || '' === $body ) {
			return array(
				'body_format' => 'none',
				'body'        => null,
			);
		}
		if ( is_array( $body ) ) {
			return array(
				'body_format' => 'array',
				'body'        => self::without_token( $body ),
			);
		}

		$raw     = (string) $body;
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			return array(
				'body_format' => 'json',
				'body'        => self::without_token( $decoded ),
			);
		}

		return array(
			'body_format' => 'raw',
			'body'        => null,
			'body_length' => strlen( $raw ),
		);
	}

	/**
	 * Retire récursivement toute clé access_token.
	 *
	 * @param array<mixed> $data Données décodées.
	 * @return array<mixed>
	 */
	private static function without_token( array $data ): array {
		unset( $data['access_token'] );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::without_token( $value );
			}
		}

		return $data;
	}

	/**
	 * Contexte d'exécution de la requête.
	 *
	 * @return string
	 */
	private static function context(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		return is_admin() ? 'admin' : 'front';
	}

	/**
	 * Adresse de la requête entrante qui a déclenché l'appel sortant.
	 *
	 * @return string
	 */
	private static function trigger_uri(): string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		return remove_query_arg( 'access_token', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	/**
	 * Imprime l'enregistreur fbq, avant tout autre script du <head>.
	 *
	 * @return void
	 */
	public static function fbq_recorder(): void {
		wp_print_inline_script_tag( self::fbq_recorder_js(), array( 'id' => 'famma-qa-fbq-recorder' ) );
	}

	/**
	 * Code de l'enregistreur fbq.
	 *
	 * window.fbq est un accesseur : il renvoie toujours l'enregistreur. Une
	 * affectation ultérieure (le panneau de integration-browser-guard.php,
	 * imprimé dans le pied de page) n'écrase rien : la fonction affectée est
	 * appelée après l'enregistrement, ce qui garde aussi ce panneau.
	 *
	 * @return string
	 */
	private static function fbq_recorder_js(): string {
		return '(function(w){if(w.__qaFbqRecorder){return;}'
			. 'var calls=w.__qaFbq=w.__qaFbq||[],next=null,key="__qaFbqLog";'
			. 'var rec=function(){var a=Array.prototype.slice.call(arguments);calls.push(a);'
			. 'try{var s=w.sessionStorage,l=JSON.parse(s.getItem(key)||"[]");l.push({t:Date.now(),page:location.pathname+location.search,args:a});s.setItem(key,JSON.stringify(l.slice(-500)));}catch(e){}'
			. 'if("function"===typeof next){try{next.apply(this,a);}catch(e){}}};'
			. 'rec.queue=[];rec.loaded=!0;rec.version="2.0";rec.push=rec;rec.qaRecorder=!0;w.__qaFbqRecorder=rec;'
			. 'try{Object.defineProperty(w,"fbq",{configurable:!0,enumerable:!0,get:function(){return rec;},set:function(f){if(f!==rec&&"function"===typeof f){next=f;}}});}catch(e){w.fbq=rec;}'
			. 'w._fbq=rec;})(window);';
	}
}

if ( Famma_Qa_Pixel_Guard::active() ) {
	Famma_Qa_Pixel_Guard::boot();
}
