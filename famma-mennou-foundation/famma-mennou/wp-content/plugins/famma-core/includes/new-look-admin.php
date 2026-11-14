<?php
/**
 * Écran FAMMA → Nouveau look : menu, feuilles et enregistrement.
 *
 * Rassemble le mode, les briques et leurs textes FR + derja, rangés par page.
 * Rien n'est chargé côté visiteur : la classe n'est instanciée que dans
 * l'administration, et sa feuille et son script seulement sur cet écran.
 *
 * Le rendu vit dans `New_Look_Admin_View` / `New_Look_Admin_Fields`, l'état
 * réel de chaque brique dans `New_Look_Admin_State`, l'enregistrement dans
 * `New_Look_Admin_Save`.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'entrée de l'écran « Nouveau look ».
 */
final class New_Look_Admin {

	/**
	 * Identifiant de l'écran.
	 */
	public const SLUG = 'famma-new-look';

	/**
	 * Action d'`admin-post.php` et du jeton de sécurité.
	 */
	public const ACTION = 'famma_new_look_save';

	/**
	 * Capacité requise pour voir l'écran et enregistrer.
	 */
	public const CAP = 'manage_woocommerce';

	/**
	 * Instance unique.
	 *
	 * @var New_Look_Admin|null
	 */
	private static ?New_Look_Admin $i = null;

	/**
	 * Identifiant WordPress de l'écran, une fois le menu posé.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Retourne l'instance partagée, en enregistrant les hooks au premier appel.
	 *
	 * @return New_Look_Admin
	 */
	public static function instance(): New_Look_Admin {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_' . self::ACTION, array( New_Look_Admin_Save::class, 'handle' ) );
	}

	/**
	 * Ajoute l'écran sous le menu FAMMA, juste après le tableau de bord.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->hook = (string) add_submenu_page(
			'famma-dashboard',
			__( 'FAMMA — nouveau look', 'famma-core' ),
			__( 'Nouveau look', 'famma-core' ),
			self::CAP,
			self::SLUG,
			array( New_Look_Admin_View::class, 'render' ),
			1
		);
	}

	/**
	 * Charge la feuille et le script, sur cet écran seulement.
	 *
	 * @param string $hook Identifiant de l'écran courant.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( 'famma-new-look-admin', plugins_url( 'assets/css/new-look-admin.css', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION );
		wp_enqueue_script( 'famma-new-look-admin', plugins_url( 'assets/js/new-look-admin.js', FAMMA_CORE_FILE ), array(), FAMMA_CORE_VERSION, true );
		wp_localize_script( 'famma-new-look-admin', 'fammaNewLook', New_Look_Admin_State::script_strings() );
	}

	/**
	 * Adresse de l'écran, avec des paramètres éventuels.
	 *
	 * @param array<string, string> $args Paramètres de requête.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}
}
