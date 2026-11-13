<?php
/**
 * En-tête de « Mon compte » à la manière de la maquette — case `acct_look`.
 *
 * Carte marine avec les initiales, le nom, le téléphone et l'ancienneté du
 * client, l'anneau « profil complété », les rappels, les compteurs et les
 * libellés des onglets. Ce fichier calcule ; le thème dessine
 * (inc/new-look-account.php).
 *
 * L'anneau suit la règle du plan de portage : 50 % pour le compte, +30 % avec
 * une adresse, +20 % avec un e-mail. Rien n'est inventé : chaque part se lit
 * dans la fiche client WooCommerce.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Données et textes de l'en-tête du compte.
 */
final class Account_Profile {

	/**
	 * Clé de page, au sens de `Pages_Content` (même section que le suivi).
	 */
	public const PAGE = 'account';

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
	 * Schéma des champs de l'en-tête et des onglets.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		return array(
			self::field( 'crumb', __( 'Fil d’Ariane — Mon compte', 'famma-core' ), __( 'Mon compte', 'famma-core' ), 'حسابي', __( 'Dernier mot du fil d’Ariane « Accueil › Mon compte ». Vide : le titre de la page.', 'famma-core' ) ),
			self::field( 'head_hello', __( 'En-tête — salutation', 'famma-core' ), __( 'Bonjour', 'famma-core' ), 'عسلامة', __( 'Carte marine en haut de « Mon compte », avec la case « Mon compte : présentation de la maquette ».', 'famma-core' ) ),
			self::field( 'head_since', __( 'En-tête — ancienneté', 'famma-core' ), __( 'Membre depuis {m}', 'famma-core' ), 'عضو من {m}', __( '{m} est remplacé par le mois et l’année de création du compte. Vide : la ligne disparaît.', 'famma-core' ) ),
			self::field( 'head_addr', __( 'En-tête — rappel adresse', 'famma-core' ), __( 'Ajoutez une adresse', 'famma-core' ), 'زيد عنوان', __( 'Affiché tant que le client n’a pas d’adresse (+30 %). Vide : le rappel disparaît.', 'famma-core' ) ),
			self::field( 'head_mail', __( 'En-tête — rappel e-mail', 'famma-core' ), __( 'Ajoutez un e-mail', 'famma-core' ), 'زيد إيميل', __( 'Affiché tant que le client n’a pas d’e-mail (+20 %). Vide : le rappel disparaît.', 'famma-core' ) ),
			self::field( 'head_done', __( 'En-tête — profil complet', 'famma-core' ), __( 'Profil complet', 'famma-core' ), 'الحساب كامل' ),
			self::field( 'stat_orders', __( 'Compteur — commandes', 'famma-core' ), __( 'commandes', 'famma-core' ), 'طلبيات' ),
			self::field( 'stat_open', __( 'Compteur — en cours', 'famma-core' ), __( 'en route', 'famma-core' ), 'في الثنية' ),
			self::field( 'stat_favs', __( 'Compteur — sélection', 'famma-core' ), __( 'dans ma sélection', 'famma-core' ), 'في اختياراتي', __( 'Nombre de favoris gardés dans ce navigateur. Vide : le compteur disparaît.', 'famma-core' ) ),
			self::field( 'tab_orders', __( 'Onglet — commandes', 'famma-core' ), __( 'Mes commandes', 'famma-core' ), 'طلبياتي' ),
			self::field( 'tab_addresses', __( 'Onglet — adresses', 'famma-core' ), __( 'Mes adresses', 'famma-core' ), 'عناويني' ),
			self::field( 'tab_infos', __( 'Onglet — informations', 'famma-core' ), __( 'Mes informations', 'famma-core' ), 'معلوماتي' ),
			self::field( 'logout', __( 'Bouton de déconnexion', 'famma-core' ), __( 'Me déconnecter', 'famma-core' ), 'اخرج', __( 'Dans « Mes informations » : la déconnexion quitte la barre d’onglets, comme dans la maquette.', 'famma-core' ) ),
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
	 * Initiales d'un nom (deux au plus).
	 *
	 * @param string $name Nom affiché.
	 * @return string
	 */
	private static function initials( string $name ): string {
		$words = preg_split( '/\s+/u', trim( $name ) );
		$words = is_array( $words ) ? array_values( array_filter( $words ) ) : array();
		$out   = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$out .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}

