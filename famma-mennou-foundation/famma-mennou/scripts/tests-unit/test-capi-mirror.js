/**
 * Lot 59 (Q21, Q22) — relais navigateur du doublon serveur et empreinte du téléphone.
 *
 * Les scripts sont produits par le vrai PHP (même binaire et mêmes options que le
 * lanceur : FAMMA_PHP, FAMMA_PHP_ARGS) puis exécutés dans une VM Node :
 * - avec le journal des visiteurs (cas courant) : le relais ne fait AUCUNE requête, ses
 *   événements partent dans l'envoi du journal, sous `m` : une seule requête par page (I4) ;
 * - sans journal : PageView + ViewContent relayés en UN seul envoi une fois le DOM prêt,
 *   chacun avec l'eventID passé à fbq ; sortie de page = envoi immédiat, jamais deux fois ;
 * - AddToCart en AJAX (liste) : même eventID et même valeur pour fbq et le relais ;
 * - doublon coupé (relais absent) : la page tourne, rien n'est relayé ;
 * - express : numéro valide → empreinte SHA-256 de « 216 » + 8 chiffres (celle de la CAPI)
 *   en mémoire et dans l'onglet, jointe au relais ; InitiateCheckout relayé avec son eventID ;
 *   au chargement suivant, fammaXid() la passe à fbq('init') en `ph` ; jamais le numéro en clair.
 * Aucune requête réelle, numéros synthétiques.
 *
 * Usage : node scripts/tests-unit/test-capi-mirror.js
 */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const root = path.join(__dirname, '../..');
const php = process.env.FAMMA_PHP || 'php';
const phpArgs = (process.env.FAMMA_PHP_ARGS || '').trim().split(/\s+/).filter(Boolean);
const sha256 = (s) => crypto.createHash('sha256').update(s, 'latin1').digest('hex');

/** Scripts de la page produit 501 (22 TND), du relais et de fammaXid, rendus par le vrai PHP. */
const fromPhp = JSON.parse(execFileSync(php, [...phpArgs, '-r', [
  'function rest_url($p = "") { return "https://qa.invalid/wp-json/" . $p; }',
  'require $argv[1] . "/scripts/tests-unit/bootstrap-browser.php";',
  'lb_reset(); $GLOBALS["wc_test_is_product"] = true; $GLOBALS["wc_test_current_product"] = new WC_Product(501, "", "Masque", "masque", 22.0);',
  'Famma_Unit_Harness::bare("\\\\Famma\\\\Core\\\\Tracking")->enqueue_tracking(); Famma_Unit_Harness::bare("\\\\Famma\\\\Core\\\\Tracking_Events")->enqueue_events();',
  'echo json_encode(array("pixels" => lb_inline("famma-pixels"), "relay" => \\Famma\\Core\\Capi_Mirror_Script::browser_script(), "xid" => \\Famma\\Core\\Capi_External_Id::browser_script(), "ajax" => \\Famma\\Core\\Tracking_Events::AJAX_LISTENER_SCRIPT));',
].join(' '), '--', root], { encoding: 'utf8' }));

const journal = fs.readFileSync(path.join(root, 'wp-content/plugins/famma-core/assets/js/visitor-journal.js'), 'utf8');

