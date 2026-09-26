<?php
/**
 * Newsletter de l'accueil : enregistrement des adresses et export.
 *
 * Le site **enregistre** les adresses ; il n'envoie aucun e-mail. L'hébergeur
 * plafonne l'envoi à 40 e-mails par jour, consommés par les commandes : une
 * campagne partirait d'un outil dédié, à partir de l'export CSV proposé ici.
 *
 * Chaque inscrit est un contenu privé `famma_subscriber` : la liste, la
 * recherche et la suppression sont celles de l'administration WordPress, sans
 * table à créer ni à migrer. Seuls les administrateurs (`manage_options`) y
 * accèdent.
 *
 * Séquence de sécurité du projet (règle 4) : nonce, pot de miel contre les
 * robots, limite de fréquence par adresse IP, `sanitize_email()` puis
 * `is_email()`. Le formulaire est public : aucune capacité n'est exigée pour
 * s'inscrire, c'est l'export qui est réservé.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Inscriptions à la newsletter.
 */
final class Newsletter {

	/**
	 * Type de contenu des inscrits.
	 */
	public const POST_TYPE = 'famma_subscriber';

	/**
	 * Action de `admin-post.php` et du nonce du formulaire.
	 */
	public const ACTION = 'famma_newsletter';

	/**
	 * Nom du champ nonce.
	 */
	public const NONCE = 'famma_nl_nonce';

	/**
	 * Nom du champ e-mail.
	 */
	public const FIELD_EMAIL = 'famma_nl_email';

	/**
	 * Pot de miel : un champ masqué qu'un humain laisse vide.
	 */
	public const FIELD_TRAP = 'famma_nl_website';

	/**
	 * Langue de la page d'inscription.
	 */
	public const FIELD_LANG = 'famma_nl_lang';

	/**
	 * Paramètre d'URL portant le résultat, lu par le thème.
	 */
	public const QUERY = 'famma_nl';

	/**
	 * Script qui rafraîchit le jeton avant l'envoi, mis en file par le thème.
	 */
	public const SCRIPT = 'famma-newsletter';

	/**
	 * Action de l'export CSV.
	 */
	private const EXPORT = 'famma_newsletter_export';

	/**
	 * Inscriptions acceptées par adresse IP et par heure.
	 */
	private const RATE_LIMIT = 5;

	/**
	 * Instance unique.
	 *
	 * @var Newsletter|null
	 */
	private static ?Newsletter $i = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return Newsletter
	 */
	public static function instance(): Newsletter {
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );

