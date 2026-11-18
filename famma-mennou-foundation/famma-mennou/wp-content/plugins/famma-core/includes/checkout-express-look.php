<?php
/**
 * Page commande comme le formulaire express (brique `co_express`, onglet
 * « Panier » de FAMMA → Nouveau look) : règles des champs.
 *
 * Choix du propriétaire du 18/11 : une seule carte « Vos coordonnées »
 * (téléphone avec « +216 » fixe, nom complet, adresse de livraison =
 * gouvernorat + ville/cité/rue dans un même cadre, e-mail facultatif), plus
 * de case « J'ai lu et j'accepte les conditions générales » (remplacée par la
 * phrase du formulaire express), plus de carte « Mode de livraison » ni de
 * bloc « Paiement à la livraison » (présentation : thème, inc/new-look-checkout.php).
 *
 * Ici, seulement ce qui touche la commande enregistrée, aligné sur la
 * commande express (`Express_Checkout::relax_address_fields()`) :
 * - ville/cité/rue facultative, le gouvernorat reste obligatoire ;
 * - plus de champ « Ville / Délégation » séparé : la ville se tape dans
 *   l'adresse ;
 * - champs rangés dans l'ordre de l'express ;
 * - pas de case des conditions, donc pas d'exigence.
 *
 * Brique décochée : rien ne change.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Commande au format du formulaire express.
 */
final class Checkout_Express_Look {

	/**
	 * Ordre des champs de facturation, comme l'express.
	 *
	 * @var array<string, int>
	 */
	private const ORDER = array(
		'billing_phone'      => 10,
		'billing_first_name' => 20,
		'billing_state'      => 30,
		'billing_address_1'  => 40,
		'billing_email'      => 50,
	);

	/**
	 * Branche les filtres.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		// Après les champs tunisiens de famma-core (10), avant la commande express (99).
		add_filter( 'woocommerce_checkout_fields', array( self::class, 'fields' ), 30 );
		add_filter( 'woocommerce_checkout_show_terms', array( self::class, 'show_terms' ), 30 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 26 );
	}

	/**
	 * La brique est-elle servie à la requête en cours ?
	 *
	 * @return bool
	 */
	public static function on(): bool {
		return New_Look::part( 'co_express' );
	}

	/**
	 * Champs de la commande au format de l'express.
	 *
	 * @param array<string, array<string, mixed>> $fields Champs par groupe.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		if ( ! self::on() || empty( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
			return $fields;
		}

		unset( $fields['billing']['billing_city'] );

		if ( isset( $fields['billing']['billing_address_1'] ) ) {
			$fields['billing']['billing_address_1']['required'] = false;
		}

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['autocomplete'] = 'tel-national';

			$attributes = (array) ( $fields['billing']['billing_phone']['custom_attributes'] ?? array() );

			$attributes['inputmode'] = 'numeric';

			$fields['billing']['billing_phone']['custom_attributes'] = $attributes;
		}

		foreach ( self::ORDER as $key => $priority ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['priority'] = $priority;
			}
		}

		return $fields;
	}

	/**
	 * Pas de case des conditions : la phrase du formulaire express la remplace.
	 *
	 * Sans case affichée, WooCommerce n'envoie pas `terms-field` et n'exige
	 * donc rien.
	 *
	 * @param mixed $show Affichage prévu.
	 * @return bool
	 */
	public static function show_terms( $show ): bool {
		return self::on() ? false : (bool) $show;
	}

	/**
	 * Feuille du formulaire express (cadres « +216 » et adresse) et script
	 * partagé de la proposition du gouvernorat, sur la page commande.
	 *
	 * @return void
	 */
	public static function assets(): void {
		if ( ! self::on() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$css = plugin_dir_path( __DIR__ ) . 'assets/css/express-order.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style( 'famma-express', plugin_dir_url( __DIR__ ) . 'assets/css/express-order.css', array(), (string) filemtime( $css ) );
		}

		Express_Order::enqueue_places();
	}

	/**
	 * Textes du script de la page commande (thème).
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array {
		return array(
			/* translators: %s: governorate name, e.g. "Nabeul". */
			'found' => __( 'Gouvernorat ajouté : %s. Vous pouvez le changer.', 'famma-core' ),
		);
	}
}