/** Navigateur minimal : minuteries manuelles, sendBeacon espionné, stockage d'onglet. */
function page(options = {}) {
  const beacons = [], fbq = [], timers = [], winL = {}, docL = {}, store = new Map(Object.entries(options.storage || {}));
  class Blob { constructor(parts, opts) { this.text = parts.join(''); this.type = opts && opts.type; } }
  const document = {
    cookie: 'famma_vid=3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21', readyState: options.readyState || 'complete', visibilityState: 'visible', body: {}, referrer: '',
    documentElement: { scrollHeight: 1000 }, querySelectorAll: () => [],
    addEventListener(t, f) { (docL[t] = docL[t] || []).push(f); },
  };
  const local = new Map();
  const context = {
    document, Blob, JSON, Date, Math, String, Number, Object, Array, RegExp, URL, URLSearchParams, Uint8Array, parseFloat, parseInt, decodeURIComponent, encodeURIComponent,
    innerHeight: 800, scrollY: 0, performance: { getEntriesByType: () => [] },
    localStorage: { getItem: (k) => (local.has(k) ? local.get(k) : null), setItem: (k, v) => local.set(k, String(v)) },
    location: { href: 'https://qa.invalid/produit/masque/?utm_source=fb', protocol: 'https:', search: '?utm_source=fb', pathname: '/produit/masque/', hostname: 'qa.invalid' },
    navigator: { sendBeacon(url, blob) { beacons.push({ url, body: JSON.parse(blob.text), type: blob.type }); return true; } },
    crypto: crypto.webcrypto,
    sessionStorage: { getItem: (k) => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, String(v)) },
    setTimeout(cb) { timers.push(cb); return timers.length; }, clearTimeout(id) { timers[id - 1] = null; },
    setInterval() { return 0; }, clearInterval() {},
    addEventListener(t, f) { (winL[t] = winL[t] || []).push(f); },
    fbq(...args) { fbq.push(args); },
  };
  context.window = context;
  vm.createContext(context);
  const run = (code) => vm.runInContext(code, context);
  if (options.relay !== false) run(fromPhp.relay);
  run(fromPhp.pixels);
  if (options.journal) {
    context.fammaJournal = { endpoint: 'https://qa.invalid/wp-json/famma/v1/collect', pageType: 'product', productId: 501, days: 180 };
    run(journal);
  }
  return {
    context, beacons, fbq, store, run,
    tick() { timers.splice(0).forEach((cb) => cb && cb()); },
    leave() { (winL.pagehide || []).forEach((f) => f()); },
    ready() { document.readyState = 'interactive'; (docL.DOMContentLoaded || []).forEach((f) => f()); },
    track: (name) => fbq.filter((c) => 'track' === c[0] && name === c[1]),
  };
}

let passed = 0, failed = 0;
function test(name, run) {
  try { run(); passed++; console.log('PASS: ' + name); } catch (e) { failed++; console.log('FAIL: ' + name + '\n     ' + String(e && e.message).split('\n').join('\n     ')); }
}

test('I4 — journal présent : le relais ne fait aucune requête ; le doublon part dans l’envoi du journal (UNE requête par page)', () => {
  const p = page({ journal: true });
  p.tick();
  assert.equal(p.beacons.length, 0, 'aucun envoi propre au relais');
  p.leave();
  assert.equal(p.beacons.length, 1, 'une seule requête pour la page');
  const sent = p.beacons[0];
  assert.equal(sent.url, 'https://qa.invalid/wp-json/famma/v1/collect');
  assert.deepEqual(sent.body.e.map((e) => e.n), ['page_view', 'page_leave']);
  assert.deepEqual(sent.body.m.e.map((e) => e.n), ['PageView', 'ViewContent']);
  assert.equal(sent.body.m.e[0].id, p.track('PageView')[0][3].eventID);
  assert.equal(sent.body.m.e[1].v, 22, 'valeur du Pixel jointe');
  p.leave();
  assert.equal(p.beacons.length, 1, 'rien deux fois');
});

test('sans journal, page encore en chargement : envoi propre seulement une fois le DOM prêt', () => {
  const p = page({ readyState: 'loading' });
  p.tick();
  assert.equal(p.beacons.length, 0);
  p.ready();
  assert.equal(p.beacons.length, 1);
  assert.equal(p.beacons[0].url, 'https://qa.invalid/wp-json/famma/v1/meta-relay');
});

test('sans journal : PageView + ViewContent relayés en UN seul envoi après le délai de regroupement', () => {
  const p = page();
  assert.equal(p.beacons.length, 0, 'rien avant le délai');
  p.tick();
  assert.equal(p.beacons.length, 1);
  assert.equal(p.beacons[0].url, 'https://qa.invalid/wp-json/famma/v1/meta-relay');
  assert.equal(p.beacons[0].type, 'text/plain');
  assert.deepEqual(p.beacons[0].body.e.map((e) => e.n), ['PageView', 'ViewContent']);
});

test('même eventID que le Pixel pour chaque événement relayé', () => {
  const p = page();
  p.tick();
  const [pv, vc] = p.beacons[0].body.e;
  assert.equal(pv.id, p.track('PageView')[0][3].eventID);
  assert.equal(vc.id, p.track('ViewContent')[0][3].eventID);
  assert.notEqual(pv.id, vc.id);
  assert.deepEqual(vc.i, [[501, 1]]);
  assert.deepEqual(pv.i, []);
  assert.equal(pv.u, 'https://qa.invalid/produit/masque/?utm_source=fb');
  assert.ok(pv.a >= 0 && pv.a < 5000, 'âge relatif, pas l’heure du navigateur');
});

