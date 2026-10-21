<?php
/**
 * Plugin Name:       FAMMA Core
 * Description:       Core business logic for FAMMA MENNOU — custom COD order workflow, cost×3 pricing with margin tracking, Tunisia checkout rules, WhatsApp, and env-driven tracking (Meta/TikTok/GA4). Keeps customisation out of WP/WooCommerce core and the theme.
 * Version:           0.3.4
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            FAMMA
 * Text Domain:       famma-core
 * WC requires at least: 8.0
 *
 * @package Famma\Core
 *
 * Single-file build: all classes live here so there is no cross-file autoload
 * to misfire, and no way to declare the same class twice. This is deliberate —
 * see ClaudeMemo.md rule 7 and docs/ARCHITECTURE.md. The phpcs ruleset excludes
 * the one-structure-per-file sniffs for this reason.
 *
 * One exception, in `includes/` : le contenu éditable des pages
 * institutionnelles. Ce fichier avait franchi les 2 100 lignes, et la règle 6
 * demande de scinder par responsabilité passé ce volume. L'exception reste
 * étroite et préserve les deux garanties ci-dessus : un `require_once` explicite
 * plutôt qu'un autoloader, et une seule classe par fichier inclus.
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

define( 'FAMMA_CORE_VERSION', '0.3.4' );
define( 'FAMMA_CORE_FILE', __FILE__ );

require_once __DIR__ . '/includes/pages-content.php';
require_once __DIR__ . '/includes/product-content.php';
require_once __DIR__ . '/includes/home-content.php';
require_once __DIR__ . '/includes/delivery-content.php';
require_once __DIR__ . '/includes/returns-content.php';
require_once __DIR__ . '/includes/newsletter.php';
require_once __DIR__ . '/includes/topbar-content.php';
require_once __DIR__ . '/includes/tracking-settings.php';
require_once __DIR__ . '/includes/meta-ids.php';
require_once __DIR__ . '/includes/tracking-events.php';
require_once __DIR__ . '/includes/browser-order-placed.php';
require_once __DIR__ . '/includes/order-source.php';
require_once __DIR__ . '/includes/product-sizes.php';
require_once __DIR__ . '/includes/product-sizes-admin.php';
require_once __DIR__ . '/includes/express-order.php';
require_once __DIR__ . '/includes/express-checkout.php';
require_once __DIR__ . '/includes/express-idempotency.php';
require_once __DIR__ . '/includes/checkout-idempotency.php';
require_once __DIR__ . '/includes/express-preparation.php';
require_once __DIR__ . '/includes/capi-delivery-registry.php';
require_once __DIR__ . '/includes/capi-signals.php';
require_once __DIR__ . '/includes/capi-click-id.php';
require_once __DIR__ . '/includes/capi-log.php';
require_once __DIR__ . '/includes/capi-product-data.php';
require_once __DIR__ . '/includes/capi-token-vault.php';
require_once __DIR__ . '/includes/capi-token-rollback.php';
require_once __DIR__ . '/includes/capi-transport.php';
require_once __DIR__ . '/includes/capi-person-name.php';
require_once __DIR__ . '/includes/capi-user-data.php';
require_once __DIR__ . '/includes/capi-data-profile.php';
require_once __DIR__ . '/includes/capi-retry.php';
require_once __DIR__ . '/includes/capi-health.php';
require_once __DIR__ . '/includes/capi-health-alert.php';
require_once __DIR__ . '/includes/capi-health-rules.php';
require_once __DIR__ . '/includes/capi-health-view.php';
require_once __DIR__ . '/includes/capi-probe-verdict.php';
require_once __DIR__ . '/includes/capi-probe.php';
require_once __DIR__ . '/includes/capi-graph-version.php';
require_once __DIR__ . '/includes/capi-reconcile.php';
require_once __DIR__ . '/includes/capi-reconcile-view.php';
require_once __DIR__ . '/includes/capi-purchase-gaps.php';
require_once __DIR__ . '/includes/measurement-settings.php';
require_once __DIR__ . '/includes/order-emails.php';
require_once __DIR__ . '/includes/anti-abuse.php';
require_once __DIR__ . '/includes/checkout-lite.php';
require_once __DIR__ . '/includes/product-video.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/seo-listing.php';
require_once __DIR__ . '/includes/seo-admin.php';
require_once __DIR__ . '/includes/hardening.php';
require_once __DIR__ . '/includes/performance.php';
require_once __DIR__ . '/includes/cache-warmup.php';
require_once __DIR__ . '/includes/redirect-cache.php';
require_once __DIR__ . '/includes/visitor-store.php';
require_once __DIR__ . '/includes/visitor-context.php';
require_once __DIR__ . '/includes/visitor-collector.php';
require_once __DIR__ . '/includes/visitor-commerce.php';
require_once __DIR__ . '/includes/visitor-report.php';
require_once __DIR__ . '/includes/visitor-admin.php';
require_once __DIR__ . '/includes/visitor-admin-views.php';
require_once __DIR__ . '/includes/visitor-insights.php';
require_once __DIR__ . '/includes/visitor-insights-views.php';
require_once __DIR__ . '/includes/visitor-order-box.php';
require_once __DIR__ . '/includes/visitor-privacy.php';
require_once __DIR__ . '/includes/visitor-performance.php';
require_once __DIR__ . '/includes/visitor-performance-views.php';

/**
 * Reads configuration from env-fed constants and WordPress options.
 *
 * Constants are defined in wp-config.php by docker-compose from .env.
 * An empty value means "feature disabled" — never a fallback to a guess.
 */
final class Config {

	/**
	 * Singleton instance.
	 *
	 * @var Config|null
	 */
	private static ?Config $i = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Config
	 */
	public static function instance(): Config {
		return self::$i ??= new self();
	}

	/**
	 * Reads a constant, falling back to a default when unset or empty.
	 *
	 * @param string $k Constant name.
	 * @param string $d Default value.
	 * @return string
	 */
	private function c( string $k, string $d = '' ): string {
		return defined( $k ) && '' !== (string) constant( $k ) ? (string) constant( $k ) : $d;
	}

	/**
	 * WhatsApp number, digits only.
	 *
	 * La constante du serveur l'emporte, comme partout ailleurs, mais elle
	 * n'est plus la seule source : le numéro se saisit aussi depuis
	 * « FAMMA → Pages ». Sans ce repli, le propriétaire devrait éditer un
	 * fichier sur le serveur pour changer un numéro de téléphone.
	 *
	 * @return string
	 */
	public function whatsapp_number(): string {
		$raw = $this->c( 'FAMMA_WHATSAPP_NUMBER' );

		if ( '' === $raw ) {
			$raw = (string) get_option( 'famma_whatsapp_number', '' );
		}

		return self::international_phone( $raw );
	}

	/**
	 * Normalises a phone number to international digits, Tunisia by default.
	 *
	 * Le service wa.me exige le numéro complet, indicatif compris, sans « + » ni « 00 ».
	 * Saisi en local (« 56 261 412 »), le numéro partait tel quel et WhatsApp
	 * le lisait comme un numéro chilien (+56) : aucun message n'arrivait
	 * (audit du 23/09, UX-01). Un numéro à 8 chiffres est donc tunisien.
	 *
	 * A Tunisian number is recognised by `Tunisia::normalize_phone()` only (8
	 * digits, or 216 / 00216 + 8 digits). Anything else is kept as an
	 * international number — « 00 » removed, never prefixed with 216: before,
	 * « 0020123456 » (an Egyptian prefix) became +216 20 123 456. Customer
	 * numbers must use `'216' . Tunisia::normalize_phone()` directly; this
	 * helper stays for the owner's WhatsApp number, which may be foreign.
	 *
	 * @param string $raw Number as typed: spaces, dots, « + » or « 00 » allowed.
	 * @return string Digits only, e.g. `21656261412`; empty when nothing usable.
	 */
	public static function international_phone( string $raw ): string {
		$local = Tunisia::normalize_phone( $raw );

		if ( '' !== $local ) {
			return '216' . $local;
		}

		$digits = (string) preg_replace( '/\D+/', '', $raw );

		if ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		}

