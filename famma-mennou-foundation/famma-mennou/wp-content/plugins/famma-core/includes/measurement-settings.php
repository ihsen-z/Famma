<?php
/**
 * Réglages avancés de la mesure Meta, dans l'écran « Mesure » (lot 5).
 *
 * - Version de l'API Meta (S2, A1) : format vNN.N, sinon refusée avec un
 *   message, l'ancienne valeur étant conservée. Vide : valeur par défaut.
 * - Profil de données (R3, A14) : `standard` ou `minimal`.
 * - Chargement du Pixel (D6, A15) : `immediate` ou `deferred`.
 * - « Suivre mes visites (mode test) » (S1, A15).
 * - OrderPlaced côté navigateur (S3-a, A15), coupé par défaut.
 * - Validation du code d'événement test (A12) : TEST suivi de chiffres.
 *
 * Les accesseurs vivent dans Config ; cet écran ne fait qu'enregistrer.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Section « Réglages avancés Meta » de l'écran « Mesure ».
 */
final class Measurement_Settings {

	/**
	 * Section de l'écran.
	 */
	private const SECTION = 'famma_section_meta_advanced';

	/**
	 * Instance unique.
	 *
	 * @var Measurement_Settings|null
	 */
	private static ?Measurement_Settings $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Measurement_Settings
	 */
	public static function instance(): Measurement_Settings {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_init', array( self::$i, 'register' ), 11 );
		}
		return self::$i;
	}

	/**
	 * Enregistre les options et les champs.
	 *
	 * @return void
	 */
	public function register(): void {
		$group = Tracking_Settings::GROUP;
		register_setting( $group, 'famma_meta_graph_version', $this->string_setting( array( self::class, 'sanitize_graph_version' ), '' ) );
		register_setting( $group, 'famma_meta_data_profile', $this->string_setting( array( self::class, 'sanitize_data_profile' ), Config::META_DATA_PROFILES[0] ) );
		register_setting( $group, 'famma_meta_pixel_loading', $this->string_setting( array( self::class, 'sanitize_pixel_loading' ), Config::META_PIXEL_LOADING_MODES[0] ) );
		foreach ( array( 'famma_track_internal', 'famma_meta_orderplaced_browser' ) as $option ) {
			register_setting(
				$group,
				$option,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
					'default'           => false,
				)
			);
		}

		add_settings_section( self::SECTION, __( 'Réglages avancés Meta', 'famma-core' ), array( $this, 'section_intro' ), Tracking_Settings::SLUG );
		add_settings_field( 'famma_meta_graph_version', __( 'Version de l’API Meta', 'famma-core' ), array( $this, 'render_graph_version' ), Tracking_Settings::SLUG, self::SECTION );
		add_settings_field( 'famma_meta_data_profile', __( 'Profil de données Meta', 'famma-core' ), array( $this, 'render_data_profile' ), Tracking_Settings::SLUG, self::SECTION );
		add_settings_field( 'famma_meta_pixel_loading', __( 'Chargement du Pixel', 'famma-core' ), array( $this, 'render_pixel_loading' ), Tracking_Settings::SLUG, self::SECTION );
		add_settings_field( 'famma_track_internal', __( 'Suivre mes visites (mode test)', 'famma-core' ), array( $this, 'render_track_internal' ), Tracking_Settings::SLUG, self::SECTION );
		add_settings_field( 'famma_meta_orderplaced_browser', __( 'OrderPlaced côté navigateur', 'famma-core' ), array( $this, 'render_orderplaced_browser' ), Tracking_Settings::SLUG, self::SECTION );
	}

	/**
	 * Arguments d'un réglage texte.
	 *
	 * @param callable $sanitize Fonction d'assainissement.
	 * @param string   $fallback Valeur par défaut.
	 * @return array<string, mixed>
	 */
	private function string_setting( callable $sanitize, string $fallback ): array {
		return array(
			'type'              => 'string',
			'sanitize_callback' => $sanitize,
			'default'           => $fallback,
		);
	}

	/**
	 * Valide la version de l'API Graph ; une valeur invalide est refusée.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public static function sanitize_graph_version( $value ): string {
		$value = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );
		if ( '' === $value || Config::valid_graph_version( $value ) ) {
			return $value;
		}
		add_settings_error(
			'famma_meta_graph_version',
			'famma_meta_graph_version_invalid',
			/* translators: %s: example of a valid Graph API version. */
			sprintf( __( 'Version de l’API Meta refusée : utilisez le format vNN.N, par exemple %s. L’ancienne valeur est conservée.', 'famma-core' ), Config::META_GRAPH_VERSION_DEFAULT )
		);
		return (string) get_option( 'famma_meta_graph_version', '' );
	}

	/**
	 * Valide le profil de données.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public static function sanitize_data_profile( $value ): string {
		return self::choice( 'famma_meta_data_profile', $value, Config::META_DATA_PROFILES, __( 'Profil de données refusé : choisissez « standard » ou « minimal ».', 'famma-core' ) );
	}

	/**
	 * Valide le mode de chargement du Pixel.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public static function sanitize_pixel_loading( $value ): string {
		return self::choice( 'famma_meta_pixel_loading', $value, Config::META_PIXEL_LOADING_MODES, __( 'Mode de chargement refusé : choisissez « immédiat » ou « différé ».', 'famma-core' ) );
	}

	/**
	 * Valide le code d'événement test (A12) : vide, ou TEST suivi de chiffres.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public static function sanitize_test_code( $value ): string {
		$value = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );
		if ( '' === $value || Config::valid_test_event_code( $value ) ) {
			return $value;
		}
		add_settings_error( 'famma_meta_test_event_code', 'famma_meta_test_event_code_invalid', __( 'Code d’événement test refusé : il doit commencer par TEST suivi de chiffres, comme le donne Meta (par exemple TEST12345). L’ancienne valeur est conservée.', 'famma-core' ) );
		return (string) get_option( 'famma_meta_test_event_code', '' );
	}

	/**
	 * Valeur d'une liste fermée ; hors liste, refusée avec un message.
	 *
	 * @param string   $option  Option concernée.
	 * @param mixed    $value   Valeur soumise.
	 * @param string[] $allowed Valeurs permises (la première est le défaut).
	 * @param string   $message Message d'erreur.
	 * @return string
	 */
	private static function choice( string $option, $value, array $allowed, string $message ): string {
		$value = sanitize_key( wp_unslash( (string) $value ) );
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}
		add_settings_error( $option, $option . '_invalid', $message );
		$previous = (string) get_option( $option, $allowed[0] );
		return in_array( $previous, $allowed, true ) ? $previous : $allowed[0];
	}

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p><?php esc_html_e( 'Réglages de secours face aux évolutions de Meta. Les valeurs par défaut conviennent : ne les changez qu’en suivant la procédure d’exploitation.', 'famma-core' ); ?></p>
		<?php
	}

	/**
	 * Champ de la version de l'API Graph.
	 *
	 * @return void
	 */
	public function render_graph_version(): void {
		$cfg     = Config::instance();
		$version = $cfg->meta_graph_version();
		$sources = array(
			'constant' => __( 'constante du serveur FAMMA_META_GRAPH_VERSION', 'famma-core' ),
			'option'   => __( 'ce champ', 'famma-core' ),
			'default'  => __( 'valeur par défaut du code', 'famma-core' ),
		);
		$status  = Capi_Graph_Version::status( $version, time() );
		?>
		<input type="text" id="famma_meta_graph_version" name="famma_meta_graph_version" value="<?php echo esc_attr( (string) get_option( 'famma_meta_graph_version', '' ) ); ?>" class="regular-text" pattern="v[0-9]{1,2}\.[0-9]" placeholder="<?php echo esc_attr( Config::META_GRAPH_VERSION_DEFAULT ); ?>" autocomplete="off" spellcheck="false" />
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: Graph API version in use, 2: where it comes from, 3: end date or "inconnue". */
					__( 'Version utilisée : %1$s (%2$s). Fin annoncée par Meta : %3$s. Format vNN.N ; laisser vide revient à la valeur par défaut.', 'famma-core' ),
					$version,
					$sources[ $cfg->meta_graph_version_source() ] ?? '',
					'' !== $status['end'] ? $status['end'] : __( 'inconnue', 'famma-core' )
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Champ du profil de données.
	 *
	 * @return void
	 */
	public function render_data_profile(): void {
		$this->select(
			'famma_meta_data_profile',
			Config::instance()->meta_data_profile(),
			array(
				'standard' => __( 'Standard (recommandé)', 'famma-core' ),
				'minimal'  => __( 'Minimal (urgence)', 'famma-core' ),
			),
			__( 'Standard : aucun paramètre personnalisé ni nom de produit envoyé à Meta. Minimal : seulement la valeur et la devise, si Meta restreint les données du site.', 'famma-core' )
		);
	}

	/**
	 * Champ du mode de chargement du Pixel.
	 *
	 * @return void
	 */
	public function render_pixel_loading(): void {
		$this->select(
			'famma_meta_pixel_loading',
			Config::instance()->meta_pixel_loading(),
			array(
				'immediate' => __( 'Immédiat (recommandé)', 'famma-core' ),
				'deferred'  => __( 'Différé', 'famma-core' ),
			),
			__( 'Immédiat : le Pixel se charge en arrière-plan dès l’arrivée, comme le code standard de Meta. Différé : page un peu plus légère, mais un visiteur pressé sur réseau lent n’est pas compté.', 'famma-core' )
		);
	}

	/**
	 * Case « Suivre mes visites (mode test) ».
	 *
	 * @return void
	 */
	public function render_track_internal(): void {
		$this->checkbox( 'famma_track_internal', Config::instance()->track_internal_visitors(), __( 'Mesurer aussi les visites des administrateurs et gérants connectés.', 'famma-core' ), __( 'Désactivé par défaut : vos propres visites ne doivent pas fausser les statistiques. À cocher le temps d’un test dans Meta, puis à décocher.', 'famma-core' ) );
	}

	/**
	 * Case « OrderPlaced côté navigateur ».
	 *
	 * @return void
	 */
	public function render_orderplaced_browser(): void {
		$this->checkbox( 'famma_meta_orderplaced_browser', Config::instance()->meta_orderplaced_browser(), __( 'Envoyer aussi OrderPlaced depuis le navigateur, avec le même identifiant.', 'famma-core' ), __( 'Désactivé par défaut : Meta garde la version du navigateur, moins riche que celle du serveur, quand les deux arrivent à moins de 5 minutes d’écart.', 'famma-core' ) );
	}

	/**
	 * Liste déroulante.
	 *
	 * @param string                $option  Option.
	 * @param string                $current Valeur actuelle.
	 * @param array<string, string> $choices Valeur => libellé.
	 * @param string                $help    Aide.
	 * @return void
	 */
	private function select( string $option, string $current, array $choices, string $help ): void {
		?>
		<select id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Case à cocher booléenne.
	 *
	 * @param string $option  Option.
	 * @param bool   $checked État actuel.
	 * @param string $label   Libellé.
	 * @param string $help    Aide.
	 * @return void
	 */
	private function checkbox( string $option, bool $checked, string $label, string $help ): void {
		?>
		<input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php
	}
}
