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
	 * Case à cocher et compteurs des 7 derniers jours (N5 : le bruit n'est visible qu'ici).
	 *
	 * @return void
	 */
	public function render(): void {
		$week   = Capi_Mirror_Health::totals( time(), 'week' );
		$limits = Capi_Mirror_Health::thresholds();
		$noise  = array();
		foreach ( $week['reasons'] as $reason => $count ) {
			if ( 'noise' === Capi_Mirror_Health::kind( (string) $reason ) ) {
				$noise[] = $reason . ' ' . $count;
			}
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>" value="1" <?php checked( Config::instance()->meta_browser_mirror() ); ?> />
			<?php esc_html_e( 'Envoyer aussi par la CAPI PageView, ViewContent, AddToCart et InitiateCheckout, avec le même identifiant que le Pixel.', 'famma-core' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Meta garde un seul exemplaire de chaque événement. Envoi groupé toutes les 1 à 5 minutes, sans ralentir les pages. Après un changement, videz le cache de pages.', 'famma-core' ); ?></p>
		<p class="description">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: events sent, 2: real events lost or refused, 3: their reason codes, 4: events sent with a corrected value, 5: noise refusals, 6: noise reason codes with counts, 7: minimum count threshold, 8: minimum share threshold in percent. */
				__( '7 derniers jours : %1$s envoyé(s) ; %2$s perdu(s) ou refusé(s) (%3$s) ; %4$s corrigé(s) ; %5$s refus de bruit, jamais en alerte (%6$s). Alerte orange seulement si, sur 24 h, les pertes atteignent %7$s événements et %8$s %%.', 'famma-core' ),
				(string) $week['sent'],
				(string) $week['issue'],
				array() !== $week['codes'] ? implode( ', ', $week['codes'] ) : '-',
				(string) $week['adjusted'],
				(string) $week['noise'],
				array() !== $noise ? implode( ', ', $noise ) : '-',
				(string) $limits['min'],
				(string) $limits['share']
			)
		);
		?>
		</p>
		<?php
	}
}
