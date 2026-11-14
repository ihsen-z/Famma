<?php
/**
 * FAMMA — jeu d'icônes en ligne.
 *
 * Les tracés viennent de la maquette (`docs/design-refs/`), style ligne,
 * bouts arrondis. Ils sont rendus en ligne plutôt que par un sprite ou une
 * police d'icônes : une icône d'en-tête ne doit pas attendre une requête
 * réseau, et `currentColor` la fait suivre la couleur du texte — donc le
 * contraste, donc le thème sombre du pied de page, sans une seule règle
 * de couleur en double.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renvoie les tracés SVG d'une icône, sans l'enveloppe `<svg>`.
 *
 * Table fermée : aucune entrée ne vient d'une saisie utilisateur, donc rien
 * à assainir ici. C'est `famma_child_icon()` qui construit le balisage.
 *
 * @return array<string, string>
 */
function famma_child_icon_paths(): array {
	return array(
		// Barre supérieure et réassurances.
		'truck'     => '<path d="M1 3h13v13H1z"/><path d="M14 8h4l3 3v5h-7z"/><circle cx="6" cy="19" r="2"/><circle cx="17" cy="19" r="2"/>',
		'card'      => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
		'chat'      => '<path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.4L3 21l2.2-5.5A8.4 8.4 0 1 1 21 11.5z"/>',
		// Etiquette de prix : la promesse de livraison offerte de la barre haute.
		'tag'       => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0l-7.2-7.2a2 2 0 0 1-.6-1.4V4a1 1 0 0 1 1-1h8a2 2 0 0 1 1.4.6l7.4 7.4a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',

		// En-tête.
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
		'cart'      => '<path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6"/><circle cx="10" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/>',
		'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close'     => '<path d="M6 6l12 12M18 6L6 18"/>',
		'chevron'   => '<path d="M6 9l6 6 6-6"/>',

		// Pied de page — aide et support.
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',

		/*
		 * Satisfait ou remboursé : un bouclier, pas un billet. La garantie
		 * porte sur la protection de l'acheteur ; une icône d'argent dirait
		 * « payez ici », l'inverse du message.
		 */
		'shield'    => '<path d="M12 3l8 3v6c0 4.5-3.2 7.9-8 9-4.8-1.1-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-4.5"/>',
		'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
		'mail'      => '<path d="M3 5h18v14H3zM3 6l9 7 9-7"/>',
		'pin'       => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>',

		// Panier et commande.
		'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
		// Suivi de commande dans « Mon compte » : reçue, puis livrée.
		'box'       => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
		'home'      => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
		// Lot 46 : situations du récit (maquette fiche, 11/11).
		'monitor'   => '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M9 20h6M12 16.5V20"/>',
		'mobile'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 18.5h2"/>',
		'car'       => '<path d="M4 16v-3.5l2-5.5a2 2 0 0 1 1.9-1.3h8.2A2 2 0 0 1 18 7l2 5.5V16"/><path d="M3 12.5h18v4H3z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
		'book'      => '<path d="M12 6.5C10 5 7.5 4.5 3.5 4.5v14c4 0 6.5.5 8.5 2 2-1.5 4.5-2 8.5-2v-14c-4 0-6.5.5-8.5 2zM12 6.5v14"/>',
		// Carte « Votre espace FAMMA » : « Retrouvez votre sélection ».
		'heart'     => '<path d="M12 20.5s-7.5-4.6-9.3-9.2A5.2 5.2 0 0 1 12 7a5.2 5.2 0 0 1 9.3 4.3C19.5 15.9 12 20.5 12 20.5z"/>',
		// « Me déconnecter », en bas de « Mes informations ».
		'logout'    => '<path d="M14.5 4.5h4a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
		// Cartes de commande : « Recommander » et « Une question » (WhatsApp).
		'redo'      => '<path d="M20 8.5A8.5 8.5 0 1 0 20.5 14"/><path d="M20.5 3.5v5h-5"/>',
		'whatsapp'  => '<path fill="currentColor" stroke="none" d="M12 2.5a9.5 9.5 0 0 0-8.2 14.3L2.5 21.5l4.8-1.3A9.5 9.5 0 1 0 12 2.5zm0 1.8a7.7 7.7 0 1 1-3.9 14.3l-.3-.2-2.8.8.8-2.7-.2-.3A7.7 7.7 0 0 1 12 4.3zM8.7 7.8c-.2 0-.5.1-.7.4-.3.3-1 1-1 2.3s1 2.7 1.1 2.8c.1.2 1.9 3 4.7 4.1 2.3.9 2.8.7 3.3.7.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.2.2-1.3-.1-.1-.3-.2-.6-.3l-1.9-.9c-.3-.1-.5-.1-.7.1l-.8 1.1c-.2.2-.3.2-.6.1-.3-.2-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.5-.5.3-.5c.1-.2 0-.4 0-.5l-.9-2c-.2-.5-.4-.5-.6-.5h-.4z"/>',
		// Encadré d'information (pages Livraison et Conditions générales).
		'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'trash'     => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
		'forward'   => '<path d="M9 6l6 6-6 6"/>',
		'return'    => '<path d="M9 14l-4-4 4-4"/><path d="M5 10h9a5 5 0 0 1 0 10h-3"/>',
		'back'      => '<path d="M15 6l-6 6 6 6"/>',

		// Accueil — newsletter et témoignages.
		'gift'      => '<rect x="3" y="9" width="18" height="12" rx="1.5"/><path d="M2 9h20v4H2zM12 9v12"/><path d="M12 9C10 9 7 8.4 7 6.2A2.2 2.2 0 0 1 12 5.6 2.2 2.2 0 0 1 17 6.2C17 8.4 14 9 12 9z"/>',
		'quote'     => '<path d="M9 7H6a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3v1a3 3 0 0 1-3 3M20 7h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3v1a3 3 0 0 1-3 3"/>',

		// Pied de page — réseaux sociaux.
		'facebook'  => '<path d="M14 8h3V5h-3a4 4 0 0 0-4 4v2H8v3h2v7h3v-7h3l1-3h-4V9a1 1 0 0 1 1-1z"/>',
		'instagram' => '<path d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.5 6.5h.01"/>',
		'tiktok'    => '<path d="M15 3v10.5a3.5 3.5 0 1 1-3-3.46M15 3c.4 2.2 2 3.9 4.5 4.2"/>',
		'youtube'   => '<path d="M2 8.5A3.5 3.5 0 0 1 5.5 5h13A3.5 3.5 0 0 1 22 8.5v7a3.5 3.5 0 0 1-3.5 3.5h-13A3.5 3.5 0 0 1 2 15.5zM10 9.5l5 2.5-5 2.5z"/>',
	);
}

