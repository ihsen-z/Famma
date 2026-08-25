<?php
/**
 * Administration du référencement : réglages globaux et champs par contenu.
 *
 * Tout ce que les moteurs affichent doit être modifiable sans toucher au
 * code : la description d'un produit ou d'une page se règle depuis l'écran
 * d'édition, la description d'accueil et les profils officiels depuis
 * « FAMMA → Référencement ».
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Écrans d'administration du référencement.
 */
final class Seo_Admin {

	/**
	 * Identifiant de la page de réglages.
	 */
	public const SLUG = 'famma-seo';

	/**
	 * Groupe de réglages, au sens de la Settings API.
	 */
	public const GROUP = 'famma_seo_group';

	/**
	 * Types de contenu recevant les champs de référencement.
	 *
	 * @var array<int, string>
	 */
	private const POST_TYPES = array( 'page', 'post', 'product' );

	/**
	 * Instance unique.
	 *
	 * @var Seo_Admin|null
	 */
	private static ?Seo_Admin $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Seo_Admin
	 */
	public static function instance(): Seo_Admin {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
	}

	// --- Réglages globaux ---

	/**
	 * Schéma des réglages globaux.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function schema(): array {
		return array(
			array(
				'key'   => 'home_description_fr',
				'label' => __( 'Description de la page d’accueil (français)', 'famma-core' ),
				'type'  => 'textarea',
				'help'  => __( 'C’est le texte que Google affiche sous le titre du site. Une à deux phrases, 160 caractères maximum. Dites ce que vous vendez, à qui, et ce qui vous distingue.', 'famma-core' ),
			),
			array(
				'key'   => 'home_description_ar',
				'label' => __( 'Description de la page d’accueil (arabe)', 'famma-core' ),
				'type'  => 'textarea',
				'help'  => __( 'Laissez vide pour réutiliser le français.', 'famma-core' ),
			),
			array(
				'key'   => 'title_suffix_fr',
				'label' => __( 'Complément de titre du catalogue (français)', 'famma-core' ),
				'type'  => 'text',
				'help'  => __( 'Ajouté au titre des produits et de la boutique dans les résultats de recherche. N’affirmez ici que ce que la boutique applique réellement.', 'famma-core' ),
			),
			array(
				'key'   => 'title_suffix_ar',
				'label' => __( 'Complément de titre du catalogue (arabe)', 'famma-core' ),
				'type'  => 'text',
				'help'  => __( 'Laissez vide pour réutiliser le français.', 'famma-core' ),
			),
			array(
				'key'   => 'same_as',
				'label' => __( 'Profils officiels', 'famma-core' ),
				'type'  => 'textarea',
				'help'  => __( 'Une adresse complète par ligne (Facebook, Instagram, TikTok…). Elles relient la boutique à ses pages officielles dans les moteurs. N’indiquez que des profils qui existent.', 'famma-core' ),
			),
		);
	}

	/**
	 * Enregistre l'option et ses champs.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Seo::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'famma_seo_section',
			__( 'Référencement et moteurs génératifs', 'famma-core' ),
			array( $this, 'section_intro' ),
			self::SLUG
		);

		foreach ( self::schema() as $field ) {
			add_settings_field(
				'famma_seo_' . $field['key'],
				esc_html( $field['label'] ),
				array( $this, 'render_field' ),
				self::SLUG,
				'famma_seo_section',
				array( 'field' => $field )
			);
		}
	}

	/**
	 * Assainit les réglages avant enregistrement.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return array<string, string>
	 */
	public function sanitize( $value ): array {
		$value = is_array( $value ) ? $value : array();
		$clean = array();

		foreach ( self::schema() as $field ) {
			$key = $field['key'];
			$raw = isset( $value[ $key ] ) ? (string) $value[ $key ] : '';

			$clean[ $key ] = 'textarea' === $field['type']
				? sanitize_textarea_field( $raw )
				: sanitize_text_field( $raw );
		}

		return $clean;
	}

