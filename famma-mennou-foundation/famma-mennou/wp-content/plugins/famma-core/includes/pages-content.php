<?php
/**
 * Contenu éditable des pages institutionnelles — À propos, Contact — et de la fiche produit.
 *
 * Règle du projet : aucun contenu éditorial ni coordonnée n'est codé en dur.
 * Tout ce que le client lit sur ces deux pages vient d'un champ que le
 * propriétaire édite depuis « FAMMA → Pages », sans dev et sans déploiement.
 *
 * Le fichier vit à part de `famma-core.php` : celui-ci passe déjà 2 100 lignes,
 * et la règle 6 demande de scinder par responsabilité dès qu'un domaine est
 * identifiable. Ici le domaine est net — le contenu de deux pages — et il ne
 * partage aucun état avec les commandes ou le tracking.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Champs de contenu des pages institutionnelles.
 *
 * Un schéma unique décrit les champs ; l'écran d'administration, l'assainissement
 * et la lecture côté thème en découlent tous les trois. Ajouter un bloc à une
 * page se fait donc en ajoutant une ligne au schéma, pas en touchant trois
 * fonctions qui finiraient par diverger.
 */
final class Pages_Content {

	/**
	 * Option abritant le contenu de la page À propos.
	 */
	public const OPTION_ABOUT = 'famma_page_about';

	/**
	 * Option abritant le contenu de la page Contact.
	 */
	public const OPTION_CONTACT = 'famma_page_contact';

	/**
	 * Option abritant les textes de la fiche produit (schéma : `Product_Content`).
	 */
	public const OPTION_PRODUCT = 'famma_page_product';

	/**
	 * Option abritant les textes de l'accueil (schéma : `Home_Content`).
	 */
	public const OPTION_HOME = 'famma_page_home';

	/**
	 * Option abritant les textes de la page Livraison (schéma : `Delivery_Content`).
	 */
	public const OPTION_DELIVERY = 'famma_page_delivery';

	/**
	 * Option abritant les textes de la page Retours (schéma : `Returns_Content`).
	 */
	public const OPTION_RETURNS = 'famma_page_returns';

	/**
	 * Pages gérées par cet écran, dans leur ordre d'affichage.
	 *
	 * @var array<int, string>
	 */
	private const PAGES = array( 'home', 'about', 'contact', 'delivery', 'returns', 'product' );

	/**
	 * Groupe de réglages, au sens de la Settings API.
	 *
	 * Public : les autres blocs de contenu éditable s'enregistrent dans le même
	 * groupe et sur le même écran, pour que le propriétaire n'ait qu'un seul
	 * endroit à connaître.
	 */
	public const GROUP = 'famma_pages';

	/**
	 * Identifiant de la page d'administration.
	 */
	public const SLUG = 'famma-pages';

	/**
	 * Coordonnées, lues par le thème via `famma_child_setting()`.
	 *
	 * Les noms sont imposés : le thème lit la constante `.env` en priorité,
	 * puis `get_option()` sur la version minuscule du même nom. Renommer une
	 * clé ici casserait silencieusement le pied de page.
	 *
	 * @var array<string, string>
	 */
	private const CONTACT_OPTIONS = array(
		'famma_whatsapp_number' => 'FAMMA_WHATSAPP_NUMBER',
		'famma_contact_phone'   => 'FAMMA_CONTACT_PHONE',
		'famma_contact_email'   => 'FAMMA_CONTACT_EMAIL',
		'famma_contact_address' => 'FAMMA_CONTACT_ADDRESS',
	);

