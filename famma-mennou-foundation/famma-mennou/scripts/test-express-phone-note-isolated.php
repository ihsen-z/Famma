<?php
/**
 * Phrase sous le téléphone (lot 23, tâche 6) : tests isolés, sans WordPress.
 * Lancer : php scripts/test-express-phone-note-isolated.php
 *
 * Le vrai `Express_Order::phone_field()` est rendu avec un double de
 * `Product_Content` : texte vide → rien sous le champ ; texte saisi → affiché
 * échappé et relié au champ par `aria-describedby`, l'erreur gardant la
 * première place ; derja en seconde ligne sur le site français seulement.
 * Le schéma réel de `Product_Content` est lu pour la proposition par défaut.
 *
 * @package Famma_Test_Scenarios
 */

// Standalone runner: WordPress is never loaded. The doubles below keep the names,
// namespaces and signatures of the FAMMA and WordPress code they replace.
// phpcs:disable Universal.Namespaces -- The FAMMA doubles and the global WordPress doubles must share this single runner.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Generic.Classes.DuplicateClassName -- Doubles keep the names of the API they replace.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Doubles keep the signatures of the API they replace.
// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Doubles are documented by the API they replace.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI runner output, never served.

namespace Famma\Core {
	final class Product_Content {
		public static array $texts = array();
		public static function text( string $key ): string {
			return self::$texts[ $key ][ is_rtl() ? 'ar' : 'fr' ] ?? '';
		}
		public static function derja( string $key ): string {
			return self::$texts[ $key ]['ar'] ?? '';
		}
	}
}

namespace {
	use Famma\Core\Express_Order;
	use Famma\Core\Product_Content;

	define( 'ABSPATH', __DIR__ . '/' );

	$famma_rtl = false;

	function is_rtl(): bool {
		global $famma_rtl;
		return $famma_rtl;
	}
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_html_e( string $text, string $domain = '' ): void {
		echo esc_html( $text );
	}
	function esc_attr_e( string $text, string $domain = '' ): void {
		echo esc_attr( $text );
	}

	$famma_plugin = dirname( __DIR__ ) . '/wp-content/plugins/famma-core';
	require $famma_plugin . '/includes/express-order.php';

	$passed = 0;
	$failed = 0;

	function check( bool $condition, string $message ): void {
		global $passed, $failed;
		if ( $condition ) {
			++$passed;
			echo 'PASS: ' . $message . PHP_EOL;
			return;
		}
		++$failed;
		echo 'FAIL: ' . $message . PHP_EOL;
	}

	/**
	 * Rend le vrai champ téléphone avec les textes donnés.
	 *
	 * @param array<string, string> $texts Textes `fr` / `ar` de `reassure_phone`.
	 * @param bool                  $rtl   Site arabe.
	 * @return string HTML.
	 */
	function phone_html( array $texts, bool $rtl = false ): string {
		global $famma_rtl;
		$famma_rtl              = $rtl;
		Product_Content::$texts = array( 'reassure_phone' => $texts );
		$form                   = ( new ReflectionClass( Express_Order::class ) )->newInstanceWithoutConstructor();
		$field                  = new ReflectionMethod( Express_Order::class, 'phone_field' );
		ob_start();
		$field->invoke( $form );
		return (string) ob_get_clean();
	}

	function described_by( string $html ): string {
		return preg_match( '/<input[^>]*aria-describedby="([^"]*)"/s', $html, $m ) ? $m[1] : '';
	}

	$html = phone_html(
		array(
			'fr' => '',
			'ar' => '',
		)
	);
	check( ! str_contains( $html, 'famma-express__note' ), 'texte vide : rien sous le téléphone' );
	check( 'famma-express-billing-phone-error' === described_by( $html ), 'texte vide : aria-describedby ne vise que l’erreur' );

	$html = phone_html(
		array(
			'fr' => 'Nous vous appelons <b>avant</b> l’envoi.',
			'ar' => 'نكلموك قبل ما نبعثوها.',
		)
	);
	check( 'famma-express-billing-phone-error famma-express-phone-note' === described_by( $html ), 'texte saisi : relié au champ, l’erreur en premier (le script la retrouve par lui)' );
	check( str_contains( $html, 'id="famma-express-phone-note"' ), 'texte saisi : la phrase porte l’identifiant cité' );
	check( str_contains( $html, 'Nous vous appelons &lt;b&gt;avant&lt;/b&gt; l’envoi.' ) && ! str_contains( $html, '<b>' ), 'texte saisi : échappé, aucune balise ne passe' );
	check( 1 === preg_match( '/<span class="famma-express__note-line" lang="ar" dir="rtl">نكلموك قبل ما نبعثوها\.<\/span>/u', $html ), 'site français : la derja suit sur sa propre ligne, en sens arabe' );
	check( strpos( $html, 'famma-express-billing-phone-error"' ) < strpos( $html, 'famma-express__note"' ), 'l’erreur reste juste sous le champ, la phrase après' );

	$html = phone_html(
		array(
			'fr' => 'Nous vous appelons avant l’envoi.',
			'ar' => '',
		)
	);
	check( str_contains( $html, 'famma-express__note' ) && ! str_contains( $html, 'lang="ar"' ), 'derja vide : le français seul' );

	$html = phone_html(
		array(
			'fr' => 'Nous vous appelons avant l’envoi.',
			'ar' => 'نكلموك قبل ما نبعثوها.',
		),
		true
	);
	check( str_contains( $html, '>نكلموك قبل ما نبعثوها.<' ) && ! str_contains( $html, 'Nous vous appelons' ) && ! str_contains( $html, 'lang="ar"' ), 'site arabe : la derja seule, sans seconde ligne' );

	// Proposition par défaut, lue dans le vrai schéma (le double ci-dessus ne remplace que la lecture).
	$source = (string) file_get_contents( $famma_plugin . '/includes/product-content.php' );
	check( 1 === preg_match( "/'key'\s*=> 'reassure_phone',\s*'type'\s*=> 'text',/", $source ), 'admin : champ reassure_phone dans FAMMA → Pages → Fiche produit' );
	check( str_contains( $source, "'default_fr' => 'Nous vous appelons sur ce numéro pour confirmer la commande avant l’envoi.'" ), 'admin : proposition française par défaut' );
	check( str_contains( $source, "'default_ar' => 'نكلموك على النمرة هذي باش نأكدو الطلبية قبل ما نبعثوها.'" ), 'admin : proposition derja par défaut' );

	echo 'RESULT ' . $passed . '/' . ( $passed + $failed ) . ' contrôles de la phrase sous le téléphone réussis' . ( $failed ? ', ' . $failed . ' ÉCHEC(S)' : '' ) . '.' . PHP_EOL;
	exit( $failed ? 1 : 0 );
}
