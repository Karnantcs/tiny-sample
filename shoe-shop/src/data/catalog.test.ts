import { describe, expect, it } from 'vitest';
import { ALL_SIZES, categories, getCategory, getProduct, products, productsInCategory } from './catalog';

describe('catalogue data', () => {
  it('has four categories', () => {
    expect(categories.map((category) => category.name)).toEqual([
      'Running',
      'Casual Sneakers',
      'Formal Leather',
      'Sandals',
    ]);
  });

  it('has three products in each category', () => {
    for (const category of categories) {
      expect(productsInCategory(category.id)).toHaveLength(3);
    }
  });

  it('gives every product a unique id and a known category', () => {
    const ids = products.map((product) => product.id);
    expect(new Set(ids).size).toBe(ids.length);
    for (const product of products) {
      expect(getCategory(product.categoryId)).toBeDefined();
    }
  });

  it('keeps every product within sizes 6 to 12, with a positive price, colours and a description', () => {
    for (const product of products) {
      expect(product.price).toBeGreaterThan(0);
      expect(product.name.length).toBeGreaterThan(0);
      expect(product.description.length).toBeGreaterThan(0);
      expect(product.colors.length).toBeGreaterThan(0);
      for (const size of product.sizes) {
        expect(ALL_SIZES).toContain(size);
      }
    }
  });

  it('looks products up by id', () => {
    expect(getProduct('run-sprint-lite')?.name).toBe('Sprint Lite');
    expect(getProduct('missing')).toBeUndefined();
  });

  it('has at least one featured product for the home page', () => {
    expect(products.some((product) => product.featured)).toBe(true);
  });
});
