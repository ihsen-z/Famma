<?php
/**
 * Saisies des visiteurs — rendu de la page et de l'encadré de session.
 *
 * Page de travail de l'équipe : compteurs par statut (cliquables), recherche
 * et filtres, tableau avec statut et remarque modifiables sur place, bouton
 * « Créer la commande », exports. Échappement tardif partout (règle 4) :
 * chaque valeur tapée par un visiteur passe par `esc_html()` / `esc_attr()` /
 * `esc_url()` / `esc_textarea()`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Page « Saisies à relancer » et encadré « Saisies » de la chronologie.
 */
final class Visitor_Inputs_Views {

	/**
	 * Lignes par page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Libellés des statuts.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return array(
			'open'        => __( 'À relancer', 'famma-core' ),
			'called'      => __( 'Appelé', 'famma-core' ),
			'unreachable' => __( 'Injoignable', 'famma-core' ),
			'converted'   => __( 'Commandé', 'famma-core' ),
			'refused'     => __( 'Pas intéressé', 'famma-core' ),
			'closed'      => __( 'Clos', 'famma-core' ),
		);
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view selectors.
		$f     = Visitor_Inputs_Rows::filters( $_GET );
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$flags = array(
			'saved' => isset( $_GET['saved'] ),
			'busy'  => isset( $_GET['busy'] ),
			'done'  => isset( $_GET['done'] ) ? absint( wp_unslash( $_GET['done'] ) ) : -1,
			'dup'   => isset( $_GET['dup'] ) ? absint( wp_unslash( $_GET['dup'] ) ) : 0,
			'row'   => isset( $_GET['row'] ) ? absint( wp_unslash( $_GET['row'] ) ) : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$counts = Visitor_Inputs_Rows::counts( $f );
		$total  = 'all' === $f['status'] ? array_sum( $counts ) : (int) ( $counts[ $f['status'] ] ?? 0 );

		echo '<div class="wrap famma-inputs">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Saisies à relancer', 'famma-core' ) . '</h1>';
		echo '<p class="famma-inputs__lead">' . esc_html__( 'Ce que les visiteurs ont tapé dans le formulaire de commande sans le valider. Appelez, notez le résultat, puis créez la commande en un clic. Une ligne passe à « Commandé » toute seule si le client commande lui-même.', 'famma-core' ) . '</p>';
		self::notices( $flags );
		echo '<div class="famma-inputs__fresh notice notice-info" hidden><p><a href="">&nbsp;</a></p></div>';
		self::cards( $counts, $f );
		self::toolbar( $f );
		self::table( Visitor_Inputs_Rows::rows( $f, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ), $f );
		self::pagination( $f, $paged, $total );
		self::settings_form();
		echo '</div>';
	}

	/**
	 * Messages après une action.
	 *
	 * @param array{saved: bool, busy: bool, done: int, dup: int, row: int} $flags Paramètres de retour.
	 * @return void
	 */
	private static function notices( array $flags ): void {
		$twin = $flags['dup'] ? wc_get_order( $flags['dup'] ) : false;
		if ( $twin instanceof \WC_Order && $flags['row'] ) {
			printf(
				'<div class="notice notice-warning famma-inputs__dup"><p>%1$s <a href="%2$s">#%3$s · %4$s</a></p>%5$s</div>',
				esc_html__( 'Ce numéro a déjà une commande récente :', 'famma-core' ),
				esc_url( $twin->get_edit_order_url() ),
				esc_html( $twin->get_order_number() ),
				esc_html( wc_get_order_status_name( $twin->get_status() ) ),
				Visitor_Inputs_Order::button( $flags['row'], true ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
			);
		}
		if ( $flags['saved'] ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Réglages enregistrés. Purgez le cache LiteSpeed pour qu\'ils s\'appliquent aux pages déjà en cache.', 'famma-core' ) . '</p></div>';
		}
		if ( $flags['busy'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Cette commande est déjà en cours de création. Actualisez dans un instant.', 'famma-core' ) . '</p></div>';
		}
		if ( 1 === $flags['done'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ligne mise à jour.', 'famma-core' ) . '</p></div>';
		} elseif ( 0 === $flags['done'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'L\'action n\'a pas pu aboutir.', 'famma-core' ) . '</p></div>';
		}
		if ( ! Visitor_Inputs::instance()->enabled() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'La capture des saisies est désactivée.', 'famma-core' ) . '</p></div>';
		}
	}

	/**
	 * Compteurs par statut, cliquables, et taux de récupération.
	 *
	 * @param array<string, int>                                        $counts Lignes par statut.
	 * @param array{status: string, s: string, product: int, days: int} $f      Filtres.
	 * @return void
	 */
	private static function cards( array $counts, array $f ): void {
		$all  = array_sum( $counts );
		$keep = array_filter(
			array(
				's'       => $f['s'],
				'product' => $f['product'],
				'days'    => $f['days'],
			)
		);
		echo '<nav class="famma-inputs__cards" aria-label="' . esc_attr__( 'Statuts', 'famma-core' ) . '">';
		foreach ( self::status_labels() + array( 'all' => __( 'Tout', 'famma-core' ) ) as $key => $label ) {
			$n = 'all' === $key ? $all : (int) $counts[ $key ];
			printf(
				'<a class="famma-inputs__card famma-inputs__card--%1$s%2$s" href="%3$s" data-status="%1$s"><span class="famma-inputs__count">%4$d</span><span class="famma-inputs__label">%5$s</span></a>',
				esc_attr( $key ),
				$key === $f['status'] ? ' is-current' : '',
				esc_url( Visitor_Inputs_Admin::url( array( 'status' => $key ) + $keep ) ),
				(int) $n,
				esc_html( $label )
			);
		}
		echo '</nav>';
		if ( $all > 0 ) {
			/* translators: %s: share of entries turned into orders. */
			echo '<p class="famma-inputs__rate">' . esc_html( sprintf( __( 'Taux de récupération : %s des saisies sont devenues des commandes.', 'famma-core' ), round( 100 * $counts['converted'] / $all ) . ' %' ) ) . '</p>';
		}
	}

	/**
	 * Recherche, filtres produit et période, exports des filtres en vigueur.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f Filtres.
	 * @return void
	 */
	private static function toolbar( array $f ): void {
		$periods = array(
			0  => __( 'Toute la période', 'famma-core' ),
			1  => __( '24 dernières heures', 'famma-core' ),
			7  => __( '7 derniers jours', 'famma-core' ),
			30 => __( '30 derniers jours', 'famma-core' ),
		);
		echo '<div class="famma-inputs__toolbar"><form method="get" class="famma-inputs__filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( Visitor_Inputs_Admin::SLUG ) . '"><input type="hidden" name="status" value="' . esc_attr( $f['status'] ) . '">';
		echo '<input type="search" name="s" value="' . esc_attr( $f['s'] ) . '" placeholder="' . esc_attr__( 'Nom, téléphone, adresse, remarque…', 'famma-core' ) . '" aria-label="' . esc_attr__( 'Rechercher', 'famma-core' ) . '">';
		echo '<select name="product" aria-label="' . esc_attr__( 'Produit', 'famma-core' ) . '"><option value="0">' . esc_html__( 'Tous les produits', 'famma-core' ) . '</option>';
		foreach ( Visitor_Inputs_Rows::products() as $id => $title ) {
			echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $f['product'], $id, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select><select name="days" aria-label="' . esc_attr__( 'Période', 'famma-core' ) . '">';
		foreach ( $periods as $days => $label ) {
			echo '<option value="' . esc_attr( (string) $days ) . '"' . selected( $f['days'], $days, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><button type="submit" class="button">' . esc_html__( 'Filtrer', 'famma-core' ) . '</button>';
		if ( '' !== $f['s'] || $f['product'] || $f['days'] ) {
			echo ' <a class="button-link" href="' . esc_url( Visitor_Inputs_Admin::url( array( 'status' => $f['status'] ) ) ) . '">' . esc_html__( 'Effacer les filtres', 'famma-core' ) . '</a>';
		}
		echo '</form><div class="famma-inputs__exports">';
		self::export_form( Visitor_Inputs_Export::ACTION_XLSX, __( 'Exporter en Excel', 'famma-core' ), 'button-primary', $f );
		self::export_form( Visitor_Inputs_Export::ACTION_AUDIENCE, __( 'Audience Meta (CSV)', 'famma-core' ), '', $f );
		echo '</div></div>';
	}

	/**
	 * Formulaire d'export portant les filtres en vigueur.
	 *
	 * @param string                                                    $action Action admin-post.
	 * @param string                                                    $label  Texte du bouton.
	 * @param string                                                    $extra_class Classe de bouton en plus.
	 * @param array{status: string, s: string, product: int, days: int} $f      Filtres.
	 * @return void
	 */
	private static function export_form( string $action, string $label, string $extra_class, array $f ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		foreach ( $f as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		echo '<button type="submit" class="button ' . esc_attr( $extra_class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Tableau des lignes.
	 *
	 * @param array<int, array<string, string>>                         $rows Lignes.
	 * @param array{status: string, s: string, product: int, days: int} $f    Filtres (pour revenir).
	 * @return void
	 */
	private static function table( array $rows, array $f ): void {
		if ( array() === $rows ) {
			echo '<p class="famma-inputs__empty">' . esc_html__( 'Aucune saisie pour ce filtre.', 'famma-core' ) . '</p>';
			return;
		}
		echo '<div class="famma-inputs__scroll"><table class="widefat striped famma-inputs__table"><thead><tr>';
		foreach ( array( __( 'Saisie', 'famma-core' ), __( 'Client', 'famma-core' ), __( 'Adresse', 'famma-core' ), __( 'Produit', 'famma-core' ), __( 'Source', 'famma-core' ), __( 'Statut', 'famma-core' ), __( 'Remarque', 'famma-core' ), __( 'Actions', 'famma-core' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			Visitor_Inputs_Row_View::render( $row, $f );
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Pagination qui garde les filtres.
	 *
	 * @param array{status: string, s: string, product: int, days: int} $f     Filtres.
	 * @param int                                                       $paged Page courante.
	 * @param int                                                       $total Lignes au total.
	 * @return void
	 */
	private static function pagination( array $f, int $paged, int $total ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}
		echo '<p class="famma-inputs__pages">';
		for ( $p = 1; $p <= $pages; $p++ ) {
			$url = Visitor_Inputs_Admin::url( array_filter( $f ) + array( 'paged' => $p ) );
			echo $p === $paged ? ' <strong>' . (int) $p . '</strong>' : ' <a href="' . esc_url( $url ) . '">' . (int) $p . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Réglages, repliés par défaut.
	 *
	 * @return void
	 */
	private static function settings_form(): void {
		$store = Visitor_Inputs::instance();
		echo '<details class="famma-inputs__settings"><summary>' . esc_html__( 'Réglages de la capture', 'famma-core' ) . '</summary>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( Visitor_Inputs_Admin::ACTION_SETTINGS );
		echo '<input type="hidden" name="action" value="' . esc_attr( Visitor_Inputs_Admin::ACTION_SETTINGS ) . '">';
		echo '<table class="form-table"><tr><th scope="row">' . esc_html__( 'Capture des saisies', 'famma-core' ) . '</th><td><label><input type="checkbox" name="enabled" value="1"' . checked( $store->enabled(), true, false ) . '> ' . esc_html__( 'Enregistrer ce que les visiteurs tapent dans le formulaire de commande', 'famma-core' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="famma-inputs-days">' . esc_html__( 'Conservation (jours)', 'famma-core' ) . '</label></th><td><input type="number" id="famma-inputs-days" name="days" min="7" max="180" value="' . esc_attr( (string) $store->retention_days() ) . '"></td></tr></table>';
		submit_button( __( 'Enregistrer les réglages', 'famma-core' ) );
		echo '</form></details>';
	}

	/**
	 * Encadré « Saisies » en tête de la chronologie d'une session.
	 *
	 * @param string $session_id Session.
	 * @return void
	 */
	public static function session_panel( string $session_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$row = Visitor_Inputs::instance()->for_session( $session_id );
		if ( null === $row ) {
			return;
		}
		$states = Visitor_Inputs::states();
		$parts  = array(
			__( 'Nom', 'famma-core' )         => $row['first_name'],
			__( 'Téléphone', 'famma-core' )   => $row['phone'],
			__( 'Gouvernorat', 'famma-core' ) => $states[ $row['state'] ] ?? '',
			__( 'Adresse', 'famma-core' )     => trim( $row['address'] . ' ' . $row['city'] ),
			__( 'Taille', 'famma-core' )      => $row['size'],
			__( 'Quantité', 'famma-core' )    => (int) $row['quantity'] ? (string) (int) $row['quantity'] : '',
			__( 'Statut', 'famma-core' )      => self::status_labels()[ $row['status'] ] ?? $row['status'],
			__( 'Remarque', 'famma-core' )    => $row['note'] ?? '',
		);
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Saisies de cette session', 'famma-core' ) . '</strong> — ';
		$out = array();
		foreach ( $parts as $label => $value ) {
			if ( '' !== (string) $value ) {
				$out[] = esc_html( $label . ' : ' . $value );
			}
		}
		echo implode( ' · ', $out ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part escaped above.
	}
}
