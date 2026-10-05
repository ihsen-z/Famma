<?php
/**
 * E-mails « Nouvelle commande » envoyés hors du checkout (audit PERF-23).
 *
 * Les nouvelles commandes COD démarrent en « pending-confirm »
 * (`Order_Statuses::cod_initial_status()`). WooCommerce ne rattache ses e-mails
 * « Nouvelle commande » (admin) et « Commande reçue » (client) qu'aux
 * transitions pending → processing / on-hold / completed : sans ce fichier,
 * aucun e-mail ne partait (audit du 23/09, CODE-01).
 *
 * Jusqu'ici ces deux e-mails partaient pendant la requête du checkout : la
 * connexion SMTP de l'hébergeur s'ajoutait au temps de réponse du clic
 * « Commander ». L'envoi est désormais confié à Action Scheduler, en action
 * asynchrone. En production, `DISABLE_WP_CRON` est actif et la tâche cron du
 * serveur passe toutes les 5 minutes : l'e-mail part au plus tard quelques
 * minutes après la commande, ce qui est acceptable pour une notification.
 *
 * L'envoi est idempotent : la méta `_famma_new_order_emails_sent` empêche un
 * second envoi si l'action est rejouée ou si la commande repasse en
 * « pending-confirm » (failed → pending-confirm).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Mise en file et envoi des e-mails de nouvelle commande.
 */
final class Order_Emails {

	/**
	 * Action Scheduler : nom de l'action asynchrone.
	 */
	public const ACTION = 'famma_send_new_order_emails';

	/**
	 * Action Scheduler : groupe des actions FAMMA.
	 */
	public const GROUP = 'famma';

	/**
	 * Méta posée une fois les e-mails envoyés.
	 */
	public const SENT_META = '_famma_new_order_emails_sent';

	/**
	 * Instance partagée.
	 *
	 * @var Order_Emails|null
	 */
	private static ?Order_Emails $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Order_Emails
	 */
	public static function instance(): Order_Emails {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * Le gestionnaire de l'action est enregistré partout (front, admin, cron,
	 * WP-CLI) : c'est le contexte cron qui l'exécute.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_order_status_pending_to_pending-confirm', array( $this, 'queue' ), 10, 2 );
		add_action( 'woocommerce_order_status_failed_to_pending-confirm', array( $this, 'queue' ), 10, 2 );
		add_action( self::ACTION, array( $this, 'send' ) );
	}

	/**
	 * Met l'envoi en file, hors de la requête du checkout.
	 *
	 * Si Action Scheduler n'est pas disponible (cas anormal : il est livré avec
	 * WooCommerce), l'envoi se fait immédiatement plutôt que pas du tout.
	 *
	 * @param int            $order_id Identifiant de la commande.
	 * @param \WC_Order|null $order    Commande, quand WooCommerce la transmet.
	 * @return void
	 */
	public function queue( int $order_id, $order = null ): void {
		unset( $order );

		if ( $order_id <= 0 ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) && did_action( 'action_scheduler_init' ) ) {
			// `unique` : une seule action en attente par commande.
			as_enqueue_async_action( self::ACTION, array( $order_id ), self::GROUP, true );
			return;
		}

		$this->send( $order_id );
	}

	/**
	 * Envoie les e-mails « Nouvelle commande » (admin) et « Commande reçue »
	 * (client), une seule fois par commande.
	 *
	 * Passe par les classes d'e-mail de WooCommerce : modèles, destinataire
	 * réglé dans WooCommerce → Réglages → E-mails et interrupteurs on/off
	 * s'appliquent toujours. `WC_Email_New_Order` se protège en plus lui-même
	 * contre un second envoi.
	 *
	 * @param int $order_id Identifiant de la commande.
	 * @return void
	 */
	public function send( $order_id ): void {
		$order_id = absint( $order_id );

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || '' !== (string) $order->get_meta( self::SENT_META ) ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->trigger( $order_id, $order );
		}

		// E-mail client facultatif au checkout : WooCommerce ne l'envoie que s'il existe.
		if ( isset( $emails['WC_Email_Customer_Processing_Order'] ) && '' !== $order->get_billing_email() ) {
			$emails['WC_Email_Customer_Processing_Order']->trigger( $order_id, $order );
		}

		$order->update_meta_data( self::SENT_META, (string) time() );
		$order->save();
	}
}
