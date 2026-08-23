<?php
/**
 * Plugin Name: FAMMA — routage du courrier local
 * Description: Route tout le courrier sortant vers Mailpit, uniquement dans l'environnement local. Sans cela, chaque e-mail transactionnel echoue sur « sendmail: can't connect » : l'image WordPress n'embarque aucun agent de transport.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * L'hote et le port sont surchargeables par les constantes
 * FAMMA_LOCAL_SMTP_HOST / FAMMA_LOCAL_SMTP_PORT, definies dans wp-config.php.
 * Les valeurs par defaut restent celles de la stack Docker (`mailpit:1025`) ;
 * sous Local (Local by Flywheel), Mailpit ecoute sur 127.0.0.1:10001, un port
 * attribue par site et qui ne peut donc pas etre code en dur ici.
 *
 * Infrastructure de developpement, pas de la logique metier : ce fichier
 * n'a donc pas sa place dans famma-core, qui est destine a la production.
 * Le garde-fou sur l'environnement le rend inoffensif s'il y arrivait quand
 * meme.
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

/**
 * Points PHPMailer at the Mailpit container.
 *
 * @param \PHPMailer\PHPMailer\PHPMailer $mailer Mailer instance, passed by reference by WordPress.
 * @return void
 */
function famma_local_mail_use_mailpit( $mailer ): void {
	if ( 'local' !== wp_get_environment_type() ) {
		return;
	}

	$mailer->isSMTP();
	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own public API.
	$mailer->Host        = defined( 'FAMMA_LOCAL_SMTP_HOST' ) ? FAMMA_LOCAL_SMTP_HOST : 'mailpit';
	$mailer->Port        = defined( 'FAMMA_LOCAL_SMTP_PORT' ) ? (int) FAMMA_LOCAL_SMTP_PORT : 1025;
	$mailer->SMTPAuth    = false;
	$mailer->SMTPAutoTLS = false;
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
}
add_action( 'phpmailer_init', 'famma_local_mail_use_mailpit' );

/**
 * Gives outgoing mail a sender the local MTA will not reject.
 *
 * @return string
 */
function famma_local_mail_from(): string {
	return 'no-reply@famma.local';
}
add_filter( 'wp_mail_from', 'famma_local_mail_from' );