	/**
	 * Instance unique.
	 *
	 * @var Pages_Content|null
	 */
	private static ?Pages_Content $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Pages_Content
	 */
	public static function instance(): Pages_Content {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Enregistre les hooks d'administration.
	 *
	 * @return void
	 */
	private function hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Schéma des champs de la page À propos.
	 *
	 * Les valeurs par défaut sont une proposition rédactionnelle relisible en
	 * administration, pas une vérité : elles ne contiennent ni chiffre, ni date,
	 * ni témoignage, ni localisation inventée (§58). Les seuls faits affirmés —
	 * paiement à la livraison, livraison offerte, couverture des 24 gouvernorats —
	 * sont ceux que le code du checkout applique réellement.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function about_schema(): array {
		return array(
			array(
				'key'        => 'hero_title',
				'type'       => 'text',
				'label'      => __( 'Titre principal', 'famma-core' ),
				'default_fr' => 'Toujours quelque chose de nouveau',
				'default_ar' => 'ديما حاجة جديدة',
			),
			array(
				'key'        => 'hero_text',
				'type'       => 'textarea',
				'label'      => __( 'Accroche', 'famma-core' ),
				'default_fr' => 'FAMMA est une boutique en ligne tunisienne. On cherche, on teste, on sélectionne — et on vous livre partout en Tunisie, à payer une fois le colis entre vos mains.',
				'default_ar' => 'فمّا حانوت أونلاين تونسي. نلوّجو، نجرّبو، نختاروا — ونوصّلولك في تونس الكل، وتخلّص كي يوصلك الكولي في يدك.',
			),
			array(
				'key'        => 'story_title',
				'type'       => 'text',
				'label'      => __( 'Titre « Qui sommes-nous »', 'famma-core' ),
				'default_fr' => 'Qui sommes-nous',
				'default_ar' => 'شكون أحنا',
			),
			array(
				'key'        => 'story_text',
				'type'       => 'textarea',
				'label'      => __( 'Texte « Qui sommes-nous »', 'famma-core' ),
				'default_fr' => "FAMMA part d'une idée simple : rassembler au même endroit des produits pratiques, utiles et parfois surprenants, sans vous faire courir d'un site à l'autre. Notre catalogue bouge souvent — ce qui plaît reste, le reste laisse la place à autre chose.",
				'default_ar' => 'فمّا جات من فكرة ساهلة: نجمّعو في بلاصة وحدة حوايج تنفع، تخدم، وساعات تعجّب — بلا ما تدور من سيت لسيت. الكاتالوغ متحرّك: اللي يعجب الناس يبقى، واللي لا يخلّي البلاصة لحاجة أخرى.',
			),
			array(
				'key'        => 'pillars_title',
				'type'       => 'text',
				'label'      => __( 'Titre de la section réassurance', 'famma-core' ),
				'default_fr' => 'Pourquoi commander chez nous',
				'default_ar' => 'علاش تطلب مننا',
			),
			array(
				'key'        => 'pillar1_title',
				'type'       => 'text',
				'label'      => __( 'Pilier 1 — titre', 'famma-core' ),
				'default_fr' => 'Paiement à la livraison',
				'default_ar' => 'الدفع كي يوصلك',
			),
			array(
				'key'        => 'pillar1_text',
				'type'       => 'textarea',
				'label'      => __( 'Pilier 1 — texte', 'famma-core' ),
				'default_fr' => "Vous ne payez rien à l'avance. Le règlement se fait en espèces, au moment où le livreur vous remet le colis.",
				'default_ar' => 'ما تخلّصش شي قبل. تخلّص كاش، وقت اللي يعطيك الليفرور الكولي في يدك.',
			),
			array(
				'key'        => 'pillar2_title',
				'type'       => 'text',
				'label'      => __( 'Pilier 2 — titre', 'famma-core' ),
				'default_fr' => 'Livraison offerte',
				'default_ar' => 'التوصيل فابور',
			),
			array(
				'key'        => 'pillar2_text',
				'type'       => 'textarea',
				'label'      => __( 'Pilier 2 — texte', 'famma-core' ),
				'default_fr' => "Les frais de livraison sont à notre charge. Le prix affiché sur la fiche produit est celui que vous payez, sans supplément à l'arrivée.",
				'default_ar' => 'التوصيل علينا أحنا. الثمن اللي تشوفو في الصفحة هو اللي تخلّصو، بلا زيادة كي يوصل.',
			),
			array(
				'key'        => 'pillar3_title',
				'type'       => 'text',
				'label'      => __( 'Pilier 3 — titre', 'famma-core' ),
				'default_fr' => 'Partout en Tunisie',
				'default_ar' => 'في تونس الكل',
			),
			array(
				'key'        => 'pillar3_text',
				'type'       => 'textarea',
				'label'      => __( 'Pilier 3 — texte', 'famma-core' ),
				'default_fr' => 'Nous livrons dans les 24 gouvernorats. Où que vous soyez, la commande suit le même parcours et les mêmes conditions.',
				'default_ar' => 'نوصّلو في 24 ولاية. وين ما تكون، الطلبية تمشي نفس الطريق وبنفس الشروط.',
			),
			array(
				'key'        => 'commitments_title',
				'type'       => 'text',
				'label'      => __( 'Titre des engagements', 'famma-core' ),
				'default_fr' => 'Nos engagements',
				'default_ar' => 'الوعود متاعنا',
			),
			array(
				'key'        => 'commitments_text',
				'type'       => 'textarea',
				'label'      => __( 'Engagements — un par ligne', 'famma-core' ),
				'help'       => __( 'Une ligne = un engagement affiché en liste. Laisser vide masque la section.', 'famma-core' ),
				'default_fr' => "Un prix clair, affiché en dinars, sans frais caché.\nUne commande confirmée par téléphone avant l'expédition.\nUn produit conforme à ce que montre la fiche.\nUne réponse à vos questions avant comme après la livraison.",
				'default_ar' => "ثمن واضح بالدينار، بلا مصاريف مخبّية.\nكل طلبية نأكّدوها بالتليفون قبل ما نبعثوها.\nالمنتج كيف ما تشوفو في الصفحة.\nنجاوبوك على أسئلتك قبل التوصيل وبعدو.",
			),
			array(
				'key'        => 'cta_title',
				'type'       => 'text',
				'label'      => __( 'Titre de l’appel à l’action', 'famma-core' ),
				'default_fr' => 'Jetez un œil au catalogue',
				'default_ar' => 'شوف الكاتالوغ',
			),
			array(
				'key'        => 'cta_label',
				'type'       => 'text',
				'label'      => __( 'Libellé du bouton', 'famma-core' ),
				'default_fr' => 'Voir la boutique',
				'default_ar' => 'شوف الحانوت',
			),
		);
	}

	/**
	 * Schéma des champs de la page Contact.
	 *
	 * Les horaires n'ont volontairement aucune valeur par défaut : les inventer
	 * reviendrait à promettre au client une disponibilité que personne n'a
	 * confirmée. Tant que le champ est vide, la section ne s'affiche pas.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function contact_schema(): array {
		return array(
			array(
				'key'        => 'hero_title',
				'type'       => 'text',
				'label'      => __( 'Titre principal', 'famma-core' ),
				'default_fr' => 'Nous contacter',
				'default_ar' => 'أتصل بينا',
			),
			array(
				'key'        => 'hero_text',
				'type'       => 'textarea',
				'label'      => __( 'Accroche', 'famma-core' ),
				'default_fr' => 'Une question sur un produit, une commande en cours, un souci à la livraison ? Écrivez-nous : le plus rapide reste WhatsApp.',
				'default_ar' => 'عندك سؤال على منتج، ولا طلبية في الطريق، ولا مشكلة في التوصيل؟ أكتبلنا — أسرع طريقة هي الواتساب.',
			),
			array(
				'key'        => 'whatsapp_title',
				'type'       => 'text',
				'label'      => __( 'WhatsApp — titre', 'famma-core' ),
				'default_fr' => 'Écrivez-nous sur WhatsApp',
				'default_ar' => 'أكتبلنا على الواتساب',
			),
			array(
				'key'        => 'whatsapp_text',
				'type'       => 'textarea',
				'label'      => __( 'WhatsApp — texte', 'famma-core' ),
				'default_fr' => 'C’est le canal que nous suivons de plus près. Envoyez votre message, nous revenons vers vous.',
				'default_ar' => 'هاذي القناة اللي نتبعوها أكثر. أبعث رسالتك، ونرجعولك.',
			),
			array(
				'key'        => 'whatsapp_label',
				'type'       => 'text',
				'label'      => __( 'WhatsApp — libellé du bouton', 'famma-core' ),
				'default_fr' => 'Ouvrir WhatsApp',
				'default_ar' => 'أفتح الواتساب',
			),
			array(
				'key'        => 'whatsapp_prefill',
				'type'       => 'textarea',
				'label'      => __( 'WhatsApp — message pré-rempli', 'famma-core' ),
				'help'       => __( 'Texte déjà saisi dans la conversation quand le client ouvre WhatsApp. Laisser vide pour ouvrir une conversation vierge.', 'famma-core' ),
				'default_fr' => 'Bonjour FAMMA, j’ai une question :',
				'default_ar' => 'أهلا فمّا، عندي سؤال:',
			),
			array(
				'key'        => 'hours_title',
				'type'       => 'text',
				'label'      => __( 'Horaires — titre', 'famma-core' ),
				'default_fr' => 'Nos horaires',
				'default_ar' => 'أوقات الخدمة',
			),
			array(
				'key'        => 'hours_text',
				'type'       => 'textarea',
				'label'      => __( 'Horaires — un par ligne', 'famma-core' ),
				'help'       => __( 'À REMPLIR : indiquez vos vraies plages horaires. Tant que ce champ est vide, la section reste invisible pour les clients.', 'famma-core' ),
				'default_fr' => '',
				'default_ar' => '',
			),
			array(
				'key'        => 'coverage_title',
				'type'       => 'text',
				'label'      => __( 'Zone de livraison — titre', 'famma-core' ),
				'default_fr' => 'Zone de livraison',
				'default_ar' => 'منطقة التوصيل',
			),
			array(
				'key'        => 'coverage_text',
				'type'       => 'textarea',
				'label'      => __( 'Zone de livraison — texte', 'famma-core' ),
				'default_fr' => 'Nous livrons dans les 24 gouvernorats, avec paiement à la livraison et frais de port offerts.',
				'default_ar' => 'نوصّلو في 24 ولاية، الدفع كي يوصلك والتوصيل فابور.',
			),
			array(
				'key'        => 'delivery_delay',
				'type'       => 'text',
				'label'      => __( 'Délai de livraison', 'famma-core' ),
				'help'       => __( 'Affiché sur chaque fiche produit, près du prix, et dans la section Livraison. Exemple de forme : « Livraison en 24 h à 48 h ». Tant que ce champ est vide, aucun délai n’est annoncé.', 'famma-core' ),
				'default_fr' => '',
				'default_ar' => '',
			),
		);
	}

	/**
	 * Délai de livraison annoncé au client, dans la langue de la page.
	 *
	 * Aucune valeur par défaut : un délai est une promesse, il ne s'affiche que
	 * si le propriétaire l'a écrit (§60).
	 *
	 * @return string Chaîne vide tant que le champ n'est pas rempli.
	 */
	public static function delivery_delay(): string {
		return trim( self::text( 'contact', 'delivery_delay' ) );
	}

	/**
	 * Schéma des champs, par page.
	 *
	 * @param string $page `about`, `contact` ou `product`.
	 * @return array<int, array<string, string>>
	 */
	public static function schema( string $page ): array {
		return match ( $page ) {
			'about'    => self::about_schema(),
			'product'  => Product_Content::schema(),
			'home'     => Home_Content::schema(),
			'delivery' => Delivery_Content::schema(),
			'returns'  => Returns_Content::schema(),
			default    => self::contact_schema(),
		};
	}

	/**
	 * Nom de l'option abritant une page.
	 *
	 * @param string $page `about`, `contact` ou `product`.
	 * @return string
	 */
	private static function option_name( string $page ): string {
		return match ( $page ) {
			'about'    => self::OPTION_ABOUT,
			'product'  => self::OPTION_PRODUCT,
			'home'     => self::OPTION_HOME,
			'delivery' => self::OPTION_DELIVERY,
			'returns'  => self::OPTION_RETURNS,
			default    => self::OPTION_CONTACT,
		};
	}

	/**
	 * Titre de la section d'une page, sur l'écran d'administration.
	 *
	 * @param string $page `about`, `contact` ou `product`.
	 * @return string
	 */
	private static function section_title( string $page ): string {
		return match ( $page ) {
			'about'    => __( 'Page À propos', 'famma-core' ),
			'product'  => __( 'Fiche produit', 'famma-core' ),
			'home'     => __( 'Page d’accueil — newsletter et « Ils nous font confiance »', 'famma-core' ),
			'delivery' => __( 'Page Livraison', 'famma-core' ),
			'returns'  => __( 'Page Retours', 'famma-core' ),
			default    => __( 'Page Contact', 'famma-core' ),
		};
	}

	/**
	 * Langue à servir pour le visiteur courant.
	 *
	 * L'arabe n'est servi qu'en contexte RTL, et seulement si le champ arabe a
	 * réellement été rempli : un bloc vide vaut moins qu'un bloc en français.
	 *
	 * @return string `ar` ou `fr`.
	 */
	private static function lang(): string {
		return is_rtl() ? 'ar' : 'fr';
	}

	/**
	 * Lit un champ de contenu, prêt à afficher, dans la langue de la page.
	 *
	 * @param string $page `about`, `contact` ou `product`.
	 * @param string $key  Clé du champ, telle que déclarée au schéma.
	 * @return string Chaîne vide si le champ n'est renseigné dans aucune langue.
	 */
	public static function text( string $page, string $key ): string {
		return self::text_in( $page, $key, self::lang() );
	}

	/**
	 * Lit un champ de contenu dans une langue donnée.
	 *
	 * La fiche produit française affiche, sous certaines lignes, leur version
	 * derja : il faut pouvoir lire l'arabe hors d'une page RTL.
	 *
	 * @param string $page     `about`, `contact` ou `product`.
	 * @param string $key      Clé du champ, telle que déclarée au schéma.
	 * @param string $lang     `fr` ou `ar`.
	 * @param bool   $fallback Retomber sur le français quand l'arabe est vide.
	 * @return string
	 */
	public static function text_in( string $page, string $key, string $lang, bool $fallback = true ): string {
		$stored = get_option( self::option_name( $page ), array() );
		$stored = is_array( $stored ) ? $stored : array();
		$lang   = 'ar' === $lang ? 'ar' : 'fr';

		$value = (string) ( $stored[ $key . '_' . $lang ] ?? '' );

		if ( $fallback && '' === trim( $value ) && 'ar' === $lang ) {
			$value = (string) ( $stored[ $key . '_fr' ] ?? '' );
		}

		if ( '' !== trim( $value ) ) {
			return $value;
		}

		// Rien en base : on retombe sur la proposition du schéma. Un champ que
		// le propriétaire a vidé volontairement est présent en base avec une
		// chaîne vide, ce qui l'exclut du test ci-dessus — le vide est donc
		// respecté et le bloc reste masqué.
		if ( array_key_exists( $key . '_' . $lang, $stored ) || array_key_exists( $key . '_fr', $stored ) ) {
			return '';
		}

		foreach ( self::schema( $page ) as $field ) {
			if ( $field['key'] === $key ) {
				$default = (string) ( $field[ 'default_' . $lang ] ?? '' );

				if ( '' !== trim( $default ) || ! $fallback ) {
					return $default;
				}

				return (string) ( $field['default_fr'] ?? '' );
			}
		}

		return '';
	}

	/**
	 * Découpe un champ multiligne en liste, en écartant les lignes vides.
	 *
	 * @param string $page `about` ou `contact`.
	 * @param string $key  Clé du champ.
	 * @return array<int, string>
	 */
	public static function lines( string $page, string $key ): array {
		$raw = self::text( $page, $key );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\R/u', $raw );
		$lines = is_array( $lines ) ? $lines : array();

		return array_values(
			array_filter(
				array_map( 'trim', $lines ),
				static function ( string $line ): bool {
					return '' !== $line;
				}
			)
		);
	}

	/**
	 * Questions / réponses saisies en blocs séparés par une ligne vide :
	 * la question sur la première ligne, la réponse dessous.
	 *
	 * Partagé par les pages Livraison et Retours. Un bloc sans réponse est
	 * écarté : un accordéon qui s'ouvre sur du vide est un défaut visible par
	 * le client.
	 *
	 * @param string $raw Texte saisi.
	 * @return array<int, array{question: string, answer: string}>
	 */
	public static function qa_pairs( string $raw ): array {
		$blocks = preg_split( '/\R\s*\R/u', trim( $raw ) );
		$pairs  = array();

		foreach ( (array) $blocks as $block ) {
			$lines = preg_split( '/\R/u', trim( (string) $block ) );
			$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );

			if ( count( $lines ) < 2 ) {
				continue;
			}

			$pairs[] = array(
				'question' => (string) array_shift( $lines ),
				'answer'   => implode( ' ', $lines ),
			);
		}

		return $pairs;
	}