test('sortie de page avant le délai : envoi immédiat, puis plus rien (une fois par affichage)', () => {
  const p = page();
  p.leave();
  assert.equal(p.beacons.length, 1);
  p.tick(); p.leave();
  assert.equal(p.beacons.length, 1);
  p.run('fammaRelay("PageView",' + JSON.stringify(p.beacons[0].body.e[0].id) + ');');
  p.tick();
  assert.equal(p.beacons.length, 1, 'même nom + eventID jamais relayé deux fois');
});

test('AddToCart en AJAX : même eventID pour fbq et le relais, ID WooCommerce et quantité', () => {
  const p = page();
  p.tick();
  const handlers = {};
  p.context.jQuery = () => ({ on: (evt, cb) => { handlers[evt] = cb; } });
  p.run(fromPhp.ajax.replace('%s', '["content_type","content_ids","contents","value","currency"]'));
  const attrs = { 'data-famma-content-id': '501', 'data-product_id': '501', 'data-famma-value': '22', 'data-quantity': '2', 'data-famma-currency': 'TND' };
  handlers.added_to_cart({}, null, null, [{ getAttribute: (k) => (k in attrs ? attrs[k] : null) }]);
  p.tick();
  const atc = p.track('AddToCart');
  assert.equal(atc.length, 1);
  const relayed = p.beacons[1].body.e[0];
  assert.equal(relayed.n, 'AddToCart');
  assert.equal(relayed.id, atc[0][3].eventID);
  assert.deepEqual(relayed.i, [[501, 2]]);
  assert.equal(relayed.v, 44, 'même valeur que fbq (22 × 2)');
  assert.equal(relayed.v, atc[0][2].value);
});

test('doublon coupé (relais absent) : la page tourne, fbq part, rien n’est relayé', () => {
  const p = page({ relay: false });
  p.tick(); p.leave();
  assert.equal(p.beacons.length, 0);
  assert.equal(p.track('PageView').length, 1);
  assert.equal(p.track('ViewContent').length, 1);
});

test('empreinte du téléphone dans l’onglet : jointe au relais ; valeur non conforme ignorée', () => {
  const h = sha256('21620123456');
  const ok = page({ storage: { famma_ph: h } });
  ok.tick();
  assert.equal(ok.beacons[0].body.ph, h);
  const bad = page({ storage: { famma_ph: '21620123456' } });
  bad.tick();
  assert.equal(bad.beacons[0].body.ph, undefined);
});

// ── Formulaire express (vrai express-order.js) ───────────────────────────────
const express = fs.readFileSync(path.join(root, 'wp-content/plugins/famma-core/assets/js/express-order.js'), 'utf8');
function element(value = '', attrs = {}) {
  const classes = new Set();
  return {
    value, attrs: { ...attrs }, listeners: {}, hidden: false, disabled: false, children: [], textContent: '', innerHTML: '',
    classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c), toggle: (c, on) => { if (on) classes.add(c); else classes.delete(c); } },
    addEventListener(k, cb) { (this.listeners[k] = this.listeners[k] || []).push(cb); },
    removeEventListener() {},
    fire(k) { (this.listeners[k] || []).slice().forEach((cb) => cb({ target: this, preventDefault() {} })); },
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); }, removeAttribute(k) { delete this.attrs[k]; },
    setCustomValidity() {}, focus() {}, scrollIntoView() {}, dispatchEvent() { return true; },
    closest() { return null; }, appendChild(c) { this.children.push(c); return c; }, querySelector() { return null; }, querySelectorAll() { return []; },
  };
}
function expressPage(options = {}) {
  const fbq = [], relayed = [], store = new Map();
  const phone = element('', { 'aria-describedby': 'famma-express-billing-phone-error' });
  const inputs = { billing_first_name: element('Client Fixture'), billing_phone: phone, billing_state: element('TN-51'), famma_quantity: element(options.qty || '1', { min: '1' }),
    famma_hp_site: element(), payment_method: element('cod'), billing_country: element('TN'), famma_product_id: element('123') };
  const map = { '.famma-express__submit': element(), '.famma-express__notices': element(), '.famma-express__info': element(), '.famma-express__total': element('', { 'data-unit-price': '79' }) };
  Object.entries(inputs).forEach(([k, v]) => { map[`[name="${k}"]`] = v; });
  const form = element();
  form.querySelector = (s) => map[s] || null;
  form.querySelectorAll = (s) => (s === 'input, select, textarea, button' ? Object.values(inputs) : []);
  form.reportValidity = () => true;
  const window = {
    innerHeight: 800, scrollBy() {}, location: { href: '' }, setTimeout() { return 0; }, clearTimeout() {},
    sessionStorage: { getItem: (k) => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, String(v)) },
    crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000001' },
    fammaEventId: () => 'evt-ic-1',
    fbq: (...a) => fbq.push(a),
    fammaRelay: (...a) => relayed.push(a),
  };
  const xid = { window: {}, document: { cookie: '' }, location: { protocol: 'https:' }, Uint8Array, Math, String, decodeURIComponent };
  xid.window = xid; vm.createContext(xid); vm.runInContext(fromPhp.xid, xid);
  window.fammaSha256 = xid.fammaSha256;
  const fammaExpress = {
    nonceUrl: '/nonce', prepareUrl: '/prepare', expressUrl: '/express', statusUrl: '/status', currency: 'TND', phoneError: 'x', stateError: 'x', nameError: 'x', sizeError: 'x', genericError: 'x',
    price: { decimals: 3, decimal: ',', thousand: ' ', format: '%2$s %1$s', symbol: 'DT' }, recapLabels: {},
    earlyCheckout: { pid: 123, unit: 79, params: { content_ids: ['123'], content_type: 'product', currency: 'TND', value: 79, contents: [{ id: '123', quantity: 1 }] } },
  };
  const document = { body: element(), documentElement: element(), activeElement: null, querySelector: (s) => (s === '.famma-express__form' ? form : null), getElementById: () => element(), createElement: () => element(), dispatchEvent: () => true };
  vm.runInNewContext(express, { document, window, fammaExpress, Uint8Array, Date, JSON, Math, FormData: class { append() {} }, Event: class {}, CustomEvent: class {}, fetch: () => new Promise(() => {}) }, { filename: 'express-order.js' });
  return { window, fbq, relayed, store, form, xid, type(v) { phone.value = v; phone.fire('input'); } };
}

