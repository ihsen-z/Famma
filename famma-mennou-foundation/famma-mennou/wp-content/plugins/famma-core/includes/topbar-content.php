<?php
/**
 * Barre de promesses de l'en-tête — contenu éditable.
 *
 * Les quatre promesses de la barre marine étaient écrites dans le thème. Elles
 * deviennent éditables, comme le reste du contenu : le propriétaire doit pouvoir
 * changer un argument commercial sans dev et sans déploiement.
 *
 * Deux contraintes de la maquette ne sont pas décoratives et restent donc
 * exposées à l'écran plutôt que cachées dans le code :
 *
 * - Sous 700px, la barre n'affiche que la **première** promesse. L'ordre décide
 *   donc de ce que voit un client sur mobile.
 * - Une seule promesse porte l'accent orange. Deux accents dans une barre de
 *   40px n'accentuent plus rien, donc le second est ignoré.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Promesses éditables de la barre supérieure.
 */
final class Topbar_Content {

	/**
	 * Option abritant les promesses.
	 */
	public const OPTION = 'famma_topbar_promises';

	/**
	 * Nombre d'emplacements proposés.
	 *
	 * Quatre, pas plus : au-delà, la barre se tasse et devient illisible sur
	 * mobile. La limite est une contrainte de mise en page, pas une préférence.
	 */
	private const SLOTS = 4;

	/**
	 * Instance unique.
	 *
	 * @var Topbar_Content|null
	 */
	private static ?Topbar_Content $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Topbar_Content
	 */
	public static function instance(): Topbar_Content {
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_picker' ) );
	}

