<?php
/**
 * Nouveau look animé — réglé depuis FAMMA → Nouveau look.
 *
 * Interrupteur unique de la couche visuelle issue des maquettes animées du
 * 04-06/11/2026 (texte marine sur orange, apparitions, vol vers le panier,
 * seconde photo au survol, transitions de page, barre du panier). Le thème ne
 * charge rien tant que le mode reste sur « Personne » (off) : le site est alors
 * identique à la version précédente, octet pour octet côté visiteur.
 *
 * Trois modes, pour un déploiement sans risque :
 * - « Personne » (`off`, défaut) : rien n'est chargé ;
 * - « Seulement les administrateurs » : le propriétaire voit le nouveau look sur
 *   le vrai site, les visiteurs non. Les pages des connectés ne sont jamais
 *   servies depuis le cache (Tracking::internal_cache_guard), le cache public
 *   reste donc celui de l'ancien look ;
 * - « Tout le monde » : bascule publique, réversible en un clic.
 *
 * Ce fichier ne porte que l'option : la liste des briques vit dans le registre
 * (`new-look-registry.php`), l'écran dans `new-look-admin*.php`, le dessin dans
 * le thème (`inc/new-look.php`), qui ne fait rien si le plugin est absent.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Option « nouveau look animé ».
 */
final class New_Look {

	/**
	 * Option abritant le mode et les briques retenues.
	 */
	public const OPTION = 'famma_new_look';

	/**
	 * Groupe de réglages de l'option et des textes rangés sur l'écran
	 * FAMMA → Nouveau look.
	 */
	public const GROUP = 'famma_new_look_group';

	/**
	 * Mode servi tant que le propriétaire n'a rien choisi : rien ne change.
	 */
	public const DEFAULT_MODE = 'off';

	/**
	 * Capacité qui donne accès à l'aperçu « administrateurs seulement ».
	 */
	public const PREVIEW_CAP = 'manage_woocommerce';

	/**
	 * Instance unique.
	 *
	 * @var New_Look|null
	 */
	private static ?New_Look $i = null;

	/**
	 * Valeurs déjà résolues pour la requête en cours.
	 *
	 * @var array{mode: string, parts: array<string, bool>}|null
	 */
	private static ?array $cache = null;

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return New_Look
	 */
	public static function instance(): New_Look {
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
		add_action( 'update_option_' . self::OPTION, array( $this, 'flush_page_cache' ) );
		add_action( 'add_option_' . self::OPTION, array( $this, 'flush_page_cache' ) );
	}

	/**
	 * Modes proposés, dans leur ordre d'affichage.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function modes(): array {
		return array(
			'off'    => array(
				'label' => __( 'Personne', 'famma-core' ),
				'help'  => __( 'Personne ne voit le nouveau look : le site garde son look actuel, aucun fichier du nouveau look n’est envoyé aux visiteurs.', 'famma-core' ),
			),
			'admins' => array(
				'label' => __( 'Seulement les administrateurs', 'famma-core' ),
				'help'  => __( 'Vous voyez le nouveau look sur le vrai site quand vous êtes connecté ; les visiteurs voient l’ancien. Attention : le pixel Meta est coupé pour les comptes internes, les événements se vérifient donc dans une fenêtre privée, après bascule.', 'famma-core' ),
			),
			'all'    => array(
				'label' => __( 'Tout le monde', 'famma-core' ),
				'help'  => __( 'Le nouveau look est public. Revenir à « Personne » rétablit l’ancien look et vide le cache.', 'famma-core' ),
			),
		);
	}

	/**
	 * Briques du nouveau look, chacune désactivable seule.
	 *
	 * Tirées du registre (`New_Look_Registry`) : ajouter une brique = une
	 * entrée dans le fichier de sa page.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function parts(): array {
		$parts = array();

		foreach ( New_Look_Registry::bricks() as $key => $brick ) {
			$parts[ $key ] = array(
				'label' => $brick['title'],
				'help'  => $brick['help'],
			);
		}

		return $parts;
	}

	/**
	 * Réglages enregistrés, revalidés.
	 *
	 * @return array{mode: string, parts: array<string, bool>}
	 */
	public static function settings(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw = get_option( self::OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();

		$mode = isset( $raw['mode'] ) ? (string) $raw['mode'] : self::DEFAULT_MODE;
		if ( ! array_key_exists( $mode, self::modes() ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$saved = isset( $raw['parts'] ) && is_array( $raw['parts'] ) ? $raw['parts'] : null;
		$parts = array();
		foreach ( array_keys( self::parts() ) as $part ) {
			// Jamais enregistré : chaque brique est prête, le mode décide seul.
			$parts[ $part ] = null === $saved ? true : ! empty( $saved[ $part ] );
		}

		self::$cache = array(
			'mode'  => $mode,
			'parts' => $parts,
		);

		return self::$cache;
	}

	/**
	 * Le nouveau look s'applique-t-il à la requête en cours ?
	 *
	 * @return bool
	 */
	public static function active(): bool {
		$mode = self::settings()['mode'];

		if ( 'all' === $mode ) {
			return true;
		}

		return 'admins' === $mode && is_user_logged_in() && current_user_can( self::PREVIEW_CAP );
	}

	/**
	 * Une brique précise est-elle servie à la requête en cours ?
	 *
	 * @param string $part Clé de `parts()`.
	 * @return bool
	 */
	public static function part( string $part ): bool {
		$parts = self::settings()['parts'];

		return self::active() && ! empty( $parts[ $part ] );
	}

	/**
	 * Enregistre l'option, pour son assainissement.
	 *
	 * Elle a son propre groupe : l'écran FAMMA → Pages ne l'envoie plus, et ne
	 * peut donc plus l'écraser. L'écran FAMMA → Nouveau look l'enregistre par
	 * `update_option()`, qui passe par `sanitize()` ci-dessous.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * N'accepte qu'un mode connu et des briques connues.
	 *
	 * @param mixed $value Valeur soumise.
	 * @return array{mode: string, parts: array<string, bool>}
	 */
	public function sanitize( $value ): array {
		$value = is_array( $value ) ? wp_unslash( $value ) : array();

		$mode = isset( $value['mode'] ) ? sanitize_key( (string) $value['mode'] ) : self::DEFAULT_MODE;
		if ( ! array_key_exists( $mode, self::modes() ) ) {
			$mode = self::DEFAULT_MODE;
		}

		$sent  = isset( $value['parts'] ) && is_array( $value['parts'] ) ? $value['parts'] : array();
		$parts = array();
		foreach ( array_keys( self::parts() ) as $part ) {
			$parts[ $part ] = ! empty( $sent[ $part ] );
		}

		self::$cache = null;

		return array(
			'mode'  => $mode,
			'parts' => $parts,
		);
	}

	/**
	 * Vide tout le cache de page après un changement.
	 *
	 * Le nouveau look touche toutes les pages (couleur des boutons) : purger
	 * une seule adresse laisserait l'ancien look sur les autres.
	 *
	 * @return void
	 */
	public function flush_page_cache(): void {
		self::$cache = null;
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API publique de LiteSpeed Cache, sans effet si le plugin est absent.
	}
}