	/**
	 * Enregistre les options et les champs.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		foreach ( self::PAGES as $page ) {
			$option = self::option_name( $page );

			register_setting(
				self::GROUP,
				$option,
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $value ) use ( $page ): array {
						return $this->sanitize_page( $page, $value );
					},
					'default'           => array(),
				)
			);

			add_settings_section(
				'famma_section_' . $page,
				self::section_title( $page ),
				array( $this, 'section_intro' ),
				self::SLUG
			);

			foreach ( self::schema( $page ) as $field ) {
				add_settings_field(
					$option . '_' . $field['key'],
					esc_html( $field['label'] ),
					array( $this, 'render_field' ),
					self::SLUG,
					'famma_section_' . $page,
					array(
						'page'  => $page,
						'field' => $field,
					)
				);
			}
		}

		add_settings_section(
			'famma_section_coordinates',
			__( 'Coordonnées', 'famma-core' ),
			array( $this, 'coordinates_intro' ),
			self::SLUG
		);

		foreach ( self::CONTACT_OPTIONS as $option => $constant ) {
			register_setting(
				self::GROUP,
				$option,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'famma_contact_email' === $option ? 'sanitize_email' : 'sanitize_text_field',
					'default'           => '',
				)
			);

			add_settings_field(
				$option,
				esc_html( $this->coordinate_label( $option ) ),
				array( $this, 'render_coordinate' ),
				self::SLUG,
				'famma_section_coordinates',
				array(
					'option'   => $option,
					'constant' => $constant,
				)
			);
		}
	}

	/**
	 * Libellé lisible d'une coordonnée.
	 *
	 * @param string $option Nom de l'option.
	 * @return string
	 */
	private function coordinate_label( string $option ): string {
		$labels = array(
			'famma_whatsapp_number' => __( 'Numéro WhatsApp', 'famma-core' ),
			'famma_contact_phone'   => __( 'Téléphone', 'famma-core' ),
			'famma_contact_email'   => __( 'Adresse e-mail', 'famma-core' ),
			'famma_contact_address' => __( 'Adresse / ville', 'famma-core' ),
		);

		return $labels[ $option ] ?? $option;
	}

