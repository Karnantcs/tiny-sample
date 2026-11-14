import { describe, expect, it } from 'vitest';
import { products } from '../data/catalog';
import type { CartItem } from '../types';
import {
  addToCart,
  cartCount,
  cartSubtotalCents,
  clampQuantity,
  lineKey,
  MAX_QUANTITY,
  removeFromCart,
  setLineQuantity,
} from './cart';

const sprint = { productId: 'run-sprint-lite', size: 9, color: 'White' };
const oxford = { productId: 'for-oxford-classic', size: 10, color: 'Black' };

describe('cart: add', () => {
  it('adds a new line with quantity 1 by default', () => {
    const items = addToCart([], sprint);
    expect(items).toEqual([{ ...sprint, quantity: 1 }]);
  });

  it('adds the same shoe in the same size and colour as one line', () => {
    let items = addToCart([], sprint);
    items = addToCart(items, sprint, 2);
    expect(items).toHaveLength(1);
    expect(items[0].quantity).toBe(3);
  });

  it('keeps a different size or colour as a separate line', () => {
    let items = addToCart([], sprint);
    items = addToCart(items, { ...sprint, size: 10 });
    items = addToCart(items, { ...sprint, color: 'Coral' });
    expect(items).toHaveLength(3);
    expect(new Set(items.map(lineKey)).size).toBe(3);
  });

  it('does not change the array it was given', () => {
    const before: CartItem[] = [];
    addToCart(before, sprint);
    expect(before).toEqual([]);
  });

  it('caps the quantity of one line at the maximum', () => {
    const items = addToCart([], sprint, 99);
    expect(items[0].quantity).toBe(MAX_QUANTITY);
    expect(addToCart(items, sprint, 5)[0].quantity).toBe(MAX_QUANTITY);
  });
});

describe('cart: change quantity', () => {
  it('sets the quantity of one line', () => {
    let items = addToCart([], sprint);
    items = addToCart(items, oxford);
    items = setLineQuantity(items, lineKey(sprint), 4);
    expect(items.find((item) => item.productId === sprint.productId)?.quantity).toBe(4);
    expect(items.find((item) => item.productId === oxford.productId)?.quantity).toBe(1);
  });

  it('will not go below 1 or above the maximum', () => {
    let items = addToCart([], sprint);
    items = setLineQuantity(items, lineKey(sprint), 0);
    expect(items[0].quantity).toBe(1);
    items = setLineQuantity(items, lineKey(sprint), 50);
    expect(items[0].quantity).toBe(MAX_QUANTITY);
  });

  it('ignores a line key that is not in the cart', () => {
    const items = addToCart([], sprint, 2);
    expect(setLineQuantity(items, 'missing|1|x', 5)).toEqual(items);
  });

  it('clampQuantity truncates decimals and handles NaN', () => {
    expect(clampQuantity(2.9)).toBe(2);
    expect(clampQuantity(Number.NaN)).toBe(1);
    expect(clampQuantity(-3)).toBe(1);
  });
});

describe('cart: remove', () => {
  it('removes only the chosen line', () => {
    let items = addToCart([], sprint);
    items = addToCart(items, oxford);
    items = removeFromCart(items, lineKey(sprint));
    expect(items).toEqual([{ ...oxford, quantity: 1 }]);
  });

  it('leaves the cart unchanged when the key is unknown', () => {
    const items = addToCart([], sprint);
    expect(removeFromCart(items, 'nope|1|x')).toEqual(items);
  });
});

describe('cart: count and subtotal', () => {
  it('counts every shoe, across all lines', () => {
    let items = addToCart([], sprint, 2);
    items = addToCart(items, oxford, 3);
    expect(cartCount(items)).toBe(5);
    expect(cartCount([])).toBe(0);
  });

  it('adds up catalogue prices in cents', () => {
    // Sprint Lite is $89.00 and Oxford Classic is $189.00.
    const items = [
      { ...sprint, quantity: 2 },
      { ...oxford, quantity: 1 },
    ];
    expect(cartSubtotalCents(items, products)).toBe(89_00 * 2 + 189_00);
  });

  it('skips lines whose product is no longer in the catalogue', () => {
    const items = [
      { ...sprint, quantity: 1 },
      { productId: 'discontinued', size: 8, color: 'Red', quantity: 4 },
    ];
    expect(cartSubtotalCents(items, products)).toBe(89_00);
  });
});
