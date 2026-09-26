<?php
/**
 * Commande allégée : le code promo et les notes seulement quand ils servent.
 *
 * Audit du 25/09 (UX-16, UX-17) : sur mobile, le premier écran de la commande
 * ne montrait qu'un encart « Avez-vous un code promo ? » et un choix de compte,
 * et le bouton de validation arrivait à 3,3 écrans.
 *
 * - Code promo : proposé seulement si au moins un code est publié. Un champ
 *   qu'aucun client ne peut remplir fait hésiter (« il me manque une remise »)
 *   au moment de payer. Dès que le propriétaire publie un code dans
 *   Marketing → Codes promo, le champ revient au panier et à la commande, sans
 *   autre réglage.
 * - Notes de commande : retirées. Une précision de livraison se donne pendant
 *   l'appel de confirmation, qui a lieu pour chaque commande COD.
 *
 * L'administration n'est pas concernée : l'écran d'une commande garde la note
 * client et le bouton « Appliquer un code promo ».
 *
 * « Expédier à une autre adresse » et la création de compte ne sont pas gérés
 * ici : ce sont des réglages de WooCommerce, laissés au propriétaire.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Retire du tunnel les champs qui ne servent à rien.
 */
final class Checkout_Lite {

	/**
	 * Instance partagée.
	 *
	 * @var Checkout_Lite|null
	 */
	private static ?Checkout_Lite $i = null;

	/**
	 * Au moins un code promo publié ? Calculé une fois par requête.
	 *
	 * @var bool|null
	 */
	private ?bool $has_coupon = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Checkout_Lite
	 */
	public static function instance(): Checkout_Lite {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'woocommerce_coupons_enabled', array( $this, 'coupons_enabled' ) );
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'order_notes_enabled' ) );
	}

	/**
	 * Code promo côté boutique : seulement si un code existe.
	 *
	 * Le réglage « Activer les codes promo » garde le dernier mot : désactivé,
	 * rien ne change ici.
	 *
	 * @param mixed $enabled Réglage de WooCommerce.
	 * @return bool
	 */
	public function coupons_enabled( $enabled ): bool {
		if ( ! $enabled || is_admin() ) {
			return (bool) $enabled;
		}

		return $this->has_published_coupon();
	}

	/**
	 * Notes de commande côté boutique : retirées.
	 *
	 * Filtre `famma_checkout_order_notes` à `true` pour les remettre.
	 *
	 * @param mixed $enabled Réglage de WooCommerce.
	 * @return bool
	 */
	public function order_notes_enabled( $enabled ): bool {
		if ( is_admin() ) {
			return (bool) $enabled;
		}

		return (bool) apply_filters( 'famma_checkout_order_notes', false );
	}

	/**
	 * Un code promo est-il publié ?
	 *
	 * Une seule requête, sur l'index du type et du statut, et seulement sur les
	 * pages qui demandent l'état des codes (panier, commande, application
	 * d'un code). Un code expiré mais encore publié compte : le champ reste,
	 * et WooCommerce refuse le code avec son propre message.
	 *
	 * @return bool
	 */
	private function has_published_coupon(): bool {
		if ( null === $this->has_coupon ) {
			$ids = get_posts(
				array(
					'post_type'        => 'shop_coupon',
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			$this->has_coupon = ! empty( $ids );
		}

		return $this->has_coupon;
	}
}