	/**
	 * Introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		echo '<p>' . esc_html__( 'Ces réglages alimentent ce que Google affiche dans ses résultats et ce que ChatGPT, Perplexity ou les résumés d’IA peuvent citer. Rien n’est inventé automatiquement : un champ laissé vide n’est simplement pas publié.', 'famma-core' ) . '</p>';
	}

	/**
	 * Affiche un champ de réglage.
	 *
	 * @param array<string, mixed> $args Arguments passés par la Settings API.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$field = isset( $args['field'] ) && is_array( $args['field'] ) ? $args['field'] : array();
		$key   = isset( $field['key'] ) ? (string) $field['key'] : '';

		if ( '' === $key ) {
			return;
		}

		$stored = get_option( Seo::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$value  = isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
		$name   = Seo::OPTION . '[' . $key . ']';
		$is_ar  = str_ends_with( $key, '_ar' );

		if ( 'textarea' === ( $field['type'] ?? 'text' ) ) {
			printf(
				'<textarea class="large-text" rows="3" name="%1$s" id="%2$s"%3$s>%4$s</textarea>',
				esc_attr( $name ),
				esc_attr( 'famma_seo_' . $key ),
				$is_ar ? ' dir="rtl" lang="ar"' : '',
				esc_textarea( $value )
			);
		} else {
			printf(
				'<input type="text" class="regular-text" name="%1$s" id="%2$s" value="%3$s"%4$s />',
				esc_attr( $name ),
				esc_attr( 'famma_seo_' . $key ),
				esc_attr( $value ),
				$is_ar ? ' dir="rtl" lang="ar"' : ''
			);
		}

		if ( isset( $field['help'] ) && '' !== (string) $field['help'] ) {
			echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}
	}

	/**
	 * Ajoute l'écran au menu FAMMA.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'famma-dashboard',
			__( 'Référencement', 'famma-core' ),
			__( 'Référencement', 'famma-core' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Affiche l'écran de réglages.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button( __( 'Enregistrer', 'famma-core' ) );
				?>
			</form>
		</div>
		<?php
	}

	// --- Champs par contenu ---

	/**
	 * Déclare la boîte de référencement sur les contenus concernés.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		foreach ( self::POST_TYPES as $type ) {
			add_meta_box(
				'famma-seo',
				__( 'Référencement FAMMA', 'famma-core' ),
				array( $this, 'render_meta_box' ),
				$type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Champs saisis par contenu.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function meta_schema(): array {
		return array(
			array(
				'key'   => 'title_fr',
				'label' => __( 'Titre dans Google (français)', 'famma-core' ),
				'type'  => 'text',
				'help'  => __( 'Laissez vide pour utiliser le titre de la page. Environ 60 caractères.', 'famma-core' ),
			),
			array(
				'key'   => 'description_fr',
				'label' => __( 'Description dans Google (français)', 'famma-core' ),
				'type'  => 'textarea',
				'help'  => __( 'Une à deux phrases, 160 caractères maximum. Si vous laissez vide, le début du contenu est utilisé.', 'famma-core' ),
			),
			array(
				'key'   => 'title_ar',
				'label' => __( 'Titre dans Google (arabe)', 'famma-core' ),
				'type'  => 'text',
				'help'  => __( 'Laissez vide pour réutiliser le français.', 'famma-core' ),
			),
			array(
				'key'   => 'description_ar',
				'label' => __( 'Description dans Google (arabe)', 'famma-core' ),
				'type'  => 'textarea',
				'help'  => __( 'Laissez vide pour réutiliser le français.', 'famma-core' ),
			),
		);
	}

	/**
	 * Affiche la boîte de référencement.
	 *
	 * @param \WP_Post $post Contenu en cours d'édition.
	 * @return void
	 */
	public function render_meta_box( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		wp_nonce_field( 'famma_seo_meta', 'famma_seo_nonce' );

		echo '<p class="description">' . esc_html__( 'Ce que les moteurs de recherche affichent pour cette page. Laissé vide, le texte est déduit du contenu publié.', 'famma-core' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::meta_schema() as $field ) {
			$key   = $field['key'];
			$name  = Seo::META_PREFIX . $key;
			$value = (string) get_post_meta( (int) $post->ID, $name, true );
			$is_ar = str_ends_with( $key, '_ar' );

			echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

			if ( 'textarea' === $field['type'] ) {
				printf(
					'<textarea class="large-text" rows="2" name="%1$s" id="%1$s"%2$s>%3$s</textarea>',
					esc_attr( $name ),
					$is_ar ? ' dir="rtl" lang="ar"' : '',
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="text" class="large-text" name="%1$s" id="%1$s" value="%2$s"%3$s />',
					esc_attr( $name ),
					esc_attr( $value ),
					$is_ar ? ' dir="rtl" lang="ar"' : ''
				);
			}

			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Enregistre les champs de référencement.
	 *
	 * @param int      $post_id Identifiant du contenu.
	 * @param \WP_Post $post    Contenu enregistré.
	 * @return void
	 */
	public function save_meta( $post_id, $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return;
		}

		$nonce = isset( $_POST['famma_seo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['famma_seo_nonce'] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'famma_seo_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}

		foreach ( self::meta_schema() as $field ) {
			$name = Seo::META_PREFIX . $field['key'];
			if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
				delete_post_meta( (int) $post_id, $name );
				continue;
			}

			$clean = 'textarea' === $field['type']
				? sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) )
				: sanitize_text_field( wp_unslash( $_POST[ $name ] ) );

			if ( '' === trim( $clean ) ) {
				delete_post_meta( (int) $post_id, $name );
				continue;
			}

			update_post_meta( (int) $post_id, $name, $clean );
		}
	}
}
