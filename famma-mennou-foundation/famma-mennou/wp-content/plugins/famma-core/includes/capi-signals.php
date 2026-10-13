<?php
/**
 * Signaux de rapprochement Meta (fbc, fbp, IP, navigateur) et mode test CAPI.
 *
 * Meta relie une commande à une annonce grâce à l'identifiant de clic `fbc`.
 * Jusqu'en 0.2.5, ce cookie n'existait que si la bibliothèque du Pixel
 * (fbevents.js) avait eu le temps de se charger — or elle est volontairement
 * différée (PERF-07). Un visiteur qui commandait vite, ou qui changeait de
 * page avant ce chargement, perdait son `fbclid` : la commande partait sans
 * `fbc`. Ce fichier crée les cookies dès l'arrivée et valide tout ce qui est
 * relu au moment de la commande.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lecture et validation des signaux navigateur, mode test CAPI.
 */
final class Capi_Signals {

	/**
	 * Format Meta d'un cookie `_fbc` / `_fbp` :
	 * fb.<index de domaine>.<horodatage ms>.<valeur>[.<appendice>].
	 *
	 * L'appendice facultatif est ajouté par les outils récents de Meta
	 * (Parameter Builder) : il doit être accepté, pas rejeté.
	 */
	private const COOKIE_PATTERN = '/^fb\.[0-9]\.[0-9]{10,13}\.[A-Za-z0-9_-]{1,500}(\.[A-Za-z0-9_+\/=-]{1,100})?$/';

	/**
	 * Format d'un code de l'outil « Tester les événements ».
	 */
	private const TEST_CODE_PATTERN = '/^TEST[A-Za-z0-9_-]+$/';

