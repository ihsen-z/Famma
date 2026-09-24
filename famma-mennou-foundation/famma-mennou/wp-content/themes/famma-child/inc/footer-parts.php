<?php
/**
 * FAMMA — briques du pied de page partagé.
 *
 * Cinq colonnes : marque, navigation, informations, aide & support, paiement
 * à la livraison. Puis la barre légale.
 *
 * Règle appliquée partout ici : **une ligne dont la donnée n'existe pas n'est
 * pas rendue**. Pas de « +216 XX XXX XXX », pas de compte social fictif, pas
 * d'adresse inventée (§58, §60). Une colonne vide disparaît, la grille se
 * réorganise seule.
 *
 * @package Famma\Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lit un réglage de contact ou de réseau social.
 *
 * L'ordre est délibéré : la constante l'emporte, parce que la configuration
 * du site vient de `.env` (§8) et doit rester reproductible par
 * `git clone` + `setup.sh`. L'option n'est qu'un repli, utile quand le
 * propriétaire renseigne la valeur depuis l'administration.
 *
 * @param string $key Nom de la constante, ex. `FAMMA_CONTACT_EMAIL`.
 * @return string Chaîne vide si rien n'est configuré.
 */
function famma_child_setting( string $key ): string {
	if ( defined( $key ) && '' !== (string) constant( $key ) ) {
		return (string) constant( $key );
	}

	return (string) get_option( strtolower( $key ), '' );
}

/**
 * Colonne marque : lockup, description, réseaux sociaux.
 *
 * @return void
 */
function famma_child_footer_brand(): void {
	$description = get_bloginfo( 'description', 'display' );

	if ( '' === trim( (string) $description ) ) {
		$description = __( 'Votre boutique en ligne tunisienne : des produits pratiques, utiles et parfois surprenants, livrés partout en Tunisie et payés à la livraison.', 'famma-child' );
	}
	?>
	<div class="famma-footer__col famma-footer__col--brand">
		<a class="famma-footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php echo wp_kses( famma_child_lockup_markup(), famma_child_svg_allowed_html() ); ?>
		</a>
		<p class="famma-footer__about"><?php echo esc_html( $description ); ?></p>
		<?php famma_child_footer_socials(); ?>
	</div>
	<?php
}

/**
 * Réseaux sociaux — seuls les comptes réellement configurés sont rendus.
 *
 * @return void
 */
