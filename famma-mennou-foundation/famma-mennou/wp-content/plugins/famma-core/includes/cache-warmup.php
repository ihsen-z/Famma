<?php
/**
 * Préchauffage du cache de page après une purge complète (PERF-21).
 *
 * Le robot d'exploration de LiteSpeed Cache est désactivé côté serveur chez
 * l'hébergeur : après « Tout purger », les pages restaient froides jusqu'à
 * la visite suivante, qui payait 2 à 6 s de PHP. Ce module redemande les
 * pages publiques une à une, depuis le cron serveur, par lots bornés dans
 * le temps, pour que ce soit lui, et non un client venu d'une pub, qui
 * paie le premier rendu.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Programme et exécute le préchauffage du cache LiteSpeed.
 */
final class Cache_Warmup {

	/**
	 * Événement cron qui traite un lot.
	 */
	private const HOOK = 'famma_cache_warmup';

	/**
	 * Délai avant le premier lot : la purge réelle part dans les en-têtes de
	 * la réponse en cours, il faut la laisser aboutir.
	 */
	private const FIRST_DELAY = 60;

	/**
	 * Temps maximal passé par lot, en secondes (max_execution_time vaut 30).
	 */
	private const BUDGET = 20;

	/**
	 * Nombre maximal de fiches produit réchauffées.
	 */
	private const MAX_PRODUCTS = 100;

	/**
	 * Instance partagée.
	 *
	 * @var Cache_Warmup|null
	 */
	private static ?Cache_Warmup $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Cache_Warmup
	 */
	public static function instance(): Cache_Warmup {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks. Les purges partent souvent de l'admin, le lot
	 * tourne dans le cron : rien n'est réservé au front.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'litespeed_purged_all', array( $this, 'schedule' ) );
		add_action( 'litespeed_purged_all_lscache', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Programme le premier lot, une seule fois par vague de purges.
	 *
	 * @return void
	 */
	public function schedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( time() + self::FIRST_DELAY, self::HOOK, array( 0 ) );
	}

	/**
	 * Réchauffe les URL à partir de la position donnée, puis programme la
	 * suite si le budget de temps est épuisé avant la fin de la liste.
	 *
	 * @param int $offset Position de départ dans la liste.
	 * @return void
	 */
	public function run( int $offset = 0 ): void {
		$urls  = $this->urls();
		$total = count( $urls );
		$start = microtime( true );

		while ( $offset < $total && ( microtime( true ) - $start ) < self::BUDGET ) {
			wp_remote_get(
				$urls[ $offset ],
				array(
					'timeout'    => 10,
					'user-agent' => 'FAMMA cache warmup',
					'cookies'    => array(),
				)
			);
			++$offset;
		}

		if ( $offset < $total ) {
			wp_schedule_single_event( time() + 1, self::HOOK, array( $offset ) );
		}
	}

	/**
	 * Construit la liste des pages publiques à réchauffer, les plus visitées
	 * d'abord : accueil, boutique, rayons, fiches, puis leurs versions
	 * traduites.
	 *
	 * @return string[]
	 */
	private function urls(): array {
		$urls = array( home_url( '/' ) );

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$urls[] = wc_get_page_permalink( 'shop' );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_string( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		$ids = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => self::MAX_PRODUCTS,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'ids',
			)
		);
		foreach ( (array) $ids as $id ) {
			$urls[] = get_permalink( (int) $id );
		}

		$urls = array_merge( $urls, $this->translated( $urls ) );

		/**
		 * Filtre la liste des URL réchauffées après une purge complète.
		 *
		 * @param string[] $urls URL absolues.
		 */
		$urls = (array) apply_filters( 'famma_cache_warmup_urls', $urls );

		return array_values( array_unique( array_filter( array_map( 'strval', $urls ) ) ) );
	}

	/**
	 * Renvoie les versions traduites des URL quand TranslatePress est actif.
	 *
	 * @param string[] $urls URL dans la langue par défaut.
	 * @return string[]
	 */
	private function translated( array $urls ): array {
		if ( ! class_exists( '\TRP_Translate_Press' ) ) {
			return array();
		}

		$trp       = \TRP_Translate_Press::get_trp_instance();
		$settings  = $trp->get_component( 'settings' );
		$converter = $trp->get_component( 'url_converter' );
		if ( ! is_object( $settings ) || ! is_object( $converter ) || ! method_exists( $converter, 'get_url_for_language' ) ) {
			return array();
		}

		$conf      = (array) $settings->get_settings();
		$default   = (string) ( $conf['default-language'] ?? '' );
		$languages = array_diff( (array) ( $conf['publish-languages'] ?? array() ), array( $default ) );

		$out = array();
		foreach ( $languages as $lang ) {
			foreach ( $urls as $url ) {
				$out[] = (string) $converter->get_url_for_language( $lang, $url, '' );
			}
		}
		return $out;
	}
}