	/**
	 * Charge le sélecteur de médias, sur le seul écran qui l'utilise.
	 *
	 * @param string $hook Identifiant de l'écran courant.
	 * @return void
	 */
	public function enqueue_media_picker( string $hook ): void {
		if ( ! str_contains( $hook, Pages_Content::SLUG ) ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script( 'media-editor', $this->picker_script() );
	}

	/**
	 * Script du sélecteur de médias.
	 *
	 * Écrit à la main plutôt que dans un fichier séparé : une trentaine de
	 * lignes qui ne servent qu'à cet écran, et qui doivent rester lisibles à
	 * côté du balisage qu'elles pilotent.
	 *
	 * @return string
	 */
	private function picker_script(): string {
		$title = esc_js( __( 'Choisir une icône', 'famma-core' ) );
		$use   = esc_js( __( 'Utiliser cette icône', 'famma-core' ) );

		return <<<JS
( function () {
	document.addEventListener( 'click', function ( event ) {
		var choose = event.target.closest( '.famma-icon-choose' );
		var clear  = event.target.closest( '.famma-icon-clear' );
		var row    = ( choose || clear ) && ( choose || clear ).closest( '.famma-icon-upload' );

		if ( ! row ) {
			return;
		}

		event.preventDefault();

		var field   = row.querySelector( '.famma-icon-id' );
		var preview = row.querySelector( '.famma-icon-preview' );

		if ( clear ) {
			field.value = '';
			preview.innerHTML = '';
			row.querySelector( '.famma-icon-clear' ).hidden = true;
			return;
		}

		var frame = wp.media( {
			title: '{$title}',
			button: { text: '{$use}' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var image = frame.state().get( 'selection' ).first().toJSON();
			var src   = ( image.sizes && image.sizes.thumbnail ) ? image.sizes.thumbnail.url : image.url;

			field.value = image.id;
			preview.innerHTML = '';
			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = '';
			img.style.maxBlockSize = '32px';
			img.style.maxInlineSize = '32px';
			preview.appendChild( img );
			row.querySelector( '.famma-icon-clear' ).hidden = false;
		} );

		frame.open();
	} );
}() );
JS;
	}

	/**
	 * Icônes proposées pour une promesse.
	 *
	 * Volontairement restreinte à ce que le thème sait dessiner : un nom libre
	 * produirait une icône vide, et le propriétaire n'aurait aucun moyen de
	 * comprendre pourquoi.
	 *
	 * @return array<string, string>
	 */
	public static function icons(): array {
		return array(
			'tag'    => __( 'Étiquette (prix, promotion)', 'famma-core' ),
			'truck'  => __( 'Camion (livraison)', 'famma-core' ),
			'card'   => __( 'Carte (paiement)', 'famma-core' ),
			'shield' => __( 'Bouclier (satisfait ou remboursé, garantie)', 'famma-core' ),
			'chat'   => __( 'Bulle (WhatsApp, support)', 'famma-core' ),
			'phone'  => __( 'Téléphone', 'famma-core' ),
			'clock'  => __( 'Horloge (délai, horaires)', 'famma-core' ),
			'pin'    => __( 'Repère (zone, adresse)', 'famma-core' ),
			'mail'   => __( 'Enveloppe (e-mail)', 'famma-core' ),
		);
	}

	/**
	 * Valeurs de départ : les promesses telles qu'elles étaient dans le thème.
	 *
	 * Ce sont des affirmations que le code applique réellement — livraison
	 * offerte (§4), couverture nationale, paiement à la livraison, support
	 * WhatsApp conditionné à l'existence d'un numéro. Rien d'inventé (§58).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function defaults(): array {
		return array(
			array(
				'label_fr' => 'LIVRAISON GRATUITE 100%',
				'label_ar' => 'التوصيل فابور 100%',
				'icon'     => 'tag',
				'icon_id'  => 0,
				'accent'   => true,
				'whatsapp' => false,
			),
			array(
				'label_fr' => 'Livraison partout en Tunisie',
				'label_ar' => 'التوصيل في تونس الكل',
				'icon'     => 'truck',
				'icon_id'  => 0,
				'accent'   => false,
				'whatsapp' => false,
			),
			array(
				'label_fr' => 'Paiement à la livraison',
				'label_ar' => 'الدفع كي يوصلك',
				'icon'     => 'card',
				'icon_id'  => 0,
				'accent'   => false,
				'whatsapp' => false,
			),
			array(
				'label_fr' => 'Support WhatsApp',
				'label_ar' => 'مساعدة على الواتساب',
				'icon'     => 'chat',
				'icon_id'  => 0,
				'accent'   => false,
				'whatsapp' => true,
			),
		);
	}

	/**
	 * Les emplacements tels qu'ils sont stockés, complétés par les valeurs
	 * de départ quand rien n'a encore été enregistré.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function slots(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || array() === $stored ) {
			return self::defaults();
		}

		$defaults = self::defaults();
		$slots    = array();

		for ( $index = 0; $index < self::SLOTS; $index++ ) {
			$slots[] = is_array( $stored[ $index ] ?? null )
				? $stored[ $index ]
				: ( $defaults[ $index ] ?? array() );
		}

		return $slots;
	}

	/**
	 * Les promesses à afficher, prêtes pour le thème.
	 *
	 * Un libellé vide retire la promesse : c'est ainsi qu'on passe de quatre à
	 * trois sans champ « activer ». Une promesse marquée « WhatsApp » disparaît
	 * tant qu'aucun numéro n'est configuré — sans cette garde, le site pourrait
	 * annoncer un support qui n'existe pas.
	 *
	 * @return array<int, array{icon: string, icon_url: string, label: string, accent: bool}>
	 */
	public static function promises(): array {
		$lang     = is_rtl() ? 'ar' : 'fr';
		$has_wa   = '' !== Config::instance()->whatsapp_number();
		$icons    = self::icons();
		$accented = false;
		$promises = array();

		foreach ( self::slots() as $slot ) {
			$label = trim( (string) ( $slot[ 'label_' . $lang ] ?? '' ) );

			if ( '' === $label && 'ar' === $lang ) {
				$label = trim( (string) ( $slot['label_fr'] ?? '' ) );
			}

			if ( '' === $label ) {
				continue;
			}

			if ( ! empty( $slot['whatsapp'] ) && ! $has_wa ) {
				continue;
			}

			$icon = (string) ( $slot['icon'] ?? 'tag' );

			// Le second accent est ignoré : deux mises en avant dans une barre
			// de 40px n'accentuent plus rien.
			$accent = ! empty( $slot['accent'] ) && ! $accented;

			if ( $accent ) {
				$accented = true;
			}

			/*
			 * Une icône téléversée l'emporte sur l'icône dessinée : si le
			 * propriétaire a pris la peine d'en envoyer une, c'est elle qu'il
			 * veut voir. La liste déroulante reste le repli.
			 */
			$icon_id  = absint( $slot['icon_id'] ?? 0 );
			$icon_url = $icon_id > 0 ? (string) wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';

			$promises[] = array(
				'icon'     => isset( $icons[ $icon ] ) ? $icon : 'tag',
				'icon_url' => $icon_url,
				'label'    => $label,
				'accent'   => $accent,
			);
		}

		return $promises;
	}

	/**
	 * Enregistre l'option et les champs.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Pages_Content::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'famma_section_topbar',
			__( 'Barre de promesses (en-tête)', 'famma-core' ),
			array( $this, 'section_intro' ),
			Pages_Content::SLUG
		);

		for ( $index = 0; $index < self::SLOTS; $index++ ) {
			add_settings_field(
				self::OPTION . '_' . $index,
				/* translators: %d: position of the promise in the bar. */
				esc_html( sprintf( __( 'Promesse %d', 'famma-core' ), $index + 1 ) ),
				array( $this, 'render_slot' ),
				Pages_Content::SLUG,
				'famma_section_topbar',
				array( 'index' => $index )
			);
		}
	}

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Les promesses de la barre marine, en haut du site. Vider un libellé retire la promesse.', 'famma-core' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'L’ordre compte :', 'famma-core' ); ?></strong>
			<?php esc_html_e( 'sur un téléphone, la barre n’affiche que la première promesse. Mettez en tête celle qui compte le plus.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche un emplacement de promesse.
	 *
	 * @param array<string, int> $args Arguments du champ.
	 * @return void
	 */
	public function render_slot( array $args ): void {
		$index = (int) ( $args['index'] ?? 0 );
		$slots = self::slots();
		$slot  = $slots[ $index ] ?? array();
		$name  = self::OPTION . '[' . $index . ']';
		$id    = self::OPTION . '_' . $index;

		$languages = array(
			'fr' => __( 'Français', 'famma-core' ),
			'ar' => __( 'Derja', 'famma-core' ),
		);

		foreach ( $languages as $lang => $label ) :
			?>
			<p class="famma-field__lang">
				<label for="<?php echo esc_attr( $id . '_' . $lang ); ?>">
					<strong><?php echo esc_html( $label ); ?></strong>
				</label>
			</p>
			<input
				type="text"
				id="<?php echo esc_attr( $id . '_' . $lang ); ?>"
				name="<?php echo esc_attr( $name . '[label_' . $lang . ']' ); ?>"
				value="<?php echo esc_attr( (string) ( $slot[ 'label_' . $lang ] ?? '' ) ); ?>"
				class="large-text"
				dir="<?php echo 'ar' === $lang ? 'rtl' : 'ltr'; ?>"
			/>
			<?php
		endforeach;
		?>

		<p class="famma-field__lang">
			<label for="<?php echo esc_attr( $id . '_icon' ); ?>">
				<strong><?php esc_html_e( 'Icône', 'famma-core' ); ?></strong>
			</label>
		</p>
		<select
			id="<?php echo esc_attr( $id . '_icon' ); ?>"
			name="<?php echo esc_attr( $name . '[icon]' ); ?>"
		>
			<?php foreach ( self::icons() as $icon => $icon_label ) : ?>
				<option
					value="<?php echo esc_attr( $icon ); ?>"
					<?php selected( (string) ( $slot['icon'] ?? 'tag' ), $icon ); ?>
				>
					<?php echo esc_html( $icon_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php
		$icon_id  = absint( $slot['icon_id'] ?? 0 );
		$icon_src = $icon_id > 0 ? wp_get_attachment_image_url( $icon_id, 'thumbnail' ) : '';
		?>
		<p class="famma-field__lang">
			<strong><?php esc_html_e( 'Ou téléverser votre propre icône', 'famma-core' ); ?></strong>
		</p>
		<div class="famma-icon-upload">
			<input
				type="hidden"
				class="famma-icon-id"
				name="<?php echo esc_attr( $name . '[icon_id]' ); ?>"
				value="<?php echo esc_attr( (string) $icon_id ); ?>"
			/>
			<span class="famma-icon-preview">
				<?php if ( '' !== $icon_src ) : ?>
					<img src="<?php echo esc_url( $icon_src ); ?>" alt="" style="max-inline-size:32px;max-block-size:32px;" />
				<?php endif; ?>
			</span>
			<button type="button" class="button famma-icon-choose">
				<?php esc_html_e( 'Choisir une image', 'famma-core' ); ?>
			</button>
			<button type="button" class="button-link famma-icon-clear" <?php echo 0 === $icon_id ? 'hidden' : ''; ?>>
				<?php esc_html_e( 'Retirer', 'famma-core' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Si une image est choisie, elle remplace l’icône de la liste ci-dessus. Prévoyez un carré d’au moins 48 px, sur fond transparent : la barre est bleu marine, une image à fond blanc y ferait une pastille.', 'famma-core' ); ?>
		</p>

		<p>
			<label for="<?php echo esc_attr( $id . '_accent' ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $id . '_accent' ); ?>"
					name="<?php echo esc_attr( $name . '[accent]' ); ?>"
					value="1"
					<?php checked( ! empty( $slot['accent'] ) ); ?>
				/>
				<?php esc_html_e( 'Mettre en avant (gras orange)', 'famma-core' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Une seule promesse peut l’être : si plusieurs sont cochées, la première l’emporte.', 'famma-core' ); ?>
		</p>

		<p>
			<label for="<?php echo esc_attr( $id . '_whatsapp' ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $id . '_whatsapp' ); ?>"
					name="<?php echo esc_attr( $name . '[whatsapp]' ); ?>"
					value="1"
					<?php checked( ! empty( $slot['whatsapp'] ) ); ?>
				/>
				<?php esc_html_e( 'N’afficher que si un numéro WhatsApp est configuré', 'famma-core' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'À cocher uniquement pour une promesse qui annonce le support WhatsApp : sans numéro, elle promettrait un canal inexistant. À laisser décoché pour toute autre promesse, sinon elle disparaîtrait sans raison.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Assainit les promesses soumises.
	 *
	 * @param mixed $value Valeur brute.
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize( $value ): array {
		$value = is_array( $value ) ? $value : array();
		$icons = self::icons();
		$clean = array();

		for ( $index = 0; $index < self::SLOTS; $index++ ) {
			$slot = is_array( $value[ $index ] ?? null ) ? $value[ $index ] : array();
			$icon = sanitize_key( wp_unslash( (string) ( $slot['icon'] ?? 'tag' ) ) );

			$clean[ $index ] = array(
				'label_fr' => sanitize_text_field( wp_unslash( (string) ( $slot['label_fr'] ?? '' ) ) ),
				'label_ar' => sanitize_text_field( wp_unslash( (string) ( $slot['label_ar'] ?? '' ) ) ),
				'icon'     => isset( $icons[ $icon ] ) ? $icon : 'tag',
				'icon_id'  => $this->clean_icon_id( $slot['icon_id'] ?? 0 ),
				'accent'   => ! empty( $slot['accent'] ),
				'whatsapp' => ! empty( $slot['whatsapp'] ),
			);
		}

		return $clean;
	}

	/**
	 * Valide un identifiant de média téléversé.
	 *
	 * On ne se contente pas d'un entier : un identifiant qui ne désigne pas une
	 * image afficherait une icône cassée, et un PDF ou un ZIP passerait par la
	 * même porte. La vérification se fait donc sur le type réel de la pièce
	 * jointe, pas sur ce que le formulaire prétend.
	 *
	 * @param mixed $raw Valeur soumise.
	 * @return int 0 si l'identifiant ne désigne pas une image.
	 */
	private function clean_icon_id( $raw ): int {
		$id = absint( $raw );

		if ( 0 === $id ) {
			return 0;
		}

		return wp_attachment_is_image( $id ) ? $id : 0;
	}
}
