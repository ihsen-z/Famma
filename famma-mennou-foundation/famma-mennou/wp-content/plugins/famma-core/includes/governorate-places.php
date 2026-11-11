<?php
/**
 * Délégations de Tunisie, pour reconnaître la ville tapée dans l'adresse.
 *
 * ── Source ─────────────────────────────────────────────────────────────────
 * Ministère de l'Intérieur, Direction Générale des Affaires Régionales,
 * portail des données ouvertes : jeu « Délégations par gouvernorats de la
 * République » (créé le 25/06/2013), pages française et arabe :
 * https://opendata.interieur.gov.tn/fr/catalog/delegations-par-gouvernorats-de-la-republique
 * https://opendata.interieur.gov.tn/ar/catalog/delegations-par-gouvernorats-de-la-republique
 * Lu le 11/11/2026 : 24 gouvernorats, 264 délégations, noms recopiés tels
 * quels (orthographe et coquilles de la source comprises, ex. « Zazis » pour
 * جرجيس). Le nom arabe de chaque gouvernorat de la source a été contrôlé
 * contre `Express_Order::governorate_arabic_names()`.
 *
 * L'INS ne publie pas de nomenclature des délégations téléchargeable ; le
 * bilan du recensement 2024 s'arrête aux gouvernorats. Les délégations
 * créées après 2013 manquent donc ici : elles s'ajoutent par le filtre
 * `famma_governorate_places`, comme les noms courants (quartiers, villes
 * hors liste) une fois validés par le propriétaire. Aucun nom inventé.
 *
 * Le script ne fait que proposer : un gouvernorat choisi à la main n'est
 * jamais remplacé, et le client peut toujours le changer.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Noms de lieux par gouvernorat, pour le formulaire express.
 */
final class Governorate_Places {

