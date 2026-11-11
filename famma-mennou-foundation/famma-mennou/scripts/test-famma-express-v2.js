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

// Ville → gouvernorat (lot 23, T4) : la vraie liste du formulaire et les vrais lieux.
const corePhp = fs.readFileSync(path.join(plugin, 'famma-core.php'), 'utf8');
const pairsIn = (text, from, to) => {
  const start = text.indexOf(from);
  const body = text.slice(start, text.indexOf(to, start));
  return [...body.matchAll(/'(TN-\d{2})' => '([^']+)',/g)].map(m => [m[1], m[2]]);
};
const governorateLabels = pairsIn(corePhp, 'public function governorates(', 'return $states;');
const governorateArabic = Object.fromEntries(pairsIn(formSource, 'function governorate_arabic_names', ');'));
assert.equal(governorateLabels.length, 24, '24 gouvernorats lus dans Tunisia::governorates()');
assert.equal(Object.keys(governorateArabic).length, 24, '24 noms arabes lus dans governorate_arabic_names()');

/**
 * Lieux réels : `Governorate_Places::places()` exécuté par PHP (FAMMA_PHP =
 * chemin de php.exe, sinon `php`). Sans PHP, le test échoue : pas de copie
 * des données dans ce fichier.
 */
function realPlaces() {
  const php = process.env.FAMMA_PHP || 'php';
  const ext = path.join(path.dirname(php), 'ext');
  const args = fs.existsSync(path.join(ext, 'php_mbstring.dll')) ? ['-n', '-d', 'extension_dir=' + ext, '-d', 'extension=mbstring'] : [];
  const code = 'define("ABSPATH", "x"); function apply_filters($h, $v) { return $v; } require $argv[1];'
    + ' echo json_encode(Famma\\Core\\Governorate_Places::places(), JSON_UNESCAPED_UNICODE);';
  const out = require('node:child_process').execFileSync(php, [...args, '-r', code, '--', path.join(plugin, 'includes/governorate-places.php')], { encoding: 'utf8' });
  return JSON.parse(out);
}
const placesData = realPlaces();

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
  // Phrase sous le téléphone (0.3.9, option phoneNote) : son identifiant suit celui de l'erreur.
  const phone = element('input', options.phone === undefined ? '20 123 456' : options.phone,
    { 'aria-describedby': 'famma-express-billing-phone-error' + (options.phoneNote ? ' famma-express-phone-note' : '') });
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
  // Adresse de livraison (0.3.9) : texte facultatif « Ville, cité, rue… » à côté de la liste des gouvernorats.
  const address = element('input', options.address || '');
  // `name` comme dans le vrai DOM : le script reconnaît le gouvernorat par son nom à l'événement `change`.
  state.name = 'billing_state'; address.name = 'billing_address_1';
  if (options.address !== undefined || options.places) inputs.billing_address_1 = address;
  // Indication sous l'adresse, remplacée par « Gouvernorat ajouté : … » quand la ville est reconnue.
  const hint = element('span'); hint.textContent = 'Choisissez le gouvernorat.';
  // Champs cachés de <wc-order-attribution-inputs> (WooCommerce), quand l'élément est dans le formulaire.
  Object.entries(options.extraInputs || {}).forEach(([k, v]) => { inputs[k] = element('input', v); });
  const map = { '.famma-express__submit': button, '.famma-express__notices': notices, '.famma-express__info': info,
    '.famma-express__total': total };
  Object.entries(inputs).forEach(([k, v]) => { map[`[name="${k}"]`] = v; });
  // Ancien balisage servi par le cache (avant 0.2.7) : case CGV obligatoire et récapitulatif inséré.
  const terms = element('input', 'on', { required: '' }); terms.required = true;
  const recap = element('div');
  if (options.legacy) { inputs.terms = terms; map['[name="terms"]'] = terms; map['.famma-express__recap'] = recap; }
  // Choix de taille (0.3.0) : groupe de boutons radio, comme Product_Sizes::render_field().
  const sizeGroup = element('fieldset');
  const sizeError = element('span'); sizeError.hidden = true;
  const sizeRadios = (options.sizes || []).map(v => { const r = element('input', v); r.name = 'famma_size'; r.checked = v === options.size; return r; });
  sizeGroup.querySelector = sel => sel === '.famma-express__error' ? sizeError
    : sel === 'input[name="famma_size"]:checked' ? (sizeRadios.find(r => r.checked) || null) : null;
  const cartButton = element('button');
  if (options.sizes) { map['[data-famma-sizes]'] = sizeGroup; map['.famma-express__cart'] = cartButton; }
  const form = element('form');
  form.querySelector = s => map[s] || null;
  form.querySelectorAll = s => {
    if (s === 'input, select, textarea, button') return [...Object.values(inputs), minus, plus, button];
    // Champs à taper, dans l'ordre du gabarit 0.3.9 (touche « Suivant » du clavier).
    if (s === 'input[type="text"], input[type="tel"]') return [phone, name, ...(inputs.billing_address_1 ? [address] : [])];
    if (s === '[data-famma-express-step]') return [minus, plus];
    return [];
  };
  form.reportValidity = () => true;
  if (options.places) {
    // Gabarit 0.3.9 : liste native avec ses 24 options (intitulé + nom arabe en data-alt).
    state.options = governorateLabels.map(([value, text]) => ({ value, text, getAttribute: k => (k === 'data-alt' ? governorateArabic[value] : null) }));
    state.options.unshift({ value: '', text: 'Gouvernorat', getAttribute: () => null });
    Object.defineProperty(state, 'selectedIndex', { get() { return state.options.findIndex(o => o.value === state.value); } });
    state.setAttribute('aria-describedby', 'famma-express-address-hint');
    // Le `change` envoyé par le script remonte au formulaire, comme dans le vrai DOM.
    state.dispatchEvent = e => { state.dispatched = (state.dispatched || 0) + 1; state.fire(e.type); form.fire(e.type, { target: state }); return true; };
    map['select[name="billing_state"]:not([data-famma-combo])'] = state;
    map['input[name="billing_address_1"]'] = address;
  }

  const calls = [], events = [], created = [], blocks = [];
  const body = element('body');
  const documentElement = element('html');
  const document = {
    body: options.noBody ? null : body, documentElement, activeElement: null,
    querySelector(s) { return s === '.famma-express__form' ? form : s === 'form.cart input.qty' ? cartQty : null; },
    getElementById(k) { return k === 'famma-express-billing-phone-error' ? phoneError : (k === 'famma-express-address-hint' ? hint : null); },
    createElement(tag) { const e = element(tag); created.push(e); return e; },
    // Envois refusés signalés au journal des visites (lot 23, `famma:express-error`).
    // Array.from : tableau de l'hôte, comparable par deepStrictEqual (le bac à sable a son propre Array).
    dispatchEvent(e) { if (e.type === 'famma:express-error') blocks.push(Array.from(e.detail.fields)); return true; },
  };
  const timers = [], scrolls = [];
  const window = {
    innerHeight: 800,
    scrollBy(x, y) { scrolls.push(y); },
    // Zone visible quand le clavier est ouvert (option viewport) : `resize` relancé par `resizeViewport()`.
    ...(options.viewport ? { visualViewport: { ...options.viewport, listeners: [], addEventListener(t, cb) { this.listeners.push(cb); } } } : {}),
    location: { href: '' },
    // Minuteries manuelles : `runTimers()` joue celles en attente (pause de frappe, fin du flash).
    setTimeout(cb) { timers.push(cb); return timers.length; },
    clearTimeout(id) { if (id) timers[id - 1] = null; },
    sessionStorage: {
      getItem(k) { if (options.storageBlocked) throw Error('blocked'); return storage.has(k) ? storage.get(k) : null; },
      setItem(k, v) { if (options.storageBlocked) throw Error('blocked'); storage.set(k, String(v)); },
      removeItem(k) { storage.delete(k); },
    },
  };
  if (options.crypto !== false) window.crypto = { randomUUID: () => '00000000-0000-4000-8000-' + String(++sequence).padStart(12, '0') };
  if (options.pixel !== false) window.fbq = (...args) => { events.push(args); };
  // Bibliothèque Meta chargée : fbevents.js pose callMethod sur la file fbq.
  if (options.pixel !== false && options.fbqLoaded) window.fbq.callMethod = () => {};
  if (options.wcAttribution) window.wc_order_attribution = options.wcAttribution;
  if (options.places && !options.noPlacesData) window.fammaExpressPlaces = placesData;
  class MockFormData {
    constructor(target) {
      this.values = [];
      if (target === form) {
        Object.entries(inputs).forEach(([k, v]) => { if (!v.disabled) this.values.push([k, v.value]); });
        sizeRadios.forEach(r => { if (r.checked && !r.disabled) this.values.push(['famma_size', r.value]); });
      }
    }
    append(k, v) { this.values.push([k, v]); }
    forEach(cb) { this.values.forEach(([k, v]) => cb(v, k)); }
  }
  const fammaExpress = {
    nonceUrl: '/nonce', prepareUrl: '/prepare', expressUrl: '/express', statusUrl: '/status', currency: 'TND',
    genericError: 'Erreur générique', sending: 'Envoi en cours', phoneError: 'Téléphone invalide', stateError: 'Gouvernorat',
    nameError: 'Nom manquant', sizeError: 'Taille manquante', confirmLabel: 'Confirmer', retryLabel: 'Vérifier ma commande en cours', amountChanged: 'Le total a été mis à jour.',
    changed: 'Commande changée', networkError: 'Connexion impossible', otherItems: 'Seul cet article est commandé ici',
    modalTitle: 'Nouveau total', modalClose: 'Fermer', finalLabel: 'Valider ma commande – %s', pending: 'En cours',
    recovery: 'Résultat inconnu', storageError: 'Stockage indisponible', stateFound: 'Gouvernorat ajouté : %s. Vous pouvez le changer.',
    recapLabels: { product: 'Article', quantity: 'Quantité', amount: 'Montant', subtotal: 'Sous-total', discount: 'Remises', shipping: 'Livraison', fees: 'Frais', tax: 'Taxes', total: 'Total' },
    price: { decimals: 3, decimal: ',', thousand: ' ', format: '%2$s %1$s', symbol: 'DT' },
  };
  (options.drop || []).forEach(k => { delete fammaExpress[k]; });
  const sandbox = {
    document, window, fammaExpress, FormData: MockFormData, Uint8Array, Date, JSON, Math,
    Event: class { constructor(type, init) { this.type = type; Object.assign(this, init); } },
    // Ancien navigateur intégré sans CustomEvent : option noCustomEvent.
    ...(options.noCustomEvent ? {} : { CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init && init.detail; } } }),
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
  return { sizeGroup, sizeError, sizeRadios, cartButton, terms, recap, qty, phone, phoneError, name, state, address, total, button, submitText, notices, info, minus, plus, cartQty, form,
    window, calls, events, blocks, storage, submit, modal, documentElement, hint, document, scrolls,
    // Frappe dans « Ville, cité, rue… » : `input` à chaque touche, `change` en fin de saisie.
    type(text) { address.value = text; address.fire('input'); },
    leave() { address.fire('change'); },
    runTimers() { timers.splice(0).forEach(cb => cb && cb()); },
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
// Comme Express_Idempotency::reply() : le statut d'un refus porte un message générique ; le détail est dans la réponse du checkout.
const failedState = { result: 'failure', famma_state: 'failed', famma_code: 'validation_failed', messages: '<ul class="woocommerce-error"><li>Échec générique du statut</li></ul>' };
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
    assert.equal(s.events.filter(e => e[1] === 'AddToCart').length + r.events.filter(e => e[1] === 'AddToCart').length, 1, 'AddToCart une seule fois malgré le refus et le rechargement');
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

  // ── Refus explicites avant réservation (aucune commande possible) ────
  for (const [label, refusal, expected] of [
    ['plafond 429 Anti_Abuse', { result: 'failure', messages: '<ul class="woocommerce-error"><li>Trop de tentatives</li></ul>', refresh: false, reload: false }, 'Trop de tentatives'],
    ['intent_invalid sans message', { result: 'failure', famma_code: 'intent_invalid' }, 'Erreur générique'],
    ['registry_unavailable', { result: 'failure', famma_code: 'registry_unavailable' }, 'Erreur générique'],
    ['cart_rejected avec avis', { result: 'failure', famma_code: 'cart_rejected', messages: '<ul><li>Stock insuffisant</li></ul>' }, 'Stock insuffisant'],
  ]) {
    await test(`Refus explicite (${label}) puis status unknown_intent → retour au repos, message, pas de boucle`, async () => {
      const s = setup([token, quote(), refusal, { result: 'failure', famma_code: 'unknown_intent' }, quote(), accepted]);
      s.submit(); await flush();
      assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express', '/status']);
      assert.equal(s.notices.textContent, expected);
      assert.equal(s.record().phase, 'draft');
      assert.equal(s.record().fields, undefined);
      assert.equal(s.phone.disabled, false, 'champs déverrouillés');
      assert.equal(s.button.disabled, false);
      assert.equal(s.submitText.innerHTML, 'Confirmer ma commande / أكد طلبيتك');
      s.submit(); await flush();
      assert.deepEqual(s.urls().slice(4), ['/prepare', '/express'], "le clic suivant refait un devis, sans rejouer l'ancien envoi");
      assert.notEqual(field(s.calls[4], 'famma_preparation_id'), field(s.calls[1], 'famma_preparation_id'));
      assert.equal(s.window.location.href, accepted.redirect);
    });
  }
  await test('Réponse illisible du checkout puis unknown_intent → reste « à vérifier » (verrouillé)', async () => {
    const s = setup([token, quote(), 'Fatal error', { result: 'failure', famma_code: 'unknown_intent' }]);
    s.submit(); await flush();
    assert.equal(s.record().phase, 'unknown');
    assert.equal(s.phone.disabled, true);
  });

  // ── Ancien balisage servi par le cache LiteSpeed ─────────────────────
  await test('Ancien HTML : case CGV rendue facultative et masquée, récapitulatif masqué', async () => {
    const s = setup([], { legacy: true, drop: ['finalLabel', 'modalTitle', 'modalClose'] });
    assert.equal(s.terms.required, false);
    assert.equal(s.terms.getAttribute('required'), null);
    assert.equal(s.terms.hidden, true);
    assert.equal(s.recap.hidden, true);
  });
  await test('Ancien HTML : modale de vérification forcée à total identique, libellés de repli non vides', async () => {
    const s = setup([token, quote(), accepted], { legacy: true, drop: ['finalLabel', 'modalTitle', 'modalClose'] });
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare']);
    const m = s.modal();
    assert.ok(m && m.overlay.hidden === false, 'modale ouverte');
    assert.ok(m.text.textContent.includes('Confirmer') && m.text.textContent.includes('79,000 DT'), 'bouton final : libellé existant + montant (' + m.text.textContent + ')');
    const closeName = m.close.getAttribute('aria-label');
    assert.ok(closeName === null ? m.close.textContent.trim() !== '' : closeName.trim() !== '', 'bouton Fermer avec un nom accessible');
    const title = s.modal().overlay.children[0].children[0].children[0];
    assert.ok(title.textContent.trim() !== '', 'titre de modale non vide');
    m.confirm.fire('click'); await flush();
    assert.equal(s.window.location.href, accepted.redirect);
  });
  await test('Nouveau HTML : aucune case CGV envoyée ni requise', async () => {
    const s = setup([token, quote(), accepted]);
    s.submit(); await flush();
    assert.ok(!names(s.calls[2]).includes('terms'));
  });

  // ── Choix de taille (0.3.0) et nom complet ───────────────────────────
  await test('Taille proposée mais non choisie : aucun envoi, message taille, groupe marqué', async () => {
    const s = setup([], { sizes: ['S', 'M'] });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.ok(s.notices.textContent.includes('Taille manquante'));
    assert.equal(s.sizeGroup.getAttribute('aria-invalid'), 'true');
    assert.equal(s.sizeError.hidden, false);
  });

  await test('Taille M choisie : envoyée au devis ET à la commande', async () => {
    const s = setup([token, quote(), accepted], { sizes: ['S', 'M'], size: 'M' });
    s.submit(); await flush();
    assert.deepEqual(s.urls(), ['/nonce', '/prepare', '/express']);
    assert.equal(field(s.calls[1], 'famma_size'), 'M');
    assert.equal(field(s.calls[2], 'famma_size'), 'M');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Ajouter au panier sans taille : bloqué avec le message taille', async () => {
    const s = setup([], { sizes: ['S', 'M'] });
    s.cartButton.fire('click');
    assert.equal(s.calls.length, 0);
    assert.ok(s.notices.textContent.includes('Taille manquante'));
  });

  await test('Ancien fammaExpress sans sizeError : repli sur le message générique', async () => {
    const s = setup([], { sizes: ['S', 'M'], drop: ['sizeError'] });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.ok(s.notices.textContent.includes('Erreur générique'));
  });

  await test('Nom complet de 2 caractères : aucun envoi, message nom', async () => {
    const s = setup([], { name: 'Al' });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.ok(s.notices.textContent.includes('Nom manquant'));
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

  // ── Lot 5 : attribution WooCommerce (D7, T2.10) et OrderPlaced navigateur (S3-a) ──
  const orderPlacedResult = { ...accepted, famma_request_id: 'req', famma_order_placed: { event_id: 'famma_orderplaced_900',
    params: { currency: 'TND', value: 79, order_id: '900', content_type: 'product', content_ids: ['123'] } } };
  const op = s => s.events.filter(e => e[0] === 'trackCustom' && e[1] === 'OrderPlaced');

  await test('Attribution WooCommerce : les champs wc_order_attribution_* du formulaire partent avec la commande', async () => {
    const s = setup([token, quote(), accepted], { extraInputs: { wc_order_attribution_source_type: 'utm', wc_order_attribution_utm_source: 'facebook' } });
    s.submit(); await flush();
    const checkout = s.calls.find(c => c.url === '/express');
    assert.equal(field(checkout, 'wc_order_attribution_source_type'), 'utm');
    assert.equal(field(checkout, 'wc_order_attribution_utm_source'), 'facebook');
    assert.equal(names(checkout).filter(n => n === 'wc_order_attribution_source_type').length, 1, 'jamais en double');
  });

  await test('Attribution WooCommerce : élément absent (cache, doublon retiré) → valeurs demandées au script WooCommerce', async () => {
    const wcAttribution = { params: { prefix: 'wc_order_attribution_' },
      getAttributionData: () => ({ source_type: 'referral', referrer: 'https://l.facebook.com/', utm_source: null }) };
    const s = setup([token, quote(), accepted], { wcAttribution });
    s.submit(); await flush();
    const checkout = s.calls.find(c => c.url === '/express');
    assert.equal(field(checkout, 'wc_order_attribution_source_type'), 'referral');
    assert.equal(field(checkout, 'wc_order_attribution_referrer'), 'https://l.facebook.com/');
    assert.equal(field(checkout, 'wc_order_attribution_utm_source'), '', 'valeur nulle envoyée vide, comme WooCommerce');
    assert.equal(s.calls.find(c => c.url === '/prepare').values.some(e => /^wc_order_attribution_/.test(e[0])), false, 'le devis ne transporte pas l’attribution');
  });

  await test('Attribution WooCommerce : élément présent → le script WooCommerce n’est pas relu (aucun doublon)', async () => {
    let reads = 0;
    const wcAttribution = { params: { prefix: 'wc_order_attribution_' }, getAttributionData: () => { reads++; return { source_type: 'typein' }; } };
    const s = setup([token, quote(), accepted], { wcAttribution, extraInputs: { wc_order_attribution_source_type: 'utm' } });
    s.submit(); await flush();
    const checkout = s.calls.find(c => c.url === '/express');
    assert.equal(reads, 0);
    assert.deepEqual(names(checkout).filter(n => n.startsWith('wc_order_attribution_')), ['wc_order_attribution_source_type']);
  });

  await test('OrderPlaced navigateur : joint par le serveur → émis une fois avec l’eventID de la CAPI, avant la redirection', async () => {
    const store = new Map();
    const s = setup([token, quote(), orderPlacedResult], { storage: store, fbqLoaded: true });
    s.submit(); await flush();
    assert.equal(op(s).length, 1);
    assert.equal(op(s)[0][3].eventID, 'famma_orderplaced_900');
    assert.equal(op(s)[0][2].value, 79);
    assert.equal(store.get('famma_orderplaced_900'), '1', 'même clé de session que la page de confirmation');
    assert.equal(s.window.location.href, accepted.redirect);
    const again = setup([token, quote(), orderPlacedResult], { storage: store, fbqLoaded: true });
    again.submit(); await flush();
    assert.equal(op(again).length, 0, 'jamais une seconde fois dans la session');
  });

  await test('OrderPlaced navigateur : interrupteur coupé (rien de joint) → aucun OrderPlaced', async () => {
    const s = setup([token, quote(), accepted], { fbqLoaded: true });
    s.submit(); await flush();
    assert.equal(op(s).length, 0);
  });

  await test('OrderPlaced navigateur : bibliothèque Meta pas encore chargée → laissé à la page de confirmation', async () => {
    const store = new Map();
    const s = setup([token, quote(), orderPlacedResult], { storage: store });
    s.submit(); await flush();
    assert.equal(op(s).length, 0);
    assert.equal(store.has('famma_orderplaced_900'), false);
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Visiteur interne : bloc tracking vide → aucun AddToCart ni InitiateCheckout, la commande passe', async () => {
    const internal = call => ({ ...quote()(call), tracking: [] });
    const s = setup([token, internal, accepted], { fbqLoaded: true });
    s.submit(); await flush();
    assert.equal(s.events.length, 0);
    assert.equal(s.window.location.href, accepted.redirect);
  });

  // ── Lot 23 : chaque envoi refusé est signalé au journal (`form_error`) ──
  const CODES = ['phone', 'name', 'state', 'size', 'form', 'storage', 'network', 'server', 'changed'];
  const onlyCodes = s => s.blocks.every(list => list.length && list.every(c => CODES.includes(c)));

  await test('Journal : gouvernorat absent → form_error [state], aucun appel', async () => {
    const s = setup([], { state: '' });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.deepEqual(s.blocks, [['state']]);
  });

  await test('Journal : téléphone invalide → [phone] ; plusieurs champs → un seul événement, dans l’ordre du contrôle', async () => {
    const a = setup([], { phone: '123' });
    a.submit(); await flush();
    assert.deepEqual(a.blocks, [['phone']]);
    const b = setup([], { name: '', state: '' });
    b.submit(); await flush();
    assert.deepEqual(b.blocks, [['name', 'state']]);
  });

  await test('Journal : aucune valeur tapée ne part, seulement des codes', async () => {
    const s = setup([], { phone: '20 12', name: 'Am', state: '' });
    s.submit(); await flush();
    assert.ok(onlyCodes(s));
    const sent = JSON.stringify(s.blocks);
    assert.ok(!sent.includes('20 12') && !sent.includes('Am'));
  });

  await test('Journal : contrôle natif du navigateur refusé → [form]', async () => {
    const s = setup([]);
    s.form.reportValidity = () => false;
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.deepEqual(s.blocks, [['form']]);
  });

  await test('Journal : stockage bloqué → [storage]', async () => {
    const s = setup([], { storageBlocked: true });
    s.submit(); await flush();
    assert.deepEqual(s.blocks, [['storage']]);
  });

  await test('Journal : réseau coupé au devis → [network] ; réponse illisible → [network] ; refus sans champ → [server]', async () => {
    const a = setup([token, new Error('réseau coupé')]);
    a.submit(); await flush(); await flush();
    assert.deepEqual(a.blocks, [['network']]);
    const b = setup([token, 'Fatal error: <b>synthétique</b>']);
    b.submit(); await flush();
    assert.deepEqual(b.blocks, [['network']]);
    const c = setup([token, { result: 'failure', messages: '' }]);
    c.submit(); await flush();
    assert.deepEqual(c.blocks, [['server']]);
  });

  await test('Journal : refus WooCommerce data-id="billing_phone" → un seul [phone], même après la lecture du statut', async () => {
    const s = setup([token, quote(), wooPhoneError, failedState]);
    s.submit(); await flush();
    assert.deepEqual(s.blocks, [['phone']]);
  });

  await test('Journal : refus WooCommerce au devis, champ inconnu → [server] ; gouvernorat → [state]', async () => {
    const a = setup([token, { result: 'failure', messages: '<ul><li data-id="billing_postcode">Code postal</li></ul>' }]);
    a.submit(); await flush();
    assert.deepEqual(a.blocks, [['server']]);
    const b = setup([token, { result: 'failure', messages: '<ul><li data-id="billing_state">Gouvernorat invalide</li></ul>' }]);
    b.submit(); await flush();
    assert.deepEqual(b.blocks, [['state']]);
  });

  await test('Journal : panier changé entre devis et commande → [changed]', async () => {
    const s = setup([token, quote(), { result: 'failure', famma_code: 'cart_changed' }]);
    s.submit(); await flush();
    assert.deepEqual(s.blocks, [['changed']]);
  });

  await test('Journal : commande acceptée ou réponse perdue (résultat inconnu) → aucun form_error', async () => {
    const a = setup([token, quote(), accepted]);
    a.submit(); await flush();
    assert.deepEqual(a.blocks, []);
    const b = setup([token, quote(), new Error('réponse perdue')]);
    b.submit(); await flush();
    assert.equal(b.record().phase, 'unknown');
    assert.deepEqual(b.blocks, [], 'une commande a peut-être été créée : ce n’est pas un refus');
  });

  await test('Journal : navigateur sans CustomEvent → le refus s’affiche, la commande suivante passe', async () => {
    const s = setup([token, quote(), accepted], { noCustomEvent: true, state: '' });
    s.submit(); await flush();
    assert.equal(s.notices.textContent, 'Gouvernorat');
    assert.deepEqual(s.blocks, []);
    s.state.value = 'Tunis';
    s.submit(); await flush();
    assert.equal(s.window.location.href, accepted.redirect);
  });

  // ── Lot 23, tâche 3 : adresse de livraison (liste des gouvernorats + ville facultative) ──
  await test('Gabarit : liste native des gouvernorats (sans menu à recherche) et ville facultative dans le même cadre', async () => {
    const box = formSource.match(/<div class="famma-express__address-box">([\s\S]*?)<\/div>/);
    assert.ok(box, 'cadre famma-express__address-box présent');
    // Une ligne entière : les attributs contiennent du PHP (`?>`), un `[^>]*` s'arrêterait trop tôt.
    const select = box[1].match(/<select[^\n]*name="billing_state"[^\n]*/);
    const input = box[1].match(/<input[\s\S]*?name="billing_address_1"[\s\S]*?\/>/);
    assert.ok(select && input, 'gouvernorat et ville dans le cadre');
    assert.ok(box[1].indexOf(select[0]) < box[1].indexOf(input[0]), 'la liste vient avant la ville');
    assert.ok(!/data-famma-combo/.test(select[0]), 'plus de menu à recherche sur le nouveau gabarit');
    assert.ok(/\srequired[\s>]/.test(select[0]) && /autocomplete="address-level1"/.test(select[0]));
    assert.ok(!/\srequired/.test(input[0]), 'la ville n’est jamais obligatoire');
    assert.ok(/autocomplete="address-line1"/.test(input[0]) && /maxlength="100"/.test(input[0]));
  });

  await test('Adresse : gouvernorat choisi, ville vide → commande envoyée', async () => {
    const s = setup([token, quote(), accepted], { address: '' });
    s.submit(); await flush();
    assert.equal(s.window.location.href, accepted.redirect);
    assert.equal(field(s.calls.find(c => c.url === '/express'), 'billing_address_1'), '');
    assert.deepEqual(s.blocks, []);
  });

  await test('Adresse : ville saisie → envoyée dans billing_address_1 avec la commande', async () => {
    const s = setup([token, quote(), accepted], { address: 'Sahloul 3, rue des Jasmins' });
    s.submit(); await flush();
    assert.equal(field(s.calls.find(c => c.url === '/express'), 'billing_address_1'), 'Sahloul 3, rue des Jasmins');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Adresse : gouvernorat vide → message, aucun appel, [state] seul même si la ville est vide', async () => {
    const s = setup([], { state: '', address: '' });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.equal(s.notices.textContent, 'Gouvernorat');
    assert.equal(s.state.getAttribute('aria-invalid'), 'true');
    assert.equal(s.address.getAttribute('aria-invalid'), null, 'la ville n’est jamais marquée');
    assert.deepEqual(s.blocks, [['state']]);
  });

  await test('Adresse : choisir un gouvernorat dans la liste efface l’erreur, puis la commande passe', async () => {
    const s = setup([token, quote(), accepted], { state: '', address: 'Hammamet' });
    s.submit(); await flush();
    s.state.value = 'TN-21';
    s.form.fire('change', { target: s.state });
    assert.equal(s.state.getAttribute('aria-invalid'), 'false');
    assert.ok(!s.state.classList.contains('famma-express__invalid'));
    s.submit(); await flush();
    assert.equal(field(s.calls.find(c => c.url === '/express'), 'billing_state'), 'TN-21');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  // ── Lot 23, tâche 4 : la ville tapée propose le gouvernorat (liste officielle des délégations) ──
  const found = name => `Gouvernorat ajouté : ${name}. Vous pouvez le changer.`;
  const guess = (text, extra = {}) => {
    const s = setup([], { places: true, state: '', ...extra });
    s.address.value = text; s.leave();
    return s;
  };

  await test('Ville : « Hammamet » remplit Nabeul après la pause de frappe, avec le message et le flash', async () => {
    const s = setup([], { places: true, state: '' });
    s.type('Hammamet');
    assert.equal(s.state.value, '', 'rien pendant la frappe');
    s.runTimers();
    assert.equal(s.state.value, 'TN-21');
    assert.equal(s.hint.textContent, found('Nabeul'));
    assert.ok(s.state.classList.contains('famma-express__flash'), 'la liste s’allume');
    assert.equal(s.state.dispatched, 1, 'un seul change, comme un choix dans la liste');
    s.runTimers();
    assert.ok(!s.state.classList.contains('famma-express__flash'), 'puis s’éteint');
  });

  await test('Ville : « Msaken » en fin de saisie (collage, remplissage du navigateur) → Sousse sans attendre', async () => {
    const s = guess('Msaken');
    assert.equal(s.state.value, 'TN-51');
    assert.equal(s.hint.textContent, found('Sousse'));
  });

  await test('Ville : en arabe « جربة » → Médenine, aussi avec ه pour ة et sans tatwil', async () => {
    const s = guess('جربة');
    assert.equal(s.state.value, 'TN-82');
    assert.equal(s.hint.textContent, found('Médenine'));
    assert.equal(guess('جربه حومة السوق').state.value, 'TN-82');
    assert.equal(guess('قابس').state.value, 'TN-81', 'قابـس dans la source');
    assert.equal(guess('صفاقس').state.value, 'TN-61', 'nom arabe de la liste (data-alt)');
  });

  await test('Ville : majuscules, accents, apostrophes, articles et noms composés', async () => {
    [
      ['HAMMAMET', 'TN-21'], ['M’saken', 'TN-51'], ["M'nihla", 'TN-12'], ['Le Kef', 'TN-33'], ['El Kef', 'TN-33'],
      ['kef', 'TN-33'], ['beja', 'TN-31'], ['Mourouj 3', 'TN-13'], ['Ksar Helal', 'TN-52'], ['Sahloul, Hammam Sousse', 'TN-51'],
      ['Douz', 'TN-73'], ['Bizerte', 'TN-23'], ['Sayada', 'TN-52'], ['cité Ennour, Sfax', 'TN-61'],
    ].forEach(([text, code]) => assert.equal(guess(text).state.value, code, text));
  });

  await test('Ville : chacun des noms officiels, tapé seul, donne son gouvernorat ou rien, jamais un autre', async () => {
    const s = setup([], { places: true, state: '' });
    const misses = [];
    Object.entries(placesData).forEach(([code, names]) => {
      let fr = 0, ar = 0;
      names.forEach(name => {
        s.state.value = ''; s.state.fire('change');
        s.address.value = name; s.leave();
        if (s.state.value && s.state.value !== code) misses.push(`${name} → ${s.state.value} (attendu ${code})`);
        if (s.state.value === code) { if (/[؀-ۿ]/.test(name)) ar++; else fr++; }
      });
      assert.ok(fr > 0 && ar > 0, `${code} : au moins un nom reconnu en français et en arabe`);
    });
    assert.deepEqual(misses, []);
  });

  await test('Ville : prudence, deux gouvernorats au même rang ou nom commun → aucun remplissage', async () => {
    ['Rue de Tunis, Sfax', 'Azzouhour', 'Médina', 'المدينة', 'Ksar Said', 'Sidi', 'Hammam', 'Sahloul 3', ''].forEach(text => {
      const s = guess(text);
      assert.equal(s.state.value, '', text);
      assert.equal(s.state.dispatched, undefined, text);
      assert.equal(s.hint.textContent, 'Choisissez le gouvernorat.', text);
    });
  });

  await test('Ville : un gouvernorat choisi à la main n’est jamais remplacé', async () => {
    const s = setup([], { places: true, state: '' });
    s.state.value = 'TN-11'; s.state.fire('change');
    s.type('Hammamet'); s.runTimers(); s.leave();
    assert.equal(s.state.value, 'TN-11');
    assert.equal(s.hint.textContent, 'Choisissez le gouvernorat.');
  });

  await test('Ville : gouvernorat déjà rempli au chargement (retour, remplissage du navigateur) → gardé', async () => {
    const s = guess('Hammamet', { state: 'TN-51' });
    assert.equal(s.state.value, 'TN-51');
    assert.equal(s.state.dispatched, undefined);
  });

  await test('Ville : la proposition suit la frappe ; changée à la main elle reste ; remise à vide elle reprend', async () => {
    const s = setup([], { places: true, state: '' });
    s.type('Hammamet'); s.runTimers();
    s.type('Sfax'); s.runTimers();
    assert.equal(s.state.value, 'TN-61', 'la ville corrigée corrige la proposition');
    s.type('Sfax, rue sans nom'); s.runTimers();
    assert.equal(s.state.value, 'TN-61', 'un texte qui ne reconnaît plus rien garde la proposition');
    s.state.value = 'TN-11'; s.state.fire('change');
    assert.equal(s.hint.textContent, 'Choisissez le gouvernorat.', 'choix à la main : l’indication revient');
    s.type('Bizerte'); s.runTimers();
    assert.equal(s.state.value, 'TN-11');
    s.state.value = ''; s.state.fire('change');
    s.type('Bizerte'); s.runTimers();
    assert.equal(s.state.value, 'TN-23', 'remis sur « Gouvernorat » : la proposition reprend');
  });

  await test('Ville : la proposition efface l’erreur du gouvernorat, la commande part avec TN-21 et la ville', async () => {
    const s = setup([token, quote(), accepted], { places: true, state: '' });
    s.submit(); await flush();
    assert.equal(s.state.getAttribute('aria-invalid'), 'true');
    s.type('Hammamet'); s.runTimers();
    assert.equal(s.state.getAttribute('aria-invalid'), 'false');
    assert.ok(!s.state.classList.contains('famma-express__invalid'));
    s.submit(); await flush();
    const sent = s.calls.find(c => c.url === '/express');
    assert.equal(field(sent, 'billing_state'), 'TN-21');
    assert.equal(field(sent, 'billing_address_1'), 'Hammamet');
    assert.equal(s.window.location.href, accepted.redirect);
    assert.deepEqual(s.blocks, [['state']]);
  });

  await test('Ville : sans données de lieux (page en cache), seuls les intitulés de la liste ; liste désactivée → rien', async () => {
    assert.equal(guess('Sousse', { noPlacesData: true }).state.value, 'TN-51');
    assert.equal(guess('Hammamet', { noPlacesData: true }).state.value, '');
    const s = setup([], { places: true, state: '' });
    s.state.disabled = true;
    s.address.value = 'Hammamet'; s.leave();
    assert.equal(s.state.value, '', 'pendant un envoi en cours, rien ne bouge');
  });

  await test('Gabarit : indication annoncée (aria-live), lieux en script en ligne protégé, message traduisible', async () => {
    assert.ok(/class="famma-express__hint"[^\n]*aria-live="polite"/.test(formSource), 'aria-live sur l’indication');
    assert.ok(/'var fammaExpressPlaces = ' \. wp_json_encode\( self::places_for_script\(\), [^)]*JSON_HEX_TAG/.test(formSource), 'JSON_HEX_TAG');
    assert.ok(/wp_add_inline_script\(\s*'famma-express',[\s\S]*?'before'\s*\)/.test(formSource), 'avant le script');
    assert.ok(/'stateFound'\s*=> __\( 'Gouvernorat ajouté : %s\. Vous pouvez le changer\.', 'famma-core' \)/.test(formSource));
  });

  // ── Lot 23, tâche 5 : téléphone en premier, saisie adaptée au navigateur de Facebook ──
  const typed = (s, value, extra = {}) => { s.phone.value = value; s.phone.fire('input', extra); return s.phone.value; };

  await test('Gabarit : ordre téléphone → nom → gouvernorat → ville → taille → bouton, attributs de saisie', async () => {
    const body = formSource.slice(formSource.indexOf('public function render()'));
    const order = ['$this->phone_field()', "'billing_first_name'", '$this->address_field()', 'Product_Sizes::render_field', 'famma-express__submit'].map(k => body.indexOf(k));
    assert.ok(order.every(i => i > 0), 'tous présents dans render()');
    assert.deepEqual([...order].sort((a, b) => a - b), order, 'dans cet ordre');
    const phoneField = formSource.slice(formSource.indexOf('private function phone_field()'), formSource.indexOf('private function quantity_field('));
    assert.ok(/<span class="famma-express__phone-box" dir="ltr">\s*<span class="famma-express__prefix" aria-hidden="true">\+216<\/span>/.test(phoneField), '+216 fixe, hors de la valeur');
    ['type="tel"', 'name="billing_phone"', 'autocomplete="tel-national"', 'inputmode="numeric"', 'enterkeyhint="next"', 'placeholder="XX XXX XXX"', 'required']
      .forEach(a => assert.ok(phoneField.includes(a), a));
    assert.ok(/'autocapitalize' => 'words',\s*'enterkeyhint'\s*=> 'next',/.test(body), 'nom : majuscules aux mots, touche Suivant');
    assert.ok(/name="billing_address_1"[\s\S]*?enterkeyhint="send"/.test(formSource), 'ville : dernier champ tapé, la touche envoie');
  });

  await test('Téléphone : groupé « XX XXX XXX » pendant la frappe', async () => {
    const s = setup([], { phone: '' });
    assert.equal(typed(s, '2'), '2');
    assert.equal(typed(s, '201'), '20 1');
    assert.equal(typed(s, '20 1234'), '20 123 4');
    assert.equal(typed(s, '20 123 456'), '20 123 456');
  });

  await test('Téléphone : +216, 00216, espaces ou chiffres arabes collés → 8 chiffres groupés ; 9 chiffres laissés tels quels', async () => {
    [['+216 20 123 456', '20 123 456'], ['00216 20123456', '20 123 456'], ['٢٠١٢٣٤٥٦', '20 123 456'], ['20-123-456', '20 123 456'], ['21612345', '21 612 345']]
      .forEach(([paste, shown]) => assert.equal(typed(setup([], { phone: '' }), paste), shown, paste));
    const s = setup([], { phone: '' });
    assert.equal(typed(s, '201234567'), '201234567');
    s.phone.fire('blur');
    assert.equal(s.phone.getAttribute('aria-invalid'), 'true', 'signalé à la sortie du champ');
  });

  await test('Téléphone : le curseur reste après le même chiffre ; effacer une espace efface le chiffre d’avant', async () => {
    const s = setup([], { phone: '' });
    s.phone.setSelectionRange = (a, b) => { s.phone.sel = [a, b]; };
    s.document.activeElement = s.phone;
    typed(s, '20 123 45');
    s.phone.selectionStart = 5; // « 20 19|23 45 » : un 9 inséré après « 20 1 »
    assert.equal(typed(s, '20 1923 45'), '20 192 345');
    assert.deepEqual(s.phone.sel, [5, 5]);
    typed(s, '20 123 456');
    s.phone.selectionStart = 2; // « 20|123 456 » : l'espace vient d'être effacée
    assert.equal(typed(s, '20123 456', { inputType: 'deleteContentBackward' }), '21 234 56');
    assert.deepEqual(s.phone.sel, [1, 1]);
  });

  await test('Téléphone groupé : la commande part, le serveur reçoit le numéro tel qu’affiché', async () => {
    const s = setup([token, quote(), accepted], { phone: '' });
    typed(s, '+216 98 765 432');
    s.submit(); await flush();
    assert.equal(field(s.calls.find(c => c.url === '/express'), 'billing_phone'), '98 765 432');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  await test('Touche « Suivant » : téléphone → nom → ville (la liste est sautée) ; « Envoyer » et l’ancien gabarit gardent l’envoi', async () => {
    const s = setup([], { places: true, state: '' });
    s.phone.setAttribute('enterkeyhint', 'next'); s.name.setAttribute('enterkeyhint', 'next'); s.address.setAttribute('enterkeyhint', 'send');
    const enter = (target, extra = {}) => { let stopped = false; s.form.fire('keydown', { target, key: 'Enter', preventDefault() { stopped = true; }, ...extra }); return stopped; };
    assert.ok(enter(s.phone) && s.name.focused, 'téléphone → nom');
    assert.ok(enter(s.name) && s.address.focused, 'nom → ville');
    assert.ok(!enter(s.address), 'ville : la touche envoie le formulaire');
    s.name.focused = false;
    assert.ok(!enter(s.phone, { isComposing: true }) && !s.name.focused, 'saisie en cours (IME) : rien');
    s.phone.removeAttribute('enterkeyhint');
    assert.ok(!enter(s.phone), 'ancien gabarit sans enterkeyhint : comportement du navigateur');
  });

  await test('Clavier ouvert : un champ caché remonte au tiers de la zone visible, un champ visible ne bouge pas', async () => {
    const s = setup([], { viewport: { offsetTop: 0, height: 390 } });
    s.form.contains = () => true;
    s.document.activeElement = s.phone;
    s.phone.getBoundingClientRect = () => ({ top: 700, bottom: 748 });
    s.form.fire('focusin', { target: s.phone });
    assert.deepEqual(s.scrolls, [], 'rien avant que le clavier ait fini de s’ouvrir');
    s.runTimers();
    assert.deepEqual(s.scrolls, [700 - 390 / 3]);
    s.phone.getBoundingClientRect = () => ({ top: 120, bottom: 168 });
    s.window.visualViewport.listeners.forEach(cb => cb());
    assert.equal(s.scrolls.length, 1, 'déjà visible : aucun défilement');
    s.document.activeElement = null;
    s.window.visualViewport.listeners.forEach(cb => cb());
    assert.equal(s.scrolls.length, 1, 'aucun champ actif : rien');
  });

  // ── Lot 23, tâche 6 : phrase sous le téléphone, modifiable dans l'admin ──
  await test('Phrase sous le téléphone : l’erreur du numéro reste affichée (premier identifiant de aria-describedby)', async () => {
    const s = setup([], { phone: '123', phoneNote: true });
    s.submit(); await flush();
    assert.equal(s.calls.length, 0);
    assert.equal(s.phoneError.textContent, 'Téléphone invalide');
    assert.equal(s.phoneError.hidden, false);
    assert.deepEqual(s.blocks, [['phone']]);
  });

  // ── Lot 23, tâche 7 : la commande emporte la session du journal (secours des cookies) ──
  await test('Session du journal : champs cachés dans le gabarit, envoyés avec la commande, pas avec le devis', async () => {
    assert.ok(/<input type="hidden" name="<\?php echo esc_attr\( Visitor_Commerce::FIELD_VISITOR \); \?>" value="" \/>/.test(formSource), 'visiteur, vide dans la page en cache');
    assert.ok(/<input type="hidden" name="<\?php echo esc_attr\( Visitor_Commerce::FIELD_SESSION \); \?>" value="" \/>/.test(formSource), 'session, vide dans la page en cache');
    const ids = { famma_vj_visitor: '33333333-3333-4333-a333-333333333333', famma_vj_session: '44444444-4444-4444-b444-444444444444' };
    const s = setup([token, quote(), accepted], { extraInputs: ids });
    s.submit(); await flush();
    const sent = s.calls.find(c => c.url === '/express');
    assert.equal(field(sent, 'famma_vj_visitor'), ids.famma_vj_visitor);
    assert.equal(field(sent, 'famma_vj_session'), ids.famma_vj_session);
    assert.equal(field(s.calls.find(c => c.url === '/prepare'), 'famma_vj_session'), undefined, 'le devis ne les reçoit pas');
    assert.equal(s.window.location.href, accepted.redirect);
  });

  console.log(`RESULT ${passed}/${passed + failed} scénarios front réussis${failed ? `, ${failed} ÉCHEC(S)` : ''}. Aucun réseau WooCommerce ni Meta utilisé.`);
  if (failed) process.exitCode = 1;
})().catch(e => { console.error(e); process.exitCode = 1; });
