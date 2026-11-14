import catalogData from './products.json';
import type { Catalog, Category, Product } from '../types';

/** The whole catalogue lives in products.json. Add products there, not here. */
const catalog = catalogData as Catalog;

export const categories: Category[] = catalog.categories;
export const products: Product[] = catalog.products;

/** Every size the store sells (US men's sizes 6 to 12). */
export const ALL_SIZES: number[] = [6, 7, 8, 9, 10, 11, 12];

export function getProduct(id: string): Product | undefined {
  return products.find((product) => product.id === id);
}

export function getCategory(id: string): Category | undefined {
  return categories.find((category) => category.id === id);
}

export function productsInCategory(categoryId: string): Product[] {
  return products.filter((product) => product.categoryId === categoryId);
}
