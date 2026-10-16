/**
 * Commande express en un clic — le vrai express-order.js exécuté dans une VM
 * Node avec un DOM, un stockage de session, un transport et un Pixel simulés.
 *
 * Aucune requête réseau réelle : `fetch` répond depuis une file de réponses
 * fixtures. Aucune donnée client : téléphones et noms sont synthétiques.
 *
 * Usage : node scripts/test-famma-express-v2.js
 */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');

const plugin = path.join(__dirname, '../wp-content/plugins/famma-core');
const source = fs.readFileSync(path.join(plugin, 'assets/js/express-order.js'), 'utf8');
const formSource = fs.readFileSync(path.join(plugin, 'includes/express-order.php'), 'utf8');
// Champ caché lu dans le vrai gabarit PHP, pas inventé par la fixture.
const countryField = formSource.match(/<input\s+type="hidden"\s+name="billing_country"\s+value="([^"]+)"\s*\/>/);
assert.ok(countryField, 'billing_country hidden field present in the PHP form');

let sequence = 0;

/** Élément DOM minimal : attributs, classes, écouteurs, enfants. */
function element(tag = 'div', value = '', attrs = {}) {
  const classes = new Set();
  const node = {
    tagName: tag.toUpperCase(), value, attrs: { ...attrs }, listeners: {}, children: [], hidden: false,
    disabled: false, focused: false, invalid: '', parentNode: null, text: '',
    classList: {
      add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c),
      toggle: (c, on) => { if (on === undefined ? !classes.has(c) : on) classes.add(c); else classes.delete(c); },
    },
    get className() { return [...classes].join(' '); },
    set className(v) { classes.clear(); String(v).split(/\s+/).filter(Boolean).forEach(c => classes.add(c)); },
    addEventListener(k, cb) { (this.listeners[k] = this.listeners[k] || []).push(cb); },
    removeEventListener(k, cb) { this.listeners[k] = (this.listeners[k] || []).filter(f => f !== cb); },
    fire(k, event = {}) { (this.listeners[k] || []).slice().forEach(cb => cb({ target: this, preventDefault() {}, ...event })); },
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    removeAttribute(k) { delete this.attrs[k]; },
    setCustomValidity(v) { this.invalid = v; },
    scrollIntoView() { this.scrolled = (this.scrolled || 0) + 1; },
    dispatchEvent(e) { this.dispatched = (this.dispatched || 0) + 1; return true; },
    focus() { this.focused = true; },
    closest(sel) { return sel === '[data-famma-express-step]' && this.getAttribute('data-famma-express-step') !== null ? this : null; },
    appendChild(child) { child.parentNode = this; this.children.push(child); return child; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
  };
  Object.defineProperty(node, 'textContent', {
    get() { return this.text + this.children.map(c => c.textContent).join(''); },
    set(v) { this.text = String(v); this.html = ''; this.children = []; },
  });
  Object.defineProperty(node, 'innerHTML', {
    get() { return this.html || ''; },
    set(v) { this.html = String(v); this.text = this.html.replace(/<[^>]*>/g, ''); this.children = []; },
  });
  return node;
}

