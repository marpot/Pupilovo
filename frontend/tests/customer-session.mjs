import assert from 'node:assert/strict';
import { readFile, writeFile, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import ts from 'typescript';

// Exercise the real API modules with HTTP responses and browser state under control.
const directory = await mkdtemp(join(tmpdir(), 'pupilovo-customer-session-'));
try {
  for (const name of ['auth', 'cart', 'checkout']) {
    const source = await readFile(new URL(`../src/api/${name}.ts`, import.meta.url), 'utf8');
    const output = ts.transpileModule(source, {
      compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ES2022 },
    }).outputText.replaceAll('@/api/', './').replace(/from '(\.\/\w+)'/g, "from '$1.mjs'");
    await writeFile(join(directory, `${name}.mjs`), output);
  }
  const storage = new Map();
  globalThis.sessionStorage = { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) };
  let redirects = [];
  let events = [];
  globalThis.window = { location: { assign: url => redirects.push(url) }, dispatchEvent: event => events.push(event) };
  globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
  const guest = { authenticated: false, user: null, nonce: null };
  const customer = id => ({ authenticated: true, user: { id, email: `test-${id}@example.com` }, nonce: `nonce-${id}` });
  let session = guest;
  let status = 200;
  let meStatus = 200;
  let requests = [];
  globalThis.fetch = async (url, options = {}) => {
    requests.push({ url, options });
    const me = url.endsWith('/auth/me');
    const login = /\/auth\/(login|google|register)$/.test(url);
    const data = me ? session : login ? { ...session, cartToken: `customer-cart-${session.user.id}` }
      : url.endsWith('/auth/logout') ? guest : { items_count: 1, order_id: 1 };
    return new Response(JSON.stringify(data), { status: me ? meStatus : status, headers: { 'Content-Type': 'application/json' } });
  };
  const auth = await import(join(directory, 'auth.mjs'));
  const cart = await import(join(directory, 'cart.mjs'));
  const checkout = await import(join(directory, 'checkout.mjs'));
  let checks = 0;
  const pass = label => { checks++; console.log(`PASS: ${label}`); };
  const reset = () => { storage.clear(); cart.clearCartToken(); requests = []; redirects = []; events = []; status = 200; meStatus = 200; session = guest; };

  for (const authenticated of [false, true]) {
    for (const action of [() => cart.getCart(), () => cart.addCartItem(1, 1), () => checkout.processCheckout({})]) {
      reset(); session = authenticated ? customer(1) : guest; cart.setCartToken('initial-cart');
      await action();
      assert.equal(requests.length, 2);
      assert.ok(requests[0].url.endsWith('/auth/me'));
      assert.equal(requests[0].options.credentials, 'include');
      assert.equal(requests[1].options.headers.get('Cart-Token'), 'initial-cart');
      assert.equal(requests[1].options.headers.get('X-WP-Nonce'), session.nonce);
      assert.equal(redirects.length, 0);
      pass(`cart read/write or checkout: ${authenticated ? 'authenticated' : 'guest'}`);
    }
  }
  for (const current of [guest, customer(2)]) {
    reset(); session = customer(1); await auth.getCurrentUser(); cart.setCartToken('private-cart');
    session = current; requests = [];
    await assert.rejects(() => checkout.processCheckout({}), { code: 'session_expired' });
    assert.equal(requests.length, 1, 'checkout must not be sent');
    assert.equal(cart.getCartToken(), null);
    assert.equal(storage.has('pupilovo-customer-id'), false);
    assert.deepEqual(redirects, ['/account?session=expired']);
    assert.equal(events.at(-1).detail.count, 0);
    pass(current.authenticated ? 'account change blocks old checkout' : 'expired session blocks checkout and clears private state');
  }
  reset(); storage.set('pupilovo-customer-id', '1'); cart.setCartToken('private-cart');
  await assert.rejects(() => cart.getCart(), { code: 'session_expired' });
  assert.equal(cart.getCartToken(), null);
  pass('persisted customer marker detects expiry after page reload');

  for (const code of [401, 403]) {
    for (const endpoint of ['me', 'cart', 'checkout', 'orders']) {
      reset(); session = customer(1); await auth.getCurrentUser(); cart.setCartToken('private-cart');
      if (endpoint === 'me') meStatus = code; else status = code;
      const action = endpoint === 'me' ? () => auth.getCurrentUser() : endpoint === 'cart' ? () => cart.getCart()
        : endpoint === 'checkout' ? () => checkout.processCheckout({}) : () => auth.getAccountData('/wp-json/pupilovo/v1/account/orders');
      await assert.rejects(action, { code: 'session_expired' });
      assert.equal(cart.getCartToken(), null);
      assert.equal(storage.has('pupilovo-customer-id'), false);
      assert.deepEqual(redirects, ['/account?session=expired']);
      pass(`${code} from ${endpoint} returns to safe login state`);
    }
  }
  for (const method of ['loginCustomer', 'registerCustomer', 'loginWithGoogle']) {
    reset(); cart.setCartToken('guest-cart'); session = customer(2);
    await auth[method](method === 'loginWithGoogle' ? 'test-credential' : { email: 'test@example.com', password: 'test-only' });
    assert.equal(requests[0].options.headers.get('Cart-Token'), 'guest-cart');
    assert.equal(cart.getCartToken(), 'customer-cart-2');
    assert.equal(storage.get('pupilovo-customer-id'), '2');
    pass(`${method} rotates guest token and records current customer`);
  }
  reset(); session = customer(1); await auth.getCurrentUser(); cart.setCartToken('private-cart');
  await auth.logoutCustomer();
  assert.equal(cart.getCartToken(), null);
  assert.equal(storage.has('pupilovo-customer-id'), false);
  assert.equal(events.at(-1).detail.count, 0);
  pass('logout clears customer token and header count');
  console.log(`${checks} customer session frontend checks passed`);
} finally {
  await rm(directory, { recursive: true, force: true });
}
