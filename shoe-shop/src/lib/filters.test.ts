import { describe, expect, it } from 'vitest';
import { products } from '../data/catalog';
import {
  DEFAULT_FILTERS,
  filterProducts,
  parseShopFilters,
  toShopQuery,
  type ShopFilters,
} from './filters';

function withFilters(overrides: Partial<ShopFilters>): ShopFilters {
  return { ...DEFAULT_FILTERS, ...overrides };
}

describe('shop filters: category', () => {
  it('shows every product when no category is chosen', () => {
    expect(filterProducts(products, DEFAULT_FILTERS)).toHaveLength(12);
  });

  it('shows only the chosen category', () => {
    const result = filterProducts(products, withFilters({ category: 'sandals' }));
    expect(result).toHaveLength(3);
    expect(result.every((product) => product.categoryId === 'sandals')).toBe(true);
  });

  it('returns nothing for an unknown category', () => {
    expect(filterProducts(products, withFilters({ category: 'boots' }))).toEqual([]);
  });
});

describe('shop filters: size', () => {
  it('keeps only products that come in the chosen size', () => {
    const result = filterProducts(products, withFilters({ size: 6 }));
    expect(result.every((product) => product.sizes.includes(6))).toBe(true);
  });

  it('leaves out products that do not come in size 12', () => {
    // Street Slip-On is sold only up to size 11.
    const result = filterProducts(products, withFilters({ size: 12 }));
    expect(result.map((product) => product.id)).not.toContain('cas-street-slip');
    expect(result.length).toBeLessThan(products.length);
  });

  it('combines with the category filter', () => {
    const result = filterProducts(products, withFilters({ category: 'formal', size: 7 }));
    expect(result.length).toBeGreaterThan(0);
    expect(result.every((product) => product.categoryId === 'formal' && product.sizes.includes(7))).toBe(true);
  });
});

describe('shop filters: sorting by price', () => {
  it('sorts low to high', () => {
    const prices = filterProducts(products, withFilters({ sort: 'price-asc' })).map((p) => p.price);
    expect(prices).toEqual([...prices].sort((a, b) => a - b));
  });

  it('sorts high to low', () => {
    const prices = filterProducts(products, withFilters({ sort: 'price-desc' })).map((p) => p.price);
    expect(prices).toEqual([...prices].sort((a, b) => b - a));
  });

  it('keeps catalogue order for "featured"', () => {
    expect(filterProducts(products, DEFAULT_FILTERS)).toEqual(products);
  });

  it('does not change the array it was given', () => {
    const copy = [...products];
    filterProducts(products, withFilters({ sort: 'price-desc' }));
    expect(products).toEqual(copy);
  });
});

describe('shop filters: reading and writing the URL', () => {
  it('reads all three filters from a query', () => {
    const filters = parseShopFilters(new URLSearchParams('category=running&size=9&sort=price-desc'));
    expect(filters).toEqual({ category: 'running', size: 9, sort: 'price-desc' });
  });

  it('falls back to no filter for invalid values', () => {
    const filters = parseShopFilters(new URLSearchParams('size=13&sort=cheapest'));
    expect(filters).toEqual(DEFAULT_FILTERS);
  });

  it('ignores a size that is not a whole number', () => {
    expect(parseShopFilters(new URLSearchParams('size=8.5')).size).toBeNull();
    expect(parseShopFilters(new URLSearchParams('size=abc')).size).toBeNull();
  });

  it('writes only the filters that are not the defaults', () => {
    expect(toShopQuery(DEFAULT_FILTERS).toString()).toBe('');
    expect(toShopQuery(withFilters({ category: 'casual', sort: 'price-asc' })).toString()).toBe(
      'category=casual&sort=price-asc',
    );
  });

  it('round-trips through the URL', () => {
    const filters = withFilters({ category: 'sandals', size: 10, sort: 'price-asc' });
    expect(parseShopFilters(toShopQuery(filters))).toEqual(filters);
  });
});
