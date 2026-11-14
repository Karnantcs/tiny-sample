import type { Product } from '../types';
import { toCents } from './money';

/** The list price in cents, before any sale discount. */
export function listPriceCents(product: Product): number {
  return toCents(product.price);
}

/** True when the product has a sale discount above 0%. */
export function isOnSale(product: Product): boolean {
  return (product.salePercent ?? 0) > 0;
}

/**
 * The price a customer pays for one shoe, in cents: the list price less any sale discount.
 * The discount is rounded to the nearest cent, so the price is always a whole number of cents.
 */
export function currentPriceCents(product: Product): number {
  const list = listPriceCents(product);
  const percent = product.salePercent ?? 0;
  if (percent <= 0) return list;
  return Math.round((list * (100 - percent)) / 100);
}