	/**
	 * Handle du script de cookies ; sert aussi de motif d'exclusion LiteSpeed.
	 */
	public const HANDLE = 'famma-fbc';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Signals|null
	 */
	private static ?Capi_Signals $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Signals
	 */
	public static function instance(): Capi_Signals {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_notices', array( self::$i, 'test_mode_notice' ) );
			add_action( 'wp_enqueue_scripts', array( self::$i, 'enqueue_cookie_script' ), 1 );
			add_filter( 'wp_inline_script_attributes', array( self::$i, 'no_optimize_attributes' ) );
			add_filter( 'litespeed_optimize_js_excludes', array( self::$i, 'litespeed_exclude' ) );
			add_filter( 'litespeed_optm_js_defer_exc', array( self::$i, 'litespeed_exclude' ) );
		}
		return self::$i;
	}

	/**
	 * Valide une valeur `_fbc` / `_fbp` ; renvoie une chaîne vide si invalide.
	 *
	 * @param string $raw Valeur brute (cookie ou méta déjà enregistrée).
	 * @return string
	 */
	public static function clean_cookie( string $raw ): string {
		$value = trim( sanitize_text_field( $raw ) );

		return 1 === preg_match( self::COOKIE_PATTERN, $value ) ? $value : '';
	}

	/**
	 * Lit et valide un cookie Meta de la requête courante.
	 *
	 * @param string $name `_fbc` ou `_fbp`.
	 * @return string
	 */
	public static function cookie( string $name ): string {
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return '';
		}

		return self::clean_cookie( sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) );
	}

	/**
	 * Valide une adresse IP ; renvoie une chaîne vide si invalide.
	 *
	 * @param string $raw Adresse brute.
	 * @return string
	 */
	public static function clean_ip( string $raw ): string {
		$ip = trim( $raw );

		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Adresse IP du client de la requête courante.
	 *
	 * `REMOTE_ADDR` uniquement : un en-tête `X-Forwarded-For` est forgeable
	 * par n'importe quel visiteur.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return self::clean_ip( $raw );
	}

	/**
	 * Agent utilisateur du client de la requête courante, borné à 512 caractères.
	 *
	 * @return string
	 */
	public static function client_user_agent(): string {
		$raw = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return substr( $raw, 0, 512 );
	}

	/**
	 * Script qui crée `_fbc` (arrivée depuis une annonce) et `_fbp` s'ils manquent.
	 *
	 * Côté navigateur plutôt que PHP : une page servie par le cache LiteSpeed
	 * n'exécute aucun PHP, un `setcookie()` n'y partirait jamais. Le script
	 * n'écrase jamais un cookie existant et le pose sur le domaine principal
	 * (comme le Pixel), pour qu'il n'en existe qu'un seul.
	 *
	 * `_fbc` : fb.1.<horodatage ms>.<fbclid>, seulement si l'URL porte un fbclid.
	 * `_fbp` : fb.1.<horodatage ms>.<nombre aléatoire de 10 chiffres>.
	 * Les deux durent 90 jours.
	 *
	 * @return string
	 */
	public static function fbc_bootstrap_script(): string {
		return '(function(){try{var a=";path=/;max-age=7776000;SameSite=Lax"+("https:"===location.protocol?";Secure":""),'
			. 'h=location.hostname.split("."),id=new URLSearchParams(location.search).get("fbclid");'
			. 'function put(n,v){for(var i=h.length-2;i>=0;i--){document.cookie=n+"="+v+a+";domain="+h.slice(i).join(".");if(document.cookie.indexOf(n+"="+v)>-1){return;}}document.cookie=n+"="+v+a;}'
			. 'if(id&&/^[A-Za-z0-9_-]{1,500}$/.test(id)&&!/(?:^|;\s*)_fbc=/.test(document.cookie)){put("_fbc","fb.1."+Date.now()+"."+id);}'
			. 'if(!/(?:^|;\s*)_fbp=/.test(document.cookie)){put("_fbp","fb.1."+Date.now()+"."+(1e9+Math.floor(Math.random()*9e9)));}'
			. '}catch(e){}})();';
	}

	/**
	 * Place le script dans le `<head>`, seul, dès qu'un Pixel Meta est configuré.
	 *
	 * Un handle à part (et non `famma-pixels`, en pied de page) : il s'exécute
	 * avant tout le reste et peut être exclu des optimisations sans toucher
	 * au Pixel.
	 *
	 * @return void
	 */
	public function enqueue_cookie_script(): void {
		if ( '' === Config::instance()->meta_pixel_id() ) {
			return;
		}
		wp_register_script( self::HANDLE, false, array(), FAMMA_CORE_VERSION, false );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script( self::HANDLE, self::fbc_bootstrap_script() );
	}

	/**
	 * Interdit à LiteSpeed (et à Rocket Loader) de retarder, différer ou combiner le script.
	 *
	 * @param array<string, string|bool> $attributes Attributs de la balise inline.
	 * @return array<string, string|bool>
	 */
	public function no_optimize_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( self::HANDLE . '-js-after' === ( $attributes['id'] ?? '' ) ) {
			$attributes['data-no-optimize'] = '1';
			$attributes['data-no-defer']    = '1';
			$attributes['data-cfasync']     = 'false';
		}

		return $attributes;
	}

	/**
	 * Ajoute le script aux exclusions LiteSpeed (combinaison, report, retard).
	 *
	 * Filet de sécurité si l'attribut `data-no-optimize` était un jour ignoré.
	 *
	 * @param mixed $excludes Liste d'exclusions de LiteSpeed Cache.
	 * @return array<int, string>
	 */
	public function litespeed_exclude( $excludes ): array {
		$excludes   = is_array( $excludes ) ? $excludes : array_filter( (array) $excludes );
		$excludes[] = self::HANDLE;

		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Le mode test est-il activé ? Désactivé par défaut.
	 *
	 * Constante `FAMMA_META_CAPI_TEST_MODE` (true) ou option
	 * `famma_meta_capi_test_mode` (1). Volontairement absent de l'écran de
	 * réglages : oublié en production, il transformerait les vraies
	 * conversions en événements de test que Meta n'attribue pas.
	 *
	 * @return bool
	 */
	public static function test_mode_enabled(): bool {
		if ( defined( 'FAMMA_META_CAPI_TEST_MODE' ) && true === constant( 'FAMMA_META_CAPI_TEST_MODE' ) ) {
			return true;
		}

		return (bool) get_option( 'famma_meta_capi_test_mode', false );
	}

	/**
	 * Code de test à joindre aux événements réels, ou chaîne vide.
	 *
	 * Rien n'est joint si le mode test est éteint ou si le code n'a pas le
	 * format de l'outil « Tester les événements ».
	 *
	 * @return string
	 */
	public static function test_event_code(): string {
		if ( ! self::test_mode_enabled() ) {
			return '';
		}
		$code = Config::instance()->meta_test_event_code();

		return 1 === preg_match( self::TEST_CODE_PATTERN, $code ) ? $code : '';
	}

	/**
	 * Avertit l'équipe tant que le mode test est actif.
	 *
	 * @return void
	 */
	public function test_mode_notice(): void {
		if ( ! self::test_mode_enabled() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$message = '' === self::test_event_code()
			? __( 'FAMMA : le mode test Meta est activé, mais aucun code de test valide (TEST…) n’est configuré. Les événements partent normalement.', 'famma-core' )
			: __( 'FAMMA : le mode test Meta est activé. OrderPlaced et Purchase partent comme événements de TEST et ne comptent pas dans les campagnes. À désactiver avant toute mise en production.', 'famma-core' );
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
	}
}
