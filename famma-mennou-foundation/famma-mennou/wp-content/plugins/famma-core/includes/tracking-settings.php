<?php
/**
 * Identifiants de mesure — Pixel Meta, CAPI, TikTok, GA4.
 *
 * Jusqu'ici ces identifiants ne vivaient que dans des constantes du serveur :
 * changer un Pixel imposait d'éditer `wp-config.php`. La règle du projet veut
 * que tout soit réglable depuis l'administration, d'où cet écran.
 *
 * La précédence reste celle des coordonnées : **une constante définie sur le
 * serveur l'emporte toujours**. L'option ne fait que combler le vide, ce qui
 * garde `.env` + `setup.sh` faisant autorité sur une installation reproduite.
 *
 * ── Le token CAPI, et ce que son passage en base coûte ──────────────────────
 *
 * Le propriétaire a choisi, en connaissance de cause, de pouvoir saisir aussi
 * le token depuis l'administration. Ce choix a un prix, et il est réel : un
 * secret rangé dans `wp_options` se retrouve dans les sauvegardes de base, dans
 * les exports, et à portée de toute extension capable de lire les options. Une
 * constante, elle, ne quitte jamais le fichier de configuration.
 *
 * Ce que ce fichier fait pour limiter la casse, faute de pouvoir l'annuler :
 *
 * - le champ ne réaffiche **jamais** le token ; il montre une empreinte
 *   (les quatre derniers caractères) et se laisse vider ou remplacer ;
 * - soumettre un champ vide **conserve** la valeur existante, au lieu de
 *   l'effacer par inadvertance à chaque enregistrement ;
 * - l'option est créée avec `autoload = no`, donc elle n'est pas chargée en
 *   mémoire à chaque page du site, seulement quand la CAPI en a besoin ;
 * - le token n'est jamais mis en file d'attente vers le navigateur (§17) —
 *   c'est `Capi` qui l'utilise, côté serveur, et lui seul.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Écran de réglages des identifiants de mesure.
 */
final class Tracking_Settings {

	/**
	 * Groupe de réglages, au sens de la Settings API.
	 */
	private const GROUP = 'famma_tracking';

	/**
	 * Identifiant de la page d'administration.
	 */
	private const SLUG = 'famma-tracking';

	/**
	 * Option abritant le token CAPI — la seule qui soit un secret.
	 */
	private const TOKEN = 'famma_meta_access_token';

	/**
	 * Instance unique.
	 *
	 * @var Tracking_Settings|null
	 */
	private static ?Tracking_Settings $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Tracking_Settings
	 */
	public static function instance(): Tracking_Settings {
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
	}

	/**
	 * Les champs de l'écran.
	 *
	 * `secret` marque le seul champ qui ne doit jamais être réaffiché.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fields(): array {
		return array(
			array(
				'option'   => 'famma_meta_pixel_id',
				'constant' => 'FAMMA_META_PIXEL_ID',
				'label'    => __( 'Meta — identifiant du Pixel', 'famma-core' ),
				'help'     => __( 'Le numéro visible dans le Gestionnaire d’événements Meta. Il est public : il apparaît dans le code de vos pages. Laisser vide désactive la mesure Meta.', 'famma-core' ),
			),
			array(
				'option'   => self::TOKEN,
				'constant' => 'FAMMA_META_ACCESS_TOKEN',
				'label'    => __( 'Meta — token de la Conversions API', 'famma-core' ),
				'help'     => __( 'Secret. Il envoie l’achat depuis le serveur, ce qui résiste aux bloqueurs de publicité. Il n’est jamais renvoyé au navigateur ni réaffiché ici : laisser vide conserve la valeur enregistrée.', 'famma-core' ),
				'secret'   => true,
			),
			array(
				'option'   => 'famma_meta_test_event_code',
				'constant' => 'FAMMA_META_TEST_EVENT_CODE',
				'label'    => __( 'Meta — code d’événement de test', 'famma-core' ),
				'help'     => __( 'Temporaire, le temps de vérifier les événements dans Meta. À vider une fois la validation faite, sinon vos vraies conversions restent marquées comme des tests.', 'famma-core' ),
			),
			array(
				'option'   => 'famma_ga4_measurement_id',
				'constant' => 'FAMMA_GA4_MEASUREMENT_ID',
				'label'    => __( 'Google Analytics 4 — identifiant de mesure', 'famma-core' ),
				'help'     => __( 'De la forme G-XXXXXXXXXX. Laisser vide désactive Google Analytics.', 'famma-core' ),
			),
			array(
				'option'   => 'famma_tiktok_pixel_id',
				'constant' => 'FAMMA_TIKTOK_PIXEL_ID',
				'label'    => __( 'TikTok — identifiant du Pixel', 'famma-core' ),
				'help'     => __( 'Laisser vide désactive TikTok. Le code est déjà en place ; il n’attend qu’un identifiant.', 'famma-core' ),
			),
		);
	}

	/**
	 * Enregistre les options et les champs.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		/*
		 * `autoload = no` sur le token : il n'a aucune raison d'être chargé en
		 * mémoire à chaque page publique. `add_option()` ne fait rien si
		 * l'option existe déjà, l'appel est donc sans effet de bord.
		 */
		add_option( self::TOKEN, '', '', 'no' );

