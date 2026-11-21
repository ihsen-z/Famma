<?php
/**
 * Avis par lien WhatsApp — le formulaire côté client.
 *
 * Avec la case `pdp_reviews` cochée :
 * - sans lien, le formulaire d'avis n'est montré qu'aux clients connectés dont
 *   la commande est livrée (réglage « acheteurs certifiés » forcé à oui sur le
 *   front, sans toucher à la valeur enregistrée), et WooCommerce refuse aussi
 *   l'envoi côté serveur ;
 * - avec un lien valable, la fiche montre le formulaire même à 0 avis (le thème
 *   retire l'onglet Avis tant qu'aucun avis n'existe), le prénom est prérempli,
 *   l'e-mail n'est pas demandé, et l'avis reçu est marqué « acheteur certifié ».
 *
 * Tout avis reste en attente de validation par le propriétaire : un auteur sans
 * e-mail n'a jamais d'avis déjà approuvé, WordPress le met donc en modération.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Formulaire d'avis ouvert par le lien.
 */
final class Review_Form {

	/**
	 * Paramètre d'URL de retour après l'envoi.
	 */
	private const THANKS = 'famma_avis_merci';

	/**
	 * Commande du lien utilisé pour l'avis qui vient d'être enregistré.
	 *
	 * @var \WC_Order|null
	 */
	private static ?\WC_Order $used = null;

