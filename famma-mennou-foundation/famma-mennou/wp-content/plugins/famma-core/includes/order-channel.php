<?php
/**
 * Canal de commande — d'où vient une commande saisie à la main.
 *
 * Une commande reçue par Messenger, WhatsApp, Instagram ou téléphone est
 * saisie dans l'admin : WooCommerce la marque `created_via = admin`, et rien
 * ne dit plus d'où elle vient. Ce champ le note, avec en option la pub ou le
 * produit d'origine, pour mesurer la vraie part de chaque canal et son taux
 * de livraison.
 *
 * ── Ce que ce champ ne fait PAS ────────────────────────────────────────────
 * Il ne change rien à ce qui part vers Meta : ni OrderPlaced (toujours
 * réservé aux commandes du site), ni Purchase, ni son `action_source`. C'est
 * une donnée interne, sans effet sur le Pixel ni sur l'apprentissage des
 * campagnes.
 *
 * Une commande passée sur le site (`created_via = checkout`) est « Site »
 * d'office : rien n'est écrit pour elle, le canal est déduit à la lecture.
 * La liste des canaux manuels se règle dans FAMMA → Canaux de commande.
 *
 * @package famma-core
 */

namespace Famma\Core;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Champ « Canal » de la fiche commande, et son décompte au tableau de bord.
 */
final class Order_Channel {

	/** Méta du canal (identifiant court). */
	public const META = '_famma_order_channel';

	/** Méta de la pub ou du produit d'origine (texte libre). */
	public const META_REF = '_famma_order_channel_ref';

	/** Option : canaux manuels, identifiant => libellé. */
	public const OPTION = 'famma_order_channels';

	/** Canal réservé des commandes passées sur le site. */
	public const SITE = 'site';

	/**
	 * Canal réservé : commande créée par le bouton « Créer la commande » des
	 * saisies à relancer. Posé par ce seul bouton, jamais modifiable ensuite.
	 */
	public const RECOVERED = 'panier_recupere';

	/** Nonce de l'encadré. */
	private const NONCE = 'famma_order_channel_nonce';

	/** Longueur maximale du texte « pub ou produit d'origine ». */
	public const REF_MAX = 100;

