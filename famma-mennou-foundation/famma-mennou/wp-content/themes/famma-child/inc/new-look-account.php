<?php
/**
 * Nouveau look — « Mon compte » à la manière de la maquette espace client.
 *
 * Case `acct_look` (lot 1 du plan de portage de l'espace client). Habillage
 * seul : aucun champ, aucun e-mail, aucune règle de WooCommerce ne change, et
 * aucun modèle WooCommerce n'est copié. Les formulaires restent ceux de
 * `myaccount/form-login.php` ; le script ne fait que ranger les deux colonnes
 * existantes en onglets, et sans script elles restent l'une sous l'autre.
 *
 * Case décochée, ou plugin absent : chaque fonction sort aussitôt.
 *
 * @package famma-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * La page courante reçoit-elle l'habillage du compte ?
 *
 * @return bool
 */
function famma_child_account_look(): bool {
	return function_exists( 'is_account_page' ) && is_account_page() && famma_child_new_look( 'acct_look' );
}

/**
 * Classe du corps sur laquelle la feuille s'appuie.
 *
 * @param array<int, string> $classes Classes du corps.
 * @return array<int, string>
 */
function famma_child_account_look_body_class( array $classes ): array {
	if ( famma_child_account_look() ) {
		$classes[] = 'famma-nl-acct';
	}

	return $classes;
}
add_filter( 'body_class', 'famma_child_account_look_body_class' );

/**
 * Feuille et script, chargés seulement sur « Mon compte ».
 *
 * @return void
 */
