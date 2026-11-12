<?php
/**
 * Accueil animé (brique home_motion) : le balisage que la feuille et le
 * script ne peuvent pas créer seuls.
 *
 * - bouton pause et minuteur du carrousel du héros (hero.js les pilote) ;
 * - mascotte de l'infolettre à la place du cadeau ;
 * - deux bandes de gouvernorats sous les étapes du parcours.
 *
 * Tout le reste (barre d'avancement, entrée du héros, apparitions, colis)
 * vit dans assets/css/new-look-home.css et assets/js/new-look.js. Case
 * décochée : aucune de ces fonctions n'ajoute un octet à la page.
 *
 * @package FammaChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * La brique est-elle servie sur cette page ?
 *
 * @return bool
 */
function famma_child_home_motion(): bool {
	return function_exists( 'famma_child_new_look_parts' ) && in_array( 'home_motion', famma_child_new_look_parts(), true );
}

/**
 * Bouton pause et minuteur sous la pile du héros.
 *
 * Le minuteur rythme la pile (hero.js) : il se fige au survol, au clavier
 * et sur pause. Masqué par le script si le visiteur demande moins de
 * mouvement — le carrousel ne tourne alors plus seul.
 *
 * @return void
 */
function famma_child_home_hero_timer(): void {
	if ( ! famma_child_home_motion() ) {
		return;
	}
	?>
	<div class="famma-hero2__timer">
		<button type="button" class="famma-hero2__pause" data-famma-hero-pause aria-pressed="false" aria-label="<?php esc_attr_e( 'Mettre le défilement en pause', 'famma-child' ); ?>">
			<svg class="famma-icon famma-hero2__pause-i" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="6.5" y="5" width="3.5" height="14" rx="1.2"/><rect x="14" y="5" width="3.5" height="14" rx="1.2"/></svg>
			<svg class="famma-icon famma-hero2__play-i" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
		</button>
		<span class="famma-hero2__rail" data-famma-hero-rail aria-hidden="true"></span>
	</div>
	<?php
}

/**
 * Icône de l'infolettre : la mascotte, qui fait un clin d'œil à l'arrivée.
 *
 * @return bool Vrai si la mascotte a été affichée (le cadeau est alors omis).
 */
function famma_child_home_newsletter_mascot(): bool {
	if ( ! famma_child_home_motion() || ! function_exists( 'famma_child_mascot_svg' ) ) {
		return false;
	}

	echo famma_child_mascot_svg( 'famma-newsletter__icon famma-nl-mascot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG fixe, classe échappée par famma_child_mascot_svg().
	return true;
}

/**
 * Bandes des gouvernorats, ajoutées à la fin du bloc « Vous payez à la livraison ».
 *
 * Les noms sont ceux que WooCommerce propose au tunnel (famma-core les
 * fournit) : aucune liste recopiée ici. Décoratives (`aria-hidden`) : la
 * même information est écrite dans les questions fréquentes.
 *
 * @param string               $content Rendu du bloc groupe.
 * @param array<string, mixed> $block   Bloc analysé.
 * @return string
 */
function famma_child_home_govs( string $content, array $block ): string {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	if ( ! preg_match( '/(^|\s)famma-how(\s|$)/', $class ) || ! is_front_page() || ! famma_child_home_motion() || ! famma_child_new_look( 'journey' ) ) {
		return $content;
	}

	$states = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( 'TN' ) : array();
	$names  = is_array( $states ) ? array_values( array_filter( array_map( 'strval', $states ) ) ) : array();
	$end    = strrpos( $content, '</' );

	if ( count( $names ) < 2 || false === $end ) {
		return $content;
	}

	$rows = array_chunk( $names, (int) ceil( count( $names ) / 2 ) );
	$html = '<div class="famma-nl-govs" aria-hidden="true">';

	foreach ( $rows as $i => $row ) {
		$items = '';
		$dups  = '';
		foreach ( $row as $name ) {
			$items .= '<span>' . esc_html( $name ) . '</span>';
			$dups  .= '<span class="famma-nl-govs__dup">' . esc_html( $name ) . '</span>';
		}
		// Deux fois la même suite : la boucle se referme sans couture.
		$html .= '<div class="famma-nl-govs__track' . ( $i ? ' famma-nl-govs__track--rev' : '' ) . '">' . $items . $dups . '</div>';
	}

	$html .= '</div>';

	return substr_replace( $content, $html, $end, 0 );
}
add_filter( 'render_block_core/group', 'famma_child_home_govs', 10, 2 );