	/**
	 * Instance partagée.
	 *
	 * @var Order_Channel|null
	 */
	private static ?Order_Channel $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Order_Channel
	 */
	public static function instance(): Order_Channel {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'add_meta_boxes', array( self::$i, 'register' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( self::$i, 'save' ), 50, 2 );
		}
		return self::$i;
	}

	/**
	 * Canaux proposés au premier usage, modifiables ensuite dans l'admin.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		return array(
			'messenger' => __( 'Messenger', 'famma-core' ),
			'whatsapp'  => __( 'WhatsApp', 'famma-core' ),
			'instagram' => __( 'Instagram', 'famma-core' ),
			'telephone' => __( 'Téléphone', 'famma-core' ),
			'autre'     => __( 'Autre', 'famma-core' ),
		);
	}

	/**
	 * Canaux manuels en vigueur (sans « Site »).
	 *
	 * @return array<string, string>
	 */
	public static function channels(): array {
		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) || array() === $saved ) {
			return self::defaults();
		}
		$out = array();
		foreach ( $saved as $slug => $label ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && ! in_array( $slug, array( self::SITE, self::RECOVERED ), true ) && is_scalar( $label ) ) {
				$out[ $slug ] = sanitize_text_field( (string) $label );
			}
		}
		return array() !== $out ? $out : self::defaults();
	}

	/**
	 * Canal d'une commande : « site », un canal manuel, ou '' (à préciser).
	 *
	 * @param \WC_Order $order Commande.
	 * @return string
	 */
	public static function of( \WC_Order $order ): string {
		if ( 'checkout' === $order->get_created_via() ) {
			return self::SITE;
		}
		return sanitize_key( (string) $order->get_meta( self::META ) );
	}

	/**
	 * Libellé lisible d'un canal.
	 *
	 * Un canal retiré de la liste garde son identifiant à l'affichage, pour
	 * que les anciennes commandes restent lisibles.
	 *
	 * @param string $slug Identifiant du canal.
	 * @return string
	 */
	public static function label( string $slug ): string {
		if ( self::SITE === $slug ) {
			return __( 'Site', 'famma-core' );
		}
		if ( self::RECOVERED === $slug ) {
			return __( 'Panier récupéré', 'famma-core' );
		}
		if ( '' === $slug ) {
			return __( 'À préciser', 'famma-core' );
		}
		return self::channels()[ $slug ] ?? $slug;
	}

	/**
	 * Déclare l'encadré sur l'écran de commande en vigueur (HPOS ou ancien).
	 *
	 * @return void
	 */
	public function register(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'famma-order-channel-box', __( 'Canal de la commande', 'famma-core' ), array( $this, 'render' ), $screen, 'side', 'high' );
	}

	/**
	 * Affiche l'encadré.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Objet passé par WordPress.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( self::SITE === self::of( $order ) ) {
			echo '<p>' . esc_html__( 'Site — commande passée sur la boutique, canal noté automatiquement.', 'famma-core' ) . '</p>';
			return;
		}
		if ( self::RECOVERED === self::of( $order ) ) {
			echo '<p>' . esc_html__( 'Panier récupéré — commande créée depuis « Saisies à relancer ».', 'famma-core' ) . '</p>';
			return;
		}
		$current = self::of( $order );
		wp_nonce_field( self::NONCE, self::NONCE );
		echo '<p><label for="famma-order-channel"><strong>' . esc_html__( 'Canal', 'famma-core' ) . '</strong></label><br>';
		echo '<select id="famma-order-channel" name="famma_order_channel" style="width:100%">';
		echo '<option value="">' . esc_html__( '— À préciser —', 'famma-core' ) . '</option>';
		$channels = self::channels();
		if ( '' !== $current && ! isset( $channels[ $current ] ) ) {
			$channels[ $current ] = $current;
		}
		foreach ( $channels as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="famma-order-channel-ref"><strong>' . esc_html__( 'Pub ou produit d’origine (facultatif)', 'famma-core' ) . '</strong></label>';
		echo '<input type="text" id="famma-order-channel-ref" name="famma_order_channel_ref" maxlength="' . esc_attr( (string) self::REF_MAX ) . '" style="width:100%" value="' . esc_attr( (string) $order->get_meta( self::META_REF ) ) . '"></p>';
		echo '<p class="description">' . esc_html__( 'Réponse du client à « Vous nous avez connu comment ? ». Donnée interne : rien n’est envoyé à Meta.', 'famma-core' ) . '</p>';
	}

	/**
	 * Enregistre le canal (nonce, capacité, liste blanche).
	 *
	 * @param int                     $order_id Identifiant de la commande.
	 * @param \WC_Order|\WP_Post|null $subject  Commande (HPOS) ou article (ancien stockage).
	 * @return void
	 */
	public function save( int $order_id, $subject = null ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order = $subject instanceof \WC_Order ? $subject : wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || in_array( self::of( $order ), array( self::SITE, self::RECOVERED ), true ) ) {
			return;
		}

		$channel = isset( $_POST['famma_order_channel'] ) ? sanitize_key( wp_unslash( $_POST['famma_order_channel'] ) ) : '';
		$ref     = isset( $_POST['famma_order_channel_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['famma_order_channel_ref'] ) ) : '';
		$ref     = mb_substr( $ref, 0, self::REF_MAX );

		if ( '' !== $channel && ( isset( self::channels()[ $channel ] ) || (string) $order->get_meta( self::META ) === $channel ) ) {
			$order->update_meta_data( self::META, $channel );
		} elseif ( '' === $channel ) {
			$order->delete_meta_data( self::META );
		}
		if ( '' !== $ref ) {
			$order->update_meta_data( self::META_REF, $ref );
		} else {
			$order->delete_meta_data( self::META_REF );
		}
		$order->save_meta_data();
	}

	/**
	 * Ajoute une commande au décompte par canal du tableau de bord.
	 *
	 * Statuts « perdus » : refusée, retournée, échec de livraison, annulée.
	 *
	 * @param array<string, array<string, int>> $rows  Décompte, modifié en place.
	 * @param \WC_Order                         $order Commande.
	 * @return void
	 */
	public static function tally( array &$rows, \WC_Order $order ): void {
		$slug = self::of( $order );
		$row  = $rows[ $slug ] ?? array(
			'placed'    => 0,
			'delivered' => 0,
			'lost'      => 0,
		);
		++$row['placed'];
		if ( $order->has_status( 'delivered' ) ) {
			++$row['delivered'];
		} elseif ( $order->has_status( array( 'refused', 'returned', 'failed-delivery', 'cancelled' ) ) ) {
			++$row['lost'];
		}
		$rows[ $slug ] = $row;
	}

	/**
	 * Tableau « Ventes par canal » du tableau de bord FAMMA.
	 *
	 * Le taux de livraison ne compte que les commandes clôturées (livrées ou
	 * perdues) : une commande encore en route ne le fausse pas.
	 *
	 * @param array<string, array<string, int>> $rows Décompte par canal.
	 * @return void
	 */
	public static function render_dashboard( array $rows ): void {
		echo '<h2>' . esc_html__( 'Ventes par canal — 30 jours', 'famma-core' ) . '</h2>';
		if ( array() === $rows ) {
			echo '<p class="famma-card__note">' . esc_html__( 'Nothing yet.', 'famma-core' ) . '</p>';
			return;
		}
		uasort(
			$rows,
			static function ( array $a, array $b ): int {
				return $b['placed'] <=> $a['placed'];
			}
		);
		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>' . esc_html__( 'Canal', 'famma-core' ) . '</th><th>' . esc_html__( 'Commandes', 'famma-core' ) . '</th><th>' . esc_html__( 'Livrées', 'famma-core' ) . '</th><th>' . esc_html__( 'Taux de livraison', 'famma-core' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $slug => $row ) {
			$closed = $row['delivered'] + $row['lost'];
			$rate   = $closed > 0 ? round( 100 * $row['delivered'] / $closed ) . ' %' : '—';
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( self::label( (string) $slug ) ),
				esc_html( (string) $row['placed'] ),
				esc_html( (string) $row['delivered'] ),
				esc_html( $rate )
			);
		}
		echo '</tbody></table>';
		echo '<p class="famma-card__note">' . esc_html__( 'Taux de livraison = livrées ÷ (livrées + refusées, retournées, échouées ou annulées). Les commandes encore en cours n’entrent pas dans le taux.', 'famma-core' ) . '</p>';
	}
}
