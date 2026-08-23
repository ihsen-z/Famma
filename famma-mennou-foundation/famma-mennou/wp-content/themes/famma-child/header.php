<?php
/**
 * FAMMA — en-tête partagé.
 *
 * Ce gabarit remplace celui de Kadence sur **toutes** les pages du site :
 * `locate_template()` interroge le thème enfant d'abord, et Kadence appelle
 * `get_header()` dans chacun de ses gabarits, y compris ceux de WooCommerce.
 * Accueil, boutique, fiche produit, panier, commande, confirmation, pages
 * légales et 404 partagent donc le même en-tête, sans exception à maintenir.
 *
 * Pourquoi un gabarit PHP plutôt que le constructeur d'en-tête de Kadence :
 * la configuration du constructeur vit en base de données, donc hors de Git,
 * invisible en revue et non reproductible par `git clone` + `setup.sh`. Ici,
 * tout se lit, se traduit par gettext et se teste.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'famma-site' ); ?>>
<?php wp_body_open(); ?>

<a class="famma-skip-link screen-reader-text" href="#famma-content">
	<?php esc_html_e( 'Aller au contenu', 'famma-child' ); ?>
</a>

<?php famma_child_topbar(); ?>

<header class="famma-header" id="famma-header">
	<div class="famma-container famma-header__inner">
		<?php famma_child_brand_link(); ?>
		<?php famma_child_primary_nav( 'desktop' ); ?>
		<div class="famma-header__spacer"></div>
		<?php famma_child_search_form( 'desktop' ); ?>
		<?php famma_child_header_actions(); ?>
	</div>

	<?php
	/*
	 * Le panneau mobile vit DANS l'en-tête collant, comme dans la maquette :
	 * il se déplie sous la barre au lieu de recouvrir la page. `hidden`
	 * plutôt qu'une classe : sans JavaScript, le panneau reste fermé et le
	 * menu du pied de page prend le relais — la navigation ne dépend jamais
	 * d'un script pour exister.
	 */
	?>
	<div class="famma-header__panel" id="famma-mobile-panel" hidden>
		<div class="famma-container">
			<?php famma_child_primary_nav( 'mobile' ); ?>
			<?php famma_child_search_form( 'mobile' ); ?>
			<?php famma_child_panel_account(); ?>
		</div>
	</div>
</header>

<?php
/*
 * `main` par defaut, `div` sur les gabarits WooCommerce du parent qui en
 * posent deja un — voir famma_child_content_tag().
 */
printf( '<%s class="famma-content" id="famma-content">', esc_html( famma_child_content_tag() ) );
?>
