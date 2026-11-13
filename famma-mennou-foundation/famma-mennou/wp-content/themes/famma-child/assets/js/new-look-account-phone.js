/**
 * FAMMA — création de compte par numéro, case acct_phone : contrôles de la
 * maquette (numéro tunisien à 8 chiffres, nom et prénom, 8 caractères),
 * coche verte, résumé des erreurs, jauge du mot de passe et adresse à
 * déplier. famma-core refait les mêmes contrôles à l'envoi : sans script, le
 * formulaire marche et les erreurs viennent du serveur.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.woocommerce-form-register' );
	var t = window.fammaAcctPhone || {};

	if ( ! form || ! document.getElementById( 'famma_phone' ) ) {
		return;
	}

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var alertSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>';

	// L'e-mail masqué ne doit rien bloquer.
	var mail = document.getElementById( 'reg_email' );

	if ( mail ) {
		mail.removeAttribute( 'required' );
		mail.removeAttribute( 'aria-required' );
	}

	function digits( v ) {
		// Chiffres arabes (٠-٩) acceptés, comme côté serveur.
		return String( v ).replace( /[٠-٩]/g, function ( c ) {
			return String( c.charCodeAt( 0 ) - 0x0660 );
		} ).replace( /\D/g, '' ).replace( /^(00216|216)(?=\d{8}$)/, '' );
	}

	var rules = {
		famma_phone: function ( v ) {
			var d = digits( v );
			if ( 8 !== d.length ) {
				return 'err_tel';
			}
			return /^[234579]/.test( d ) ? '' : 'err_tel_tn';
		},
		famma_name: function ( v ) {
			v = v.trim();
			return v.length >= 4 && v.split( /\s+/ ).length >= 2 ? '' : 'err_name';
		},
		reg_password: function ( v ) {
			return v.length >= 8 ? '' : 'err_pw';
		}
	};

	function errBox( input ) {
		var id = input.id + '-e';
		var box = document.getElementById( id );

		if ( ! box ) {
			box = document.createElement( 'span' );
			box.className = 'famma-acct-err';
			box.id = id;
			box.hidden = true;
			input.closest( '.form-row' ).appendChild( box );
			input.setAttribute( 'aria-describedby', ( ( input.getAttribute( 'aria-describedby' ) || '' ) + ' ' + id ).trim() );
		}

		return box;
	}

	function check( id, quiet ) {
		var input = document.getElementById( id );

		if ( ! input ) {
			return '';
		}

		var key = rules[ id ]( input.value );
		var row = input.closest( '.form-row' );
		var box = errBox( input );
		var msg = key ? String( t[ key ] || '' ) : '';

		row.classList.toggle( 'is-bad', !! key && ! quiet );
		row.classList.toggle( 'is-valid', ! key && '' !== input.value );
		input.setAttribute( 'aria-invalid', key && ! quiet ? 'true' : 'false' );
		box.hidden = ! key || !! quiet || '' === msg;

		if ( key && ! quiet ) {
			box.innerHTML = alertSvg;
			var span = document.createElement( 'span' );
			span.textContent = msg;
			box.appendChild( span );
		}

		return key;
	}

	Object.keys( rules ).forEach( function ( id ) {
		var input = document.getElementById( id );

		if ( ! input ) {
			return;
		}

		input.addEventListener( 'blur', function () {
			if ( input.value ) {
				check( id );
			}
		} );

		input.addEventListener( 'input', function () {
			check( id, ! input.closest( '.form-row' ).classList.contains( 'is-bad' ) );
		} );
	} );

	// Jauge du mot de passe, comme la maquette.
	var pw = document.getElementById( 'reg_password' );

	if ( pw ) {
		var meter = document.createElement( 'span' );
		var label = document.createElement( 'span' );

		meter.className = 'famma-acct-meter';
		meter.setAttribute( 'aria-hidden', 'true' );
		meter.innerHTML = '<i></i><i></i><i></i>';
		label.className = 'famma-acct-meter-t';
		label.id = 'reg_password-m';
		label.setAttribute( 'aria-live', 'polite' );

		var after = pw.closest( '.password-input' ) || pw;
		after.parentNode.insertBefore( label, after.nextSibling );
		after.parentNode.insertBefore( meter, label );
		pw.setAttribute( 'aria-describedby', ( ( pw.getAttribute( 'aria-describedby' ) || '' ) + ' reg_password-m' ).trim() );

		var colours = [ '', 'var(--famma-danger)', 'var(--famma-orange)', 'var(--famma-whatsapp)' ];

		pw.addEventListener( 'input', function () {
			var v = pw.value;
			var s = 0;

			if ( v ) {
				s = v.length >= 8 ? 1 : 0;
				if ( s && /\d/.test( v ) && /[a-zA-Z؀-ۿ]/.test( v ) ) {
					s++;
				}
				if ( s > 1 && ( v.length >= 12 || /[^a-zA-Z0-9]/.test( v ) ) ) {
					s++;
				}
				s = Math.max( 1, s );
			}

			Array.prototype.forEach.call( meter.children, function ( bar, k ) {
				bar.style.setProperty( '--c', colours[ s ] );
				bar.classList.toggle( 'on', k < s );
			} );

			label.textContent = s ? String( t[ 'pw_m' + s ] || '' ) : '';
		} );
	}

	// Envoi : résumé des erreurs en haut, comme la maquette.
	var sum = document.getElementById( 'famma-up-sum' );

	form.addEventListener( 'submit', function ( e ) {
		var bad = Object.keys( rules ).map( function ( id ) {
			return [ id, check( id ) ];
		} ).filter( function ( x ) {
			return x[ 1 ];
		} );

		if ( ! bad.length ) {
			if ( sum ) {
				sum.hidden = true;
			}
			return;
		}

		e.preventDefault();

		if ( ! sum ) {
			document.getElementById( bad[ 0 ][ 0 ] ).focus();
			return;
		}

		var head = String( t[ bad.length > 1 ? 'sum_hs' : 'sum_h' ] || '' ).replace( '{n}', String( bad.length ) );
		sum.textContent = '';

		if ( head ) {
			var h = document.createElement( 'h3' );
			h.textContent = head;
			sum.appendChild( h );
		}

		bad.forEach( function ( x ) {
			var a = document.createElement( 'a' );
			a.href = '#' + x[ 0 ];
			a.textContent = String( t[ x[ 1 ] ] || '' );
			a.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				document.getElementById( x[ 0 ] ).focus();
			} );
			sum.appendChild( a );
		} );

		sum.hidden = false;
		sum.focus();

		if ( ! reduce && form.animate ) {
			form.animate(
				[ { transform: 'translateX(0)' }, { transform: 'translateX(-7px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' } ],
				{ duration: 360 }
			);
		}
	} );

	// « Ajouter mon adresse maintenant ».
	var btn = form.querySelector( '.famma-acct-dbtn' );
	var disc = document.getElementById( 'famma-up-addr' );

	if ( btn && disc ) {
		btn.addEventListener( 'click', function () {
			var open = 'true' !== btn.getAttribute( 'aria-expanded' );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			disc.classList.toggle( 'is-open', open );

			if ( open ) {
				var first = disc.querySelector( 'select, input' );
				if ( first ) {
					window.setTimeout( function () {
						first.focus( { preventScroll: true } );
					}, reduce ? 0 : 320 );
				}
			}
		} );
	}

	// Valeurs renvoyées après une erreur du serveur : coches à jour.
	[ 'famma_phone', 'famma_name' ].forEach( function ( id ) {
		var input = document.getElementById( id );
		if ( input && input.value ) {
			check( id, true );
		}
	} );
}() );