		return $digits;
	}

	/**
	 * Builds a `tel:` URI in international format, or an empty string.
	 *
	 * @param string $raw Number as typed in the admin.
	 * @return string e.g. `tel:+21656261412`.
	 */
	public static function tel_uri( string $raw ): string {
		$digits = self::international_phone( $raw );

		return '' === $digits ? '' : 'tel:+' . $digits;
	}

	/**
	 * Reads a constant, falling back to the matching admin option.
	 *
	 * Same precedence as the shop's contact details: a constant defined on the
	 * server always wins, so a `.env` reproduced by `git clone` + `setup.sh`
	 * stays authoritative. The option only fills the gap, which is what lets the
	 * owner change a tracking identifier without touching a file on the server.
	 *
	 * @param string $constant Constant name, e.g. `FAMMA_META_PIXEL_ID`.
	 * @return string Empty string when neither is set — meaning "disabled".
	 */
	private function setting( string $constant ): string {
		$value = $this->c( $constant );

		if ( '' !== $value ) {
			return $value;
		}

		return trim( (string) get_option( strtolower( $constant ), '' ) );
	}

	/**
	 * Meta pixel identifier (safe to expose to the browser).
	 *
	 * @return string
	 */
	public function meta_pixel_id(): string {
		return $this->setting( 'FAMMA_META_PIXEL_ID' );
	}

	/**
	 * Meta Conversions API token. Server-side only — never enqueued (§17).
	 *
	 * La constante du serveur l'emporte toujours. À défaut, l'option est lue
	 * par `Capi_Token_Vault`, qui la garde chiffrée au repos (lot 5, A11).
	 *
	 * @return string
	 */
	public function meta_access_token(): string {
		$value = $this->c( 'FAMMA_META_ACCESS_TOKEN' );

		return '' !== $value ? $value : Capi_Token_Vault::read();
	}

	/**
	 * Meta test event code, used when validating CAPI events.
	 *
	 * @return string
	 */
	public function meta_test_event_code(): string {
		return $this->setting( 'FAMMA_META_TEST_EVENT_CODE' );
	}

	/**
	 * Version de l'API Graph utilisée par défaut (S2), à revérifier à chaque montée.
	 */
	public const META_GRAPH_VERSION_DEFAULT = 'v26.0';

	/**
	 * Dates de fin publiées par Meta (R1). Une version absente n'a pas de date connue.
	 */
	public const META_GRAPH_VERSION_END_DATES = array(
		'v21.0' => '2027-01-21',
		'v22.0' => '2027-05-20',
		'v23.0' => '2027-10-08',
		'v24.0' => '2028-02-18',
		'v25.0' => '2028-07-29',
	);

	/**
	 * Filet codé (R1) : après cette date, une version inférieure ou égale au
	 * plafond ci-dessous affiche un bandeau critique permanent.
	 */
	public const META_GRAPH_VERSION_CRITICAL_AFTER = '2026-12-01';

	/**
	 * Plafond du filet codé (R1).
	 */
	public const META_GRAPH_VERSION_CRITICAL_MAX = 'v21.0';

	/**
	 * Nombre de jours avant la fin d'une version où l'avertissement apparaît (R1).
	 */
	public const META_GRAPH_VERSION_NOTICE_DAYS = 60;

	/**
	 * Profils de données Meta (R3) ; le premier est la valeur par défaut.
	 */
	public const META_DATA_PROFILES = array( 'standard', 'minimal' );

	/**
	 * Modes de chargement du Pixel (D6) ; le premier est la valeur par défaut.
	 */
	public const META_PIXEL_LOADING_MODES = array( 'immediate', 'deferred' );

	/**
	 * Nombre maximal de tentatives d'envoi d'un même événement serveur.
	 */
	public const CAPI_MAX_ATTEMPTS = 5;

	/**
	 * Délais de relance après un échec transitoire (D1) : 5 min, 30 min, 2 h, 6 h.
	 */
	public const CAPI_RETRY_DELAYS = array( 300, 1800, 7200, 21600 );

	/**
	 * Âge maximal d'un event_time encore envoyé ou relancé (6 jours ; Meta refuse au-delà de 7).
	 */
	public const CAPI_MAX_EVENT_AGE = 518400;

	/**
	 * « Renvoyer » (R8) : un envoi rejeté reste renvoyable 6 jours.
	 */
	public const CAPI_RESEND_REJECTED_MAX_AGE = 518400;

	/**
	 * « Renvoyer » (R8) : un envoi incertain reste renvoyable 24 heures.
	 */
	public const CAPI_RESEND_UNCERTAIN_MAX_AGE = 86400;

	/**
	 * Un envoi « en cours » plus vieux que ceci est repris comme incertain (D1).
	 */
	public const CAPI_STALE_SENDING = 900;

	/**
	 * Format accepté pour une version de l'API Graph : vNN.N.
	 *
	 * @param string $version Valeur saisie.
	 * @return bool
	 */
	public static function valid_graph_version( string $version ): bool {
		return 1 === preg_match( '/^v\d{1,2}\.\d$/D', $version );
	}

	/**
	 * Version de l'API Graph visée par tous les appels Meta du serveur (S2).
	 *
	 * Ordre : constante `FAMMA_META_GRAPH_VERSION`, puis option
	 * `famma_meta_graph_version` (écran « Mesure »), puis la valeur par défaut.
	 * Le filtre `famma_capi_graph_version` reste possible ; une valeur filtrée
	 * hors format est ignorée.
	 *
	 * @return string
	 */
	public function meta_graph_version(): string {
		$version = $this->meta_graph_version_unfiltered();
		$custom  = apply_filters( 'famma_capi_graph_version', $version );

		return is_string( $custom ) && self::valid_graph_version( $custom ) ? $custom : $version;
	}

	/**
	 * Origine de la version retenue, pour l'écran « Mesure ».
	 *
	 * @return string `constant`, `option` ou `default`.
	 */
	public function meta_graph_version_source(): string {
		if ( self::valid_graph_version( $this->c( 'FAMMA_META_GRAPH_VERSION' ) ) ) {
			return 'constant';
		}

		return self::valid_graph_version( trim( (string) get_option( 'famma_meta_graph_version', '' ) ) ) ? 'option' : 'default';
	}

	/**
	 * Version retenue avant le filtre de code.
	 *
	 * @return string
	 */
	private function meta_graph_version_unfiltered(): string {
		$constant = $this->c( 'FAMMA_META_GRAPH_VERSION' );
		if ( self::valid_graph_version( $constant ) ) {
			return $constant;
		}
		$option = trim( (string) get_option( 'famma_meta_graph_version', '' ) );

		return self::valid_graph_version( $option ) ? $option : self::META_GRAPH_VERSION_DEFAULT;
	}

	/**
	 * Format d'un code de l'outil « Tester les événements » : TEST suivi de chiffres (A12).
	 *
	 * @param string $code Code saisi.
	 * @return bool
	 */
	public static function valid_test_event_code( string $code ): bool {
		return 1 === preg_match( '/^TEST\d+$/D', $code );
	}

	/**
	 * Type d'environnement WordPress (`production` si rien n'est déclaré).
	 *
	 * @return string
	 */
	public function environment_type(): string {
		return function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production';
	}

	/**
	 * Garde d'environnement (D4) : un envoi réel à Meta n'est permis qu'en production.
	 *
	 * Une copie du site (Local, clone Docker, préproduction) n'envoie rien,
	 * même avec le vrai jeton, sauf dérogation explicite par la constante
	 * `FAMMA_META_ALLOW_NON_PRODUCTION` (ou le filtre de code
	 * `famma_meta_sending_allowed`, pour un banc de test). Une option ne
	 * suffirait pas : elle voyage avec la base copiée.
	 *
	 * @return bool
	 */
	public function meta_sending_allowed(): bool {
		$environment = $this->environment_type();
		$allowed     = 'production' === $environment
			|| ( defined( 'FAMMA_META_ALLOW_NON_PRODUCTION' ) && true === filter_var( constant( 'FAMMA_META_ALLOW_NON_PRODUCTION' ), FILTER_VALIDATE_BOOLEAN ) );

		return (bool) apply_filters( 'famma_meta_sending_allowed', $allowed, $environment );
	}

	/**
	 * Profil de données Meta (R3) : `standard` par défaut, `minimal` en urgence.
	 *
	 * @return string
	 */
	public function meta_data_profile(): string {
		$profile = (string) get_option( 'famma_meta_data_profile', self::META_DATA_PROFILES[0] );

		return in_array( $profile, self::META_DATA_PROFILES, true ) ? $profile : self::META_DATA_PROFILES[0];
	}

	/**
	 * Chargement du Pixel (D6) : `immediate` par défaut, ou `deferred`.
	 *
	 * @return string
	 */
	public function meta_pixel_loading(): string {
		$mode = (string) get_option( 'famma_meta_pixel_loading', self::META_PIXEL_LOADING_MODES[0] );

		return in_array( $mode, self::META_PIXEL_LOADING_MODES, true ) ? $mode : self::META_PIXEL_LOADING_MODES[0];
	}

	/**
	 * Dérogation « Suivre mes visites (mode test) » (S1) : faux par défaut.
	 *
	 * @return bool
	 */
	public function track_internal_visitors(): bool {
		if ( defined( 'FAMMA_TRACK_INTERNAL' ) && true === filter_var( constant( 'FAMMA_TRACK_INTERNAL' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return true;
		}

		return (bool) get_option( 'famma_track_internal', false );
	}

	/**
	 * Envoi d'OrderPlaced aussi par le navigateur (S3-a) : coupé par défaut (E6).
	 *
	 * @return bool
	 */
	public function meta_orderplaced_browser(): bool {
		return (bool) get_option( 'famma_meta_orderplaced_browser', false );
	}

	/**
	 * TikTok pixel identifier.
	 *
	 * @return string
	 */
	public function tiktok_pixel_id(): string {
		return $this->setting( 'FAMMA_TIKTOK_PIXEL_ID' );
	}

	/**
	 * GA4 measurement identifier.
	 *
	 * @return string
	 */
	public function ga4_measurement_id(): string {
		return $this->setting( 'FAMMA_GA4_MEASUREMENT_ID' );
	}

	/**
	 * Google site verification token.
	 *
	 * @return string
	 */
	public function google_site_verification(): string {
		return $this->c( 'FAMMA_GOOGLE_SITE_VERIFICATION' );
	}

	/**
	 * Internal shipping cost in TND — never charged to the customer (§3, §4).
	 *
	 * @return float
	 */
	public function internal_shipping_cost(): float {
		return (float) get_option( 'famma_internal_shipping_cost', 7.0 );
	}

	/**
	 * Default selling-price multiplier applied to cost price (§3).
	 *
	 * @return float
	 */
	public function price_multiplier(): float {
		return (float) get_option( 'famma_price_multiplier', 3.0 );
	}
}

/**
 * Custom COD order workflow and the payment rule that goes with it.
 *
 * See docs/ORDER-WORKFLOW.md for why three filters are needed to make
 * "paid on delivery" actually work.
 */
final class Order_Statuses {

	/**
	 * Singleton instance.
	 *
	 * @var Order_Statuses|null
	 */
	private static ?Order_Statuses $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Order_Statuses
	 */
	public static function instance(): Order_Statuses {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * The custom statuses, slug => translated label.
	 *
	 * @return array<string,string>
	 */
	private function statuses(): array {
		return array(
			'pending-confirm' => _x( 'Pending confirmation', 'Order status', 'famma-core' ),
			'confirmed'       => _x( 'Confirmed', 'Order status', 'famma-core' ),
			'shipped'         => _x( 'Shipped', 'Order status', 'famma-core' ),
			'delivered'       => _x( 'Delivered', 'Order status', 'famma-core' ),
			'failed-delivery' => _x( 'Failed delivery', 'Order status', 'famma-core' ),
			'returned'        => _x( 'Returned', 'Order status', 'famma-core' ),
			'refused'         => _x( 'Refused', 'Order status', 'famma-core' ),
		);
	}

	/**
	 * Registers WordPress and WooCommerce hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_to_wc_list' ) );

		/*
		 * Encaissement en priorité 10, Purchase en priorité 20 (Capi), sur
		 * « Livrée » comme sur « Terminée » : la conversion ne part jamais
		 * avant l'encaissement (lot 5, D5).
		 */
		add_action( 'woocommerce_order_status_delivered', array( $this, 'mark_cod_paid_on_delivery' ), 10 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'mark_cod_paid_on_completion' ), 10 );
		add_filter( 'woocommerce_reports_order_statuses', array( $this, 'reporting_statuses' ) );

		/*
		 * Without these three filters payment_complete() does nothing at all
		 * from a custom status: WooCommerce only performs the payment
		 * bookkeeping from on-hold / pending / failed / cancelled. Verified
		 * broken at runtime on WooCommerce 11.0.1 — the order stayed
		 * "delivered" but with no paid date, and revenue was never counted.
		 * This is non-negotiable business rule 4 (§12).
		 */
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'allow_complete_from_delivered' ), 10, 2 );
		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'paid_statuses' ) );

		/*
		 * Priority 99, not 10: WC_Gateway_COD registers this same filter and
		 * forces "completed" for any cash-on-delivery order. Gateways are
		 * instantiated after plugins_loaded, so at equal priority its callback
		 * would run after ours and the order would leave "delivered"
		 * immediately. Observed at runtime.
		 */
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'stay_delivered_when_paid' ), 99, 3 );

		/*
		 * WC_Gateway_COD drops a fresh order straight into "processing", and
		 * "processing" is one of WooCommerce's paid statuses — so a brand-new,
		 * undelivered order reported is_paid() === true. Observed on a real
		 * browser checkout. §12 forbids exactly that. New COD orders therefore
		 * start at "pending confirmation", which is the first step of the
		 * workflow in §2 anyway.
		 */
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'cod_initial_status' ) );

		/*
		 * Conséquence du point précédent : WooCommerce ne rattache ses e-mails
		 * « Nouvelle commande » qu'aux transitions vers processing / on-hold /
		 * completed. Leur envoi, hors du checkout, vit dans `Order_Emails`
		 * (audit du 23/09 CODE-01, puis PERF-23).
		 */
	}

	/**
	 * First status of a cash-on-delivery order (§2, §12).
	 *
	 * @return string
	 */
	public function cod_initial_status(): string {
		return 'pending-confirm';
	}

	/**
	 * Registers the custom statuses as post statuses.
	 *
	 * @return void
	 */
	public function register_post_statuses(): void {
		foreach ( $this->statuses() as $slug => $label ) {
			register_post_status(
				'wc-' . $slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,

					/*
					 * Built by hand rather than with _n_noop(): the label is
					 * dynamic, and translation functions must receive literal
					 * strings. The label itself is already translated above.
					 */
					'label_count'               => array(
						'singular' => $label . ' <span class="count">(%s)</span>',
						'plural'   => $label . ' <span class="count">(%s)</span>',
						'context'  => null,
						'domain'   => 'famma-core',
					),
				)
			);
		}
	}

	/**
	 * Inserts the custom statuses into the WooCommerce status list, in workflow order.
	 *
	 * @param array<string,string> $s Existing statuses.
	 * @return array<string,string>
	 */
	public function add_to_wc_list( array $s ): array {
		$out = array();
		$st  = $this->statuses();
		foreach ( $s as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'wc-pending' === $k ) {
				$out['wc-pending-confirm'] = $st['pending-confirm'];
				$out['wc-confirmed']       = $st['confirmed'];
			}
			if ( 'wc-processing' === $k ) {
				$out['wc-shipped']         = $st['shipped'];
				$out['wc-delivered']       = $st['delivered'];
				$out['wc-failed-delivery'] = $st['failed-delivery'];
				$out['wc-returned']        = $st['returned'];
				$out['wc-refused']         = $st['refused'];
			}
		}
		return $out;
	}

	/**
	 * Collects the cash payment when the order reaches "delivered" (§12).
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function mark_cod_paid_on_delivery( int $order_id ): void {
		$o = wc_get_order( $order_id );
		if ( ! $o instanceof \WC_Order ) {
			return;
		}
		$this->collect_cod_payment( $o, __( 'COD payment collected on delivery.', 'famma-core' ) );
	}

	/**
	 * Encaisse une commande en paiement à la livraison passée à « Terminée » (D5).
	 *
	 * Décision par défaut, réversible : pour une commande payée à la
	 * livraison, « Terminée » vaut « Livrée ». Avant, WooCommerce la marquait
	 * payée sans encaissement COD ni Purchase (T2.4). Une commande d'un
	 * autre moyen de paiement garde le comportement de WooCommerce.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function mark_cod_paid_on_completion( int $order_id ): void {
		$o = wc_get_order( $order_id );
		if ( ! $o instanceof \WC_Order || ! self::is_cod_order( $o ) ) {
			return;
		}
		$this->collect_cod_payment( $o, __( 'Paiement à la livraison encaissé : commande passée à « Terminée ».', 'famma-core' ) );
	}

	/**
	 * La commande est-elle payée à la livraison ?
	 *
	 * Le paiement à la livraison est le seul moyen de paiement de la boutique
	 * (§2) : une commande sans moyen de paiement (créée dans l'administration,
	 * par exemple) en relève aussi. Filtrable par `famma_order_is_cod`.
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool
	 */
	public static function is_cod_order( \WC_Order $order ): bool {
		$cod = in_array( (string) $order->get_payment_method(), array( 'cod', '' ), true );

		return (bool) apply_filters( 'famma_order_is_cod', $cod, $order );
	}

	/**
	 * Pourquoi une commande ne peut pas (encore) donner de Purchase ; vide si elle le peut.
	 *
	 * Règle du circuit de commande (§2, D5), seule et partagée par l'émetteur
	 * (Capi), le rapprochement (R8) et l'état de santé (R7) : « Livrée », ou
	 * « Terminée » pour une commande payée à la livraison déjà encaissée. Le
	 * rapprochement reste indépendant de la logique d'envoi (réglages,
	 * environnement, registre), mais n'attend jamais un Purchase que
	 * l'émetteur refuserait.
	 *
	 * @param \WC_Order $order Commande.
	 * @return string `not_delivered`, `not_collected` (« Terminée » en paiement à
	 *                la livraison sans encaissement, cas d'avant la 0.3.2) ou vide.
	 */
	public static function purchase_ineligibility( \WC_Order $order ): string {
		if ( $order->has_status( 'delivered' ) ) {
			return '';
		}
		if ( $order->has_status( 'completed' ) && self::is_cod_order( $order ) ) {
			return 'yes' === $order->get_meta( '_famma_cod_paid' ) ? '' : 'not_collected';
		}
		return 'not_delivered';
	}

	/**
	 * Runs the COD bookkeeping once, whatever status triggered it.
	 *
	 * @param \WC_Order $o    Order being collected.
	 * @param string    $note Order note recorded with the collection.
	 * @return void
	 */
	private function collect_cod_payment( \WC_Order $o, string $note ): void {
		/*
		 * The guard uses a marker of our own, not is_paid() and not date_paid.
		 * Both are already true on entry: WC_Abstract_Order::maybe_set_date_paid()
		 * runs during save(), hence before this transition, and treats
		 * "delivered" as the payment-complete status because of our
		 * stay_delivered_when_paid() filter. Relying on either would return
		 * early, payment_complete() would never run, the
		 * woocommerce_payment_complete action would never fire — and stock
		 * would never be decremented.
		 */
		if ( 'yes' === $o->get_meta( '_famma_cod_paid' ) ) {
			return;
		}

		$o->payment_complete();
		$o->update_meta_data( '_famma_cod_paid', 'yes' );
		$o->add_order_note( $note );
		$o->save();
	}

	/**
	 * Allows payment_complete() to run from "delivered", and from "completed"
	 * for a cash-on-delivery order (D5).
	 *
	 * @param string[]       $statuses Statuses payment completion is allowed from.
	 * @param \WC_Order|null $order    Order concerned, when WooCommerce passes it.
	 * @return string[]
	 */
	public function allow_complete_from_delivered( array $statuses, $order = null ): array {
		$statuses[] = 'delivered';
		if ( $order instanceof \WC_Order && $order->has_status( 'completed' ) && self::is_cod_order( $order ) ) {
			$statuses[] = 'completed';
		}
		return $statuses;
	}

	/**
	 * Keeps the order in "delivered" instead of jumping to processing/completed,
	 * and a "completed" order where it is.
	 *
	 * @param string         $status   Default target status.
	 * @param int            $order_id Order identifier.
	 * @param \WC_Order|null $order    Order concerned.
	 * @return string
	 */
	public function stay_delivered_when_paid( string $status, int $order_id, $order = null ): string {
		if ( $order instanceof \WC_Order && $order->has_status( 'delivered' ) ) {
			return 'delivered';
		}
		if ( $order instanceof \WC_Order && $order->has_status( 'completed' ) ) {
			return 'completed';
		}
		return $status;
	}

	/**
	 * Makes "delivered" count as paid.
	 *
	 * Without this is_paid() stays false and revenue reports silently ignore
	 * every delivered order.
	 *
	 * @param mixed $statuses Statuses considered paid.
	 * @return mixed
	 */
	public function paid_statuses( mixed $statuses ): mixed {
		// Another filter may hand over a non-array: pass it on instead of crashing.
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}

		$statuses[] = 'delivered';

		/*
		 * "processing" is dropped on purpose. WooCommerce treats it as paid,
		 * which is right for a prepaid gateway but wrong here: §2 places
		 * PROCESSING between CONFIRMED and SHIPPED, i.e. while the parcel is
		 * being prepared and no cash has been collected yet. Counting it as
		 * revenue is the false-positive §12 forbids.
		 *
		 * Valid while cash on delivery is the only gateway (§2). Adding an
		 * online payment method later means revisiting this line.
		 */
		return array_values( array_diff( $statuses, array( 'processing' ) ) );
	}

	/**
	 * Includes delivered orders in WooCommerce reports.
	 *
	 * WooCommerce passes false here when a report query must not filter by
	 * status at all (refund queries of the "Sales by date" report). That value
	 * is returned untouched: forcing a list would change the query, and a
	 * typed array parameter crashed the report (fatal error of 13/10/2026).
	 *
	 * @param mixed $s Statuses included in reports, or false for "any status".
	 * @return mixed
	 */
	public function reporting_statuses( mixed $s ): mixed {
		if ( ! is_array( $s ) ) {
			return $s;
		}

		// Same reasoning as paid_statuses(): a COD order is revenue only once delivered.
		$s = array_values( array_diff( $s, array( 'processing', 'on-hold' ) ) );
		foreach ( array( 'delivered', 'completed' ) as $x ) {
			if ( ! in_array( $x, $s, true ) ) {
				$s[] = $x;
			}
		}
		return $s;
	}
}

