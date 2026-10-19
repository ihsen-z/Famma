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
 * - depuis la 0.3.2, le jeton y est chiffré (`Capi_Token_Vault`) : il
 *   n'apparaît plus en clair sur `/wp-admin/options.php` ni dans une copie de
 *   la base ;
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
	public const GROUP = 'famma_tracking';

	/**
	 * Identifiant de la page d'administration.
	 */
	public const SLUG = 'famma-tracking';

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
		add_action( 'admin_post_famma_capi_test', array( $this, 'send_test_event' ) );
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
				'help'     => __( 'Format TEST suivi de chiffres, copié depuis « Tester les événements » de Meta. Utilisé par le test dédié ci-dessous, et par le mode test s’il est activé sur le serveur : sans code valide, ce mode bloque alors les envois au lieu de les faire partir en réel.', 'famma-core' ),
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
		register_setting(
			self::GROUP,
			'famma_meta_orderplaced_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
		add_settings_field(
			'famma_meta_orderplaced_enabled',
			__( 'Meta — commande enregistrée (OrderPlaced)', 'famma-core' ),
			array( $this, 'render_orderplaced_toggle' ),
			self::SLUG,
			'famma_section_tracking'
		);

		foreach ( $this->fields() as $field ) {
			register_setting(
				self::GROUP,
				$field['option'],
				array(
					'type'              => 'string',
					'sanitize_callback' => $this->sanitizer( $field ),
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
	 * Fonction d'assainissement d'un champ.
	 *
	 * @param array<string, mixed> $field Définition du champ.
	 * @return callable
	 */
	private function sanitizer( array $field ): callable {
		if ( ! empty( $field['secret'] ) ) {
			return array( $this, 'sanitize_token' );
		}
		if ( 'famma_meta_test_event_code' === $field['option'] ) {
			return array( Measurement_Settings::class, 'sanitize_test_code' );
		}
		return array( $this, 'sanitize_id' );
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

	/** Explain and display the optional pre-delivery server event switch. */
	public function render_orderplaced_toggle(): void {
		?>
		<input type="hidden" name="famma_meta_orderplaced_enabled" value="0" />
		<label>
			<input type="checkbox" name="famma_meta_orderplaced_enabled" value="1" <?php checked( (bool) get_option( 'famma_meta_orderplaced_enabled', false ) ); ?> />
			<?php esc_html_e( 'Envoyer OrderPlaced après une commande acceptée sur le site.', 'famma-core' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Désactivé par défaut. En l’activant, les identifiants client déjà collectés pour Meta sont transmis dès la commande, avant livraison. OrderPlaced mesure une demande enregistrée ; Purchase reste réservé à la livraison encaissée. Le test synthétique fonctionne même lorsque cette option est désactivée.', 'famma-core' ); ?></p>
		<?php
	}

	/**
	 * Assainit le token CAPI, en conservant l'existant si le champ est vide.
	 *
	 * Le champ ne réaffichant jamais le token, un enregistrement de l'écran
	 * l'effacerait à chaque fois sans cette garde — et la CAPI cesserait
	 * d'émettre sans que rien ne le signale. Un nouveau jeton est chiffré
	 * avant d'être enregistré ; une valeur déjà chiffrée (migration par
	 * `Capi_Token_Vault`) passe telle quelle.
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

		if ( Capi_Token_Vault::is_sealed( $submitted ) ) {
			return $submitted;
		}

		$clean  = sanitize_text_field( $submitted );
		$sealed = Capi_Token_Vault::seal( $clean );

		return '' !== $sealed ? $sealed : $clean;
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
				} elseif ( 'broken' === Capi_Token_Vault::state() ) {
					esc_html_e( 'Le token enregistré est illisible (clés du site modifiées ?) : ressaisissez-le.', 'famma-core' );
				} else {
					printf(
						/* translators: %s: last four characters of the stored token. */
						esc_html__( 'Un token est enregistré, chiffré (se termine par %s). Saisir « supprimer » l’efface.', 'famma-core' ),
						'<code>' . esc_html( Capi_Token_Vault::last4() ) . '</code>'
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
			<?php Capi_Health_View::instance()->render_panel(); ?>
			<?php settings_errors(); ?>
			<?php $this->render_test_result(); ?>
			<form action="options.php" method="post" autocomplete="off">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button( __( 'Enregistrer', 'famma-core' ) );
				?>
			</form>
			<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
				<h2><?php esc_html_e( 'Tester les événements sans créer de commande', 'famma-core' ); ?></h2>
				<p><?php esc_html_e( 'Envoie un seul événement synthétique dans les événements de test Meta. Aucune commande, vente, donnée client ou modification de stock. Le code de test est obligatoire.', 'famma-core' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="famma_capi_test" />
					<?php wp_nonce_field( 'famma_capi_test' ); ?>
					<label for="famma_capi_test_event"><?php esc_html_e( 'Événement à tester', 'famma-core' ); ?></label>
					<select id="famma_capi_test_event" name="famma_capi_test_event" required>
						<option value="OrderPlaced"><?php esc_html_e( 'OrderPlaced — commande enregistrée', 'famma-core' ); ?></option>
						<option value="Purchase"><?php esc_html_e( 'Purchase — achat livré', 'famma-core' ); ?></option>
					</select>
					<label for="famma_capi_test_code"><?php esc_html_e( 'Code des événements de test Meta', 'famma-core' ); ?></label>
					<input id="famma_capi_test_code" name="famma_capi_test_code" type="text" required pattern="TEST[0-9]+" placeholder="TEST12345" value="<?php echo esc_attr( Config::instance()->meta_test_event_code() ); ?>" autocomplete="off" />
					<label for="famma_capi_test_value"><?php esc_html_e( 'Montant du test', 'famma-core' ); ?></label>
					<input id="famma_capi_test_value" name="famma_capi_test_value" type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,3})?" placeholder="25.000" required aria-describedby="famma_capi_test_value_help" autocomplete="off" />
					<p id="famma_capi_test_value_help">
					<?php
						/* translators: %s: configured shop currency. */
						echo esc_html( sprintf( __( 'Montant de simulation uniquement, en %s : au moins 0.001, avec trois décimales maximum (point ou virgule). Aucune vente réelle.', 'famma-core' ), get_woocommerce_currency() ) );
					?>
					</p>
					<?php submit_button( __( 'Envoyer l’événement de test', 'famma-core' ), 'secondary' ); ?>
				</form>
				<?php Capi_Probe::instance()->render(); ?>
				<?php Capi_Reconcile_View::render(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Send a synthetic test only; never create or modify a WooCommerce order. */
	public function send_test_event(): void {
		check_admin_referer( 'famma_capi_test' );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$test_code = isset( $_POST['famma_capi_test_code'] ) ? sanitize_text_field( wp_unslash( $_POST['famma_capi_test_code'] ) ) : '';
		$event     = isset( $_POST['famma_capi_test_event'] ) ? sanitize_text_field( wp_unslash( $_POST['famma_capi_test_event'] ) ) : '';
		$cfg       = Config::instance();
		if ( ! in_array( $event, array( 'OrderPlaced', 'Purchase' ), true ) || ! Config::valid_test_event_code( $test_code ) || '' === $cfg->meta_pixel_id() || '' === $cfg->meta_access_token() ) {
			$this->redirect_test_result( 'configuration' );
		}
		$raw_value = isset( $_POST['famma_capi_test_value'] ) && is_string( $_POST['famma_capi_test_value'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['famma_capi_test_value'] ) ) ) : '';
		if ( ! preg_match( '/^[0-9]+(?:[.,][0-9]{1,3})?$/D', $raw_value ) ) {
			$this->redirect_test_result( 'amount' );
		}
		$value = (float) wc_format_decimal( str_replace( ',', '.', $raw_value ), 3 );
		if ( ! is_finite( $value ) || $value < 0.001 ) {
			$this->redirect_test_result( 'amount' );
		}
		$currency = get_woocommerce_currency();
		if ( ! preg_match( '/^[A-Z]{3}$/D', $currency ) || ! array_key_exists( $currency, get_woocommerce_currencies() ) ) {
			$this->redirect_test_result( 'currency' );
		}
		if ( ! $cfg->meta_sending_allowed() ) {
			$this->redirect_test_result( 'environment' );
		}
		$payload = array(
			'test_event_code' => $test_code,
			'data'            => array(
				array(
					'event_name'       => $event,
					'event_time'       => time(),
					'event_id'         => 'famma_test_' . wp_generate_uuid4(),
					'action_source'    => 'website',
					'event_source_url' => home_url( '/' ),
					'user_data'        => array( 'em' => hash( 'sha256', 'famma-capi-test@example.invalid' ) ),
					'custom_data'      => array(
						'currency' => $currency,
						'value'    => $value,
					),
				),
			),
		);
		$token   = $cfg->meta_access_token();
		$result  = Capi_Transport::send_events( $cfg->meta_pixel_id(), $token, $payload );
		Capi_Log::write(
			array(
				'event_name' => $event,
				'event_id'   => (string) $payload['data'][0]['event_id'],
				'value'      => $value,
				'currency'   => $currency,
				'user_data'  => $payload['data'][0]['user_data'],
				'state'      => (string) $result['class'],
				'test'       => true,
			) + $result,
			$token
		);
		if ( 'transient' === $result['class'] && in_array( $result['reason'], array( 'network', 'timeout' ), true ) ) {
			$this->redirect_test_result( 'network' );
		}
		$this->redirect_test_result( in_array( $result['class'], array( 'accepted', 'accepted_warning' ), true ) ? 'accepted' : 'rejected' );
	}

	/**
	 * Redirect with a fixed result identifier, never a response body or secret.
	 *
	 * @param string $result Fixed status identifier.
	 */
	private function redirect_test_result( string $result ): void {
		wp_safe_redirect( add_query_arg( 'famma_capi_result', $result, admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/** Display fixed, escaped status text. */
	private function render_test_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only fixed notice.
		$result   = isset( $_GET['famma_capi_result'] ) ? sanitize_key( wp_unslash( $_GET['famma_capi_result'] ) ) : '';
		$messages = array(
			'accepted'      => __( 'Meta a accepté l’événement de test. Vérifiez son nom et sa réception dans Tester les événements. Aucun achat réel n’a été créé.', 'famma-core' ),
			'configuration' => __( 'Test non envoyé : renseignez le Pixel, le token CAPI et un code de test Meta valide.', 'famma-core' ),
			'amount'        => __( 'Test non envoyé : saisissez un montant positif d’au moins 0.001, avec trois décimales maximum et un point ou une virgule.', 'famma-core' ),
			'currency'      => __( 'Test non envoyé : la devise configurée dans WooCommerce est invalide.', 'famma-core' ),
			'network'       => __( 'Test non reçu : la connexion du serveur à Meta a échoué.', 'famma-core' ),
			'environment'   => __( 'Test non envoyé : ce site n’est pas la production, aucune requête ne part vers Meta.', 'famma-core' ),
			'rejected'      => __( 'Meta n’a pas confirmé la réception du test. Vérifiez le token, le Pixel et le code de test.', 'famma-core' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			echo '<div class="notice notice-' . ( 'accepted' === $result ? 'success' : 'error' ) . '"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
	}
}