	/**
	 * Délégations par gouvernorat : [ nom français, nom arabe ], dans l'ordre de la source.
	 */
	private const DELEGATIONS = array(
		'TN-11' => array(
			array( 'Carthage', 'قرطاج' ),
			array( 'La Médina', 'المدينة' ),
			array( 'Bab Bhar', 'باب البحر' ),
			array( 'Bab Souika', 'باب سويقة' ),
			array( 'Omrane', 'العمران' ),
			array( 'Omrane Supérieur', 'العمران الأعلى' ),
			array( 'Attahrir', 'التحرير' ),
			array( 'El Menzah', 'المنزه' ),
			array( 'Cité Alkhadhra', 'حي الخضراء' ),
			array( 'Bardo', 'باردو' ),
			array( 'Séjoumi', 'السيجومي' ),
			array( 'Azzouhour', 'الزهور' ),
			array( 'Alhrairia', 'الحرائرية' ),
			array( 'Sidi Hsine', 'سيدي حسين' ),
			array( 'Ouardia', 'الوردية' ),
			array( 'Kabaria', 'الكبارية' ),
			array( 'Sidi Elbéchir', 'سيدي البشير' ),
			array( 'Jebel Jelloud', 'جبل الجلود' ),
			array( 'La Goulette', 'حلق الوادي' ),
			array( 'Le Kram', 'الكرم' ),
			array( 'La Marsa', 'المرسى' ),
		),
		'TN-12' => array(
			array( 'Ariana Ville', 'أريانة المدينة' ),
			array( 'Soukra', 'سكرة' ),
			array( 'Raouède', 'رواد' ),
			array( 'Kalâat Andalous', 'قلعة الأندلس' ),
			array( 'Sidi Thabet', 'سيدي ثابت' ),
			array( 'Cité Attadhamon', 'حي التضامن' ),
			array( 'M’nihla', 'المنيهلة' ),
		),
		'TN-14' => array(
			array( 'Manouba', 'منوبة' ),
			array( 'Oued Ellil', 'وادي الليل' ),
			array( 'Tebourba', 'طبربة' ),
			array( 'Battan', 'البطان' ),
			array( 'Jedaida', 'الجديدة' ),
			array( 'Mornaguia', 'المرناقية' ),
			array( 'Borj Amri', 'برج العامري' ),
			array( 'Douar Hicher', 'دوار هيشر' ),
		),
		'TN-13' => array(
			array( 'Ben Arous', 'بن عروس' ),
			array( 'Nouvelle Médina', 'المدينة الجديدة' ),
			array( 'Mourouj', 'المروج' ),
			array( 'Hammam Lif', 'حمام الأنف' ),
			array( 'Hammam Chatt', 'حمام الشط' ),
			array( 'Boumhel Bassatine', 'بومهل البساتين' ),
			array( 'Ezzahra', 'الزهراء' ),
			array( 'Radès', 'رادس' ),
			array( 'Megrine', 'مقرين' ),
			array( 'M’hamdia', 'المحمدية' ),
			array( 'Fouchana', 'فوشانة' ),
			array( 'Mornag', 'مرناق' ),
		),
		'TN-21' => array(
			array( 'Nabeul', 'نابل' ),
			array( 'Dar Chaâbane Elfehri', 'دار شعبان الفهري' ),
			array( 'Béni Khiar', 'بني خيار' ),
			array( 'Korba', 'قربة' ),
			array( 'Menzel Temime', 'منزل تميم' ),
			array( 'Mida', 'الميدة' ),
			array( 'Kelibia', 'قليبية' ),
			array( 'Hammam Ghezaz', 'حمام الأغزاز' ),
			array( 'Haouaria', 'الهوارية' ),
			array( 'Takelsa', 'تاكلسة' ),
			array( 'Slimane', 'سليمان' ),
			array( 'Menzel Bouzelfa', 'منزل بوزلفة' ),
			array( 'Béni Khalled', 'بني خلاد' ),
			array( 'Grombalia', 'قرمبالية' ),
			array( 'Bouârgoub', 'بوعرقوب' ),
			array( 'Hammamet', 'الحمامات' ),
		),
		'TN-23' => array(
			array( 'Bizerte Nord', 'بنزرت الشمالية' ),
			array( 'Jarzouna', 'جرزونة' ),
			array( 'Bizerte Sud', 'بنزرت الجنوبية' ),
			array( 'Sejnane', 'سجنان' ),
			array( 'Joumine', 'جومين' ),
			array( 'Mateur', 'ماطر' ),
			array( 'Ghezala', 'غزالة' ),
			array( 'Menzel Bourguiba', 'منزل بورقيبة' ),
			array( 'Tinja', 'تينجة' ),
			array( 'Utique', 'أوتيك' ),
			array( 'Ghar El Melh', 'غار الملح' ),
			array( 'Menzel Jemil', 'منزل جميل' ),
			array( 'El Alia', 'العالية' ),
			array( 'Ras Jebel', 'رأس الجبل' ),
		),
		'TN-22' => array(
			array( 'Zaghouan', 'زغوان' ),
			array( 'Zériba', 'الزريبة' ),
			array( 'Bir Mecharga', 'بئر مشارقة' ),
			array( 'Fahs', 'الفحص' ),
			array( 'Nadhour', 'الناظور' ),
			array( 'Saouaf', 'صواف' ),
		),
		'TN-51' => array(
			array( 'Sousse Ville', 'سوسة المدينة' ),
			array( 'Zaouia Ksiba Thrayat', 'الزاوية القصيبة الثريات' ),
			array( 'Sousse Ryadh', 'سوسة الرياض' ),
			array( 'Sousse Jawhara', 'سوسة جوهرة' ),
			array( 'Sousse Sidi Abdelhamid', 'سوسة سيدي عبد الحميد' ),
			array( 'Hammam sousse', 'حمام سوسة' ),
			array( 'Akouda', 'أكودة' ),
			array( 'Kalâa Elkébira', 'القلعة الكبرى' ),
			array( 'Sidi Bouali', 'سيدي بوعلي' ),
			array( 'Hergla', 'هرقلة' ),
			array( 'Enfidha', 'النفيضة' ),
			array( 'Bouficha', 'بوفيشة' ),
			array( 'Koundar', 'كندار' ),
			array( 'Sidi Elheni', 'سيدي الهاني' ),
			array( 'Msaken', 'مساكن' ),
			array( 'Kalâa Ességhira', 'القلعة الصغرى' ),
		),
		'TN-52' => array(
			array( 'Monastir', 'المنستيـر' ),
			array( 'Ouerdanine', 'الوردانيـن' ),
			array( 'Sahline', 'الساحليـن' ),
			array( 'Zéramdine', 'زرمديـن' ),
			array( 'Béni Hassan', 'بنـي حسان' ),
			array( 'Jammel', 'جمـال' ),
			array( 'Benbla', 'بنبلة' ),
			array( 'Moknine', 'المكنين' ),
			array( 'Bekalta', 'البقالطة' ),
			array( 'Teboulba', 'طبلبة' ),
			array( 'Ksar Helal', 'قصر هلال' ),
			array( 'Ksibet Medyouni', 'قصيبة المديوني' ),
			array( 'Sayada Lamta Bouhjar', 'صيادة لمطة بوحجر' ),
		),
		'TN-53' => array(
			array( 'Mahdia', 'المهدية' ),
			array( 'Boumerdes', 'بومرداس' ),
			array( 'Ouled Chamekh', 'أولاد الشامخ' ),
			array( 'Chorbane', 'شربان' ),
			array( 'Hbira', 'هبيرة' ),
			array( 'Souassi', 'السواسي' ),
			array( 'Eljem', 'الجم' ),
			array( 'Chebba', 'الشابة' ),
			array( 'Malloulech', 'ملولش' ),
			array( 'Sidi Alouane', 'سيدي علوان' ),
			array( 'Ksour Essef', 'قصور الساف' ),
		),
		'TN-61' => array(
			array( 'Sfax Ville', 'صفاقـس المدينة' ),
			array( 'Sfax Ouest', 'صفاقـس الغربية' ),
			array( 'Sakiet Ezzit', 'ساقية الزيت' ),
			array( 'Sakiet Eddaier', 'ساقية الداير' ),
			array( 'Sfax sud', 'صفاقس الجنوبية' ),
			array( 'Tina', 'طينة' ),
			array( 'Agareb', 'عقارب' ),
			array( 'Jebeniana', 'جبنيانة' ),
			array( 'El Amra', 'العامرة' ),
			array( 'El Hencha', 'الحنشة' ),
			array( 'Menzel chaker', 'منزل شاكر' ),
			array( 'Ghraiba', 'الغريبة' ),
			array( 'Bir Ali Ben Khelifa', 'بئر علي بن خليفة' ),
			array( 'Sekhira', 'الصخيرة' ),
			array( 'Mahrès', 'المحرس' ),
			array( 'Kerkennah', 'قـرقنـة' ),
		),
		'TN-31' => array(
			array( 'Béja nord', 'باجة الشمالية' ),
			array( 'Béja sud', 'باجة الجنوبية' ),
			array( 'Amdoun', 'عمدون' ),
			array( 'Nefza', 'نفزة' ),
			array( 'Teboursouk', 'تبرسق' ),
			array( 'Tibar', 'تيبار' ),
			array( 'Testour', 'تستور' ),
			array( 'Goubellat', 'قبلاط' ),
			array( 'Mejez El Bab', 'مجاز الباب' ),
		),
		'TN-32' => array(
			array( 'Jendouba', 'جنـدوبة' ),
			array( 'Jendouba Nord', 'جنـدوبة الشمالية' ),
			array( 'Boussalem', 'بوسالم' ),
			array( 'Tabarka', 'طبرقـة' ),
			array( 'Ain Drahem', 'عين دراهم' ),
			array( 'Fernana', 'فرنانة' ),
			array( 'Ghardimaou', 'غار الدماء' ),
			array( 'Oued Mliz', 'وادي مليز' ),
			array( 'Balta Bouaouene', 'بلطة بوعوان' ),
		),
		'TN-33' => array(
			array( 'Kef Ouest', 'الكاف الغربية' ),
			array( 'Kef Est', 'الكاف الشرقية' ),
			array( 'Nebeur', 'نبـر' ),
			array( 'Sakiet Sidi Youssef', 'ساقية سيدي يوسف' ),
			array( 'Tejerouine', 'تاجروين' ),
			array( 'Kalâat sinane', 'قلعة سنان' ),
			array( 'Kalâa El khasba', 'القلعة الخصبة' ),
			array( 'Jerissa', 'الجريصة' ),
			array( 'Gsour', 'القصور' ),
			array( 'Dahmani', 'الدهماني' ),
			array( 'Le Sers', 'السرس' ),
		),
		'TN-34' => array(
			array( 'Siliana nord', 'سليانة الشمالية' ),
			array( 'Siliana sud', 'سليانة الجنوبية' ),
			array( 'Bouarada', 'بوعرادة' ),
			array( 'Gâafour', 'قعفور' ),
			array( 'El Aroussa', 'العروسة' ),
			array( 'Le Krib', 'الكريب' ),
			array( 'Bourouis', 'بورويس' ),
			array( 'Makther', 'مكثر' ),
			array( 'Rouhia', 'الروحية' ),
			array( 'Kesra', 'كسرى' ),
			array( 'Bargou', 'برقو' ),
		),
		'TN-41' => array(
			array( 'Kairouan Nord', 'القيروان الشمالية' ),
			array( 'Kairouan Sud', 'القيروان الجنوبية' ),
			array( 'Chebika', 'الشبيكة' ),
			array( 'Sebikha', 'السبيخة' ),
			array( 'Oueslatia', 'الوسلاتية' ),
			array( 'Haffouz', 'حفوز' ),
			array( 'El Ala', 'العلا' ),
			array( 'Hajeb El Ayoun', 'حاجب العيون' ),
			array( 'Nasrallah', 'نصر الله' ),
			array( 'Cherarda', 'الشراردة' ),
			array( 'Bouhajla', 'بوحجلة' ),
		),
		'TN-43' => array(
			array( 'Sidi Bouzid Ouest', 'سيدي بوزيد الغربية' ),
			array( 'Sidi Bouzid Est', 'سيدي بوزيد الشرقية' ),
			array( 'Jelma', 'جلمة' ),
			array( 'Sabbalet Ouled Askar', 'سبالة أولاد عسكر' ),
			array( 'Bir Hfay', 'بئر الحفي' ),
			array( 'Sidi Ali Benôun', 'سيدي علي بن عون' ),
			array( 'Menzel Bouzayane', 'منزل بوزيان' ),
			array( 'Meknassi', 'المكناسي' ),
			array( 'Souk Jedid', 'سوق الجديد' ),
			array( 'Mezouna', 'المزونة' ),
			array( 'Regueb', 'الرقاب' ),
			array( 'Ouled Haffouz', 'أولاد حفوز' ),
		),
		'TN-42' => array(
			array( 'Kasserine Nord', 'القصرين الشمالية' ),
			array( 'Kasserine Sud', 'القصرين الجنوبية' ),
			array( 'Azzouhour', 'الزهور' ),
			array( 'Hassi ferid', 'حاسي الفريد' ),
			array( 'Sbitla', 'سبيطلة' ),
			array( 'Sbiba', 'سبيبة' ),
			array( 'Jedliane', 'جدليان' ),
			array( 'El Ayoun', 'العيون' ),
			array( 'Tela', 'تالة' ),
			array( 'Hidra', 'حيدرة' ),
			array( 'Foussana', 'فوسانة' ),
			array( 'Feriana', 'فريانة' ),
			array( 'Mejel Bel Abbes', 'ماجل بلعباس' ),
		),
		'TN-81' => array(
			array( 'Gabès ville', 'قابـس المدينة' ),
			array( 'Gabès ouest', 'قابـس الغربية' ),
			array( 'Gabès sud', 'قابـس الجنوبية' ),
			array( 'Ghannouch', 'غنوش' ),
			array( 'Metouia', 'المطوية' ),
			array( 'Menzel habib', 'منزل الحبيب' ),
			array( 'Hamma', 'الحامة' ),
			array( 'Matmata', 'مطماطة' ),
			array( 'Matmata nouvelle', 'مطماطة الجديدة' ),
			array( 'Mareth', 'مارث' ),
		),
		'TN-82' => array(
			array( 'Mednine Nord', 'مدنيـن الشمالية' ),
			array( 'Mednine Sud', 'مدنين الجنوبية' ),
			array( 'Béni khedach', 'بني خداش' ),
			array( 'Ben Guerdene', 'بن قردان' ),
			array( 'Zazis', 'جرجيس' ),
			array( 'Jerba Houmet Souk', 'جربة حومة السوق' ),
			array( 'Jerba Midoun', 'جربة ميدون' ),
			array( 'Jerba Ajim', 'جربة أجيم' ),
			array( 'Sidi Makhlouf', 'سيدي مخلوف' ),
		),
		'TN-71' => array(
			array( 'Gafsa Nord', 'قفصة الشمالية' ),
			array( 'Sidi Aich', 'سيدي عيش' ),
			array( 'El Ksar', 'القصر' ),
			array( 'Gafsa Sud', 'قفصة الجنوبية' ),
			array( 'Moulares', 'أم العرائس' ),
			array( 'Redyef', 'الرديف' ),
			array( 'Métlaoui', 'المتلوي' ),
			array( 'El Mdhilla', 'المظيلة' ),
			array( 'El Guettar', 'القطار' ),
			array( 'Belkhir', 'بلخير' ),
			array( 'Sned', 'السند' ),
		),
		'TN-72' => array(
			array( 'Tozeur', 'توزر' ),
			array( 'Degueche', 'دقاش' ),
			array( 'Tameghza', 'تمغزة' ),
			array( 'Nefta', 'نفطة' ),
			array( 'Hezoua', 'حزوة' ),
		),
		'TN-83' => array(
			array( 'Tataouine Nord', 'تطاوين الشمالية' ),
			array( 'Tataouine Sud', 'تطاوين الجنوبية' ),
			array( 'Smar', 'الصمار' ),
			array( 'Bir Lahmer', 'البئر الأحمر' ),
			array( 'Ghomrassen', 'غمراسن' ),
			array( 'Dhehiba', 'ذهيبة' ),
			array( 'Remada', 'رمادة' ),
		),
		'TN-73' => array(
			array( 'Kébili Sud', 'قبلي الجنوبية' ),
			array( 'Kébili Nord', 'قبلي الشمالية' ),
			array( 'Souk El Ahad', 'سوق الأحد' ),
			array( 'Douz nord', 'دوز الشمالية' ),
			array( 'Douz sud', 'دوز الجنوبية' ),
			array( 'El Faouar', 'الفوار' ),
		),
	);

