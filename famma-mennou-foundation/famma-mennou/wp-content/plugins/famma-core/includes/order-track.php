<?php
/**
 * Suivi de commande dans « Mon compte » — case `acct_track` du nouveau look.
 *
 * Lot 3 du plan de portage de l'espace client. Ce fichier porte la règle
 * métier : quelle étape (Reçue → Confirmée → Expédiée → Livrée) correspond à
 * chaque statut COD, et quelles commandes peuvent être recommandées. Le thème
 * ne fait que dessiner la frise (inc/new-look-account-track.php).
 *
 * Les libellés sont éditables dans FAMMA → Pages (section « Mon compte »),
 * par le même mécanisme que `Shop_Content`. La version derja par défaut est
 * celle de la maquette espace client du 12/11, à relire.
 *
 * « Recommander » utilise le mécanisme natif de WooCommerce (lien
 * `order_again` signé, réservé au client de la commande) : le panier est
 * rempli aux prix du jour. On n'ajoute que le statut « Livrée », absent de la
 * liste par défaut, et le lien dans la liste des commandes.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Étapes du suivi et « Recommander ».
 */
final class Order_Track {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'account';

	/**
	 * Case du nouveau look.
	 */
	public const PART = 'acct_track';

	/**
	 * Nombre d'étapes de la frise.
	 */
	public const STEPS = 4;

	/**
	 * Date courte sous une étape (« 12 nov ») : la date complète est déjà
	 * écrite au-dessus de la frise par WooCommerce.
	 */
	private const DATE_FORMAT = 'j M';

	/**
	 * Étape atteinte par statut (sans le préfixe `wc-`). Absent : hors parcours.
	 *
	 * @var array<string, int>
	 */
	private const STEP_OF = array(
		'pending'         => 1,
		'pending-confirm' => 1,
		'on-hold'         => 1,
		'confirmed'       => 2,
		'processing'      => 2,
		'shipped'         => 3,
		'delivered'       => 4,
		'completed'       => 4,
	);

	/**
	 * Accroche les filtres de WooCommerce.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_valid_order_statuses_for_order_again', array( self::class, 'order_again_statuses' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'list_actions' ), 10, 2 );
	}

	/**
	 * La case est-elle cochée ?
	 *
	 * @return bool
	 */
	public static function on(): bool {
		return New_Look::part( self::PART );
	}

