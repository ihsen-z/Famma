<?php
/**
 * Écran FAMMA → Nouveau look : squelette de la page.
 *
 * Mode, résumé, recherche, onglets et panneaux. Sans script, les cinq pages
 * s'affichent l'une sous l'autre et le formulaire s'enregistre pareil.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Rendu de l'écran « Nouveau look ».
 */
final class New_Look_Admin_View {

	/**
	 * Affiche l'écran.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( New_Look_Admin::CAP ) ) {
			return;
		}

		$settings = New_Look::settings();
		$bricks   = New_Look_Registry::bricks();
		$states   = array();
		foreach ( $bricks as $key => $brick ) {
			$states[ $key ] = New_Look_Admin_State::of( $key, $brick, $settings );
		}

		// Simple drapeau d'affichage posé par la redirection après enregistrement.
		$saved = isset( $_GET['famma_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap fnl">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Nouveau look', 'famma-core' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir le site', 'famma-core' ); ?></a>
			<hr class="wp-header-end" />

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés. Le cache des pages a été vidé.', 'famma-core' ); ?></p></div>
			<?php endif; ?>

			<p class="fnl-lead"><?php esc_html_e( 'Ce que vous cochez ici change le site. Chaque bloc dit ce qu’il fait, ce dont il a besoin et s’il est vraiment visible ; ses textes français et derja se modifient juste en dessous.', 'famma-core' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fnl-form" id="fnl-form">
				<input type="hidden" name="action" value="<?php echo esc_attr( New_Look_Admin::ACTION ); ?>" />
				<input type="hidden" name="famma_tab" id="fnl-tab" value="site" />
				<?php wp_nonce_field( New_Look_Admin::ACTION ); ?>

				<?php self::mode( $settings, $states ); ?>

				<div class="fnl-tools" hidden>
					<label class="screen-reader-text" for="fnl-q"><?php esc_html_e( 'Chercher un réglage ou un texte', 'famma-core' ); ?></label>
					<input type="search" id="fnl-q" class="fnl-search" placeholder="<?php esc_attr_e( 'Chercher un réglage ou un texte (ex. livraison, WhatsApp, adresse)', 'famma-core' ); ?>" />
				</div>

				<div class="fnl-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Pages du site', 'famma-core' ); ?>" hidden>
					<?php foreach ( New_Look_Registry::pages() as $page ) : ?>
						<button type="button" class="fnl-tab" role="tab" id="<?php echo esc_attr( 'fnl-t-' . $page ); ?>" aria-controls="<?php echo esc_attr( 'fnl-p-' . $page ); ?>" aria-selected="false" data-tab="<?php echo esc_attr( $page ); ?>">
							<?php echo esc_html( New_Look_Admin_State::page( $page )['title'] ); ?>
							<span class="fnl-tab__n"><?php echo esc_html( (string) count( New_Look_Registry::for_page( $page ) ) ); ?></span>
							<span class="fnl-tab__hit" hidden></span>
						</button>
					<?php endforeach; ?>
				</div>

				<?php
				foreach ( New_Look_Registry::pages() as $page ) {
					self::panel( $page, $bricks, $states );
				}
				?>

				<div class="fnl-save">
					<span class="fnl-save__state" id="fnl-dirty" aria-live="polite"><?php esc_html_e( 'Aucune modification', 'famma-core' ); ?></span>
					<?php submit_button( __( 'Enregistrer les modifications', 'famma-core' ), 'primary', 'fnl-submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Choix du mode et résumé des états.
	 *
	 * @param array<string, mixed>  $settings Réglages en cours.
	 * @param array<string, string> $states   État de chaque brique.
	 * @return void
	 */
	private static function mode( array $settings, array $states ): void {
		$name = New_Look::OPTION . '[mode]';
		?>
		<section class="fnl-mode" aria-labelledby="fnl-mode-title">
			<div class="fnl-mode__main">
				<h2 id="fnl-mode-title"><?php esc_html_e( 'Qui voit le nouveau look ?', 'famma-core' ); ?></h2>
				<fieldset class="fnl-seg">
					<legend class="screen-reader-text"><?php esc_html_e( 'Qui voit le nouveau look ?', 'famma-core' ); ?></legend>
					<?php foreach ( New_Look::modes() as $mode => $meta ) : ?>
						<label class="fnl-seg__opt">
							<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $mode ); ?>" aria-describedby="<?php echo esc_attr( 'fnl-mode-help-' . $mode ); ?>" <?php checked( $settings['mode'], $mode ); ?> />
							<?php echo esc_html( $meta['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php foreach ( New_Look::modes() as $mode => $meta ) : ?>
					<p class="description fnl-mode__help" id="<?php echo esc_attr( 'fnl-mode-help-' . $mode ); ?>" data-mode="<?php echo esc_attr( $mode ); ?>" <?php echo $settings['mode'] === $mode ? '' : 'hidden'; ?>><?php echo esc_html( $meta['help'] ); ?></p>
				<?php endforeach; ?>
			</div>
			<ul class="fnl-summary" id="fnl-summary" aria-live="polite">
				<?php self::summary( $states ); ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Lignes du résumé : combien de briques dans chaque état.
	 *
	 * @param array<string, string> $states État de chaque brique.
	 * @return void
	 */
	private static function summary( array $states ): void {
		$counts = array(
			'all'    => 0,
			'admins' => 0,
			'warn'   => 0,
			'off'    => 0,
		);

		foreach ( $states as $state ) {
			$group = match ( $state ) {
				'all', 'admins'     => $state,
				'missing', 'wait'   => 'warn',
				default             => 'off',
			};
			++$counts[ $group ];
		}

		foreach ( New_Look_Admin_State::summary_labels() as $group => $label ) {
			if ( 0 === $counts[ $group ] && 'off' !== $group ) {
				continue;
			}
			printf(
				'<li class="fnl-pill fnl-pill--%1$s">%2$s</li>',
				esc_attr( $group ),
				esc_html( sprintf( $label, $counts[ $group ] ) )
			);
		}
	}

	/**
	 * Panneau d'une page : ses briques, puis ses autres textes.
	 *
	 * @param string                              $page   Clé de page.
	 * @param array<string, array<string, mixed>> $bricks Toutes les briques.
	 * @param array<string, string>               $states État de chaque brique.
	 * @return void
	 */
	private static function panel( string $page, array $bricks, array $states ): void {
		$meta = New_Look_Admin_State::page( $page );
		$view = New_Look_Admin_State::view_url( $page );
		$used = array();
		?>
		<section class="fnl-panel" id="<?php echo esc_attr( 'fnl-p-' . $page ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( 'fnl-t-' . $page ); ?>" data-page="<?php echo esc_attr( $page ); ?>">
			<div class="fnl-panel__top">
				<div>
					<h2 class="fnl-panel__title"><?php echo esc_html( $meta['title'] ); ?></h2>
					<p class="fnl-panel__intro"><?php echo esc_html( $meta['intro'] ); ?></p>
				</div>
				<?php if ( '' !== $view ) : ?>
					<a class="button" href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $meta['view'] ); ?> <span aria-hidden="true">↗</span></a>
				<?php endif; ?>
			</div>
			<?php
			foreach ( $bricks as $key => $brick ) {
				if ( $brick['page'] !== $page ) {
					continue;
				}
				$used = array_merge( $used, New_Look_Registry::text_keys( $brick ) );
				New_Look_Admin_Fields::brick( $key, $brick, $states[ $key ], $bricks );
			}
			?>
			<p class="fnl-nomatch" hidden></p>
			<?php New_Look_Admin_Fields::other( $page, $used ); ?>
		</section>
		<?php
	}
}
