<?php
/**
 * Cases « Meta — paniers récupérés (OrderPlaced) » (lot 7 du pixel) et
 * « Meta — commandes saisies à la main (OrderPlaced) » (L6, Q24) de l'écran FAMMA → Mesure.
 *
 * Même formulaire et même groupe que les autres interrupteurs Meta
 * (`Tracking_Settings`, API Réglages : nonce et `manage_options` vérifiés par
 * options.php, valeur passée par `rest_sanitize_boolean`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Réglages de l'OrderPlaced des paniers récupérés et des commandes saisies à la main.
 */
final class Capi_Recovered_Cart_Admin {

	/**
	 * Déclare l'option et sa case (`admin_init`, après `Tracking_Settings`).
	 *
	 * @return void
	 */
	public static function register_setting(): void {
		register_setting(
			Tracking_Settings::GROUP,
			Capi_Recovered_Cart::OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		add_settings_field(
			Capi_Recovered_Cart::OPTION,
			__( 'Meta — paniers récupérés (OrderPlaced)', 'famma-core' ),
			array( self::class, 'render_toggle' ),
			Tracking_Settings::SLUG,
			'famma_section_tracking'
		);
		register_setting(
			Tracking_Settings::GROUP,
			Capi_Manual_Order::OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		add_settings_field(
			Capi_Manual_Order::OPTION,
			__( 'Meta — commandes saisies à la main (OrderPlaced)', 'famma-core' ),
			array( self::class, 'render_manual_toggle' ),
			Tracking_Settings::SLUG,
			'famma_section_tracking'
		);
	}

	/**
	 * Affiche la case des commandes saisies à la main (L6).
	 *
	 * @return void
	 */
	public static function render_manual_toggle(): void {
		$minutes = intdiv( Capi_Recovered_Cart::delay(), 60 );
		?>
		<input type="hidden" name="<?php echo esc_attr( Capi_Manual_Order::OPTION ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( Capi_Manual_Order::OPTION ); ?>" value="1" <?php checked( Capi_Manual_Order::enabled() ); ?> />
			<?php esc_html_e( 'Envoyer aussi OrderPlaced pour une commande saisie dans l’écran de commande (Messenger, WhatsApp, téléphone…).', 'famma-core' ); ?>
		</label>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: delay in minutes before the event leaves. */
					__( 'Activé par défaut ; sans effet si OrderPlaced est désactivé ci-dessus. L’événement part %d minutes après l’enregistrement, tout étant revérifié au départ (case « Commande d’essai », statut, article). Source « messagerie » pour Messenger, WhatsApp et Instagram, sinon « appel téléphonique ». Les commandes créées par l’application ou l’API ne sont pas concernées.', 'famma-core' ),
					$minutes
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Affiche la case.
	 *
	 * @return void
	 */
	public static function render_toggle(): void {
		$minutes = intdiv( Capi_Recovered_Cart::delay(), 60 );
		?>
		<input type="hidden" name="<?php echo esc_attr( Capi_Recovered_Cart::OPTION ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( Capi_Recovered_Cart::OPTION ); ?>" value="1" <?php checked( Capi_Recovered_Cart::enabled() ); ?> />
			<?php esc_html_e( 'Envoyer aussi OrderPlaced pour une commande créée depuis « Saisies à relancer » (canal Panier récupéré).', 'famma-core' ); ?>
		</label>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: delay in minutes before the event leaves. */
					__( 'Activé par défaut ; sans effet si OrderPlaced est désactivé ci-dessus. L’événement part %d minutes après la création (source « appel téléphonique »), le temps de cocher « Commande d’essai » ; tout est revérifié au départ. Les identifiants de la visite d’origine (cookies Meta, IP du journal) sont recopiés sur la commande. Les autres commandes saisies à la main n’envoient pas OrderPlaced ; une commande d’essai n’envoie rien.', 'famma-core' ),
					$minutes
				)
			);
			?>
		</p>
		<?php
	}
}
