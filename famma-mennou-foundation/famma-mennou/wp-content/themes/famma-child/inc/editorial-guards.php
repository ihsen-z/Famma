<?php
/**
 * Garde-fous éditoriaux.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retire les encarts « à compléter » du contenu servi aux visiteurs.
 *
 * Ces encarts (`.famma-todo`) sont des notes de chantier : ils nomment ce que
 * le propriétaire doit encore arrêter — transporteur retenu, délais par
 * gouvernorat, conditions de retour. Le style les rend volontairement voyants
 * pour qu'on ne les oublie pas, mais rien n'empêchait qu'ils atteignent le
 * client : la page `/livraison/`, la page `/faq/` et l'onglet « Livraison » de
 * la fiche produit les affichaient tels quels.
 *
 * Ce n'est pas qu'une question de crédibilité. En paiement à la livraison, le
 * client lit la page Livraison précisément pour savoir quand et comment il
 * recevra son colis ; y trouver « à compléter par le propriétaire » est un
 * motif d'abandon, et plus tard un motif de refus à la porte.
 *
 * Le retrait est fait côté serveur et non en CSS : un `display: none`
 * laisserait le texte dans la source, donc lisible par un lecteur d'écran et
 * indexable par un moteur. Les personnes qui peuvent éditer le contenu
 * continuent de voir la note — c'est pour elles qu'elle existe.
 *
 * @param string $content Contenu déjà filtré par le cœur.
 * @return string
 */
function famma_child_strip_todo_notes( string $content ): string {
	if ( current_user_can( 'edit_posts' ) ) {
		return $content;
	}

	if ( false === strpos( $content, 'famma-todo' ) ) {
		return $content;
	}

	/*
	 * Le marqueur est écrit comme un paragraphe plat dans les pages livrées.
	 * `div` est accepté par tolérance ; un encart contenant lui-même un bloc
	 * de même nom ne serait pas retiré proprement, mais ce cas n'existe pas
	 * et le style resterait voyant pour le signaler.
	 */
	return (string) preg_replace(
		'#<(p|div)[^>]*class="[^"]*\bfamma-todo\b[^"]*"[^>]*>.*?</\1>\s*#is',
		'',
		$content
	);
}
add_filter( 'the_content', 'famma_child_strip_todo_notes', 20 );
