/**
 * Phase B du pixel — external_id côté navigateur (Capi_External_Id::browser_script()).
 *
 * Le script imprimé avant fbq('init') est produit par le vrai PHP (même binaire et
 * mêmes options que le lanceur : FAMMA_PHP, FAMMA_PHP_ARGS), puis exécuté dans une VM Node :
 * - fammaSha256() = SHA-256 de Node sur des chaînes de 0 à 130 caractères (bords de bloc 55/56/64) ;
 * - cookie famma_vid présent : fammaXid().external_id = empreinte PHP (Capi_External_Id::from_visitor),
 *   la même que la CAPI met dans user_data.external_id ; majuscules ramenées en minuscules ;
 * - cookie absent, journal chargé sur la page : cookie créé aux règles du journal, empreinte de ce cookie ;
 * - cookie absent, équipe ou administration (journal non chargé) : rien, aucun cookie créé ;
 * - cookie invalide : rien, cookie conservé ; sans crypto : rien d'inventé.
 *
 * Usage : node scripts/tests-unit/test-capi-external-id.js
 */
'use strict';
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');

const plugin = path.join(__dirname, '../../wp-content/plugins/famma-core');
const php = process.env.FAMMA_PHP || 'php';
const phpArgs = (process.env.FAMMA_PHP_ARGS || '').trim().split(/\s+/).filter(Boolean);
const UUID = '3f2b8c1e-9d4a-4c7b-8e2f-1a6d5c4b3e21';
const uuidV4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

/** Script et empreintes produits par le vrai PHP (journal chargé ou non sur la page). */
function fromPhp(journal) {
  const code = [
    'namespace { define("ABSPATH", "x"); function is_user_logged_in() { return false; } function current_user_can() { return false; }',
    'function is_admin() { return ' + (journal ? 'false' : 'true') + '; } function absint($v) { return abs((int) $v); }',
    'function get_option($n, $d = false) { return "famma_visitor_retention_days" === $n ? 120 : $d; } }',
    'namespace { foreach (array("visitor-context.php", "visitor-settings.php", "capi-external-id.php") as $f) { require $argv[1] . "/includes/" . $f; }',
    'echo json_encode(array("script" => \\Famma\\Core\\Capi_External_Id::browser_script(), "upper" => \\Famma\\Core\\Capi_External_Id::from_visitor(strtoupper($argv[2])), "hash" => \\Famma\\Core\\Capi_External_Id::from_visitor($argv[2]), "bad" => \\Famma\\Core\\Capi_External_Id::from_visitor("pas-un-uuid"))); }',
  ].join('\n');
  return JSON.parse(execFileSync(php, [...phpArgs, '-r', code, '--', plugin, UUID], { encoding: 'utf8' }));
}

/** Navigateur minimal : un pot à cookies, https, crypto au choix. */
function browser(script, cookies = {}, cryptoImpl = crypto.webcrypto) {
  const jar = Object.assign({}, cookies);
  const writes = [];
  const document = {};
  Object.defineProperty(document, 'cookie', {
    get: () => Object.keys(jar).map((k) => k + '=' + jar[k]).join('; '),
    set: (raw) => { writes.push(raw); const pair = raw.split(';')[0]; const i = pair.indexOf('='); jar[pair.slice(0, i)] = pair.slice(i + 1); },
  });
  const context = { document, location: { protocol: 'https:' }, Uint8Array, Math, String, decodeURIComponent };
  if (cryptoImpl) context.crypto = cryptoImpl;
  context.window = context;
  vm.createContext(context);
  vm.runInContext(script, context);
  return { xid: () => context.fammaXid(), sha: (s) => context.fammaSha256(s), jar, writes };
}
const sha256 = (s) => crypto.createHash('sha256').update(s, 'latin1').digest('hex');

let passed = 0, failed = 0;
function test(name, run) {
  try { run(); passed++; console.log('PASS: ' + name); } catch (e) { failed++; console.log('FAIL: ' + name + '\n     ' + String(e && e.message).split('\n').join('\n     ')); }
}

const on = fromPhp(true);
const off = fromPhp(false);

test('fammaSha256 = SHA-256 de Node, chaînes de 0 à 130 caractères (bords de bloc compris)', () => {
  const page = browser(on.script);
  assert.equal(page.sha('abc'), 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
  for (let n = 0; n <= 130; n++) {
    const s = 'x'.repeat(n).split('').map((c, i) => String.fromCharCode(32 + ((i * 7 + n) % 95))).join('');
    assert.equal(page.sha(s), sha256(s), 'longueur ' + n);
  }
  assert.equal(page.sha('é'.repeat(2) + 'Ā'), undefined, 'non ASCII : rien');
});

test('Cookie famma_vid présent : external_id navigateur = empreinte PHP (celle de la CAPI) = SHA-256 de l’UUID', () => {
  const page = browser(on.script, { famma_vid: UUID });
  assert.deepEqual({ ...page.xid() }, { external_id: on.hash });
  assert.equal(on.hash, sha256(UUID));
  assert.equal(page.writes.length, 0, 'aucun cookie réécrit');
});

test('Majuscules : ramenées en minuscules des deux côtés, même empreinte', () => {
  const page = browser(on.script, { famma_vid: UUID.toUpperCase() });
  assert.equal(page.xid().external_id, on.hash);
  assert.equal(on.upper, on.hash);
});

test('Premier affichage, journal chargé : famma_vid créé aux règles du journal, external_id = son empreinte', () => {
  const page = browser(on.script);
  const xid = page.xid();
  assert.match(page.jar.famma_vid, uuidV4);
  assert.equal(xid.external_id, sha256(page.jar.famma_vid));
  assert.match(page.writes[0], /^famma_vid=[0-9a-f-]{36};path=\/;max-age=10368000;SameSite=Lax;Secure$/);
});

test('Premier affichage, repli getRandomValues : UUID v4 valide', () => {
  const page = browser(on.script, {}, { getRandomValues: (a) => crypto.webcrypto.getRandomValues(a) });
  const xid = page.xid();
  assert.match(page.jar.famma_vid, uuidV4);
  assert.equal(xid.external_id, sha256(page.jar.famma_vid));
});

test('Journal non chargé (équipe, administration) : aucun external_id, aucun cookie créé', () => {
  const page = browser(off.script);
  assert.equal(page.xid(), undefined);
  assert.equal(page.writes.length, 0);
  assert.equal(browser(off.script, { famma_vid: UUID }).xid().external_id, on.hash, 'cookie déjà là : même empreinte');
});

test('Cookie invalide : rien envoyé, cookie conservé ; côté PHP, rien non plus', () => {
  const page = browser(on.script, { famma_vid: 'pas-un-uuid' });
  assert.equal(page.xid(), undefined);
  assert.equal(page.jar.famma_vid, 'pas-un-uuid');
  assert.equal(on.bad, '');
});

test('Sans crypto : aucun identifiant inventé', () => {
  const page = browser(on.script, {}, null);
  assert.equal(page.xid(), undefined);
  assert.equal(page.writes.length, 0);
});

console.log('RESULT ' + passed + '/' + (passed + failed));
process.exit(failed ? 1 : 0);
