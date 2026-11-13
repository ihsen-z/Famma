<?php
/**
 * S'inscrire et se connecter avec son numéro — case `acct_phone` (lot 2 de
 * l'espace client, décision D1 : e-mail facultatif).
 *
 * WooCommerce exige un e-mail pour créer un client (`wc_create_new_customer`).
 * Quand le client n'en donne pas, son compte reçoit une adresse **technique**
 * `216XXXXXXXX@tel.famma.invalid` : le domaine `.invalid` est réservé
 * (RFC 2606) et ne peut recevoir aucun message, et `block_mail()` refuse de
 * toute façon tout envoi vers lui (`Account_Phone_Guard`). Cette adresse n'est jamais montrée : elle
 * est vidée au paiement, dans « Mes informations » et dans les données
 * envoyées à Meta.
 *
 * Le numéro (8 chiffres, règle de `Tunisia::normalize_phone()`) est gardé dans
 * la méta `_famma_phone`, unique. La connexion accepte le numéro sous toutes
 * ses formes, ou l'e-mail (`Account_Phone_Login`). Les textes viennent de
 * `Account_Phone_Texts`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Inscription et connexion par numéro.
 */
final class Account_Phone {

	/**
	 * Domaine des adresses techniques (jamais joignable).
	 */
	public const DOMAIN = 'tel.famma.invalid';

	/**
	 * Méta utilisateur : numéro national, 8 chiffres.
	 */
	public const META = '_famma_phone';

	/**
	 * Inscriptions tentées par adresse IP en 10 minutes.
	 */
	private const REG_LIMIT = 6;

	/**
	 * L'inscription en cours a reçu une adresse technique.
	 *
	 * @var bool
	 */
	private static bool $technical = false;

	/**
	 * Pose les crochets. La connexion par numéro (`Account_Phone_Login`) et
	 * les protections (`Account_Phone_Guard`) restent actives case décochée :
	 * les comptes déjà créés par numéro doivent continuer à fonctionner.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'wp_loaded', array( self::class, 'prepare_registration' ), 19 );
		add_filter( 'woocommerce_process_registration_errors', array( self::class, 'registration_errors' ), 10, 4 );
		add_action( 'woocommerce_created_customer', array( self::class, 'created_customer' ), 10, 1 );
		add_filter( 'gettext_woocommerce', array( self::class, 'created_notice' ), 10, 2 );
		add_action( 'woocommerce_register_form_start', array( self::class, 'clear_technical' ), 0 );
		add_filter( 'pre_option_woocommerce_registration_generate_password', array( self::class, 'choose_password' ) );
		add_filter( 'pre_option_woocommerce_registration_generate_username', array( self::class, 'generate_username' ) );
		Account_Phone_Login::hooks();
		Account_Phone_Guard::hooks();
	}

	/**
	 * La case est-elle cochée pour la personne en cours ? Le formulaire vit
	 * dans la présentation de la maquette : les deux cases sont nécessaires.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return New_Look::part( 'acct_phone' ) && New_Look::part( 'acct_look' );
	}

	/**
	 * Adresse technique d'un numéro.
	 *
	 * @param string $phone 8 chiffres.
	 * @return string
	 */
	public static function technical_email( string $phone ): string {
		return '216' . $phone . '@' . self::DOMAIN;
	}

	/**
	 * Une adresse est-elle technique ?
	 *
	 * @param string $email Adresse.
	 * @return bool
	 */
	public static function is_technical( string $email ): bool {
		return str_ends_with( strtolower( trim( $email ) ), '@' . self::DOMAIN );
	}

