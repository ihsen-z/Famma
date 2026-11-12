<?php
/**
 * Suivi de commande dans « Mon compte » — case `acct_track` du nouveau look.
 *
 * Lot 3 du plan de portage de l'espace client, aligné sur la maquette le
 * 12/11 (cartes de commande). Ce fichier porte la règle métier : quelle étape
 * (Reçue → Confirmée → Expédiée → Livrée) correspond à chaque statut COD,
 * quand chaque étape a été atteinte, et quelles commandes peuvent être
 * recommandées. Le thème ne fait que dessiner (inc/new-look-account-track.php).
 *
 * Les libellés sont éditables dans FAMMA → Pages (section « Mon compte »),
 * par le même mécanisme que `Shop_Content`. La version derja par défaut est
 * celle de la maquette espace client du 12/11, à relire.
 *
 * « Recommander » utilise le mécanisme natif de WooCommerce (lien
 * `order_again` signé, réservé au client de la commande) : le panier est
 * rempli aux prix du jour. On n'ajoute que le statut « Livrée », absent de la
 * liste par défaut.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Étapes du suivi, cartes de commande et « Recommander ».
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
	 * Méta : heure (horodatage GMT) à laquelle chaque étape a été atteinte.
	 */
	public const META_TIMES = '_famma_track_times';

	/**
	 * Date et heure sous une étape (« mar. 11 nov. · 18:40 »).
	 */
	private const STEP_FORMAT = 'D j M · H:i';

	/**
	 * Date en tête de carte (« 11 novembre »).
	 */
	private const CARD_FORMAT = 'j F';

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
	 * L'heure des étapes est notée à chaque changement de statut, case cochée
	 * ou non : quand la case sera cochée, les commandes en cours auront déjà
	 * leurs heures.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_valid_order_statuses_for_order_again', array( self::class, 'order_again_statuses' ) );
		add_action( 'woocommerce_order_status_changed', array( self::class, 'record_step' ), 20, 4 );
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
	 * Un champ du schéma.
	 *
	 * @param string $key   Clé.
	 * @param string $label Libellé dans l'admin.
	 * @param string $fr    Texte français par défaut.
	 * @param string $ar    Texte derja par défaut.
	 * @param string $help  Aide.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $label, string $fr, string $ar, string $help = '' ): array {
		return array(
			'key'        => $key,
			'type'       => 'text',
			'label'      => $label,
			'help'       => $help,
			'default_fr' => $fr,
			'default_ar' => $ar,
		);
	}

	/**
	 * Schéma des champs du suivi, dans la section « Mon compte ».
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
			$fields[] = self::field(
				'track_' . $i,
				/* translators: %d: numéro de l'étape. */
				sprintf( __( 'Suivi — étape %d', 'famma-core' ), $i ),
				$label[0],
				$label[1],
				1 === $i ? __( 'Frise de chaque commande, avec la case « Mon compte : suivi en 4 étapes » du nouveau look. Un mot ou deux.', 'famma-core' ) : ''
			);
		}

		return array_merge(
			$fields,
			array(
				self::field( 'card_number', __( 'Carte — titre', 'famma-core' ), __( 'Commande {n}', 'famma-core' ), 'طلبية {n}', __( '{n} est remplacé par le numéro de la commande.', 'famma-core' ) ),
				self::field( 'card_item', __( 'Carte — un article', 'famma-core' ), __( '1 article', 'famma-core' ), 'حاجة وحدة' ),
				self::field( 'card_items', __( 'Carte — plusieurs articles', 'famma-core' ), __( '{n} articles', 'famma-core' ), '{n} حوايج', __( '{n} est remplacé par le nombre d’articles.', 'famma-core' ) ),
				self::field( 'card_go', __( 'Carte — pastille « en cours »', 'famma-core' ), __( 'En route', 'famma-core' ), 'في الثنية' ),
				self::field( 'card_ok', __( 'Carte — pastille « livrée »', 'famma-core' ), __( 'Livrée', 'famma-core' ), 'وصلت' ),
				self::field( 'track_due', __( 'Carte — sous le total, avant livraison', 'famma-core' ), __( 'À payer au livreur', 'famma-core' ), 'تخلّص للّيفرور', __( 'Vide : la ligne disparaît.', 'famma-core' ) ),
				self::field( 'card_paid', __( 'Carte — sous le total, après livraison', 'famma-core' ), __( 'Payée à la livraison', 'famma-core' ), 'تخلّصت كي وصلت' ),
				self::field( 'card_note_go', __( 'Carte — note avant livraison', 'famma-core' ), __( 'Le livreur vous appelle avant de passer. Préparez {total} en espèces.', 'famma-core' ), 'الليفرور يكلمك قبل ما يجي. حضّر {total} كاش.', __( '{total} est remplacé par le montant. Vide : la note disparaît.', 'famma-core' ) ),
				self::field( 'card_note_ok', __( 'Carte — note après livraison', 'famma-core' ), __( 'Livrée et payée. Un souci ? Vous avez 7 jours pour échanger.', 'famma-core' ), 'وصلت وتخلّصت. عندك مشكل؟ عندك 7 أيام باش تبدّل.', __( 'À vérifier : le délai doit être celui de la page Retours. Vide : la note disparaît.', 'famma-core' ) ),
				self::field( 'card_more', __( 'Carte — déplier', 'famma-core' ), __( 'Voir le détail', 'famma-core' ), 'شوف التفاصيل' ),
				self::field( 'card_less', __( 'Carte — replier', 'famma-core' ), __( 'Masquer le détail', 'famma-core' ), 'خبّي التفاصيل' ),
				self::field( 'card_sub', __( 'Carte — sous-total', 'famma-core' ), __( 'Sous-total', 'famma-core' ), 'المجموع' ),
				self::field( 'card_ship', __( 'Carte — livraison', 'famma-core' ), __( 'Livraison', 'famma-core' ), 'التوصيل' ),
				self::field( 'card_free', __( 'Carte — livraison gratuite', 'famma-core' ), __( 'Gratuite', 'famma-core' ), 'بلاش' ),
				self::field( 'card_total', __( 'Carte — total', 'famma-core' ), __( 'Total', 'famma-core' ), 'الجملة' ),
				self::field( 'track_reorder', __( 'Bouton « Recommander »', 'famma-core' ), __( 'Recommander', 'famma-core' ), 'عاود اطلب', __( 'Commandes livrées : remet les mêmes articles dans le panier, aux prix du jour. Vide : le bouton disparaît.', 'famma-core' ) ),
				self::field( 'card_ask', __( 'Bouton « Une question »', 'famma-core' ), __( 'Une question', 'famma-core' ), 'عندي سؤال', __( 'Ouvre WhatsApp, si un numéro est réglé. Vide : le bouton disparaît.', 'famma-core' ) ),
				self::field( 'card_ask_msg', __( 'Message WhatsApp prérempli', 'famma-core' ), __( 'Bonjour, une question sur ma commande {n}.', 'famma-core' ), 'عسلامة، عندي سؤال على الطلبية {n}.', __( '{n} est remplacé par le numéro de la commande.', 'famma-core' ) ),
			)
		);
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
	 * Statuts « en cours » (étapes 1 à 3), pour les compteurs.
	 *
	 * @return array<int, string>
	 */
	public static function open_statuses(): array {
		return array_keys( array_filter( self::STEP_OF, static fn( int $s ): bool => $s < self::STEPS ) );
	}

	/**
	 * Note l'heure à laquelle une commande atteint une étape.
	 *
	 * Seule la première fois compte : une commande remise en arrière puis
	 * avancée de nouveau garde l'heure d'origine de chaque étape.
	 *
	 * @param int            $order_id Commande.
	 * @param string         $from     Ancien statut.
	 * @param string         $to       Nouveau statut.
	 * @param \WC_Order|null $order    Commande, quand WooCommerce la fournit.
	 * @return void
	 */
	public static function record_step( $order_id, $from, $to, $order = null ): void {
		$step = self::step( (string) $to );

		if ( $step < 2 ) {
			return;
		}

		$order = $order instanceof \WC_Order ? $order : wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$times = $order->get_meta( self::META_TIMES );
		$times = is_array( $times ) ? $times : array();

		if ( isset( $times[ $step ] ) ) {
			return;
		}

		$times[ $step ] = time();
		$order->update_meta_data( self::META_TIMES, $times );
		$order->save_meta_data();
	}

	/**
	 * Date formatée d'un horodatage GMT, dans le fuseau du site.
	 *
	 * @param int    $timestamp Horodatage.
	 * @param string $format    Format de date.
	 * @return string
	 */
	private static function format_time( int $timestamp, string $format ): string {
		$date = new \WC_DateTime( '@' . $timestamp );
		$date->setTimezone( new \DateTimeZone( wc_timezone_string() ) );

		return wc_format_datetime( $date, $format );
	}

	/**
	 * Remplace `{clé}` dans un texte déjà échappé.
	 *
	 * @param string                $text Texte réglé dans l'admin.
	 * @param array<string, string> $vars Valeurs, déjà prêtes à afficher (HTML sûr).
	 * @return string HTML sûr.
	 */
	private static function fill( string $text, array $vars ): string {
		$html = esc_html( $text );

		foreach ( $vars as $key => $value ) {
			$html = str_replace( '{' . $key . '}', $value, $html );
		}

		return $html;
	}

	/**
	 * Tout ce que le thème doit dessiner pour une commande.
	 *
	 * Les valeurs `*_html` sont déjà échappées (montants WooCommerce compris) ;
	 * les autres sont du texte brut, à échapper à l'affichage.
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<string, mixed>
	 */
	public static function view( \WC_Order $order ): array {
		$status = (string) $order->get_status();
		$step   = self::step( $status );
		$done   = self::STEPS === $step;
		$times  = $order->get_meta( self::META_TIMES );
		$times  = is_array( $times ) ? $times : array();

		$created = $order->get_date_created();
		$paid    = $order->get_date_paid();

		if ( $created ) {
			$times[1] = $created->getTimestamp();
		}

		if ( $done && $paid && ! isset( $times[4] ) ) {
			$times[4] = $paid->getTimestamp();
		}

		$steps = array();

		for ( $i = 1; $i <= self::STEPS; $i++ ) {
			$steps[ $i ] = array(
				'label' => self::text( 'track_' . $i ),
				'date'  => ( $i <= $step && isset( $times[ $i ] ) ) ? self::format_time( (int) $times[ $i ], self::STEP_FORMAT ) : '',
			);
		}

		$total_html = (string) $order->get_formatted_order_total();
		$count      = (int) $order->get_item_count();
		$number     = (string) $order->get_order_number();

		$note = '';
		if ( $done ) {
			$note = self::fill( self::text( 'card_note_ok' ), array() );
		} elseif ( $step >= 1 && ! $order->is_paid() ) {
			$note = self::fill( self::text( 'card_note_go' ), array( 'total' => '<b>' . $total_html . '</b>' ) );
		}

		$pay = '';
		if ( $done ) {
			$pay = self::text( 'card_paid' );
		} elseif ( $step >= 1 && ! $order->is_paid() ) {
			$pay = self::text( 'track_due' );
		}

		return array(
			'step'       => $step,
			'done'       => $done,
			'status'     => wc_get_order_status_name( $status ),
			'pill'       => 0 === $step ? $status : ( $done ? 'ok' : 'go' ),
			'pill_label' => 0 === $step ? wc_get_order_status_name( $status ) : self::text( $done ? 'card_ok' : 'card_go' ),
			'steps'      => $steps,
			'title'      => str_replace( '{n}', $number, self::text( 'card_number' ) ),
			'date'       => $created ? wc_format_datetime( $created, self::CARD_FORMAT ) : '',
			'count'      => 1 === $count ? self::text( 'card_item' ) : str_replace( '{n}', (string) $count, self::text( 'card_items' ) ),
			'total_html' => $total_html,
			'pay'        => $pay,
			'note_html'  => $note,
			'items'      => self::items( $order ),
			'sums'       => self::sums( $order ),
			'place'      => self::place( $order ),
			'actions'    => self::actions( $order ),
		);
	}

	/**
	 * Articles de la commande, avec leur vignette.
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<int, array{name: string, qty: int, image: string, total_html: string}>
	 */
	private static function items( \WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product  = $item->get_product();
			$image_id = $product ? (int) $product->get_image_id() : 0;
			$image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' ) : '';

			$items[] = array(
				'name'       => $item->get_name(),
				'qty'        => (int) $item->get_quantity(),
				'image'      => '' !== $image ? $image : (string) wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' ),
				'total_html' => (string) $order->get_formatted_line_subtotal( $item ),
			);
		}

		return $items;
	}

	/**
	 * Lignes du récapitulatif : sous-total, livraison, total.
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<int, array{label: string, value_html: string, kind: string}>
	 */
	private static function sums( \WC_Order $order ): array {
		$shipping = (float) $order->get_shipping_total();
		$free     = self::text( 'card_free' );

		return array(
			array(
				'label'      => self::text( 'card_sub' ),
				'value_html' => (string) wc_price( (float) $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ),
				'kind'       => 'sub',
			),
			array(
				'label'      => self::text( 'card_ship' ),
				'value_html' => $shipping > 0
					? (string) wc_price( $shipping, array( 'currency' => $order->get_currency() ) )
					: esc_html( $free ) . ' · ' . wc_price( 0, array( 'currency' => $order->get_currency() ) ),
				'kind'       => $shipping > 0 ? 'ship' : 'free',
			),
			array(
				'label'      => self::text( 'card_total' ),
				'value_html' => (string) $order->get_formatted_order_total(),
				'kind'       => 'tot',
			),
		);
	}

	/**
	 * Lieu de livraison affiché : ville, sinon gouvernorat.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string
	 */
	private static function place( \WC_Order $order ): string {
		$city = trim( (string) $order->get_billing_city() );

		if ( '' !== $city ) {
			return $city;
		}

		$state  = (string) $order->get_billing_state();
		$states = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( (string) $order->get_billing_country() ) : array();

		return is_array( $states ) && isset( $states[ $state ] ) ? (string) $states[ $state ] : $state;
	}

	/**
	 * Boutons de la carte : Recommander, Une question, puis les actions
	 * WooCommerce restantes (payer, annuler…), sans « Voir ».
	 *
	 * @param \WC_Order $order Commande.
	 * @return array<int, array{key: string, url: string, name: string}>
	 */
	private static function actions( \WC_Order $order ): array {
		$out   = array();
		$label = self::text( 'track_reorder' );
		// Filtre de WooCommerce relu tel quel : même liste que son bouton natif.
		$valid = (array) apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( '' !== $label && $order->has_status( $valid ) && $order->get_item_count() && function_exists( 'wc_get_cart_url' ) ) {
			$out[] = array(
				'key'  => 'order-again',
				'url'  => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
				'name' => $label,
			);
		}

		$ask = self::text( 'card_ask' );
		$wa  = '' !== $ask ? WhatsApp::link( str_replace( '{n}', (string) $order->get_order_number(), self::text( 'card_ask_msg' ) ) ) : '';

		if ( '' !== $wa ) {
			$out[] = array(
				'key'  => 'ask',
				'url'  => $wa,
				'name' => $ask,
			);
		}

		if ( function_exists( 'wc_get_account_orders_actions' ) ) {
			foreach ( wc_get_account_orders_actions( $order ) as $key => $action ) {
				if ( in_array( $key, array( 'view', 'order-again' ), true ) || empty( $action['url'] ) ) {
					continue;
				}

				$out[] = array(
					'key'  => (string) $key,
					'url'  => (string) $action['url'],
					'name' => (string) ( $action['name'] ?? '' ),
				);
			}
		}

		return $out;
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
}
