'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { createShop } = require('../server');

const customer = { name: 'Ada Lovelace', email: 'ada@example.com', address: '1 Analytical Way, London' };
const card = { number: '4242424242424242', expiry: '12/30', cvc: '123' };

/** Starts a fresh shop (its own stock) on a random port. Returns the base URL and a close function. */
async function start() {
  const server = http.createServer(createShop());
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const { port } = server.address();
  return {
    base: `http://127.0.0.1:${port}`,
    close: () => new Promise((resolve) => server.close(resolve)),
  };
}

function post(base, path, body, raw) {
  return fetch(base + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: raw !== undefined ? raw : JSON.stringify(body),
  });
}

test('lists the products without exposing exact stock counts', async () => {
  const shop = await start();
  try {
    const res = await fetch(`${shop.base}/api/products`);
    assert.equal(res.status, 200);
    const { products } = await res.json();
    assert.equal(products.length, 4);
    const street = products.find((p) => p.id === 'street-high');
    assert.ok(street.sizes.some((s) => s.size === '9' && s.inStock === false));
    assert.equal(JSON.stringify(products).includes('"stock"'), false);
  } finally {
    await shop.close();
  }
});

test('returns one product, or 404 for an unknown id', async () => {
  const shop = await start();
  try {
    const ok = await fetch(`${shop.base}/api/products/trail-ridge`);
    assert.equal(ok.status, 200);
    assert.equal((await ok.json()).product.name, 'Trail Ridge GTX');

    const missing = await fetch(`${shop.base}/api/products/nope`);
    assert.equal(missing.status, 404);
    assert.equal((await missing.json()).error.code, 'not_found');
  } finally {
    await shop.close();
  }
});

test('places an order over HTTP, returns 201 and the order can be fetched again', async () => {
  const shop = await start();
  try {
    const res = await post(shop.base, '/api/orders', {
      items: [{ id: 'runner-x1', size: '9', qty: 1 }],
      customer,
      card,
    });
    assert.equal(res.status, 201);
    const { order } = await res.json();
    assert.equal(order.subtotalCents, 12900);
    assert.equal(order.shippingCents, 0);
    assert.equal(order.totalCents, 12900);

    const again = await fetch(`${shop.base}/api/orders/${order.id}`);
    assert.equal(again.status, 200);
    assert.equal((await again.json()).order.id, order.id);
  } finally {
    await shop.close();
  }
});

test('returns the payment error with its status and code', async () => {
  const shop = await start();
  try {
    const res = await post(shop.base, '/api/orders', {
      items: [{ id: 'runner-x1', size: '9', qty: 1 }],
      customer,
      card: { number: '4000000000000002', expiry: '12/30', cvc: '123' },
    });
    assert.equal(res.status, 402);
    assert.equal((await res.json()).error.code, 'card_declined');
  } finally {
    await shop.close();
  }
});

test('returns field errors as 422 with a fields map', async () => {
  const shop = await start();
  try {
    const res = await post(shop.base, '/api/orders', {
      items: [{ id: 'runner-x1', size: '9', qty: 1 }],
      customer: { name: '', email: '', address: '' },
      card: { number: '', expiry: '', cvc: '' },
    });
    assert.equal(res.status, 422);
    const { error } = await res.json();
    assert.equal(error.code, 'invalid_form');
    assert.ok(error.fields.email);
    assert.ok(error.fields.number);
  } finally {
    await shop.close();
  }
});

test('handles bad JSON, a non-object body and an oversized body', async () => {
  const shop = await start();
  try {
    const badJson = await post(shop.base, '/api/orders', undefined, '{not json');
    assert.equal(badJson.status, 400);
    assert.equal((await badJson.json()).error.code, 'invalid_json');

    const nullBody = await post(shop.base, '/api/orders', undefined, 'null');
    assert.equal(nullBody.status, 400);
    assert.equal((await nullBody.json()).error.code, 'empty_cart');

    const big = await post(shop.base, '/api/orders', undefined, JSON.stringify({ note: 'x'.repeat(70 * 1024) }));
    assert.equal(big.status, 413);
  } finally {
    await shop.close();
  }
});

test('returns 404 for an unknown order and for unknown API routes', async () => {
  const shop = await start();
  try {
    assert.equal((await fetch(`${shop.base}/api/orders/ORD-NOPE0000`)).status, 404);
    assert.equal((await fetch(`${shop.base}/api/unknown`)).status, 404);
  } finally {
    await shop.close();
  }
});

test('uses 405 for the wrong method on the order endpoint and on the static site', async () => {
  const shop = await start();
  try {
    assert.equal((await fetch(`${shop.base}/api/orders`)).status, 405);
    assert.equal((await fetch(`${shop.base}/`, { method: 'POST', body: '{}' })).status, 405);
  } finally {
    await shop.close();
  }
});

test('serves the storefront with the right content types and security headers', async () => {
  const shop = await start();
  try {
    const page = await fetch(`${shop.base}/`);
    assert.equal(page.status, 200);
    assert.match(page.headers.get('content-type'), /text\/html/);
    assert.equal(page.headers.get('x-content-type-options'), 'nosniff');
    assert.match(await page.text(), /Stride &amp; Co\./);

    const script = await fetch(`${shop.base}/app.js`);
    assert.match(script.headers.get('content-type'), /javascript/);

    const style = await fetch(`${shop.base}/styles.css`);
    assert.match(style.headers.get('content-type'), /text\/css/);
  } finally {
    await shop.close();
  }
});

test('does not serve files outside public/', async () => {
  const shop = await start();
  try {
    // "..%2f" survives URL parsing and becomes "../" only when the path is decoded,
    // so this request reaches the guard in serveStatic.
    for (const target of ['/..%2fserver.js', '/..%2fpackage.json', '/..%2f..%2fetc%2fpasswd']) {
      const res = await fetch(`${shop.base}${target}`);
      assert.equal(res.status, 404, target);
      const text = await res.text();
      assert.equal(text.includes('createShop'), false, target);
    }
  } finally {
    await shop.close();
  }
});
