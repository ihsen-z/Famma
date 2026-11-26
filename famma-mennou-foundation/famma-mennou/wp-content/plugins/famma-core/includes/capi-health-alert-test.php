<?php
/**
 * Bouton « Envoyer un e-mail d'essai » des alertes de la mesure Meta (FAMMA → Mesure,
 * réévaluation du pixel, critère C6).
 *
 * Prouve que l'adresse des alertes reçoit vraiment les e-mails (l'hébergeur n'a pas de SMTP
 * configuré). Formulaire `admin-post.php` séparé du formulaire des réglages : nonce, puis
 * `manage_woocommerce`, puis envoi à l'adresse ENREGISTRÉE (jamais une adresse prise dans la
 * requête). Au plus un essai par minute (transient, compté même si l'envoi échoue). Le message
 * est marqué « essai », sans jeton ni donnée client (`Capi_Health_Alert::send_test()`).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Essai d'envoi des alertes.
 */
final class Capi_Health_Alert_Test {

	/**
	 * Action admin-post et nonce.
	 */
	public const ACTION = 'famma_capi_alert_test';

	/**
	 * Paramètre de retour portant le résultat.
	 */
	public const RESULT = 'famma_capi_alert_test_result';

	/**
	 * Transient de plafonnement.
	 */
	public const LOCK = 'famma_capi_alert_test_lock';

	/**
	 * Délai minimal entre deux essais, en secondes.
	 */
	public const INTERVAL = 60;

	/**
	 * Instance unique.
	 *
	 * @var Capi_Health_Alert_Test|null
	 */
	private static ?Capi_Health_Alert_Test $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant le hook au premier appel.
	 *
	 * @return Capi_Health_Alert_Test
	 */
	public static function instance(): Capi_Health_Alert_Test {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::ACTION, array( self::$i, 'handle' ) );
		}
		return self::$i;
	}

	/**
	 * Action du bouton : nonce, capacité, envoi, retour sur « Mesure » avec le résultat.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( add_query_arg( self::RESULT, self::run(), admin_url( 'admin.php?page=famma-tracking' ) ) );
		exit;
	}

	/**
	 * Envoie l'essai à l'adresse enregistrée.
	 *
	 * @return string `sent`, `failed`, `empty` (aucune adresse valide) ou `throttled` (moins d'une minute).
	 */
	public static function run(): string {
		$to = Capi_Health_Alert::recipient();
		if ( '' === $to ) {
			return 'empty';
		}
		if ( false !== get_transient( self::LOCK ) ) {
			return 'throttled';
		}
		set_transient( self::LOCK, time(), self::INTERVAL );
		return Capi_Health_Alert::send_test( $to ) ? 'sent' : 'failed';
	}

	/**
	 * Message de résultat, d'après le paramètre de retour.
	 *
	 * @param string $result Résultat (`sent`, `failed`, `empty`, `throttled`).
	 * @return array{0: string, 1: string} Classe de l'avis et texte, ou deux chaînes vides.
	 */
	public static function message( string $result ): array {
		switch ( $result ) {
			case 'sent':
				return array( 'notice-success', __( 'E-mail d’essai envoyé à l’adresse des alertes. Vérifiez sa réception (courrier indésirable compris).', 'famma-core' ) );
			case 'failed':
				return array( 'notice-error', __( 'Échec : le serveur n’a pas accepté l’e-mail d’essai. Les alertes ne partiront pas tant que l’envoi d’e-mails ne fonctionne pas.', 'famma-core' ) );
			case 'empty':
				return array( 'notice-warning', __( 'Aucune adresse des alertes valide enregistrée : renseignez-la et enregistrez, puis relancez l’essai.', 'famma-core' ) );
			case 'throttled':
				return array( 'notice-warning', __( 'Un essai vient déjà d’être envoyé : patientez une minute avant d’en lancer un autre.', 'famma-core' ) );
		}
		return array( '', '' );
	}

	/**
	 * Bloc de l'écran « Mesure » : résultat du dernier essai et bouton (formulaire séparé).
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- libellé de résultat seulement, en lecture seule.
		$result = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';
		$notice = self::message( $result );
		?>
		<h2><?php esc_html_e( 'E-mail d’essai des alertes', 'famma-core' ); ?></h2>
		<?php if ( '' !== $notice[0] ) : ?>
			<div class="notice <?php echo esc_attr( $notice[0] ); ?> inline"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Envoie à l’adresse des alertes enregistrée un e-mail marqué « essai », sans jeton ni donnée client, pour vérifier qu’il arrive. Un essai par minute au plus.', 'famma-core' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION ); ?>
			<?php submit_button( __( 'Envoyer un e-mail d’essai', 'famma-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
}
