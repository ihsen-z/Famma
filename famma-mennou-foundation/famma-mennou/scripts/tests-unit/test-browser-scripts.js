/**
 * Lot 5, flux B — the browser scripts of famma-core run for real in a Node VM:
 * - B2 (S3-a): fammaEventId() draws a different eventID for every page view,
 *   crypto.randomUUID first, then crypto.getRandomValues, then clock and chance;
 *   two visitors served the same cached page get two different IDs (I1);
 * - B2 (T3.2, I3): the "once per session" wrapper of InitiateCheckout and
 *   OrderPlaced emits once, and still emits when sessionStorage is blocked;
 * - B6 (R12): the UTM capture script writes famma_utm with the UTM, fbclid and
 *   external referrer, and never replaces a cookie that already carries UTM;
 * - T3.2 (AJAX list): the AddToCart listener of the product list binds once
 *   jQuery AND document.body exist, whatever the loading order: in the <head>
 *   (immediate loading, body still null), jQuery after the Pixel, deferred
 *   footer, jQuery delayed by LiteSpeed. Exactly one AddToCart per real add.
 * The scripts are read from the PHP sources (Tracking::EVENT_ID_SCRIPT,
 * Order_Source::UTM_SCRIPT, Tracking_Events::ONCE_SCRIPT and
 * Tracking_Events::AJAX_LISTENER_SCRIPT), never copied here.
 *
 * Usage : node scripts/tests-unit/test-browser-scripts.js
 */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
const { webcrypto } = require('node:crypto');

const plugin = path.join(__dirname, '../../wp-content/plugins/famma-core');
const core = fs.readFileSync(path.join(plugin, 'famma-core.php'), 'utf8');
const events = fs.readFileSync(path.join(plugin, 'includes/tracking-events.php'), 'utf8');
const source = fs.readFileSync(path.join(plugin, 'includes/order-source.php'), 'utf8');