/**
 * Construit une icône en ligne.
 *
 * L'icône est toujours décorative : le libellé lisible est porté par le texte
 * voisin, ou par un `aria-label` sur l'élément interactif parent. D'où
 * `aria-hidden="true"` et `focusable="false"` systématiques — sans quoi
 * Internet Explorer et certains lecteurs d'écran annoncent un objet vide.
 *
 * @param string $name  Clé dans famma_child_icon_paths().
 * @param string $extra Classes additionnelles.
 * @return string Balisage SVG, ou chaîne vide si l'icône n'existe pas.
 */
function famma_child_icon( string $name, string $extra = '' ): string {
	$paths = famma_child_icon_paths();

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$class = 'famma-icon famma-icon--' . $name;
	if ( '' !== $extra ) {
		$class .= ' ' . $extra;
	}

	return sprintf(
		'<svg class="%1$s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		esc_attr( $class ),
		$paths[ $name ]
	);
}

/**
 * Liste blanche d'échappement pour un SVG en ligne.
 *
 * `wp_kses()` supprime les balises SVG par défaut. Plutôt que d'échapper au
 * filtre avec un `echo` brut, on lui décrit exactement ce qui est permis :
 * la sortie reste filtrée, et une balise inattendue serait retirée même si
 * elle venait à se glisser dans la table des tracés.
 *
 * @return array<string, array<string, bool>>
 */
function famma_child_svg_allowed_html(): array {
	return array(
		'svg'    => array(
			'class'           => true,
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'aria-label'      => true,
			'focusable'       => true,
			'role'            => true,
			'xmlns'           => true,
		),
		'path'   => array(
			'd'               => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		),
		'circle' => array(
			'cx'           => true,
			'cy'           => true,
			'r'            => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		),
		'rect'   => array(
			'x'            => true,
			'y'            => true,
			'width'        => true,
			'height'       => true,
			'rx'           => true,
			'fill'         => true,
			'stroke'       => true,
			'stroke-width' => true,
		),
		'g'      => array(
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
		),
		'span'   => array(
			'class' => true,
			'lang'  => true,
			'dir'   => true,
		),
	);
}

/**
 * Affiche une icône déjà filtrée.
 *
 * @param string $name  Clé de l'icône.
 * @param string $extra Classes additionnelles.
 * @return void
 */
function famma_child_the_icon( string $name, string $extra = '' ): void {
	echo wp_kses( famma_child_icon( $name, $extra ), famma_child_svg_allowed_html() );
}
