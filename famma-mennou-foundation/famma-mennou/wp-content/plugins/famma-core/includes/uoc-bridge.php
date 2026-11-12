<?php
/**
 * Passerelle avec le plugin « Upsell One Click ».
 *
 * Quand ce plugin est actif, l'offre après achat de famma-core se met en
 * veille (voir `boot()`) et cette passerelle :
 * - reprend une fois les réglages et l'historique `_famma_offer_*` (copie,
 *   rien n'est effacé : désactiver le plugin rend la main à famma-core) ;
 * - exclut les produits à tailles et les commandes d'essai ;
 * - recopie chaque étape (vue, fiche ouverte, refus, ajout) dans les méta
 *   `_famma_offer_*` et met en file l'événement Meta OfferAccepted.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lien famma-core ↔ Upsell One Click.
 */
final class Uoc_Bridge {

	/** Option : reprise des réglages déjà faite. */
	public const MIGRATED = 'famma_uoc_migrated';

	/**
	 * Instance partagée.
	 *
	 * @var Uoc_Bridge|null
	 */
	private static ?Uoc_Bridge $i = null;

	/**
	 * Upsell One Click est-il actif ? (Ses constantes sont définies au
	 * chargement du plugin, avant `plugins_loaded`.)
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return defined( 'UPSELL_OC_VERSION' ) && class_exists( '\UpsellOneClick\Offer' );
	}

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Uoc_Bridge
	 */
	public static function instance(): Uoc_Bridge {
		if ( null === self::$i ) {
			self::$i = new self();
			add_filter( 'upsell_oc_offerable', array( self::$i, 'without_sizes' ), 10, 2 );
			add_filter( 'upsell_oc_count_order_in_stats', array( self::$i, 'without_tests' ), 10, 2 );
			add_action( 'upsell_oc_offer_event', array( self::$i, 'mirror' ), 10, 3 );
			add_action( 'init', array( self::$i, 'migrate' ), 20 );
		}
		return self::$i;
	}

	/**
	 * Un produit à tailles demande un choix : pas d'ajout en un clic.
	 *
	 * @param bool  $ok      Proposable.
	 * @param mixed $product Produit.
	 * @return bool
	 */
	public function without_sizes( $ok, $product ): bool {
		return (bool) $ok && ! ( $product instanceof \WC_Product && '' !== trim( (string) $product->get_meta( Product_Sizes::META ) ) );
	}

	/**
	 * Les commandes d'essai ne comptent pas dans les chiffres.
	 *
	 * @param bool  $count Compter.
	 * @param mixed $order Commande.
	 * @return bool
	 */
	public function without_tests( $count, $order ): bool {
		return (bool) $count && ! ( $order instanceof \WC_Order && $order->get_meta( '_famma_test_order' ) );
	}

	/**
	 * Recopie l'étape dans les méta famma-core et, à l'ajout, met en file
	 * OfferAccepted (envoyé seulement si la case Meta est cochée).
	 *
	 * @param string $event `shown`, `viewed`, `accepted` ou `declined`.
	 * @param mixed  $order Commande.
	 * @param mixed  $offer Offre.
	 * @return void
	 */
	public function mirror( $event, $order, $offer ): void {
		if ( ! $order instanceof \WC_Order || ! is_array( $offer ) ) {
			return;
		}
		$keys = array(
			'shown'    => Offer_Settings::ORDER_SHOWN,
			'viewed'   => Offer_Settings::ORDER_VIEWED,
			'accepted' => Offer_Settings::ORDER_ACCEPTED,
			'declined' => Offer_Settings::ORDER_DECLINED,
		);
		if ( ! isset( $keys[ $event ] ) ) {
			return;
		}
		$order->update_meta_data( $keys[ $event ], time() );
		$order->update_meta_data( Offer_Settings::ORDER_SOURCE, (string) ( $offer['source'] ?? '' ) );
		if ( 'accepted' === $event || 'shown' === $event ) {
			$order->update_meta_data( Offer_Settings::ORDER_TARGET, (string) ( $offer['target'] ?? '' ) );
		}
		if ( 'accepted' === $event ) {
			$order->update_meta_data( Offer_Settings::ORDER_AMOUNT, wc_format_decimal( (float) ( $offer['price'] ?? 0 ), 3 ) );
		}
		$order->save();
		if ( 'accepted' === $event ) {
			Capi::instance()->queue_offer_accepted( $order->get_id() );
		}
	}

	/**
	 * Reprise unique des réglages, de l'historique et des textes.
	 *
	 * @return void
	 */
	public function migrate(): void {
		if ( get_option( self::MIGRATED ) ) {
			return;
		}
		$this->migrate_products();
		$this->migrate_orders();
		$this->migrate_options();
		update_option( self::MIGRATED, FAMMA_CORE_VERSION . ' ' . gmdate( 'c' ), true );
	}

