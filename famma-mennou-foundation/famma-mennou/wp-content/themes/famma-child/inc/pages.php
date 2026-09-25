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

/**
 * Le plugin sait-il servir les textes de la page Livraison ?
 *
 * @return bool
 */
function famma_child_delivery_available(): bool {
	return class_exists( '\Famma\Core\Delivery_Content' );
}

/**
 * Texte de la page Livraison, délai déjà inséré.
 *
 * @param string $key Clé du champ.
 * @return string Chaîne vide si le bloc n'est pas configuré.
 */
function famma_child_delivery_text( string $key ): string {
	return famma_child_delivery_available() ? \Famma\Core\Delivery_Content::text( $key ) : '';
}

/**
 * Champ multiligne de la page Livraison, une entrée par ligne.
 *
 * @param string $key Clé du champ.
 * @return array<int, string>
 */
function famma_child_delivery_lines( string $key ): array {
	return famma_child_delivery_available() ? \Famma\Core\Delivery_Content::lines( $key ) : array();
}

/**
 * Zones desservies.
 *
 * @return array<int, array{zone: string, detail: string}>
 */
function famma_child_delivery_zones(): array {
	return famma_child_delivery_available() ? \Famma\Core\Delivery_Content::zones() : array();
}

/**
 * Questions fréquentes de la page Livraison.
 *
 * @return array<int, array{question: string, answer: string}>
 */
function famma_child_delivery_faq(): array {
	return famma_child_delivery_available() ? \Famma\Core\Delivery_Content::faq() : array();
}

/**
 * Le plugin sait-il servir les textes de la page Retours ?
 *
 * @return bool
 */
function famma_child_returns_available(): bool {
	return class_exists( '\Famma\Core\Returns_Content' );
}

/**
 * Texte de la page Retours (FAMMA → Pages → Page Retours).
 *
 * @param string $key Clé du champ.
 * @return string Chaîne vide si le bloc n'est pas rempli.
 */
function famma_child_returns_text( string $key ): string {
	return famma_child_returns_available() ? \Famma\Core\Returns_Content::text( $key ) : '';
}

/**
 * Champ multiligne de la page Retours, une entrée par ligne.
 *
 * @param string $key Clé du champ.
 * @return array<int, string>
 */
function famma_child_returns_lines( string $key ): array {
	return famma_child_returns_available() ? \Famma\Core\Returns_Content::lines( $key ) : array();
}

/**
 * Champ multiligne de la page Retours découpé en colonnes sur « | ».
 *
 * @param string $key     Clé du champ.
 * @param int    $columns Nombre de colonnes.
 * @return array<int, array<int, string>>
 */
function famma_child_returns_rows( string $key, int $columns ): array {
	return famma_child_returns_available() ? \Famma\Core\Returns_Content::rows( $key, $columns ) : array();
}

/**
 * Questions fréquentes de la page Retours.
 *
 * @return array<int, array{question: string, answer: string}>
 */
function famma_child_returns_faq(): array {
	return famma_child_returns_available() ? \Famma\Core\Returns_Content::faq() : array();
}

/**
 * Fil d'Ariane d'une page institutionnelle : Accueil › page courante.
 *
 * Même dessin que celui du tunnel de commande, et libellé traduit : un
 * `aria-label` en dur serait lu en français sur la version arabe.
 *
 * @return void
 */
function famma_child_page_crumbs(): void {
	?>
	<nav class="famma-crumbs" aria-label="<?php esc_attr_e( 'Fil d’Ariane', 'famma-child' ); ?>">
		<a class="famma-crumbs__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Accueil', 'famma-child' ); ?></a>
		<?php famma_child_the_icon( 'forward', 'famma-crumbs__sep' ); ?>
		<span class="famma-crumbs__current" aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
	</nav>
	<?php
}

/**
 * Découpe le contenu d'un document (CGV…) en introduction et articles.
 *
 * Chaque titre de niveau 2 saisi dans l'éditeur ouvre un article : il reçoit
 * un numéro, une ancre et une entrée au sommaire. Le propriétaire n'a donc
 * rien d'autre à faire que rédiger avec des titres — la mise en page de la
 * maquette suit, quel que soit le nombre d'articles. Ce qui précède le
 * premier titre sert d'introduction.
 *
 * @param string $html Contenu déjà passé par `the_content`.
 * @return array{intro: string, sections: array<int, array{id: string, number: string, title: string, body: string}>}
 */
function famma_child_document_sections( string $html ): array {
	$parts = preg_split( '#<h2\b[^>]*>(.*?)</h2>#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$parts = is_array( $parts ) ? $parts : array( $html );

	$intro    = (string) array_shift( $parts );
	$sections = array();
	$used     = array();
	$count    = count( $parts );

	for ( $i = 0; $i + 1 < $count; $i += 2 ) {
		$title = trim( wp_strip_all_tags( (string) $parts[ $i ] ) );

		if ( '' === $title ) {
			continue;
		}

		$number = str_pad( (string) ( count( $sections ) + 1 ), 2, '0', STR_PAD_LEFT );
		$id     = 'article-' . sanitize_title( $title );

		// Deux articles de même titre ne partagent pas la même ancre.
		if ( isset( $used[ $id ] ) ) {
			$id .= '-' . $number;
		}
		$used[ $id ] = true;

		$sections[] = array(
			'id'     => $id,
			'number' => $number,
			'title'  => $title,
			'body'   => (string) $parts[ $i + 1 ],
		);
	}

	return array(
		'intro'    => $intro,
		'sections' => $sections,
	);
}