function famma_child_footer_socials(): void {
	$networks = array(
		'facebook'  => __( 'Facebook', 'famma-child' ),
		'instagram' => __( 'Instagram', 'famma-child' ),
		'tiktok'    => __( 'TikTok', 'famma-child' ),
		'youtube'   => __( 'YouTube', 'famma-child' ),
	);

	$links = array();
	foreach ( $networks as $slug => $label ) {
		$url = famma_child_setting( 'FAMMA_SOCIAL_' . strtoupper( $slug ) );
		if ( '' !== $url ) {
			$links[ $slug ] = array(
				'url'   => $url,
				'label' => $label,
			);
		}
	}

	if ( array() === $links ) {
		return;
	}
	?>
	<ul class="famma-socials">
		<?php foreach ( $links as $slug => $link ) : ?>
			<li>
				<a class="famma-socials__link" href="<?php echo esc_url( $link['url'] ); ?>"
					rel="noopener noreferrer" target="_blank">
					<?php famma_child_the_icon( $slug ); ?>
					<span class="screen-reader-text"><?php echo esc_html( $link['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}

/**
 * Colonne de liens alimentée par un menu.
 *
 * @param string $location Emplacement de menu.
 * @param string $title    Titre de la colonne.
 * @return void
 */
function famma_child_footer_menu_col( string $location, string $title ): void {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}
	?>
	<nav class="famma-footer__col" aria-label="<?php echo esc_attr( $title ); ?>">
		<h2 class="famma-footer__title"><?php echo esc_html( $title ); ?></h2>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => $location,
				'container'      => false,
				'menu_class'     => 'famma-footer__list',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		?>
	</nav>
	<?php
}

/**
 * Colonne « Aide & support ».
 *
 * Chaque ligne dépend d'une valeur configurée. Aucune n'est simulée : mieux
 * vaut une colonne à deux lignes qu'un numéro de téléphone imaginaire sur
 * lequel un client tenterait d'appeler.
 *
 * @return void
 */
function famma_child_footer_help(): void {
	$rows = array();

	$whatsapp = famma_child_whatsapp_number();
	if ( '' !== $whatsapp ) {
		$rows[] = array(
			'icon'  => 'chat',
			'label' => __( 'WhatsApp', 'famma-child' ),
			'url'   => 'https://wa.me/' . rawurlencode( $whatsapp ),
			'class' => 'is-whatsapp',
		);
	}

	$phone = famma_child_setting( 'FAMMA_CONTACT_PHONE' );
	if ( '' !== $phone ) {
		$rows[] = array(
			'icon'  => 'phone',
			'label' => $phone,
			'url'   => famma_child_tel_uri( $phone ),
			'class' => '',
		);
	}

	$email = famma_child_setting( 'FAMMA_CONTACT_EMAIL' );
	if ( '' !== $email && is_email( $email ) ) {
		$rows[] = array(
			'icon'  => 'mail',
			'label' => $email,
			'url'   => 'mailto:' . sanitize_email( $email ),
			'class' => '',
		);
	}

	$address = famma_child_setting( 'FAMMA_CONTACT_ADDRESS' );
	if ( '' !== $address ) {
		$rows[] = array(
			'icon'  => 'pin',
			'label' => $address,
			'url'   => '',
			'class' => '',
		);
	}

	if ( array() === $rows ) {
		return;
	}
	?>
	<div class="famma-footer__col">
		<h2 class="famma-footer__title"><?php esc_html_e( 'Aide &amp; support', 'famma-child' ); ?></h2>
		<ul class="famma-footer__list famma-footer__list--help">
			<?php foreach ( $rows as $row ) : ?>
				<li class="famma-footer__help <?php echo esc_attr( $row['class'] ); ?>">
					<?php famma_child_the_icon( $row['icon'] ); ?>
					<?php if ( '' !== $row['url'] ) : ?>
						<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['label'] ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( $row['label'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/**
 * Colonne « Paiement à la livraison ».
 *
 * La maquette y place une illustration 150×96. Tant que le propriétaire ne
 * l'a pas fournie, la colonne rend son titre et son texte : le message passe,
 * et aucun cadre vide ne traîne.
 *
 * @return void
 */
function famma_child_footer_cod(): void {
	$illustration = famma_child_setting( 'FAMMA_COD_ILLUSTRATION' );
	?>
	<div class="famma-footer__col famma-footer__col--cod">
		<h2 class="famma-footer__title"><?php esc_html_e( 'Paiement à la livraison', 'famma-child' ); ?></h2>
		<?php if ( '' !== $illustration ) : ?>
			<img class="famma-footer__cod-image" src="<?php echo esc_url( $illustration ); ?>"
				alt="" width="150" height="96" loading="lazy" decoding="async">
		<?php endif; ?>
		<p class="famma-footer__cod-text">
			<?php esc_html_e( 'Vous payez uniquement à la réception de votre commande.', 'famma-child' ); ?>
			<?php if ( ! famma_child_is_derja() ) : // En derja, la phrase ci-dessus dit déjà la même chose. ?>
				<span class="famma-footer__cod-ar" lang="ar" dir="rtl">خلص كي توصل</span>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Barre basse : copyright et liens légaux.
 *
 * L'année vient de l'horloge du site, jamais d'une constante gravée : un
 * copyright figé à 2024 vieillit tout seul et se voit.
 *
 * @return void
 */
function famma_child_footer_bottom(): void {
	?>
	<div class="famma-footer__bottom">
		<div class="famma-container famma-footer__bottom-inner">
			<p class="famma-footer__copyright">
				<?php
				printf(
					/* translators: 1: année en cours, 2: nom du site. */
					esc_html__( '© %1$s %2$s. Tous droits réservés.', 'famma-child' ),
					esc_html( wp_date( 'Y' ) ),
					esc_html( get_bloginfo( 'name', 'display' ) )
				);
				?>
			</p>
			<?php famma_child_legal_bar(); ?>
		</div>
	</div>
	<?php
}
