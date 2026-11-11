<?php
/**
 * Nouveau look animé — réglé depuis FAMMA → Pages.
 *
 * Interrupteur unique de la couche visuelle issue des maquettes animées du
 * 04-06/11/2026 (texte marine sur orange, apparitions, vol vers le panier,
 * seconde photo au survol, transitions de page, barre du panier). Le thème ne
 * charge rien tant que le mode reste sur « Désactivé » : le site est alors
 * identique à la version précédente, octet pour octet côté visiteur.
 *
 * Trois modes, pour un déploiement sans risque :
 * - « Désactivé » (défaut) : rien n'est chargé ;
 * - « Administrateurs seulement » : le propriétaire voit le nouveau look sur
 *   le vrai site, les visiteurs non. Les pages des connectés ne sont jamais
 *   servies depuis le cache (Tracking::internal_cache_guard), le cache public
 *   reste donc celui de l'ancien look ;
 * - « Tout le monde » : bascule publique, réversible en un clic.
 *
 * Ce fichier ne porte que l'option et son écran : le dessin vit dans le thème
 * (`inc/new-look.php`), qui ne fait rien si le plugin est absent.
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
				'label' => __( 'Désactivé', 'famma-core' ),
				'help'  => __( 'Le site garde son look actuel. Aucun fichier du nouveau look n’est envoyé aux visiteurs.', 'famma-core' ),
			),
			'admins' => array(
				'label' => __( 'Administrateurs seulement (aperçu)', 'famma-core' ),
				'help'  => __( 'Vous voyez le nouveau look sur le vrai site quand vous êtes connecté ; les visiteurs voient l’ancien. Attention : le pixel Meta est coupé pour les comptes internes, les événements se vérifient donc dans une fenêtre privée, après bascule.', 'famma-core' ),
			),
			'all'    => array(
				'label' => __( 'Tout le monde', 'famma-core' ),
				'help'  => __( 'Le nouveau look est public. Revenir à « Désactivé » rétablit l’ancien look et vide le cache.', 'famma-core' ),
			),
		);
	}

	/**
	 * Briques du nouveau look, chacune désactivable seule.
	 *
	 * @return array<string, array{label: string, help: string}>
	 */
	public static function parts(): array {
		return array(
			'navy'        => array(
				'label' => __( 'Texte marine sur les boutons orange', 'famma-core' ),
				'help'  => __( 'Contraste 6,3:1 au lieu de 2,6:1 pour le blanc actuel : les boutons deviennent lisibles au soleil et conformes à l’accessibilité.', 'famma-core' ),
			),
			'motion'      => array(
				'label' => __( 'Apparitions et vol vers le panier', 'famma-core' ),
				'help'  => __( 'Les cartes apparaissent en douceur, la photo vole vers le panier à l’ajout, le cœur des favoris pétille. Désactivé d’office si le visiteur a demandé moins d’animations.', 'famma-core' ),
			),
			'alt_photo'   => array(
				'label' => __( 'Seconde photo au survol', 'famma-core' ),
				'help'  => __( 'Sur ordinateur, la deuxième photo de la galerie remplace la première au survol. Elle n’est téléchargée qu’au premier survol.', 'famma-core' ),
			),
			'transitions' => array(
				'label' => __( 'Transitions entre les pages', 'famma-core' ),
				'help'  => __( 'Fondu entre les pages de la boutique (filtres, tri, catégories), l’en-tête reste en place. Ignoré par les navigateurs qui ne le gèrent pas.', 'famma-core' ),
			),
			'tools'       => array(
				'label' => __( 'Boutique : rayons et budget en puces sur mobile', 'famma-core' ),
				'help'  => __( 'Sur téléphone, une rangée de puces reste collée sous l’en-tête : les rayons avec leur nombre de produits, puis des budgets tirés des vrais prix du catalogue. Ce sont les mêmes filtres que la colonne « Filtres », en un toucher.', 'famma-core' ),
			),
			'journey'     => array(
				'label' => __( 'Accueil : bloc « Vous payez à la livraison » en parcours animé', 'famma-core' ),
				'help'  => __( 'Le bloc déjà présent dans la page d’accueil devient une bande marine : une route relie les étapes, qui se cochent au défilement. Ses textes se modifient toujours dans l’éditeur de la page d’accueil.', 'famma-core' ),
			),
			'dock'        => array(
				'label' => __( 'Barre du panier « Aujourd’hui 0 DT »', 'famma-core' ),
				'help'  => __( 'Dans la boutique, une barre en bas d’écran rappelle qu’on ne paie rien aujourd’hui et donne le total à payer au livreur. Elle n’apparaît que si le panier contient un article.', 'famma-core' ),
			),
		);
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
	 * Enregistre l'option et son champ, sur l'écran FAMMA → Pages.
	 *
	 * La Settings API vérifie elle-même le nonce (`settings_fields`) et la
	 * capacité `manage_options` à l'envoi du formulaire (`options.php`).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			Pages_Content::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'famma_section_new_look',
			__( 'Nouveau look animé', 'famma-core' ),
			array( $this, 'section_intro' ),
			Pages_Content::SLUG
		);

		add_settings_field(
			self::OPTION,
			esc_html__( 'Mode et briques', 'famma-core' ),
			array( $this, 'render_field' ),
			Pages_Content::SLUG,
			'famma_section_new_look'
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

	/**
	 * Texte d'introduction de la section.
	 *
	 * @return void
	 */
	public function section_intro(): void {
		?>
		<p>
			<?php esc_html_e( 'Commencez par « Administrateurs seulement » : vous voyez le nouveau look sur le vrai site, les visiteurs et les campagnes ne voient aucun changement. Passez à « Tout le monde » seulement après avoir testé une commande de bout en bout.', 'famma-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Affiche le mode (boutons radio) et les briques (cases à cocher).
	 *
	 * @return void
	 */
	public function render_field(): void {
		$current = self::settings();
		$name    = self::OPTION;
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Mode du nouveau look', 'famma-core' ); ?></legend>
			<?php foreach ( self::modes() as $mode => $meta ) : ?>
				<p>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $name . '[mode]' ); ?>"
							value="<?php echo esc_attr( $mode ); ?>"
							aria-describedby="<?php echo esc_attr( $name . '_' . $mode . '_help' ); ?>"
							<?php checked( $current['mode'], $mode ); ?>
						/>
						<strong><?php echo esc_html( $meta['label'] ); ?></strong>
					</label>
					<br />
					<span class="description" id="<?php echo esc_attr( $name . '_' . $mode . '_help' ); ?>"><?php echo esc_html( $meta['help'] ); ?></span>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<fieldset>
			<legend><strong><?php esc_html_e( 'Briques servies quand le nouveau look est actif', 'famma-core' ); ?></strong></legend>
			<?php foreach ( self::parts() as $part => $meta ) : ?>
				<p>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $name . '[parts][' . $part . ']' ); ?>"
							value="1"
							aria-describedby="<?php echo esc_attr( $name . '_' . $part . '_help' ); ?>"
							<?php checked( ! empty( $current['parts'][ $part ] ) ); ?>
						/>
						<?php echo esc_html( $meta['label'] ); ?>
					</label>
					<br />
					<span class="description" id="<?php echo esc_attr( $name . '_' . $part . '_help' ); ?>"><?php echo esc_html( $meta['help'] ); ?></span>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}
}
