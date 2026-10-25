<?php
/**
 * Provenance first-party des commandes (§20, lot 5 R12), indépendante de tout Pixel.
 *
 * - Le navigateur pose le cookie `famma_utm` (30 jours) : les `utm_*` de
 *   l'arrivée, plus `fbclid` (présence) et `ref` (site référent externe).
 *   Le script est imprimé par `Tracking::enqueue_tracking()` sur toute page,
 *   même sans Pixel et pour un visiteur interne (V8, T2.9).
 * - À la création de la commande, les `utm_*` sont recopiés en `_famma_utm_*`
 *   (sens inchangé : le tableau de bord les lit toujours), et une source de
 *   synthèse `_famma_order_source` est toujours écrite, au format
 *   « source / support » (« direct » par défaut).
 *
 * Classe statique, sans état ni point d'accroche : le hook
 * `woocommerce_checkout_create_order` reste porté par `Tracking`
 * (`stamp_utm_on_order()`, API publique conservée).
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Capture et synthèse de la provenance d'une commande.
 */
final class Order_Source {

	/**
	 * Paramètres de campagne recopiés sur la commande (`_famma_utm_*`).
	 */
	private const UTM_KEYS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );

	/**
	 * Capture first-party de la provenance (§20, R12), indépendante de tout Pixel.
	 *
	 * Cookie `famma_utm`, 30 jours : les `utm_*` de l'arrivée (inchangé), plus
	 * `fbclid` (présence) et `ref` (site référent externe). Une arrivée avec
	 * UTM remplace le cookie ; une arrivée sans UTM mais avec fbclid ou
	 * référent externe ne remplace qu'un cookie sans UTM, pour que les
	 * `_famma_utm_*` gardent exactement leur sens d'avant.
	 */
	public const UTM_SCRIPT = '(function(){try{var p=new URLSearchParams(location.search),k=["utm_source","utm_medium","utm_campaign","utm_content","utm_term"],o={},h=false,a=";path=/;max-age="+(60*60*24*30)+";SameSite=Lax",r="",u=false,c=document.cookie.match(/(?:^|;\s*)famma_utm=([^;]*)/),n=function(x){return String(x||"").toLowerCase().replace(/^www\./,"");};k.forEach(function(x){var v=p.get(x);if(v){o[x]=v;h=true;}});try{r=document.referrer?n(new URL(document.referrer).hostname):"";}catch(e){r="";}if(r===n(location.hostname)){r="";}if(p.get("fbclid")){o.fbclid=1;}if(r){o.ref=r.slice(0,100);}if(!h&&c){try{u=/utm_/.test(decodeURIComponent(c[1]));}catch(e){u=false;}}if(h||((o.fbclid||r)&&!u)){document.cookie="famma_utm="+encodeURIComponent(JSON.stringify(o))+a;}}catch(e){}})();';

	/**
	 * Cookie de provenance `famma_utm`, décodé ; tableau vide s'il est absent ou invalide.
	 *
	 * @return array<string, mixed>
	 */
	private static function cookie(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only attribution cookie, not an action.
		$raw = isset( $_COOKIE['famma_utm'] ) && is_string( $_COOKIE['famma_utm'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['famma_utm'] ) ) : '';
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			return array();
		}

		$parsed = json_decode( $raw, true );

		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * Copies the captured UTM parameters and the synthetic source onto the order (§20, R12).
	 *
	 * The cookie is attacker-controllable, so each value is length-capped and
	 * sanitised individually before being stored. `_famma_order_source` est
	 * toujours écrit, même sans cookie (« direct »). L'appelant enregistre la
	 * commande (`woocommerce_checkout_create_order`, avant le `save()` de WooCommerce).
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public static function stamp( \WC_Order $order ): void {
		$parsed = self::cookie();

		foreach ( self::UTM_KEYS as $k ) {
			if ( ! empty( $parsed[ $k ] ) && is_scalar( $parsed[ $k ] ) ) {
				$order->update_meta_data( '_famma_' . $k, substr( sanitize_text_field( (string) $parsed[ $k ] ), 0, 255 ) );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only Meta click cookie, validated by Capi_Signals.
		$fbc = isset( $_COOKIE['_fbc'] ) && is_string( $_COOKIE['_fbc'] ) ? Capi_Signals::clean_cookie( sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) ) ) : '';
		$order->update_meta_data( '_famma_order_source', self::source( $parsed, '' !== $fbc ) );
	}

	/**
	 * Source de synthèse d'une commande, au format « source / support » (R12).
	 *
	 * Même ordre que le parcours visiteurs : UTM d'abord (`facebook / paid`),
	 * puis fbclid (`facebook / fbclid`), puis site référent externe
	 * (`google.com / referral`), puis un cookie `_fbc` valide (clic Meta
	 * antérieur au cookie de provenance), sinon `direct`.
	 *
	 * @param array<string, mixed> $cookie  Cookie `famma_utm` décodé.
	 * @param bool                 $has_fbc Un cookie `_fbc` valide accompagne la commande.
	 * @return string
	 */
	public static function source( array $cookie, bool $has_fbc ): string {
		$clean  = static function ( $value ): string {
			return is_scalar( $value ) ? strtolower( substr( trim( sanitize_text_field( (string) $value ) ), 0, 64 ) ) : '';
		};
		$source = $clean( $cookie['utm_source'] ?? '' );
		if ( '' !== $source ) {
			$medium = $clean( $cookie['utm_medium'] ?? '' );
			return '' !== $medium ? $source . ' / ' . $medium : $source;
		}
		if ( ! empty( $cookie['fbclid'] ) ) {
			return 'facebook / fbclid';
		}
		$ref = is_scalar( $cookie['ref'] ?? null ) ? (string) preg_replace( '/[^a-z0-9.-]/', '', strtolower( (string) $cookie['ref'] ) ) : '';
		$ref = substr( (string) preg_replace( '/^www\./', '', $ref ), 0, 100 );
		if ( '' !== $ref ) {
			return $ref . ' / referral';
		}

		return $has_fbc ? 'facebook / fbclid' : 'direct';
	}
}
