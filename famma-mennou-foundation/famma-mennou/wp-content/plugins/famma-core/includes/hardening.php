<?php
/**
 * Durcissement de la surface d'authentification publique.
 *
 * Audit du 23/09 (SEC-06) : l'identifiant administrateur se lisait en clair sur
 * `/wp-json/wp/v2/users`, et XML-RPC restait actif. Les deux ensemble donnent
 * à un robot le login ET un point d'entrée capable d'essayer des centaines de
 * mots de passe par requête (`system.multicall`). La boutique n'utilise ni
 * l'application mobile WordPress ni Jetpack : XML-RPC ne sert à rien ici.
 *
 * Ce fichier ne remplace pas le blocage côté serveur (`.htaccess`), qui évite
 * même de démarrer PHP : il garantit la même protection si le site change
 * d'hébergement ou si la règle serveur disparaît.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Ferme XML-RPC et la liste publique des comptes.
 */
final class Hardening {

	/**
	 * Instance partagée.
	 *
	 * @var Hardening|null
	 */
	private static ?Hardening $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Hardening
	 */
	public static function instance(): Hardening {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		// Méthodes authentifiées de XML-RPC refusées avant tout test de mot de passe.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', array( $this, 'drop_pingback_methods' ) );
		add_filter( 'wp_headers', array( $this, 'drop_pingback_header' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_users_route' ), 10, 3 );

		// Audit du 25/09 (SEC-20, SEC-14) : l'identifiant et les versions fuyaient ailleurs.
		add_filter( 'oembed_response_data', array( $this, 'drop_oembed_author' ) );
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * Retire l'auteur des réponses oEmbed.
	 *
	 * `/wp-json/oembed/1.0/embed` renvoyait `author_name` et `author_url` :
	 * l'identifiant masqué sur `/wp/v2/users` restait lisible en une requête.
	 *
	 * @param array<string, mixed> $data Réponse oEmbed.
	 * @return array<string, mixed>
	 */
	public function drop_oembed_author( $data ): array {
		$data = (array) $data;

		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}

	/**
	 * Retire les méthodes pingback, utilisables pour des attaques par rebond.
	 *
	 * @param array<string, mixed> $methods Méthodes XML-RPC.
	 * @return array<string, mixed>
	 */
	public function drop_pingback_methods( $methods ): array {
		$methods = (array) $methods;

		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );

		return $methods;
	}

	/**
	 * Supprime l'en-tête `X-Pingback`, qui annonce XML-RPC à chaque page.
	 *
	 * @param array<string, string> $headers En-têtes HTTP.
	 * @return array<string, string>
	 */
	public function drop_pingback_header( $headers ): array {
		$headers = (array) $headers;

		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Réserve `/wp/v2/users` aux comptes autorisés à lister les utilisateurs.
	 *
	 * `rest_pre_dispatch` s'exécute après l'authentification : l'éditeur de
	 * blocs d'un administrateur connecté garde donc son accès (auteur, `me`),
	 * seuls les visiteurs anonymes reçoivent une 401.
	 *
	 * @param mixed            $result  Réponse déjà calculée, ou null.
	 * @param \WP_REST_Server  $server  Serveur REST.
	 * @param \WP_REST_Request $request Requête.
	 * @return mixed
	 */
	public function guard_users_route( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		// Le routeur REST ignore la casse (`/wp/v2/Users` atteint le même contrôleur) : SEC-21.
		if ( ! str_starts_with( strtolower( $request->get_route() ), '/wp/v2/users' ) || current_user_can( 'list_users' ) ) {
			return $result;
		}

		// L'éditeur interroge « me » et l'auteur des contenus qu'il modifie.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.', 'famma-core' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