test('express : numéro invalide → aucune empreinte, rien en mémoire ni dans l’onglet', () => {
  const t = expressPage();
  ['2', '20 12', '12 345 678'].forEach((v) => t.type(v));
  assert.equal(t.window.fammaPh, undefined);
  assert.equal(t.store.get('famma_ph'), undefined);
});

test('express : numéro valide → SHA-256 de 216 + 8 chiffres (format de la CAPI), en mémoire et dans l’onglet, jamais en clair', () => {
  const t = expressPage();
  t.type('+216 20 123 456');
  assert.equal(t.window.fammaPh, sha256('21620123456'));
  assert.equal(t.store.get('famma_ph'), sha256('21620123456'));
  assert.ok(![...t.store.values()].some((v) => v.includes('20123456')), 'aucun numéro en clair stocké');
});

test('express : InitiateCheckout relayé une fois, même eventID que fbq, article et quantité', () => {
  const t = expressPage({ qty: '2' });
  t.type('20 123 456'); t.type('20 123 457');
  const ic = t.fbq.filter((c) => 'InitiateCheckout' === c[1]);
  assert.equal(ic.length, 1);
  assert.equal(t.relayed.length, 1);
  assert.equal(JSON.stringify(t.relayed[0].slice(0, 3)), JSON.stringify(['InitiateCheckout', ic[0][3].eventID, [[123, 2]]]));
  assert.equal(t.relayed[0][3], ic[0][2].value, 'même valeur que le Pixel (M3)');
});

test('ph haché passé au Pixel : au chargement suivant, fammaXid() donne {external_id, ph} à fbq(init)', () => {
  const t = expressPage();
  t.type('20 123 456');
  const next = { window: {}, document: { cookie: 'famma_vid=3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21' }, location: { protocol: 'https:' }, Uint8Array, Math, String, decodeURIComponent,
    sessionStorage: { getItem: (k) => t.store.get(k) || null } };
  next.window = next; vm.createContext(next); vm.runInContext(fromPhp.xid, next);
  const data = next.fammaXid();
  assert.equal(data.ph, sha256('21620123456'));
  assert.equal(data.external_id, sha256('3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21'));
  next.sessionStorage = { getItem: () => null };
  assert.deepEqual(Object.keys(next.fammaXid()), ['external_id'], 'sans saisie, external_id seul comme avant');
});

console.log(`RESULT ${passed}/${passed + failed}`);
process.exitCode = failed ? 1 : 0;