		return $out;
	}

	/**
	 * Tout ce que l'en-tête affiche pour un client.
	 *
	 * @param \WP_User $user Client connecté.
	 * @return array<string, mixed>
	 */
	public static function data( \WP_User $user ): array {
		$customer = new \WC_Customer( $user->ID );

		$first = $customer->get_first_name() ? $customer->get_first_name() : $customer->get_billing_first_name();
		$last  = $customer->get_last_name() ? $customer->get_last_name() : $customer->get_billing_last_name();
		$name  = trim( $first . ' ' . $last );
		$name  = '' !== $name ? $name : (string) $user->display_name;

		$has_addr = '' !== trim( (string) $customer->get_billing_address_1() ) || '' !== trim( (string) $customer->get_billing_state() );
		$mail     = $customer->get_billing_email() ? $customer->get_billing_email() : (string) $user->user_email;
		$has_mail = is_email( $mail ) && ! Account_Phone::is_technical( $mail );

		$since_tpl = self::text( 'head_since' );
		$since     = '' !== $since_tpl ? str_replace( '{m}', wp_date( 'F Y', (int) strtotime( $user->user_registered . ' UTC' ) ), $since_tpl ) : '';

		$nudges = array();
		if ( ! $has_addr && '' !== self::text( 'head_addr' ) ) {
			$nudges[] = array(
				'label' => self::text( 'head_addr' ),
				'gain'  => '+30 %',
				'url'   => wc_get_account_endpoint_url( 'edit-address' ),
			);
		}
		if ( ! $has_mail && '' !== self::text( 'head_mail' ) ) {
			$nudges[] = array(
				'label' => self::text( 'head_mail' ),
				'gain'  => '+20 %',
				'url'   => wc_get_account_endpoint_url( 'edit-account' ),
			);
		}

		return array(
			'name'     => $name,
			'initials' => self::initials( $name ),
			'hello'    => self::text( 'head_hello' ),
			'phone'    => (string) $customer->get_billing_phone(),
			'since'    => $since,
			'pct'      => 50 + ( $has_addr ? 30 : 0 ) + ( $has_mail ? 20 : 0 ),
			'nudges'   => $nudges,
			'done'     => array() === $nudges ? self::text( 'head_done' ) : '',
			'orders'   => self::count_orders( $user->ID, array() ),
			'open'     => self::count_orders( $user->ID, Order_Track::open_statuses() ),
			'addrs'    => self::count_addresses( $user->ID, $has_addr ),
		);
	}

	/**
	 * Nombre de commandes d'un client, par statut (tous si vide).
	 *
	 * Requête limitée aux identifiants, compatible HPOS.
	 *
	 * @param int                $user_id  Client.
	 * @param array<int, string> $statuses Statuts sans préfixe.
	 * @return int
	 */
	private static function count_orders( int $user_id, array $statuses ): int {
		$args = array(
			'customer' => $user_id,
		);

		if ( array() !== $statuses ) {
			$args['status'] = $statuses;
		} else {
			// Même filtre que la liste « Mes commandes » : WooCommerce y retire
			// les brouillons de commande (checkout-draft), le compteur aussi.
			$args = (array) apply_filters( 'woocommerce_my_account_my_orders_query', $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			unset( $args['page'], $args['paginate'] );
		}

		$args['customer'] = $user_id;
		$args['return']   = 'ids';
		$args['limit']    = 500;

		return count( (array) wc_get_orders( $args ) );
	}

	/**
	 * Nombre d'adresses d'un client : la liste « plusieurs adresses » quand
	 * elle est servie, sinon l'adresse de commande (0 ou 1).
	 *
	 * @param int  $user_id  Client.
	 * @param bool $has_addr Adresse de commande remplie.
	 * @return int
	 */
	private static function count_addresses( int $user_id, bool $has_addr ): int {
		if ( Account_Addresses::active() ) {
			return count( Account_Addresses::all( $user_id ) );
		}

		return $has_addr ? 1 : 0;
	}

	/**
	 * Numéro de téléphone tunisien groupé (« 56 261 412 »).
	 *
	 * @param string $phone Numéro saisi.
	 * @return string
	 */
	public static function format_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		$digits = is_string( $digits ) ? $digits : '';

		if ( 11 === strlen( $digits ) && 0 === strpos( $digits, '216' ) ) {
			$digits = substr( $digits, 3 );
		}

		if ( 8 !== strlen( $digits ) ) {
			return trim( $phone );
		}

		return '+216 ' . substr( $digits, 0, 2 ) . ' ' . substr( $digits, 2, 3 ) . ' ' . substr( $digits, 5, 3 );
	}
}
