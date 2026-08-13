<?php
/**
 * Génère l'image de partage social 1200×630 (§7, §16).
 *
 * Facebook, WhatsApp et Instagram refusent le SVG : il faut un PNG. Aucun
 * rastériseur SVG n'est disponible ici — Imagick n'a pas de délégué SVG et
 * ni rsvg-convert ni inkscape ne sont installés. L'image est donc composée
 * directement avec GD, à partir des mêmes primitives géométriques que la
 * planche 2a.
 *
 * La baseline arabe est volontairement absente : GD ne sait pas faire la
 * liaison des lettres arabes et rendrait « فمّا » en caractères détachés,
 * ce qui serait pire que rien. Le PNG porte donc la version française.
 *
 * Idempotent : réécrit le fichier à l'identique.
 *
 * @package Famma\Local
 */

( static function (): void {

	$width  = 1200;
	$height = 630;

	/*
	 * Ce script tourne dans le conteneur `wordpress`, seul à embarquer les
	 * polices URW, et non dans celui de WP-CLI. Le thème y est monté sous
	 * /var/www/html ; le repli couvre une exécution depuis l'hôte.
	 */
	$theme = is_dir( '/var/www/html/wp-content/themes/famma-child' )
		? '/var/www/html/wp-content/themes/famma-child'
		: __DIR__ . '/../wp-content/themes/famma-child';
	$out   = $theme . '/assets/img/famma-og.png';

	$font_bold    = '/usr/share/fonts/opentype/urw-base35/NimbusSans-Bold.otf';
	$font_regular = '/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf';

	foreach ( array( $font_bold, $font_regular ) as $font ) {
		if ( ! is_readable( $font ) ) {
			echo 'ECHEC : police introuvable — ' . $font . "\n";
			return;
		}
	}

	$img = imagecreatetruecolor( $width, $height );
	imagealphablending( $img, true );

	$navy   = imagecolorallocate( $img, 11, 31, 58 );
	$orange = imagecolorallocate( $img, 255, 122, 0 );
	$white  = imagecolorallocate( $img, 255, 255, 255 );
	$muted  = imagecolorallocate( $img, 200, 208, 220 );

	imagefilledrectangle( $img, 0, 0, $width, $height, $navy );

	/**
	 * Dessine un rectangle à coins arrondis.
	 *
	 * @param resource|GdImage $im     Image.
	 * @param int              $x      Bord gauche.
	 * @param int              $y      Bord haut.
	 * @param int              $w      Largeur.
	 * @param int              $h      Hauteur.
	 * @param int              $r      Rayon.
	 * @param int              $colour Couleur allouée.
	 * @return void
	 */
	$rounded = static function ( $im, int $x, int $y, int $w, int $h, int $r, int $colour ): void {
		imagefilledrectangle( $im, $x + $r, $y, $x + $w - $r, $y + $h, $colour );
		imagefilledrectangle( $im, $x, $y + $r, $x + $w, $y + $h - $r, $colour );
		$d = $r * 2;
		imagefilledellipse( $im, $x + $r, $y + $r, $d, $d, $colour );
		imagefilledellipse( $im, $x + $w - $r, $y + $r, $d, $d, $colour );
		imagefilledellipse( $im, $x + $r, $y + $h - $r, $d, $d, $colour );
		imagefilledellipse( $im, $x + $w - $r, $y + $h - $r, $d, $d, $colour );
	};

	/*
	 * La marque, en version fond sombre : bulle blanche en réserve, œil
	 * orange, clin d'œil et sourire en navy. Le contour est obtenu en
	 * superposant une bulle navy plus petite sur la bulle blanche.
	 */
	$mark_x = 500;
	$mark_y = 96;
	$mark_w = 200;
	$mark_h = 160;
	$stroke = 13;

	$rounded( $img, $mark_x, $mark_y, $mark_w, $mark_h, 40, $white );
	// Queue de la bulle.
	imagefilledpolygon(
		$img,
		array(
			$mark_x + 70,
			$mark_y + $mark_h - 6,
			$mark_x + 130,
			$mark_y + $mark_h - 6,
			$mark_x + 88,
			$mark_y + $mark_h + 44,
		),
		$white
	);
	$rounded(
		$img,
		$mark_x + $stroke,
		$mark_y + $stroke,
		$mark_w - ( $stroke * 2 ),
		$mark_h - ( $stroke * 2 ),
		28,
		$navy
	);

	// Antenne et son point.
	imagesetthickness( $img, 10 );
	imageline( $img, $mark_x + 96, $mark_y - 4, $mark_x + 96, $mark_y - 34, $white );
	imagefilledellipse( $img, $mark_x + 96, $mark_y - 46, 26, 26, $orange );

	// Œil orange, clin d'œil blanc, sourire blanc.
	$rounded( $img, $mark_x + 46, $mark_y + 52, 18, 38, 9, $orange );
	imagesetthickness( $img, 11 );
	imageline( $img, $mark_x + 148, $mark_y + 52, $mark_x + 126, $mark_y + 71, $white );
	imageline( $img, $mark_x + 126, $mark_y + 71, $mark_x + 148, $mark_y + 90, $white );
	imagearc( $img, $mark_x + 100, $mark_y + 96, 78, 54, 20, 160, $white );

	// Étincelles.
	imagesetthickness( $img, 9 );
	imageline( $img, $mark_x + 214, $mark_y + 4, $mark_x + 240, $mark_y - 22, $orange );
	imageline( $img, $mark_x + 228, $mark_y + 40, $mark_x + 258, $mark_y + 30, $orange );
	imageline( $img, $mark_x + 204, $mark_y - 24, $mark_x + 214, $mark_y - 46, $orange );

	/*
	 * Le mot-symbole, avec le A final en orange : on mesure « FAMM » pour
	 * savoir où poser le « A » sans le superposer.
	 */
	$size  = 104;
	$box   = imagettfbbox( $size, 0, $font_bold, 'FAMMA' );
	$full  = $box[2] - $box[0];
	$box   = imagettfbbox( $size, 0, $font_bold, 'FAMM' );
	$stem  = $box[2] - $box[0];
	$start = (int) ( ( $width - $full ) / 2 );
	$base  = 420;

	imagettftext( $img, $size, 0, $start, $base, $white, $font_bold, 'FAMM' );
	imagettftext( $img, $size, 0, $start + $stem, $base, $orange, $font_bold, 'A' );

	// Baseline française.
	$tagline = 'Il y a toujours quelque chose de nouveau.';
	$box     = imagettfbbox( 32, 0, $font_regular, $tagline );
	imagettftext( $img, 32, 0, (int) ( ( $width - ( $box[2] - $box[0] ) ) / 2 ), 486, $muted, $font_regular, $tagline );

	// Promesse commerciale, séparée par un filet orange.
	imagesetthickness( $img, 4 );
	imageline( $img, (int) ( $width / 2 ) - 60, 528, (int) ( $width / 2 ) + 60, 528, $orange );

	$promise = 'Livraison en Tunisie  ·  Paiement à la livraison';
	$box     = imagettfbbox( 27, 0, $font_bold, $promise );
	imagettftext( $img, 27, 0, (int) ( ( $width - ( $box[2] - $box[0] ) ) / 2 ), 580, $white, $font_bold, $promise );

	if ( ! imagepng( $img, $out, 9 ) ) {
		echo "ECHEC : ecriture du PNG impossible.\n";
		imagedestroy( $img );
		return;
	}
	imagedestroy( $img );

	echo "Image de partage generee :\n";
	echo '  ' . basename( $out ) . ' — ' . $width . 'x' . $height . ', ' . round( filesize( $out ) / 1024 ) . " Ko\n";
	echo "  Baseline arabe omise : GD ne fait pas la liaison des lettres arabes.\n";
} )();
