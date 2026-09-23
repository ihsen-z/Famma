<?php
/**
 * FAMMA Core — vidéo publicitaire de la fiche produit.
 *
 * Trois champs éditables par le propriétaire sur l'écran produit : l'adresse
 * de la vidéo, un titre et une description. Rien n'est écrit en dur dans le
 * thème : tant que l'adresse est vide, la section n'existe pas sur le front
 * (§60 — aucun contenu inventé, aucun bloc vide).
 *
 * Le rendu vit dans le thème (`inc/product-video.php`) ; ce fichier ne fait
 * que stocker et relire la donnée, plus fabriquer le lecteur (règle 6 : la
 * logique métier reste dans le plugin, la présentation dans le thème).
 *
 * @package Famma\Core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Champs et lecteur de la vidéo publicitaire produit.
 */
final class Product_Video {

	/**
	 * Meta key holding the video address.
	 *
	 * @var string
	 */
	public const META_URL = '_famma_video_url';

	/**
	 * Meta key holding the section title.
	 *
	 * @var string
	 */
	public const META_TITLE = '_famma_video_title';

	/**
	 * Meta key holding the section description.
	 *
	 * @var string
	 */
	public const META_DESC = '_famma_video_description';

	/**
	 * File extensions rendered with a native `<video>` element.
	 *
	 * @var array<int, string>
	 */
	private const FILE_EXTENSIONS = array( 'mp4', 'm4v', 'webm', 'ogv', 'mov' );

	/**
	 * Singleton instance.
	 *
	 * @var Product_Video|null
	 */
	private static ?Product_Video $i = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Product_Video
	 */
	public static function instance(): Product_Video {
		if ( null === self::$i ) {
			self::$i = new self();
			self::$i->hooks();
		}
		return self::$i;
	}

	/**
	 * Registers hooks.
	 *
	 * Les champs n'existent que dans l'administration (règle 10) ; le front
	 * n'a besoin que des lecteurs `data()` / `player()`, appelés par le thème.
	 *
	 * @return void
	 */
	private function hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'woocommerce_product_options_advanced', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
	}

	/**
	 * Renders the three fields on the product screen.
	 *
	 * @return void
	 */
	public function render_fields(): void {
		global $product_object;

		$url   = '';
		$title = '';
		$desc  = '';

		if ( $product_object instanceof \WC_Product ) {
			$url   = (string) $product_object->get_meta( self::META_URL );
			$title = (string) $product_object->get_meta( self::META_TITLE );
			$desc  = (string) $product_object->get_meta( self::META_DESC );
		}

		echo '<div class="options_group">';

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_URL,
				'value'       => $url,
				'type'        => 'url',
				'label'       => __( 'Advertising video', 'famma-core' ),
				'placeholder' => 'https://',
				'desc_tip'    => false,
				'description' => __( 'Address of a YouTube, Vimeo or Facebook video, or of a video file uploaded to the media library (.mp4, .webm). Leave empty to hide the video section on the product page.', 'famma-core' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_TITLE,
				'value'       => $title,
				'label'       => __( 'Video section title', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'Heading shown above the video. Leave empty to display the video without a heading.', 'famma-core' ),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META_DESC,
				'value'       => $desc,
				'label'       => __( 'Video section description', 'famma-core' ),
				'desc_tip'    => false,
				'description' => __( 'Short text shown next to the video. Leave empty to display the video alone.', 'famma-core' ),
				'rows'        => 5,
			)
		);

		echo '</div>';
	}

	/**
	 * Persists the three fields.
	 *
	 * Séquence complète de la règle 4 — nonce, capacité, assainissement —
	 * refaite sur place comme dans `Product_Faq`.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_fields( \WC_Product $product ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] )
			? sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		if ( isset( $_POST[ self::META_URL ] ) ) {
			$raw = trim( sanitize_text_field( wp_unslash( $_POST[ self::META_URL ] ) ) );
			$url = '' === $raw ? '' : esc_url_raw( $raw, array( 'http', 'https' ) );

			$product->update_meta_data( self::META_URL, $url );
		}

		if ( isset( $_POST[ self::META_TITLE ] ) ) {
			$product->update_meta_data(
				self::META_TITLE,
				sanitize_text_field( wp_unslash( $_POST[ self::META_TITLE ] ) )
			);
		}

		if ( isset( $_POST[ self::META_DESC ] ) ) {
			$product->update_meta_data(
				self::META_DESC,
				sanitize_textarea_field( wp_unslash( $_POST[ self::META_DESC ] ) )
			);
		}
	}

	/**
	 * Returns the stored video data, or an empty array when there is none.
	 *
	 * Le titre et la description ne suffisent jamais à eux seuls : sans
	 * adresse de vidéo, il n'y a pas de section à afficher.
	 *
	 * @param \WC_Product $product Product to read.
	 * @return array{url: string, title: string, description: string}|array{}
	 */
	public function data( \WC_Product $product ): array {
		$url = trim( (string) $product->get_meta( self::META_URL ) );

		if ( '' === $url ) {
			return array();
		}

		return array(
			'url'         => $url,
			'title'       => trim( (string) $product->get_meta( self::META_TITLE ) ),
			'description' => trim( (string) $product->get_meta( self::META_DESC ) ),
		);
	}

	/**
	 * Builds the player markup for a video address.
	 *
	 * Un fichier téléversé est lu par `<video>` : pas de requête vers un tiers,
	 * pas de cookie posé avant que le client ne clique. Une adresse de
	 * plateforme passe par l'oEmbed de WordPress, qui connaît les fournisseurs
	 * autorisés — jamais une `<iframe>` fabriquée à la main à partir d'une
	 * adresse saisie par un humain.
	 *
	 * @param string $url Stored video address.
	 * @return string Player markup, or an empty string when the address is unusable.
	 */
	public function player( string $url ): string {
		$url = esc_url_raw( $url, array( 'http', 'https' ) );

		if ( '' === $url ) {
			return '';
		}

		$path      = (string) wp_parse_url( $url, PHP_URL_PATH );
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, self::FILE_EXTENSIONS, true ) ) {
			return sprintf(
				'<video class="famma-product-video__player" controls preload="metadata" playsinline src="%s"></video>',
				esc_url( $url )
			);
		}

		/*
		 * `wp_oembed_get()` interroge le fournisseur (YouTube, Vimeo…) à CHAQUE
		 * appel, sans cache : chaque affichage de la fiche — la page
		 * d'atterrissage des pubs — attendait un aller-retour réseau (audit du
		 * 23/09, CODE-07). Le rendu est gardé une journée par adresse ; un échec
		 * est gardé une heure, pour ne pas marteler un fournisseur indisponible.
		 */
		$key   = 'famma_oembed_' . md5( $url );
		$embed = get_transient( $key );

		if ( false === $embed ) {
			$embed = wp_oembed_get( $url );
			$embed = is_string( $embed ) ? $embed : '';
			set_transient( $key, $embed, '' === $embed ? HOUR_IN_SECONDS : DAY_IN_SECONDS );
		}

		return (string) $embed;
	}
}