	/**
	 * Localités nommées ensemble dans une même délégation de la source.
	 *
	 * Chacune est un mot du nom officiel. Écartés : « Zaouia » et « Ksiba »
	 * (confondus avec d'autres lieux), « Bassatine » (nom commun).
	 */
	private const COMPOSITE_PARTS = array(
		'TN-52' => array( 'Sayada', 'صيادة', 'Lamta', 'لمطة', 'Bouhjar', 'بوحجر' ),
		'TN-51' => array( 'Thrayat', 'الثريات' ),
		'TN-13' => array( 'Boumhel', 'بومهل' ),
	);

	/**
	 * Noms communs écartés : la médina est la vieille ville de chaque cité,
	 * pas seulement la délégation de Tunis qui porte ce nom.
	 */
	private const GENERIC_NAMES = array( 'La Médina', 'المدينة' );

	/**
	 * Derniers mots qui précisent une délégation dans la même ville : « Bizerte
	 * Nord » mène aussi à « Bizerte », « سوسة المدينة » à « سوسة ».
	 */
	private const QUALIFIERS = array( 'ville', 'nord', 'sud', 'est', 'ouest', 'المدينة', 'الشمالية', 'الجنوبية', 'الشرقية', 'الغربية' );

	/**
	 * Noms de lieux par code de gouvernorat, sans doublon.
	 *
	 * Pour chaque gouvernorat : ses délégations en français et en arabe, puis
	 * des formes tirées de ces mêmes noms :
	 * - le nom sans son dernier mot de précision (« Douz nord » → « Douz ») ;
	 * - le premier mot partagé par au moins deux délégations du seul même
	 *   gouvernorat, d'au moins quatre lettres (« Jerba Midoun », « Jerba
	 *   Ajim »… → « Jerba ») ;
	 * - les localités des délégations composées (`COMPOSITE_PARTS`).
	 *
	 * Les noms communs (`GENERIC_NAMES`) sont écartés.
	 *
	 * Le nom du gouvernorat lui-même est ajouté par le formulaire, qui le tient
	 * de la même source que la liste affichée.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function places(): array {
		$out    = array();
		$firsts = self::shared_first_words();
		foreach ( self::DELEGATIONS as $code => $delegations ) {
			$names = array();
			foreach ( $delegations as $pair ) {
				foreach ( $pair as $name ) {
					$names[] = $name;
					$base    = self::without_qualifier( $name );
					if ( '' !== $base ) {
						$names[] = $base;
					}
				}
			}
			$names        = array_merge( $names, $firsts[ $code ] ?? array(), self::COMPOSITE_PARTS[ $code ] ?? array() );
			$out[ $code ] = array_values( array_unique( array_diff( $names, self::GENERIC_NAMES ) ) );
		}

		/**
		 * Noms de lieux reconnus dans l'adresse du formulaire express.
		 *
		 * Pour ajouter une délégation créée après 2013 ou un nom courant validé :
		 * `$places['TN-82'][] = 'Zarzis';`. Les noms partagés par deux
		 * gouvernorats sont ignorés par le script.
		 *
		 * @param array<string, array<int, string>> $out Noms par code de gouvernorat (TN-11…).
		 */
		$filtered = apply_filters( 'famma_governorate_places', $out );
		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * Nom sans son dernier mot de précision, ou chaîne vide s'il n'en a pas.
	 *
	 * @param string $name Nom de délégation.
	 * @return string
	 */
	private static function without_qualifier( string $name ): string {
		$words = preg_split( '/\s+/u', trim( $name ) );
		if ( ! is_array( $words ) || count( $words ) < 2 ) {
			return '';
		}
		$last = mb_strtolower( (string) end( $words ) );
		if ( ! in_array( $last, self::QUALIFIERS, true ) ) {
			return '';
		}
		array_pop( $words );
		return implode( ' ', $words );
	}

