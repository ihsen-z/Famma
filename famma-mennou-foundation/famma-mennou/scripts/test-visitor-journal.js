#!/usr/bin/env node
/**
 * Journal des visites (visitor-journal.js) : tests isolés, sans navigateur ni réseau.
 * Lancer : node scripts/test-visitor-journal.js
 *
 * Le vrai script tourne dans un bac à sable `vm` ; les envois sendBeacon sont
 * capturés et relus. Couvre l'événement `form_error` (lot 23) : seuls des codes
 * de champ partent, jamais une valeur tapée.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../wp-content/plugins/famma-core/assets/js/visitor-journal.js'), 'utf8');

function setup(options = {}) {
  const listeners = {};
  const beacons = [];
  const on = (type, fn) => { (listeners[type] = listeners[type] || []).push(fn); };
  const fire = (type, event) => (listeners[type] || []).forEach(fn => fn(Object.assign({ type }, event)));
  const store = new Map();
  let sequence = 0;
  const document = {
    cookie: '', referrer: '', visibilityState: 'visible',
    addEventListener: on,
    documentElement: { scrollHeight: 1000 },
  };
  const window = {
    fammaJournal: { endpoint: '/wp-json/famma/v1/collect', pageType: 'product', productId: 123, days: 180, ...(options.inputs ? { inputs: options.inputs } : {}) },
    JSON, addEventListener: on, innerHeight: 800, scrollY: 0,
    crypto: { randomUUID: () => '00000000-0000-4000-8000-' + String(++sequence).padStart(12, '0') },
  };
  class Blob { constructor(parts) { this.text = parts.join(''); } }
  const sandbox = {
    window, document, JSON, Date, Math, Object, Array, String, RegExp, URL, URLSearchParams, Blob,
    location: { protocol: 'https:', pathname: '/produit/fixture/', search: '', hostname: 'example.test' },
    localStorage: { getItem: k => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, String(v)) },
    navigator: { sendBeacon: (url, blob) => { beacons.push({ url, body: JSON.parse(blob.text) }); return true; } },
    performance: { getEntriesByType: () => [] },
    setInterval: () => 0, setTimeout: () => 0, clearTimeout: () => {},
    crypto: window.crypto,
  };
  vm.runInNewContext(source, sandbox, { filename: 'visitor-journal.js' });
  const events = () => beacons.flatMap(b => b.body.e);
  const errors = () => events().filter(e => e.n === 'form_error');
  return { fire, beacons, events, errors };
}

let passed = 0, failed = 0;
function test(name, run) {
  try { run(); passed++; console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + String(e && e.message).split('\n').join('\n     ')); }
}

test('famma:express-error [state] → un form_error « state », envoyé tout de suite', () => {
  const s = setup();
  s.fire('famma:express-error', { detail: { fields: ['state'] } });
  assert.equal(s.errors().length, 1);
  assert.equal(s.errors()[0].l, 'state');
  assert.equal(s.errors()[0].pt, 'product');
  assert.equal(s.errors()[0].pid, 123);
  assert.equal(s.beacons.at(-1).url, '/wp-json/famma/v1/collect');
});

test('Plusieurs champs → codes joints par des virgules, dans l’ordre reçu', () => {
  const s = setup();
  s.fire('famma:express-error', { detail: { fields: ['name', 'phone'] } });
  assert.equal(s.errors()[0].l, 'name,phone');
});

test('Valeurs tapées, majuscules, objets ou textes longs : écartés, jamais envoyés', () => {
  const s = setup();
  s.fire('famma:express-error', { detail: { fields: ['20 123 456', 'Sousse', 'STATE', { a: 1 }, 'x'.repeat(40), 'phone'] } });
  assert.equal(s.errors().length, 1);
  assert.equal(s.errors()[0].l, 'phone');
  const sent = JSON.stringify(s.beacons);
  assert.ok(!sent.includes('20 123 456') && !sent.includes('Sousse'));
});

test('Aucun code valable, ou détail absent → aucun form_error', () => {
  const s = setup();
  s.fire('famma:express-error', { detail: { fields: ['Sousse'] } });
  s.fire('famma:express-error', { detail: null });
  s.fire('famma:express-error', {});
  assert.equal(s.errors().length, 0);
});

test('Saisies à relancer : le téléphone tapé en premier part même si le client s’arrête là (lot 23, T5)', () => {
  const s = setup({ inputs: '/wp-json/famma/v1/inputs' });
  // Formulaire 0.3.9 : téléphone d'abord, les autres champs encore vides.
  const fields = { billing_phone: '20 123 456', billing_first_name: '', billing_state: '', billing_address_1: '' };
  const form = { querySelectorAll: sel => { const m = /\[name="([^"]+)"\]/.exec(sel); return m && m[1] in fields ? [els[m[1]]] : []; } };
  const els = Object.fromEntries(Object.entries(fields).map(([name, value]) => [name, { name, value, type: 'text', closest: () => form }]));
  s.fire('focusout', { target: els.billing_phone });
  s.fire('pagehide', {});
  const sent = s.beacons.filter(b => b.url === '/wp-json/famma/v1/inputs');
  assert.equal(sent.length, 1, 'un envoi des saisies au départ de la page');
  assert.equal(sent[0].body.f.billing_phone, '20 123 456');
  assert.equal(sent[0].body.f.billing_first_name, '');
});

console.log(`RESULT ${passed}/${passed + failed} scénarios du journal réussis${failed ? `, ${failed} ÉCHEC(S)` : ''}.`);
if (failed) process.exitCode = 1;
