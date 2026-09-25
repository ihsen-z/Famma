<?php
/**
 * Page Retours : textes éditables depuis FAMMA → Pages, et demande de retour
 * envoyée sur WhatsApp.
 *
 * Maquette « FAMMA Retours Echanges » : pastille, chiffres clés, étapes,
 * retours acceptés / refusés, tableau par situation, formulaire de demande,
 * conseils d'emballage, encart d'aide et questions fréquentes.
 *
 * ── Aucun engagement par défaut ─────────────────────────────────────────────
 *
 * La maquette annonçait une politique complète : retour gratuit sous 7 jours,
 * collecte à 0 DT, remboursement sous 14 jours, garantie de 12 mois. Ce sont
 * des engagements que seul le propriétaire peut prendre (§60, textes légaux
 * fournis par lui). Décision du 25/09/2026 : tous les blocs qui portent un
 * engagement — pastille, chiffres clés, conditions, tableau, emballage — sont
 * livrés **vides**, donc masqués, jusqu'à ce qu'il les remplisse. Les valeurs
 * par défaut ne disent que ce qui est vrai aujourd'hui, et ce que la page
 * publiée disait déjà : la politique est en cours de rédaction, écrivez-nous
 * avant de renvoyer un article.
 *
 * ── Le formulaire ───────────────────────────────────────────────────────────
 *
 * Décision du même jour : la demande part sur WhatsApp. Le formulaire poste
 * vers la page elle-même ; `handle_request()` compose le message et renvoie le
 * visiteur vers `wa.me`, conversation pré-remplie. Rien n'est enregistré sur
 * le site et aucun e-mail n'est envoyé (quota de 40 par jour chez
 * l'hébergeur). Le visiteur relit le message dans WhatsApp avant de l'envoyer.
 *
 * Même mécanisme que les autres pages : ce fichier porte le schéma et la
 * lecture ; l'écran et l'enregistrement sont ceux de `Pages_Content`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Schéma, lecture et formulaire de la page Retours.
 */
final class Returns_Content {

	/**
	 * Clé de page, au sens de `Pages_Content`.
	 */
	public const PAGE = 'returns';

	/**
	 * Adresse (slug) de la page publiée.
	 */
	public const SLUG = 'retours';

	/**
	 * Champ caché qui signale une demande de retour.
	 */
	public const REQUEST_FIELD = 'famma_return_request';

	/**
	 * Longueur maximale des précisions : le message part dans une URL.
	 */
	private const NOTE_MAX = 800;

	/**
	 * Branche le traitement du formulaire.
	 *
	 * @return void
	 */
	public static function hooks(): void {
		add_action( 'template_redirect', array( self::class, 'handle_request' ), 1 );
	}

