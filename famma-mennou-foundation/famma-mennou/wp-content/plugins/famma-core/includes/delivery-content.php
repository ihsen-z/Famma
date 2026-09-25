<?php
/**
 * Textes de la page Livraison, éditables depuis FAMMA → Pages.
 *
 * Maquette « FAMMA Livraison » : bandeau, trois points forts, mode de
 * livraison, zones, étapes, questions fréquentes et encart d'aide. La maquette
 * portait des données d'exemple (tarifs de 7 à 12 DT, express, points relais,
 * suivi par SMS, retour gratuit sous 7 jours) qui ne correspondent pas à la
 * boutique : elles ne sont pas reprises. Les valeurs par défaut ci-dessous ne
 * disent que ce qui est vrai et déjà publié — livraison offerte (§4), 24
 * gouvernorats, paiement à la livraison, appel de confirmation.
 *
 * Le délai n'est écrit nulle part ici : le marqueur `{delai}` est remplacé par
 * le champ « Délai de livraison » de la section Contact, déjà affiché sur les
 * fiches produit. Tant que ce champ est vide, tout texte qui le cite disparaît
 * plutôt que d'annoncer un délai inventé.
 *
 * Même mécanisme que la fiche produit : ce fichier ne porte que le schéma et
 * la lecture ; l'écran et l'enregistrement sont ceux de `Pages_Content`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma et lecture des textes de la page Livraison.
 */
