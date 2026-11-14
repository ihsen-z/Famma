/**
 * Onglet « Fiche FAMMA » (lot 46) : pose des repères au clic sur la photo.
 *
 * Chaque clic ajoute une ligne « gauche, haut » (en %) au champ « Place des
 * repères » ; le champ reste modifiable à la main et les points se redessinent
 * à chaque saisie. Sans ce script, le champ se remplit à la main.
 */
( function () {
	'use strict';

	var select = document.querySelector( '.famma-story-image' );
	var stage = document.querySelector( '.famma-story-stage' );
	var field = document.querySelector( 'textarea.famma-story-spots' );
	var clear = document.querySelector( '.famma-story-clear' );

	if ( ! select || ! stage || ! field ) {
		return;
	}

	var img = stage.querySelector( 'img' );
	var dots = stage.querySelector( '.famma-story-dots' );

	function spots() {
		return field.value.split( /\r?\n/ ).map( function ( line ) {
			var m = line.match( /^\s*(\d{1,3}(?:[.,]\d+)?)\s*[,;]\s*(\d{1,3}(?:[.,]\d+)?)\s*$/ );
			return m ? [ parseFloat( m[ 1 ].replace( ',', '.' ) ), parseFloat( m[ 2 ].replace( ',', '.' ) ) ] : null;
		} ).filter( Boolean );
	}

	function draw() {
		dots.textContent = '';
		spots().forEach( function ( s, i ) {
			var dot = document.createElement( 'li' );
			dot.textContent = String( i + 1 );
			// Coordonnées de la photo, qui n'est jamais retournée : gauche/haut physiques.
			dot.style.left = Math.min( 100, s[ 0 ] ) + '%';
			dot.style.top = Math.min( 100, s[ 1 ] ) + '%';
			dots.appendChild( dot );
		} );
	}

	function show() {
		var opt = select.options[ select.selectedIndex ];
		var src = opt ? opt.getAttribute( 'data-src' ) : '';

		stage.hidden = ! src;
		if ( src ) {
			img.src = src;
		}
		draw();
	}

	img.addEventListener( 'click', function ( e ) {
		var box = img.getBoundingClientRect();
		var x = Math.round( ( ( e.clientX - box.left ) / box.width ) * 1000 ) / 10;
		var y = Math.round( ( ( e.clientY - box.top ) / box.height ) * 1000 ) / 10;
		var text = field.value.replace( /\s+$/, '' );

		field.value = ( text ? text + '\n' : '' ) + x + ', ' + y;
		draw();
	} );

	if ( clear ) {
		clear.addEventListener( 'click', function () {
			field.value = '';
			draw();
		} );
	}

	select.addEventListener( 'change', show );
	field.addEventListener( 'input', draw );
	show();
}() );