	/**
	 * Schéma des champs de la page Retours.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function schema(): array {
		$empty_hides = __( 'Vide : le bloc disparaît de la page.', 'famma-core' );
		$commitment  = __( 'Engagement : n’écrivez que ce que vous tenez réellement. Vide : le bloc disparaît.', 'famma-core' );
		$per_line    = __( 'Un élément par ligne. Vide : le bloc disparaît. N’écrivez que ce que vous tenez réellement.', 'famma-core' );

		$fields = array(
			self::field( 'badge', 'text', __( 'Retours — pastille au-dessus du titre', 'famma-core' ), $commitment . ' ' . __( 'Exemple : « Retour gratuit sous 7 jours ».', 'famma-core' ), '', '' ),
			self::field( 'lede', 'textarea', __( 'Retours — texte d’introduction', 'famma-core' ), $empty_hides, 'Un article reçu pose problème ? Écrivez-nous avant de le renvoyer, sur WhatsApp ou avec le formulaire de cette page : nous regardons votre cas avec vous.', 'كان عندك مشكل مع حاجة وصلتك، أكتبلنا قبل ما تبعثها، على الواتساب ولا بالفورمولار اللي في الصفحة هذي: نشوفو حالتك معاك.' ),
			self::field( 'notice', 'textarea', __( 'Retours — encadré d’information', 'famma-core' ), __( 'Affiché sous l’introduction. Videz-le une fois votre politique de retour remplie ci-dessous.', 'famma-core' ), 'Notre politique de retour et de remboursement est en cours de rédaction. Elle sera publiée sur cette page dès qu’elle sera prête.', 'سياسة الإرجاع قاعدين نكتبو فيها، وباش نحطّوها هوني كي تكمل.' ),
		);

		for ( $n = 1; $n <= 4; $n++ ) {
			$fields[] = self::field(
				'highlight_' . $n . '_value',
				'text',
				/* translators: %d: numéro du chiffre clé. */
				sprintf( __( 'Retours — chiffre clé %d, valeur', 'famma-core' ), $n ),
				1 === $n ? $commitment . ' ' . __( 'Exemple : « 7 jours ».', 'famma-core' ) : '',
				'',
				''
			);
			$fields[] = self::field(
				'highlight_' . $n . '_title',
				'text',
				/* translators: %d: numéro du chiffre clé. */
				sprintf( __( 'Retours — chiffre clé %d, titre', 'famma-core' ), $n ),
				1 === $n ? __( 'Un chiffre clé sans titre disparaît. Exemple : « Pour changer d’avis ».', 'famma-core' ) : '',
				'',
				''
			);
			$fields[] = self::field(
				'highlight_' . $n . '_text',
				'textarea',
				/* translators: %d: numéro du chiffre clé. */
				sprintf( __( 'Retours — chiffre clé %d, texte', 'famma-core' ), $n ),
				'',
				'',
				''
			);
		}

		$steps = array(
			1 => array( 'Écrivez-nous', 'Sur WhatsApp ou avec le formulaire de cette page, avec votre numéro de commande.', 'أكتبلنا', 'على الواتساب ولا بالفورمولار اللي في الصفحة هذي، مع نومرو الطلبية.' ),
			2 => array( 'Montrez-nous le problème', 'Si l’article est abîmé ou ne correspond pas à votre commande, joignez une photo à votre message.', 'ورّينا المشكل', 'كان الحاجة مكسّرة ولا موش كيف اللي طلبتها، زيد تصويرة مع الميساج.' ),
			3 => array( 'Attendez notre réponse', 'Nous convenons avec vous de la suite. Ne renvoyez aucun colis avant notre accord.', 'استنّى جوابنا', 'نتّفقو معاك على الخطوة الجاية. ما تبعث حتى كولي قبل ما نقولولك.' ),
			4 => array( '', '', '', '' ),
		);

		$fields[] = self::field( 'steps_title', 'text', __( 'Retours — titre des étapes', 'famma-core' ), $empty_hides, 'Comment faire une demande', 'كيفاش تطلب إرجاع' );
		$fields[] = self::field( 'steps_sub', 'text', __( 'Retours — sous-titre des étapes', 'famma-core' ), $empty_hides, '', '' );

		foreach ( $steps as $n => $step ) {
			$fields[] = self::field(
				'step_' . $n . '_title',
				'text',
				/* translators: %d: numéro de l'étape. */
				sprintf( __( 'Retours — étape %d, titre', 'famma-core' ), $n ),
				1 === $n ? __( 'Une étape sans titre disparaît. Décrivez votre vraie façon de faire (collecte, dépôt, délai de réponse) une fois votre politique fixée.', 'famma-core' ) : '',
				$step[0],
				$step[2]
			);
			$fields[] = self::field(
				'step_' . $n . '_text',
				'textarea',
				/* translators: %d: numéro de l'étape. */
				sprintf( __( 'Retours — étape %d, texte', 'famma-core' ), $n ),
				'',
				$step[1],
				$step[3]
			);
		}

		return array_merge(
			$fields,
			array(
				self::field( 'ok_title', 'text', __( 'Retours — titre « acceptés »', 'famma-core' ), '', 'Retours acceptés', 'الإرجاع المقبول' ),
				self::field( 'ok_items', 'textarea', __( 'Retours — cas acceptés', 'famma-core' ), $per_line, '', '' ),
				self::field( 'ko_title', 'text', __( 'Retours — titre « refusés »', 'famma-core' ), '', 'Retours refusés', 'الإرجاع المرفوض' ),
				self::field( 'ko_items', 'textarea', __( 'Retours — cas refusés', 'famma-core' ), $per_line, '', '' ),

				self::field( 'cases_title', 'text', __( 'Retours — titre du tableau', 'famma-core' ), '', 'Selon votre situation', 'حسب حالتك' ),
				self::field( 'cases_head', 'text', __( 'Retours — en-têtes du tableau', 'famma-core' ), __( 'Quatre intitulés séparés par « | ».', 'famma-core' ), 'Situation | Délai | Frais | Résultat', 'الحالة | الأجل | المصاريف | النتيجة' ),
				self::field( 'cases', 'textarea', __( 'Retours — lignes du tableau', 'famma-core' ), __( 'Une ligne par situation : « Situation | Délai | Frais | Résultat ». Vide : le tableau disparaît. N’écrivez que ce que vous tenez réellement.', 'famma-core' ), '', '' ),

				self::field( 'form_title', 'text', __( 'Retours — titre du formulaire', 'famma-core' ), __( 'Vide : le formulaire disparaît. Il n’apparaît que si un numéro WhatsApp est configuré.', 'famma-core' ), 'Demander un retour', 'أطلب إرجاع' ),
				self::field( 'form_sub', 'textarea', __( 'Retours — texte sous le titre du formulaire', 'famma-core' ), $empty_hides, 'Le formulaire prépare votre message : il s’ouvre dans WhatsApp, vous le relisez et vous l’envoyez.', 'الفورمولار يحضّرلك الميساج: يتحلّ في الواتساب، تقراه وتبعثو.' ),
				self::field( 'form_order', 'text', __( 'Retours — champ « numéro de commande »', 'famma-core' ), '', 'Numéro de commande', 'نومرو الطلبية' ),
				self::field( 'form_order_ph', 'text', __( 'Retours — exemple de numéro de commande', 'famma-core' ), '', 'Ex. : 1234', 'مثلاً: 1234' ),
				self::field( 'form_phone', 'text', __( 'Retours — champ « téléphone »', 'famma-core' ), '', 'Téléphone', 'التليفون' ),
				self::field( 'form_phone_ph', 'text', __( 'Retours — exemple de téléphone', 'famma-core' ), '', 'XX XXX XXX', 'XX XXX XXX' ),
				self::field( 'form_reason', 'text', __( 'Retours — question « motif »', 'famma-core' ), '', 'Motif', 'السبب' ),
				self::field( 'form_reasons', 'textarea', __( 'Retours — motifs proposés', 'famma-core' ), __( 'Un motif par ligne. Vide : la question disparaît.', 'famma-core' ), "Article abîmé à la réception\nArticle défectueux\nArticle différent de ma commande\nAutre", "الحاجة وصلت مكسّرة\nالحاجة ما تخدمش\nموش هي اللي طلبتها\nسبب آخر" ),
				self::field( 'form_want', 'text', __( 'Retours — question « souhait »', 'famma-core' ), '', 'Ce que vous souhaitez', 'شنوّة تحب' ),
				self::field( 'form_outcomes', 'textarea', __( 'Retours — souhaits proposés', 'famma-core' ), __( 'Un souhait par ligne ; « Souhait | précision » pour ajouter une ligne d’explication. Vide : la question disparaît.', 'famma-core' ), "Remboursement\nÉchange", "ترجيع الفلوس\nتبديل" ),
				self::field( 'form_note', 'text', __( 'Retours — champ « précisions »', 'famma-core' ), '', 'Précisions', 'تفاصيل' ),
				self::field( 'form_note_ph', 'text', __( 'Retours — exemple de précisions', 'famma-core' ), '', 'Décrivez brièvement le problème.', 'أشرحلنا المشكل في كلمتين.' ),
				self::field( 'form_submit', 'text', __( 'Retours — bouton d’envoi', 'famma-core' ), '', 'Envoyer sur WhatsApp', 'أبعث على الواتساب' ),
				self::field( 'form_legal', 'textarea', __( 'Retours — mention sous le bouton', 'famma-core' ), $empty_hides, 'Le message s’ouvre dans WhatsApp : rien n’est envoyé tant que vous ne l’avez pas validé.', 'الميساج يتحلّ في الواتساب: ما يتبعث شي قبل ما تأكّدو إنتي.' ),
				self::field( 'form_intro', 'text', __( 'Retours — première ligne du message WhatsApp', 'famma-core' ), '', 'Bonjour, je souhaite faire une demande de retour.', 'سلام، نحب نعمل طلب إرجاع.' ),

				self::field( 'pack_title', 'text', __( 'Retours — titre « préparer le colis »', 'famma-core' ), '', 'Préparer votre colis', 'حضّر الكولي' ),
				self::field( 'pack_items', 'textarea', __( 'Retours — conseils d’emballage', 'famma-core' ), $per_line, '', '' ),

				self::field( 'help_title', 'text', __( 'Retours — encart d’aide, titre', 'famma-core' ), $empty_hides, 'Un doute sur votre retour ?', 'عندك سؤال على الإرجاع؟' ),
				self::field( 'help_text', 'textarea', __( 'Retours — encart d’aide, texte', 'famma-core' ), __( 'Les horaires de la section Contact s’affichent dessous.', 'famma-core' ), 'Écrivez-nous avec votre numéro de commande : nous vous répondons.', 'أكتبلنا مع نومرو الطلبية ونجاوبوك.' ),
				self::field( 'help_whatsapp', 'text', __( 'Retours — encart d’aide, bouton WhatsApp', 'famma-core' ), __( 'Le bouton n’apparaît que si un numéro WhatsApp est configuré.', 'famma-core' ), 'Écrire sur WhatsApp', 'أكتبلنا على واتساب' ),
				self::field( 'help_terms', 'text', __( 'Retours — encart d’aide, lien vers les conditions générales', 'famma-core' ), $empty_hides, 'Lire les conditions générales', 'أقرا الشروط العامة' ),

				self::field( 'faq_title', 'text', __( 'Retours — titre des questions', 'famma-core' ), $empty_hides, 'Questions fréquentes', 'الأسئلة المتكرّرة' ),
				self::field(
					'faq',
					'textarea',
					__( 'Retours — questions et réponses', 'famma-core' ),
					__( 'Une question par bloc : la question sur la première ligne, la réponse dessous, une ligne vide entre deux questions. N’écrivez que ce qui est vrai.', 'famma-core' ),
					"Puis-je renvoyer un article sans vous prévenir ?\nNon. Écrivez-nous d’abord, sur WhatsApp ou avec le formulaire de cette page : nous convenons avec vous de la suite.\n\nMon colis est arrivé abîmé, que faire ?\nPrenez une photo du colis et de l’article, puis envoyez-la-nous avec votre numéro de commande.",
					"نجم نرجّع حاجة من غير ما نقلكم؟\nلا. أكتبلنا قبل، على الواتساب ولا بالفورمولار اللي في الصفحة هذي: نتّفقو معاك على الخطوة الجاية.\n\nالكولي وصلني مكسّر، شنوّة نعمل؟\nصوّر الكولي والحاجة، وأبعثلنا التصاور مع نومرو الطلبية."
				),
			)
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
	 * Texte d'un champ, dans la langue de la page.
	 *
	 * @param string $key Clé du champ.
	 * @return string
	 */
	public static function text( string $key ): string {
		return trim( Pages_Content::text( self::PAGE, $key ) );
	}

	/**
	 * Champ multiligne, une entrée par ligne.
	 *
	 * @param string $key Clé du champ.
	 * @return array<int, string>
	 */
	public static function lines( string $key ): array {
		return Pages_Content::lines( self::PAGE, $key );
	}

	/**
	 * Champ multiligne découpé en colonnes sur « | ».
	 *
	 * @param string $key     Clé du champ.
	 * @param int    $columns Nombre de colonnes attendues.
	 * @return array<int, array<int, string>> Lignes complétées à `$columns` cellules.
	 */
	public static function rows( string $key, int $columns ): array {
		$rows = array();

		foreach ( self::lines( $key ) as $line ) {
			$cells  = array_map( 'trim', explode( '|', $line, $columns ) );
			$rows[] = array_pad( $cells, $columns, '' );
		}

		return $rows;
	}

	/**
	 * Questions fréquentes de la page Retours.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function faq(): array {
		return Pages_Content::qa_pairs( self::text( 'faq' ) );
	}

	/**
	 * Description Google de la page Retours : son texte d'introduction.
	 *
	 * @param string $description Description proposée.
	 * @param mixed  $post        Page affichée.
	 * @return string
	 */
	public static function seo_description( $description, $post ): string {
		if ( '' !== trim( (string) $description ) || ! $post instanceof \WP_Post || self::SLUG !== $post->post_name ) {
			return (string) $description;
		}

		return self::text( 'lede' );
	}

	/**
	 * Transforme une demande de retour en conversation WhatsApp.
	 *
	 * Le formulaire poste vers la page Retours elle-même : la langue de la
	 * page (française ou arabe) est donc déjà celle du visiteur, et les
	 * intitulés du message suivent.
	 *
	 * Pas de jeton de sécurité (nonce), et c'est voulu : cette requête n'écrit
	 * rien, ne crée rien, n'envoie rien. Son seul effet est de renvoyer le
	 * visiteur vers le WhatsApp de la boutique avec son propre texte, qu'il
	 * relit avant d'envoyer — une requête forgée n'aurait rien à gagner. Un
	 * jeton imprimé dans la page, elle, la ferait échouer : la page est servie
	 * par le cache pendant 7 jours, un jeton ne vit que 24 heures.
	 *
	 * Motif et souhait arrivent sous forme de rang dans la liste configurée :
	 * seul un libellé saisi par le propriétaire peut entrer dans le message.
	 *
	 * @return void
	 */
	public static function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- lecture seule, voir le docblock : aucune écriture, simple redirection vers WhatsApp.
		if ( ! isset( $_POST[ self::REQUEST_FIELD ] ) || ! is_page( self::SLUG ) ) {
			return;
		}

		$raw = isset( $_POST['famma_return'] ) && is_array( $_POST['famma_return'] )
			? wp_unslash( $_POST['famma_return'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque valeur est assainie ci-dessous, une à une.
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$value = static function ( string $key ) use ( $raw ): string {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
		};

		$reasons = self::lines( 'form_reasons' );
		$wants   = self::rows( 'form_outcomes', 2 );
		$reason  = '' !== $value( 'reason' ) ? ( $reasons[ absint( $value( 'reason' ) ) ] ?? '' ) : '';
		$want    = '' !== $value( 'outcome' ) ? ( $wants[ absint( $value( 'outcome' ) ) ][0] ?? '' ) : '';

		$lines = array( self::text( 'form_intro' ) );

		foreach (
			array(
				'form_order'  => sanitize_text_field( $value( 'order' ) ),
				'form_phone'  => sanitize_text_field( $value( 'phone' ) ),
				'form_reason' => $reason,
				'form_want'   => $want,
				'form_note'   => mb_substr( sanitize_textarea_field( $value( 'note' ) ), 0, self::NOTE_MAX ),
			) as $label => $answer
		) {
			if ( '' !== trim( $answer ) ) {
				$lines[] = self::text( $label ) . ' : ' . trim( $answer );
			}
		}

		$url = WhatsApp::link( implode( "\n", array_filter( $lines, 'strlen' ) ) );

		if ( ! str_starts_with( $url, 'https://wa.me/' ) ) {
			wp_safe_redirect( (string) get_permalink() );
			exit;
		}

		/*
		 * `wp_safe_redirect()` retire de l'adresse tout « %0A » (protection
		 * contre l'injection d'en-têtes) : les lignes du message arrivaient
		 * collées dans WhatsApp, mesuré en ligne le 25/09. L'adresse est
		 * construite ici par `rawurlencode()`, qui ne peut produire aucun
		 * retour chariot brut, et commence obligatoirement par `https://wa.me/` :
		 * l'en-tête est donc posé directement, sans perdre les retours à la
		 * ligne. `nocache_headers()` : la réponse dépend du formulaire, aucun
		 * cache ne doit la garder.
		 */
		nocache_headers();
		header( 'Location: ' . $url, true, 303 );
		exit;
	}
}
