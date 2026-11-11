<?php
/**
 * Parcours visiteurs — entonnoir du formulaire express, par fiche produit.
 *
 * Pour chaque produit : sessions qui ont commencé le formulaire, qui l'ont
 * envoyé, qui ont commandé, et envois refusés (`form_error`) par cause.
 *
 * Une seule lecture des événements utiles de la période, agrégée en PHP : le
 * libellé d'un refus peut porter plusieurs codes (« name,state »), et une
 * session ne doit compter qu'une fois par cause. Le résultat, des nombres
 * seulement, est gardé cinq minutes en transient.
 *
 * Une commande compte pour un produit quand la même session a commencé son
 * formulaire : la commande est enregistrée côté serveur, hors fiche produit.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Agrégats de l'entonnoir du formulaire express.
 */
final class Visitor_Form_Funnel {

	/**
	 * Durée du cache, en secondes (comme Visitor_Report).
	 */
	private const CACHE_TTL = 300;

	/**
	 * Événements lus au plus sur une période : borne la mémoire.
	 */
	public const MAX_ROWS = 20000;

	/**
	 * Marches de l'entonnoir, par événement du formulaire.
	 */
	private const STEPS = array(
		'form_start'  => 'started',
		'form_submit' => 'submitted',
		'form_error'  => 'blocked',
	);

	/**
	 * Entonnoir de la période, depuis le cache ou la table.
	 *
	 * @param int $days Période en jours.
	 * @return array{products: array<int, array<string, int>>, total: array<string, int>, causes: array<string, array<string, int>>, truncated: bool}
	 */
	public function express( int $days ): array {
		$key    = 'famma_vj_express_' . $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, result cached in a transient just below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, product_id, event, label
				FROM %i
				WHERE event IN ('form_start','form_submit','form_error','order_placed') AND created_at >= %s
					AND ( page_type = 'product' OR event = 'order_placed' )
				LIMIT %d",
				Visitor_Store::instance()->table(),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
				self::MAX_ROWS
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		$out  = self::aggregate( $rows );

		$out['truncated'] = count( $rows ) >= self::MAX_ROWS;
		set_transient( $key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * Compte les sessions de chaque marche, par produit et au total.
	 *
	 * Une session qui a envoyé ou vu un refus a forcément commencé : elle
	 * compte à « commencé » même si `form_start` manque (script chargé tard).
	 *
	 * @param array<int, array<string, mixed>> $rows Événements : session_id, product_id, event, label.
	 * @return array{products: array<int, array<string, int>>, total: array<string, int>, causes: array<string, array<string, int>>, truncated: bool}
	 */
	public static function aggregate( array $rows ): array {
		$ordered  = array();
		$sets     = array();
		$all      = array();
		$refusals = array();
		$causes   = array();
		foreach ( $rows as $row ) {
			$session = (string) ( $row['session_id'] ?? '' );
			$event   = (string) ( $row['event'] ?? '' );
			if ( '' === $session ) {
				continue;
			}
			if ( 'order_placed' === $event ) {
				$ordered[ $session ] = true;
				continue;
			}
			$product = (int) ( $row['product_id'] ?? 0 );
			$step    = self::STEPS[ $event ] ?? '';
			if ( $product <= 0 || '' === $step ) {
				continue;
			}
			$sets[ $product ]['started'][ $session ] = true;
			$sets[ $product ][ $step ][ $session ]   = true;
			$all['started'][ $session ]              = true;
			$all[ $step ][ $session ]                = true;
			if ( 'form_error' === $event ) {
				$refusals[ $product ] = ( $refusals[ $product ] ?? 0 ) + 1;
				self::add_causes( $causes, (string) ( $row['label'] ?? '' ), $session );
			}
		}

		$products = array();
		foreach ( $sets as $product => $set ) {
			$products[ $product ] = self::counts( $set, $ordered, $refusals[ $product ] ?? 0 );
		}
		uasort(
			$products,
			static function ( array $a, array $b ): int {
				return $b['started'] <=> $a['started'];
			}
		);

		$out = array();
		foreach ( $causes as $code => $cause ) {
			$out[ $code ] = array(
				'refusals' => $cause['refusals'],
				'sessions' => count( $cause['sessions'] ),
				'lost'     => count( array_diff_key( $cause['sessions'], $ordered ) ),
			);
		}
		uasort(
			$out,
			static function ( array $a, array $b ): int {
				return $b['refusals'] <=> $a['refusals'];
			}
		);

		return array(
			'products'  => $products,
			'total'     => self::counts( $all, $ordered, array_sum( $refusals ) ),
			'causes'    => $out,
			'truncated' => false,
		);
	}

	/**
	 * Ajoute les codes d'un refus à leurs compteurs.
	 *
	 * @param array<string, array{refusals: int, sessions: array<string, bool>}> $causes  Compteurs par code.
	 * @param string                                                             $label   Codes séparés par des virgules.
	 * @param string                                                             $session Session du refus.
	 * @return void
	 */
	private static function add_causes( array &$causes, string $label, string $session ): void {
		foreach ( array_unique( explode( ',', $label ) ) as $code ) {
			if ( ! in_array( $code, Visitor_Collector::FORM_ERROR_CODES, true ) ) {
				continue;
			}
			if ( ! isset( $causes[ $code ] ) ) {
				$causes[ $code ] = array(
					'refusals' => 0,
					'sessions' => array(),
				);
			}
			++$causes[ $code ]['refusals'];
			$causes[ $code ]['sessions'][ $session ] = true;
		}
	}

	/**
	 * Nombres d'une ligne : sessions par marche, refus, bloquées puis commandées.
	 *
	 * @param array<string, array<string, bool>> $set      Sessions par marche.
	 * @param array<string, bool>                $ordered  Sessions avec commande.
	 * @param int                                $refusals Envois refusés.
	 * @return array<string, int>
	 */
	private static function counts( array $set, array $ordered, int $refusals ): array {
		$started = $set['started'] ?? array();
		$blocked = $set['blocked'] ?? array();
		return array(
			'started'   => count( $started ),
			'submitted' => count( $set['submitted'] ?? array() ),
			'ordered'   => count( array_intersect_key( $started, $ordered ) ),
			'blocked'   => count( $blocked ),
			'refusals'  => $refusals,
			'recovered' => count( array_intersect_key( $blocked, $ordered ) ),
		);
	}
}
