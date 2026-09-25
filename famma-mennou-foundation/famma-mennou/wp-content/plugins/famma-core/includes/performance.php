<?php
/**
 * Allègements front-end sans effet sur le métier.
 *
 * Audit de performance du 23/09 (docs/audit-2026-09-23/audit-performance.md).
 * Ne sont regroupés ici que les réglages qui tiennent en un filtre et ne
 * dépendent pas du thème : un site doit rester aussi léger après changement
 * de thème. Les retouches de gabarit (hero, boutique) vivent dans le thème
 * enfant, les réglages de cache dans LiteSpeed Cache.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Retire du front ce que la boutique ne sert pas, et précharge la navigation.
 */
final class Performance {

	/**
	 * Instance partagée.
	 *
	 * @var Performance|null
	 */
	private static ?Performance $i = null;

	/**
	 * Renvoie l'instance partagée, en posant les hooks au premier appel.
	 *
	 * @return Performance
	 */
	public static function instance(): Performance {
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
		// PERF-04 : `sizes="auto"` se résout mal dans la galerie FlexSlider,
		// qui télécharge alors les photos deux à trois fois.
		add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );

		// PERF-12 : les futurs envois JPEG/PNG sont enregistrés en WebP.
		add_filter( 'image_editor_output_format', array( $this, 'webp_output' ) );

		if ( is_admin() ) {
			return;
		}

		// PERF-11 : les navigateurs mobiles affichent les emojis nativement.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		add_filter( 'emoji_svg_url', '__return_false' );

		add_filter( 'wp_resource_hints', array( $this, 'resource_hints' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'drop_password_meter' ), 100 );

		// PERF-19 : le cache de page est actif, précharger au toucher ne coûte
		// plus de PHP pour les pages publiques.
		add_filter( 'wp_speculation_rules_configuration', array( $this, 'speculation_config' ) );
		add_filter( 'wp_speculation_rules_href_exclude_paths', array( $this, 'speculation_excludes' ) );
	}

	/**
	 * Associe les formats JPEG et PNG à WebP pour les tailles générées.
	 *
	 * @param array<string, string> $formats Correspondances type MIME source → cible.
	 * @return array<string, string>
	 */
	public function webp_output( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}

	/**
	 * Préconnecte le pixel Meta, et retire la préconnexion aux emojis.
	 *
	 * Le pixel reste chargé tôt (PERF-07 : il nourrit l'optimisation des
	 * campagnes) : on lui fait seulement gagner la poignée de main TLS.
	 *
	 * @param array<int, string|array<string, string>> $urls          URLs de l'indication.
	 * @param string                                   $relation_type Type d'indication.
	 * @return array<int, string|array<string, string>>
	 */
	public function resource_hints( array $urls, string $relation_type ): array {
		if ( 'dns-prefetch' === $relation_type ) {
			return array_values(
				array_filter(
					$urls,
					static fn( $url ): bool => ! is_string( $url ) || false === strpos( $url, 's.w.org' )
				)
			);
		}

		if ( 'preconnect' === $relation_type && '' !== Config::instance()->meta_pixel_id() ) {
			$urls[] = 'https://connect.facebook.net';
		}

		return $urls;
	}

	/**
	 * Décharge l'indicateur de force du mot de passe (≈ 400 Kio avec zxcvbn).
	 *
	 * PERF-08 : un invité n'en a besoin que si le checkout lui fait choisir
	 * un mot de passe. Le panier n'en a jamais besoin.
	 *
	 * @return void
	 */
	public function drop_password_meter(): void {
		if ( is_user_logged_in() || ! function_exists( 'is_cart' ) ) {
			return;
		}

		$needs_meter = is_checkout()
			&& 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' )
			&& 'yes' !== get_option( 'woocommerce_registration_generate_password' );

		if ( ( is_cart() || is_checkout() ) && ! $needs_meter ) {
			wp_dequeue_script( 'wc-password-strength-meter' );
		}
	}

	/**
	 * Passe le préchargement de « au clic » à « au survol ou au toucher ».
	 *
	 * @param array<string, string>|null $config Configuration du cœur, `null` si désactivée.
	 * @return array<string, string>|null
	 */
	public function speculation_config( ?array $config ): ?array {
		if ( null === $config || is_user_logged_in() ) {
			return $config;
		}

		$config['eagerness'] = 'moderate';
		return $config;
	}

	/**
	 * Exclut du préchargement les pages non cachables du tunnel.
	 *
	 * Les URLs à paramètres (`?add-to-cart=`) sont déjà exclues par le cœur.
	 * Deux motifs par page : le chemin nu, et le même sous un préfixe de
	 * langue (`/ar/panier/`).
	 *
	 * @param string[] $paths Motifs déjà exclus.
	 * @return string[]
	 */
	public function speculation_excludes( array $paths ): array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return $paths;
		}

		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
			$path = (string) wp_parse_url( (string) get_permalink( wc_get_page_id( $page ) ), PHP_URL_PATH );

			if ( '' !== $path && '/' !== $path ) {
				$paths[] = untrailingslashit( $path ) . '*';
				$paths[] = '/*' . untrailingslashit( $path ) . '*';
			}
		}

		return $paths;
	}
}
