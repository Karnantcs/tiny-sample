import { describe, expect, it } from 'vitest';
import { products } from '../data/catalog';
import { currentPriceCents, isOnSale, listPriceCents } from './pricing';
import type { Product } from '../types';

const base: Product = {
  id: 'test-shoe',
  name: 'Test Shoe',
  categoryId: 'kids',
  price: 49.0,
  sizes: [6],
  colors: [{ name: 'Blue', hex: '#2563eb' }],
  description: 'A test shoe.',
  featured: false,
};

describe('pricing: sale prices', () => {
  it('charges the list price when there is no sale', () => {
    expect(isOnSale(base)).toBe(false);
    expect(currentPriceCents(base)).toBe(4900);
  });

  it('takes the sale percentage off the list price', () => {
    const onSale = { ...base, salePercent: 20 };
    expect(isOnSale(onSale)).toBe(true);
    expect(listPriceCents(onSale)).toBe(4900);
    expect(currentPriceCents(onSale)).toBe(3920);
  });

  it('rounds the discounted price to the nearest cent', () => {
    // $10.01 is 1001 cents; 15% off leaves 850.85 cents, which rounds to 851.
    expect(currentPriceCents({ ...base, price: 10.01, salePercent: 15 })).toBe(851);
  });

  it('treats a 0% sale as no sale', () => {
    expect(isOnSale({ ...base, salePercent: 0 })).toBe(false);
    expect(currentPriceCents({ ...base, salePercent: 0 })).toBe(4900);
  });

  it('prices the two sale products in the catalogue at 20% off', () => {
    expect(currentPriceCents(products.find((p) => p.id === 'kid-bounce-runner')!)).toBe(3920);
    expect(currentPriceCents(products.find((p) => p.id === 'kid-hook-loop')!)).toBe(3120);
  });

  it('leaves the full price on products that are not on sale', () => {
    const splash = products.find((p) => p.id === 'kid-splash-boot')!;
    expect(isOnSale(splash)).toBe(false);
    expect(currentPriceCents(splash)).toBe(4200);
  });
});
