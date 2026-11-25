<?php
/**
 * Icône au trait d'un rayon, choisie dans Produits → Catégories.
 *
 * Sur l'accueil, la carte d'un rayon montre sa miniature WooCommerce quand le
 * propriétaire en a envoyé une ; sinon, une étoile orange. Ce fichier ajoute
 * un troisième choix entre les deux : une icône au trait, choisie dans la
 * fiche de la catégorie, que le thème dessine à l'arrivée quand la case
 * « Accueil : icônes des rayons » du nouveau look est cochée.
 *
 * Ordre d'affichage côté thème : miniature, puis icône, puis étoile.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Icône des rayons (méta de terme `_famma_cat_icon`).
 */
final class Cat_Icons {

	/**
	 * Méta de terme.
	 */
	public const META = '_famma_cat_icon';

	/**
	 * Taxonomie concernée.
	 */
	private const TAXONOMY = 'product_cat';

	/**
	 * Action du jeton de sécurité.
	 */
	private const NONCE = 'famma_cat_icon';

	/**
	 * Instance partagée.
	 *
	 * @var Cat_Icons|null
	 */
	private static ?Cat_Icons $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Cat_Icons
	 */
	public static function instance(): Cat_Icons {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks de l'écran des catégories.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'add_field' ), 20 );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'edit_field' ), 20 );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save' ) );
	}

	/**
	 * Icônes proposées : clé => libellé.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		return array(
			'beauty'  => __( 'Goutte (beauté, soin)', 'famma-core' ),
			'sport'   => __( 'Haltère (sport)', 'famma-core' ),
			'kitchen' => __( 'Marmite (cuisine)', 'famma-core' ),
			'kids'    => __( 'Ourson (enfants)', 'famma-core' ),
			'home'    => __( 'Maison', 'famma-core' ),
			'tools'   => __( 'Clé (outils)', 'famma-core' ),
		);
	}

	/**
	 * Tracés des icônes (grille 24 × 24, trait sans remplissage).
	 *
	 * @return array<string, array<int, string>> Clé => éléments SVG.
	 */
	public static function shapes(): array {
		return array(
			'beauty'  => array(
				'<path d="M12 3.5s-6 6.3-6 11a6 6 0 0 0 12 0c0-4.7-6-11-6-11z"/>',
				'<path d="M9.2 15.2a3 3 0 0 0 2.8 2.3"/>',
			),
			'sport'   => array(
				'<path d="M6.5 7v10M3.5 9.5v5M17.5 7v10M20.5 9.5v5M6.5 12h11"/>',
			),
			'kitchen' => array(
				'<path d="M4.5 10.5h15v7a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3z"/>',
				'<path d="M2 10.5h20"/>',
				'<path d="M9 7c0-1.2 1-1.3 1-2.5M14 7c0-1.2 1-1.3 1-2.5"/>',
			),
			'kids'    => array(
				'<circle cx="12" cy="13.5" r="7"/>',
				'<circle cx="6.5" cy="6" r="2"/>',
				'<circle cx="17.5" cy="6" r="2"/>',
				'<path d="M9.5 15.5c1.2 1.3 3.8 1.3 5 0"/>',
				'<path d="M9.5 11.5h.01M14.5 11.5h.01"/>',
			),
			'home'    => array(
				'<path d="M3 11.5L12 4l9 7.5"/>',
				'<path d="M5.5 9.5V20h13V9.5"/>',
				'<path d="M10 20v-5.5h4V20"/>',
			),
			'tools'   => array(
				'<path d="M14.7 6.3a4 4 0 0 0-5.2 5.2L3.8 17.2a1.8 1.8 0 0 0 2.5 2.5l5.7-5.7a4 4 0 0 0 5.2-5.2l-2.5 2.5-2.3-.5-.5-2.3z"/>',
			),
		);
	}

	/**
	 * Icône choisie pour un rayon.
	 *
	 * @param int $term_id Identifiant du rayon.
	 * @return string Clé connue, ou chaîne vide.
	 */
	public static function for_term( int $term_id ): string {
		$key = (string) get_term_meta( $term_id, self::META, true );

		return array_key_exists( $key, self::choices() ) ? $key : '';
	}

	/**
	 * SVG d'une icône, `pathLength="1"` sur chaque trait pour le tracé animé.
	 *
	 * Les tracés sont des constantes de ce fichier : rien ne vient d'une
	 * saisie, la sortie est néanmoins filtrée par `wp_kses`.
	 *
	 * @param string $key       Clé d'icône.
	 * @param string $css_class Classe de l'élément SVG.
	 * @return string SVG, ou chaîne vide si la clé est inconnue.
	 */
	public static function svg( string $key, string $css_class = '' ): string {
		$shapes = self::shapes();

		if ( ! isset( $shapes[ $key ] ) ) {
			return '';
		}

		$inner = '';
		foreach ( $shapes[ $key ] as $shape ) {
			$inner .= str_replace( '/>', ' pathLength="1"/>', $shape );
		}

		$svg = sprintf(
			'<svg class="%1$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">%2$s</svg>',
			esc_attr( $css_class ),
			$inner
		);

		return wp_kses( $svg, self::allowed_svg() );
	}

	/**
	 * Balises et attributs SVG autorisés à la sortie.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_svg(): array {
		$common = array(
			'pathlength' => true,
		);

		return array(
			'svg'    => array(
				'class'           => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'focusable'       => true,
				'aria-hidden'     => true,
			),
			'path'   => $common + array( 'd' => true ),
			'circle' => $common + array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			),
		);
	}

	/**
	 * Liste déroulante, partagée par les deux écrans.
	 *
	 * @param string $current Clé enregistrée.
	 * @return void
	 */
	private function select( string $current ): void {
		wp_nonce_field( self::NONCE, 'famma_cat_icon_nonce' );
		?>
		<select name="famma_cat_icon" id="famma-cat-icon">
			<option value=""><?php esc_html_e( 'Aucune (étoile orange)', 'famma-core' ); ?></option>
			<?php foreach ( self::choices() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Aide sous la liste.
	 *
	 * @return string
	 */
	private function help(): string {
		return __( 'Sur l’accueil, la carte du rayon montre d’abord la miniature de la catégorie (champ « Miniature » ci-dessous ou ci-dessus). Sans miniature : cette icône, dessinée à l’arrivée, si la case « Accueil : icônes des rayons » est cochée dans FAMMA → Nouveau look (onglet Accueil). Sinon : l’étoile orange.', 'famma-core' );
	}

	/**
	 * Champ de l'écran « Ajouter une catégorie ».
	 *
	 * @return void
	 */
	public function add_field(): void {
		?>
		<div class="form-field term-famma-cat-icon-wrap">
			<label for="famma-cat-icon"><?php esc_html_e( 'Icône sur l’accueil', 'famma-core' ); ?></label>
			<?php $this->select( '' ); ?>
			<p><?php echo esc_html( $this->help() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Champ de l'écran « Modifier la catégorie ».
	 *
	 * @param \WP_Term $term Rayon modifié.
	 * @return void
	 */
	public function edit_field( \WP_Term $term ): void {
		?>
		<tr class="form-field term-famma-cat-icon-wrap">
			<th scope="row"><label for="famma-cat-icon"><?php esc_html_e( 'Icône sur l’accueil', 'famma-core' ); ?></label></th>
			<td>
				<?php $this->select( self::for_term( (int) $term->term_id ) ); ?>
				<p class="description"><?php echo esc_html( $this->help() ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Enregistre le choix (nonce, droit, valeur connue).
	 *
	 * @param int $term_id Identifiant du rayon.
	 * @return void
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['famma_cat_icon_nonce'], $_POST['famma_cat_icon'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['famma_cat_icon_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$key = sanitize_key( wp_unslash( $_POST['famma_cat_icon'] ) );

		if ( array_key_exists( $key, self::choices() ) ) {
			update_term_meta( $term_id, self::META, $key );
		} else {
			delete_term_meta( $term_id, self::META );
		}

		// L'accueil en cache montre encore l'ancienne carte.
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si le plugin est absent.
	}
}