/** Formulaire express et page autour, puis exécution du vrai script. */
function setup(responses = [], options = {}) {
  const storage = options.storage || new Map();
  const unit = options.unit || '79';
  const qty = element('input', options.qty || '1', { min: '1', max: options.max || '' });
  const phone = element('input', options.phone === undefined ? '20 123 456' : options.phone, { 'aria-describedby': 'famma-express-billing-phone-error' });
  const phoneError = element('span');
  const name = element('input', options.name === undefined ? 'Client Fixture' : options.name);
  const state = element('select', options.state === undefined ? 'TN-51' : options.state);
  const trap = element('input');
  const id = element('input', '123');
  const country = element('input', countryField[1]);
  const payment = element('input', 'cod');
  const total = element('span', '', { 'data-unit-price': unit });
  const button = element('button');
  const submitText = element('span');
  submitText.innerHTML = 'Confirmer ma commande / أكد طلبيتك';
  button.appendChild(submitText);
  button.querySelector = s => (s === '.famma-express__submit-text' ? submitText : null);
  const notices = element('div');
  notices.querySelectorAll = sel => {
    if (sel !== '[data-id]') return [];
    return [...notices.innerHTML.matchAll(/data-id="([^"]*)"/g)].map(m => ({ getAttribute: () => m[1] }));
  };
  const info = element('p'); info.hidden = true;
  const minus = element('button', '', { 'data-famma-express-step': '-1' });
  const plus = element('button', '', { 'data-famma-express-step': '1' });
  const cartQty = element('input', '1');
  const inputs = { billing_first_name: name, billing_phone: phone, billing_state: state, famma_quantity: qty,
    famma_hp_site: trap, payment_method: payment, billing_country: country, famma_product_id: id };
  const map = { '.famma-express__submit': button, '.famma-express__notices': notices, '.famma-express__info': info,
    '.famma-express__total': total };
  Object.entries(inputs).forEach(([k, v]) => { map[`[name="${k}"]`] = v; });
  const form = element('form');
  form.querySelector = s => map[s] || null;
  form.querySelectorAll = s => {
    if (s === 'input, select, textarea, button') return [...Object.values(inputs), minus, plus, button];
    if (s === '[data-famma-express-step]') return [minus, plus];
    return [];
  };
  form.reportValidity = () => true;

  const calls = [], events = [], created = [];
  const body = element('body');
  const documentElement = element('html');
  const document = {
    body: options.noBody ? null : body, documentElement, activeElement: null,
    querySelector(s) { return s === '.famma-express__form' ? form : s === 'form.cart input.qty' ? cartQty : null; },
    getElementById(k) { return k === 'famma-express-billing-phone-error' ? phoneError : null; },
    createElement(tag) { const e = element(tag); created.push(e); return e; },
  };
  const window = {
    location: { href: '' },
    sessionStorage: {
      getItem(k) { if (options.storageBlocked) throw Error('blocked'); return storage.has(k) ? storage.get(k) : null; },
      setItem(k, v) { if (options.storageBlocked) throw Error('blocked'); storage.set(k, String(v)); },
      removeItem(k) { storage.delete(k); },
    },
  };
  if (options.crypto !== false) window.crypto = { randomUUID: () => '00000000-0000-4000-8000-' + String(++sequence).padStart(12, '0') };
  if (options.pixel !== false) window.fbq = (...args) => { events.push(args); };
  class MockFormData {
    constructor(target) { this.values = []; if (target === form) Object.entries(inputs).forEach(([k, v]) => { if (!v.disabled) this.values.push([k, v.value]); }); }
    append(k, v) { this.values.push([k, v]); }
    forEach(cb) { this.values.forEach(([k, v]) => cb(v, k)); }
  }
  const fammaExpress = {
    nonceUrl: '/nonce', prepareUrl: '/prepare', expressUrl: '/express', statusUrl: '/status', currency: 'TND',
    genericError: 'Erreur générique', sending: 'Envoi en cours', phoneError: 'Téléphone invalide', stateError: 'Gouvernorat',
    nameError: 'Nom manquant', confirmLabel: 'Confirmer', retryLabel: 'Vérifier ma commande en cours', amountChanged: 'Le total a été mis à jour.',
    changed: 'Commande changée', networkError: 'Connexion impossible', otherItems: 'Seul cet article est commandé ici',
    modalTitle: 'Nouveau total', modalClose: 'Fermer', finalLabel: 'Valider ma commande – %s', pending: 'En cours',
    recovery: 'Résultat inconnu', storageError: 'Stockage indisponible',
    recapLabels: { product: 'Article', quantity: 'Quantité', amount: 'Montant', subtotal: 'Sous-total', discount: 'Remises', shipping: 'Livraison', fees: 'Frais', tax: 'Taxes', total: 'Total' },
    price: { decimals: 3, decimal: ',', thousand: ' ', format: '%2$s %1$s', symbol: 'DT' },
  };
  const sandbox = {
    document, window, fammaExpress, FormData: MockFormData, Uint8Array, Date, JSON, Math,
    Event: class { constructor(type, init) { this.type = type; Object.assign(this, init); } },
    fetch(url, init) {
      const call = { url, values: init.body.values, init };
      calls.push(call);
      if (!responses.length) return Promise.reject(Error('Unexpected request ' + url));
      const next = responses.shift();
      if (next instanceof Error) return Promise.reject(next);
      if (next instanceof Promise) return next.then(r => ({ text: () => Promise.resolve(JSON.stringify(r)) }));
      const result = typeof next === 'function' ? next(call) : next;
      return Promise.resolve({ text: () => Promise.resolve(typeof result === 'string' ? result : JSON.stringify(result)) });
    },
  };
  vm.runInNewContext(source, sandbox, { filename: 'express-order.js' });
  const submit = () => form.fire('submit');
  const find = cls => created.find(e => e.classList.contains(cls));
  const modal = () => {
    const overlay = find('famma-express-modal');
    return overlay && { overlay, confirm: find('famma-express-modal__confirm'), close: find('famma-express-modal__close'),
      text: find('famma-express-modal__confirm-text'), content: find('famma-express-modal__content') };
  };
  return { qty, phone, phoneError, name, state, total, button, submitText, notices, info, minus, plus, cartQty, form,
    window, calls, events, storage, submit, modal, documentElement,
    urls: () => calls.map(c => c.url),
    record: () => JSON.parse(storage.get('famma_express_intent_123')) };
}

