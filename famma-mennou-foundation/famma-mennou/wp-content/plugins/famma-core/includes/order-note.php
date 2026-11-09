<?php
/**
 * Remarque interne d'une commande — colonne de la liste et encadré.
 *
 * Une note libre par commande (« rappeler après 18 h », « client hésite sur
 * la taille »…), visible dans la liste WooCommerce → Commandes et modifiable
 * sur place, sans ouvrir la commande. La même remarque s'édite dans
 * l'encadré « Remarque » de la fiche commande.
 *
 * Distincte de la « note du client » WooCommerce (écrite par le client) et
 * des notes de commande (historique horodaté) : ici, une seule valeur
 * courante, écrite par l'équipe. Donnée interne : rien ne part vers Meta.
 *
 * Écriture : nonce, puis `manage_woocommerce`, puis texte assaini (règle 4).
 *
 * @package famma-core
 */

namespace Famma\Core;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Colonne « Remarque », édition en ligne et encadré de la fiche commande.
 */
final class Order_Note {

	/** Méta de la remarque. */
	public const META = '_famma_order_note';

	/** Longueur maximale de la remarque. */
	public const MAX = 500;

	/** Action AJAX d'enregistrement depuis la liste. */
	public const AJAX = 'famma_order_note';

	/** Nonce de l'encadré de la fiche commande. */
	private const NONCE = 'famma_order_note_nonce';

	/**
	 * Instance partagée.
	 *
	 * @var Order_Note|null
	 */
	private static ?Order_Note $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Order_Note
	 */
	public static function instance(): Order_Note {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Crochets : colonne (HPOS et ancien stockage), AJAX, encadré, scripts.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_save' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_box' ), 50, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Assainit une remarque : texte sur plusieurs lignes, longueur bornée.
	 *
	 * @param string $raw Texte reçu (déjà `wp_unslash()`).
	 * @return string
	 */
	public static function clean( string $raw ): string {
		return mb_substr( trim( sanitize_textarea_field( $raw ) ), 0, self::MAX );
	}

	/**
	 * Écrit (ou efface, si vide) la remarque d'une commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @param string    $note  Remarque assainie.
	 * @return void
	 */
	public static function write( \WC_Order $order, string $note ): void {
		if ( '' === $note ) {
			$order->delete_meta_data( self::META );
		} else {
			$order->update_meta_data( self::META, $note );
		}
	}

	/**
	 * Ajoute la colonne « Remarque » en fin de liste, avant les actions.
	 *
	 * @param array<string, string> $columns Colonnes de la liste.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'wc_actions' === $key ) {
				$out['famma_note'] = __( 'Remarque', 'famma-core' );
			}
			$out[ $key ] = $label;
		}
		if ( ! isset( $out['famma_note'] ) ) {
			$out['famma_note'] = __( 'Remarque', 'famma-core' );
		}
		return $out;
	}

	/**
	 * Cellule « Remarque » : le texte et un bouton pour le modifier.
	 *
	 * La classe `no-link` empêche WooCommerce d'ouvrir la commande au clic.
	 *
	 * @param string                 $column Colonne en cours.
	 * @param \WC_Order|int|\WP_Post $order  Commande (HPOS) ou identifiant (ancien stockage).
	 * @return void
	 */
	public function render_column( string $column, $order ): void {
		if ( 'famma_note' !== $column ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order instanceof \WP_Post ? $order->ID : (int) $order );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$note = (string) $order->get_meta( self::META );
		printf(
			'<div class="famma-note no-link" data-order="%1$d"><span class="famma-note__text">%2$s</span> <button type="button" class="button-link famma-note__edit" aria-label="%3$s">%4$s</button></div>',
			(int) $order->get_id(),
			esc_html( $note ),
			esc_attr__( 'Modifier la remarque', 'famma-core' ),
			'' === $note ? esc_html__( '+ Ajouter', 'famma-core' ) : esc_html__( 'Modifier', 'famma-core' )
		);
	}