/**
 * Stock reservation policy for the cash-on-delivery workflow (§2).
 *
 * WooCommerce reserves stock when an order reaches processing, on-hold or
 * completed. None of those is on the COD path, so without this class stock
 * was only ever decremented at delivery — long after the parcel left, and
 * late enough for two orders to be taken on the same last unit.
 *
 * The reservation point is "confirmed", i.e. once a human has confirmed the
 * order by phone. Reserving earlier would let unconfirmed or fake orders
 * block real stock, which is a routine problem on Tunisian COD stores (§47).
 * Stock is given back on every outcome where the goods come home.
 */
final class Stock {

	/**
	 * Singleton instance.
	 *
	 * @var Stock|null
	 */
	private static ?Stock $i = null;

	/**
	 * Statuses on which the goods return to stock.
	 *
	 * @var string[]
	 */
	private const RELEASING_STATUSES = array( 'cancelled', 'refused', 'returned', 'failed-delivery' );

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Stock
	 */
	public static function instance(): Stock {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers the reservation and release hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_order_status_confirmed', array( $this, 'reserve' ) );
		foreach ( self::RELEASING_STATUSES as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'release' ) );
		}
	}

	/**
	 * Reserves stock for a confirmed order.
	 *
	 * Idempotent: WooCommerce guards on the _order_stock_reduced meta, so a
	 * replayed transition cannot decrement twice.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function reserve( int $order_id ): void {
		wc_maybe_reduce_stock_levels( $order_id );
	}

	/**
	 * Puts the goods back in stock when the order does not reach the customer.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function release( int $order_id ): void {
		wc_maybe_increase_stock_levels( $order_id );
	}
}

/**
 * Internal product economics: cost, margin, and the cost×3 default price (§3).
 *
 * None of these figures is ever shown to a customer.
 */
final class Product_Financials {

	/**
	 * Singleton instance.
	 *
	 * @var Product_Financials|null
	 */
	private static ?Product_Financials $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Product_Financials
	 */
	public static function instance(): Product_Financials {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
	}

	/**
	 * Renders the private FAMMA financials panel on the product screen.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'FAMMA financials (internal — not shown to customers)', 'famma-core' ) . '</strong></p>';
		woocommerce_wp_text_input(
			array(
				'id'          => '_famma_cost_price',
				'label'       => __( 'Cost price (TND)', 'famma-core' ),
				'desc_tip'    => true,
				'description' => __( 'Leave selling price empty to auto-set to cost × 3.', 'famma-core' ),
				'data_type'   => 'price',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_famma_shipping_cost',
				'label'       => __( 'Internal shipping cost (TND)', 'famma-core' ),
				'desc_tip'    => true,
				'description' => __( 'Included in price; customer pays 0.', 'famma-core' ),
				'data_type'   => 'price',
				'placeholder' => (string) Config::instance()->internal_shipping_cost(),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'        => '_famma_handling_cost',
				'label'     => __( 'Handling cost (TND)', 'famma-core' ),
				'data_type' => 'price',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'        => '_famma_acquisition_cost',
				'label'     => __( 'Acquisition cost / order (TND)', 'famma-core' ),
				'data_type' => 'price',
			)
		);

		global $product_object;
		if ( $product_object instanceof \WC_Product ) {
			$m = $this->margin( $product_object );
			echo '<p class="form-field"><span>' . esc_html(
				sprintf(
					/* translators: 1: net profit in TND, 2: margin as a percentage */
					__( 'Estimated net margin: %1$s TND (%2$s%%)', 'famma-core' ),
					number_format( $m['profit'], 2 ),
					number_format( $m['percent'], 1 )
				)
			) . '</span></p>';
		}
		echo '</div>';
	}

	/**
	 * Persists the financial fields and applies the cost×3 default (§3).
	 *
	 * WooCommerce vérifie déjà le nonce avant de déclencher ce hook, mais la
	 * règle 4 du projet impose la séquence complète — nonce, capacité,
	 * assainissement — sur place et sans la déléguer. Le contrôle est donc
	 * refait ici, ce qui supprime au passage les `phpcs:ignore` qui
	 * signalaient l'absence de vérification.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_fields( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		foreach ( array( '_famma_cost_price', '_famma_shipping_cost', '_famma_handling_cost', '_famma_acquisition_cost' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = wc_clean( wp_unslash( $_POST[ $key ] ) );
				$product->update_meta_data( $key, '' === $val ? '' : wc_format_decimal( $val ) );
			}
		}

		$cost = (float) $product->get_meta( '_famma_cost_price' );
		if ( $cost > 0 && '' === (string) $product->get_regular_price() ) {
			$product->set_regular_price( wc_format_decimal( $cost * Config::instance()->price_multiplier() ) );
		}
	}

	/**
	 * Computes the estimated margin for a product.
	 *
	 * Le prix retenu est le prix EFFECTIF, pas le prix barré : c'est celui
	 * que le client paie, donc le seul qui produise une marge réelle. Sur un
	 * produit en promotion, se fier au prix régulier surestimait la marge en
	 * silence — exactement au moment où elle se resserre.
	 *
	 * @param \WC_Product $product Product to evaluate.
	 * @return array{price:float,total_cost:float,profit:float,percent:float}
	 */
	public function margin( \WC_Product $product ): array {
		$effective = $product->get_price();
		$price     = '' === (string) $effective
			? (float) $product->get_regular_price()
			: (float) $effective;

		$cost   = (float) $product->get_meta( '_famma_cost_price' );
		$sm     = $product->get_meta( '_famma_shipping_cost' );
		$ship   = '' === (string) $sm ? Config::instance()->internal_shipping_cost() : (float) $sm;
		$tot    = $cost + $ship + (float) $product->get_meta( '_famma_handling_cost' ) + (float) $product->get_meta( '_famma_acquisition_cost' );
		$profit = $price - $tot;

		return array(
			'price'      => $price,
			'total_cost' => $tot,
			'profit'     => $profit,
			'percent'    => $price > 0 ? ( $profit / $price ) * 100 : 0.0,
		);
	}
}

/**
 * Per-product FAQ, written by the owner.
 *
 * La FAQ vit dans le plugin et non dans le thème : c'est une donnée métier,
 * elle doit survivre à un changement de thème (règle 6). Le thème ne fait que
 * la présenter.
 *
 * Aucune question n'est fournie par défaut. §60 interdit le contenu inventé,
 * et une FAQ est précisément le texte qui engage la boutique : « puis-je
 * échanger ? », « en combien de temps ? ». Une réponse approximative écrite
 * ici deviendrait une promesse non tenue à la porte du client.
 */
final class Product_Faq {

	/**
	 * Meta key holding the parsed question/answer pairs.
	 *
	 * @var string
	 */
	public const META = '_famma_faq';

	/**
	 * Singleton instance.
	 *
	 * @var Product_Faq|null
	 */
	private static ?Product_Faq $i = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Product_Faq
	 */
	public static function instance(): Product_Faq {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'woocommerce_product_options_advanced', array( $this, 'render_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_field' ) );
	}

	/**
	 * Renders the FAQ textarea on the product screen.
	 *
	 * Un seul champ texte plutôt qu'un répéteur en JavaScript : le format est
	 * lisible tel quel, se relit sans interface, et ne casse pas si le script
	 * d'administration ne se charge pas.
	 *
	 * @return void
	 */
	public function render_field(): void {
		global $product_object;

		$value = '';

		if ( $product_object instanceof \WC_Product ) {
			$value = $this->to_text( $this->pairs( $product_object ) );
		}

		echo '<div class="options_group">';
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META,
				'value'       => $value,
				'label'       => __( 'Product FAQ', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'One entry per block: the question on the first line, the answer on the following lines. Separate entries with a blank line. Leave empty to hide the FAQ tab.', 'famma-core' ),
				'rows'        => 10,
			)
		);
		echo '</div>';
	}

	/**
	 * Persists the FAQ pairs.
	 *
	 * Séquence complète de la règle 4 — nonce, capacité, assainissement —
	 * refaite sur place comme dans Product_Financials.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_field( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::META ] ) ) {
			return;
		}

		$raw = sanitize_textarea_field( wp_unslash( $_POST[ self::META ] ) );

		$product->update_meta_data( self::META, $this->parse( $raw ) );
	}

	/**
	 * Returns the stored pairs for a product, always as a clean list.
	 *
	 * @param \WC_Product $product Product to read.
	 * @return array<int, array<string, string>>
	 */
	public function pairs( \WC_Product $product ): array {
		$stored = $product->get_meta( self::META );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$pairs = array();

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['q'], $entry['a'] ) ) {
				continue;
			}

			$question = trim( (string) $entry['q'] );
			$answer   = trim( (string) $entry['a'] );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'q' => $question,
				'a' => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Parses the textarea into question/answer pairs.
	 *
	 * Une entrée sans réponse est ignorée, pas stockée à moitié : un accordéon
	 * qui s'ouvre sur du vide est un défaut visible par le client.
	 *
	 * @param string $raw Sanitised textarea content.
	 * @return array<int, array<string, string>>
	 */
	private function parse( string $raw ): array {
		$blocks = preg_split( '/\R{2,}/u', trim( $raw ) );
		$pairs  = array();

		foreach ( (array) $blocks as $block ) {
			$lines = preg_split( '/\R/u', trim( (string) $block ) );
			$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );

			if ( count( $lines ) < 2 ) {
				continue;
			}

			$question = sanitize_text_field( (string) array_shift( $lines ) );
			$answer   = sanitize_textarea_field( implode( "\n", $lines ) );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$pairs[] = array(
				'q' => $question,
				'a' => $answer,
			);
		}

		return $pairs;
	}

	/**
	 * Rebuilds the editable text from stored pairs.
	 *
	 * @param array<int, array<string, string>> $pairs Stored pairs.
	 * @return string
	 */
	private function to_text( array $pairs ): string {
		$blocks = array();

		foreach ( $pairs as $pair ) {
			$blocks[] = $pair['q'] . "\n" . $pair['a'];
		}

		return implode( "\n\n", $blocks );
	}
}

/**
 * Shipping economics: 0 TND charged, 7 TND tracked internally (§3, §4).
 */
final class Shipping {