	/**
	 * Copie les réglages produit, sans écraser un réglage déjà saisi.
	 *
	 * @return void
	 */
	private function migrate_products(): void {
		$map = array(
			Offer_Settings::META_ENABLED => \UpsellOneClick\Offer::META_ENABLED,
			Offer_Settings::META_PRODUCT => \UpsellOneClick\Offer::META_PRODUCT,
			Offer_Settings::META_PRICE   => \UpsellOneClick\Offer::META_PRICE,
			Offer_Settings::META_TITLE   => \UpsellOneClick\Offer::META_TITLE,
			Offer_Settings::META_TEXT    => \UpsellOneClick\Offer::META_TEXT,
			Offer_Settings::META_SHEET   => \UpsellOneClick\Offer::META_SHEET,
			Offer_Settings::META_POINTS  => \UpsellOneClick\Offer::META_POINTS,
			Offer_Settings::META_USAGE   => \UpsellOneClick\Offer::META_USAGE,
		);
		$ids = wc_get_products(
			array(
				'limit'    => 200,
				'return'   => 'ids',
				'status'   => array( 'publish', 'private', 'draft' ),
				'meta_key' => Offer_Settings::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- une seule fois, 200 produits au plus.
			)
		);
		foreach ( (array) $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof \WC_Product && $this->copy( $product, $map ) ) {
				$product->save();
			}
		}
	}

	/**
	 * Copie l'historique des 60 derniers jours (chiffres de l'onglet).
	 *
	 * @return void
	 */
	private function migrate_orders(): void {
		$map = array(
			Offer_Settings::ORDER_SHOWN    => \UpsellOneClick\Offer::ORDER_SHOWN,
			Offer_Settings::ORDER_VIEWED   => \UpsellOneClick\Offer::ORDER_VIEWED,
			Offer_Settings::ORDER_ACCEPTED => \UpsellOneClick\Offer::ORDER_ACCEPTED,
			Offer_Settings::ORDER_DECLINED => \UpsellOneClick\Offer::ORDER_DECLINED,
			Offer_Settings::ORDER_AMOUNT   => \UpsellOneClick\Offer::ORDER_AMOUNT,
			Offer_Settings::ORDER_SOURCE   => \UpsellOneClick\Offer::ORDER_SOURCE,
			Offer_Settings::ORDER_TARGET   => \UpsellOneClick\Offer::ORDER_TARGET,
		);
		$ids = wc_get_orders(
			array(
				'limit'        => 500,
				'return'       => 'ids',
				'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ),
				// `meta_key` seul (et non `meta_query`) : accepté par les deux
				// stockages des commandes, classique et HPOS.
				'meta_key'     => Offer_Settings::ORDER_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- une seule fois, 500 commandes au plus.
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$changed = $this->copy( $order, $map );
			foreach ( $order->get_items() as $item ) {
				if ( $item->get_meta( Offer_Settings::ITEM_FLAG ) && ! $item->get_meta( \UpsellOneClick\Offer::ITEM_FLAG ) ) {
					$item->add_meta_data( \UpsellOneClick\Offer::ITEM_FLAG, 'yes', true );
					$item->save();
				}
			}
			if ( $changed ) {
				$order->save();
			}
		}
	}

	/**
	 * Copie des méta d'un objet WooCommerce, sans écraser.
	 *
	 * @param \WC_Data              $data Produit ou commande.
	 * @param array<string, string> $map  Clé famma => clé Upsell One Click.
	 * @return bool Vrai si une méta a été copiée.
	 */
	private function copy( \WC_Data $data, array $map ): bool {
		$changed = false;
		foreach ( $map as $from => $to ) {
			$value = $data->get_meta( $from );
			if ( '' !== (string) $value && '' === (string) $data->get_meta( $to ) ) {
				$data->update_meta_data( $to, $value );
				$changed = true;
			}
		}
		return $changed;
	}

	/**
	 * Réglages du magasin : statut « En attente de confirmation », paiement à
	 * la livraison et textes FAMMA (français + derja). Un réglage déjà
	 * présent n'est jamais écrasé.
	 *
	 * @return void
	 */
	private function migrate_options(): void {
		$nbsp    = "\u{00A0}";
		$options = array(
			'upsell_oc_open_statuses'    => array( 'wc-pending-confirm' ),
			'upsell_oc_payment_methods'  => array( 'cod' ),
			'upsell_oc_text_kicker'      => 'Offre spéciale',
			'upsell_oc_text_kicker_alt'  => 'عرض خاص',
			'upsell_oc_text_title'       => 'Ajoutez-le à votre colis',
			'upsell_oc_text_text'        => 'Même colis, livraison gratuite. Valable jusqu’à notre appel de confirmation.',
			'upsell_oc_text_accept'      => 'Oui, ajouter · +{price}',
			'upsell_oc_text_accept_alt'  => 'زيدها للكوموند',
			'upsell_oc_text_decline'     => 'Non merci',
			'upsell_oc_text_more'        => 'Découvrir le produit',
			'upsell_oc_text_more_alt'    => 'شوف المنتج',
			'upsell_oc_text_back'        => 'Retour',
			'upsell_oc_text_usage_title' => 'Comment l’utiliser',
			'upsell_oc_text_trust_1'     => 'Paiement à la livraison',
			'upsell_oc_text_trust_2'     => 'Dans le même colis',
			'upsell_oc_text_trust_3'     => 'Livraison gratuite',
			'upsell_oc_text_note'        => 'Vous pourrez encore changer d’avis lors de notre appel de confirmation.',
			'upsell_oc_text_added'       => 'C’est ajouté à votre commande. Nouveau total à payer à la livraison' . $nbsp . ': {total}.',
			'upsell_oc_text_added_alt'   => 'تزاد للكوموند متاعك',
			'upsell_oc_text_closed'      => 'Cette offre n’est plus disponible' . $nbsp . ': votre commande est déjà en préparation.',
			'upsell_oc_text_closed_alt'  => 'العرض هذا وفى',
			'upsell_oc_text_retry'       => 'Le clic n’a pas abouti. Réessayez le bouton ci-dessous.',
			'upsell_oc_text_retry_alt'   => 'عاود اضغط على الزر',
			'upsell_oc_color_accent'     => '#FF7A00',
			'upsell_oc_color_primary'    => '#1D4ED8',
		);
		foreach ( $options as $name => $value ) {
			if ( false === get_option( $name ) ) {
				update_option( $name, $value );
			}
		}
	}
}
