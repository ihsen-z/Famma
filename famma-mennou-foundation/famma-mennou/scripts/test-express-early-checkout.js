/**
 * Lot 6 (Q16) — InitiateCheckout du formulaire express dès un téléphone valide.
 *
 * Le vrai express-order.js est exécuté dans une VM Node avec un DOM minimal :
 * un seul envoi par affichage de page, jamais sur un numéro invalide, jamais au
 * clic « Confirmer » quand la donnée `earlyCheckout` est imprimée, comportement
 * d'avant quand elle est absente (interrupteur coupé), aucun plantage sans Pixel.
 * Aucune requête réelle, aucune donnée client (numéros synthétiques).
 *
 * Usage : node scripts/test-express-early-checkout.js
 */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../wp-content/plugins/famma-core/assets/js/express-order.js'), 'utf8');

function element(value = '', attrs = {}) {
  const classes = new Set();
  return {
    value, attrs: { ...attrs }, listeners: {}, hidden: false, disabled: false, children: [], textContent: '', innerHTML: '',
    classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c), toggle: (c, on) => { if (on) classes.add(c); else classes.delete(c); } },
    addEventListener(k, cb) { (this.listeners[k] = this.listeners[k] || []).push(cb); },
    removeEventListener(k, cb) { this.listeners[k] = (this.listeners[k] || []).filter(f => f !== cb); },
    fire(k, event = {}) { (this.listeners[k] || []).slice().forEach(cb => cb({ target: this, preventDefault() {}, ...event })); },
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    removeAttribute(k) { delete this.attrs[k]; },
    setCustomValidity() {}, focus() {}, scrollIntoView() {}, dispatchEvent() { return true; },
    closest() { return null; }, appendChild(c) { this.children.push(c); return c; },
    querySelector() { return null; }, querySelectorAll() { return []; },
  };
}

function setup(options = {}) {
  const events = [], timers = [], calls = [];
  const qty = element(options.qty || '1', { min: '1', max: '' });
  const phone = element(options.phone || '', { 'aria-describedby': 'famma-express-billing-phone-error' });
  const inputs = { billing_first_name: element('Client Fixture'), billing_phone: phone, billing_state: element('TN-51'), famma_quantity: qty,
    famma_hp_site: element(), payment_method: element('cod'), billing_country: element('TN'), famma_product_id: element('123') };
  const map = { '.famma-express__submit': element(), '.famma-express__notices': element(), '.famma-express__info': element(), '.famma-express__total': element('', { 'data-unit-price': '79' }) };
  Object.entries(inputs).forEach(([k, v]) => { map[`[name="${k}"]`] = v; });
  const form = element();
  form.querySelector = s => map[s] || null;
  form.querySelectorAll = s => (s === 'input, select, textarea, button' ? Object.values(inputs) : []);
  form.reportValidity = () => true;
  const document = {
    body: element(), documentElement: element(), activeElement: null,
    querySelector: s => (s === '.famma-express__form' ? form : null),
    getElementById: () => element(), createElement: () => element(),
    dispatchEvent: () => true,
  };
  const store = new Map();
  const window = {
    innerHeight: 800, scrollBy() {}, location: { href: '' },
    setTimeout(cb) { timers.push(cb); return timers.length; }, clearTimeout() {},
    sessionStorage: { getItem: k => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, String(v)), removeItem: k => store.delete(k) },
  };
  let uuid = 0;
  window.crypto = { randomUUID: () => '00000000-0000-4000-8000-' + String(++uuid).padStart(12, '0') };
  if (options.pixel !== false) window.fbq = (...args) => { events.push(args); };
  let counter = 0;
  if (options.fammaEventId) window.fammaEventId = () => 'evt-' + (++counter);
  const fammaExpress = {
    nonceUrl: '/nonce', prepareUrl: '/prepare', expressUrl: '/express', statusUrl: '/status', currency: 'TND',
    phoneError: 'Téléphone invalide', stateError: 'Gouvernorat', nameError: 'Nom', sizeError: 'Taille', genericError: 'Erreur',
    price: { decimals: 3, decimal: ',', thousand: ' ', format: '%2$s %1$s', symbol: 'DT' }, recapLabels: {},
  };
  if (options.early !== false) {
    fammaExpress.earlyCheckout = options.early || { unit: 79, params: { content_ids: ['123'], content_type: 'product', currency: 'TND', value: 79, contents: [{ id: '123', quantity: 1 }] } };
  }
  const sandbox = {
    document, window, fammaExpress, Uint8Array, Date, JSON, Math,
    FormData: class {
      constructor(target) { this.values = []; if (target === form) Object.entries(inputs).forEach(([k, v]) => this.values.push([k, v.value])); }
      append(k, v) { this.values.push([k, v]); }
      forEach(cb) { this.values.forEach(([k, v]) => cb(v, k)); }
    },
    Event: class { constructor(t) { this.type = t; } }, CustomEvent: class { constructor(t) { this.type = t; } },
    // Réponses fixtures du parcours Confirmer : jeton, devis isolé (avec son bloc tracking serveur), commande acceptée.
    fetch(url, init) {
      calls.push(url);
      const get = n => (init.body.values.find(e => e[0] === n) || [])[1];
      let reply;
      if ('/nonce' === url) reply = { success: true, data: { nonce: 'isolated-nonce' } };
      else if ('/prepare' === url) {
        const q = +get('famma_quantity') || 1;
        reply = { result: 'success', preparation_id: get('famma_preparation_id'), cart_hash: 'cart-a', other_items: 0,
          summary: { items: [], total: 79 * q, total_html: (79 * q) + ',000&nbsp;DT', currency: 'TND' },
          tracking: {
            add_to_cart: { emit: true, event_id: 'srv-add-a', previous: 0, unit: 79, params: { value: 79 * q, currency: 'TND', contents: [{ id: '123', quantity: q }] } },
            initiate_checkout: { event_id: 'srv-ic-a', params: { value: 79 * q, currency: 'TND', content_ids: ['123'] } },
          } };
      } else reply = { result: 'success', redirect: '/commande/order-received/900/?key=wc_order_fixture', famma_state: 'accepted', order_id: 900 };
      return Promise.resolve({ text: () => Promise.resolve(JSON.stringify(reply)) });
    },
  };
  vm.runInNewContext(source, sandbox, { filename: 'express-order.js' });
  const icEvents = () => events.filter(e => 'InitiateCheckout' === e[1]);
  const totalNode = map['.famma-express__total'];
  return { totalNode, phone, qty, form, window, events, icEvents, timers, store, calls,
    type(value, kind = 'input') { phone.value = value; phone.fire(kind); } };
}