	/**
	 * Singleton instance.
	 *
	 * @var Shipping|null
	 */
	private static ?Shipping $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Shipping
	 */
	public static function instance(): Shipping {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'woocommerce_general_settings', array( $this, 'settings' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'record_internal_shipping' ), 20 );
	}

	/**
	 * Adds the FAMMA business-rule block to WooCommerce general settings.
	 *
	 * @param array<int,array<string,mixed>> $s Existing settings.
	 * @return array<int,array<string,mixed>>
	 */
	public function settings( array $s ): array {
		return array_merge(
			$s,
			array(
				array(
					'title' => __( 'FAMMA business rules', 'famma-core' ),
					'type'  => 'title',
					'id'    => 'famma_business_rules',
				),
				array(
					'title'             => __( 'Default price multiplier', 'famma-core' ),
					'desc'              => __( 'Selling price = cost × this (default 3). Manual prices override.', 'famma-core' ),
					'id'                => 'famma_price_multiplier',
					'type'              => 'number',
					'default'           => '3',
					'custom_attributes' => array(
						'step' => '0.1',
						'min'  => '1',
					),
				),
				array(
					'title'             => __( 'Internal shipping cost (TND)', 'famma-core' ),
					'desc'              => __( 'Included in price; customer pays 0. Margin only.', 'famma-core' ),
					'id'                => 'famma_internal_shipping_cost',
					'type'              => 'number',
					'default'           => '7',
					'custom_attributes' => array(
						'step' => '0.5',
						'min'  => '0',
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'famma_business_rules',
				),
			)
		);
	}

	/**
	 * Stamps the internal shipping cost on the order for later margin analysis.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function record_internal_shipping( \WC_Order $order ): void {
		$order->update_meta_data( '_famma_internal_shipping_cost', Config::instance()->internal_shipping_cost() );
	}
}

/**
 * Tunisia-specific checkout rules: governorates and phone validation (§13, §47).
 */
final class Tunisia {

	/**
	 * Singleton instance.
	 *
	 * @var Tunisia|null
	 */
	private static ?Tunisia $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Tunisia
	 */
	public static function instance(): Tunisia {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * These target the classic (shortcode) checkout, which is the deliberate
	 * choice recorded in docs/DECISIONS.md, ADR-003.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_filter( 'woocommerce_states', array( $this, 'governorates' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_filter( 'woocommerce_process_checkout_field_billing_phone', array( $this, 'normalize_billing_phone' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_phone' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_full_name' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'require_email_for_account' ), 20, 2 );

		/*
		 * The country field is removed from the checkout — the shop only sells
		 * to Tunisia, so asking is pure friction (§13). But WooCommerce still
		 * needs a country: WC_Checkout::process_checkout() aborts with
		 * "Please enter an address to continue." when the customer's shipping
		 * country is empty. Verified in a real browser checkout: no order
		 * could be placed at all. These four filters supply it.
		 */
		add_filter( 'woocommerce_customer_default_country', array( $this, 'default_country' ) );
		add_filter( 'default_checkout_billing_country', array( $this, 'default_country' ) );
		add_filter( 'default_checkout_shipping_country', array( $this, 'default_country' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'force_country' ) );

		/*
		 * The store's base location pre-filled « Tunis » as every guest's
		 * governorate (audit 25/09, UX-43): a hurried customer from Sfax
		 * confirmed the wrong one. The country stays, the governorate is left
		 * for the customer to choose, as on the express form.
		 */
		add_filter( 'woocommerce_customer_default_location_array', array( $this, 'blank_default_state' ) );

		/*
		 * One "full name" field instead of first + last name, and an optional
		 * city (owner's decision, 23/09/2026): the governorate is enough to
		 * route a parcel, and every extra field on mobile costs orders.
		 */
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'split_full_name' ), 20 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale' ) );

		add_filter( 'woocommerce_currency_symbol', array( $this, 'currency_symbol' ), 10, 2 );
	}

	/**
	 * Splits the single "full name" field into first and last name.
	 *
	 * The customer types « Mohamed Ben Ali » once; the order still carries a
	 * first and a last name, which is what the courier, the order e-mails and
	 * the Meta Conversions API expect. First word = first name, the rest = last
	 * name. A one-word name leaves the last name empty, which is accepted.
	 *
	 * When the parcel goes to the billing address, WooCommerce has already
	 * copied the billing names into the shipping ones: they are aligned here.
	 *
	 * @param array<string,mixed> $data Posted checkout data.
	 * @return array<string,mixed>
	 */
	public function split_full_name( array $data ): array {
		$full = trim( (string) ( $data['billing_first_name'] ?? '' ) );

		if ( '' === $full || '' !== trim( (string) ( $data['billing_last_name'] ?? '' ) ) ) {
			return $data;
		}

		$parts = preg_split( '/\s+/u', $full, 2 );
		$parts = is_array( $parts ) ? $parts : array( $full );

		$data['billing_first_name'] = (string) $parts[0];
		$data['billing_last_name']  = (string) ( $parts[1] ?? '' );

		if ( empty( $data['ship_to_different_address'] ) && array_key_exists( 'shipping_first_name', $data ) ) {
			$data['shipping_first_name'] = $data['billing_first_name'];
			$data['shipping_last_name']  = $data['billing_last_name'];
		}

		return $data;
	}

	/**
	 * Makes the city optional for Tunisia in the country locale.
	 *
	 * The locale is what the classic checkout script reads to toggle the
	 * « required » mark when the address form is refreshed; without this entry
	 * it would put the asterisk back on the city field.
	 *
	 * @param array<string,array<string,mixed>> $locale Locale rules by country.
	 * @return array<string,array<string,mixed>>
	 */
	public function country_locale( array $locale ): array {
		$locale['TN']['city']['required'] = false;

		return $locale;
	}

	/**
	 * « DT » on the French site, « د.ت » on the Arabic one.
	 *
	 * In a left-to-right paragraph, a number followed by Arabic letters is laid
	 * out right-to-left as a block: « 79,000 د.ت » was displayed with the symbol
	 * BEFORE the amount on the French pages (audit du 23/09, FP-06). « DT » is
	 * also how Tunisian shops write the dinar in French.
	 *
	 * @param string $symbol   Currency symbol.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public function currency_symbol( string $symbol, string $currency ): string {
		if ( 'TND' !== $currency || str_starts_with( determine_locale(), 'ar' ) ) {
			return $symbol;
		}

		return 'DT';
	}

	/**
	 * Tunisia is the only country the shop sells to (§11).
	 *
	 * @return string
	 */
	public function default_country(): string {
		return 'TN';
	}

	/**
	 * Keeps Tunisia as the default location, without a governorate.
	 *
	 * @param array<string,string> $location Default location (`country`, `state`).
	 * @return array<string,string>
	 */
	public function blank_default_state( $location ): array {
		$location = (array) $location;

		return array(
			'country' => isset( $location['country'] ) && '' !== $location['country'] ? (string) $location['country'] : 'TN',
			'state'   => '',
		);
	}

	/**
	 * Stamps Tunisia on the posted checkout data, since the field is hidden.
	 *
	 * @param array<string,mixed> $data Posted checkout data.
	 * @return array<string,mixed>
	 */
	public function force_country( array $data ): array {
		$data['billing_country']  = 'TN';
		$data['shipping_country'] = 'TN';
		return $data;
	}

	/**
	 * Declares the 24 Tunisian governorates as WooCommerce states.
	 *
	 * @param array<string,array<string,string>> $states Existing states by country.
	 * @return array<string,array<string,string>>
	 */
	public function governorates( array $states ): array {
		$states['TN'] = array(
			'TN-11' => 'Tunis',
			'TN-12' => 'Ariana',
			'TN-13' => 'Ben Arous',
			'TN-14' => 'Manouba',
			'TN-21' => 'Nabeul',
			'TN-22' => 'Zaghouan',
			'TN-23' => 'Bizerte',
			'TN-31' => 'Béja',
			'TN-32' => 'Jendouba',
			'TN-33' => 'Le Kef',
			'TN-34' => 'Siliana',
			'TN-41' => 'Kairouan',
			'TN-42' => 'Kasserine',
			'TN-43' => 'Sidi Bouzid',
			'TN-51' => 'Sousse',
			'TN-52' => 'Monastir',
			'TN-53' => 'Mahdia',
			'TN-61' => 'Sfax',
			'TN-71' => 'Gafsa',
			'TN-72' => 'Tozeur',
			'TN-73' => 'Kébili',
			'TN-81' => 'Gabès',
			'TN-82' => 'Médenine',
			'TN-83' => 'Tataouine',
		);
		return $states;
	}

	/**
	 * Trims the checkout to what a Tunisian COD order actually needs (§13).
	 *
	 * @param array<string,array<string,mixed>> $f Checkout fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function checkout_fields( array $f ): array {
		unset(
			$f['billing']['billing_company'],
			$f['billing']['billing_address_2'],
			$f['billing']['billing_postcode'],
			$f['billing']['billing_country']
		);

		/*
		 * The same trim applies to the "ship to a different address" block:
		 * leaving it untouched put the country and postcode fields back in
		 * front of the customer as soon as the box was ticked.
		 */
		unset(
			$f['shipping']['shipping_company'],
			$f['shipping']['shipping_address_2'],
			$f['shipping']['shipping_postcode'],
			$f['shipping']['shipping_country']
		);
		if ( isset( $f['shipping']['shipping_state'] ) ) {
			$f['shipping']['shipping_state']['label'] = __( 'Governorate / الولاية', 'famma-core' );
		}
		if ( isset( $f['shipping']['shipping_city'] ) ) {
			$f['shipping']['shipping_city']['label'] = __( 'City / Delegation — المدينة', 'famma-core' );
		}

		/*
		 * Email stays available but stops being mandatory: §13 lists the
		 * required fields and email is not among them. On a cash-on-delivery
		 * order the reachable channel is the phone number (§33), and an extra
		 * required field on mobile is pure drop-off.
		 */
		if ( isset( $f['billing']['billing_email'] ) ) {
			$f['billing']['billing_email']['required'] = false;
			$f['billing']['billing_email']['priority'] = 90;
			$f['billing']['billing_email']['label']    = __( 'Email (optional) / البريد الإلكتروني', 'famma-core' );
		}

		/*
		 * One "full name" field: `split_full_name()` turns it back into first
		 * and last name on the order.
		 */
		unset( $f['billing']['billing_last_name'] );
		if ( isset( $f['billing']['billing_first_name'] ) ) {
			$f['billing']['billing_first_name']['label']        = __( 'Full name / الاسم واللقب', 'famma-core' );
			$f['billing']['billing_first_name']['autocomplete'] = 'name';
			$f['billing']['billing_first_name']['class']        = array( 'form-row-wide' );
		}

		if ( isset( $f['billing']['billing_phone'] ) ) {
			$f['billing']['billing_phone']['required']    = true;
			$f['billing']['billing_phone']['priority']    = 25;
			$f['billing']['billing_phone']['placeholder'] = 'XX XXX XXX';
			$f['billing']['billing_phone']['label']       = __( 'Phone / الهاتف', 'famma-core' );
		}
		if ( isset( $f['billing']['billing_state'] ) ) {
			$f['billing']['billing_state']['label']    = __( 'Governorate / الولاية', 'famma-core' );
			$f['billing']['billing_state']['required'] = true;
		}
		if ( isset( $f['billing']['billing_city'] ) ) {
			// WooCommerce appends « (optional) » itself to a non-required field.
			$f['billing']['billing_city']['label']    = __( 'City / Delegation — المدينة', 'famma-core' );
			$f['billing']['billing_city']['required'] = false;
		}
		if ( isset( $f['billing']['billing_address_1'] ) ) {
			$f['billing']['billing_address_1']['label'] = __( 'Address / العنوان', 'famma-core' );
		}
		return $f;
	}

	/**
	 * Requires an email address — but only from a customer asking for an account.
	 *
	 * Email is deliberately optional here (§13): on a cash-on-delivery order the
	 * reachable channel is the phone number, and an extra mandatory field on
	 * mobile is pure drop-off. WooCommerce, however, needs an email to open an
	 * account. Without this rule, ticking "create an account" with no email
	 * produced a raw WooCommerce error at the exact moment the customer believes
	 * they are done.
	 *
	 * The constraint applies to that case only: a guest order still needs no
	 * email. The message names the way out rather than just stating the refusal.
	 *
	 * Reads the posted data WooCommerce has already assembled and sanitised,
	 * never `$_POST`: `WC_Checkout::get_posted_data()` exposes `createaccount`,
	 * so there is no reason to reach for the raw superglobal.
	 *
	 * @param array<string, mixed> $data   Posted checkout data.
	 * @param \WP_Error            $errors Validation errors.
	 * @return void
	 */
	public function require_email_for_account( array $data, \WP_Error $errors ): void {
		if ( empty( $data['createaccount'] ) ) {
			return;
		}

		if ( '' !== trim( (string) ( $data['billing_email'] ?? '' ) ) ) {
			return;
		}

		$errors->add(
			'famma_account_email',
			__( 'To create an account, please give an email address. Otherwise choose "Order without an account": your order goes through without one.', 'famma-core' )
		);
	}

	/**
	 * Normalises a Tunisian phone number to its 8 national digits.
	 *
	 * Accepts what customers really type: spaces (including non-breaking
	 * ones), dots, dashes, parentheses, « + », Arabic-Indic and Persian digits,
	 * and the +216 / 00216 / 216 prefix. The prefix is only removed when the
	 * length proves it is one (13 digits « 00216… », 11 digits « 216… »): an
	 * 8-digit number starting with 216, such as 21612345, stays a number.
	 *
	 * @param string $raw Number as typed.
	 * @return string The 8 digits, or '' when the number is not plausible.
	 */
	public static function normalize_phone( string $raw ): string {
		$raw    = strtr(
			$raw,
			array(
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
			)
		);
		$digits = (string) preg_replace( '/\D+/', '', $raw );

		if ( 13 === strlen( $digits ) && str_starts_with( $digits, '00216' ) ) {
			$digits = substr( $digits, 5 );
		} elseif ( 11 === strlen( $digits ) && str_starts_with( $digits, '216' ) ) {
			$digits = substr( $digits, 3 );
		}

		return 1 === preg_match( '/^[2-9]\d{7}$/', $digits ) ? $digits : '';
	}

	/**
	 * Stores the billing phone as its 8 national digits.
	 *
	 * Applied by `WC_Checkout::get_posted_data()` before WooCommerce's own
	 * phone check (`WC_Validation::is_phone_format()` refuses Arabic-Indic
	 * digits): the order, the customer and the courier all get « 20123456 ».
	 * An implausible number is left as typed so `validate_phone()` names it.
	 *
	 * @param mixed $value Posted value, already passed through wc_clean().
	 * @return mixed
	 */
	public function normalize_billing_phone( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		$digits = self::normalize_phone( $value );

		return '' === $digits ? $value : $digits;
	}

	/**
	 * Rejects anything that is not a plausible Tunisian mobile/landline number.
	 *
	 * Same rule as normalize_phone(). WooCommerce's generic « not a valid
	 * phone number » error is replaced by this one, so the customer reads a
	 * single message. The `id` data becomes `data-id="billing_phone"` on the
	 * notice: the express form uses it to mark the field.
	 *
	 * @param array<string,mixed> $data   Posted checkout data.
	 * @param \WP_Error           $errors Error collector.
	 * @return void
	 */
	public function validate_phone( array $data, \WP_Error $errors ): void {
		if ( empty( $data['billing_phone'] ) ) {
			return;
		}
		if ( '' !== self::normalize_phone( (string) $data['billing_phone'] ) ) {
			return;
		}
		$errors->remove( 'billing_phone_validation' );
		$errors->add(
			'billing_phone_invalid',
			__( 'Please enter a valid Tunisian phone number (8 digits). أدخل رقم هاتف تونسي صحيح.', 'famma-core' ),
			array( 'id' => 'billing_phone' )
		);
	}

	/**
	 * Message d'un nom complet absent ou trop court (formulaire express et serveur).
	 *
	 * @return string
	 */
	public static function name_error(): string {
		return __( 'Indiquez votre nom complet (au moins 3 caractères). / اكتب اسمك ولقبك (3 حروف على الأقل).', 'famma-core' );
	}

	/**
	 * Refuse un nom complet de moins de 3 caractères.
	 *
	 * Le nom est déjà redécoupé par `split_full_name()` : prénom et nom sont
	 * recollés pour compter. Un nom vide est laissé à WooCommerce (champ
	 * obligatoire), pour ne pas afficher deux messages.
	 *
	 * @param array<string,mixed> $data   Données postées.
	 * @param \WP_Error           $errors Erreurs de validation.
	 * @return void
	 */
	public function validate_full_name( array $data, \WP_Error $errors ): void {
		$full = trim( (string) ( $data['billing_first_name'] ?? '' ) . ' ' . (string) ( $data['billing_last_name'] ?? '' ) );

		if ( '' === $full || mb_strlen( $full ) >= 3 ) {
			return;
		}

		$errors->add( 'billing_first_name_short', self::name_error(), array( 'id' => 'billing_first_name' ) );
	}
}

/**
 * Floating WhatsApp button and dynamic wa.me links (§15).
 *
 * Everything is a no-op until FAMMA_WHATSAPP_NUMBER is set: no markup, no CSS.
 */
final class WhatsApp {

	/**
	 * Singleton instance.
	 *
	 * @var WhatsApp|null
	 */
	private static ?WhatsApp $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return WhatsApp
	 */
	public static function instance(): WhatsApp {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers front-end hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_footer', array( $this, 'render_float' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
		add_shortcode( 'famma_whatsapp', array( $this, 'shortcode' ) );
	}

	/**
	 * Renders a WhatsApp call to action, or nothing at all.
	 *
	 * Returning an empty string when no number is configured is the point:
	 * page content can reference the CTA freely without ever producing a dead
	 * link while the owner has not supplied a number (§15, §60).
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'text'    => '',
				'message' => '',
			),
			$atts,
			'famma_whatsapp'
		);

		$href = self::link( $atts['message'] );
		if ( '' === $href ) {
			return '';
		}

		$label = '' !== $atts['text'] ? $atts['text'] : __( 'Contact us on WhatsApp', 'famma-core' );

		return sprintf(
			'<a class="famma-wa-cta" href="%1$s" target="_blank" rel="noopener nofollow">%2$s</a>',
			esc_url( $href ),
			esc_html( $label )
		);
	}

	/**
	 * Builds a wa.me link, or an empty string when no number is configured.
	 *
	 * @param string $message Optional prefilled message.
	 * @return string
	 */
	public static function link( string $message = '' ): string {
		$n = Config::instance()->whatsapp_number();
		if ( '' === $n ) {
			return '';
		}
		$u = 'https://wa.me/' . $n;
		if ( '' !== $message ) {
			$u .= '?text=' . rawurlencode( $message );
		}
		return $u;
	}

	/**
	 * Enqueues the button styles, only when the button will actually render.
	 *
	 * @return void
	 */
	public function styles(): void {
		if ( '' === Config::instance()->whatsapp_number() || ! self::show_float() ) {
			return;
		}

		/*
		 * L'anneau de focus était orange sur un bouton vert : 1,32:1,
		 * autrement dit invisible (§44). Bleu + halo blanc reste lisible
		 * quel que soit le contenu qui défile sous le bouton flottant.
		 *
		 * Les var() portent une valeur de repli : le plugin doit rester
		 * autonome si le thème enfant est remplacé (règle 6).
		 */
		$css = '.famma-wa{position:fixed;inset-inline-end:16px;inset-block-end:16px;z-index:var(--z-float,290);display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#25D366;box-shadow:0 4px 14px rgba(0,0,0,.25);text-decoration:none}.famma-wa svg{width:30px;height:30px;fill:#fff}.famma-wa:focus-visible{outline:3px solid var(--famma-blue,#0B1F3A);outline-offset:2px;box-shadow:0 0 0 5px #fff,0 4px 14px rgba(0,0,0,.25)}';
		wp_register_style( 'famma-wa', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-wa' );
		wp_add_inline_style( 'famma-wa', $css );
	}

	/**
	 * Should the floating button be shown on this page?
	 *
	 * The theme answers false where the page already carries its own WhatsApp
	 * card and the floating button would cover form fields (cart, checkout:
	 * audit 25/09, UX-49).
	 *
	 * @return bool
	 */
	public static function show_float(): bool {
		return (bool) apply_filters( 'famma_whatsapp_show_float', true );
	}

	/**
	 * Prints the floating button in the footer.
	 *
	 * @return void
	 */
	public function render_float(): void {
		if ( ! self::show_float() ) {
			return;
		}

		$href = self::link( get_bloginfo( 'name' ) . ' — ' . __( "Bonjour, j'ai une question 🙂 / سلام، عندي سؤال", 'famma-core' ) );
		if ( '' === $href ) {
			return;
		}
		printf(
			'<a class="famma-wa" href="%1$s" target="_blank" rel="noopener nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( $href ),
			esc_attr__( 'Contact us on WhatsApp', 'famma-core' ),
			'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2Zm5.9 14.1c-.2.7-1.4 1.4-2 1.4-.5.1-1.1.1-1.8-.1-.4-.1-1-.3-1.6-.6-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.4-1.1-2.7 0-1.3.7-1.9.9-2.2.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .5l-.4.6c-.1.2-.3.3-.1.6.1.3.6 1 1.3 1.6.9.8 1.6 1 1.9 1.2.2.1.4 0 .5-.1l.6-.7c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.1.1.6-.1 1.2Z"/></svg>'
		);
	}
}

/**
 * Analytics wiring: GA4, Meta Pixel, TikTok, UTM persistence (§17–§20).
 *
 * Only public identifiers ever reach the browser. The Meta access token is
 * read server-side and is never enqueued, printed or logged.
 *
 * Lot 5 :
 * - S1 : un visiteur interne (administrateur ou gérant connecté) ne reçoit
 *   ni Pixel, ni événement navigateur, ni GA4, ni TikTok. Les envois serveur
 *   (OrderPlaced, Purchase) et les données enregistrées sur la commande ne
 *   sont jamais coupés ;
 * - S3-a : l'eventID de PageView (et des événements de `Tracking_Events`) est
 *   tiré dans le navigateur, jamais figé par le cache ;
 * - D6 : chargement immédiat (code standard de Meta, dans le `<head>`) par
 *   défaut, ou différé (comportement de la 0.3.1) ;
 * - R12 : la capture UTM ne dépend plus d'aucun Pixel, et chaque commande
 *   reçoit une source de synthèse `_famma_order_source` (`Order_Source`).
 */
final class Tracking {

	/**
	 * Générateur d'eventID du navigateur (S3-a) : `crypto.randomUUID()`, sinon
	 * un UUID v4 tiré de `crypto.getRandomValues()`, sinon horloge et hasard.
	 */
	public const EVENT_ID_SCRIPT = 'window.fammaEventId=window.fammaEventId||function(){var c=window.crypto,b,h="",i;if(c&&c.randomUUID){return c.randomUUID();}if(c&&c.getRandomValues){b=new Uint8Array(16);c.getRandomValues(b);b[6]=b[6]&15|64;b[8]=b[8]&63|128;for(i=0;i<16;i++){h+=(b[i]+256).toString(16).slice(1)+(3===i||5===i||7===i||9===i?"-":"");}return h;}return Date.now().toString(36)+"-"+Math.random().toString(36).slice(2)+"-"+Math.random().toString(36).slice(2);};';

	/**
	 * Singleton instance.
	 *
	 * @var Tracking|null
	 */
	private static ?Tracking $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Tracking
	 */
	public static function instance(): Tracking {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers front-end and order hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'wp_head', array( $this, 'site_verification' ), 5 );
		add_action( 'template_redirect', array( $this, 'internal_cache_guard' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_utm_on_order' ), 30 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_capi_signals' ), 31 );
		// Le déclencheur du Purchase serveur vit dans Capi (priorité 20, après l'encaissement).
		add_filter( 'wp_inline_script_attributes', array( $this, 'immediate_attributes' ) );
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'litespeed_exclude' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'litespeed_exclude' ) );
	}

	/**
	 * Chargement différé du Pixel (D6) : réglage `famma_meta_pixel_loading`,
	 * que le filtre de code `famma_defer_pixels` peut encore forcer.
	 *
	 * @return bool
	 */
	private static function deferred(): bool {
		return (bool) apply_filters( 'famma_defer_pixels', 'deferred' === Config::instance()->meta_pixel_loading() );
	}

	/**
	 * En chargement immédiat, LiteSpeed ne retarde ni ne diffère le Pixel.
	 *
	 * Sans ces attributs, l'option « JS différé / retardé » de LiteSpeed
	 * remettrait le code de base à après une interaction : exactement la perte
	 * que le chargement immédiat corrige (T3.5). Mêmes attributs que
	 * `Capi_Signals::no_optimize_attributes()`. En différé, rien ne change.
	 *
	 * @param mixed $attributes Attributs de la balise inline.
	 * @return array<string, string|bool>
	 */
	public function immediate_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( 'famma-pixels-js-after' === ( $attributes['id'] ?? '' ) && ! self::deferred() ) {
			$attributes['data-no-optimize'] = '1';
			$attributes['data-no-defer']    = '1';
			$attributes['data-cfasync']     = 'false';
		}

		return $attributes;
	}

	/**
	 * Filet de sécurité : `famma-pixels` dans les exclusions JS de LiteSpeed, en immédiat seulement.
	 *
	 * @param mixed $excludes Liste d'exclusions de LiteSpeed Cache.
	 * @return array<int, string>
	 */
	public function litespeed_exclude( $excludes ): array {
		$excludes = is_array( $excludes ) ? $excludes : array_filter( (array) $excludes );
		if ( ! self::deferred() ) {
			$excludes[] = 'famma-pixels';
		}

		return array_values( array_unique( $excludes ) );
	}

	/**
	 * Visiteur interne (S1) : utilisateur connecté qui gère le site ou la boutique.
	 *
	 * Même règle que le mu-plugin `famma-google-tags.php` (`manage_options` ou
	 * `manage_woocommerce`) : GA4 et Meta excluent les mêmes personnes. Les
	 * clients connectés restent mesurés. Filtre `famma_tracking_exclude_visitor`.
	 *
	 * @return bool
	 */
	public static function is_internal_visitor(): bool {
		$internal = function_exists( 'is_user_logged_in' ) && is_user_logged_in()
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) );

		return (bool) apply_filters( 'famma_tracking_exclude_visitor', $internal );
	}

	/**
	 * Le navigateur de cette requête doit-il recevoir le suivi (Pixel, événements, TikTok, GA4) ?
	 *
	 * Vrai pour tout visiteur non interne, et pour un visiteur interne quand
	 * la dérogation « Suivre mes visites (mode test) » est active
	 * (`Config::track_internal_visitors()`, constante `FAMMA_TRACK_INTERNAL`).
	 * Ne concerne jamais les envois serveur.
	 *
	 * @return bool
	 */
	public static function browser_tracking_allowed(): bool {
		return ! self::is_internal_visitor() || Config::instance()->track_internal_visitors();
	}

	/**
	 * Une page vue par un visiteur interne n'est jamais mise en cache.
	 *
	 * LiteSpeed ne sert déjà jamais son cache public à un utilisateur connecté,
	 * et la variante « connecté » qu'il peut garder est privée (liée au cookie
	 * de session de cet utilisateur) : une page sans Pixel ne peut pas être
	 * servie à un visiteur. Ce garde va plus loin : il couvre une exclusion
	 * élargie par le filtre à un visiteur non connecté, et rend effet immédiat
	 * à la bascule de la dérogation (pas de page privée périmée 30 minutes).
	 *
	 * @return void
	 */
	public function internal_cache_guard(): void {
		if ( is_admin() || ! self::is_internal_visitor() ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API de LiteSpeed Cache (sans effet s'il est absent).
		do_action( 'litespeed_control_set_nocache', 'famma-core : visiteur interne, page propre à la mesure' );
		if ( function_exists( 'wc_maybe_define_constant' ) ) {
			wc_maybe_define_constant( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Prints the Google site-verification meta tag, when configured.
	 *
	 * @return void
	 */
	public function site_verification(): void {
		$token = Config::instance()->google_site_verification();
		if ( '' === $token ) {
			return;
		}
		printf( '<meta name="google-site-verification" content="%s" />%s', esc_attr( $token ), "\n" );
	}

	/**
	 * Enqueues the analytics snippets that have an identifier configured.
	 *
	 * Everything goes through wp_enqueue_script / wp_add_inline_script rather
	 * than raw <script> echoes, so third parties can dequeue them and the
	 * loading strategy stays under WordPress's control (§23).
	 *
	 * Le handle `famma-pixels` existe désormais sur toute page : il porte au
	 * moins la capture UTM (R12). En chargement immédiat (D6, défaut), il est
	 * imprimé dans le `<head>`, comme le code standard de Meta ; en différé,
	 * en pied de page comme en 0.3.1.
	 *
	 * @return void
	 */
	public function enqueue_tracking(): void {
		$cfg = Config::instance();

		/*
		 * Différé : les files fbq / ttq sont créées tout de suite, mais le
		 * téléchargement des bibliothèques tierces attend la fin du chargement
		 * et un moment d'inactivité (PERF-07). Immédiat : la bibliothèque part
		 * aussitôt, en asynchrone ; un départ rapide ne perd plus les
		 * événements (T3.5). Le filtre `famma_defer_pixels` suit le réglage.
		 */
		$deferred = self::deferred();

		wp_register_script( 'famma-pixels', false, array(), FAMMA_CORE_VERSION, array( 'in_footer' => $deferred ) );
		wp_enqueue_script( 'famma-pixels' );

		if ( ! self::browser_tracking_allowed() ) {
			wp_add_inline_script( 'famma-pixels', Order_Source::UTM_SCRIPT );
			return;
		}

		$ga = $cfg->ga4_measurement_id();
		if ( '' !== $ga ) {
			wp_enqueue_script(
				'famma-ga4',
				'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga ),
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party URL, a ver= query would defeat Google's own caching.
				array( 'strategy' => 'async' )
			);
			wp_add_inline_script(
				'famma-ga4',
				'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');'
			);
		}

		$pixel = $cfg->meta_pixel_id();
		$ttk   = $cfg->tiktok_pixel_id();

		if ( '' !== $pixel || '' !== $ttk ) {
			wp_add_inline_script(
				'famma-pixels',
				$deferred
					? 'window.fammaDefer=window.fammaDefer||function(f){var r=function(){"requestIdleCallback" in window?requestIdleCallback(f,{timeout:3000}):setTimeout(f,1);};"complete"===document.readyState?r():addEventListener("load",r,{once:true});};'
					: 'window.fammaDefer=window.fammaDefer||function(f){f();};'
			);
		}

		if ( '' !== $pixel ) {
			wp_add_inline_script( 'famma-pixels', self::EVENT_ID_SCRIPT . self::meta_base_code( $pixel, $deferred ) );
		}

		if ( '' !== $ttk ) {
			$load = 'ttq.load(' . wp_json_encode( $ttk ) . ')';
			wp_add_inline_script(
				'famma-pixels',
				'!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(e,n){e[n]=function(){e.push([n].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e){var n="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=n;ttq._t=ttq._t||{};ttq._t[e]=+new Date;var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=n+"?sdkid="+e;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};'
				. ( $deferred ? 'fammaDefer(function(){' . $load . '});' : $load . ';' )
				. 'ttq.page()}(window,document,"ttq");'
			);
		}

		// UTM persistence (§20, R12) — independent of any pixel being configured.
		wp_add_inline_script( 'famma-pixels', Order_Source::UTM_SCRIPT );
	}

	/**
	 * Code de base du Pixel Meta : un seul `fbq('init')` et le PageView.
	 *
	 * Immédiat : le code standard de Meta (bibliothèque insérée en `async`
	 * aussitôt). Différé : la même file, la bibliothèque attend `fammaDefer`.
	 * L'eventID du PageView est tiré dans le navigateur.
	 *
	 * @param string $pixel    Identifiant public du Pixel.
	 * @param bool   $deferred Chargement différé.
	 * @return string
	 */
	private static function meta_base_code( string $pixel, bool $deferred ): string {
		$insert = 't=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)';

		return "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];"
			. ( $deferred ? 'fammaDefer(function(){' . $insert . '})' : $insert )
			. "}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . wp_json_encode( $pixel ) . ");fbq('track','PageView',{},{eventID:fammaEventId()});";
	}

	/**
	 * Copies the captured UTM parameters and the synthetic source onto the order (§20, R12).
	 *
	 * Point d'accroche `woocommerce_checkout_create_order` (priorité 30, avant
	 * les signaux CAPI en 31), conservé comme API publique ; la logique vit
	 * dans `Order_Source::stamp()`.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function stamp_utm_on_order( \WC_Order $order ): void {
		Order_Source::stamp( $order );
	}

	/**
	 * Stores the signals the Conversions API needs, at order creation (§17).
	 *
	 * The Purchase event is sent days later, at delivery — long after the
	 * browser is gone. Adresse IP, agent utilisateur et cookies Meta doivent
	 * donc être capturés maintenant, sinon l'événement serveur partira sans
	 * rien pour être rapproché de la visite qui l'a produit.
	 *
	 * @param \WC_Order $order Order being created.
	 * @return void
	 */
	public function stamp_capi_signals( \WC_Order $order ): void {
		// Seules les valeurs au format Meta sont gardées : un cookie forgé ou
		// tronqué ne doit jamais atteindre la Conversions API.
		$signals = array(
			'_famma_fbp'       => Capi_Signals::cookie( '_fbp' ),
			'_famma_fbc'       => Capi_Signals::cookie( '_fbc' ),
			'_famma_client_ip' => Capi_Signals::client_ip(),
			'_famma_client_ua' => Capi_Signals::client_user_agent(),
		);
		foreach ( $signals as $meta => $value ) {
			if ( '' !== $value ) {
				$order->update_meta_data( $meta, $value );
			}
		}
	}
}

/**
 * Meta Conversions API client (§17).
 *
 * Deux événements serveur : OrderPlaced (commande acceptée sur le site, mise
 * en file Action Scheduler) et Purchase (commande livrée, ou « Terminée »
 * pour un paiement à la livraison, toujours après l'encaissement).
 *
 * Règles tenues sans exception :
 *
 *  1. Le token n'est jamais journalisé, jamais renvoyé, jamais imprimé. Les
 *     messages d'erreur ne citent que le code HTTP et le message de Meta.
 *  2. Rien n'est envoyé sans Pixel ET token, hors production (D4), ni en
 *     mode test sans code de test valide (A12).
 *  3. `_famma_purchase_sent` n'est écrit qu'après un envoi réellement
 *     accepté. Un incident réseau laisse donc la commande rejouable.
 *  4. Chaque issue d'OrderPlaced et de Purchase écrit une ligne du journal
 *     `famma-capi` (D7), et un échec transitoire est relancé par `Capi_Retry`
 *     avec le même event_id (D1).
 */
final class Capi {

	/**
	 * Singleton instance.
	 *
	 * @var Capi|null
	 */
	private static ?Capi $i = null;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Capi
	 */
	public static function instance(): Capi {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'famma_capi_purchase', array( $this, 'send_purchase' ), 10, 2 );

		// Priorité 20 : après l'encaissement COD d'Order_Statuses (priorité 10).
		add_action( 'woocommerce_order_status_delivered', array( $this, 'fire_purchase_on_status' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'fire_purchase_on_status' ), 20 );
		add_filter( 'woocommerce_payment_successful_result', array( $this, 'order_placed_after_checkout' ), 999, 2 );
		add_filter( 'woocommerce_checkout_no_payment_needed_redirect', array( $this, 'order_placed_without_payment' ), 999, 2 );
		add_action( 'famma_capi_orderplaced_send', array( $this, 'send_queued_order_placed' ) );
		add_action( Capi_Retry::HOOK, array( $this, 'send_retry' ), 10, 2 );
	}

	/**
	 * Report an accepted storefront checkout, never merely order creation.
	 *
	 * @param array $result   WooCommerce successful gateway result.
	 * @param int   $order_id Persisted order identifier.
	 * @return array Unchanged gateway result.
	 */
	public function order_placed_after_checkout( array $result, int $order_id ): array {
		if ( 'success' === ( $result['result'] ?? '' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				$this->queue_order_placed( $order );
			}
		}
		return $result;
	}

	/**
	 * Report a successful zero-total checkout without modifying its redirect.
	 *
	 * @param string    $redirect Checkout return URL.
	 * @param \WC_Order $order    Persisted order.
	 * @return string Unchanged redirect.
	 */
	public function order_placed_without_payment( string $redirect, \WC_Order $order ): string {
		$this->queue_order_placed( $order );
		return $redirect;
	}

	/**
	 * Persist checkout acceptance and enqueue once without delaying the customer.
	 *
	 * @param \WC_Order $order Accepted storefront checkout.
	 */
	private function queue_order_placed( \WC_Order $order ): void {
		$base = $this->log_base( $order, 'OrderPlaced' );
		if ( ! get_option( 'famma_meta_orderplaced_enabled', false ) ) {
			$this->outcome( $base, 'disabled', 'option' );
			return;
		}
		$reason = $this->order_placed_ineligibility( $order );
		if ( '' !== $reason ) {
			$this->outcome( $base, 'ineligible', $reason );
			return;
		}
		if ( $order->get_meta( '_famma_orderplaced_sent' ) ) {
			$this->outcome( $base, 'already_sent', 'marker' );
			return;
		}
		$order->update_meta_data( '_famma_orderplaced_checkout_accepted', 1 );
		if ( ! $order->get_meta( '_famma_orderplaced_event_time' ) ) {
			$created = $order->get_date_created();
			$order->update_meta_data( '_famma_orderplaced_event_time', $created ? $created->getTimestamp() : time() );
			$order->update_meta_data( '_famma_orderplaced_event_id', 'famma_orderplaced_' . $order->get_id() );
		}
		$order->save();
		if ( ! $this->enqueue_order_placed( $order->get_id() ) ) {
			$this->outcome( $base, 'queue_failed', 'scheduler' );
		}
	}

	/**
	 * Enqueue the unique OrderPlaced task (Action Scheduler, WP-Cron as fallback).
	 *
	 * @param int $order_id Order identifier.
	 * @return bool Whether a task is now waiting for this order.
	 */
	private function enqueue_order_placed( int $order_id ): bool {
		$args = array( 'order_id' => $order_id );
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( as_enqueue_async_action( 'famma_capi_orderplaced_send', $args, 'famma-capi', true ) > 0 ) {
				return true;
			}
			// Une tâche unique déjà en file renvoie 0 : ce n'est pas un échec.
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'famma_capi_orderplaced_send', $args, 'famma-capi' ) ) {
				return true;
			}
		}
		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			return false;
		}
		if ( wp_next_scheduled( 'famma_capi_orderplaced_send', $args ) ) {
			return true;
		}
		return false !== wp_schedule_single_event( time() + 1, 'famma_capi_orderplaced_send', $args );
	}

	/**
	 * Execute the queued event; recheck settings and current order status.
	 *
	 * @param int $order_id Persisted checkout order identifier.
	 */
	public function send_queued_order_placed( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			$this->outcome( $this->log_base_for( $order_id, 'OrderPlaced' ), 'ineligible', 'order_missing' );
			return;
		}
		$this->send_order_placed( $order );
	}

	/**
	 * Why a checkout order cannot be reported as OrderPlaced, or an empty string.
	 *
	 * Exclut les commandes de l'administration, non enregistrées, brouillons
	 * et refusées.
	 *
	 * @param \WC_Order $order Checkout order.
	 * @return string Reason token, empty when eligible.
	 */
	private function order_placed_ineligibility( \WC_Order $order ): string {
		if ( $order->get_id() <= 0 ) {
			return 'unsaved';
		}
		if ( 'checkout' !== $order->get_created_via() ) {
			return 'created_via:' . ( '' !== (string) $order->get_created_via() ? (string) $order->get_created_via() : 'unknown' );
		}
		if ( $order->has_status( array( 'checkout-draft', 'draft', 'failed', 'cancelled', 'trash', 'refused', 'returned', 'failed-delivery' ) ) ) {
			return 'status:' . $order->get_status();
		}
		return '';
	}

	/**
	 * Send a placed order separately from its later delivered Purchase.
	 *
	 * @param \WC_Order $order Accepted storefront checkout order.
	 * @param bool      $retry Whether a bounded retry may reclaim an uncertain send.
	 * @return string Outcome recorded in the famma-capi journal.
	 */
	public function send_order_placed( \WC_Order $order, bool $retry = false ): string {
		$base = $this->log_base( $order, 'OrderPlaced' );
		if ( ! get_option( 'famma_meta_orderplaced_enabled', false ) ) {
			return $this->outcome( $base, 'disabled', 'option' );
		}
		$reason = $this->order_placed_ineligibility( $order );
		if ( '' !== $reason ) {
			return $this->outcome( $base, 'ineligible', $reason );
		}
		if ( ! $order->get_meta( '_famma_orderplaced_checkout_accepted' ) ) {
			return $this->outcome( $base, 'ineligible', 'checkout_not_accepted' );
		}
		return $this->send_order_event( $order, 'OrderPlaced', 'famma_orderplaced_' . $order->get_id(), $retry );
	}

	/**
	 * Motif journalisé quand le Purchase est écarté, ou vide s'il peut partir.
	 *
	 * La règle vient du circuit de commande (`Order_Statuses::purchase_ineligibility()`),
	 * la même pour l'émetteur, le rapprochement (R8) et l'état de santé (R7).
	 *
	 * @param \WC_Order $order Order concerned.
	 * @return string `not_collected`, `status:<statut>` ou vide.
	 */
	private function purchase_block_reason( \WC_Order $order ): string {
		$why = Order_Statuses::purchase_ineligibility( $order );
		if ( '' === $why ) {
			return '';
		}
		return 'not_collected' === $why ? $why : 'status:' . $order->get_status();
	}

	/**
	 * Fires the real Purchase once, when the order is delivered or completed (§17, D5).
	 *
	 * Priority 20, after the COD collection at priority 10. The event id makes
	 * browser/server deduplication possible; the dispatcher owns its
	 * persistence under the atomic delivery claim.
	 *
	 * @param int $order_id Order identifier.
	 * @return void
	 */
	public function fire_purchase_on_status( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$base   = $this->log_base( $order, 'Purchase' );
		$reason = $this->purchase_block_reason( $order );
		if ( '' !== $reason ) {
			$this->outcome( $base, 'ineligible', $reason );
			return;
		}
		if ( $order->get_meta( '_famma_purchase_sent' ) ) {
			$this->outcome( $base, 'already_sent', 'marker' );
			return;
		}

		/**
		 * Fires when a delivered COD order should be reported as a Purchase.
		 *
		 * @param \WC_Order $order    The delivered order.
		 * @param string    $event_id Deduplication identifier.
		 */
		do_action( 'famma_capi_purchase', $order, 'famma_purchase_' . $order_id );
	}

	/**
	 * Sends the Purchase event, and records the outcome on the order.
	 *
	 * @param \WC_Order $order    Delivered order.
	 * @param string    $event_id Deduplication identifier shared with the pixel.
	 * @return void
	 */
	public function send_purchase( \WC_Order $order, string $event_id ): void {
		$reason = $this->purchase_block_reason( $order );
		if ( '' !== $reason ) {
			$this->outcome( $this->log_base( $order, 'Purchase', $event_id ), 'ineligible', $reason );
			return;
		}
		$this->send_order_event( $order, 'Purchase', $event_id );
	}

	/**
	 * Bounded retry run by Action Scheduler (D1), same event_id as the first try.
	 *
	 * @param int    $order_id   Order identifier.
	 * @param string $event_name OrderPlaced or Purchase.
	 * @return void
	 */
	public function send_retry( int $order_id, string $event_name = '' ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			$this->outcome( $this->log_base_for( $order_id, $event_name ), 'ineligible', 'order_missing' );
			return;
		}
		if ( in_array( $event_name, array( 'OrderPlaced', 'Purchase' ), true ) ) {
			$this->resend( $order, $event_name );
			return;
		}
		$this->outcome( $this->log_base_for( $order_id, $event_name ), 'ineligible', 'event_unknown' );
	}

	/**
	 * Explicit resend (bounded retry or reconciliation), same event_id.
	 *
	 * @param \WC_Order $order      Order concerned.
	 * @param string    $event_name OrderPlaced or Purchase.
	 * @return string Outcome recorded in the famma-capi journal.
	 */
	public function resend( \WC_Order $order, string $event_name ): string {
		if ( 'OrderPlaced' === $event_name ) {
			return $this->send_order_placed( $order, true );
		}
		$event_id = 'famma_purchase_' . $order->get_id();
		$reason   = $this->purchase_block_reason( $order );
		if ( '' !== $reason ) {
			return $this->outcome( $this->log_base( $order, 'Purchase', $event_id ), 'ineligible', $reason );
		}
		return $this->send_order_event( $order, 'Purchase', $event_id, true );
	}

	/**
	 * Order metadata keys of one event.
	 *
	 * @param string $event_name OrderPlaced or Purchase.
	 * @return array{sent: string, time: string, attempts: string, id: string}
	 */
	private function meta_keys( string $event_name ): array {
		$is_purchase = 'Purchase' === $event_name;
		return array(
			'sent'     => $is_purchase ? '_famma_purchase_sent' : '_famma_orderplaced_sent',
			'time'     => $is_purchase ? '_famma_purchase_event_time' : '_famma_orderplaced_event_time',
			'attempts' => $is_purchase ? '_famma_capi_attempts' : '_famma_orderplaced_attempts',
			'id'       => $is_purchase ? '_famma_purchase_event_id' : '_famma_orderplaced_event_id',
		);
	}

	/**
	 * Shared server dispatch with separate identifiers and markers per event.
	 *
	 * @param \WC_Order $order      Persisted order.
	 * @param string    $event_name OrderPlaced or Purchase.
	 * @param string    $event_id   Stable deduplication identifier.
	 * @param bool      $retry      Whether a bounded retry may reclaim an uncertain send.
	 * @return string Outcome recorded in the famma-capi journal.
	 */
	private function send_order_event( \WC_Order $order, string $event_name, string $event_id, bool $retry = false ): string {
		$cfg   = Config::instance();
		$pixel = $cfg->meta_pixel_id();
		$token = $cfg->meta_access_token();
		$base  = $this->log_base( $order, $event_name, $event_id );
		$keys  = $this->meta_keys( $event_name );

		if ( '' === $pixel || '' === $token ) {
			return $this->outcome( $base, 'not_configured', '' === $pixel ? 'pixel' : 'token' );
		}
		if ( $order->get_meta( $keys['sent'] ) ) {
			return $this->outcome( $base, 'already_sent', 'marker' );
		}
		if ( ! $cfg->meta_sending_allowed() ) {
			return $this->outcome( $base, 'blocked_environment', $cfg->environment_type() );
		}

		// Mode test : un code absent ou invalide bloque l'envoi au lieu de le faire partir en réel (A12).
		$test_code = '';
		if ( Capi_Signals::test_mode_enabled() ) {
			$test_code = $cfg->meta_test_event_code();
			if ( ! Config::valid_test_event_code( $test_code ) ) {
				return $this->outcome( $base, 'test_code_invalid', '' === $test_code ? 'missing' : 'format' );
			}
		}

		$event_time = (int) $order->get_meta( $keys['time'] );
		if ( $event_time <= 0 ) {
			$created    = $order->get_date_created();
			$event_time = 'Purchase' !== $event_name && $created ? $created->getTimestamp() : time();
		}
		if ( time() - $event_time > Config::CAPI_MAX_EVENT_AGE ) {
			return $this->outcome( $base, 'too_old', 'event_time' );
		}

		$registry = Capi_Delivery_Registry::instance();
		$claim    = $registry->acquire( $pixel, $event_name, $event_id, $order->get_id(), $event_time, Config::CAPI_MAX_ATTEMPTS, (int) $order->get_meta( $keys['attempts'] ), $retry );
		$state    = (string) ( $claim['state'] ?? '' );
		if ( 'accepted' === $state ) {
			$this->persist_event_success( $order->get_id(), $event_name, $event_id, false );
			return $this->outcome( $base, 'already_sent', 'registry' );
		}
		if ( 'unavailable' === $state ) {
			Capi_Health::signal( 'registry_unavailable' );
			return $this->outcome( $base, 'registry_unavailable', 'table' );
		}
		if ( empty( $claim['owned'] ) ) {
			return $this->not_owned( $order->get_id(), $base, $claim, $event_time );
		}

		// Refresh metadata under ownership: another execution may have saved it.
		$order = wc_get_order( $order->get_id() );
		if ( ! $order instanceof \WC_Order ) {
			$registry->finish( $claim, 'uncertain', 0, 0 );
			return $this->outcome( $base, 'ineligible', 'order_missing' );
		}
		$order->read_meta_data( true );
		if ( $order->get_meta( $keys['sent'] ) ) {
			$registry->finish( $claim, 'accepted', 0, 0 );
			return $this->outcome( $base, 'already_sent', 'marker' );
		}
		$reason = 'Purchase' === $event_name ? $this->purchase_block_reason( $order ) : '';
		if ( '' !== $reason ) {
			$registry->finish( $claim, 'rejected', 0, 0 );
			return $this->outcome( $base, 'ineligible', $reason );
		}
		$event_time = (int) $claim['event_time'];
		$attempts   = (int) $claim['attempt'];
		$order->update_meta_data( $keys['time'], $event_time );
		$order->update_meta_data( $keys['id'], $event_id );
		$order->update_meta_data( $keys['attempts'], $attempts );
		/* translators: 1: event name, 2: stable event identifier, 3: attempt number. */
		$order->add_order_note( sprintf( __( 'Meta %1$s dispatch started (%2$s, attempt %3$d).', 'famma-core' ), $event_name, $event_id, $attempts ) );
		$order->save();
		do_action( 'famma_capi_delivery_claimed', $claim, $order, $event_name );

		$event   = $this->build_event( $order, $event_name, $event_id, $event_time );
		$payload = array( 'data' => array( $event ) );
		if ( '' !== $test_code ) {
			$payload['test_event_code'] = $test_code;
		}
		$result = Capi_Transport::send_events( $pixel, $token, $payload );

		$log = array(
			'order_id'   => $order->get_id(),
			'event_name' => $event_name,
			'event_id'   => $event_id,
			'value'      => $event['custom_data']['value'] ?? 0,
			'currency'   => $event['custom_data']['currency'] ?? '',
			'user_data'  => $event['user_data'],
			'attempt'    => $attempts,
			'test'       => '' !== $test_code,
		) + $result;

		return $this->finish_dispatch( $order, $claim, $log, $token );
	}

	/**
	 * Explain, in the journal, why this execution does not own the send.
	 *
	 * Un envoi incertain d'une autre exécution reçoit sa relance bornée si
	 * elle manque (envoi interrompu repris comme incertain, D1).
	 *
	 * @param int                  $order_id   Order identifier.
	 * @param array<string, mixed> $base       Journal identity of the event.
	 * @param array<string, mixed> $claim      Registry answer.
	 * @param int                  $event_time Immutable event time.
	 * @return string Outcome recorded in the famma-capi journal.
	 */
	private function not_owned( int $order_id, array $base, array $claim, int $event_time ): string {
		$state = (string) ( $claim['state'] ?? '' );
		if ( 'uncertain' === $state ) {
			$retry = Capi_Retry::schedule( $order_id, (string) $base['event_name'], (int) ( $claim['attempt'] ?? 1 ), (int) ( $claim['event_time'] ?? $event_time ) );
			return $this->outcome( $base, 'held_uncertain', 'retry:' . $retry );
		}
		if ( 'sending' === $state ) {
			return $this->outcome( $base, 'in_progress', 'registry' );
		}
		return $this->outcome( $base, 'blocked', 'rejected' === $state ? 'attempts' : ( '' !== $state ? $state : 'registry' ) );
	}

	/**
	 * Builds the event sent to Meta.
	 *
	 * `website` seulement pour une commande passée dans un navigateur
	 * (user-agent enregistré) ; sinon la source hors site de la doc Meta, sans
	 * `event_source_url` ni `client_user_agent` (D5, A9). Le bloc
	 * `custom_data` suit le profil de données (R3).
	 *
	 * @param \WC_Order $order      Order being reported.
	 * @param string    $event_name OrderPlaced or Purchase.
	 * @param string    $event_id   Stable deduplication identifier.
	 * @param int       $event_time Immutable event time.
	 * @return array<string, mixed>
	 */
	private function build_event( \WC_Order $order, string $event_name, string $event_id, int $event_time ): array {
		$website = Capi_User_Data::is_website( $order );
		$custom  = array(
			'currency'     => $order->get_currency(),
			// Total réel de la commande (lignes, frais, livraison, remises) : jamais une valeur fixe.
			'value'        => round( (float) $order->get_total(), 3 ),
			'order_id'     => (string) $order->get_id(),
			'content_type' => 'product',
		) + Capi_Product_Data::for_order( $order );
		if ( 'OrderPlaced' === $event_name ) {
			$custom['famma_stage'] = 'order_placed';
		}
		$event = array(
			'event_name'    => $event_name,
			'event_time'    => $event_time,
			'event_id'      => $event_id,
			'action_source' => $website ? 'website' : Capi_User_Data::offsite_action_source( $order ),
			'user_data'     => Capi_User_Data::build( $order, $website ),
			'custom_data'   => Capi_Data_Profile::apply( $custom, $event_name, $order ),
		);
		if ( $website ) {
			$event['event_source_url'] = wc_get_checkout_url();
		}
		return $event;
	}

	/**
	 * Records the transport outcome: registry first, then journal, then order.
	 *
	 * Accepté → marqueur posé ; transitoire → état incertain et relance
	 * bornée ; accès ou contrat → rejet sans relance automatique, alerte.
	 *
	 * @param \WC_Order            $order Order concerned.
	 * @param array<string, mixed> $claim Owned registry claim.
	 * @param array<string, mixed> $log   Journal fields, transport result included.
	 * @param string               $token Secret never written anywhere.
	 * @return string Outcome recorded in the famma-capi journal.
	 */
	private function finish_dispatch( \WC_Order $order, array $claim, array $log, string $token ): string {
		$registry   = Capi_Delivery_Registry::instance();
		$class      = (string) $log['class'];
		$http       = (int) $log['http'];
		$event_name = (string) $log['event_name'];
		$event_id   = (string) $log['event_id'];
		$attempts   = (int) $log['attempt'];
		Capi_Health::record_outcome( $class, $log );

		if ( in_array( $class, array( 'accepted', 'accepted_warning' ), true ) ) {
			if ( ! $registry->finish( $claim, 'accepted', $http, 1, $class ) ) {
				$log['retry'] = Capi_Retry::schedule( $order->get_id(), $event_name, $attempts, (int) $claim['event_time'] );
				Capi_Log::write( $log + array( 'state' => 'ack_persistence_uncertain' ), $token );
				$this->record_failure( $order, 'ack_persistence_uncertain; ' . $event_id, $attempts, $event_name );
				return 'ack_persistence_uncertain';
			}
			Capi_Log::write( $log + array( 'state' => 'accepted' ), $token );
			do_action( 'famma_capi_delivery_acknowledged', $claim, $order, $event_name );
			$this->persist_event_success( $order->get_id(), $event_name, $event_id );
			return 'accepted';
		}

		if ( 'transient' === $class || 'blocked_environment' === $class ) {
			$registry->finish( $claim, 'uncertain', $http, 0, $class );
			$state        = $this->uncertain_state( (string) $log['reason'] );
			$log['retry'] = 'blocked_environment' === $class ? 'environment' : Capi_Retry::schedule( $order->get_id(), $event_name, $attempts, (int) $claim['event_time'] );
			Capi_Log::write( $log + array( 'state' => $state ), $token );
			$this->record_failure( $order, $state . ( $http > 0 ? '; HTTP ' . $http : '' ) . '; ' . $event_id, $attempts, $event_name );
			return $state;
		}

		$registry->finish( $claim, 'rejected', $http, 0, $class );
		Capi_Log::write( $log + array( 'state' => 'rejected' ), $token );
		$this->record_failure( $order, 'rejected; ' . $class . '; HTTP ' . $http . '; ' . $event_id, $attempts, $event_name );
		return 'rejected';
	}

	/**
	 * Journal state of an uncertain outcome, by cause (names kept since 0.2.4).
	 *
	 * @param string $reason Transport reason.
	 * @return string
	 */
	private function uncertain_state( string $reason ): string {
		if ( in_array( $reason, array( 'network', 'timeout' ), true ) ) {
			return 'transport_uncertain';
		}
		return 'ack_missing' === $reason ? 'ack_uncertain' : 'uncertain';
	}

	/** Restore an acknowledged delivery without issuing another transport.
	 *
	 * @param int    $order_id Order identifier.
	 * @param string $event_name Event name.
	 * @param string $event_id Stable deduplication identifier.
	 * @param bool   $record_note Whether this execution owns the transport.
	 */
	private function persist_event_success( int $order_id, string $event_name, string $event_id, bool $record_note = true ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$order->read_meta_data( true );
		$sent_key = $this->meta_keys( $event_name )['sent'];
		if ( $order->get_meta( $sent_key ) ) {
			return;
		}
		$order->update_meta_data( $sent_key, current_time( 'mysql' ) );
		if ( $record_note ) {
			$order->add_order_note(
				sprintf(
				/* translators: 1: event name, 2: deduplication event identifier. */
					__( '%1$s event sent to Meta (event id %2$s).', 'famma-core' ),
					$event_name,
					$event_id
				)
			);
		}
		$order->save();
	}

	/**
	 * Records a failed attempt on the order, without ever exposing the token.
	 *
	 * @param \WC_Order $order      Order concerned.
	 * @param string    $message    Fixed outcome summary (never a response body).
	 * @param int       $attempts   Attempts made so far.
	 * @param string    $event_name Event being sent.
	 * @return void
	 */
	private function record_failure( \WC_Order $order, string $message, int $attempts, string $event_name ): void {
		$order->add_order_note(
			sprintf(
				/* translators: 1: event name, 2: error message, 3: attempts made, 4: maximum attempts. */
				__( 'Meta %1$s event failed: %2$s (attempt %3$d of %4$d).', 'famma-core' ),
				$event_name,
				$message,
				$attempts,
				Config::CAPI_MAX_ATTEMPTS
			)
		);
		$order->save();
	}

	/**
	 * Journal identity of an event of an order.
	 *
	 * @param \WC_Order $order      Order concerned.
	 * @param string    $event_name OrderPlaced or Purchase.
	 * @param string    $event_id   Event identifier, derived when empty.
	 * @return array{order_id: int, event_name: string, event_id: string}
	 */
	private function log_base( \WC_Order $order, string $event_name, string $event_id = '' ): array {
		$base = $this->log_base_for( $order->get_id(), $event_name );
		if ( '' !== $event_id ) {
			$base['event_id'] = $event_id;
		}
		return $base;
	}

	/**
	 * Journal identity of an event, from the order number alone.
	 *
	 * @param int    $order_id   Order identifier.
	 * @param string $event_name OrderPlaced or Purchase.
	 * @return array{order_id: int, event_name: string, event_id: string}
	 */
	private function log_base_for( int $order_id, string $event_name ): array {
		return array(
			'order_id'   => $order_id,
			'event_name' => $event_name,
			'event_id'   => 'famma_' . ( 'Purchase' === $event_name ? 'purchase' : 'orderplaced' ) . '_' . $order_id,
		);
	}

	/**
	 * Writes one journal line for a non-dispatch outcome and returns its state.
	 *
	 * @param array<string, mixed> $base   Journal identity of the event.
	 * @param string               $state  Outcome.
	 * @param string               $reason Short reason token.
	 * @return string
	 */
	private function outcome( array $base, string $state, string $reason ): string {
		Capi_Log::outcome( $base, $state, $reason );
		return $state;
	}
}