		add_settings_section(
			'famma_section_tracking',
			__( 'Identifiants de mesure', 'famma-core' ),
			array( $this, 'section_intro' ),
			self::SLUG
		);

		foreach ( $this->fields() as $field ) {
			register_setting(
				self::GROUP,
				$field['option'],
				array(
					'type'              => 'string',
					'sanitize_callback' => empty( $field['secret'] )
						? array( $this, 'sanitize_id' )
						: array( $this, 'sanitize_token' ),
					'default'           => '',
				)
			);

			add_settings_field(
				$field['option'],
				esc_html( (string) $field['label'] ),
				array( $this, 'render_field' ),
				self::SLUG,
				'famma_section_tracking',
				$field
			);
		}
	}

	/**
	 * Assainit un identifiant public.
	 *
	 * Les identifiants de mesure n'ont jamais autre chose que des chiffres, des
	 * lettres, des tirets et des soulignés. Tout le reste est écarté plutôt que
	 * recopié : une valeur mal collée doit se voir tout de suite.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public function sanitize_id( $value ): string {
		$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( (string) $value ) );

		return (string) $clean;
	}

	/**
	 * Assainit le token CAPI, en conservant l'existant si le champ est vide.
	 *
	 * Le champ ne réaffichant jamais le token, un enregistrement de l'écran
	 * l'effacerait à chaque fois sans cette garde — et la CAPI cesserait
	 * d'émettre sans que rien ne le signale.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return string
	 */
	public function sanitize_token( $value ): string {
		$submitted = trim( (string) wp_unslash( (string) $value ) );

		if ( '' === $submitted ) {
			return (string) get_option( self::TOKEN, '' );
		}

		// Le mot « supprimer » est la seule façon de vider volontairement.
		if ( 'supprimer' === strtolower( $submitted ) ) {
			return '';
		}

		return sanitize_text_field( $submitted );
	}

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Un champ vide désactive le service correspondant. Aucun repli, aucune valeur devinée : rien n’est mesuré tant que rien n’est saisi.', 'famma-core' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Si la même valeur est définie dans le fichier de configuration du serveur, c’est elle qui s’applique et le champ ci-dessous reste sans effet — l’écran le signale alors.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche un champ.
	 *
	 * @param array<string, mixed> $args Définition du champ.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$option   = (string) ( $args['option'] ?? '' );
		$constant = (string) ( $args['constant'] ?? '' );
		$secret   = ! empty( $args['secret'] );
		$locked   = defined( $constant ) && '' !== (string) constant( $constant );
		$stored   = (string) get_option( $option, '' );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			value="<?php echo $secret ? '' : esc_attr( $stored ); ?>"
			class="regular-text"
			autocomplete="off"
			spellcheck="false"
			<?php echo $secret ? 'placeholder="' . esc_attr__( 'Laisser vide pour conserver la valeur actuelle', 'famma-core' ) . '"' : ''; ?>
		/>

		<?php if ( $secret ) : ?>
			<p class="description">
				<?php
				if ( '' === $stored ) {
					esc_html_e( 'Aucun token enregistré : la Conversions API est inactive.', 'famma-core' );
				} else {
					printf(
						/* translators: %s: last four characters of the stored token. */
						esc_html__( 'Un token est enregistré (se termine par %s). Saisir « supprimer » l’efface.', 'famma-core' ),
						'<code>' . esc_html( substr( $stored, -4 ) ) . '</code>'
					);
				}
				?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $args['help'] ) ) : ?>
			<p class="description"><?php echo esc_html( (string) $args['help'] ); ?></p>
		<?php endif; ?>

		<?php if ( $locked ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: constant name defined on the server. */
					esc_html__( 'Valeur imposée par la constante %s du serveur : ce champ reste sans effet tant qu’elle est définie.', 'famma-core' ),
					'<code>' . esc_html( $constant ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Ajoute l'écran sous le menu FAMMA.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'famma-dashboard',
			__( 'Mesure', 'famma-core' ),
			__( 'Mesure', 'famma-core' ),
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
			<form action="options.php" method="post" autocomplete="off">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button( __( 'Enregistrer', 'famma-core' ) );
				?>
			</form>
		</div>
		<?php
	}
}
