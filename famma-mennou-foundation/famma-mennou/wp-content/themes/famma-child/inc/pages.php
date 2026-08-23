<?php
/**
 * Pages institutionnelles — accès au contenu éditable.
 *
 * Le thème ne détient aucun texte de ces pages : il les demande à `famma-core`,
 * qui les sert depuis les champs de « FAMMA → Pages ». Ce fichier n'est qu'un
 * guichet — il traduit la question « que faut-il afficher ici ? » en un appel au
 * plugin, et retourne le vide quand rien n'est configuré.
 *
 * Le thème reste utilisable si le plugin est désactivé : les blocs disparaissent
 * au lieu de provoquer une erreur fatale, comme pour le numéro WhatsApp.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le plugin est-il en mesure de servir le contenu des pages ?
 *
 * @return bool
 */
function famma_child_pages_available(): bool {
	return class_exists( '\Famma\Core\Pages_Content' );
}

/**
 * Lit un bloc de texte d'une page institutionnelle.
 *
 * @param string $page `about` ou `contact`.
 * @param string $key  Clé du champ.
 * @return string Chaîne vide si le bloc n'est pas configuré.
 */
function famma_child_page_text( string $page, string $key ): string {
	if ( ! famma_child_pages_available() ) {
		return '';
	}

	return trim( \Famma\Core\Pages_Content::text( $page, $key ) );
}

/**
 * Lit un bloc multiligne et le rend sous forme de liste.
 *
 * @param string $page `about` ou `contact`.
 * @param string $key  Clé du champ.
 * @return array<int, string>
 */
function famma_child_page_lines( string $page, string $key ): array {
	if ( ! famma_child_pages_available() ) {
		return array();
	}

	return \Famma\Core\Pages_Content::lines( $page, $key );
}

/**
 * Rappel « champ à renseigner », visible du seul propriétaire.
 *
 * Un bloc vide ne doit jamais laisser de trou dans la page côté client : il
 * disparaît. Mais le disparaître en silence ferait qu'un champ oublié ne se
 * remarque jamais. Le rappel n'est donc rendu que pour un utilisateur qui peut
 * réellement aller le corriger.
 *
 * @param string $label Nom du bloc manquant, tel qu'il apparaît en administration.
 * @return void
 */
function famma_child_page_todo( string $label ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$url = admin_url( 'admin.php?page=famma-pages' );
	?>
	<p class="famma-page__todo">
		<?php
		printf(
			/* translators: 1: name of the empty content block, 2: settings screen URL. */
			esc_html__( 'Visible par vous seul : le bloc « %1$s » est vide, il n’apparaît pas pour les clients. %2$s', 'famma-child' ),
			esc_html( $label ),
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Le renseigner', 'famma-child' ) . '</a>'
		);
		?>
	</p>
	<?php
}

/**
 * Rend le contenu rédigé dans l'éditeur de la page, s'il y en a.
 *
 * Le gabarit compose ses sections **autour** du contenu de la page, comme
 * l'accueil : un gabarit qui écrase ce que le propriétaire a écrit dans
 * l'éditeur transforme l'administration en décor.
 *
 * @return void
 */
function famma_child_page_editorial(): void {
	while ( have_posts() ) :
		the_post();

		if ( '' === trim( (string) get_the_content() ) ) {
			continue;
		}
		?>
		<section class="famma-page__editorial entry-content">
			<?php the_content(); ?>
		</section>
		<?php
	endwhile;
}

/**
 * Lien WhatsApp prêt à cliquer, message pré-rempli compris.
 *
 * @return string Chaîne vide si aucun numéro n'est configuré.
 */
function famma_child_page_whatsapp_url(): string {
	$number = famma_child_whatsapp_number();

	if ( '' === $number ) {
		return '';
	}

	$url     = 'https://wa.me/' . $number;
	$prefill = famma_child_page_text( 'contact', 'whatsapp_prefill' );

	// `add_query_arg` encode déjà la valeur : l'encoder ici la coderait deux
	// fois, et le client verrait les %20 dans sa conversation.
	if ( '' !== $prefill ) {
		$url = add_query_arg( 'text', $prefill, $url );
	}

	return $url;
}
