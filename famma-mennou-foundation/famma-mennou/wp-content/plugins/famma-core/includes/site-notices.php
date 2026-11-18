<?php
/**
 * Messages du site au style FAMMA (brique `notices`) : contenu.
 *
 * Le thème habille les messages (carte, couleurs, erreur sous le champ,
 * bandeau qui se ferme seul) ; ce fichier n'en change que le texte :
 * - erreurs de la commande : phrases de `Notice_Texts` dans la langue de la
 *   page, à la place de celles de WooCommerce et de famma-core (deux
 *   langues, « de facturation »). Chaque erreur garde son code et son champ
 *   (`id`), que le script de commande de WooCommerce lit pour l'afficher
 *   sous le champ ;
 * - « … a été ajouté à votre panier » : bandeau court avec le nom du produit
 *   et un lien vers le panier, jamais montré sur le panier lui-même ;
 * - tout autre message écrit en deux langues (quantité limitée…) : seulement
 *   la langue de la page.
 *
 * Brique décochée : rien ne change.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Textes des messages du site.
 */
final class Site_Notices {

	/**
	 * Champ de commande (suffixe de la clé WooCommerce) → texte « manquant ».
	 *
	 * @var array<string, string>
	 */
	private const REQUIRED = array(
		'first_name' => 'err_name',
		'phone'      => 'err_phone',
		'state'      => 'err_state',
		'address_1'  => 'err_address',
		'city'       => 'err_city',
		'email'      => 'err_email_account',
	);

	/**
	 * Code d'erreur précis → texte.
	 *
	 * @var array<string, string>
	 */
	private const CODES = array(
		'billing_first_name_short' => 'err_name_short',
		'billing_phone_invalid'    => 'err_phone_bad',
		'billing_phone_validation' => 'err_phone_bad',
		'billing_email_validation' => 'err_email_bad',
		'famma_account_email'      => 'err_email_account',
		'terms'                    => 'err_terms',
	);

	/**
	 * Branche les filtres.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		// Après les contrôles de famma-core (5 à 20), avant l'idempotence (PHP_INT_MAX).
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'rewrite' ), 1000, 2 );
		add_filter( 'wc_add_to_cart_message_html', array( self::class, 'added' ), 20, 2 );
		add_action( 'woocommerce_before_cart', array( self::class, 'drop_added' ), 1 );

		foreach ( array( 'error', 'success', 'notice' ) as $type ) {
			add_filter( 'woocommerce_add_' . $type, array( self::class, 'one_language' ), 20 );
		}
	}

	/**
	 * La brique est-elle servie à la requête en cours ?
	 *
	 * @return bool
	 */
	public static function on(): bool {
		return New_Look::part( 'notices' );
	}

	/**
	 * Réécrit les erreurs de la commande dans la langue de la page.
	 *
	 * @param array<string, mixed> $data   Données postées (inutilisées).
	 * @param \WP_Error            $errors Erreurs de la commande.
	 * @return void
	 */
	public static function rewrite( array $data, \WP_Error $errors ): void {
		unset( $data );

		if ( ! self::on() ) {
			return;
		}

		foreach ( $errors->get_error_codes() as $code ) {
			$code  = (string) $code;
			$extra = $errors->get_error_data( $code );
			$extra = is_array( $extra ) ? $extra : array();
			$field = isset( $extra['id'] ) ? (string) $extra['id'] : '';
			$text  = Notice_Texts::text( self::key( $code, $field ) );

			if ( '' === $text ) {
				continue;
			}

			// L'erreur du compte sans e-mail n'a pas de champ : elle va sous l'e-mail.
			if ( 'famma_account_email' === $code ) {
				$extra['id'] = 'billing_email';
			}

			$errors->remove( $code );
			$errors->add( $code, esc_html( $text ), $extra );
		}
	}

	/**
	 * Message en deux langues (« Phrase française. جملة بالدارجة. ») : ne
	 * garde que celle de la page.
	 *
	 * Seulement une phrase française terminée (. ! ?) suivie d'une phrase en
	 * arabe, comme les messages de famma-core (quantité limitée, commande
	 * refusée…) : un nom de produit en arabe dans une phrase française reste.
	 *
	 * @param mixed $message Message.
	 * @return mixed
	 */
	public static function one_language( $message ) {
		if ( ! is_string( $message ) || ! self::on() || str_contains( $message, 'famma-added' ) ) {
			return $message;
		}

		if ( 1 !== preg_match( '/^(.*?\p{Latin}.*?[.!?])\s*(?:\/\s*)?(\p{Arabic}.*)$/us', $message, $parts ) ) {
			return $message;
		}

		return trim( is_rtl() ? $parts[2] : $parts[1] );
	}

	/**
	 * Clé de texte d'une erreur.
	 *
	 * @param string $code  Code de l'erreur.
	 * @param string $field Champ visé (`billing_phone`…), vide s'il n'y en a pas.
	 * @return string Chaîne vide : phrase d'origine gardée.
	 */
	public static function key( string $code, string $field ): string {
		if ( isset( self::CODES[ $code ] ) ) {
			return self::CODES[ $code ];
		}

		if ( '' === $field || ! str_ends_with( $code, '_required' ) ) {
			return '';
		}

		$suffix = (string) preg_replace( '/^(billing|shipping)_/', '', $field );

		return self::REQUIRED[ $suffix ] ?? 'err_field';
	}

	/**
	 * « Ajouté au panier : {p} » et le lien « Voir le panier ».
	 *
	 * @param string            $message  Message de WooCommerce.
	 * @param array<int, mixed> $products Produits ajoutés (id => quantité).
	 * @return string
	 */
	public static function added( $message, $products ): string {
		$message = (string) $message;
		$text    = self::on() ? Notice_Texts::text( 'added' ) : '';

		if ( '' === $text || ! is_array( $products ) || ! function_exists( 'wc_get_cart_url' ) ) {
			return $message;
		}

		$names = array();
		foreach ( array_keys( $products ) as $id ) {
			$names[] = wp_strip_all_tags( get_the_title( (int) $id ) );
		}

		$link = Notice_Texts::text( 'added_link' );
		$html = '<span class="famma-added">' . esc_html( str_replace( '{p}', implode( ', ', array_filter( $names ) ), $text ) ) . '</span>';

		if ( '' !== $link ) {
			$html .= ' <a href="' . esc_url( wc_get_cart_url() ) . '" class="famma-added__link">' . esc_html( $link ) . '</a>';
		}

		return $html;
	}

	/**
	 * Retire le bandeau « Ajouté au panier » sur le panier : on y est déjà.
	 *
	 * @return void
	 */
	public static function drop_added(): void {
		if ( ! self::on() || ! function_exists( 'wc_get_notices' ) ) {
			return;
		}

		$notices = wc_get_notices();
		if ( empty( $notices['success'] ) || ! is_array( $notices['success'] ) ) {
			return;
		}

		$notices['success'] = array_values(
			array_filter(
				$notices['success'],
				static function ( $notice ): bool {
					$text = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;

					return ! str_contains( $text, 'class="famma-added"' );
				}
			)
		);

		wc_set_notices( $notices );
	}

	/**
	 * Textes du script des messages (thème), dans la langue de la page.
	 *
	 * @return array<string, string>
	 */
	public static function strings(): array {
		$out = array();

		foreach ( Notice_Texts::schema() as $field ) {
			$out[ (string) $field['key'] ] = Notice_Texts::text( (string) $field['key'] );
		}

		return $out;
	}
}
