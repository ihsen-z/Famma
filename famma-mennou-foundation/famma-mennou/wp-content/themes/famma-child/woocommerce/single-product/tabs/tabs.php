<?php
/**
 * Sections de bas de fiche produit — en accordéon, plus en onglets.
 *
 * Surcharge de `woocommerce/single-product/tabs/tabs.php` (§B14-15).
 *
 * ── Pourquoi l'accordéon ────────────────────────────────────────────────────
 *
 * Les onglets de WooCommerce demandent une rangée horizontale. Sur un écran de
 * 375px, « Description · Avis · Livraison · Spécifications · FAQ » ne tient
 * pas : la rangée se casse sur trois lignes ou part en défilement latéral, et
 * dans les deux cas les derniers onglets deviennent invisibles. Or ce sont
 * précisément ceux qui lèvent les objections d'achat — livraison, FAQ. Le
 * trafic vient d'une publicité, sur mobile : cacher les réponses derrière un
 * onglet hors écran, c'est les supprimer.
 *
 * L'accordéon empile. Chaque intitulé reste lisible, et le client ouvre ce
 * qu'il veut lire.
 *
 * ── Pourquoi `<details>` plutôt qu'un accordéon en JavaScript ───────────────
 *
 * `<details>`/`<summary>` fait nativement ce qu'un accordéon maison réimplémente
 * mal : le clavier, le rôle ARIA, l'état ouvert/fermé annoncé par les lecteurs
 * d'écran, et la recherche dans la page qui ouvre la section. Surtout, il
 * fonctionne **sans JavaScript** : si un script échoue, le contenu reste
 * atteignable. Un accordéon JS qui échoue, lui, laisse une page muette.
 *
 * ── Pourquoi les classes de WooCommerce ont disparu ─────────────────────────
 *
 * Et ce n'est pas cosmétique. `single-product.js` de WooCommerce fait, au
 * chargement (`.trigger('init')`, ligne 199) :
 *
 *     $( '.wc-tabs-wrapper, .woocommerce-tabs' )
 *         .find( '.wc-tab, .woocommerce-tabs .panel:not(.panel .panel)' ).hide();
 *
 * Autrement dit : garder `woocommerce-tabs` sur le conteneur ou `wc-tab` /
 * `panel` sur les panneaux, et jQuery masque tout le contenu de l'accordéon
 * juste après le rendu. Les sections s'ouvriraient sur du vide. Ces classes
 * sont donc volontairement absentes.
 *
 * `woocommerce-Tabs-panel--<clé>` et `entry-content` sont conservées : elles
 * ne sont ciblées par aucun masquage, et des extensions s'y accrochent pour
 * styliser le contenu d'un onglet donné.
 *
 * Les `id="tab-<clé>"` sont conservés eux aussi : des liens internes pointent
 * dessus, et un lecteur peut arriver sur la page avec l'ancre déjà dans l'URL.
 *
 * @package Famma\Child
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filtre standard de WooCommerce : les extensions ajoutent leurs sections ici.
 *
 * @see woocommerce_default_product_tabs()
 */
$famma_product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( empty( $famma_product_tabs ) ) {
	return;
}

$famma_is_first = true;
?>

<div class="famma-accordion" id="famma-product-sections">
	<?php foreach ( $famma_product_tabs as $famma_key => $famma_tab ) : ?>
		<details
			class="famma-accordion__item famma-accordion__item--<?php echo esc_attr( $famma_key ); ?>"
			id="tab-<?php echo esc_attr( $famma_key ); ?>"
			<?php
			/*
			 * La première section est ouverte, les autres fermées. Tout ouvrir
			 * reviendrait à la page à défilement infini que l'accordéon est
			 * censé remplacer ; tout fermer donnerait une page qui semble vide
			 * sous le bouton d'achat.
			 */
			echo $famma_is_first ? ' open' : '';
			?>
		>
			<summary class="famma-accordion__head">
				<span class="famma-accordion__title">
					<?php
					echo wp_kses_post(
						apply_filters( 'woocommerce_product_' . $famma_key . '_tab_title', $famma_tab['title'], $famma_key )
					);
					?>
				</span>
				<?php
				/*
				 * `aria-hidden` sur le chevron : `<summary>` annonce déjà son
				 * état, et un lecteur d'écran n'a pas à entendre « triangle ».
				 */
				?>
				<span class="famma-accordion__chevron" aria-hidden="true"></span>
			</summary>

			<div class="famma-accordion__panel woocommerce-Tabs-panel--<?php echo esc_attr( $famma_key ); ?> entry-content">
				<?php
				if ( isset( $famma_tab['callback'] ) ) {
					call_user_func( $famma_tab['callback'], $famma_key, $famma_tab );
				}
				?>
			</div>
		</details>
		<?php $famma_is_first = false; ?>
	<?php endforeach; ?>

	<?php do_action( 'woocommerce_product_after_tabs' ); ?>
</div>