	/**
	 * Accroche le formulaire, seulement si la case est cochée.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		if ( ! is_admin() ) {
			add_action( 'init', array( self::class, 'register' ) );
		}
	}

	/**
	 * Accroche le formulaire, une fois l'utilisateur connu (la case peut ne
	 * valoir que pour les administrateurs, en aperçu).
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Review_Link::enabled() ) {
			return;
		}

		add_filter( 'pre_option_woocommerce_review_rating_verification_required', array( self::class, 'verified_only' ) );
		add_filter( 'pre_option_require_name_email', array( self::class, 'name_email' ) );
		add_filter( 'woocommerce_product_tabs', array( self::class, 'tab' ), 99 );
		add_filter( 'wp_get_current_commenter', array( self::class, 'commenter' ) );
		add_filter( 'woocommerce_product_review_comment_form_args', array( self::class, 'form_args' ) );
		add_filter( 'comment_form_fields', array( self::class, 'fields' ) );
		add_action( 'pre_comment_on_post', array( self::class, 'check_link' ), 5 );
		add_action( 'comment_post', array( self::class, 'on_review' ), 20 );
		add_filter( 'comment_post_redirect', array( self::class, 'redirect' ), 10, 2 );
		add_action( 'woocommerce_before_single_product', array( self::class, 'notice' ), 5 );
		add_action( 'template_redirect', array( self::class, 'no_cache' ), 1 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
	}

	/**
	 * Produit concerné par la requête : la fiche affichée ou l'avis envoyé.
	 *
	 * @return int
	 */
	private static function product_id(): int {
		// Lecture seule : identifiant du produit visé par l'envoi en cours, revérifié par le lien.
		if ( isset( $_POST['comment_post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return absint( wp_unslash( $_POST['comment_post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$id = get_queried_object_id();

		return $id && 'product' === get_post_type( $id ) ? $id : 0;
	}

	/**
	 * Commande du lien valable pour la requête, ou null.
	 *
	 * @return \WC_Order|null
	 */
	private static function order(): ?\WC_Order {
		$product_id = self::product_id();

		return $product_id ? Review_Link::from_request( $product_id ) : null;
	}

	/**
	 * Avis réservés aux acheteurs livrés, sauf avec un lien valable.
	 *
	 * @param mixed $pre Valeur courte-circuitée.
	 * @return string
	 */
	public static function verified_only( $pre ): string {
		unset( $pre );

		return self::order() ? 'no' : 'yes';
	}

	/**
	 * Avec un lien valable, ni nom ni e-mail ne sont exigés.
	 *
	 * @param mixed $pre Valeur courte-circuitée.
	 * @return mixed
	 */
	public static function name_email( $pre ) {
		return self::order() ? '0' : $pre;
	}

	/**
	 * Remet l'onglet des avis, que le thème retire à 0 avis, pour le lien.
	 *
	 * @param mixed $tabs Onglets.
	 * @return mixed
	 */
	public static function tab( $tabs ) {
		if ( ! is_array( $tabs ) || isset( $tabs['reviews'] ) || ! self::order() ) {
			return $tabs;
		}

		$tabs['reviews'] = array(
			'title'    => Review_Texts::text( 'rv_title' ),
			'priority' => 20,
			'callback' => 'comments_template',
		);

		return $tabs;
	}

	/**
	 * Prénom de la commande prérempli dans le formulaire.
	 *
	 * @param mixed $commenter Auteur mémorisé par WordPress.
	 * @return mixed
	 */
	public static function commenter( $commenter ) {
		$order = self::order();

		if ( $order && is_array( $commenter ) && '' === (string) ( $commenter['comment_author'] ?? '' ) ) {
			$commenter['comment_author'] = trim( $order->get_billing_first_name() );
		}

		return $commenter;
	}

	/**
	 * Formulaire du lien : sans e-mail, avec le lien en champ caché.
	 *
	 * @param mixed $args Arguments de `comment_form()`.
	 * @return mixed
	 */
	public static function form_args( $args ) {
		$order = self::order();

		if ( ! $order || ! is_array( $args ) ) {
			return $args;
		}

		unset( $args['fields']['email'] );

		$title = Review_Texts::text( 'rv_title' );
		$hello = Review_Texts::text( 'rv_hello' );

		if ( '' !== $title ) {
			$args['title_reply'] = esc_html( $title );
		}

		$args['comment_notes_before'] = '' === $hello ? '' : '<p class="comment-notes">' . esc_html( $hello ) . '</p>';
		$args['submit_field']         = '<input type="hidden" name="' . esc_attr( Review_Link::QUERY ) . '" value="' . esc_attr( Review_Link::value( $order ) ) . '" />' . ( $args['submit_field'] ?? '<p class="form-submit">%1$s %2$s</p>' );

		return $args;
	}

	/**
	 * Sans e-mail, la case « Enregistrer mon nom, mon e-mail… » n'a pas de sens.
	 *
	 * @param mixed $fields Champs du formulaire.
	 * @return mixed
	 */
	public static function fields( $fields ) {
		if ( is_array( $fields ) && self::order() ) {
			unset( $fields['cookies'], $fields['email'] );
		}

		return $fields;
	}

	/**
	 * Un envoi qui porte un lien non valable est refusé.
	 *
	 * @param int $post_id Produit visé.
	 * @return void
	 */
	public static function check_link( $post_id ): void {
		if ( 'product' !== get_post_type( (int) $post_id ) || ! Review_Link::in_request() || self::order() ) {
			return;
		}

		wp_die(
			esc_html( Review_Texts::text( 'rv_invalid' ) ),
			'',
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}

	/**
	 * Avis enregistré par le lien : acheteur certifié, lien consommé.
	 *
	 * Passe après WooCommerce, qui ne peut pas rattacher un auteur sans e-mail
	 * à sa commande.
	 *
	 * @param int $comment_id Avis.
	 * @return void
	 */
	public static function on_review( $comment_id ): void {
		$comment = get_comment( (int) $comment_id );
		$order   = self::order();

		if ( ! $comment instanceof \WP_Comment || ! $order || self::product_id() !== (int) $comment->comment_post_ID ) {
			return;
		}

		update_comment_meta( $comment->comment_ID, 'verified', 1 );
		update_comment_meta( $comment->comment_ID, '_famma_review_order', $order->get_id() );
		Review_Link::mark_done( $order, (int) $comment->comment_post_ID );
		/* translators: %s: nom du produit. */
		$order->add_order_note( sprintf( __( 'Avis client reçu par le lien WhatsApp pour « %s », en attente de validation dans Produits → Avis.', 'famma-core' ), get_the_title( (int) $comment->comment_post_ID ) ) );
		self::$used = $order;
	}

	/**
	 * Après l'envoi : retour sur la fiche avec le remerciement.
	 *
	 * @param string      $location Adresse prévue par WordPress.
	 * @param \WP_Comment $comment  Avis.
	 * @return string
	 */
	public static function redirect( $location, $comment ) {
		if ( ! self::$used || ! $comment instanceof \WP_Comment ) {
			return $location;
		}

		return add_query_arg( self::THANKS, '1', (string) get_permalink( (int) $comment->comment_post_ID ) ) . '#reviews';
	}

	/**
	 * Message en haut de la fiche : merci, ou lien plus valable.
	 *
	 * @return void
	 */
	public static function notice(): void {
		$product_id = self::product_id();

		if ( isset( $_GET[ self::THANKS ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$text  = Review_Texts::text( 'rv_thanks' );
			$class = 'woocommerce-message';
		} elseif ( $product_id && Review_Link::in_request() && ! self::order() ) {
			$text  = Review_Texts::text( 'rv_invalid' );
			$class = 'woocommerce-info';
		} else {
			return;
		}

		if ( '' !== $text ) {
			echo '<div class="woocommerce-notices-wrapper"><div class="' . esc_attr( $class ) . '" role="status">' . esc_html( $text ) . '</div></div>';
		}
	}

	/**
	 * Une page ouverte par un lien n'est jamais mise en cache.
	 *
	 * @return void
	 */
	public static function no_cache(): void {
		if ( ! Review_Link::in_request() && ! isset( $_GET[ self::THANKS ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- constante reconnue par les extensions de cache.
		}

		do_action( 'litespeed_control_set_nocache', 'famma: lien d’avis' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- action de LiteSpeed Cache.
		nocache_headers();
	}

	/**
	 * Une page ouverte par un lien n'est pas indexée.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( Review_Link::in_request() || isset( $_GET[ self::THANKS ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}
}