final class Delivery_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'delivery';

	/**
	 * Marqueur remplacé par le délai de livraison.
	 */
	public const DELAY_TAG = '{delai}';

	/**
	 * Schéma des champs de la page Livraison.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$empty_hides = __( 'Vide : le bloc disparaît de la page.', 'famma-core' );
		$delay_help  = __( '{delai} est remplacé par le « Délai de livraison » de la section Contact. Tant que ce délai est vide, le texte qui le cite disparaît.', 'famma-core' );

		return array(
			self::field( 'badge', 'text', __( 'Livraison — pastille au-dessus du titre', 'famma-core' ), $empty_hides, 'Livraison partout en Tunisie', 'التوصيل في تونس الكل' ),
			self::field( 'lede', 'textarea', __( 'Livraison — texte d’introduction', 'famma-core' ), $empty_hides, 'Nous livrons dans les 24 gouvernorats. La livraison est offerte et vous payez en espèces, à la réception.', 'نوصّلو في الـ 24 ولاية. التوصيل فابور، وتخلّص كاش كي توصلك الطلبية.' ),

			self::field( 'highlight_1_title', 'text', __( 'Livraison — point fort 1, titre', 'famma-core' ), $empty_hides, 'Livraison offerte', 'التوصيل فابور' ),
			self::field( 'highlight_1_text', 'textarea', __( 'Livraison — point fort 1, texte', 'famma-core' ), '', '0 DT de frais : le prix affiché sur la fiche produit est le prix que vous payez.', '0 دينار مصاريف: السوم المكتوب في صفحة المنتج هو اللي تخلّصو.' ),
			self::field( 'highlight_2_title', 'text', __( 'Livraison — point fort 2, titre', 'famma-core' ), $empty_hides, 'Paiement à la livraison', 'الدفع كي يوصلك' ),
			self::field( 'highlight_2_text', 'textarea', __( 'Livraison — point fort 2, texte', 'famma-core' ), '', 'Vous réglez en espèces au livreur, à la réception du colis. Rien à payer en ligne.', 'تخلّص كاش لليفرور كي يوصلك الكولي. ما تخلّص حتى شي أونلاين.' ),
			self::field( 'highlight_3_title', 'text', __( 'Livraison — point fort 3, titre', 'famma-core' ), $delay_help, self::DELAY_TAG, self::DELAY_TAG ),
			self::field( 'highlight_3_text', 'textarea', __( 'Livraison — point fort 3, texte', 'famma-core' ), '', 'Partout en Tunisie, dans les 24 gouvernorats.', 'في تونس الكل، في الـ 24 ولاية.' ),

			self::field( 'option_title', 'text', __( 'Livraison — titre « Mode de livraison »', 'famma-core' ), $empty_hides, 'Mode de livraison', 'طريقة التوصيل' ),
			self::field( 'option_sub', 'text', __( 'Livraison — phrase sous le titre du mode', 'famma-core' ), '', 'Un seul mode, le même partout en Tunisie.', 'طريقة وحدة، نفسها في تونس الكل.' ),
			self::field( 'option_name', 'text', __( 'Livraison — nom du mode', 'famma-core' ), $empty_hides, 'Livraison à domicile', 'توصيل للدار' ),
			self::field( 'option_price', 'text', __( 'Livraison — prix du mode', 'famma-core' ), __( 'Ce que paie le client : la livraison est offerte (0 DT affiché).', 'famma-core' ), 'Offerte', 'فابور' ),
			self::field( 'option_points', 'textarea', __( 'Livraison — points du mode (un par ligne)', 'famma-core' ), $delay_help, "Dans les 24 gouvernorats\n" . self::DELAY_TAG . "\nPaiement en espèces à la réception\nCommande confirmée par téléphone avant l’expédition", "في الـ 24 ولاية\n" . self::DELAY_TAG . "\nتخلّص كاش كي توصلك\nنعيطولك باش نأكدو الطلبية قبل ما نبعثوها" ),

			self::field( 'zone_title', 'text', __( 'Livraison — titre des zones', 'famma-core' ), $empty_hides, 'Zones desservies', 'المناطق اللي نوصّلولها' ),
			self::field( 'zone_sub', 'text', __( 'Livraison — phrase sous le titre des zones', 'famma-core' ), '', 'Les 24 gouvernorats, aux mêmes conditions.', 'الـ 24 ولاية، بنفس الشروط.' ),
			self::field(
				'zones',
				'textarea',
				__( 'Livraison — zones (une par ligne, « Zone : gouvernorats »)', 'famma-core' ),
				$empty_hides,
				"Grand Tunis : Tunis, Ariana, Ben Arous, Manouba\nNord-Est : Nabeul, Zaghouan, Bizerte\nNord-Ouest : Béja, Jendouba, Le Kef, Siliana\nCentre-Est : Sousse, Monastir, Mahdia, Sfax\nCentre-Ouest : Kairouan, Kasserine, Sidi Bouzid\nSud-Est : Gabès, Médenine, Tataouine\nSud-Ouest : Gafsa, Tozeur, Kébili",
				"تونس الكبرى : تونس، أريانة، بن عروس، منوبة\nالشمال الشرقي : نابل، زغوان، بنزرت\nالشمال الغربي : باجة، جندوبة، الكاف، سليانة\nالوسط الشرقي : سوسة، المنستير، المهدية، صفاقس\nالوسط الغربي : القيروان، القصرين، سيدي بوزيد\nالجنوب الشرقي : قابس، مدنين، تطاوين\nالجنوب الغربي : قفصة، توزر، قبلي"
			),
			self::field( 'zone_price', 'text', __( 'Livraison — frais affichés pour chaque zone', 'famma-core' ), '', 'Offerte', 'فابور' ),
			self::field( 'zone_note', 'textarea', __( 'Livraison — note sous les zones', 'famma-core' ), $delay_help, 'Mêmes conditions dans toutes les zones. ' . self::DELAY_TAG . ', paiement à la réception.', 'نفس الشروط في المناطق الكل. ' . self::DELAY_TAG . '، والخلاص كي توصلك.' ),

			self::field( 'steps_title', 'text', __( 'Livraison — titre des étapes', 'famma-core' ), $empty_hides, 'Comment se passe une livraison', 'كيفاش يتعدّى التوصيل' ),
			self::field( 'step_1_title', 'text', __( 'Livraison — étape 1, titre', 'famma-core' ), $empty_hides, 'Vous commandez', 'تطلب' ),
			self::field( 'step_1_text', 'textarea', __( 'Livraison — étape 1, texte', 'famma-core' ), '', 'En ligne, sans créer de compte et sans rien payer.', 'أونلاين، بلا كونت وبلا ما تخلّص حتى شي.' ),
			self::field( 'step_2_title', 'text', __( 'Livraison — étape 2, titre', 'famma-core' ), $empty_hides, 'Nous vous appelons', 'نعيطولك' ),
			self::field( 'step_2_text', 'textarea', __( 'Livraison — étape 2, texte', 'famma-core' ), '', 'Pour confirmer votre commande avant l’expédition.', 'باش نأكدو الطلبية قبل ما نبعثوها.' ),
			self::field( 'step_3_title', 'text', __( 'Livraison — étape 3, titre', 'famma-core' ), $empty_hides, 'Nous livrons', 'نوصّلو' ),
			self::field( 'step_3_text', 'textarea', __( 'Livraison — étape 3, texte', 'famma-core' ), $delay_help, self::DELAY_TAG . ', partout en Tunisie.', self::DELAY_TAG . '، في تونس الكل.' ),
			self::field( 'step_4_title', 'text', __( 'Livraison — étape 4, titre', 'famma-core' ), $empty_hides, 'Vous payez à la réception', 'تخلّص كي توصلك' ),
			self::field( 'step_4_text', 'textarea', __( 'Livraison — étape 4, texte', 'famma-core' ), '', 'En espèces, au livreur, au moment où vous recevez le colis.', 'كاش، لليفرور، كي يوصلك الكولي.' ),

			self::field( 'faq_title', 'text', __( 'Livraison — titre des questions', 'famma-core' ), $empty_hides, 'Questions fréquentes', 'الأسئلة المتكرّرة' ),
			self::field(
				'faq',
				'textarea',
				__( 'Livraison — questions et réponses', 'famma-core' ),
				__( 'Une question par bloc : la question sur la première ligne, la réponse dessous, une ligne vide entre deux questions. N’écrivez que ce qui est vrai.', 'famma-core' ),
				"Combien coûte la livraison ?\n0 DT. La livraison est comprise dans le prix affiché.\n\nVous livrez où ?\nPartout en Tunisie, dans les 24 gouvernorats.\n\nComment je paie ?\nEn espèces, au livreur, au moment où vous recevez votre commande. Aucun paiement en ligne n’est demandé.\n\nLivrez-vous hors de Tunisie ?\nNon : nous livrons uniquement en Tunisie, dans les 24 gouvernorats.\n\nDois-je créer un compte ?\nNon. La commande se fait avec votre nom, votre téléphone et votre adresse.",
				"قدّاش يكلّف التوصيل؟\n0 دينار. التوصيل داخل في السوم المكتوب.\n\nوين توصّلو؟\nفي تونس الكل، في الـ 24 ولاية.\n\nكيفاش نخلّص؟\nكاش، لليفرور، كي توصلك الطلبية. ما نطلبو منك حتى خلاص أونلاين.\n\nتوصّلو برّا تونس؟\nلا: نوصّلو كان في تونس، في الـ 24 ولاية.\n\nلازمني نعمل كونت؟\nلا. تطلب بإسمك، نومرو التليفون والعنوان متاعك."
			),

			self::field( 'help_title', 'text', __( 'Livraison — encart d’aide, titre', 'famma-core' ), $empty_hides, 'Une question sur votre livraison ?', 'عندك سؤال على التوصيل؟' ),
			self::field( 'help_text', 'textarea', __( 'Livraison — encart d’aide, texte', 'famma-core' ), '', 'Écrivez-nous sur WhatsApp. Si vous avez déjà commandé, indiquez votre numéro de commande.', 'أكتبلنا على واتساب. كان طلبت قبل، أعطينا نومرو الطلبية.' ),
			self::field( 'help_whatsapp', 'text', __( 'Livraison — encart d’aide, bouton WhatsApp', 'famma-core' ), __( 'Le bouton n’apparaît que si un numéro WhatsApp est configuré.', 'famma-core' ), 'Écrire sur WhatsApp', 'أكتبلنا على واتساب' ),
			self::field( 'help_shop', 'text', __( 'Livraison — encart d’aide, bouton boutique', 'famma-core' ), $empty_hides, 'Voir la boutique', 'شوف الحانوت' ),
		);
	}

	/**
	 * Un champ du schéma.
	 *
	 * @param string $key        Clé.
	 * @param string $type       `text` ou `textarea`.
	 * @param string $label      Libellé d'administration.
	 * @param string $help       Aide d'administration.
	 * @param string $default_fr Valeur par défaut en français.
	 * @param string $default_ar Valeur par défaut en derja.
	 * @return array<string, string>
	 */
	private static function field( string $key, string $type, string $label, string $help, string $default_fr, string $default_ar ): array {
		return array(
			'key'        => $key,
			'type'       => $type,
			'label'      => $label,
			'help'       => $help,
			'default_fr' => $default_fr,
			'default_ar' => $default_ar,
		);
	}

	/**
	 * Texte d'un champ, marqueur de délai remplacé.
	 *
	 * @param string $key Clé du champ.
	 * @return string Chaîne vide si le champ est vide, ou s'il cite un délai non renseigné.
	 */
	public static function text( string $key ): string {
		return self::with_delay( trim( Pages_Content::text( self::PAGE, $key ) ) );
	}

	/**
	 * Champ multiligne, une entrée par ligne, marqueur de délai remplacé.
	 *
	 * @param string $key Clé du champ.
	 * @return array<int, string>
	 */
	public static function lines( string $key ): array {
		$lines = array_map( array( self::class, 'with_delay' ), Pages_Content::lines( self::PAGE, $key ) );

		return array_values(
			array_filter(
				$lines,
				static function ( string $line ): bool {
					return '' !== $line;
				}
			)
		);
	}

	/**
	 * Zones desservies : « Zone : gouvernorats », une par ligne.
	 *
	 * @return array<int, array{zone: string, detail: string}>
	 */
	public static function zones(): array {
		$zones = array();

		foreach ( self::lines( 'zones' ) as $line ) {
			$parts = preg_split( '/\s*:\s*/u', $line, 2 );
			$parts = is_array( $parts ) ? $parts : array( $line );

			$zones[] = array(
				'zone'   => trim( (string) $parts[0] ),
				'detail' => trim( (string) ( $parts[1] ?? '' ) ),
			);
		}

		return $zones;
	}

	/**
	 * Questions fréquentes : blocs séparés par une ligne vide.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function faq(): array {
		return Pages_Content::qa_pairs( self::with_delay( trim( Pages_Content::text( self::PAGE, 'faq' ) ) ) );
	}

	/**
	 * Description Google de la page Livraison : son texte d'introduction.
	 *
	 * Branché sur `famma_seo_composed_description` (includes/seo.php) : la page
	 * n'a presque rien dans l'éditeur, son texte vit dans FAMMA → Pages.
	 *
	 * @param string $description Description proposée.
	 * @param mixed  $post        Page affichée.
	 * @return string
	 */
	public static function seo_description( $description, $post ): string {
		if ( '' !== trim( (string) $description ) || ! $post instanceof \WP_Post || 'livraison' !== $post->post_name ) {
			return (string) $description;
		}

		return self::text( 'lede' );
	}

	/**
	 * Remplace le marqueur de délai.
	 *
	 * @param string $text Texte saisi.
	 * @return string Chaîne vide si le texte cite un délai qui n'est pas renseigné.
	 */
	private static function with_delay( string $text ): string {
		if ( ! str_contains( $text, self::DELAY_TAG ) ) {
			return $text;
		}

		$delay = Pages_Content::delivery_delay();

		if ( '' === $delay ) {
			return '';
		}

		return trim( str_replace( self::DELAY_TAG, $delay, $text ) );
	}
}