	/**
	 * Premiers mots communs à plusieurs délégations d'un seul gouvernorat.
	 *
	 * @return array<string, array<int, string>> Mots par code de gouvernorat.
	 */
	private static function shared_first_words(): array {
		$seen = array();
		foreach ( self::DELEGATIONS as $code => $delegations ) {
			foreach ( $delegations as $pair ) {
				foreach ( $pair as $name ) {
					$words = preg_split( '/\s+/u', trim( $name ) );
					if ( ! is_array( $words ) || count( $words ) < 2 ) {
						continue;
					}
					$first = $words[0];
					$key   = self::fold( $first );
					if ( mb_strlen( $key ) < 4 ) {
						continue;
					}
					$seen[ $key ]['word']           = $first;
					$seen[ $key ]['codes'][ $code ] = ( $seen[ $key ]['codes'][ $code ] ?? 0 ) + 1;
				}
			}
		}
		$out = array();
		foreach ( $seen as $entry ) {
			if ( 1 === count( $entry['codes'] ) && reset( $entry['codes'] ) >= 2 ) {
				$out[ (string) key( $entry['codes'] ) ][] = $entry['word'];
			}
		}
		return $out;
	}

	/**
	 * Forme de comparaison d'un mot : minuscules, sans accents ni tatwil.
	 *
	 * @param string $word Mot.
	 * @return string
	 */
	private static function fold( string $word ): string {
		$word = str_replace( "\u{0640}", '', mb_strtolower( $word ) );
		return function_exists( 'remove_accents' ) ? remove_accents( $word ) : $word;
	}
}