function famma_child_enqueue_account_look(): void {
	if ( ! famma_child_account_look() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/css/new-look-account.css';
	$js  = $dir . '/assets/js/new-look-account.js';

	if ( file_exists( $css ) ) {
		wp_enqueue_style(
			'famma-new-look-account',
			$uri . '/assets/css/new-look-account.css',
			array( 'famma-tokens' ),
			(string) filemtime( $css )
		);
	}

	if ( is_user_logged_in() || ! file_exists( $js ) ) {
		return;
	}

	// Les onglets n'existent que sur le formulaire de connexion / création.
	wp_enqueue_script(
		'famma-new-look-account',
		$uri . '/assets/js/new-look-account.js',
		array(),
		(string) filemtime( $js ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$auth = class_exists( '\Famma\Core\Account_Auth' );

	wp_add_inline_script(
		'famma-new-look-account',
		'window.fammaAccount=' . wp_json_encode(
			array(
				'tabs' => __( 'Se connecter ou créer un compte', 'famma-child' ),
				'up'   => $auth ? \Famma\Core\Account_Auth::text( 'tab_up' ) : '',
				'in'   => $auth ? \Famma\Core\Account_Auth::text( 'tab_in' ) : '',
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_look', 24 );

/**
 * Onglets de la maquette : Mes commandes · Mes adresses · Mes informations.
 *
 * « Téléchargements » (rien à télécharger) et « Tableau de bord » (la page
 * s'ouvre sur les commandes) quittent la barre ; « Déconnexion » passe dans
 * « Mes informations ». Seuls les liens bougent : les pages restent servies
 * par WooCommerce. Les onglets ajoutés par d'autres extensions restent, après
 * les nôtres.
 *
 * @param array<string, string> $items Liens du menu du compte.
 * @return array<string, string>
 */
function famma_child_account_look_menu( array $items ): array {
	if ( ! famma_child_new_look( 'acct_look' ) ) {
		return $items;
	}

	unset( $items['downloads'], $items['dashboard'], $items['customer-logout'] );

	if ( ! class_exists( '\Famma\Core\Account_Profile' ) ) {
		return $items;
	}

	$ours = array();
	$keys = array(
		'orders'       => 'tab_orders',
		'edit-address' => 'tab_addresses',
		'edit-account' => 'tab_infos',
	);

	foreach ( $keys as $endpoint => $field ) {
		if ( isset( $items[ $endpoint ] ) ) {
			$label             = \Famma\Core\Account_Profile::text( $field );
			$ours[ $endpoint ] = '' !== $label ? $label : $items[ $endpoint ];
			unset( $items[ $endpoint ] );
		}
	}

	return $ours + $items;
}
add_filter( 'woocommerce_account_menu_items', 'famma_child_account_look_menu', 20 );

/**
 * Sans bandeau de titre ni boîte blanche : la page commence par le fil
 * d'Ariane, comme la maquette.
 *
 * @param array<string, mixed> $layout Mise en page calculée par Kadence.
 * @return array<string, mixed>
 */
function famma_child_account_look_layout( $layout ) {
	if ( is_array( $layout ) && famma_child_account_look() ) {
		$layout['title'] = 'hide';
		$layout['boxed'] = 'unboxed';
	}

	return $layout;
}
add_filter( 'kadence_post_layout', 'famma_child_account_look_layout', 20 );

/**
 * « Mon compte » connecté s'ouvre sur les commandes : la maquette n'a pas de
 * tableau de bord.
 *
 * @return void
 */
function famma_child_account_look_redirect(): void {
	if ( ! famma_child_account_look() || ! is_user_logged_in() || is_wc_endpoint_url() ) {
		return;
	}

	wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
	exit;
}
add_action( 'template_redirect', 'famma_child_account_look_redirect', 20 );

/**
 * Fil d'Ariane « Accueil › Mon compte », connecté ou non.
 *
 * @return void
 */
function famma_child_account_look_crumbs(): void {
	if ( famma_child_account_look() && function_exists( 'famma_child_page_crumbs' ) ) {
		famma_child_page_crumbs();
	}
}
add_action( 'woocommerce_before_customer_login_form', 'famma_child_account_look_crumbs', 1 );
add_action( 'woocommerce_before_account_navigation', 'famma_child_account_look_crumbs', 0 );

/**
 * Carte marine du client et compteurs, au-dessus des onglets.
 *
 * Avant l'enveloppe de Kadence (priorité 2) : l'en-tête prend toute la
 * largeur, les onglets restent dessous.
 *
 * @return void
 */
function famma_child_account_look_head(): void {
	if ( ! famma_child_account_look() || ! class_exists( '\Famma\Core\Account_Profile' ) ) {
		return;
	}

	$user = wp_get_current_user();

	if ( ! $user->exists() ) {
		return;
	}

	$data  = \Famma\Core\Account_Profile::data( $user );
	$pct   = (int) $data['pct'];
	$ring  = 276.46;
	$phone = \Famma\Core\Account_Profile::format_phone( (string) $data['phone'] );
	$favs  = \Famma\Core\Account_Profile::text( 'stat_favs' );
	?>
	<section class="famma-acct-head" aria-labelledby="famma-acct-name" data-pct="<?php echo esc_attr( (string) $pct ); ?>">
		<div class="famma-acct-ring">
			<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">
				<circle class="famma-acct-ring__trk" cx="50" cy="50" r="44" fill="none" stroke-width="7"/>
				<circle class="famma-acct-ring__val" cx="50" cy="50" r="44" fill="none" stroke-width="7" stroke-dasharray="<?php echo esc_attr( (string) $ring ); ?>" style="stroke-dashoffset:<?php echo esc_attr( (string) round( $ring * ( 1 - $pct / 100 ), 2 ) ); ?>"/>
			</svg>
			<span class="famma-acct-ring__av" aria-hidden="true"><?php echo esc_html( (string) $data['initials'] ); ?></span>
			<span class="famma-acct-ring__pct"><?php echo esc_html( $pct . ' %' ); ?></span>
		</div>
		<div class="famma-acct-head__t">
			<?php if ( '' !== $data['hello'] ) : ?>
				<small><?php echo esc_html( (string) $data['hello'] ); ?></small>
			<?php endif; ?>
			<h1 id="famma-acct-name"><?php echo esc_html( (string) $data['name'] ); ?></h1>
			<?php if ( '' !== $phone ) : ?>
				<span class="famma-acct-head__tel"><bdi dir="ltr"><?php echo esc_html( $phone ); ?></bdi></span>
			<?php endif; ?>
			<?php if ( '' !== $data['since'] ) : ?>
				<small><?php echo esc_html( (string) $data['since'] ); ?></small>
			<?php endif; ?>
		</div>
		<div class="famma-acct-head__next">
			<?php foreach ( $data['nudges'] as $nudge ) : ?>
				<a class="famma-acct-nudge" href="<?php echo esc_url( $nudge['url'] ); ?>"><?php echo esc_html( $nudge['label'] ); ?> <b><?php echo esc_html( $nudge['gain'] ); ?></b></a>
			<?php endforeach; ?>
			<?php if ( '' !== $data['done'] ) : ?>
				<span class="famma-acct-nudge is-done"><?php echo esc_html( (string) $data['done'] ); ?></span>
			<?php endif; ?>
		</div>
	</section>
	<div class="famma-acct-stats">
		<a class="famma-acct-stat" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><b data-roll="<?php echo esc_attr( (string) $data['orders'] ); ?>"><?php echo esc_html( (string) $data['orders'] ); ?></b><?php echo esc_html( \Famma\Core\Account_Profile::text( 'stat_orders' ) ); ?></a>
		<a class="famma-acct-stat is-hot" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><b data-roll="<?php echo esc_attr( (string) $data['open'] ); ?>"><?php echo esc_html( (string) $data['open'] ); ?></b><?php echo esc_html( \Famma\Core\Account_Profile::text( 'stat_open' ) ); ?></a>
		<?php if ( '' !== $favs ) : ?>
			<span class="famma-acct-stat" data-famma-favs hidden><b data-roll="0">0</b><?php echo esc_html( $favs ); ?></span>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_before_account_navigation', 'famma_child_account_look_head', 1 );

/**
 * « Me déconnecter » sous « Mes informations » : le lien quitte les onglets.
 *
 * @return void
 */
function famma_child_account_look_logout(): void {
	if ( ! famma_child_account_look() || ! class_exists( '\Famma\Core\Account_Profile' ) ) {
		return;
	}

	$label = \Famma\Core\Account_Profile::text( 'logout' );

	if ( '' === $label ) {
		return;
	}

	printf(
		'<div class="famma-acct-box famma-acct-logout"><a class="button famma-acct-logout__btn" href="%1$s">%2$s<span>%3$s</span></a></div>',
		esc_url( wc_logout_url() ),
		wp_kses( famma_child_icon( 'logout' ), famma_child_svg_allowed_html() ),
		esc_html( $label )
	);
}
add_action( 'woocommerce_after_edit_account_form', 'famma_child_account_look_logout' );

/**
 * Script des animations du compte connecté : soulignement qui glisse, panneau
 * qui arrive du bon côté, en-tête et compteurs à l'arrivée, cartes en
 * cascade, frise qui se remplit à l'écran, dépliage des commandes.
 *
 * @return void
 */
function famma_child_enqueue_account_panel(): void {
	if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	if ( ! famma_child_new_look( 'acct_look' ) && ! famma_child_new_look( 'acct_track' ) ) {
		return;
	}

	$js = get_stylesheet_directory() . '/assets/js/new-look-account-panel.js';

	if ( ! file_exists( $js ) ) {
		return;
	}

	/*
	 * Dans l'en-tête, avant l'affichage : d'où vient le visiteur ? D'ailleurs
	 * (connexion, autre page) → l'en-tête « arrive » ; d'un autre onglet →
	 * le panneau glisse de son côté. Rien n'est envoyé nulle part.
	 */
	$tabs = array(
		array( 'orders', 'view-order' ),
		array( 'edit-address' ),
		array( 'edit-account' ),
	);
	$map  = array();

	foreach ( $tabs as $endpoints ) {
		$paths = array();

		foreach ( $endpoints as $endpoint ) {
			$path = wp_parse_url( wc_get_account_endpoint_url( $endpoint ), PHP_URL_PATH );

			if ( is_string( $path ) && '' !== $path ) {
				$paths[] = trailingslashit( $path );
			}
		}

		$map[] = $paths;
	}

	wp_register_script( 'famma-new-look-account-nav', false, array(), FAMMA_CHILD_VERSION, false );
	wp_enqueue_script( 'famma-new-look-account-nav' );
	wp_add_inline_script(
		'famma-new-look-account-nav',
		'(function(d){try{var t=' . wp_json_encode( $map ) . ',h=d.documentElement,f=function(u){var p=new URL(u,location.href);if(p.origin!==location.origin)return -1;var s=p.pathname.replace(/\/?$/,"/");for(var i=0;i<t.length;i++)for(var j=0;j<t[i].length;j++)if(s.indexOf(t[i][j])===0)return i;return -1},n=f(location.href),r=d.referrer?f(d.referrer):-1;if(n<0)return;if(r<0)h.classList.add("famma-acct-intro");else if(r!==n){h.classList.add(n>r?"famma-acct-fwd":"famma-acct-back");h.setAttribute("data-famma-acct-from",String(r))}}catch(e){}})(document);'
	);

	wp_enqueue_script(
		'famma-new-look-account-panel',
		get_stylesheet_directory_uri() . '/assets/js/new-look-account-panel.js',
		array(),
		(string) filemtime( $js ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'famma_child_enqueue_account_panel', 26 );