	/**
	 * Assainit une page entière selon son schéma.
	 *
	 * Toute clé absente du schéma est écartée : un POST forgé ne peut pas
	 * enrichir l'option de champs arbitraires.
	 *
	 * @param string $page  `about` ou `contact`.
	 * @param mixed  $value Valeur brute soumise.
	 * @return array<string, string>
	 */
	private function sanitize_page( string $page, $value ): array {
		$value = is_array( $value ) ? $value : array();
		$clean = array();

		foreach ( self::schema( $page ) as $field ) {
			foreach ( array( 'fr', 'ar' ) as $lang ) {
				$name = $field['key'] . '_' . $lang;

				if ( ! isset( $value[ $name ] ) ) {
					continue;
				}

				$raw = wp_unslash( (string) $value[ $name ] );

				$clean[ $name ] = 'textarea' === $field['type']
					? sanitize_textarea_field( $raw )
					: sanitize_text_field( $raw );
			}
		}

		return $clean;
	}

	/**
	 * Texte d'introduction d'une section de page.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Un champ vidé masque son bloc côté client au lieu d’afficher un trou.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Texte d'introduction de la section des coordonnées.
	 *
	 * @return void
	 */
	public function coordinates_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Ces coordonnées alimentent la page Contact et le pied de page. Si la valeur est aussi définie dans le fichier .env du serveur, c’est celle du serveur qui s’applique.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche un champ de contenu, en français et en derja.
	 *
	 * @param array<string, mixed> $args Arguments du champ.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$page   = (string) ( $args['page'] ?? 'about' );
		$field  = (array) ( $args['field'] ?? array() );
		$option = self::option_name( $page );
		$stored = get_option( $option, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$languages = array(
			'fr' => __( 'Français', 'famma-core' ),
			'ar' => __( 'Derja', 'famma-core' ),
		);

		foreach ( $languages as $lang => $label ) {
			$name    = $option . '[' . $field['key'] . '_' . $lang . ']';
			$id      = $option . '_' . $field['key'] . '_' . $lang;
			$key     = $field['key'] . '_' . $lang;
			$current = array_key_exists( $key, $stored )
				? (string) $stored[ $key ]
				: (string) ( $field[ 'default_' . $lang ] ?? '' );
			$dir     = 'ar' === $lang ? 'rtl' : 'ltr';
			?>
			<p class="famma-field__lang">
				<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			</p>
			<?php if ( 'textarea' === ( $field['type'] ?? 'text' ) ) : ?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="3"
					class="large-text"
					dir="<?php echo esc_attr( $dir ); ?>"
				><?php echo esc_textarea( $current ); ?></textarea>
			<?php else : ?>
				<input
					type="text"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $current ); ?>"
					class="large-text"
					dir="<?php echo esc_attr( $dir ); ?>"
				/>
			<?php endif; ?>
			<?php
		}

		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}
	}

	/**
	 * Affiche un champ de coordonnée.
	 *
	 * @param array<string, string> $args Arguments du champ.
	 * @return void
	 */
	public function render_coordinate( array $args ): void {
		$option   = (string) ( $args['option'] ?? '' );
		$constant = (string) ( $args['constant'] ?? '' );
		$locked   = defined( $constant ) && '' !== (string) constant( $constant );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $option ); ?>"
			name="<?php echo esc_attr( $option ); ?>"
			value="<?php echo esc_attr( (string) get_option( $option, '' ) ); ?>"
			class="regular-text"
		/>
		<?php if ( $locked ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: constant name defined in .env. */
					esc_html__( 'Valeur imposée par la constante %s du serveur : ce champ reste sans effet tant qu’elle est définie.', 'famma-core' ),
					'<code>' . esc_html( $constant ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Ajoute l'écran sous le menu FAMMA.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'famma-dashboard',
			__( 'Pages', 'famma-core' ),
			__( 'Pages', 'famma-core' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Affiche l'écran de réglages.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php esc_html_e( 'Textes de l’accueil, contenu des pages À propos et Contact, et textes de la fiche produit. Rien n’est codé en dur : ce que vous écrivez ici est ce que le client lit.', 'famma-core' ); ?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button( __( 'Enregistrer', 'famma-core' ) );
				?>
			</form>
		</div>
		<?php
	}
}