/**
 * Simplified merchant dashboard (§28).
 *
 * Revenue counts delivered orders only — a COD order is not money until the
 * cash is collected (§12). Every figure here therefore differs on purpose
 * from WooCommerce's own reports, which treat a placed order as revenue.
 *
 * Admin only, and never loaded on the front end (rule 10).
 */
final class Dashboard {

	/**
	 * Singleton instance.
	 *
	 * @var Dashboard|null
	 */
	private static ?Dashboard $i = null;

	/**
	 * Transient holding the computed figures.
	 */
	private const CACHE_KEY = 'famma_dashboard_stats';

	/**
	 * How long the figures stay cached, in seconds.
	 */
	private const CACHE_TTL = 300;

	/**
	 * Hard cap on orders read per window.
	 *
	 * Rule 10 forbids unbounded queries. When the cap is reached the page
	 * says so rather than silently reporting a partial total.
	 */
	private const MAX_ORDERS = 1000;

	/**
	 * Returns the shared instance, registering hooks on first call.
	 *
	 * @return Dashboard
	 */
	public static function instance(): Dashboard {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function hooks(): void {
		// Priority 9: the parent must exist before the newsletter post type
		// (priority 10) attaches its submenu, or WordPress leaves the
		// dashboard out of the menu and the FAMMA link opens the newsletter.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
		add_action( 'admin_post_famma_refresh_stats', array( $this, 'refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	/**
	 * Adds the FAMMA top-level admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'FAMMA', 'famma-core' ),
			__( 'FAMMA', 'famma-core' ),
			'manage_woocommerce',
			'famma-dashboard',
			array( $this, 'render' ),
			'dashicons-chart-area',
			56
		);

		// Same slug as the parent: the first submenu entry, labelled for the page.
		add_submenu_page(
			'famma-dashboard',
			__( 'FAMMA — tableau de bord', 'famma-core' ),
			__( 'Tableau de bord', 'famma-core' ),
			'manage_woocommerce',
			'famma-dashboard',
			array( $this, 'render' )
		);
	}

	/**
	 * Loads the dashboard styles, on the dashboard screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function styles( string $hook ): void {
		if ( 'toplevel_page_famma-dashboard' !== $hook ) {
			return;
		}

		$css = '.famma-grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));margin:16px 0 28px}'
			. '.famma-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}'
			. '.famma-card__label{color:#50575e;font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px}'
			. '.famma-card__value{font-size:26px;font-weight:700;color:#0B1F3A;line-height:1.2;margin:0}'
			. '.famma-card__note{color:#787c82;font-size:12px;margin:6px 0 0}'
			. '.famma-card--profit .famma-card__value{color:#1FA65A}'
			. '.famma-card--loss .famma-card__value{color:#D64545}'
			. '.famma-warn{border-inline-start:4px solid #E6A700;background:#fff8e6;padding:12px 16px;margin:16px 0}';

		wp_register_style( 'famma-dashboard', false, array(), FAMMA_CORE_VERSION );
		wp_enqueue_style( 'famma-dashboard' );
		wp_add_inline_style( 'famma-dashboard', $css );
	}

	/**
	 * Clears the cache and returns to the dashboard.
	 *
	 * @return void
	 */
	public function refresh(): void {
		check_admin_referer( 'famma_refresh_stats' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'famma-core' ) );
		}