	/**
	 * Schéma des champs de la section « Mon compte ».
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$steps = array(
			1 => array( __( 'Reçue', 'famma-core' ), 'وصلتنا' ),
			2 => array( __( 'Confirmée', 'famma-core' ), 'تأكدت' ),
			3 => array( __( 'Expédiée', 'famma-core' ), 'خرجت' ),
			4 => array( __( 'Livrée', 'famma-core' ), 'وصلت' ),
		);

		$fields = array();

		foreach ( $steps as $i => $label ) {
			$fields[] = array(
				'key'        => 'track_' . $i,
				'type'       => 'text',
				/* translators: %d: numéro de l'étape. */
				'label'      => sprintf( __( 'Suivi — étape %d', 'famma-core' ), $i ),
				'help'       => 1 === $i ? __( 'Frise de chaque commande dans « Mon compte », avec la case « Mon compte : suivi en 4 étapes » du nouveau look. Un mot ou deux.', 'famma-core' ) : '',
				'default_fr' => $label[0],
				'default_ar' => $label[1],
			);
		}

		$fields[] = array(
			'key'        => 'track_due',
			'type'       => 'text',
			'label'      => __( 'Suivi — somme à payer au livreur', 'famma-core' ),
			'help'       => __( 'Sous la frise, tant que la commande n’est pas livrée. {total} est remplacé par le montant de la commande. Vide : la ligne disparaît.', 'famma-core' ),
			'default_fr' => __( 'À payer au livreur : {total}', 'famma-core' ),
			'default_ar' => 'تخلّص للّيفرور : {total}',
		);

		$fields[] = array(
			'key'        => 'track_reorder',
			'type'       => 'text',
			'label'      => __( 'Bouton « Recommander »', 'famma-core' ),
			'help'       => __( 'Dans la liste des commandes livrées : remet les mêmes articles dans le panier, aux prix du jour. Vide : le bouton disparaît de la liste.', 'famma-core' ),
			'default_fr' => __( 'Recommander', 'famma-core' ),
			'default_ar' => 'عاود اطلب',
		);

		return $fields;
	}

	/**
	 * Lit un champ dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Étape atteinte pour un statut.
	 *
	 * @param string $status Statut, avec ou sans le préfixe `wc-`.
	 * @return int 1 à 4, ou 0 hors parcours (refusée, retournée, annulée…).
	 */
	public static function step( string $status ): int {
		$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
		$step   = self::STEP_OF[ $status ] ?? 0;

		/**
		 * Filtre l'étape du suivi atteinte pour un statut de commande.
		 *
		 * @param int    $step   0 (hors parcours) à 4.
		 * @param string $status Statut sans préfixe.
		 */
		$step = (int) apply_filters( 'famma_order_track_step', $step, $status );

		return max( 0, min( self::STEPS, $step ) );
	}

	/**
	 * Tout ce que le thème doit dessiner pour une commande.
	 *
	 * @param \WC_Order $order Commande.
	 * @return array{step: int, status: string, steps: array<int, array{label: string, date: string}>, due: string}
	 */
	public static function view( \WC_Order $order ): array {
		$status = (string) $order->get_status();
		$step   = self::step( $status );
		$steps  = array();

		for ( $i = 1; $i <= self::STEPS; $i++ ) {
			$steps[ $i ] = array(
				'label' => self::text( 'track_' . $i ),
				'date'  => '',
			);
		}

		$created = $order->get_date_created();
		if ( $created ) {
			$steps[1]['date'] = wc_format_datetime( $created, self::DATE_FORMAT );
		}

		$paid = $order->get_date_paid();
		if ( 4 === $step && $paid ) {
			$steps[4]['date'] = wc_format_datetime( $paid, self::DATE_FORMAT );
		}

		$due = '';
		if ( $step >= 1 && $step < self::STEPS && ! $order->is_paid() ) {
			$line = self::text( 'track_due' );
			if ( '' !== $line ) {
				$due = str_replace( '{total}', $order->get_formatted_order_total(), esc_html( $line ) );
			}
		}

		return array(
			'step'   => $step,
			'status' => wc_get_order_status_name( $status ),
			'steps'  => $steps,
			'due'    => $due,
		);
	}

	/**
	 * Une commande livrée peut être recommandée, comme une commande terminée.
	 *
	 * @param array<int, string> $statuses Statuts autorisés.
	 * @return array<int, string>
	 */
	public static function order_again_statuses( $statuses ): array {
		$statuses = is_array( $statuses ) ? $statuses : array();

		if ( self::on() && ! in_array( 'delivered', $statuses, true ) ) {
			$statuses[] = 'delivered';
		}

		return $statuses;
	}

	/**
	 * Lien « Recommander » dans la liste des commandes.
	 *
	 * Même adresse signée que le bouton natif de WooCommerce
	 * (`woocommerce_order_again_button`) : le contrôle du client et du statut
	 * reste celui de WooCommerce.
	 *
	 * @param array<string, array{url: string, name: string}> $actions Actions de la commande.
	 * @param \WC_Order                                       $order   Commande.
	 * @return array<string, array{url: string, name: string}>
	 */
	public static function list_actions( $actions, $order ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! self::on() || ! $order instanceof \WC_Order || ! function_exists( 'wc_get_cart_url' ) ) {
			return $actions;
		}

		$label = self::text( 'track_reorder' );
		// Filtre de WooCommerce relu tel quel : même liste que son bouton natif.
		$valid = (array) apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( '' === $label || ! $order->has_status( $valid ) || ! $order->get_item_count() ) {
			return $actions;
		}

		$actions['order-again'] = array(
			'url'  => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			'name' => $label,
		);

		return $actions;
	}
}
