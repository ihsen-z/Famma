<?php
/**
 * Onglets de bas de fiche produit — le gabarit de WooCommerce, sans copie.
 *
 * Surcharge de `woocommerce/single-product/tabs/tabs.php`.
 *
 * ── Pourquoi ce fichier existe encore ──────────────────────────────────────
 *
 * Il portait un accordéon (§B14-15). Le propriétaire a demandé, le 25/09/2026,
 * la rangée d'onglets de la maquette « FAMMA Produit » : Description ·
 * Caractéristiques · Avis clients · FAQ · Livraison & Retours. C'est le dessin
 * natif de WooCommerce — liste `ul.tabs`, panneaux `.wc-tab` —, et son script
 * `single-product.js` fait déjà tout le reste : un seul panneau ouvert, rôles
 * ARIA à jour, flèches du clavier (sens inversé en arabe), ouverture de
 * l'onglet Avis sur `#reviews` ou `#comment-…`.
 *
 * Plutôt que de recopier le gabarit — une copie à resynchroniser à chaque
 * version de WooCommerce (règle 13) —, ce fichier rend la main à l'original.
 * Il n'est pas supprimé pour une raison de déploiement : l'archive posée sur
 * le serveur écrase les fichiers, elle n'en retire aucun, et l'ancien
 * accordéon resterait actif.
 *
 * La présentation (rangée défilante sur mobile, soulignement orange, panneau
 * bordé) vit dans `assets/css/product.css`, le contenu des onglets dans
 * `inc/product.php`.
 *
 * @package Famma\Child
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;

$famma_core_tabs = WC()->plugin_path() . '/templates/single-product/tabs/tabs.php';

if ( is_readable( $famma_core_tabs ) ) {
	require $famma_core_tabs;
}