let passed = 0;
const total = 17;
function check(name, fn) {
  try { fn(); passed++; console.log('PASS: ' + name); } catch (e) { console.log('FAIL: ' + name + ' — ' + e.message); process.exitCode = 1; }
}

check('numéro invalide ou incomplet : rien n’est envoyé', () => {
  const t = setup();
  ['2', '20 12', '20 123 45', '12 345 678', 'abc'].forEach(v => t.type(v));
  assert.equal(t.icEvents().length, 0);
});

check('premier numéro valide : un InitiateCheckout, avec value, currency, content_ids et eventID', () => {
  const t = setup({ fammaEventId: true });
  t.type('20 123 4'); t.type('20 123 456');
  const ic = t.icEvents();
  assert.equal(ic.length, 1);
  assert.equal(ic[0][2].value, 79);
  assert.equal(ic[0][2].currency, 'TND');
  assert.deepEqual(Array.from(ic[0][2].content_ids), ['123']);
  assert.equal(ic[0][3].eventID, 'evt-1');
});

check('une seule fois par affichage : frappes, change, blur et envoi suivants ne renvoient rien', () => {
  const t = setup();
  t.type('20 123 456');
  t.type('20 123 457'); t.type('20 123 457', 'change'); t.phone.fire('blur'); t.form.fire('submit');
  assert.equal(t.icEvents().length, 1);
});

check('eventID de repli (sans fammaEventId) : non vide', () => {
  const t = setup();
  t.type('20123456');
  assert.ok(String(t.icEvents()[0][3].eventID).length > 8);
});

check('numéros avec indicatif (+216, 00216, chiffres arabes) reconnus comme valides', () => {
  ['+216 20 123 456', '0021620123456', '٢٠ ١٢٣ ٤٥٦'].forEach(v => {
    const t = setup(); t.type(v); assert.equal(t.icEvents().length, 1, v);
  });
});

check('remplissage automatique (change seul) : part', () => {
  const t = setup(); t.type('20 123 456', 'change'); assert.equal(t.icEvents().length, 1);
});

