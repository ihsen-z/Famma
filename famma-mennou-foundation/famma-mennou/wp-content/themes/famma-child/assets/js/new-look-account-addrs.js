/**
 * FAMMA — carnet d'adresses (case acct_addrs).
 *
 * - « Modifier » / « Ajouter une adresse » remplissent la feuille avec
 *   l'adresse choisie avant que le script de l'étape C ne l'ouvre (écoute
 *   en phase de capture).
 * - Gouvernorat obligatoire, contrôlé avant l'envoi (famma-core le refait).
 * - « Supprimer » : la carte s'efface, puis le formulaire part.
 * - Le message « Adresse enregistrée / supprimée » se retire après 5 s ; il
 *   quitte l'adresse de la page pour qu'un rechargement ne le remontre pas.
 * Sans script, les liens ouvrent la feuille dans la page et les boutons
 * envoient leur formulaire.
 */
( function () {
	'use strict';

	var doc = document;
	var sheet = doc.getElementById( 'famma-acct-sheet' );
	var form = sheet && sheet.querySelector( '.famma-acct-adform' );
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var cfg = window.fammaAddrs || {};

	// Lien suivi sans script (?famma_ad_edit=…) : la feuille arrive ouverte
	// dans la page ; avec le script, elle devient une vraie feuille.
	if ( sheet && sheet.open && 'function' === typeof sheet.showModal ) {
		sheet.close();
		sheet.showModal();
	}

	if ( form ) {
		var title = doc.getElementById( 'famma-acct-sheet-t' );
		var state = form.querySelector( '#famma_addr_state' );
		var line = form.querySelector( '#famma_addr_line' );
		var def = form.querySelector( '[name="famma_addr_def"]' );
		var idIn = form.querySelector( '[name="famma_addr_id"]' );
		var err = doc.getElementById( 'famma_addr_state-e' );
		var first = ! doc.querySelector( '.famma-acct-ads .famma-acct-ad' );

		var setBad = function ( bad ) {
			var row = state.closest( '.famma-acct-f' );
			row.classList.toggle( 'is-bad', bad );
			state.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
			if ( err ) {
				err.hidden = ! bad;
				err.textContent = bad ? String( cfg.gov || '' ) : '';
			}
		};

		doc.addEventListener( 'click', function ( e ) {
			var link = e.target.closest && e.target.closest( '[data-famma-ad-edit]' );

			if ( ! link ) {
				return;
			}

			var id = link.getAttribute( 'data-famma-ad-edit' );
			var card = id ? doc.querySelector( '[data-famma-ad="' + id + '"]' ) : null;
			var lbl = card ? card.getAttribute( 'data-lbl' ) : 'home';
			var radio = form.querySelector( '[name="famma_addr_lbl"][value="' + lbl + '"]' );

			idIn.value = id || '';
			state.value = card ? card.getAttribute( 'data-state' ) : '';
			line.value = card ? card.getAttribute( 'data-addr' ) : '';
			def.checked = card ? '1' === card.getAttribute( 'data-def' ) : first;
			if ( radio ) {
				radio.checked = true;
			}
			if ( title ) {
				title.textContent = title.getAttribute( card ? 'data-edit' : 'data-new' ) || title.textContent;
			}
			setBad( false );
		}, true );

		state.addEventListener( 'change', function () {
			if ( state.value ) {
				setBad( false );
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			if ( state.value ) {
				return;
			}

			e.preventDefault();
			setBad( true );
			state.focus();

			if ( ! reduce && state.animate ) {
				state.closest( '.famma-acct-f' ).animate(
					[ { transform: 'translateX(0)' }, { transform: 'translateX(-7px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' } ],
					{ duration: 360 }
				);
			}
		} );
	}

	// Supprimer : la carte s'efface avant l'envoi.
	doc.addEventListener( 'submit', function ( e ) {
		var btn = e.submitter || e.target.querySelector( '[data-famma-ad-del]' );

		if ( reduce || ! btn || ! btn.hasAttribute( 'data-famma-ad-del' ) || e.target.dataset.leaving ) {
			return;
		}

		var card = e.target.closest( '.famma-acct-ad' );

		if ( ! card ) {
			return;
		}

		e.preventDefault();
		e.target.dataset.leaving = '1';
		card.classList.add( 'is-leaving' );
		window.setTimeout( function () {
			e.target.submit();
		}, 260 );
	} );

	// Message : retiré après 5 s, et l'adresse de la page nettoyée.
	var toast = doc.querySelector( '.famma-acct-adtoast' );

	if ( toast ) {
		if ( window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.delete( 'famma_ad' );
			url.searchParams.delete( 'famma_ad_id' );
			window.history.replaceState( null, '', url.toString() );
		}

		window.setTimeout( function () {
			toast.classList.add( 'is-out' );
		}, 5000 );
	}
}() );