const flush = async () => { for (let i = 0; i < 20; i++) await new Promise(r => setImmediate(r)); };
const token = { success: true, data: { nonce: 'isolated-nonce' } };
const field = (call, n) => (call.values.find(e => e[0] === n) || [])[1];
const names = call => call.values.map(e => e[0]);

/** Devis isolé du serveur : uniquement ce produit, total = 79 × quantité, sauf surcharge. */
function quote(over = {}) {
  return call => {
    const q = +field(call, 'famma_quantity') || 1;
    const t = over.total !== undefined ? over.total : 79 * q;
    return {
      result: 'success', preparation_id: field(call, 'famma_preparation_id'), cart_hash: over.hash || 'cart-a',
      other_items: over.other || 0,
      summary: { items: [{ product_id: 123, name: 'Produit fixture', quantity: q, line_total_html: t + ',000 DT' }], total: t,
        total_html: t + ',000&nbsp;DT', currency: 'TND', subtotal_html: t + ',000 DT', shipping_html: '', discount_html: '', fees_html: '', tax_html: '' },
      tracking: {
        add_to_cart: { emit: true, event_id: over.add || 'srv-add-a', previous: 0, unit: 79, params: { value: 79 * q, currency: 'TND', contents: [{ id: '123', quantity: q }] } },
        initiate_checkout: { event_id: over.ic || 'srv-ic-a', params: { value: t, currency: 'TND', content_ids: ['123'], num_items: q } },
      },
    };
  };
}
const accepted = { result: 'success', redirect: '/commande/order-received/900/?key=wc_order_fixture', famma_state: 'accepted', order_id: 900 };
const wooPhoneError = { result: 'failure', messages: '<ul class="woocommerce-error" role="alert"><li data-id="billing_phone">Numéro invalide</li></ul>' };
const failedState = { result: 'failure', famma_state: 'failed', famma_code: 'validation_failed' };
const ic = s => s.events.filter(e => e[1] === 'InitiateCheckout');

