/**
 * Nouveau look — fiche produit, lot 47 : boîte d'achat de la maquette.
 *
 * Trois briques, chacune seulement si elle est servie :
 * - `pdp_buy` (`.famma-buy` posé par le thème) : taille, quantité et total
 *   d'abord, barre d'avancement, « Commander » qui déplie téléphone, nom et
 *   adresse ; taille oubliée : les tailles tremblent ; un seul reflet sur le
 *   bouton après 10 s sans action ;
 * - `pdp_form` (classe du corps) : coche verte sur chaque champ bien rempli,
 *   total qui rebondit ;
 * - `pdp_dock` (`.famma-sticky-cta--dock`) : la barre du bas suit la taille
 *   et le total.
 *
 * Lot 49 : `pdp_altsize` (`.famma-altsize`, rangé sous les tailles),
 * `pdp_labels` (intitulés « Téléphone / الهاتف » réduits à la langue de la
 * page), étincelles quand une taille est choisie (`pdp_buy`), éclair orange
 * sur le gouvernorat choisi (`pdp_form`).
 *
 * Le formulaire express (famma-core) n'est jamais modifié dans son
 * fonctionnement : ses nœuds sont seulement rangés autrement, ses contrôles
 * et son envoi restent les siens. Les règles ci-dessous (téléphone, nom,
 * gouvernorat) ne servent qu'à l'affichage, jamais à bloquer.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.famma-express__form' );

	if ( ! form ) {
		return;
	}

	var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var checks = document.body.classList.contains( 'famma-nl-pdp-form' );
	var buy = document.querySelector( '.famma-buy' );
	var dock = document.querySelector( '.famma-sticky-cta--dock' );
	var sizes = form.querySelector( '[data-famma-sizes]' );
	var total = form.querySelector( '.famma-express__total' );
	var phone = form.querySelector( '[name="billing_phone"]' );
	var name = form.querySelector( '[name="billing_first_name"]' );
	var state = form.querySelector( '[name="billing_state"]' );
	var open = ! buy;
	var rtl = 'rtl' === document.documentElement.getAttribute( 'dir' );
	var alt = document.querySelector( '.famma-altsize' );

	// Lien « autre taille » (pdp_altsize) : sous les tailles, comme la maquette.
	if ( alt && sizes ) {
		sizes.appendChild( alt );
	}

	/* ---------- Règles d'affichage (mêmes que l'envoi) ---------- */

	function digits( value ) {
		var d = String( value || '' ).replace( /[٠-٩۰-۹]/g, function ( c ) {
			var code = c.charCodeAt( 0 );
			return String( code - ( code >= 0x06f0 ? 0x06f0 : 0x0660 ) );
		} ).replace( /\D+/g, '' );

		if ( 13 === d.length && 0 === d.indexOf( '00216' ) ) {
			d = d.slice( 5 );
		} else if ( 11 === d.length && 0 === d.indexOf( '216' ) ) {
			d = d.slice( 3 );
		}
		return d;
	}

	var fields = [
		{ el: phone, ok: function () { return /^[2-9]\d{7}$/.test( digits( phone.value ) ); } },
		{ el: name, ok: function () { return String( name.value || '' ).trim().length >= 3; } },
		{ el: state, ok: function () { return '' !== String( state.value || '' ); } },
	].filter( function ( f ) {
		return !! f.el;
	} );

	function size() {
		var c = sizes ? sizes.querySelector( 'input[type="radio"]:checked' ) : null;
		return c ? String( c.value ) : '';
	}

	function pop( el ) {
		if ( calm || ! el ) {
			return;
		}
		el.classList.remove( 'is-pop' );
		void el.offsetWidth;
		el.classList.add( 'is-pop' );
	}

	/* ---------- Effets (lot 49) ---------- */

	// Étincelles autour d'un élément, en coordonnées d'écran (physiques, comme en arabe).
	function burst( el ) {
		if ( calm || ! el || ! el.animate ) {
			return;
		}

		var r = el.getBoundingClientRect();
		var x = r.left + r.width / 2;
		var y = r.top + r.height / 2;
		var spark = function ( i ) {
			var a = ( i / 6 ) * Math.PI * 2 + 0.3;
			var d = 20 + ( i % 3 ) * 8;
			var turn = ' rotate(' + ( ( a * 180 ) / Math.PI + 90 ) + 'deg)';
			var at = function ( k ) {
				return 'translate(' + ( x + Math.cos( a ) * k ) + 'px,' + ( y + Math.sin( a ) * k ) + 'px)' + turn;
			};
			var s = document.createElement( 'i' );

			s.className = 'famma-spk';
			s.setAttribute( 'aria-hidden', 'true' );
			document.body.appendChild( s );
			s.animate(
				[
					{ transform: at( 0 ) + ' scaleY(.2)', opacity: 1 },
					{ transform: at( d ) + ' scaleY(1)', opacity: 1, offset: 0.55 },
					{ transform: at( d + 9 ) + ' scaleY(.2)', opacity: 0 },
				],
				{ duration: 520, easing: 'cubic-bezier(.22,1,.36,1)' }
			);
			// Retrait par minuterie : une animation d'onglet en arrière-plan peut ne jamais finir.
			window.setTimeout( function () {
				s.remove();
			}, 600 );
		};

		for ( var i = 0; i < 6; i++ ) {
			spark( i );
		}
	}

	/*
	 * Intitulés bilingues « Téléphone / الهاتف » : seulement la langue de la
	 * page. Les textes de famma-core le sont déjà côté serveur ; restent le
	 * titre (« Commander / أطلب توا ») et un intitulé de taille saisi à la main.
	 * Un vrai couple seulement : de l'arabe à droite, pas à gauche.
	 */
	if ( document.body.classList.contains( 'famma-nl-pdp-labels' ) ) {
		var arabic = /[\u0600-\u06ff]/;

		Array.prototype.forEach.call( ( form.closest( '.famma-express' ) || form ).querySelectorAll( '.famma-express__title, .famma-express__legend, label, legend' ), function ( el ) {
			Array.prototype.forEach.call( el.childNodes, function ( node ) {
				var parts = 3 === node.nodeType ? node.textContent.split( ' / ' ) : [];

				if ( 2 !== parts.length || ! arabic.test( parts[ 1 ] ) || arabic.test( parts[ 0 ] ) || ! parts[ 0 ].trim() ) {
					return;
				}

				node.textContent = ( rtl ? parts[ 1 ] : parts[ 0 ] ).trim() + ' ';
			} );
		} );
	}

	/* ---------- Coches vertes (pdp_form) ---------- */

	function paintChecks() {
		fields.forEach( function ( f ) {
			var wrap = f.el.closest( '.famma-express__field' );

			if ( wrap ) {
				wrap.classList.toggle( 'is-good', f.ok() );
			}
		} );
	}

	/* ---------- Barre d'avancement (pdp_buy) ---------- */

	var bar = buy ? buy.querySelector( '.famma-buy__pg-b i' ) : null;
	var line = buy ? buy.querySelector( '.famma-buy__pg-t' ) : null;

	function choice() {
		var label = buy.getAttribute( 'data-size-label' ) || '';
		return sizes ? ( label + ' ' + size() ).trim() : buy.getAttribute( 'data-chosen' ) || '';
	}

	function progress() {
		if ( ! buy ) {
			return;
		}

		var n = fields.filter( function ( f ) {
			return f.ok();
		} ).length;
		var pct = 0.25;
		var msg;

		if ( sizes && ! size() ) {
			msg = buy.getAttribute( 'data-pg0' );
		} else if ( ! open && 0 === n ) {
			pct = 0.5;
			msg = buy.getAttribute( 'data-pg1' );
		} else {
			pct = 0.5 + ( 0.5 * n ) / ( fields.length || 1 );
			msg = fields.length && n === fields.length ? buy.getAttribute( 'data-pg3' ) : buy.getAttribute( 'data-pg2' );
		}

		msg = String( msg || '' ).split( '{choix}' ).join( choice() ).split( '{n}' ).join( String( fields.length - n ) );
		bar.style.transform = 'scaleX(' + pct + ')';

		if ( line.textContent !== msg ) {
			line.textContent = msg;
			pop( line );
		}
	}

	/* ---------- Barre du bas (pdp_dock) ---------- */

	function paintDock() {
		if ( ! dock ) {
			return;
		}

		var info = dock.querySelector( '.famma-sticky-cta__line' );
		var label = dock.querySelector( '.famma-sticky-cta__label' );
		var derja = dock.querySelector( '.famma-sticky-cta__ar' );
		var price = dock.querySelector( '.famma-sticky-cta__price' );
		var chosen = size();
		var pick = info ? info.getAttribute( 'data-pick' ) : '';

		if ( info && chosen ) {
			info.textContent = ( ( info.getAttribute( 'data-size-label' ) || '' ) + ' ' + chosen ).trim();
		}
		if ( label ) {
			var picking = !! ( sizes && ! chosen && pick );

			label.textContent = picking ? pick : label.getAttribute( 'data-order' );
			// La ligne derja dit « Commander » : cachée tant que le bouton dit « Choisir ma taille ».
			if ( derja ) {
				derja.hidden = picking;
			}
		}
		if ( price && total ) {
			price.textContent = total.textContent;
		}
	}

	/* ---------- Boîte d'achat repliée (pdp_buy) ---------- */

	var opener = buy ? buy.querySelector( '.famma-buy__open' ) : null;
	var order = form.querySelector( '.famma-express__group[role="group"]' );
	var details = form.querySelector( 'fieldset.famma-express__group' );

	function nudge() {
		var err = sizes.querySelector( '.famma-express__error' );
		var options = sizes.querySelector( '.famma-express__size-options' );
		var first = sizes.querySelector( 'input[type="radio"]' );

		if ( err ) {
			err.textContent = buy.getAttribute( 'data-size-err' ) || '';
			err.hidden = ! err.textContent;
		}
		if ( options && ! calm ) {
			options.classList.remove( 'is-nudge' );
			void options.offsetWidth;
			options.classList.add( 'is-nudge' );
		}
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	}

	function openForm( focus ) {
		if ( open ) {
			return;
		}
		open = true;
		form.classList.remove( 'is-closed' );
		form.classList.add( 'is-open' );
		opener.setAttribute( 'aria-expanded', 'true' );
		progress();

		// Tout de suite, dans le geste : iOS n'ouvre le clavier qu'à cette condition.
		if ( focus && phone && ! phone.disabled ) {
			phone.focus( { preventScroll: true } );
		}
	}

	function goBuy() {
		if ( sizes && ! size() ) {
			nudge();
		} else {
			openForm( true );
		}
	}

	/*
	 * Formulaire verrouillé par le formulaire express (commande en suspens
	 * reprise après rechargement : champs désactivés, « Réessayer ») : il
	 * reste ouvert, sinon la reprise serait impossible.
	 */
	function locked() {
		return !! ( ( phone && phone.disabled ) || form.querySelector( '[name="famma_quantity"]:disabled' ) );
	}

	if ( buy && order && details && opener && locked() ) {
		open = true;
		buy.remove();
		buy = null;
	} else if ( buy && order && details && opener ) {
		var pg = buy.querySelector( '.famma-buy__pg' );
		var cta = buy.querySelector( '.famma-buy__cta' );
		var after = buy.querySelector( '.famma-buy__after' );

		// Taille, quantité et total d'abord (ordre du DOM = ordre de lecture et de tabulation).
		form.insertBefore( pg, form.firstChild );
		form.insertBefore( order, details );
		form.insertBefore( cta, details );
		if ( after ) {
			form.insertBefore( after, details );
		}
		buy.remove();
		form.classList.add( 'is-closed' );
		opener.setAttribute( 'aria-expanded', 'false' );

		// Taille choisie : indicateur qui glisse sous la pastille.
		var options = sizes ? sizes.querySelector( '.famma-express__size-options' ) : null;
		var ind = null;

		if ( options ) {
			ind = document.createElement( 'span' );
			ind.className = 'famma-buy__ind';
			ind.setAttribute( 'aria-hidden', 'true' );
			options.insertBefore( ind, options.firstChild );
		}

		var placeInd = function () {
			var c = sizes ? sizes.querySelector( 'input[type="radio"]:checked' ) : null;
			var pill = c ? c.closest( '.famma-express__size' ) : null;

			if ( ! ind ) {
				return;
			}
			if ( ! pill ) {
				ind.style.opacity = '0';
				return;
			}
			// Mesures physiques (offsetLeft) : la translation l'est aussi, en arabe comme en français.
			ind.style.width = pill.offsetWidth + 'px';
			ind.style.height = pill.offsetHeight + 'px';
			ind.style.transform = 'translate(' + pill.offsetLeft + 'px,' + pill.offsetTop + 'px)';
			ind.style.opacity = '1';
		};

		if ( sizes ) {
			sizes.addEventListener( 'change', function () {
				var err = sizes.querySelector( '.famma-express__error' );

				if ( err && err.textContent === buy.getAttribute( 'data-size-err' ) ) {
					err.hidden = true;
				}
				placeInd();
				opener.classList.remove( 'is-shine' );

				var c = sizes.querySelector( 'input[type="radio"]:checked' );

				burst( c ? c.closest( '.famma-express__size' ) : null );
			} );
			window.addEventListener( 'resize', placeInd, { passive: true } );
			placeInd();
		}

		opener.addEventListener( 'click', goBuy );

		// Un seul reflet après 10 s sans action, jamais en boucle.
		if ( ! calm ) {
			window.setTimeout( function () {
				if ( ! open && ! size() && ! document.hidden ) {
					opener.classList.add( 'is-shine' );
				}
			}, 10000 );
		}

		/*
		 * Barre du bas et bouton « Commander » de « Quand payez-vous ? » : même
		 * geste. En capture, avant `sticky-cta.js` : le formulaire est déjà
		 * ouvert quand celui-ci pose le curseur dans le champ Nom (clavier iOS).
		 * Taille manquante : les tailles tremblent une fois le défilement fait.
		 */
		document.addEventListener( 'click', function ( e ) {
			var t = e.target && e.target.closest ? e.target.closest( '.famma-sticky-cta__button, .famma-pay__btn' ) : null;

			if ( ! t || open ) {
				return;
			}
			if ( sizes && ! size() ) {
				window.setTimeout( nudge, calm ? 0 : 450 );
			} else {
				openForm( true );
			}
		}, true );

		/*
		 * Envoi pendant que la boîte est fermée (Entrée ou « OK » du clavier
		 * dans la quantité) : jamais d'envoi vers des champs cachés, on ouvre.
		 * En capture sur le document, avant l'écouteur du formulaire express.
		 */
		document.addEventListener( 'submit', function ( e ) {
			if ( e.target === form && ! open ) {
				e.preventDefault();
				e.stopPropagation();
				goBuy();
			}
		}, true );

		progress();
	}

	/* ---------- Écoute du formulaire ---------- */

	function refresh() {
		if ( checks ) {
			paintChecks();
		}
		progress();
		paintDock();
	}

	form.addEventListener( 'input', refresh );
	form.addEventListener( 'change', refresh );

	// Gouvernorat choisi (liste ou recherche) : éclair orange sur le champ.
	if ( checks && state && ! calm ) {
		state.addEventListener( 'change', function () {
			// Le champ du gouvernorat lui-même : la recherche (combobox) si elle remplace la liste.
			var box = state.closest( '.famma-express__address-box' ) || state.parentNode;
			var gov = box.querySelector( '[role="combobox"]' ) || state;

			if ( '' !== state.value ) {
				gov.classList.remove( 'is-found' );
				void gov.offsetWidth;
				gov.classList.add( 'is-found' );
			}
		} );
	}

	// Le total est réécrit par le formulaire express quand la quantité change.
	if ( total && 'MutationObserver' in window ) {
		new window.MutationObserver( function () {
			if ( checks ) {
				pop( total );
			}
			paintDock();
		} ).observe( total, { childList: true, characterData: true, subtree: true } );
	}

	refresh();
}() );
