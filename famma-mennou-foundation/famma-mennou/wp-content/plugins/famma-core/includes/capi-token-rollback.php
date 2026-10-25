<?php
/**
 * Retour arrière vers famma-core 0.3.1 sans perdre le jeton CAPI (relecture du lot 5, SEC-1).
 *
 * La 0.3.2 chiffre le jeton enregistré dès sa première lecture
 * (`Capi_Token_Vault`). La 0.3.1 lit la même option sans la déchiffrer : une
 * fois ses fichiers remis, elle enverrait « famma-enc:v1:… » comme jeton,
 * Meta refuserait chaque OrderPlaced et chaque Purchase, et rien ne
 * l'alerterait (ni état de santé, ni relance en 0.3.1).
 *
 * Avant de remettre les fichiers de la 0.3.1, « Remettre le jeton en clair »
 * (FAMMA → Mesure) réécrit le jeton en clair et suspend son rechiffrement
 * 7 jours. Sans accès à l'administration :
 * `wp eval 'var_export( Famma\Core\Capi_Token_Vault::unseal() );'`.
 * « Rechiffrer maintenant » annule la préparation. Un jeton posé en constante
 * `FAMMA_META_ACCESS_TOKEN` n'est jamais chiffré : rien à préparer.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Actions « Remettre le jeton en clair » et « Rechiffrer maintenant », et leur bloc dans « Mesure ».
 */
final class Capi_Token_Rollback {

	/**
	 * Action admin-post et nonce : remise en clair.
	 */
	public const UNSEAL = 'famma_capi_token_unseal';

	/**
	 * Action admin-post et nonce : rechiffrement.
	 */
	public const RESEAL = 'famma_capi_token_reseal';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Token_Rollback|null
	 */
	private static ?Capi_Token_Rollback $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Token_Rollback
	 */
	public static function instance(): Capi_Token_Rollback {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::UNSEAL, array( self::$i, 'handle_unseal' ) );
			add_action( 'admin_post_' . self::RESEAL, array( self::$i, 'handle_reseal' ) );
		}
		return self::$i;
	}

	/**
	 * Remet le jeton en clair : nonce, capacités, puis `Capi_Token_Vault::unseal()`.
	 *
	 * @return void
	 */
	public function handle_unseal(): void {
		self::authorize( self::UNSEAL );
		self::redirect( Capi_Token_Vault::unseal() ? 'unsealed' : 'failed' );
	}

	/**
	 * Rechiffre le jeton : nonce, capacités, puis `Capi_Token_Vault::reseal()`.
	 *
	 * @return void
	 */
	public function handle_reseal(): void {
		self::authorize( self::RESEAL );
		self::redirect( Capi_Token_Vault::reseal() ? 'resealed' : 'failed' );
	}

	/**
	 * Bloc « Retour à la version 0.3.1 » de l'écran « Mesure ».
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! self::allowed() ) {
			return;
		}
		$constant = defined( 'FAMMA_META_ACCESS_TOKEN' ) && '' !== trim( (string) constant( 'FAMMA_META_ACCESS_TOKEN' ) );
		$state    = Capi_Token_Vault::state();
		$held     = 'plain' === $state && Capi_Token_Vault::held();
		if ( ! $constant && 'sealed' !== $state && ! $held ) {
			return; // Aucun jeton, jeton illisible, ou valeur fournie par un filtre : rien à préparer.
		}
		echo '<h2 id="famma-token-rollback">' . esc_html__( 'Retour à la version 0.3.1 de FAMMA Core', 'famma-core' ) . '</h2>';
		self::result();
		if ( $constant ) {
			echo '<p>' . esc_html__( 'Le jeton vient de la constante du serveur : la version 0.3.1 le lit de la même façon, rien à préparer.', 'famma-core' ) . '</p>';
			return;
		}
		if ( $held ) {
			$since  = (int) get_option( Capi_Token_Vault::HOLD, 0 );
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: date the token was stored in clear, 2: date it is encrypted again. */
					__( 'Jeton remis en clair le %1$s pour un retour à la 0.3.1. Sans retour arrière, il sera rechiffré automatiquement à partir du %2$s.', 'famma-core' ),
					wp_date( $format, $since ),
					wp_date( $format, $since + Capi_Token_Vault::HOLD_TTL )
				)
			) . '</p>';
			self::button( self::RESEAL, __( 'Rechiffrer maintenant', 'famma-core' ) );
			return;
		}
		echo '<p>' . esc_html__( 'Le jeton est chiffré, et la version 0.3.1 ne sait pas le lire : remise en place, elle l’enverrait tel quel et Meta refuserait chaque OrderPlaced et chaque Purchase, sans alerte. Juste avant de remettre ses fichiers, remettez le jeton en clair. Son rechiffrement est alors suspendu 7 jours.', 'famma-core' ) . '</p>';
		self::button( self::UNSEAL, __( 'Remettre le jeton en clair', 'famma-core' ) );
	}

	/**
	 * L'utilisateur courant peut-il gérer le jeton ? Mêmes droits que le test CAPI.
	 *
	 * @return bool
	 */
	private static function allowed(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Vérifie le nonce de l'action, puis les capacités.
	 *
	 * @param string $action Action et nonce.
	 * @return void
	 */
	private static function authorize( string $action ): void {
		check_admin_referer( $action );
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Formulaire d'une action, protégé par son nonce.
	 *
	 * @param string $action Action et nonce.
	 * @param string $label  Texte du bouton.
	 * @return void
	 */
	private static function button( string $action, string $label ): void {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<?php wp_nonce_field( $action ); ?>
			<?php submit_button( $label, 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Retour vers « Mesure » avec un identifiant d'issue fixe, jamais le jeton.
	 *
	 * @param string $result unsealed, resealed ou failed.
	 * @return void
	 */
	private static function redirect( string $result ): void {
		wp_safe_redirect( add_query_arg( 'famma_token_rollback', $result, admin_url( 'admin.php?page=famma-tracking' ) ) . '#famma-token-rollback' );
		exit;
	}

	/**
	 * Avis de l'issue de la dernière action.
	 *
	 * @return void
	 */
	private static function result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identifiant d'issue fixe, en lecture seule.
		$result   = isset( $_GET['famma_token_rollback'] ) ? sanitize_key( wp_unslash( $_GET['famma_token_rollback'] ) ) : '';
		$messages = array(
			'unsealed' => __( 'Jeton remis en clair : vous pouvez remettre les fichiers de la 0.3.1.', 'famma-core' ),
			'resealed' => __( 'Jeton de nouveau chiffré.', 'famma-core' ),
			'failed'   => __( 'Action impossible : aucun jeton lisible n’est enregistré.', 'famma-core' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( 'failed' === $result ? 'warning' : 'success' ), esc_html( $messages[ $result ] ) );
		}
	}
}
