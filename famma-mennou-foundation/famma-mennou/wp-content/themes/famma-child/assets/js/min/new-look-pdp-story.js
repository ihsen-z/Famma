/* famma-child : copie allégée de assets/js/new-look-pdp-story.js (scripts/build-pdp-min.py), ne pas modifier. */
( function () {
'use strict';
var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
var txt = document.querySelector( '.famma-story__txt' );
var chips = document.querySelector( '.famma-story__chips' );
var echo = document.querySelector( '.famma-story__echo' );
var items = Array.prototype.slice.call( document.querySelectorAll( '.famma-ana__item' ) );
var spots = document.querySelectorAll( '.famma-ana__spot' );
var cam = document.querySelector( '.famma-ana__cam' );
var sticky = document.querySelector( '.famma-ana__sticky' );
var words = [];
var current = 0;
var ticking = false;
function split( node ) {
Array.prototype.slice.call( node.childNodes ).forEach( function ( child ) {
if ( 1 === child.nodeType ) {
split( child );
return;
}
if ( 3 !== child.nodeType ) {
return;
}
var frag = document.createDocumentFragment();
child.textContent.split( /(\s+)/ ).forEach( function ( part ) {
if ( ! part ) {
return;
}
if ( /^\s+$/.test( part ) ) {
frag.appendChild( document.createTextNode( part ) );
return;
}
var w = document.createElement( 'span' );
w.className = 'famma-story__w';
w.textContent = part;
words.push( w );
frag.appendChild( w );
} );
node.replaceChild( frag, child );
} );
}
if ( txt && ! calm ) {
split( txt );
txt.classList.add( 'is-scrub' );
}
if ( chips && echo ) {
var pick = echo.getAttribute( 'data-pick' ) || '';
chips.addEventListener( 'click', function ( e ) {
var chip = e.target && e.target.closest ? e.target.closest( '.famma-story__chip' ) : null;
if ( ! chip ) {
return;
}
Array.prototype.forEach.call( chips.querySelectorAll( '.famma-story__chip' ), function ( c ) {
c.setAttribute( 'aria-pressed', c === chip ? 'true' : 'false' );
} );
var phrase = chip.getAttribute( 'data-phrase' ) || '';
var at = pick.indexOf( '{situation}' );
var before = at >= 0 ? pick.slice( 0, at ) : ( pick ? pick + ' ' : '' );
var after = at >= 0 ? pick.slice( at + 11 ) : '';
var b = document.createElement( 'b' );
b.textContent = phrase;
echo.textContent = '';
echo.appendChild( document.createTextNode( before ) );
echo.appendChild( b );
echo.appendChild( document.createTextNode( after ) );
if ( ! calm ) {
echo.classList.remove( 'is-new' );
void echo.offsetWidth;
echo.classList.add( 'is-new' );
}
} );
}
function setAna( i ) {
current = i;
items.forEach( function ( x, k ) {
x.classList.toggle( 'is-on', k === i );
} );
Array.prototype.forEach.call( spots, function ( x, k ) {
x.classList.toggle( 'is-on', k === i );
} );
if ( calm || ! cam || ! spots[ i ] ) {
return;
}
var x = parseFloat( spots[ i ].style.getPropertyValue( '--x' ) ) || 50;
var y = parseFloat( spots[ i ].style.getPropertyValue( '--y' ) ) || 50;
var clamp = function ( v ) {
return Math.max( -25, Math.min( 25, v ) );
};
cam.style.transform = 'translate(' + clamp( ( 50 - x ) * 1.5 ) + '%,' + clamp( ( 50 - y ) * 1.5 ) + '%) scale(1.5)';
}
function tick() {
ticking = false;
var vh = window.innerHeight;
var n = -1;
var on = -1;
if ( words.length ) {
var r = txt.getBoundingClientRect();
if ( r.bottom > -50 && r.top < vh ) {
var p = Math.max( 0, Math.min( 1, ( vh * 0.82 - r.top ) / ( r.height + vh * 0.3 ) ) );
n = Math.round( p * words.length );
}
}
if ( items.length ) {
var list = items[ 0 ].parentNode.getBoundingClientRect();
if ( list.bottom > 0 && list.top < vh ) {
var line = vh * 0.55;
var photo = sticky ? sticky.getBoundingClientRect() : null;
if ( photo && photo.left < list.right && photo.right > list.left ) {
line = Math.max( line, photo.bottom + ( vh - photo.bottom ) * 0.5 );
}
on = 0;
items.forEach( function ( item, k ) {
if ( item.getBoundingClientRect().top < line ) {
on = k;
}
} );
}
}
if ( n >= 0 ) {
words.forEach( function ( w, k ) {
w.classList.toggle( 'is-on', k < n );
} );
}
if ( on >= 0 && on !== current ) {
setAna( on );
}
}
function onScroll() {
if ( ! ticking ) {
ticking = true;
window.requestAnimationFrame( tick );
}
}
if ( items.length ) {
setAna( 0 );
}
if ( words.length || items.length ) {
window.addEventListener( 'scroll', onScroll, { passive: true } );
window.addEventListener( 'resize', onScroll, { passive: true } );
tick();
}
}() );
