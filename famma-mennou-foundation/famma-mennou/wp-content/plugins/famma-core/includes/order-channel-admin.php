<?php
/**
 * Canal de commande — écran de réglage FAMMA → Canaux de commande.
 *
 * La liste des canaux manuels, un par ligne (règle « tout paramétrable
 * depuis l'admin »). La colonne et le filtre de la liste des commandes
 * vivent dans `order-channel-list.php`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Réglage de la liste des canaux.
 */
final class Order_Channel_Admin {

	/** Identifiant de l'écran de réglage. */
	private const SLUG = 'famma-order-channels';

	/** Groupe de réglages. */
	private const GROUP = 'famma_order_channels_group';

	/** Nombre maximal de canaux manuels. */
	private const MAX_CHANNELS = 15;

	/** Longueur maximale d'un libellé. */
	private const LABEL_MAX = 40;

	/**
	 * Instance partagée.
	 *
	 * @var Order_Channel_Admin|null
	 */
	private static ?Order_Channel_Admin $i = null;

	/**
	 * Renvoie l'instance partagée, crochets posés au premier appel.
	 *
	 * @return Order_Channel_Admin
	 */
	public static function instance(): Order_Channel_Admin {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Crochets d'administration.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( $this, 'capability' ) );
	}

	/**
	 * Un gestionnaire de boutique peut régler les canaux.
	 *
	 * @return string
	 */
	public function capability(): string {
		return 'manage_woocommerce';
	}

	/**
	 * Ajoute l'écran sous le menu FAMMA.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'famma-dashboard',
			__( 'Canaux de commande', 'famma-core' ),
			__( 'Canaux de commande', 'famma-core' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enregistre l'option des canaux.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Order_Channel::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Transforme la zone de texte (un canal par ligne) en liste identifiant => libellé.
	 *
	 * Un libellé déjà connu garde son identifiant ; « Site » est réservé.
	 *
	 * @param mixed $input Valeur envoyée.
	 * @return array<string, string>
	 */
	public function sanitize( $input ): array {
		// Déjà sous forme de liste (second passage de WordPress) : on la renettoie.
		$pairs = array();
		if ( is_array( $input ) ) {
			foreach ( $input as $slug => $label ) {
				if ( is_scalar( $label ) ) {
					$pairs[] = array( sanitize_key( (string) $slug ), (string) $label );
				}
			}
		} else {
			foreach ( preg_split( '/\r\n|\r|\n/', is_scalar( $input ) ? (string) $input : '' ) as $line ) {
				$pairs[] = array( '', $line );
			}
		}
		$known = array_flip( Order_Channel::channels() );
		$out   = array();
		foreach ( $pairs as list( $given, $line ) ) {
			$label = mb_substr( sanitize_text_field( $line ), 0, self::LABEL_MAX );
			if ( '' === $label ) {
				continue;
			}
			$slug = '' !== $given ? $given : ( $known[ $label ] ?? sanitize_key( sanitize_title( remove_accents( $label ) ) ) );
			if ( '' === $slug || Order_Channel::SITE === $slug || isset( $out[ $slug ] ) ) {
				continue;
			}
			$out[ $slug ] = $label;
			if ( count( $out ) >= self::MAX_CHANNELS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Affiche l'écran de réglage.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$lines = implode( "\n", Order_Channel::channels() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Canaux proposés sur une commande saisie à la main (reçue par message ou par téléphone). Une commande passée sur le site est notée « Site » automatiquement.', 'famma-core' ); ?></p>
			<p><?php esc_html_e( 'Ces canaux restent internes : ils ne changent rien à ce qui est envoyé à Meta.', 'famma-core' ); ?></p>
			<?php settings_errors(); ?>
			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>
				<p><label for="famma-order-channels"><strong><?php esc_html_e( 'Un canal par ligne', 'famma-core' ); ?></strong></label></p>
				<textarea id="famma-order-channels" name="<?php echo esc_attr( Order_Channel::OPTION ); ?>" rows="8" cols="40" class="large-text" style="max-width:420px"><?php echo esc_textarea( $lines ); ?></textarea>
				<p class="description">
					<?php
					/* translators: %d: maximum number of channels. */
					echo esc_html( sprintf( __( '%d canaux au plus. Renommer un canal en crée un nouveau : les commandes déjà notées gardent l’ancien.', 'famma-core' ), self::MAX_CHANNELS ) );
					?>
				</p>
				<?php submit_button( __( 'Enregistrer', 'famma-core' ) ); ?>
			</form>
		</div>
		<?php
	}
}
