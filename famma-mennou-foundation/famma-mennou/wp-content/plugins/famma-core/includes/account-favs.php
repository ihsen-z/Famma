<?php
/**
 * « Ma sélection » gardée dans le compte — case `acct_favs`.
 *
 * Les favoris vivent dans le navigateur (thème, `favourites.js`). Une fois
 * connecté, avec la case cochée, la liste est aussi gardée dans le compte
 * (méta utilisateur `_famma_favs`) : un favori ajouté sur un téléphone se
 * retrouve sur un autre après connexion. Les visiteurs ne changent pas : rien
 * n'est envoyé, la page reste en cache.
 *
 * Ce fichier porte la logique : l'onglet « Ma sélection » de « Mon compte »
 * (point d'accès WooCommerce `ma-selection`), la route REST qui reçoit les
 * ajouts et retraits, le script qui fusionne le navigateur et le compte, et
 * les textes réglables dans FAMMA → Pages → « Mon compte ». Le thème dessine
 * l'onglet.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Sélection du client gardée dans son compte.
 */
final class Account_Favs {

	/**
	 * Clé de page, au sens de `Pages_Content` (même section que l'en-tête).
	 */
	public const PAGE = 'account';

	/**
	 * Point d'accès de « Mon compte ».
	 */
	public const ENDPOINT = 'ma-selection';

	/**
	 * Méta utilisateur : identifiants des produits, du plus ancien au plus récent.
	 */
	public const META = '_famma_favs';

	/**
	 * Nombre maximal de produits gardés.
	 */
	public const MAX = 100;

	/**
	 * Option : point d'accès pour lequel les règles de réécriture sont à jour.
	 */
	private const REWRITE_OPTION = 'famma_favs_rewrite';

	/**
	 * Pose les crochets.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'query_vars' ) );
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 30 );
	}

	/**
	 * La case est-elle cochée pour la personne en cours ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return New_Look::part( 'acct_favs' );
	}

	/**
	 * Déclare le point d'accès à WooCommerce, qui crée la règle de réécriture.
	 * Toujours déclaré (case cochée ou non) : cocher la case ne demande pas de
	 * réécrire les règles.
	 *
	 * @param array<string, string> $vars Points d'accès de WooCommerce.
	 * @return array<string, string>
	 */
	public static function query_vars( $vars ): array {
		$vars                   = is_array( $vars ) ? $vars : array();
		$vars[ self::ENDPOINT ] = self::ENDPOINT;

		return $vars;
	}