		delete_transient( self::CACHE_KEY );
		wp_safe_redirect( admin_url( 'admin.php?page=famma-dashboard' ) );
		exit;
	}

	/**
	 * Collects the figures for one time window.
	 *
	 * @param string $after ISO date the window starts at.
	 * @return array<string,mixed>
	 */
	private function collect( string $after ): array {
		$orders = wc_get_orders(
			array(
				'limit'        => self::MAX_ORDERS,
				'date_created' => '>=' . $after,
				'status'       => array_keys( wc_get_order_statuses() ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		$out = array(
			'placed'    => 0,
			'delivered' => 0,
			'revenue'   => 0.0,
			'cost'      => 0.0,
			'cancelled' => 0,
			'returned'  => 0,
			'refused'   => 0,
			'failed'    => 0,
			'products'  => array(),
			'sources'   => array(),
			'capped'    => count( $orders ) >= self::MAX_ORDERS,
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			++$out['placed'];

			switch ( $order->get_status() ) {
				case 'cancelled':
					++$out['cancelled'];
					break;
				case 'returned':
					++$out['returned'];
					break;
				case 'refused':
					++$out['refused'];
					break;
				case 'failed-delivery':
					++$out['failed'];
					break;
			}

			$source                    = $order->get_meta( '_famma_utm_source' );
			$source                    = '' === (string) $source ? __( 'Direct', 'famma-core' ) : (string) $source;
			$out['sources'][ $source ] = ( $out['sources'][ $source ] ?? 0 ) + 1;

			// Seules les commandes livrées comptent comme du chiffre d'affaires (§12).
			if ( ! $order->has_status( 'delivered' ) ) {
				continue;
			}

			++$out['delivered'];
			$out['revenue'] += (float) $order->get_total();
			$out['cost']    += (float) $order->get_meta( '_famma_internal_shipping_cost' );

			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$qty  = (int) $item->get_quantity();
				$name = $product->get_name();

				$out['products'][ $name ] = ( $out['products'][ $name ] ?? 0 ) + $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_cost_price' ) * $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_handling_cost' ) * $qty;
				$out['cost']             += (float) $product->get_meta( '_famma_acquisition_cost' );
			}
		}

		$out['profit'] = $out['revenue'] - $out['cost'];
		$out['aov']    = $out['delivered'] > 0 ? $out['revenue'] / $out['delivered'] : 0.0;

		arsort( $out['products'] );
		arsort( $out['sources'] );

		return $out;
	}

	/**
	 * Returns every window's figures, cached.
	 *
	 * @return array<string,mixed>
	 */
	private function stats(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$today = wp_date( 'Y-m-d' );
		$stats = array(
			'today'       => $this->collect( $today . ' 00:00:00' ),
			'week'        => $this->collect( wp_date( 'Y-m-d', strtotime( '-6 days' ) ) . ' 00:00:00' ),
			'month'       => $this->collect( wp_date( 'Y-m-d', strtotime( '-29 days' ) ) . ' 00:00:00' ),
			'computed_at' => time(),
		);

		set_transient( self::CACHE_KEY, $stats, self::CACHE_TTL );

		return $stats;
	}

	/**
	 * Prints one figure card.
	 *
	 * @param string $label    Card label.
	 * @param string $value    Already-escaped value markup.
	 * @param string $note     Optional note under the value.
	 * @param string $modifier Optional CSS modifier suffix.
	 * @return void
	 */
	private function card( string $label, string $value, string $note = '', string $modifier = '' ): void {
		printf(
			'<div class="famma-card%1$s"><p class="famma-card__label">%2$s</p><p class="famma-card__value">%3$s</p>%4$s</div>',
			$modifier ? ' famma-card--' . esc_attr( $modifier ) : '',
			esc_html( $label ),
			wp_kses_post( $value ),
			'' !== $note ? '<p class="famma-card__note">' . esc_html( $note ) . '</p>' : ''
		);
	}

	/**
	 * Renders the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$stats = $this->stats();
		$month = $stats['month'];

		echo '<div class="wrap"><h1>' . esc_html__( 'FAMMA — dashboard', 'famma-core' ) . '</h1>';

		echo '<p>' . esc_html__( 'Revenue counts delivered orders only: a cash-on-delivery order is not money until the cash is collected. These figures therefore differ from WooCommerce reports on purpose.', 'famma-core' ) . '</p>';

		if ( $month['capped'] ) {
			echo '<div class="famma-warn"><strong>' . esc_html__( 'Partial figures.', 'famma-core' ) . '</strong> '
				. esc_html(
					sprintf(
						/* translators: %d: maximum number of orders read. */
						__( 'The last %d orders were read. Older orders in the window are not counted.', 'famma-core' ),
						self::MAX_ORDERS
					)
				) . '</div>';
		}

		echo '<h2>' . esc_html__( 'Orders', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Today', 'famma-core' ), (string) $stats['today']['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( '7 days', 'famma-core' ), (string) $stats['week']['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( '30 days', 'famma-core' ), (string) $month['placed'], __( 'orders placed', 'famma-core' ) );
		$this->card( __( 'Delivered — 30 days', 'famma-core' ), (string) $month['delivered'], __( 'cash collected', 'famma-core' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Money — last 30 days', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Revenue', 'famma-core' ), wc_price( $month['revenue'] ), __( 'delivered orders only', 'famma-core' ) );
		$this->card( __( 'Estimated cost', 'famma-core' ), wc_price( $month['cost'] ), __( 'goods, shipping, handling', 'famma-core' ) );
		$this->card(
			__( 'Estimated margin', 'famma-core' ),
			wc_price( $month['profit'] ),
			__( 'revenue minus cost', 'famma-core' ),
			$month['profit'] >= 0 ? 'profit' : 'loss'
		);
		$this->card( __( 'Average order', 'famma-core' ), wc_price( $month['aov'] ), __( 'per delivered order', 'famma-core' ) );
		echo '</div>';

		echo '<h2>' . esc_html__( 'Losses — last 30 days', 'famma-core' ) . '</h2><div class="famma-grid">';
		$this->card( __( 'Failed delivery', 'famma-core' ), (string) $month['failed'] );
		$this->card( __( 'Refused', 'famma-core' ), (string) $month['refused'] );
		$this->card( __( 'Returned', 'famma-core' ), (string) $month['returned'] );
		$this->card( __( 'Cancelled', 'famma-core' ), (string) $month['cancelled'] );
		echo '</div>';

		$this->table( __( 'Top products — 30 days', 'famma-core' ), __( 'Product', 'famma-core' ), __( 'Units delivered', 'famma-core' ), $month['products'] );
		$this->table( __( 'Acquisition — 30 days', 'famma-core' ), __( 'Source', 'famma-core' ), __( 'Orders', 'famma-core' ), $month['sources'] );

		printf(
			'<p><a class="button" href="%1$s">%2$s</a> <span class="famma-card__note">%3$s</span></p>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=famma_refresh_stats' ), 'famma_refresh_stats' ) ),
			esc_html__( 'Recalculate now', 'famma-core' ),
			esc_html(
				sprintf(
					/* translators: %s: human-readable time difference. */
					__( 'Figures computed %s ago, cached to keep the page fast.', 'famma-core' ),
					human_time_diff( (int) $stats['computed_at'] )
				)
			)
		);

		echo '</div>';
	}

	/**
	 * Prints a two-column table, or a placeholder when there is nothing to show.
	 *
	 * @param string            $title  Section title.
	 * @param string            $key    Left column header.
	 * @param string            $value  Right column header.
	 * @param array<string,int> $rows   Rows, already sorted.
	 * @return void
	 */
	private function table( string $title, string $key, string $value, array $rows ): void {
		echo '<h2>' . esc_html( $title ) . '</h2>';

		if ( empty( $rows ) ) {
			echo '<p class="famma-card__note">' . esc_html__( 'Nothing yet.', 'famma-core' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th>'
			. esc_html( $key ) . '</th><th>' . esc_html( $value ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10, true ) as $label => $count ) {
			printf( '<tr><td>%1$s</td><td>%2$s</td></tr>', esc_html( $label ), esc_html( (string) $count ) );
		}

		echo '</tbody></table>';
	}
}