check('collage : part après le tour de minuterie, une seule fois', () => {
  const t = setup(); t.phone.value = '20 123 456'; t.phone.fire('paste');
  assert.equal(t.icEvents().length, 0);
  t.timers.splice(0).forEach(cb => cb());
  assert.equal(t.icEvents().length, 1);
});

check('filet à l’envoi : numéro rempli sans événement, parti au submit, une seule fois', () => {
  const t = setup({ phone: '20 123 456' }); assert.equal(t.icEvents().length, 0);
  t.form.fire('submit'); t.form.fire('submit');
  assert.equal(t.icEvents().length, 1);
});

check('quantité 3 : value = 3 x prix unitaire, contents[0].quantity = 3', () => {
  const t = setup({ qty: '3' }); t.type('20 123 456');
  const p = t.icEvents()[0][2];
  assert.equal(p.value, 237);
  assert.equal(p.contents[0].quantity, 3);
});

check('valeur = total affiché du formulaire : prix unitaire en cours (lot/offre) x quantité, pas l’imprimé', () => {
  const t = setup({ qty: '2' }); t.totalNode.setAttribute('data-unit-price', '69');
  t.type('20 123 456');
  assert.equal(t.icEvents()[0][2].value, 138);
  assert.equal(t.icEvents()[0][2].contents[0].quantity, 2);
});

check('profil minimal (sans contents) : value ajustée, pas de plantage', () => {
  const t = setup({ qty: '2', early: { unit: 79, params: { content_ids: ['123'], content_type: 'product', currency: 'TND', value: 79 } } });
  t.type('20 123 456');
  assert.equal(t.icEvents()[0][2].value, 158);
});

check('sans Pixel (session admin) : aucun plantage, rien d’envoyé', () => {
  const t = setup({ pixel: false }); t.type('20 123 456'); t.form.fire('submit');
  assert.equal(t.events.length, 0);
});

check('Pixel chargé plus tard : l’événement part encore (drapeau non consommé)', () => {
  const t = setup({ pixel: false });
  t.type('20 123 456');
  t.window.fbq = (...a) => { t.events.push(a); };
  t.type('20 123 457');
  assert.equal(t.icEvents().length, 1);
});

check('interrupteur coupé (earlyCheckout absent) : rien ne part à la saisie', () => {
  const t = setup({ early: false }); t.type('20 123 456');
  assert.equal(t.icEvents().length, 0);
});

check('donnée vide ([], interrupteur coupé côté PHP) : script intact, rien à la saisie', () => {
  const t = setup({ early: [] }); t.type('20 123 456');
  assert.equal(t.icEvents().length, 0);
});

const flush = async () => { for (let i = 0; i < 20; i++) await new Promise(r => setImmediate(r)); };
const names = t => t.events.map(e => e[1] + ':' + (e[3] && e[3].eventID));

(async () => {
  for (const [label, early, expected] of [
    ['interrupteur actif : clic Confirmer = AddToCart seul, InitiateCheckout déjà parti à la saisie (pas de double)', undefined, ['InitiateCheckout', 'AddToCart:srv-add-a']],
    ['interrupteur coupé : comportement d’avant, InitiateCheckout (id serveur) au clic Confirmer avec AddToCart', false, ['AddToCart:srv-add-a', 'InitiateCheckout:srv-ic-a']],
  ]) {
    try {
      const t = setup({ early, phone: '' });
      t.type('20 123 456');
      const before = t.icEvents().length;
      t.form.fire('submit'); await flush();
      assert.ok(t.calls.includes('/prepare') && t.calls.includes('/express'), 'parcours complet joué : ' + t.calls.join(','));
      const got = t.events.map(e => e[1] + (e[3] && /^srv-/.test(e[3].eventID) ? ':' + e[3].eventID : ''));
      if (false === early) {
        assert.equal(before, 0);
        assert.deepEqual(got, expected);
      } else {
        assert.equal(before, 1);
        assert.deepEqual(got, expected);
        assert.equal(t.icEvents().length, 1, 'un seul InitiateCheckout sur toute la page');
      }
      passed++; console.log('PASS: ' + label);
    } catch (e) { console.log('FAIL: ' + label + ' — ' + e.message); process.exitCode = 1; }
  }
  console.log('RESULT ' + passed + '/' + total);
  if (passed !== total) process.exitCode = 1;
})();
