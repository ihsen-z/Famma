/**
 * FAMMA — gouvernorat proposé d'après la ville tapée (0.3.9, partagé depuis
 * 0.3.48 entre le formulaire express et la page commande).
 *
 * Les noms viennent de la liste officielle des délégations (`fammaPlaces`,
 * voir `Governorate_Places`) et des intitulés de la liste elle-même
 * (`data-alt` : nom arabe). Le script ne fait que proposer : il ne remplit
 * que si le gouvernorat n'a pas été choisi à la main, l'annonce, et le client
 * peut toujours le changer.
 *
 * Expose `window.fammaGovernoratePlaces.attach( select, input, opts )`.
 */
( function () {
	'use strict';

	/**
	 * Forme de comparaison d'un texte : minuscules, sans accents, voyelles
	 * arabes ni tatwil, alif/ta marbouta/alif maqsoura unifiés, « l' » et
	 * « d' » ôtés, ponctuation en espaces, articles le/la/el en « el ».
	 *
	 * @param {string} text Texte tapé ou nom de lieu.
	 * @return {string} Mots séparés par une espace.
	 */
	function foldPlace( text ) {
		var value = String( text || '' ).toLowerCase();
		if ( value.normalize ) {
			value = value.normalize( 'NFD' );
		}
		value = value
			.replace( /[̀-ًͯ-ٰٟـ]/g, '' )
			.replace( /[آأإٱ]/g, 'ا' )
			.replace( /ة/g, 'ه' )
			.replace( /[ىئ]/g, 'ي' )
			.replace( /ؤ/g, 'و' )
			.replace( /ڨ/g, 'ق' )
			.replace( /ڤ/g, 'ف' )
			.replace( /(^|[^a-z])[ld]['’ʼ`´]/g, '$1 ' )
			.replace( /['’ʼ`´]/g, '' )
			.replace( /[^a-z0-9ء-ي]+/g, ' ' );
		return value.split( ' ' ).filter( Boolean ).map( function ( word ) {
			return 'le' === word || 'la' === word ? 'el' : word;
		} ).join( ' ' );
	}

	/**
	 * Index des noms de lieux : forme de comparaison → code du gouvernorat.
	 *
	 * Un nom porté par deux gouvernorats ne désigne aucun des deux : il est
	 * écarté. « La Marsa » se tape aussi « Marsa » : l'article est facultatif
	 * quand le reste compte au moins cinq lettres (« El Ksar » ne devient pas
	 * « Ksar », trop courant).
	 *
	 * @param {Object} places Noms par code de gouvernorat.
	 * @param {Element} select Liste des gouvernorats.
	 * @return {{keys: Object, longest: number}}
	 */
	function placeIndex( places, select ) {
		var keys = Object.create( null );
		var longest = 1;

		function add( key, code ) {
			if ( ! key ) {
				return;
			}
			if ( key in keys && keys[ key ] !== code ) {
				keys[ key ] = '';
				return;
			}
			keys[ key ] = code;
			longest = Math.max( longest, key.split( ' ' ).length );
		}

		function addName( name, code ) {
			var key = foldPlace( name );
			add( key, code );
			if ( 0 === key.indexOf( 'el ' ) && key.length >= 8 ) {
				add( key.slice( 3 ), code );
			}
		}

		Array.prototype.forEach.call( select.options, function ( option ) {
			var names = places && Array.isArray( places[ option.value ] ) ? places[ option.value ] : [];
			if ( ! option.value ) {
				return;
			}
			addName( option.text, option.value );
			addName( option.getAttribute( 'data-alt' ), option.value );
			names.forEach( function ( name ) {
				addName( name, option.value );
			} );
		} );

		return { keys: keys, longest: longest };
	}

	/**
	 * Gouvernorat reconnu dans le texte, ou chaîne vide.
	 *
	 * Le nom le plus long l'emporte (« Hammam Sousse » avant « Sousse ») ; à
	 * longueur égale, deux gouvernorats différents (« rue de Tunis, Sfax »)
	 * ne donnent rien plutôt qu'un choix au hasard.
	 *
	 * @param {string} text Texte tapé.
	 * @param {{keys: Object, longest: number}} index Index de `placeIndex`.
	 * @return {string} Code du gouvernorat.
	 */
	function placeFor( text, index ) {
		var words = foldPlace( text ).split( ' ' ).filter( Boolean );
		var size;
		var start;
		var code;
		var hit;

		for ( size = Math.min( index.longest, words.length ); size > 0; size-- ) {
			code = '';
			for ( start = 0; start + size <= words.length; start++ ) {
				hit = index.keys[ words.slice( start, start + size ).join( ' ' ) ];
				if ( ! hit ) {
					continue;
				}
				if ( code && code !== hit ) {
					return '';
				}
				code = hit;
			}
			if ( code ) {
				return code;
			}
		}
		return '';
	}

	/** Événement `change` qui remonte, y compris dans un vieux navigateur intégré. */
	function fireChange( field ) {
		var event;
		try {
			event = new Event( 'change', { bubbles: true } );
		} catch ( error ) {
			event = document.createEvent( 'HTMLEvents' );
			event.initEvent( 'change', true, false );
		}
		field.dispatchEvent( event );
	}

	/**
	 * Branche la proposition sur une liste et un champ d'adresse.
	 *
	 * @param {HTMLSelectElement} select Liste des gouvernorats.
	 * @param {HTMLInputElement}  input  Champ où le client tape sa ville.
	 * @param {Object}   [opts]            Réglages.
	 * @param {Object}   [opts.places]     Noms par gouvernorat (défaut : `window.fammaPlaces`).
	 * @param {Function} [opts.canFill]    Renvoie faux quand il ne faut rien remplir (envoi en cours…).
	 * @param {string}   [opts.found]      Phrase d'annonce, `%s` = gouvernorat.
	 * @param {string}   [opts.flashClass] Classe posée 1,6 s sur la liste remplie.
	 * @return {void}
	 */
	function attach( select, input, opts ) {
		var o = opts || {};
		var index = placeIndex( o.places || window.fammaPlaces || {}, select );
		var hint = document.getElementById( select.getAttribute( 'aria-describedby' ) || '' );
		var hintText = hint ? hint.textContent : '';
		// Déjà rempli au chargement (retour arrière, remplissage du navigateur) : c'est un choix du client.
		var byHand = '' !== select.value;
		var filling = false;
		var timer = 0;
		var flashTimer = 0;

		function suggest() {
			var code;
			window.clearTimeout( timer );
			if ( byHand || select.disabled || ( o.canFill && ! o.canFill() ) ) {
				return;
			}
			code = placeFor( input.value, index );
			if ( ! code || code === select.value ) {
				return;
			}
			select.value = code;
			if ( select.value !== code ) {
				return;
			}
			filling = true;
			try {
				fireChange( select );
			} finally {
				filling = false;
			}
			if ( hint && o.found ) {
				hint.textContent = o.found.replace( '%s', select.options[ select.selectedIndex ].text );
			}
			if ( o.flashClass && select.classList ) {
				window.clearTimeout( flashTimer );
				select.classList.add( o.flashClass );
				flashTimer = window.setTimeout( function () {
					select.classList.remove( o.flashClass );
				}, 1600 );
			}
		}

		select.addEventListener( 'change', function () {
			if ( filling ) {
				return;
			}
			// Remis sur « Gouvernorat » : la proposition reprend.
			byHand = '' !== select.value;
			if ( hint ) {
				hint.textContent = hintText;
			}
		} );

		input.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( suggest, 600 );
		} );
		// Fin de saisie, collage, remplissage du navigateur : sans attendre.
		input.addEventListener( 'change', suggest );
	}

	window.fammaGovernoratePlaces = { attach: attach, find: function ( text, select, places ) {
		return placeFor( text, placeIndex( places || window.fammaPlaces || {}, select ) );
	} };
}() );