/**
 * Loads the plugin translations.
 *
 * Hooked on `init` rather than `plugins_loaded`: since WordPress 6.7,
 * triggering translation loading before `init` raises a _doing_it_wrong
 * notice as soon as a .mo file exists.
 *
 * @return void
 */
function load_textdomain(): void {
	static $loaded = '';

	$locale = determine_locale();

	if ( $locale === $loaded ) {
		return;
	}

	/*
	 * Chargement explicite du fichier, et non `load_plugin_textdomain()`.
	 *
	 * Sous TranslatePress, la langue de la page n'est arrêtée qu'après `init`,
	 * et `load_plugin_textdomain()` — qui redéduit la locale lui-même — ne
	 * retrouvait pas le fichier arabe. Le second argument de
	 * `unload_textdomain()` compte autant : par défaut il retire le domaine du
	 * registre de chargement différé, ce qui fait échouer en silence le
	 * chargement qui suit.
	 *
	 * Mesuré sur le site, pas déduit : sans cela, `/ar/` servait les statuts de
	 * commande en anglais alors que la traduction existait.
	 */
	$mofile = plugin_dir_path( FAMMA_CORE_FILE ) . 'languages/famma-core-' . $locale . '.mo';

	if ( is_readable( $mofile ) ) {
		unload_textdomain( 'famma-core', true );
		\load_textdomain( 'famma-core', $mofile, $locale );
		$loaded = $locale;
		return;
	}

	load_plugin_textdomain( 'famma-core', false, dirname( plugin_basename( FAMMA_CORE_FILE ) ) . '/languages' );

	$loaded = $locale;
}

