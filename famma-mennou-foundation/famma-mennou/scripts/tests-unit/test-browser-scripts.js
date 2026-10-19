/**
 * Lot 5, flux B — the browser scripts of famma-core run for real in a Node VM:
 * - B2 (S3-a): fammaEventId() draws a different eventID for every page view,
 *   crypto.randomUUID first, then crypto.getRandomValues, then clock and chance;
 *   two visitors served the same cached page get two different IDs (I1);
 * - B2 (T3.2, I3): the "once per session" wrapper of InitiateCheckout and
 *   OrderPlaced emits once, and still emits when sessionStorage is blocked;
 * - B6 (R12): the UTM capture script writes famma_utm with the UTM, fbclid and
 *   external referrer, and never replaces a cookie that already carries UTM.
 * The scripts are read from the PHP sources (Tracking::EVENT_ID_SCRIPT,
 * Tracking::UTM_SCRIPT, Tracking_Events::ONCE_SCRIPT), never copied here.
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

/** Valeur d'une constante PHP entre apostrophes, déséchappée comme PHP. */
function phpConst(source, name) {
  const m = source.match(new RegExp(`const ${name} = '((?:[^'\\\\]|\\\\.)*)';`));
  assert.ok(m, `constante ${name} introuvable`);
  return m[1].replace(/\\(['\\])/g, '$1');
}
const EVENT_ID = phpConst(core, 'EVENT_ID_SCRIPT');
const UTM = phpConst(core, 'UTM_SCRIPT');
const ONCE = phpConst(events, 'ONCE_SCRIPT');

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

console.log(`RESULT ${passed}/${passed + failed} scénarios navigateur réussis${failed ? `, ${failed} ÉCHEC(S)` : ''}. Aucun réseau utilisé.`);
if (failed) process.exitCode = 1;