	/**
	 * Une seule fois : rafraîchit les règles de réécriture pour que
	 * `/mon-compte/ma-selection/` existe.
	 *
	 * @return void
	 */
	public static function maybe_flush(): void {
		if ( self::ENDPOINT === get_option( self::REWRITE_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::ENDPOINT, false );
	}

	/**
	 * Un champ du schéma.
	 *
	 * @param string $key   Clé.
	 * @param string $label Libellé dans l'admin.
	 * @param string $fr    Texte français par défaut.
	 * @param string $ar    Texte derja par défaut.
	 * @param string $help  Aide.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $label, string $fr, string $ar, string $help = '' ): array {
		return array(
			'key'        => $key,
			'type'       => 'text',
			'label'      => $label,
			'help'       => $help,
			'default_fr' => $fr,
			'default_ar' => $ar,
		);
	}

	/**
	 * Schéma des textes de l'onglet.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		return array(
			self::field( 'tab_favs', __( 'Onglet — sélection', 'famma-core' ), __( 'Ma sélection', 'famma-core' ), 'اختياراتي', __( 'Avec la case « Mon compte : Ma sélection gardée dans le compte ». Aussi le titre du panneau.', 'famma-core' ) ),
			self::field( 'fav_add', __( 'Sélection — bouton ajouter au panier', 'famma-core' ), __( 'Ajouter', 'famma-core' ), 'زيد' ),
			self::field( 'fav_choose', __( 'Sélection — bouton produit à options', 'famma-core' ), __( 'Choisir', 'famma-core' ), 'اختار', __( 'Pour un produit à tailles ou couleurs : le bouton mène à sa fiche.', 'famma-core' ) ),
			self::field( 'fav_rm', __( 'Sélection — cœur « retirer » (lu à voix haute)', 'famma-core' ), __( 'Retirer de ma sélection', 'famma-core' ), 'نحّي من اختياراتي' ),
			self::field( 'fav_gone', __( 'Sélection — message après retrait', 'famma-core' ), __( 'Retiré de votre sélection.', 'famma-core' ), 'تنحّى من اختياراتك.' ),
			self::field( 'fav_undo', __( 'Sélection — bouton annuler', 'famma-core' ), __( 'Annuler', 'famma-core' ), 'رجّع' ),
			self::field( 'fav_empty', __( 'Sélection — vide', 'famma-core' ), __( 'Votre sélection est vide. Touchez le cœur sur un produit pour le garder ici.', 'famma-core' ), 'ما فما حتى شي. اضغط على القلب في منتج باش يتحفظ هوني.' ),
			self::field( 'fav_go', __( 'Sélection — bouton vers la boutique', 'famma-core' ), __( 'Voir la boutique', 'famma-core' ), 'شوف الحانوت' ),
		);
	}

	/**
	 * Lit un champ dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Un produit peut-il figurer dans une sélection ?
	 *
	 * @param int $id Identifiant du produit.
	 * @return bool
	 */
	private static function eligible( int $id ): bool {
		if ( $id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $id );

		return $product instanceof \WC_Product && 'publish' === $product->get_status() && $product->is_visible();
	}

	/**
	 * Liste brute gardée dans le compte.
	 *
	 * @param int $user_id Utilisateur.
	 * @return array<int, int>
	 */
	private static function stored( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META, true );

		return is_array( $raw ) ? array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) ) : array();
	}

	/**
	 * Produits de la sélection du client encore en vente, du plus récent au
	 * plus ancien.
	 *
	 * @param int $user_id Utilisateur.
	 * @return array<int, int>
	 */
	public static function ids( int $user_id ): array {
		return array_reverse( array_values( array_filter( self::stored( $user_id ), array( self::class, 'eligible' ) ) ) );
	}

	/**
	 * Déclare la route d'envoi des ajouts et retraits.
	 *
	 * @return void
	 */
	public static function register_route(): void {
		register_rest_route(
			'famma/v1',
			'/favs',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'sync' ),
				'permission_callback' => array( self::class, 'can_sync' ),
				'args'                => array(
					'add'    => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( self::class, 'clean_ids' ),
					),
					'remove' => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => array( self::class, 'clean_ids' ),
					),
				),
			)
		);
	}

	/**
	 * Seul un client connecté, case cochée pour lui, écrit dans son compte.
	 * Le jeton `wp_rest` (en-tête X-WP-Nonce) est vérifié par WordPress :
	 * sans lui, la requête arrive sans utilisateur et est refusée ici.
	 *
	 * @return bool
	 */
	public static function can_sync(): bool {
		return is_user_logged_in() && current_user_can( 'read' ) && self::active();
	}

	/**
	 * Identifiants reçus : entiers positifs, sans doublon, en nombre limité.
	 *
	 * @param mixed $value Valeur reçue.
	 * @return array<int, int>
	 */
	public static function clean_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) ), 0, self::MAX );
	}

	/**
	 * Applique les ajouts et retraits du navigateur, renvoie la liste du compte.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public static function sync( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = get_current_user_id();
		$list    = self::stored( $user_id );
		$remove  = (array) $request->get_param( 'remove' );
		$add     = (array) $request->get_param( 'add' );
		$list    = array_values( array_diff( $list, $remove ) );

		foreach ( $add as $id ) {
			if ( ! in_array( $id, $list, true ) && self::eligible( (int) $id ) ) {
				$list[] = (int) $id;
			}
		}

		$list = array_slice( $list, -self::MAX );

		update_user_meta( $user_id, self::META, $list );

		$response = new \WP_REST_Response(
			array(
				'favs' => array_map( 'strval', $list ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}

	/**
	 * Script de fusion, pour un client connecté seulement : la page d'un
	 * visiteur ne change pas et reste en cache.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_user_logged_in() || ! self::active() ) {
			return;
		}

		$path = plugin_dir_path( FAMMA_CORE_FILE ) . 'assets/js/favs-sync.js';

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_script(
			'famma-favs-sync',
			plugins_url( 'assets/js/favs-sync.js', FAMMA_CORE_FILE ),
			array(),
			(string) filemtime( $path ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_script(
			'famma-favs-sync',
			'window.fammaFavsSync=' . wp_json_encode(
				array(
					'url'   => esc_url_raw( rest_url( 'famma/v1/favs' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'who'   => substr( wp_hash( 'famma-favs|' . get_current_user_id() ), 0, 16 ),
				)
			) . ';',
			'before'
		);
	}
}
