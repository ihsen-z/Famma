/* famma-child : copie allégée de assets/js/new-look-pdp-sections.js (scripts/build-pdp-min.py), ne pas modifier. */
( function () {
'use strict';
var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
function openFor( hash ) {
if ( ! hash || hash.length < 2 ) {
return;
}
var target;
try {
target = document.querySelector( hash );
} catch ( e ) {
return;
}
var item = target && target.closest ? target.closest( '.famma-dts__item' ) : null;
if ( ! item ) {
return;
}
item.open = true;
target.scrollIntoView( { behavior: calm ? 'auto' : 'smooth', block: 'start' } );
}
if ( document.querySelector( '.famma-dts' ) ) {
openFor( window.location.hash );
window.addEventListener( 'hashchange', function () {
openFor( window.location.hash );
} );
document.addEventListener( 'click', function ( e ) {
var link = e.target && e.target.closest ? e.target.closest( 'a[href^="#"]' ) : null;
if ( link && link.hash && link.hash === window.location.hash ) {
openFor( link.hash );
}
} );
}
var pay = document.querySelector( '.famma-pay' );
if ( ! pay ) {
return;
}
var road = pay.querySelector( '.famma-pay__road' );
var base = pay.querySelector( '.famma-pay__base' );
var fill = pay.querySelector( '.famma-pay__fill' );
var parcel = pay.querySelector( '.famma-pay__parcel' );
var sum = pay.querySelector( '.famma-pay__sum' );
var stops = pay.querySelectorAll( '.famma-pay__stop' );
var cta = pay.querySelector( '.famma-pay__btn' );
if ( cta ) {
cta.addEventListener( 'click', function ( e ) {
var title = document.getElementById( 'famma-express-title' );
var form = ( title && title.closest( '.famma-express' ) ) || document.querySelector( '.famma-product form.cart' );
if ( ! form ) {
return;
}
e.preventDefault();
form.scrollIntoView( { behavior: calm ? 'auto' : 'smooth', block: 'start' } );
} );
}
var totalText = sum ? sum.getAttribute( 'data-total' ) || '' : '';
var zeroText = sum ? sum.getAttribute( 'data-zero' ) || '' : '';
var value = sum ? parseFloat( sum.getAttribute( 'data-value' ) ) : NaN;
var decimals = sum ? parseInt( sum.getAttribute( 'data-decimals' ), 10 ) || 0 : 0;
var sep = sum ? sum.getAttribute( 'data-sep' ) || '.' : '.';
function write( v, done ) {
if ( ! sum ) {
return;
}
if ( done || isNaN( value ) ) {
sum.textContent = v > 0 ? totalText : zeroText;
return;
}
sum.textContent = totalText.replace( /\d[\d\s.,\u00a0\u202f]*\d|\d/, ( Math.round( v ) ).toFixed( decimals ).replace( '.', sep ) );
}
if ( calm || stops.length < 2 || ! road || ! base || ! fill || ! parcel ) {
pay.classList.add( 'is-arrived' );
write( 1, true );
return;
}
pay.classList.add( 'is-live' );
var path = null;
var arrived = false;
var shown = 0;
var raf = 0;
var ticking = false;
function meterTo( to ) {
window.cancelAnimationFrame( raf );
if ( isNaN( value ) ) {
write( to, true );
return;
}
var from = shown;
var target = to ? value : 0;
var t0 = window.performance.now();
( function step( now ) {
var k = Math.max( 0, Math.min( 1, ( now - t0 ) / 900 ) );
var e = 1 - Math.pow( 1 - k, 3 );
shown = from + ( target - from ) * e;
if ( k < 1 ) {
write( shown, false );
raf = window.requestAnimationFrame( step );
} else {
write( target, true );
}
}( t0 ) );
}
function centre( el, box ) {
var r = el.getBoundingClientRect();
return { x: r.left + ( r.width / 2 ) - box.left, y: r.top + ( r.height / 2 ) - box.top };
}
function measure() {
var nodes = road.querySelectorAll( '.famma-pay__node' );
var box = road.getBoundingClientRect();
var a = centre( nodes[ 0 ], box );
var b = centre( nodes[ nodes.length - 1 ], box );
var horiz = Math.abs( b.x - a.x ) > Math.abs( b.y - a.y );
var left = Math.min( a.x, b.x );
var top = Math.min( a.y, b.y );
path = { a: a, b: b, horiz: horiz };
[ base, fill ].forEach( function ( el ) {
el.style.left = ( horiz ? left : left - 1.5 ) + 'px';
el.style.top = ( horiz ? top - 1.5 : top ) + 'px';
el.style.width = ( horiz ? Math.abs( b.x - a.x ) : 3 ) + 'px';
el.style.height = ( horiz ? 3 : Math.abs( b.y - a.y ) ) + 'px';
} );
base.style.setProperty( '--famma-ang', horiz ? '90deg' : '180deg' );
if ( horiz ) {
fill.style.transformOrigin = b.x > a.x ? 'left center' : 'right center';
} else {
fill.style.transformOrigin = 'center top';
}
}
function setRoad( p ) {
if ( ! path ) {
return;
}
fill.style.transform = path.horiz ? 'scaleX(' + p + ')' : 'scaleY(' + p + ')';
parcel.style.transform = 'translate(' + ( path.a.x + ( ( path.b.x - path.a.x ) * p ) ) + 'px,' + ( path.a.y + ( ( path.b.y - path.a.y ) * p ) ) + 'px)';
stops.forEach( function ( stop, i ) {
stop.classList.toggle( 'is-on', p >= ( i / ( stops.length - 1 ) ) - 0.015 );
} );
var done = p >= 0.995;
if ( done !== arrived ) {
arrived = done;
pay.classList.toggle( 'is-arrived', done );
meterTo( done ? 1 : 0 );
}
}
function tick() {
ticking = false;
var vh = window.innerHeight;
var r = road.getBoundingClientRect();
setRoad( Math.max( 0, Math.min( 1, ( ( vh * 0.72 ) - r.top ) / ( r.height + ( vh * 0.2 ) ) ) ) );
}
function onScroll() {
if ( ! ticking ) {
ticking = true;
window.requestAnimationFrame( tick );
}
}
function start() {
measure();
tick();
}
window.addEventListener( 'scroll', onScroll, { passive: true } );
window.addEventListener( 'resize', start );
window.addEventListener( 'load', start );
start();
}() );
