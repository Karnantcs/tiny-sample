import { describe, expect, it } from 'vitest';
import { products } from '../data/catalog';
import { buildOrder, generateOrderNumber } from './order';
import { parseHash, buildHash } from './router';
import { validateShipping } from './validation';
import type { ShippingDetails } from '../types';

const shippingOk: ShippingDetails = {
  fullName: 'Alex Shopper',
  email: 'alex@example.com',
  address: '12 Market Street',
  city: 'Springfield',
  state: 'IL',
  postalCode: '62704',
};

describe('shipping form validation', () => {
  it('accepts a complete address', () => {
    expect(validateShipping(shippingOk)).toEqual({});
  });

  it('reports every missing field', () => {
    const errors = validateShipping({
      fullName: '',
      email: '',
      address: '',
      city: '',
      state: '',
      postalCode: '',
    });
    expect(Object.keys(errors).sort()).toEqual(
      ['address', 'city', 'email', 'fullName', 'postalCode', 'state'].sort(),
    );
  });

  it('rejects a malformed email', () => {
    expect(validateShipping({ ...shippingOk, email: 'alex@example' }).email).toBeDefined();
    expect(validateShipping({ ...shippingOk, email: 'no-at-sign.com' }).email).toBeDefined();
  });

  it('rejects a postal code with symbols', () => {
    expect(validateShipping({ ...shippingOk, postalCode: '62!04' }).postalCode).toBeDefined();
  });

  it('trims spaces before checking', () => {
    expect(validateShipping({ ...shippingOk, fullName: '   ' }).fullName).toBeDefined();
  });
});

describe('order building', () => {
  it('prices each line at purchase and computes totals', () => {
    const order = buildOrder({
      orderNumber: 'STR-TEST-0001',
      items: [
        { productId: 'run-sprint-lite', size: 9, color: 'White', quantity: 2 },
        { productId: 'san-sunset-slide', size: 7, color: 'Coral', quantity: 1 },
      ],
      products,
      shipping: shippingOk,
      transactionId: 'mock_tx',
      cardLast4: '4242',
      createdAt: new Date('2025-06-15T12:00:00Z'),
    });
    // 2 x $89.00 + $32.00 = $210.00, free shipping, 8% tax = $16.80.
    expect(order.totals).toEqual({
      subtotalCents: 21000,
      shippingCents: 0,
      taxCents: 1680,
      totalCents: 22680,
    });
    expect(order.lines[0]).toMatchObject({ name: 'Sprint Lite', unitPriceCents: 8900, quantity: 2 });
    expect(order.createdAt).toBe('2025-06-15T12:00:00.000Z');
  });

  it('skips cart lines for products that no longer exist', () => {
    const order = buildOrder({
      orderNumber: 'STR-TEST-0002',
      items: [{ productId: 'gone', size: 8, color: 'Red', quantity: 1 }],
      products,
      shipping: shippingOk,
      transactionId: 'mock_tx',
      cardLast4: '4242',
    });
    expect(order.lines).toEqual([]);
    expect(order.totals.subtotalCents).toBe(0);
  });
});

describe('order numbers', () => {
  it('look like STR-<time>-<random>', () => {
    expect(generateOrderNumber(1_700_000_000_000, () => 0.5)).toMatch(/^STR-[0-9A-Z]{6}-[0-9A-Z]{4}$/);
  });

  it('are different for different random values', () => {
    const a = generateOrderNumber(1_700_000_000_000, () => 0.1);
    const b = generateOrderNumber(1_700_000_000_000, () => 0.9);
    expect(a).not.toBe(b);
  });
});

describe('hash routes', () => {
  it('reads the path and query', () => {
    const route = parseHash('#/shop?category=running&size=9');
    expect(route.segments).toEqual(['shop']);
    expect(route.query.get('category')).toBe('running');
    expect(route.query.get('size')).toBe('9');
  });

  it('reads a product id', () => {
    expect(parseHash('#/product/run-sprint-lite').segments).toEqual(['product', 'run-sprint-lite']);
  });

  it('treats an empty hash as the home page', () => {
    expect(parseHash('').segments).toEqual([]);
    expect(parseHash('#/').segments).toEqual([]);
  });

  it('keeps a broken percent-encoding as it is', () => {
    expect(parseHash('#/product/%E0%A4%A').segments).toEqual(['product', '%E0%A4%A']);
  });

  it('builds a hash from segments and a query', () => {
    expect(buildHash(['shop'])).toBe('#/shop');
    expect(buildHash(['product', 'run sprint'])).toBe('#/product/run%20sprint');
    expect(buildHash([], new URLSearchParams())).toBe('#/');
  });
});