/** Valeur d'une constante PHP entre apostrophes, déséchappée comme PHP. */
function phpConst(source, name) {
  const m = source.match(new RegExp(`const ${name} = '((?:[^'\\\\]|\\\\.)*)';`));
  assert.ok(m, `constante ${name} introuvable`);
  return m[1].replace(/\\(['\\])/g, '$1');
}
const EVENT_ID = phpConst(core, 'EVENT_ID_SCRIPT');
const UTM = phpConst(source, 'UTM_SCRIPT');
const ONCE = phpConst(events, 'ONCE_SCRIPT');
const AJAX = phpConst(events, 'AJAX_LISTENER_SCRIPT');

let passed = 0, failed = 0;
function test(name, run) {
  try { run(); passed++; console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + String(e && e.message).split('\n').join('\n     ')); }
}

/** Fenêtre minimale : crypto au choix, sessionStorage au choix. */
function browser(options = {}) {
  const window = {};
  if (options.crypto !== undefined) window.crypto = options.crypto;
  if (options.storage !== undefined) window.sessionStorage = options.storage;
  const context = { window, Uint8Array, Date, Math, String, JSON, calls: [] };
  vm.createContext(context);
  return context;
}
const uuidV4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

// ── B2 : eventID tiré dans le navigateur ─────────────────────────────────────
test('fammaEventId : crypto.randomUUID quand il existe', () => {
  const c = browser({ crypto: { randomUUID: () => webcrypto.randomUUID() } });
  vm.runInContext(EVENT_ID, c);
  const a = vm.runInContext('window.fammaEventId()', c);
  const b = vm.runInContext('window.fammaEventId()', c);
  assert.match(a, uuidV4);
  assert.notEqual(a, b, 'un identifiant par appel');
});

test('fammaEventId : repli crypto.getRandomValues → UUID v4 valide, jamais deux fois le même', () => {
  const c = browser({ crypto: { getRandomValues: a => webcrypto.getRandomValues(a) } });
  vm.runInContext(EVENT_ID, c);
  const seen = new Set();
  for (let i = 0; i < 2000; i++) {
    const id = vm.runInContext('window.fammaEventId()', c);
    assert.match(id, uuidV4);
    seen.add(id);
  }
  assert.equal(seen.size, 2000);
});

test('fammaEventId : sans crypto (vieux navigateur) → identifiant non vide et distinct', () => {
  const c = browser({});
  vm.runInContext(EVENT_ID, c);
  const ids = new Set(Array.from({ length: 500 }, () => vm.runInContext('window.fammaEventId()', c)));
  assert.equal(ids.size, 500);
  ids.forEach(id => assert.ok(id.length >= 10));
});

test('I1 : deux visiteurs servis par la même page en cache → deux eventID différents', () => {
  const html = EVENT_ID + 'calls.push(window.fammaEventId());';
  const one = browser({ crypto: webcrypto });
  const two = browser({ crypto: webcrypto });
  vm.runInContext(html, one);
  vm.runInContext(html, two);
  assert.notEqual(one.calls[0], two.calls[0]);
});

test('fammaEventId déjà défini (script inséré deux fois) : jamais redéfini', () => {
  const c = browser({ crypto: webcrypto });
  vm.runInContext('window.fammaEventId=function(){return "keep";};' + EVENT_ID, c);
  assert.equal(vm.runInContext('window.fammaEventId()', c), 'keep');
});

// ── B2 : une seule fois par session (InitiateCheckout, OrderPlaced) ──────────
function storage(blocked = false) {
  const data = new Map();
  return {
    data,
    getItem(k) { if (blocked) throw Error('blocked'); return data.has(k) ? data.get(k) : null; },
    setItem(k, v) { if (blocked) throw Error('blocked'); data.set(k, String(v)); },
  };
}
const once = (key, body) => ONCE.replace('%1$s', JSON.stringify(key)).replace('%2$s', body);

test('T3.2 / I3 : émis une fois, puis plus jamais dans la session (rechargement, retour arrière)', () => {
  const store = storage();
  const script = once('famma_ic_abc', 'calls.push("IC");return true;');
  for (let i = 0; i < 3; i++) {
    const c = browser({ storage: store });
    vm.runInContext(script, c);
    if (0 === i) assert.deepEqual(c.calls, ['IC']);
    else assert.deepEqual(c.calls, [], 'rechargement : rien');
  }
  assert.equal(store.data.get('famma_ic_abc'), '1');
});

test('T3.2 : panier modifié → nouvelle clé, nouvel InitiateCheckout', () => {
  const store = storage();
  const c = browser({ storage: store });
  vm.runInContext(once('famma_ic_abc', 'calls.push("A");return true;'), c);
  vm.runInContext(once('famma_ic_def', 'calls.push("B");return true;'), c);
  assert.deepEqual(c.calls, ['A', 'B']);
});

test('Stockage bloqué : l’événement part quand même (jamais perdu)', () => {
  const store = storage(true);
  const c = browser({ storage: store });
  vm.runInContext(once('famma_orderplaced_7', 'calls.push("OP");return true;'), c);
  assert.deepEqual(c.calls, ['OP']);
});

test('Émetteur en échec (fbq absent) : rien n’est mémorisé, la prochaine page réessaie', () => {
  const store = storage();
  const c = browser({ storage: store });
  vm.runInContext(once('famma_orderplaced_8', 'calls.push("try");'), c);
  assert.equal(store.data.has('famma_orderplaced_8'), false);
});

// ── B6 : capture UTM indépendante du Pixel ───────────────────────────────────
function landing({ search = '', referrer = '', host = 'fammaminnou.tn', cookie = '' } = {}) {
  const writes = [];
  const context = {
    URLSearchParams, URL, JSON, String, decodeURIComponent, encodeURIComponent,
    location: { search, hostname: host },
    document: {
      referrer,
      get cookie() { return cookie; },
      set cookie(v) { writes.push(v); },
    },
  };
  vm.createContext(context);
  vm.runInContext(UTM, context);
  return writes.map(w => {
    const m = w.match(/^famma_utm=([^;]*);path=\/;max-age=2592000;SameSite=Lax$/);
    assert.ok(m, 'cookie famma_utm, 30 jours, Lax : ' + w);
    return JSON.parse(decodeURIComponent(m[1]));
  });
}
const utmCookie = obj => 'famma_utm=' + encodeURIComponent(JSON.stringify(obj));

test('R12 : arrivée d’une pub (UTM + fbclid) → UTM, fbclid et référent externe', () => {
  const w = landing({ search: '?utm_source=facebook&utm_medium=paid&utm_campaign=olympe&fbclid=IwAR1', referrer: 'https://l.facebook.com/l.php' });
  assert.deepEqual(w, [{ utm_source: 'facebook', utm_medium: 'paid', utm_campaign: 'olympe', fbclid: 1, ref: 'l.facebook.com' }]);
});

test('R12 : fbclid sans UTM, aucun cookie → {fbclid}', () => {
  assert.deepEqual(landing({ search: '?fbclid=IwAR2' }), [{ fbclid: 1 }]);
});

test('R12 : référent externe sans UTM → {ref}, « www. » retiré', () => {
  assert.deepEqual(landing({ referrer: 'https://www.google.com/' }), [{ ref: 'google.com' }]);
});

test('R12 : cookie avec UTM déjà présent → une visite sans UTM ne le remplace pas (_famma_utm_* inchangés)', () => {
  assert.deepEqual(landing({ referrer: 'https://www.google.com/', cookie: 'a=1; ' + utmCookie({ utm_source: 'facebook' }) }), []);
});

test('R12 : cookie sans UTM → remplacé par la dernière provenance', () => {
  assert.deepEqual(landing({ referrer: 'https://google.com/', cookie: utmCookie({ ref: 'bing.com' }) }), [{ ref: 'google.com' }]);
});

test('R12 : nouvelle campagne UTM → remplace toujours le cookie', () => {
  assert.deepEqual(landing({ search: '?utm_source=tiktok', cookie: utmCookie({ utm_source: 'facebook' }) }), [{ utm_source: 'tiktok' }]);
});

test('R12 : navigation interne ou accès direct → rien n’est écrit', () => {
  assert.deepEqual(landing({ referrer: 'https://www.fammaminnou.tn/boutique/' }), []);
  assert.deepEqual(landing({ referrer: 'https://fammaminnou.tn/' , host: 'www.fammaminnou.tn' }), []);
  assert.deepEqual(landing({}), []);
});

// ── T3.2 : AddToCart de la liste produits (AJAX), quel que soit l'ordre de chargement ──
const STANDARD = ['content_type', 'content_ids', 'contents', 'value', 'currency'];
const MINIMAL = ['content_type', 'content_ids', 'value', 'currency'];
const listener = keys => AJAX.replace('%s', JSON.stringify(keys));
const plain = x => JSON.parse(JSON.stringify(x));

/**
 * Page simulée : document.body nul tant que le DOM n'est pas prêt (script
 * dans le <head>), mini-jQuery qui pose les écouteurs sur l'élément réel (un
 * ensemble vide n'écoute rien, comme jQuery(null)), minuteries manuelles.
 */
function shop({ jquery = true, body = false, fbq = true } = {}) {
  const docL = {}, winL = {}, timers = new Map(), calls = [];
  let next = 1;
  const bodyEl = { handlers: {} };
  const fire = (map, type) => (map[type] || []).slice().forEach(f => f({ type }));
  function jQuery(el) {
    return { length: el ? 1 : 0, on(type, fn) { if (el) (el.handlers[type] = el.handlers[type] || []).push(fn); return this; } };
  }
  const document = { body: body ? bodyEl : null, addEventListener(t, f) { (docL[t] = docL[t] || []).push(f); } };
  const window = { addEventListener(t, f) { (winL[t] = winL[t] || []).push(f); }, fammaEventId: () => 'browser-uuid-' + (calls.length + 1) };
  if (jquery) window.jQuery = jQuery;
  if (fbq) window.fbq = (...a) => calls.push(plain(a));
  const context = {
    window, document, parseFloat, String, Date,
    setInterval: f => { const id = next++; timers.set(id, f); return id; },
    clearInterval: id => { timers.delete(id); },
  };
  vm.createContext(context);
  return {
    calls, timers, window,
    run(script) { vm.runInContext(script, context); },
    domReady() { document.body = bodyEl; fire(docL, 'DOMContentLoaded'); },
    load() { fire(winL, 'load'); },
    liteSpeedLoaded(on) { fire('window' === on ? winL : docL, 'DOMContentLiteSpeedLoaded'); },
    interact(type = 'pointerdown') { fire(winL, type); },
    addJQuery() { window.jQuery = jQuery; },
    tick(n = 1) { for (let i = 0; i < n; i++) [...timers.values()].forEach(f => f()); },
    bound() { return (bodyEl.handlers.added_to_cart || []).length; },
    // WooCommerce : $( document.body ).trigger( 'added_to_cart', [ fragments, cart_hash, $thisbutton ] ).
    added(attrs) {
      const button = attrs ? [{ getAttribute: n => (n in attrs ? attrs[n] : null) }] : undefined;
      (bodyEl.handlers.added_to_cart || []).forEach(h => h({ type: 'added_to_cart' }, {}, 'hash', button));
    },
  };
}
const BUTTON = { 'data-product_id': '16', 'data-famma-content-id': 'QA-L5-SKU', 'data-famma-value': '49', 'data-famma-currency': 'TND', 'data-quantity': '2' };
const EXPECTED = ['track', 'AddToCart', { content_type: 'product', content_ids: ['QA-L5-SKU'], contents: [{ id: 'QA-L5-SKU', quantity: 2 }], value: 98, currency: 'TND' }, { eventID: 'browser-uuid-1' }];

test('T3.2 liste AJAX, chargement immédiat (défaut) : script dans le <head>, jQuery déjà là, body nul → écouteur posé au DOM prêt, 1 AddToCart', () => {
  const p = shop({ jquery: true, body: false });
  p.run(listener(STANDARD));
  assert.equal(p.bound(), 0, 'rien n’est posé sur un ensemble vide');
  p.domReady();
  assert.equal(p.bound(), 1);
  p.added(BUTTON);
  assert.deepEqual(p.calls, [EXPECTED]);
});

test('T3.2 liste AJAX : jQuery imprimé après le Pixel → écouteur posé au DOM prêt', () => {
  const p = shop({ jquery: false, body: false });
  p.run(listener(STANDARD));
  p.addJQuery();
  p.domReady();
  p.added(BUTTON);
  assert.deepEqual(p.calls, [EXPECTED]);
});

test('T3.2 liste AJAX, chargement différé : pied de page, body et jQuery présents → posé tout de suite', () => {
  const p = shop({ jquery: true, body: true });
  p.run(listener(STANDARD));
  assert.equal(p.bound(), 1);
  p.added(BUTTON);
  assert.deepEqual(p.calls, [EXPECTED]);
});

test('T3.2 liste AJAX : jQuery retardé par LiteSpeed (après load) → posé à DOMContentLiteSpeedLoaded, sondage arrêté', () => {
  for (const on of ['document', 'window']) {
    const p = shop({ jquery: false, body: false });
    p.run(listener(STANDARD));
    p.domReady();
    p.load();
    assert.equal(p.bound(), 0);
    assert.equal(p.timers.size, 1, 'un sondage de secours tourne');
    p.addJQuery();
    p.liteSpeedLoaded(on);
    assert.equal(p.bound(), 1, 'posé sur ' + on);
    assert.equal(p.timers.size, 0, 'sondage arrêté une fois posé');
    p.added(BUTTON);
    assert.deepEqual(p.calls, [EXPECTED]);
  }
});

test('T3.2 liste AJAX : jQuery chargé tard, sans événement LiteSpeed → sondage borné, relancé à l’interaction', () => {
  const p = shop({ jquery: false, body: false });
  p.run(listener(STANDARD));
  p.domReady();
  p.load();
  p.tick(40);
  assert.equal(p.timers.size, 0, 'sondage borné (20 s), jamais infini');
  p.interact('scroll');
  assert.equal(p.timers.size, 1, 'relancé par une interaction');
  p.interact('pointerdown');
  assert.equal(p.timers.size, 1, 'jamais deux sondages à la fois');
  p.addJQuery();
  p.tick();
  assert.equal(p.bound(), 1);
  assert.equal(p.timers.size, 0);
  p.interact('keydown');
  assert.equal(p.timers.size, 0, 'plus rien après la pose');
  p.added(BUTTON);
  assert.deepEqual(p.calls, [EXPECTED]);
});

test('T3.2 liste AJAX : toutes les reprises déclenchées → un seul écouteur, un seul AddToCart par ajout', () => {
  const p = shop({ jquery: true, body: false });
  p.run(listener(STANDARD));
  p.domReady();
  p.load();
  p.liteSpeedLoaded('document');
  p.liteSpeedLoaded('window');
  p.interact();
  p.tick(5);
  assert.equal(p.bound(), 1);
  p.added(BUTTON);
  assert.equal(p.calls.length, 1);
});

test('T3.2 liste AJAX : fbq lu au moment de l’ajout (absent → rien, sans erreur ; présent ensuite → envoyé)', () => {
  const p = shop({ jquery: true, body: true, fbq: false });
  p.run(listener(STANDARD));
  assert.equal(p.bound(), 1, 'posé même si fbq n’est pas encore défini');
  p.added(BUTTON);
  assert.equal(p.calls.length, 0);
  p.window.fbq = (...a) => p.calls.push(plain(a));
  p.added(BUTTON);
  assert.deepEqual(p.calls, [EXPECTED]);
});

test('V5 profil minimal : l’AddToCart AJAX retire contents', () => {
  const p = shop({ jquery: true, body: true });
  p.run(listener(MINIMAL));
  p.added(BUTTON);
  assert.deepEqual(p.calls, [['track', 'AddToCart', { content_type: 'product', content_ids: ['QA-L5-SKU'], value: 98, currency: 'TND' }, { eventID: 'browser-uuid-1' }]]);
});

test('Liste AJAX : bouton sans identifiant ou absent → rien, sans erreur ; data-product_id en repli', () => {
  const p = shop({ jquery: true, body: true });
  p.run(listener(STANDARD));
  p.added(undefined);
  p.added({ 'data-famma-value': '49' });
  assert.equal(p.calls.length, 0);
  p.added({ 'data-product_id': '17', 'data-famma-value': '30', 'data-famma-currency': 'TND' });
  assert.deepEqual(p.calls[0][2], { content_type: 'product', content_ids: ['17'], contents: [{ id: '17', quantity: 1 }], value: 30, currency: 'TND' });
});

console.log(`RESULT ${passed}/${passed + failed} scénarios navigateur réussis${failed ? `, ${failed} ÉCHEC(S)` : ''}. Aucun réseau utilisé.`);
if (failed) process.exitCode = 1;
