<?php
/**
 * Doublon serveur (lot 59) : relais `fammaRelay` du navigateur.
 *
 * Les pages sont servies par le cache LiteSpeed : PHP ne s'exécute pas, et un
 * nonce imprimé serait le même pour tous. Le navigateur garde donc chaque
 * événement doublé (nom, eventID du Pixel, articles, valeur du Pixel) et :
 * - le confie au journal des visiteurs (`visitor-journal.js`, `attach()` /
 *   `take()`) : les événements voyagent dans l'envoi que le journal fait déjà,
 *   sous `m`, donc AUCUNE requête de plus par page (I4) ;
 * - sans journal sur la page (réglage, script retardé), les envoie lui-même à
 *   `famma/v1/meta-relay`, une fois la page chargée et à la sortie de page.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Script du relais et appels imprimés après `fbq('track')`.
 */
final class Capi_Mirror_Script {

	/**
	 * Délai de regroupement côté navigateur, en millisecondes (pages sans journal).
	 */
	public const RELAY_DELAY_MS = 800;

	/**
	 * Appel du relais qui suit un `fbq('track')` imprimé par PHP.
	 *
	 * L'eventID est celui que `fammaEventId()` vient de tirer
	 * (`window.fammaLastEventId`) : jamais un identifiant fabriqué en PHP.
	 * La valeur est celle que reçoit le Pixel (M3) ; le serveur la borne.
	 * Sans relais défini (doublon coupé), l'appel ne fait rien.
	 *
	 * @param string            $event Événement de la liste blanche.
	 * @param array<int, int[]> $items Articles `[ID WooCommerce, quantité]`.
	 * @param float|null        $value Valeur envoyée au Pixel, ou null.
	 * @return string JavaScript.
	 */
	public static function relay_call( string $event, array $items = array(), ?float $value = null ): string {
		$args = 'window.fammaLastEventId';
		if ( array() !== $items || null !== $value ) {
			$args .= ',' . wp_json_encode( array_values( $items ) );
		}
		if ( null !== $value ) {
			$args .= ',' . wp_json_encode( $value );
		}
		return 'window.fammaRelay&&fammaRelay(' . wp_json_encode( $event ) . ',' . $args . ');';
	}

	/**
	 * Pose le relais avant le code de base du Pixel, pour un visiteur suivi.
	 *
	 * @return void
	 */
	public static function enqueue_relay(): void {
		if ( ! Tracking_Events::pixel_active() || ! Capi_Mirror::enabled() ) {
			return;
		}
		wp_add_inline_script( 'famma-pixels', self::browser_script(), 'before' );
	}

	/**
	 * Script `fammaRelay(nom, eventID, articles, valeur)`, avec `take()` et `attach()`.
	 *
	 * Une paire nom + eventID n'est gardée qu'une fois par affichage. Chaque
	 * entrée porte son âge (pas l'heure du navigateur) ; le lot porte `ph`,
	 * l'empreinte du téléphone posée par `express-order.js`, que le serveur ne
	 * garde que si elle correspond à une saisie connue (I1). Sans journal
	 * attaché, envoi `sendBeacon` (sinon `fetch` keepalive) une fois le DOM
	 * prêt, au plus tôt {@see RELAY_DELAY_MS} après le premier événement, et à
	 * la sortie de page. Le texte ne dépend que des réglages (page en cache).
	 *
	 * @return string JavaScript.
	 */
	public static function browser_script(): string {
		return 'window.fammaRelay=window.fammaRelay||(function(u,w,d){var q=[],k={},t=0,j=0;'
			. 'function ph(){var h=w.fammaPh||"";if(!h){try{h=w.sessionStorage.getItem("' . Capi_External_Id::PHONE_KEY . '")||"";}catch(e){}}return /^[0-9a-f]{64}$/.test(h)?h:"";}'
			. 'function take(){if(!q.length){return null;}var n=Date.now(),b={e:q.splice(0,' . Capi_Mirror_Event::MAX_EVENTS . ').map(function(x){var o={n:x.n,id:x.id,u:x.u,a:n-x.s,i:x.i};if(null!=x.v){o.v=x.v;}return o;})},p=ph();if(p){b.ph=p;}return b;}'
			. 'function own(){if(t){clearTimeout(t);t=0;}var b,s;while((b=take())){s=JSON.stringify(b);'
			. 'try{if(navigator.sendBeacon&&navigator.sendBeacon(u,new Blob([s],{type:"text/plain"}))){continue;}}catch(e){}'
			. 'try{if(w.fetch){w.fetch(u,{method:"POST",body:s,keepalive:true,credentials:"same-origin",headers:{"Content-Type":"text/plain"}}).catch(function(){});}}catch(e){}}}'
			. 'function due(){t=0;if(j){return;}if("loading"===d.readyState){d.addEventListener("DOMContentLoaded",due);return;}own();}'
			. 'function leave(){if(!j){own();}}'
			. 'w.addEventListener("pagehide",leave);d.addEventListener("visibilitychange",function(){if("hidden"===d.visibilityState){leave();}});'
			. 'var f=function(n,id,i,v){w.fammaLastEventId=void 0;if(!n||!id||k[n+"|"+id]){return;}k[n+"|"+id]=1;'
			. 'var x={n:String(n),id:String(id),u:String(location.href).slice(0,2000),s:Date.now(),i:i||[]};if("number"===typeof v&&isFinite(v)){x.v=v;}'
			. 'q.push(x);if(!j&&!t){t=setTimeout(due,' . self::RELAY_DELAY_MS . ');}};'
			. 'f.take=take;f.attach=function(){j=1;if(t){clearTimeout(t);t=0;}};return f;'
			. '})(' . wp_json_encode( rest_url( Capi_Mirror::ROUTE_NAMESPACE . Capi_Mirror::ROUTE ) ) . ',window,document);';
	}

	/**
	 * Le relais n'est jamais différé ni regroupé par LiteSpeed : sans lui, le
	 * PageView du code de base ne serait pas relayé.
	 *
	 * @param mixed $attributes Attributs de la balise inline.
	 * @return array<string, string|bool>
	 */
	public static function no_optimize_attributes( $attributes ): array {
		$attributes = (array) $attributes;
		if ( 'famma-pixels-js-before' === ( $attributes['id'] ?? '' ) ) {
			$attributes['data-no-optimize'] = '1';
			$attributes['data-no-defer']    = '1';
			$attributes['data-cfasync']     = 'false';
		}
		return $attributes;
	}
}
