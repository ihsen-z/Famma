<?php
/**
 * Plugin Name: FAMMA — redirections des anciennes URL de boutique
 * Description: Redirige en 301 les adresses anglaises par defaut de WooCommerce (/shop/, /cart/, /checkout/, /my-account/) vers leurs equivalents francais. Complete `francise-slugs.php`.
 * Version:     0.1.0
 * Author:      FAMMA
 *
 * Pourquoi ce fichier existe. WordPress sait rediriger l'ancien slug d'un
 * article : `wp_old_slug_redirect()` lit la meta `_wp_old_slug` a partir de la
 * variable de requete `name`. Une PAGE, elle, est resolue par `pagename` — la
 * fonction ne s'y applique donc pas, et rien du coeur ne prend le relais.
 * Constate a l'execution apres la francisation : `/shop/` et `/cart/`
 * repondaient 404, la ou `/product/...` etait bien redirige par WooCommerce.
 *
 * La table ci-dessous ne fige pas les nouvelles adresses : elle nomme les
 * anciennes, et demande a WooCommerce l'URL courante de chaque page. Renommer
 * a nouveau `/boutique/` en `/magasin/` n'obligerait donc a rien ici.
 *
 * @package Famma\Local
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirige les adresses anglaises par defaut vers les pages francaises.
 *
 * N'agit que sur une vraie 404, et jamais dans l'administration : sans cette
 * garde, la moindre requete interne paierait la resolution des permaliens.
 *
 * @return void
 */
function famma_legacy_urls_redirect(): void {
	if ( is_admin() || wp_doing_ajax() || ! is_404() ) {
		return;
	}

	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		: '';

	if ( '' === $request ) {
		return;
	}

	$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
	$segment = strtok( trim( $path, '/' ), '/' );

	if ( false === $segment || '' === $segment ) {
		return;
	}

	/**
	 * Anciens segments d'URL, et la page WooCommerce qui les remplace.
	 *
	 * @var array<string, string> $legacy
	 */
	$legacy = apply_filters(
		'famma_legacy_url_map',
		array(
			'shop'       => 'shop',
			'cart'       => 'cart',
			'checkout'   => 'checkout',
			'my-account' => 'myaccount',
		)
	);

	if ( ! isset( $legacy[ $segment ] ) ) {
		return;
	}

	$target = wc_get_page_permalink( $legacy[ $segment ] );

	if ( ! $target ) {
		return;
	}

	/*
	 * Le segment renomme peut avoir la meme valeur que l'ancien (page non
	 * francisee) : rediriger vers soi-meme creerait une boucle.
	 */
	if ( untrailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) ) === untrailingslashit( $path ) ) {
		return;
	}

	// La chaine de requete est conservee : un lien publicitaire porte ses UTM,
	// et les perdre casserait l'attribution de la campagne.
	$query = (string) wp_parse_url( $request, PHP_URL_QUERY );
	if ( '' !== $query ) {
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'famma_legacy_urls_redirect', 5 );
