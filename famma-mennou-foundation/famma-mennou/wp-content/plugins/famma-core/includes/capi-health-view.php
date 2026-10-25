<?php
/**
 * Bandeaux de l'état de santé de la mesure Meta (lot 5, R7, A4).
 *
 * Affiché dans « Mesure » (panneau complet, même au vert), et en haut de la
 * liste des commandes et du tableau de bord FAMMA dès qu'il n'est plus vert.
 * L'avis « jeton à ressaisir » (A11) s'affiche sur tout l'écran
 * d'administration tant que le jeton chiffré reste illisible.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu des bandeaux, sans calcul : l'état vient de `Capi_Health::state()`.
 */
final class Capi_Health_View {

	/**
	 * Écrans qui reçoivent le bandeau quand l'état n'est pas vert.
	 */
	private const SCREENS = array( 'toplevel_page_famma-dashboard', 'woocommerce_page_wc-orders', 'edit-shop_order' );

	/**
	 * Instance unique.
	 *
	 * @var Capi_Health_View|null
	 */
	private static ?Capi_Health_View $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Capi_Health_View
	 */
	public static function instance(): Capi_Health_View {
		if ( null === self::$i ) {
			self::$i = new self();
			add_action( 'admin_notices', array( self::$i, 'notices' ) );
		}
		return self::$i;
	}

	/**
	 * Bandeau des écrans Commandes et Tableau de bord, et avis du jeton illisible.
	 *
	 * @return void
	 */
	public function notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$state  = Capi_Health::state();
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( in_array( $id, self::SCREENS, true ) && 'vert' !== $state['statut'] ) {
			$this->banner( $state, true );
			return;
		}
		foreach ( $state['raisons'] as $reason ) {
			if ( 'token_unreadable' === ( $reason['code'] ?? '' ) && ! str_ends_with( $id, '_page_famma-tracking' ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( Capi_Health_Rules::label( $reason ) ) );
			}
		}
	}

	/**
	 * Panneau complet de l'écran « Mesure ».
	 *
	 * @return void
	 */
	public function render_panel(): void {
		$this->banner( Capi_Health::state(), false );
	}

	/**
	 * Bandeau d'un état.
	 *
	 * @param array<string, mixed> $state   État enregistré.
	 * @param bool                 $compact Lien vers « Mesure » plutôt que le détail des vérifications.
	 * @return void
	 */
	private function banner( array $state, bool $compact ): void {
		$classes = array(
			'vert'   => 'notice-success',
			'orange' => 'notice-warning',
			'rouge'  => 'notice-error',
		);
		$titles  = array(
			'vert'   => __( 'Mesure Meta : tout va bien.', 'famma-core' ),
			'orange' => __( 'Mesure Meta : à surveiller.', 'famma-core' ),
			'rouge'  => __( 'Mesure Meta : en panne, des conversions sont perdues.', 'famma-core' ),
		);
		$status  = (string) $state['statut'];
		?>
		<div class="notice <?php echo esc_attr( $classes[ $status ] ?? 'notice-info' ); ?>">
			<p><strong><?php echo esc_html( $titles[ $status ] ?? '' ); ?></strong></p>
			<?php if ( array() !== $state['raisons'] ) : ?>
				<ul>
					<?php foreach ( $state['raisons'] as $reason ) : ?>
						<li><?php echo esc_html( Capi_Health_Rules::label( (array) $reason ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p>
				<?php if ( $compact ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=famma-tracking' ) ); ?>"><?php esc_html_e( 'Voir FAMMA → Mesure', 'famma-core' ); ?></a>
				<?php elseif ( (int) $state['verifie_le'] > 0 ) : ?>
					<?php
					/* translators: %s: date and time of the last health check. */
					echo esc_html( sprintf( __( 'Dernière vérification : %s.', 'famma-core' ), wp_date( 'd/m/Y H:i', (int) $state['verifie_le'] ) ) );
					?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
