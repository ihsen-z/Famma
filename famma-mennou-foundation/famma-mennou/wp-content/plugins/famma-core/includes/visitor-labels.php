<?php
/**
 * Parcours visiteurs — libellés lisibles.
 *
 * Un seul dictionnaire pour l'écran, l'encadré de la commande et l'export des
 * données personnelles : un événement, un champ ou une cause de refus porte
 * partout le même nom. Avant la 0.3.49, ces listes vivaient dans les classes
 * d'affichage et les autres modules devaient les y chercher.
 *
 * @package famma-core
 */

namespace Famma\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Libellés des événements, champs, causes de refus, pages et appareils.
 */
final class Visitor_Labels {

	/**
	 * Événements : libellé, icône Dashicons et famille (couleur de la pastille).
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function event_meta(): array {
		return array(
			'page_view'         => array( __( 'Page vue', 'famma-core' ), 'visibility', 'nav' ),
			'page_leave'        => array( __( 'Sortie de page', 'famma-core' ), 'exit', 'nav' ),
			'add_to_cart'       => array( __( 'Ajout au panier', 'famma-core' ), 'cart', 'buy' ),
			'form_start'        => array( __( 'Formulaire commencé', 'famma-core' ), 'edit', 'form' ),
			'form_field'        => array( __( 'Champ touché', 'famma-core' ), 'edit-large', 'form' ),
			'form_submit'       => array( __( 'Formulaire envoyé', 'famma-core' ), 'yes', 'form' ),
			'form_error'        => array( __( 'Envoi refusé', 'famma-core' ), 'warning', 'error' ),
			'order_placed'      => array( __( 'Commande passée', 'famma-core' ), 'yes-alt', 'buy' ),
			'click_whatsapp'    => array( __( 'Clic WhatsApp', 'famma-core' ), 'whatsapp', 'contact' ),
			'click_phone'       => array( __( 'Clic téléphone', 'famma-core' ), 'phone', 'contact' ),
			'click_outbound'    => array( __( 'Lien sortant', 'famma-core' ), 'external', 'nav' ),
			'product_media'     => array( __( 'Média produit', 'famma-core' ), 'format-gallery', 'product' ),
			'product_variation' => array( __( 'Variation choisie', 'famma-core' ), 'tag', 'product' ),
			'web_vitals'        => array( __( 'Temps de chargement', 'famma-core' ), 'performance', 'tech' ),
			'js_error'          => array( __( 'Erreur JavaScript', 'famma-core' ), 'dismiss', 'error' ),
		);
	}

	/**
	 * Libellé d'un événement.
	 *
	 * @param string $event Nom technique.
	 * @return string
	 */
	public static function event( string $event ): string {
		return self::event_meta()[ $event ][0] ?? $event;
	}

	/**
	 * Libellés des événements, par nom technique.
	 *
	 * @return array<string, string>
	 */
	public static function events(): array {
		return array_map( static fn( array $meta ): string => $meta[0], self::event_meta() );
	}

	/**
	 * Libellé d'un champ de formulaire connu.
	 *
	 * @param string $name Nom technique du champ.
	 * @return string
	 */
	public static function field( string $name ): string {
		$labels = array(
			'billing_first_name' => __( 'Nom complet / prénom', 'famma-core' ),
			'billing_last_name'  => __( 'Nom', 'famma-core' ),
			'billing_phone'      => __( 'Téléphone', 'famma-core' ),
			'billing_state'      => __( 'Gouvernorat', 'famma-core' ),
			'billing_city'       => __( 'Ville', 'famma-core' ),
			'billing_address_1'  => __( 'Adresse', 'famma-core' ),
			'billing_email'      => __( 'E-mail', 'famma-core' ),
			'order_comments'     => __( 'Note de commande', 'famma-core' ),
			'famma_quantity'     => __( 'Quantité', 'famma-core' ),
			'famma_size'         => __( 'Taille', 'famma-core' ),
		);
		return $labels[ $name ] ?? $name;
	}

	/**
	 * Causes lisibles d'un envoi refusé (`form_error`).
	 *
	 * @param string $codes Codes séparés par des virgules, déjà filtrés à la collecte.
	 * @return string
	 */
	public static function errors( string $codes ): string {
		$labels = array(
			'phone'   => __( 'Téléphone', 'famma-core' ),
			'name'    => __( 'Nom', 'famma-core' ),
			'state'   => __( 'Gouvernorat', 'famma-core' ),
			'size'    => __( 'Taille', 'famma-core' ),
			'form'    => __( 'Contrôle du navigateur', 'famma-core' ),
			'storage' => __( 'Stockage du navigateur indisponible', 'famma-core' ),
			'network' => __( 'Réseau coupé', 'famma-core' ),
			'server'  => __( 'Refus du serveur', 'famma-core' ),
			'changed' => __( 'Panier ou prix changé', 'famma-core' ),
		);
		$out    = array();
		foreach ( explode( ',', $codes ) as $code ) {
			if ( '' !== $code ) {
				$out[] = $labels[ $code ] ?? $code;
			}
		}
		return implode( ' · ', $out );
	}

	/**
	 * Types de page.
	 *
	 * @return array<string, string>
	 */
	public static function page_types(): array {
		return array(
			'home'     => __( 'Accueil', 'famma-core' ),
			'shop'     => __( 'Boutique', 'famma-core' ),
			'product'  => __( 'Fiche produit', 'famma-core' ),
			'cart'     => __( 'Panier', 'famma-core' ),
			'checkout' => __( 'Commande', 'famma-core' ),
			'thankyou' => __( 'Remerciement', 'famma-core' ),
			'search'   => __( 'Recherche', 'famma-core' ),
			'page'     => __( 'Page', 'famma-core' ),
			'other'    => __( 'Autre', 'famma-core' ),
		);
	}

	/**
	 * Familles d'appareils.
	 *
	 * @return array<string, string>
	 */
	public static function devices(): array {
		return array(
			'mobile'  => __( 'Mobile', 'famma-core' ),
			'desktop' => __( 'Ordinateur', 'famma-core' ),
			'tablet'  => __( 'Tablette', 'famma-core' ),
		);
	}

	/**
	 * Libellé d'un appareil.
	 *
	 * @param string $device Nom technique.
	 * @return string
	 */
	public static function device( string $device ): string {
		return self::devices()[ $device ] ?? $device;
	}
}