	/**
	 * L'envoi en cours vient-il de notre formulaire d'inscription ?
	 * Le jeton de WooCommerce est vérifié avant toute lecture des champs.
	 *
	 * @return bool
	 */
	private static function our_registration(): bool {
		if ( ! isset( $_POST['register'], $_POST['famma_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié juste après.
			return false;
		}

		$nonce = isset( $_POST['woocommerce-register-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-register-nonce'] ) ) : '';

		return (bool) wp_verify_nonce( $nonce, 'woocommerce-register' ) && self::active();
	}

	/**
	 * Champ envoyé par notre formulaire, nettoyé.
	 *
	 * @param string $key Nom du champ.
	 * @return string
	 */
	private static function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- appelé après our_registration().
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Avant WooCommerce (priorité 19 contre 20) : sans e-mail, le formulaire
	 * reçoit l'adresse technique du numéro, pour que WooCommerce crée le
	 * compte. Un numéro invalide n'en reçoit pas : `registration_errors()`
	 * l'arrête avec un message clair.
	 *
	 * @return void
	 */
	public static function prepare_registration(): void {
		if ( ! self::our_registration() ) {
			return;
		}

		$phone = Tunisia::normalize_phone( self::posted( 'famma_phone' ) );

		if ( '' === $phone || '' !== sanitize_email( self::posted( 'email' ) ) ) {
			return;
		}

		$_POST['email']  = self::technical_email( $phone );
		self::$technical = true;
	}

	/**
	 * Contrôles de notre formulaire : champ piège, nombre d'essais, numéro,
	 * numéro déjà pris, nom et prénom (deux mots), mot de passe d'au moins 8
	 * caractères — les règles de la maquette.
	 *
	 * @param \WP_Error $errors   Erreurs de WooCommerce.
	 * @param string    $username Identifiant (inutilisé).
	 * @param string    $password Mot de passe.
	 * @param string    $email    E-mail (inutilisé).
	 * @return \WP_Error
	 */
	public static function registration_errors( $errors, $username, $password, $email ) {
		unset( $username, $email );

		if ( ! $errors instanceof \WP_Error || ! self::our_registration() ) {
			return $errors;
		}

		if ( '' !== self::posted( 'famma_hp' ) || Visitor_Context::rate_limited( 'famma_reg_', self::REG_LIMIT ) ) {
			$errors->add( 'famma_reg_blocked', Account_Phone_Texts::text( 'err_later' ) );
			return $errors;
		}

		$raw   = self::posted( 'famma_phone' );
		$phone = Tunisia::normalize_phone( $raw );

		if ( '' === $phone ) {
			$errors->add( 'famma_phone', Account_Phone_Texts::text( strlen( (string) preg_replace( '/\D+/', '', $raw ) ) === 8 ? 'err_tel_tn' : 'err_tel' ) );
		} elseif ( null !== Account_Phone_Login::find_user( $phone ) ) {
			$errors->add( 'famma_phone_taken', Account_Phone_Texts::text( 'err_taken' ) );
		}

		$name = trim( self::posted( 'famma_name' ) );

		if ( mb_strlen( $name ) < 4 || count( (array) preg_split( '/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY ) ) < 2 ) {
			$errors->add( 'famma_name', Account_Phone_Texts::text( 'err_name' ) );
		}

		if ( strlen( (string) $password ) < 8 ) {
			$errors->add( 'famma_pw', Account_Phone_Texts::text( 'err_pw' ) );
		}

		return $errors;
	}

	/**
	 * Compte créé : numéro, nom et, s'ils ont été donnés, gouvernorat et
	 * adresse. Aucun événement de mesure n'est envoyé.
	 *
	 * @param int $customer_id Nouveau client.
	 * @return void
	 */
	public static function created_customer( $customer_id ): void {
		if ( ! self::our_registration() ) {
			return;
		}

		$phone = Tunisia::normalize_phone( self::posted( 'famma_phone' ) );
		$name  = preg_split( '/\s+/u', trim( self::posted( 'famma_name' ) ), 2 );
		$first = (string) ( $name[0] ?? '' );
		$last  = (string) ( $name[1] ?? '' );

		update_user_meta( (int) $customer_id, self::META, $phone );

		$customer = new \WC_Customer( (int) $customer_id );
		$customer->set_first_name( $first );
		$customer->set_last_name( $last );
		$customer->set_display_name( trim( $first . ' ' . $last ) );
		$customer->set_billing_first_name( $first );
		$customer->set_billing_last_name( $last );
		$customer->set_billing_phone( $phone );
		$customer->set_billing_country( 'TN' );

		$gov    = strtoupper( self::posted( 'famma_gov' ) );
		$states = WC()->countries ? WC()->countries->get_states( 'TN' ) : array();

		if ( '' !== $gov && is_array( $states ) && isset( $states[ $gov ] ) ) {
			$customer->set_billing_state( $gov );
		}

		$addr = self::posted( 'famma_addr' );

		if ( '' !== $addr ) {
			$customer->set_billing_address_1( $addr );
		}

		$customer->save();
	}

	/**
	 * Avec une adresse technique, WooCommerce annoncerait « vos identifiants
	 * ont été envoyés par e-mail » : rien ne l'est.
	 *
	 * @param string $translation Texte traduit.
	 * @param string $text        Texte d'origine.
	 * @return string
	 */
	public static function created_notice( $translation, $text ) {
		if ( ! self::$technical ) {
			return $translation;
		}

		if ( 'Your account was created successfully. Your login details have been sent to your email address.' !== $text && 'Your account was created successfully and a password has been sent to your email address.' !== $text ) {
			return $translation;
		}

		$ours = Account_Phone_Texts::text( 'created' );

		return '' !== $ours ? $ours : $translation;
	}

	/**
	 * Formulaire renvoyé avec une erreur : WooCommerce réafficherait
	 * l'adresse technique dans son champ e-mail.
	 *
	 * @return void
	 */
	public static function clear_technical(): void {
		if ( isset( $_POST['email'] ) && self::is_technical( sanitize_text_field( wp_unslash( $_POST['email'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- on efface seulement une valeur posée par prepare_registration().
			$_POST['email'] = '';
		}
	}

	/**
	 * La maquette fait choisir le mot de passe à l'inscription : aucun lien
	 * ne peut partir par e-mail vers un compte sans e-mail.
	 *
	 * @param mixed $value Réglage enregistré (court-circuit).
	 * @return mixed
	 */
	public static function choose_password( $value ) {
		return self::on_register_page() ? 'no' : $value;
	}

	/**
	 * Pas de champ « Identifiant » : WooCommerce le fabrique.
	 *
	 * @param mixed $value Réglage enregistré (court-circuit).
	 * @return mixed
	 */
	public static function generate_username( $value ) {
		return self::on_register_page() ? 'yes' : $value;
	}

	/**
	 * Inscription en cours de traitement, ou page de connexion affichée à un
	 * visiteur, case cochée.
	 *
	 * @return bool
	 */
	private static function on_register_page(): bool {
		if ( self::our_registration() ) {
			return true;
		}

		return ! is_user_logged_in() && did_action( 'wp' ) && function_exists( 'is_account_page' ) && is_account_page() && self::active();
	}
}
