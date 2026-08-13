<?php
/**
 * Page d'accueil « catalogue vide » (§9, §58).
 *
 * Idempotent : retrouve la page par son slug et met son contenu à jour
 * plutôt que d'en créer une seconde.
 *
 * RÈGLE APPLIQUÉE PARTOUT ICI (§48, §60) : rien n'est inventé. Aucun
 * produit, aucun avis, aucun chiffre, aucun délai de livraison, aucune
 * garantie. Ne figurent que des faits établis par la spécification :
 * paiement à la livraison, livraison en Tunisie, 0 TND affiché. Les
 * formulations en Derja sont celles fournies par le propriétaire aux §5,
 * §9 et §48 — aucune n'est traduite automatiquement.
 *
 * @package Famma\Local
 */

( static function (): void {

	$blocks = <<<'HTML'
<!-- wp:group {"tagName":"section","align":"full","className":"famma-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull famma-hero">
<!-- wp:paragraph {"className":"famma-hero__kicker"} -->
<p class="famma-hero__kicker" lang="ar" dir="rtl">فمّا ديما حاجة جديدة</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1,"className":"famma-hero__title"} -->
<h1 class="wp-block-heading famma-hero__title">Il y a toujours quelque chose de nouveau.</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"famma-hero__lead"} -->
<p class="famma-hero__lead">FAMMA choisit des produits pratiques et utiles, livrés partout en Tunisie et payés à la livraison.</p>
<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"famma-chips","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull famma-chips">
<!-- wp:columns {"className":"famma-chips__row"} -->
<div class="wp-block-columns famma-chips__row">
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"famma-chip"} -->
<p class="famma-chip"><strong>Livraison en Tunisie</strong><br><span lang="ar" dir="rtl">التوصيل للدار</span></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"famma-chip"} -->
<p class="famma-chip"><strong>Paiement à la livraison</strong><br><span lang="ar" dir="rtl">الدفع عند التوصيل</span></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"famma-chip"} -->
<p class="famma-chip"><strong>Livraison offerte</strong><br>0 TND</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"famma-soon","layout":{"type":"constrained"}} -->
<section class="wp-block-group famma-soon">
<!-- wp:heading {"className":"famma-section__title"} -->
<h2 class="wp-block-heading famma-section__title">Les premiers produits arrivent</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"famma-soon__ar"} -->
<p class="famma-soon__ar" lang="ar" dir="rtl">استنّاو الجديد</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Le catalogue n'est pas encore ouvert. Aucun produit n'est en ligne pour le moment — nous préférons ne rien afficher plutôt que d'afficher n'importe quoi.</p>
<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"famma-how","layout":{"type":"constrained"}} -->
<section class="wp-block-group famma-how">
<!-- wp:heading {"className":"famma-section__title"} -->
<h2 class="wp-block-heading famma-section__title">Vous payez à la livraison</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Rien à payer en ligne. Vous commandez, nous vous appelons pour confirmer, puis vous réglez en espèces au livreur au moment où vous recevez votre commande.</p>
<!-- /wp:paragraph -->
<!-- wp:list {"className":"famma-steps"} -->
<ul class="wp-block-list famma-steps">
<!-- wp:list-item --><li>Vous commandez en ligne, sans créer de compte.</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Nous vous appelons pour confirmer la commande.</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Vous recevez le colis et vous payez au livreur.</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"famma-faq","layout":{"type":"constrained"}} -->
<section class="wp-block-group famma-faq">
<!-- wp:heading {"className":"famma-section__title"} -->
<h2 class="wp-block-heading famma-section__title">Questions fréquentes</h2>
<!-- /wp:heading -->
<!-- wp:details -->
<details class="wp-block-details"><summary>Comment je paie&nbsp;?</summary>
<!-- wp:paragraph --><p>En espèces, au livreur, au moment où vous recevez votre commande. Aucun paiement en ligne n'est demandé.</p><!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
<!-- wp:details -->
<details class="wp-block-details"><summary>Combien coûte la livraison&nbsp;?</summary>
<!-- wp:paragraph --><p>0 TND. La livraison est déjà comprise dans le prix affiché.</p><!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
<!-- wp:details -->
<details class="wp-block-details"><summary>Vous livrez où&nbsp;?</summary>
<!-- wp:paragraph --><p>Partout en Tunisie, dans les 24 gouvernorats.</p><!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","align":"full","className":"famma-contact","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull famma-contact">
<!-- wp:html -->
<img class="famma-seal" src="FAMMA_SEAL_URI" width="148" height="148" alt="FAMMA — فمّا ديما حاجة جديدة" loading="lazy" decoding="async">
<!-- /wp:html -->
<!-- wp:heading {"className":"famma-section__title"} -->
<h2 class="wp-block-heading famma-section__title">Une question&nbsp;?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><span lang="ar" dir="rtl">عندك سؤال؟ أحكيلنا على واتساب.</span></p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[famma_whatsapp text="Écrire sur WhatsApp"]
<!-- /wp:shortcode -->
</section>
<!-- /wp:group -->
HTML;

	/*
	 * Le sceau (planche 2b) vit dans le thème enfant, pas dans la
	 * médiathèque : c'est un élément d'identité versionné avec le code, et
	 * WordPress refuse de toute façon le téléversement de SVG par défaut.
	 * L'URL est injectée ici pour que le contenu reste indépendant du nom
	 * du thème.
	 */
	$blocks = str_replace(
		'FAMMA_SEAL_URI',
		esc_url( get_stylesheet_directory_uri() . '/assets/img/famma-badge.svg' ),
		$blocks
	);

	$slug     = 'accueil';
	$existing = get_page_by_path( $slug, OBJECT, 'page' );

	$args = array(
		'post_title'   => 'Accueil',
		'post_name'    => $slug,
		'post_content' => $blocks,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $existing instanceof WP_Post ) {
		$args['ID'] = $existing->ID;
		$id         = wp_update_post( $args, true );
		$action     = 'mise a jour';
	} else {
		$id     = wp_insert_post( $args, true );
		$action = 'creee';
	}

	if ( is_wp_error( $id ) ) {
		echo 'ECHEC : ' . $id->get_error_message() . "\n";
		return;
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $id );

	echo "Page d'accueil {$action} (ID {$id}) et definie comme page d'accueil.\n";
	echo "  Aucun produit, aucun avis, aucun chiffre invente (§58, §60).\n";
	echo "  Restent a fournir par le proprietaire : logo (§7), numero WhatsApp,\n";
	echo "  delais de livraison, politique de retour, textes legaux (§45).\n";
} )();