/**
 * Boots every component, once WooCommerce is known to be present.
 *
 * @return void
 */
function boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'FAMMA Core requires WooCommerce to be installed and active.', 'famma-core' ) . '</p></div>';
			}
		);
		return;
	}

	Config::instance();
	Order_Statuses::instance();
	Stock::instance();
	Product_Financials::instance();
	Shipping::instance();
	Tunisia::instance();
	Tracking::instance();
	Tracking_Events::instance();
	Browser_Order_Placed::instance();
	Product_Sizes::instance();
	Express_Order::instance();
	Express_Idempotency::instance();
	Checkout_Idempotency::instance();
	Express_Preparation::instance();
	Express_Checkout::instance();
	Express_Cart_Isolation::instance();
	Order_Emails::instance();
	Anti_Abuse::instance();
	Checkout_Lite::instance();
	Capi::instance();
	Capi_Delivery_Registry::instance();
	Capi_Signals::instance();
	// Sonde et battement de cœur : leurs tâches Action Scheduler tournent aussi hors de l'administration.
	Capi_Health::instance();
	Capi_Probe::instance();
	WhatsApp::instance();
	Product_Video::instance();
	Seo::instance();
	Seo_Listing::instance();
	add_filter( 'famma_seo_composed_description', array( Delivery_Content::class, 'seo_description' ), 10, 2 );
	add_filter( 'famma_seo_composed_description', array( Returns_Content::class, 'seo_description' ), 10, 2 );
	Returns_Content::hooks();
	Hardening::instance();
	Performance::instance();
	Cache_Warmup::instance();
	Redirect_Cache::instance();
	Visitor_Store::instance();
	Visitor_Collector::instance();
	Visitor_Commerce::instance();
	// Le formulaire public passe par admin-post.php : chargé partout.
	Newsletter::instance();

	// Le tableau de bord ne sert qu'à l'administration : rien de tout cela
	// n'est chargé sur le front (règle 10).
	if ( is_admin() ) {
		Product_Faq::instance();
		Dashboard::instance();
		Pages_Content::instance();
		Topbar_Content::instance();
		Tracking_Settings::instance();
		Capi_Token_Rollback::instance();
		Measurement_Settings::instance();
		Capi_Health_View::instance();
		Capi_Graph_Version::instance();
		Capi_Reconcile::instance();
		Capi_Purchase_Gaps::instance();
		Seo_Admin::instance();
		Visitor_Admin::instance();
		Visitor_Order_Box::instance();
		Visitor_Privacy::instance();
	}
}

/**
 * Declares compatibility with WooCommerce High-Performance Order Storage.
 *
 * @return void
 */
function declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FAMMA_CORE_FILE, true );
	}
}

/**
 * Registers the custom statuses on activation and refreshes rewrite rules.
 *
 * @return void
 */
function on_activation(): void {
	if ( class_exists( 'WooCommerce' ) ) {
		Order_Statuses::instance()->register_post_statuses();
	}
	flush_rewrite_rules();
}

add_action( 'init', __NAMESPACE__ . '\\load_textdomain' );
// Rejoué une fois la requête résolue : c'est seulement là que TranslatePress a
// arrêté la langue de la page.
add_action( 'wp', __NAMESPACE__ . '\\load_textdomain', 1 );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\declare_hpos_compatibility' );
register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activation' );