	/**
	 * Enregistre la remarque envoyée depuis la liste (nonce, droit, texte).
	 *
	 * @return void
	 */
	public function ajax_save(): void {
		check_ajax_referer( self::AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'famma-core' ) ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$note     = isset( $_POST['note'] ) ? self::clean( (string) wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by clean().
		$order    = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Commande introuvable.', 'famma-core' ) ), 404 );
		}
		self::write( $order, $note );
		$order->save_meta_data();
		wp_send_json_success( array( 'note' => $note ) );
	}

	/**
	 * Déclare l'encadré sur l'écran de commande en vigueur (HPOS ou ancien).
	 *
	 * @return void
	 */
	public function register_box(): void {
		$screen = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
		add_meta_box( 'famma-order-note-box', __( 'Remarque', 'famma-core' ), array( $this, 'render_box' ), $screen, 'side', 'high' );
	}

	/**
	 * Affiche l'encadré de la fiche commande.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Objet passé par WordPress.
	 * @return void
	 */
	public function render_box( $post_or_order ): void {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_nonce_field( self::NONCE, self::NONCE );
		printf(
			'<textarea name="famma_order_note" rows="4" maxlength="%1$d" style="width:100%%" aria-label="%2$s">%3$s</textarea><p class="description">%4$s</p>',
			(int) self::MAX,
			esc_attr__( 'Remarque', 'famma-core' ),
			esc_textarea( (string) $order->get_meta( self::META ) ),
			esc_html__( 'Note interne de l’équipe, visible dans la liste des commandes. Le client ne la voit pas.', 'famma-core' )
		);
	}

	/**
	 * Enregistre la remarque de l'encadré (nonce, droit, texte).
	 *
	 * @param int                     $order_id Identifiant de la commande.
	 * @param \WC_Order|\WP_Post|null $subject  Commande (HPOS) ou article (ancien stockage).
	 * @return void
	 */
	public function save_box( int $order_id, $subject = null ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order = $subject instanceof \WC_Order ? $subject : wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$note = isset( $_POST['famma_order_note'] ) ? self::clean( (string) wp_unslash( $_POST['famma_order_note'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by clean().
		self::write( $order, $note );
		$order->save_meta_data();
	}

	/**
	 * Script et styles de l'édition en ligne, sur la liste des commandes seulement.
	 *
	 * @param string $hook Page d'administration courante.
	 * @return void
	 */
	public function assets( string $hook ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// Le formulaire de filtre de la liste renvoie `action=-1` : seule la fiche (edit/new) est exclue.
		$action  = isset( $_GET['action'] ) && is_scalar( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$is_hpos = 'woocommerce_page_wc-orders' === $hook && ! in_array( $action, array( 'edit', 'new' ), true );
		$is_post = 'edit.php' === $hook && $screen instanceof \WP_Screen && 'shop_order' === $screen->post_type;
		if ( ! $is_hpos && ! $is_post ) {
			return;
		}
		wp_enqueue_style( 'famma-order-note', plugins_url( 'assets/css/order-note.css', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION );
		wp_enqueue_script( 'famma-order-note', plugins_url( 'assets/js/order-note.js', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION, true );
		wp_localize_script(
			'famma-order-note',
			'fammaOrderNote',
			array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'action' => self::AJAX,
				'nonce'  => wp_create_nonce( self::AJAX ),
				'max'    => self::MAX,
				'i18n'   => array(
					'save'   => __( 'Enregistrer', 'famma-core' ),
					'cancel' => __( 'Annuler', 'famma-core' ),
					'add'    => __( '+ Ajouter', 'famma-core' ),
					'edit'   => __( 'Modifier', 'famma-core' ),
					'saving' => __( 'Enregistrement…', 'famma-core' ),
					'error'  => __( 'Échec de l’enregistrement, réessayez.', 'famma-core' ),
				),
			)
		);
	}
}
