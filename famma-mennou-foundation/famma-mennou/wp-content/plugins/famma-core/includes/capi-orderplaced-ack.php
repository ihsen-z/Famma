<?php
/**
 * « Acquitter » les alertes OrderPlaced manquant (phase B, lot 4, relecture I2).
 *
 * Une alerte de santé doit toujours pouvoir être levée par l'administrateur,
 * même quand « Renvoyer » ne peut rien (tentatives épuisées, refus trop
 * ancien). Le bouton s'affiche dans le panneau de santé de FAMMA → Mesure
 * tant que des commandes sont signalées ; il acquitte les commandes que la
 * surveillance signale AU MOMENT de l'action (relues côté serveur : aucun
 * identifiant n'est pris dans la requête). Méta `_famma_orderplaced_ack` et
 * note de commande ; le rapprochement continue de montrer l'OrderPlaced
 * manquant. Sécurité : nonce, `manage_woocommerce`, admin-post sans nopriv.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Acquittement des commandes signalées par `Capi_Orderplaced_Watch`.
 */
final class Capi_Orderplaced_Ack {

	/**
	 * Méta de commande : date de l'acquittement.
	 */
	public const META = '_famma_orderplaced_ack';

	/**
	 * Action admin-post et nonce.
	 */
	public const ACTION = 'famma_capi_orderplaced_ack';

	/**
	 * Instance unique.
	 *
	 * @var Capi_Orderplaced_Ack|null
	 */
	private static ?Capi_Orderplaced_Ack $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Orderplaced_Ack
	 */
	public static function instance(): Capi_Orderplaced_Ack {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_post_' . self::ACTION, array( self::$i, 'handle' ) );
		}
		return self::$i;
	}

	/**
	 * L'alerte OrderPlaced de cette commande a-t-elle été acquittée ?
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function acknowledged( \WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::META );
	}

	/**
	 * Action « Acquitter » : nonce, capacité, puis méta et note sur chaque commande signalée.
	 *
	 * @return void
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}
		$count = self::acknowledge_all( time() );
		Capi_Health::evaluate();
		wp_safe_redirect( add_query_arg( 'famma_capi_op_ack', (string) $count, admin_url( 'admin.php?page=famma-tracking' ) ) );
		exit;
	}

	/**
	 * Acquitte toutes les commandes signalées (manquantes ou perdues).
	 *
	 * @param int $now Horloge.
	 * @return int Commandes acquittées.
	 */
	public static function acknowledge_all( int $now ): int {
		$sorted = Capi_Orderplaced_Watch::sort( $now );
		$count  = 0;
		foreach ( array_keys( $sorted['missing'] + $sorted['lost'] ) as $order_id ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order instanceof \WC_Order || self::acknowledged( $order ) ) {
				continue;
			}
			$order->update_meta_data( self::META, (string) $now );
			$order->add_order_note( __( 'Alerte « OrderPlaced manquant » acquittée dans FAMMA → Mesure : cette commande ne compte plus pour l’état de santé de la mesure.', 'famma-core' ), 0, true );
			$order->save();
			++$count;
		}
		return $count;
	}

	/**
	 * Bouton du panneau de santé, affiché seulement si des commandes sont signalées.
	 *
	 * @param array<string, mixed> $state État enregistré.
	 * @return void
	 */
	public static function render( array $state ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nombre affiché seulement, en lecture seule.
		if ( isset( $_GET['famma_capi_op_ack'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idem.
			$done = absint( wp_unslash( $_GET['famma_capi_op_ack'] ) );
			/* translators: %d: number of acknowledged orders. */
			printf( '<div class="notice notice-success inline"><p>%s</p></div>', esc_html( sprintf( __( 'Alertes OrderPlaced acquittées : %d commande(s).', 'famma-core' ), $done ) ) );
		}
		$codes = array_column( (array) $state['raisons'], 'code' );
		if ( ! current_user_can( 'manage_woocommerce' ) || array() === array_intersect( $codes, array( 'orderplaced_missing', 'orderplaced_lost' ) ) ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION ); ?>
			<?php submit_button( __( 'Acquitter les alertes OrderPlaced', 'famma-core' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
}
