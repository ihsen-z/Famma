<?php
/**
 * Case « Doublon serveur des événements navigateur » de l'écran « Mesure » (lot 59, Q21).
 *
 * Activée par défaut (décision du propriétaire du 26/11). Décochée : aucun
 * relais n'est imprimé dans les pages, la route ne range plus rien et la file
 * en attente est vidée au prochain passage, sans rien envoyer. L'accesseur vit
 * dans Config (`meta_browser_mirror()`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Réglage du doublon serveur.
 */
final class Capi_Mirror_Settings {

	/**
	 * Option enregistrée.
	 */
	public const OPTION = 'famma_meta_browser_mirror';

	/**
	 * Section « Réglages avancés Meta » (`Measurement_Settings`).
	 */
	private const SECTION = 'famma_section_meta_advanced';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Mirror_Settings|null
	 */
	private static ?Capi_Mirror_Settings $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Mirror_Settings
	 */
	public static function instance(): Capi_Mirror_Settings {
		if ( null === self::$i ) {
			self::$i = new self();
			// Après `Measurement_Settings` (11), qui déclare la section.
			add_action( 'admin_init', array( self::$i, 'register' ), 12 );
		}
		return self::$i;
	}

	/**
	 * Enregistre l'option et son champ.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			Tracking_Settings::GROUP,
			self::OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		add_settings_field( self::OPTION, __( 'Doublon serveur des événements navigateur', 'famma-core' ), array( $this, 'render' ), Tracking_Settings::SLUG, self::SECTION );
	}

	/**
	 * Case à cocher.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>" value="1" <?php checked( Config::instance()->meta_browser_mirror() ); ?> />
			<?php esc_html_e( 'Envoyer aussi par la CAPI PageView, ViewContent, AddToCart et InitiateCheckout, avec le même identifiant que le Pixel.', 'famma-core' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Meta garde un seul exemplaire de chaque événement. Envoi groupé toutes les 1 à 5 minutes, sans ralentir les pages. Après un changement, videz le cache de pages.', 'famma-core' ); ?></p>
		<?php
	}
}