		/*
		 * Audit du 25/09 (CODE-31) : l'accueil est servi par le cache de page
		 * jusqu'à 7 jours, alors qu'un jeton vit 12 à 24 h. Le formulaire
		 * demande donc un jeton frais à ce point non caché juste avant l'envoi.
		 */
		add_action( 'wc_ajax_famma_newsletter_nonce', array( $this, 'send_nonce' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_script' ) );
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'export_link' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_value' ), 10, 2 );
	}

	/**
	 * Déclare le type de contenu des inscrits, sous le menu FAMMA.
	 *
	 * Toutes les capacités pointent sur `manage_options` : un éditeur de
	 * contenu ne voit pas les adresses des clients. La création manuelle est
	 * fermée — une adresse n'entre que par le formulaire.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Newsletter', 'famma-core' ),
					'singular_name'      => __( 'Inscrit', 'famma-core' ),
					'menu_name'          => __( 'Newsletter', 'famma-core' ),
					'all_items'          => __( 'Newsletter', 'famma-core' ),
					'search_items'       => __( 'Rechercher une adresse', 'famma-core' ),
					'not_found'          => __( 'Aucune inscription pour l’instant.', 'famma-core' ),
					'not_found_in_trash' => __( 'Aucune inscription dans la corbeille.', 'famma-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'famma-dashboard',
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'map_meta_cap'    => false,
				'capabilities'    => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'do_not_allow',
				),
				'rewrite'         => false,
				'query_var'       => false,
				'can_export'      => false,
				'capability_type' => 'post',
			)
		);
	}

	/**
	 * Enregistre le script du formulaire, sans le charger.
	 *
	 * Le thème le met en file en imprimant le formulaire : il n'est servi que
	 * sur la page qui en a besoin, en pied de page.
	 *
	 * @return void
	 */
	public function register_script(): void {
		$file = plugin_dir_path( __DIR__ ) . 'assets/js/newsletter.js';

		if ( ! file_exists( $file ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT,
			plugin_dir_url( __DIR__ ) . 'assets/js/newsletter.js',
			array(),
			(string) filemtime( $file ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Renvoie un jeton neuf pour le formulaire.
	 *
	 * Rien n'est écrit ici : le jeton ne sert qu'à l'envoi, qui garde ses
	 * propres défenses (pot de miel, limite par adresse IP).
	 *
	 * @return void
	 */
	public function send_nonce(): void {
		wp_send_json_success(
			array( 'nonce' => wp_create_nonce( self::ACTION ) )
		);
	}

	/**
	 * Point d'entrée du jeton, à imprimer sur le formulaire.
	 *
	 * @return string
	 */
	public static function nonce_endpoint(): string {
		return class_exists( '\WC_AJAX' ) ? (string) \WC_AJAX::get_endpoint( 'famma_newsletter_nonce' ) : '';
	}

	/**
	 * Traite une inscription, puis renvoie le visiteur sur sa page.
	 *
	 * @return void
	 */
	public function handle(): void {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			$this->back( 'expired' );
		}

		// Un robot remplit tout : on le remercie sans rien enregistrer.
		if ( ! empty( $_POST[ self::FIELD_TRAP ] ) ) {
			$this->back( 'ok' );
		}

		$email = isset( $_POST[ self::FIELD_EMAIL ] ) ? sanitize_email( wp_unslash( $_POST[ self::FIELD_EMAIL ] ) ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			$this->back( 'invalid' );
		}

		if ( ! $this->within_rate_limit() ) {
			$this->back( 'busy' );
		}

		$email = strtolower( $email );

		/*
		 * Déjà inscrit : même réponse qu'une inscription nouvelle. Répondre
		 * « déjà inscrit » permettrait à n'importe qui de vérifier si une
		 * adresse figure dans la liste.
		 */
		if ( ! $this->exists( $email ) ) {
			$lang = isset( $_POST[ self::FIELD_LANG ] ) && 'ar' === sanitize_key( wp_unslash( $_POST[ self::FIELD_LANG ] ) ) ? 'ar' : 'fr';

			$id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_title'  => $email,
					'post_status' => 'private',
					'meta_input'  => array( '_famma_nl_lang' => $lang ),
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				$this->back( 'error' );
			}
		}

		$this->back( 'ok' );
	}

	/**
	 * L'adresse est-elle déjà inscrite ?
	 *
	 * @param string $email Adresse en minuscules.
	 * @return bool
	 */
	private function exists( string $email ): bool {
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'any',
				'title'                  => $email,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return ! empty( $query->posts );
	}

	/**
	 * Limite les inscriptions par adresse IP (empreinte, jamais l'IP en clair).
	 *
	 * @return bool Faux quand la limite horaire est atteinte.
	 */
	private function within_rate_limit(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'famma_nl_' . md5( $ip . wp_salt( 'nonce' ) );

		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Renvoie le visiteur sur la page d'où il vient, avec le résultat.
	 *
	 * @param string $status `ok`, `invalid`, `expired`, `busy` ou `error`.
	 * @return void
	 */
	private function back( string $status ): void {
		$target = wp_validate_redirect( (string) wp_get_referer(), home_url( '/' ) );

		// Sans référent, `add_query_arg()` repartirait de l'URL courante, admin-post.php.
		if ( '' === $target ) {
			$target = home_url( '/' );
		}

		$target = remove_query_arg( self::QUERY, $target );
		$target = add_query_arg( self::QUERY, $status, $target ) . '#famma-newsletter';

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Lien « Exporter en CSV » au-dessus de la liste des inscrits.
	 *
	 * @param array<string, string> $views Liens de filtre de la liste.
	 * @return array<string, string>
	 */
	public function export_link( $views ): array {
		$views = is_array( $views ) ? $views : array();

		if ( ! current_user_can( 'manage_options' ) ) {
			return $views;
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT ), self::EXPORT );

		$views['famma_export'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Exporter en CSV', 'famma-core' )
		);

		return $views;
	}

	/**
	 * Colonnes de la liste des inscrits.
	 *
	 * @param array<string, string> $columns Colonnes par défaut.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		return array(
			'cb'            => (string) ( $columns['cb'] ?? '' ),
			'title'         => __( 'Adresse e-mail', 'famma-core' ),
			'famma_nl_lang' => __( 'Langue', 'famma-core' ),
			'date'          => __( 'Inscription', 'famma-core' ),
		);
	}

	/**
	 * Valeur de la colonne « Langue ».
	 *
	 * @param string $column  Colonne.
	 * @param int    $post_id Inscrit.
	 * @return void
	 */
	public function column_value( $column, $post_id ): void {
		if ( 'famma_nl_lang' !== $column ) {
			return;
		}

		echo 'ar' === get_post_meta( (int) $post_id, '_famma_nl_lang', true )
			? esc_html__( 'Derja', 'famma-core' )
			: esc_html__( 'Français', 'famma-core' );
	}

	/**
	 * Télécharge la liste des inscrits au format CSV.
	 *
	 * @return void
	 */
	public function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'famma-core' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::EXPORT );

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=famma-newsletter-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );

		if ( false === $out ) {
			exit;
		}

		// BOM : Excel ouvre alors le fichier en UTF-8.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- flux de sortie HTTP, pas un fichier.
		fputcsv( $out, array( 'email', 'langue', 'date_inscription' ) );

		foreach ( $ids as $id ) {
			fputcsv(
				$out,
				array(
					self::csv_safe( get_the_title( $id ) ),
					'ar' === get_post_meta( $id, '_famma_nl_lang', true ) ? 'ar' : 'fr',
					get_post_time( 'Y-m-d H:i', false, $id ),
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- flux de sortie HTTP, pas un fichier.
		exit;
	}

	/**
	 * Neutralise une cellule qu'un tableur interpréterait comme une formule.
	 *
	 * @param string $value Valeur brute.
	 * @return string
	 */
	private static function csv_safe( string $value ): string {
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}
}