let passed = 0, failed = 0;
async function test(name, run) {
  try { await run(); passed++; console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n     ' + String(e && e.message).split('\n').join('\n     ')); }
}

(async () => {
  // ── Parcours nominal ───────────────────────────────────────────────────
  await test('Un clic = prepare puis checkout puis redirection vers la page WooCommerce', async () => {
    const s = setup([token, quote(), accepted]);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express']);
    assert.equal(s.window.location.href, accepted.redirect);
    assert.equal(s.button.disabled, true, 'bouton laissé désactivé pendant la redirection');
    assert.equal(s.modal(), undefined, 'aucune modale quand le total est identique');
    const final = s.calls[2];
    assert.equal(field(final, 'famma_preparation_id'), field(s.calls[1], 'famma_preparation_id'));
    assert.equal(field(final, 'famma_cart_hash'), 'cart-a');
    assert.ok(field(final, 'famma_request_id'));
    assert.equal(field(final, 'woocommerce-process-checkout-nonce'), 'isolated-nonce');
    assert.equal(field(final, 'billing_country'), countryField[1]);
    assert.equal(field(final, 'payment_method'), 'cod');
    assert.ok(!names(final).includes('terms') && !names(final).includes('terms-field'), 'aucune case CGV envoyée');
    assert.deepEqual(names(s.calls[1]).sort(), ['billing_country', 'billing_state', 'famma_hp_site', 'famma_preparation_id', 'famma_product_id', 'famma_quantity', 'woocommerce-process-checkout-nonce'].sort());
    assert.equal(s.record().phase, 'accepted');
    assert.equal(s.record().fields, undefined, 'les champs du client ne restent pas en stockage');
  });

  await test('Pendant l’envoi : bouton désactivé, libellé « Envoi en cours », aria-busy', async () => {
    let release; const slow = new Promise(r => { release = r; });
    const s = setup([token, slow]);
    s.submit();
    assert.equal(s.button.disabled, true);
    assert.equal(s.submitText.textContent, 'Envoi en cours');
    assert.equal(s.button.getAttribute('aria-busy'), 'true');
    assert.equal(s.phone.disabled, true, 'champs verrouillés pendant l’envoi');
    release({ result: 'failure', messages: '<ul><li>Stock insuffisant</li></ul>' }); await flush();
    assert.equal(s.button.disabled, false);
    assert.equal(s.submitText.innerHTML, 'Confirmer ma commande / أكد طلبيتك', 'libellé bilingue d’origine remis');
    assert.equal(s.phone.disabled, false);
  });

  await test('Double clic = un seul prepare et un seul checkout', async () => {
    const s = setup([token, quote(), accepted]);
    s.submit(); s.submit(); s.submit(); await flush(); s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/prepare').length, 1);
    assert.equal(s.calls.filter(c => c.url === '/express').length, 1);
  });

  await test('Panier avec un autre article (other_items>0), total isolé = total affiché : pas de modale, information affichée', async () => {
    const s = setup([token, quote({ other: 2 }), accepted]);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express']);
    assert.equal(s.modal(), undefined);
    assert.equal(s.info.hidden, false);
    assert.equal(s.info.textContent, 'Seul cet article est commandé ici');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Quantité 3 : total affiché 237 = total serveur → commande directe avec famma_quantity=3', async () => {
    const s = setup([token, quote(), accepted]);
    s.form.fire('click', { target: s.plus }); s.form.fire('click', { target: s.plus });
    assert.equal(s.qty.value, '3');
    assert.equal(s.total.textContent, '237,000 DT');
    assert.equal(s.cartQty.value, '3', 'formulaire WooCommerce synchronisé');
    s.submit(); await flush();
    assert.equal(field(s.calls[1], 'famma_quantity'), '3');
    assert.equal(field(s.calls[2], 'famma_quantity'), '3');
    assert.equal(s.window.location.href, accepted.redirect);
    assert.equal(ic(s)[0][2].value, 237);
  });

  await test('Quantité bornée par min/max ; boutons − / + désactivés aux bornes', async () => {
    const s = setup([], { max: '2' });
    assert.equal(s.minus.disabled, true);
    s.form.fire('click', { target: s.plus }); s.form.fire('click', { target: s.plus }); s.form.fire('click', { target: s.plus });
    assert.equal(s.qty.value, '2');
    assert.equal(s.plus.disabled, true);
    assert.equal(s.minus.disabled, false);
    s.qty.value = '40'; s.qty.fire('input');
    assert.equal(s.total.textContent, '158,000 DT');
  });

  // ── Total serveur différent : modale ───────────────────────────────────
  await test('Total serveur différent → modale (aucun checkout) puis « Valider » → checkout et redirection', async () => {
    const s = setup([token, quote({ total: 84 }), accepted]);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare']);
    const m = s.modal();
    assert.ok(m, 'modale construite');
    assert.equal(m.overlay.hidden, false);
    assert.equal(m.overlay.parentNode.tagName, 'BODY', 'modale hors du formulaire');
    assert.equal(m.text.textContent, 'Valider ma commande – 84,000 DT');
    assert.equal(m.confirm.focused, true);
    assert.ok(s.documentElement.classList.contains('famma-express-modal-open'));
    s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/express').length, 0, 'clic principal ignoré pendant la modale');
    m.confirm.fire('click'); m.confirm.fire('click'); await flush();
    assert.equal(s.calls.filter(c => c.url === '/express').length, 1);
    assert.equal(s.calls.filter(c => c.url === '/prepare').length, 1, 'pas de second devis');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Modale fermée → aucun checkout ; clic suivant = nouveau devis avec nouvel identifiant', async () => {
    const s = setup([token, quote({ total: 84 }), quote(), accepted]);
    s.submit(); await flush();
    const first = field(s.calls[1], 'famma_preparation_id');
    const m = s.modal();
    m.close.fire('click'); await flush();
    assert.equal(m.overlay.hidden, true);
    assert.equal(s.button.focused, true, 'focus rendu au bouton');
    assert.equal(s.calls.filter(c => c.url === '/express').length, 0);
    s.submit(); await flush();
    const prep = s.calls.filter(c => c.url === '/prepare');
    assert.equal(prep.length, 2);
    assert.notEqual(field(prep[1], 'famma_preparation_id'), first);
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Modale : échec de validation au « Valider » → modale fermée, message WooCommerce, bouton réactivé', async () => {
    const s = setup([token, quote({ total: 84 }), wooPhoneError, failedState]);
    s.submit(); await flush();
    s.modal().confirm.fire('click'); await flush();
    assert.equal(s.modal().overlay.hidden, true);
    assert.equal(s.notices.textContent, 'Numéro invalide');
    assert.equal(s.phone.getAttribute('aria-invalid'), 'true');
    assert.equal(s.button.disabled, false);
  });

  await test('Sans <body> (modale impossible) : message + montant, le clic suivant valide ce même devis', async () => {
    const s = setup([token, quote({ total: 84 }), accepted], { noBody: true });
    s.submit(); await flush();
    assert.ok(s.notices.textContent.startsWith('Le total a été mis à jour.'));
    assert.ok(s.notices.textContent.includes('84,000 DT'));
    assert.equal(s.button.disabled, false);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express']);
    assert.equal(s.window.location.href, accepted.redirect);
  });

  // ── Téléphone ──────────────────────────────────────────────────────────
  const valid = ['20123456', '20 123 456', '+216 20123456', '0021620123456', '216 20 123 456', '21612345', '٢٠١٢٣٤٥٦', '۲۰ ۱۲۳ ۴۵۶', '+٢١٦ ٢٠ ١٢٣ ٤٥٦'];
  for (const ph of valid) {
    await test(`Téléphone accepté localement : « ${ph} »`, async () => {
      const s = setup([token, quote(), accepted], { phone: ph });
      s.submit(); await flush();
      assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express']);
      assert.equal(field(s.calls[2], 'billing_phone'), ph, 'saisie brute transmise : le serveur normalise');
      assert.notEqual(s.phone.getAttribute('aria-invalid'), 'true');
    });
  }
  const invalid = ['123', '12345678', '2012345', '201234567', '2161234567', '00216123', '+33 6 12 34 56 78', ''];
  for (const ph of invalid) {
    await test(`Téléphone refusé localement sans aucun appel : « ${ph} »`, async () => {
      const s = setup([], { phone: ph });
      s.submit(); await flush();
      assert.equal(s.calls.length, 0);
      assert.equal(s.events.length, 0);
      assert.equal(s.phone.getAttribute('aria-invalid'), 'true');
      assert.ok(s.phone.classList.contains('famma-express__invalid'));
      assert.equal(s.phoneError.hidden, false);
      assert.ok(s.notices.textContent.includes('Téléphone invalide'));
      assert.equal(s.button.disabled, false);
    });
  }
  await test('Champ téléphone corrigé : l’erreur disparaît à la frappe', async () => {
    const s = setup([], { phone: '123' });
    s.submit(); await flush();
    s.phone.value = '20 123 456'; s.phone.fire('input'); s.form.fire('input', { target: s.phone });
    assert.equal(s.phone.getAttribute('aria-invalid'), 'false');
    assert.equal(s.phoneError.hidden, true);
  });
  await test('Nom vide ou gouvernorat absent : aucun appel, champs marqués', async () => {
    const a = setup([], { name: '  ' }); a.submit(); await flush();
    assert.equal(a.calls.length, 0); assert.equal(a.name.getAttribute('aria-invalid'), 'true');
    const b = setup([], { state: '' }); b.submit(); await flush();
    assert.equal(b.calls.length, 0); assert.equal(b.state.getAttribute('aria-invalid'), 'true');
  });

  // ── Erreurs ────────────────────────────────────────────────────────────
  await test('Erreur réseau au prepare → message, bouton réactivé, aucun renvoi automatique', async () => {
    const s = setup([token, new Error('réseau coupé')]);
    s.submit(); await flush(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare']);
    assert.equal(s.notices.textContent, 'Connexion impossible');
    assert.equal(s.button.disabled, false);
    assert.equal(s.phone.disabled, false);
    assert.ok(s.notices.scrolled > 0, 'défilement jusqu’au message');
    assert.equal(s.events.length, 0);
  });

  await test('Erreur réseau au jeton → message, aucun prepare', async () => {
    const s = setup([new Error('réseau coupé')]);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce']);
    assert.equal(s.notices.textContent, 'Connexion impossible');
    assert.equal(s.button.disabled, false);
  });

  await test('Erreur réseau au checkout → état « à vérifier », aucun renvoi automatique ; le clic interroge famma_express_status', async () => {
    const s = setup([token, quote(), new Error('réponse perdue'), accepted]);
    s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/express').length, 1);
    assert.equal(s.record().phase, 'unknown');
    assert.equal(s.notices.textContent, 'Résultat inconnu');
    assert.equal(s.phone.disabled, true, 'champs verrouillés tant que le résultat est inconnu');
    assert.equal(s.button.disabled, false);
    assert.equal(s.submitText.textContent, 'Vérifier ma commande en cours');
    const key = s.record().request_id;
    s.submit(); await flush();
    assert.equal(s.calls.at(-1).url, '/status');
    assert.equal(field(s.calls.at(-1), 'famma_request_id'), key);
    assert.equal(s.calls.filter(c => c.url === '/express').length, 1, 'aucune seconde commande');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Erreur WooCommerce data-id="billing_phone" → message au-dessus du bouton, champ marqué, nouvelle intention', async () => {
    const s = setup([token, quote(), wooPhoneError, failedState]);
    const key = s.record().request_id;
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express', '/status']);
    assert.equal(s.notices.textContent, 'Numéro invalide');
    assert.equal(s.phone.getAttribute('aria-invalid'), 'true');
    assert.ok(s.phone.classList.contains('famma-express__invalid'));
    assert.equal(s.button.disabled, false);
    assert.equal(s.phone.disabled, false);
    assert.notEqual(s.record().request_id, key, 'nouvelle intention après un refus prouvé');
    assert.equal(s.record().phase, 'draft');
  });

  await test('Erreur WooCommerce au prepare (data-id) → champ marqué, aucun checkout', async () => {
    const s = setup([token, { result: 'failure', famma_code: 'invalid', messages: '<ul><li data-id="billing_state">Gouvernorat invalide</li></ul>' }]);
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare']);
    assert.equal(s.state.getAttribute('aria-invalid'), 'true');
    assert.equal(s.notices.textContent, 'Gouvernorat invalide');
    assert.equal(s.button.disabled, false);
  });

  await test('Réponse vide ou illisible → message générique, jamais d’échec silencieux', async () => {
    const s = setup([token, 'Fatal error: <b>synthétique</b>']);
    s.submit(); await flush();
    assert.equal(s.notices.textContent, 'Connexion impossible');
    const t = setup([token, { result: 'failure', messages: '' }]);
    t.submit(); await flush();
    assert.equal(t.notices.textContent, 'Erreur générique');
  });

  await test('Avertissement PHP avant un JSON valide : toléré', async () => {
    const s = setup([token, c => 'Warning: synthetic\n' + JSON.stringify(quote()(c)), accepted]);
    s.submit(); await flush();
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Panier changé entre devis et commande (cart_changed) → message, le clic suivant refait un devis', async () => {
    const s = setup([token, quote(), { result: 'failure', famma_code: 'cart_changed' }, quote(), accepted]);
    s.submit(); await flush();
    assert.equal(s.notices.textContent, 'Commande changée');
    assert.equal(s.record().phase, 'draft');
    assert.equal(s.button.disabled, false);
    s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/prepare').length, 2);
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Jeton expiré : renouvelé une seule fois, même intention et même charge utile', async () => {
    const s = setup([token, quote(), { famma_token: 'expired' }, { success: true, data: { nonce: 'fresh' } }, accepted]);
    s.submit(); await flush();
    const final = s.calls.filter(c => c.url === '/express');
    assert.equal(final.length, 2);
    assert.equal(field(final[0], 'famma_request_id'), field(final[1], 'famma_request_id'));
    assert.equal(field(final[1], 'woocommerce-process-checkout-nonce'), 'fresh');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Jeton expiré deux fois : pas de boucle, état à vérifier', async () => {
    const s = setup([token, quote(), { famma_token: 'expired' }, { success: true, data: { nonce: 'fresh' } }, { famma_token: 'expired' }, { result: 'failure', famma_code: 'unknown_intent' }]);
    s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/express').length, 2);
  });

  // ── Pixel ──────────────────────────────────────────────────────────────
  await test('InitiateCheckout émis une fois avec l’event_id serveur, après AddToCart', async () => {
    const s = setup([token, quote(), accepted]);
    s.submit(); await flush();
    assert.deepEqual(s.events.map(e => e[1]), ['AddToCart', 'InitiateCheckout']);
    assert.equal(ic(s)[0][3].eventID, 'srv-ic-a');
    assert.equal(ic(s)[0][2].value, 79);
    assert.equal(s.events[0][3].eventID, 'srv-add-a');
  });

  await test('InitiateCheckout une seule fois : second clic après refus, puis rechargement de la page', async () => {
    const store = new Map();
    const s = setup([token, quote(), wooPhoneError, failedState, quote({ ic: 'srv-ic-b', add: 'srv-add-b' }), wooPhoneError, failedState], { storage: store });
    s.submit(); await flush();
    assert.equal(ic(s).length, 1);
    s.submit(); await flush();
    assert.equal(s.calls.filter(c => c.url === '/prepare').length, 2);
    assert.equal(ic(s).length, 1, 'pas de second InitiateCheckout dans la page');
    const r = setup([token, quote({ ic: 'srv-ic-c', add: 'srv-add-c' }), accepted], { storage: store });
    r.submit(); await flush();
    assert.equal(ic(r).length, 0, 'pas de second InitiateCheckout après rechargement');
    assert.equal(r.window.location.href, accepted.redirect);
    assert.equal(store.has('famma_express_ic_123'), false, 'commande acceptée : prochaine intention libre');
  });

  await test('Pixel absent ou qui lève une exception : la commande passe quand même', async () => {
    const s = setup([token, quote(), accepted], { pixel: false });
    s.submit(); await flush();
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Aucun Pixel si la validation locale échoue ou si le devis est refusé', async () => {
    const s = setup([token, { result: 'failure', messages: '<ul><li>Stock insuffisant</li></ul>' }]);
    s.submit(); await flush();
    assert.equal(s.events.length, 0);
  });

  // ── Reprise par famma_express_status ───────────────────────────────────
  await test('Rechargement avec résultat inconnu → le clic interroge famma_express_status, pas de nouvelle commande', async () => {
    const store = new Map();
    const s = setup([token, quote(), new Error('perdu')], { storage: store });
    s.submit(); await flush();
    const key = s.record().request_id;
    const r = setup([token, accepted], { storage: store, phone: '' });
    assert.equal(r.notices.textContent, 'Résultat inconnu', 'message affiché dès le chargement');
    assert.equal(r.phone.disabled, true);
    r.submit(); await flush();
    assert.deepEqual(r.urls(), ['/nonce', '/status']);
    assert.equal(field(r.calls[1], 'famma_request_id'), key);
    assert.equal(r.window.location.href, accepted.redirect);
  });

  await test('Statut « unknown_intent » → renvoi du même instantané et du même request_id', async () => {
    const store = new Map();
    const s = setup([token, quote(), new Error('perdu')], { storage: store });
    s.submit(); await flush();
    const original = s.calls.find(c => c.url === '/express').values;
    const r = setup([token, { result: 'failure', famma_code: 'unknown_intent' }, accepted], { storage: store, phone: '' });
    r.submit(); await flush();
    assert.deepEqual(r.urls(), ['/nonce', '/status', '/express']);
    assert.deepEqual(r.calls[2].values, original);
    assert.equal(r.window.location.href, accepted.redirect);
  });

  await test('Statut « processing » : formulaire verrouillé, même request_id', async () => {
    const s = setup([token, quote(), { result: 'processing', famma_state: 'processing' }, { result: 'processing', famma_state: 'processing' }]);
    const key = s.record().request_id;
    s.submit(); await flush();
    assert.equal(s.record().phase, 'processing');
    assert.equal(s.notices.textContent, 'En cours');
    s.submit(); await flush();
    assert.equal(s.calls.at(-1).url, '/status');
    assert.equal(s.record().request_id, key);
    assert.equal(s.phone.disabled, true);
    assert.equal(s.calls.filter(c => c.url === '/express').length, 1);
  });

  // ── Garde-fous ─────────────────────────────────────────────────────────
  await test('Stockage de session bloqué : aucun envoi, message clair', async () => {
    const s = setup([], { storageBlocked: true });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.equal(s.notices.textContent, 'Stockage indisponible');
  });

  await test('Pas de source aléatoire sûre : aucun envoi, message clair', async () => {
    const s = setup([], { crypto: false });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.equal(s.notices.textContent, 'Stockage indisponible');
  });

  await test('Une nouvelle commande ultérieure est une nouvelle intention', async () => {
    const store = new Map();
    const s = setup([token, quote(), accepted], { storage: store });
    s.submit(); await flush();
    const key = s.record().request_id;
    const n = setup([token, quote(), accepted], { storage: store });
    n.submit(); await flush();
    assert.notEqual(n.record().request_id, key);
    assert.equal(n.window.location.href, accepted.redirect);
  });

  console.log(`RESULT ${passed}/${passed + failed} scénarios front réussis${failed ? `, ${failed} ÉCHEC(S)` : ''}. Aucun réseau WooCommerce ni Meta utilisé.`);
  if (failed) process.exitCode = 1;
})().catch(e => { console.error(e); process.exitCode = 1; });
