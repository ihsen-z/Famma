<?php
/**
 * `external_id` Meta : empreinte SHA-256 du cookie visiteur `famma_vid` (phase B du pixel).
 *
 * Décision du propriétaire (Q5, 25/11) : même valeur côté navigateur
 * (`fbq('init')`, correspondance avancée manuelle) et côté serveur
 * (`user_data.external_id` de tous les événements CAPI). Base : le cookie
 * `famma_vid` du journal des visiteurs, recopié sur la commande en
 * `_famma_visitor_id` (`Visitor_Commerce`, `Visitor_Inputs_Order`). Cookie
 * absent ou invalide : aucun external_id, jamais de valeur inventée.
 *
 * Valeur : SHA-256 en hexadécimal minuscule de l'UUID en minuscules. Le
 * navigateur la calcule lui-même (le HTML est en cache LiteSpeed, PHP ne peut
 * pas l'imprimer) et la passe déjà hachée : ainsi Meta reçoit la même
 * empreinte des deux côtés, que le Pixel hache ou non `external_id`.
 *
 * Références Meta (relues le 25/11/2026) :
 * - Conversions API, « Customer Information Parameters » : external_id
 *   « Hashing recommended » ; envoyé par un autre canal, il doit être
 *   « in the same format as when sent via the Conversions API ».
 * - Meta Pixel, « Advanced Matching » : `fbq('init', id, {external_id: …})` ;
 *   seul le hachage automatique de `em` est documenté.
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Empreinte du visiteur, côté serveur et navigateur.
 */
final class Capi_External_Id {

	/**
	 * Empreinte d'un identifiant visiteur, ou chaîne vide s'il n'est pas un UUID du journal.
	 *
	 * @param string $visitor_id Valeur du cookie `famma_vid` (ou de `_famma_visitor_id`).
	 * @return string SHA-256 hexadécimal minuscule, ou chaîne vide.
	 */
	public static function from_visitor( string $visitor_id ): string {
		$value = strtolower( trim( $visitor_id ) );

		return Visitor_Context::is_uuid( $value ) ? hash( 'sha256', $value ) : '';
	}

	/**
	 * Empreinte du visiteur d'une commande (méta figée à la commande, jamais la requête courante).
	 *
	 * @param \WC_Order $order Commande.
	 * @return string
	 */
	public static function for_order( \WC_Order $order ): string {
		return self::from_visitor( (string) $order->get_meta( Visitor_Commerce::META_VISITOR ) );
	}

	/**
	 * Script imprimé avant `fbq('init')` : définit `fammaXid()`.
	 *
	 * `fammaXid()` renvoie `{external_id: <empreinte>}`, ou `undefined` sans
	 * cookie valide. Au tout premier affichage, le pixel passe avant le
	 * journal (pied de page, `defer`) : si le journal se charge sur cette page,
	 * le cookie `famma_vid` est créé ici, aux mêmes règles que
	 * `visitor-journal.js` (UUID v4, même durée, `path=/`, `SameSite=Lax`) ;
	 * le journal le reprend ensuite tel quel. Sans journal sur la page, rien
	 * n'est créé. Le texte ne dépend que des réglages (cache LiteSpeed).
	 *
	 * @return string JavaScript.
	 */
	public static function browser_script(): string {
		$days = Visitor_Context::journal_loads() ? Visitor_Settings::retention_days() : 0;

		return 'window.fammaXid=function(){try{'
			. 'var re=/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,d=' . (int) $days . ',c=window.crypto,'
			. 'm=document.cookie.match(/(?:^|; )' . Visitor_Context::VISITOR_COOKIE . '=([^;]*)/),v=m?decodeURIComponent(m[1]).toLowerCase():"";'
			. 'if(!m&&d>0){if(c&&c.randomUUID){v=c.randomUUID();}else if(c&&c.getRandomValues){var b=c.getRandomValues(new Uint8Array(16));b[6]=b[6]&15|64;b[8]=b[8]&63|128;v="";for(var i=0;i<16;i++){v+=(b[i]<16?"0":"")+b[i].toString(16)+(3===i||5===i||7===i||9===i?"-":"");}}'
			. 'if(re.test(v)){document.cookie="' . Visitor_Context::VISITOR_COOKIE . '="+v+";path=/;max-age="+d*86400+";SameSite=Lax"+("https:"===location.protocol?";Secure":"");}}'
			. 'if(!re.test(v)){return;}var h=fammaSha256(v);return h?{external_id:h}:void 0;}catch(e){}};'
			. self::sha256_script();
	}

	/**
	 * SHA-256 synchrone d'une chaîne ASCII (l'UUID), en JavaScript.
	 *
	 * `crypto.subtle.digest()` est asynchrone : `fbq('init')` ne peut pas
	 * l'attendre sans décaler le PageView. Chaîne non ASCII : `undefined`.
	 *
	 * @return string JavaScript définissant `fammaSha256()`.
	 */
	private static function sha256_script(): string {
		return 'window.fammaSha256=function(s){var r=function(v,n){return(v>>>n)|(v<<(32-n));},M=4294967296,w=[],L=s.length*8,H=[],K=[],n=0,x={},i,j,o="";'
			. 'for(var q=2;n<64;q++){if(!x[q]){for(i=0;i<313;i+=q){x[i]=q;}H[n]=(Math.pow(q,.5)*M)|0;K[n++]=(Math.pow(q,1/3)*M)|0;}}'
			. 's+="\x80";while(s.length%64-56){s+="\x00";}'
			. 'for(i=0;i<s.length;i++){j=s.charCodeAt(i);if(j>>8){return;}w[i>>2]|=j<<((3-i)%4)*8;}'
			. 'w[w.length]=(L/M)|0;w[w.length]=L;'
			. 'for(j=0;j<w.length;){var W=w.slice(j,j+=16),P=H;H=H.slice(0,8);'
			. 'for(i=0;i<64;i++){var a=W[i-15],b=W[i-2],A=H[0],E=H[4],'
			. 't=H[7]+(r(E,6)^r(E,11)^r(E,25))+((E&H[5])^(~E&H[6]))+K[i]+(W[i]=i<16?W[i]:(W[i-16]+(r(a,7)^r(a,18)^(a>>>3))+W[i-7]+(r(b,17)^r(b,19)^(b>>>10)))|0),'
			. 'u=(r(A,2)^r(A,13)^r(A,22))+((A&H[1])^(A&H[2])^(H[1]&H[2]));H=[(t+u)|0].concat(H);H[4]=(H[4]+t)|0;}'
			. 'for(i=0;i<8;i++){H[i]=(H[i]+P[i])|0;}}'
			. 'for(i=0;i<8;i++){for(j=3;j+1;j--){var y=(H[i]>>(j*8))&255;o+=(y<16?"0":"")+y.toString(16);}}return o;};';
	}
}
