'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { createCatalog } = require('../src/catalog');
const { createMockGateway } = require('../src/payments/mockGateway');
const { createOrderService, ShopError } = require('../src/orders');

const NOW = () => new Date('2026-06-15T12:00:00Z');
const customer = { name: 'Ada Lovelace', email: 'ada@example.com', address: '1 Analytical Way, London' };
const card = { number: '4242 4242 4242 4242', expiry: '12/30', cvc: '123' };

function setup(gateway = createMockGateway()) {
  const catalog = createCatalog();
  const service = createOrderService({ catalog, gateway, now: NOW });
  return { catalog, service };
}

function stockOf(catalog, id, size) {
  return catalog.find(id).sizes.find((s) => s.size === size).stock;
}

async function expectError(promise, status, code) {
  await assert.rejects(promise, (err) => {
    assert.ok(err instanceof ShopError, `expected ShopError, got ${err}`);
    assert.equal(err.status, status);
    assert.equal(err.code, code);
    return true;
  });
}

test('places an order, prices it from the catalog and adds shipping under $100', async () => {
  const { catalog, service } = setup();
  const order = await service.placeOrder({ items: [{ id: 'street-high', size: '8', qty: 1 }], customer, card });

  assert.match(order.id, /^ORD-[0-9A-F]{8}$/);
  assert.equal(order.status, 'paid');
  assert.equal(order.subtotalCents, 8900);
  assert.equal(order.shippingCents, 499);
  assert.equal(order.totalCents, 9399);
  assert.equal(order.items[0].unitPriceCents, 8900);
  assert.equal(order.payment.status, 'approved');
  assert.equal(order.payment.gateway, 'mock');
  assert.equal(stockOf(catalog, 'street-high', '8'), 4);
});

test('ships free for orders of $100 or more', async () => {
  const { service } = setup();
  const order = await service.placeOrder({ items: [{ id: 'city-loafer', size: '9', qty: 1 }], customer, card });
  assert.equal(order.subtotalCents, 15900);
  assert.equal(order.shippingCents, 0);
  assert.equal(order.totalCents, 15900);
});

test('merges repeated lines and multiplies by quantity', async () => {
  const { catalog, service } = setup();
  const order = await service.placeOrder({
    items: [
      { id: 'street-high', size: '6', qty: 2 },
      { id: 'street-high', size: '6', qty: 3 },
    ],
    customer,
    card,
  });
  assert.equal(order.items.length, 1);
  assert.equal(order.items[0].qty, 5);
  assert.equal(order.subtotalCents, 8900 * 5);
  assert.equal(stockOf(catalog, 'street-high', '6'), 0);
});

test('rejects a line that asks for more stock than there is, and takes nothing', async () => {
  const { catalog, service } = setup();
  await expectError(service.placeOrder({ items: [{ id: 'city-loafer', size: '11', qty: 2 }], customer, card }), 409, 'out_of_stock');
  assert.equal(stockOf(catalog, 'city-loafer', '11'), 1);
});

test('says a size is sold out when it has no stock left', async () => {
  const { service } = setup();
  await assert.rejects(
    service.placeOrder({ items: [{ id: 'street-high', size: '9', qty: 1 }], customer, card }),
    (err) => err.status === 409 && /sold out/.test(err.message)
  );
});

test('a declined card gives 402 and puts the stock back', async () => {
  const { catalog, service } = setup();
  const declineCard = { number: '4000 0000 0000 0002', expiry: '12/30', cvc: '123' };
  await expectError(
    service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 2 }], customer, card: declineCard }),
    402,
    'card_declined'
  );
  assert.equal(stockOf(catalog, 'runner-x1', '9'), 6);
});

test('a gateway that fails gives 502 and puts the stock back', async () => {
  const broken = { name: 'broken', charge: async () => { throw new Error('network down'); } };
  const { catalog, service } = setup(broken);
  await expectError(service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 1 }], customer, card }), 502, 'payment_unavailable');
  assert.equal(stockOf(catalog, 'runner-x1', '9'), 6);
});

test('returns field errors for a bad customer and card before touching stock', async () => {
  const { catalog, service } = setup();
  await assert.rejects(
    service.placeOrder({
      items: [{ id: 'runner-x1', size: '9', qty: 1 }],
      customer: { name: 'A', email: 'not-an-email', address: '' },
      card: { number: '1111', expiry: 'soon', cvc: '1' },
    }),
    (err) => {
      assert.equal(err.status, 422);
      assert.equal(err.code, 'invalid_form');
      assert.deepEqual(Object.keys(err.fields).sort(), ['address', 'cvc', 'email', 'expiry', 'name', 'number']);
      return true;
    }
  );
  assert.equal(stockOf(catalog, 'runner-x1', '9'), 6);
});

test('rejects an empty cart, bad quantities, and unknown products or sizes', async () => {
  const { service } = setup();
  await expectError(service.placeOrder({ items: [], customer, card }), 400, 'empty_cart');
  await expectError(service.placeOrder({ customer, card }), 400, 'empty_cart');
  await expectError(service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 0 }], customer, card }), 400, 'invalid_quantity');
  await expectError(service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 11 }], customer, card }), 400, 'invalid_quantity');
  await expectError(service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 1.5 }], customer, card }), 400, 'invalid_quantity');
  await expectError(service.placeOrder({ items: [{ id: 'nope', size: '9', qty: 1 }], customer, card }), 400, 'unknown_product');
  await expectError(service.placeOrder({ items: [{ id: 'runner-x1', size: '20', qty: 1 }], customer, card }), 400, 'unknown_size');
  await expectError(service.placeOrder({ items: [{ size: '9' }], customer, card }), 400, 'invalid_item');
});

test('keeps orders for lookup and never stores the full card number', async () => {
  const { service } = setup();
  const order = await service.placeOrder({ items: [{ id: 'runner-x1', size: '9', qty: 1 }], customer, card });

  const found = service.getOrder(order.id);
  assert.equal(found.id, order.id);
  assert.equal(found.payment.last4, '4242');
  assert.equal(JSON.stringify(found).includes('4242424242424242'), false);
  assert.equal(JSON.stringify(found).includes('"cvc"'), false);
  assert.equal(service.getOrder('ORD-DOESNOTEXIST'), null);
});
