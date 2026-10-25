<?php
/**
 * Style du carrousel de l'accueil — choisi depuis FAMMA → Pages.
 *
 * Quatre présentations du même contenu (les produits illustrés du héros) :
 * le thème ne charge que le balisage, la feuille et le comportement du style
 * retenu. Changer de style ne coûte donc rien au visiteur : aucune variante
 * inutilisée n'est envoyée au navigateur.
 *
 * Ce fichier ne porte que l'option et son écran. Le dessin vit dans le thème
 * (`famma_child_home_hero()`), qui retombe sur le style historique si le
 * plugin est absent.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Option « style du carrousel de l'accueil ».
 */
final class Home_Hero_Style {

	/**
	 * Option abritant le style retenu.
	 */
	public const OPTION = 'famma_home_hero_style';

	/**
	 * Style servi tant que le propriétaire n'a rien choisi.
	 *
	 * La pile de cartes : c'est le choix du propriétaire du 25/10/2026, posé
	 * en défaut pour qu'il s'applique dès le déploiement, sans passage par
	 * l'administration.
	 */
	public const DEFAULT = 'stack';

	/**
	 * Instance unique.
	 *
	 * @var Home_Hero_Style|null
	 */
	private static ?Home_Hero_Style $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Home_Hero_Style
	 */
	public static function instance(): Home_Hero_Style {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks d'administration.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_page_cache' ) );
	}

	/**
	 * Styles proposés, dans leur ordre d'affichage à l'écran.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function styles(): array {
		return array(
			'stack'    => array(
				'label' => __( 'Pile de cartes', 'famma-core' ),
				'help'  => __( 'Les produits en éventail ; on jette la carte du dessus d’un coup de doigt. Flèches, nom, prix et compteur sous la pile.', 'famma-core' ),
			),
			'showcase' => array(
				'label' => __( 'Vitrine', 'famma-core' ),
				'help'  => __( 'Une grande photo arrondie, nom et prix posés dessus, barres de progression façon « stories ».', 'famma-core' ),
			),
			'thumbs'   => array(
				'label' => __( 'Vignettes', 'famma-core' ),
				'help'  => __( 'Une grande photo et les miniatures des produits dessous. Pas de défilement automatique : le visiteur choisit.', 'famma-core' ),
			),
			'classic'  => array(
				'label' => __( 'Classique', 'famma-core' ),
				'help'  => __( 'L’ancien héros : photo sur disque orange, points de navigation.', 'famma-core' ),
			),
		);
	}

	/**
	 * Style à servir.
	 *
	 * @return string Toujours une clé de `styles()`.
	 */
	public static function get(): string {
		$style = (string) get_option( self::OPTION, self::DEFAULT );

		return array_key_exists( $style, self::styles() ) ? $style : self::DEFAULT;
	}

	/**
	 * Enregistre l'option et son champ, sur l'écran FAMMA → Pages.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Pages_Content::GROUP,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::DEFAULT,
			)
		);

		add_settings_section(
			'famma_section_home_hero',
			__( 'Page d’accueil — carrousel du héros', 'famma-core' ),
			array( $this, 'section_intro' ),
			Pages_Content::SLUG
		);

		add_settings_field(
			self::OPTION,
			esc_html__( 'Style du carrousel', 'famma-core' ),
			array( $this, 'render_field' ),
			Pages_Content::SLUG,
			'famma_section_home_hero'
		);
	}

	/**
	 * N'accepte qu'un style connu : un POST forgé retombe sur le défaut.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public function sanitize( $value ): string {
		$value = sanitize_key( wp_unslash( (string) $value ) );

		return array_key_exists( $value, self::styles() ) ? $value : self::DEFAULT;
	}

	/**
	 * Vide le cache de page après un changement de style.
	 *
	 * L'accueil est servi depuis le cache LiteSpeed : sans purge, le nouveau
	 * style n'apparaîtrait qu'à l'expiration de la page en cache.
	 *
	 * @return void
	 */
	public function flush_page_cache(): void {
		do_action( 'litespeed_purge_url', home_url( '/' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si le plugin est absent.
	}

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Le carrousel présente les derniers produits qui ont une photo. Il n’apparaît qu’à partir de deux produits ; avec un seul, le héros reste fixe.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche les styles sous forme de boutons radio.
	 *
	 * @return void
	 */
	public function render_field(): void {
		$current = self::get();
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Style du carrousel', 'famma-core' ); ?></legend>
			<?php foreach ( self::styles() as $style => $meta ) : ?>
				<p>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( self::OPTION ); ?>"
							value="<?php echo esc_attr( $style ); ?>"
							aria-describedby="<?php echo esc_attr( self::OPTION . '_' . $style . '_help' ); ?>"
							<?php checked( $current, $style ); ?>
						/>
						<strong><?php echo esc_html( $meta['label'] ); ?></strong>
					</label>
					<br />
					<span class="description" id="<?php echo esc_attr( self::OPTION . '_' . $style . '_help' ); ?>">
						<?php echo esc_html( $meta['help'] ); ?>
					</span>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}
}
