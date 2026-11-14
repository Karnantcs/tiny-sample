import { toCents } from '../lib/money';
import type { CartItem, Product } from '../types';

export const MIN_QUANTITY = 1;
export const MAX_QUANTITY = 10;

/** Identifies a cart line: the same shoe in the same size and colour is one line. */
export function lineKey(item: Pick<CartItem, 'productId' | 'size' | 'color'>): string {
  return `${item.productId}|${item.size}|${item.color}`;
}

/** Keeps a quantity a whole number between MIN_QUANTITY and MAX_QUANTITY. */
export function clampQuantity(quantity: number): number {
  if (!Number.isFinite(quantity)) return MIN_QUANTITY;
  return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, Math.trunc(quantity)));
}

/** Adds a shoe. If the same size and colour is already in the cart, the quantity goes up. */
export function addToCart(
  items: CartItem[],
  line: Omit<CartItem, 'quantity'>,
  quantity = 1,
): CartItem[] {
  const key = lineKey(line);
  if (items.some((item) => lineKey(item) === key)) {
    return items.map((item) =>
      lineKey(item) === key
        ? { ...item, quantity: clampQuantity(item.quantity + quantity) }
        : item,
    );
  }
  return [...items, { ...line, quantity: clampQuantity(quantity) }];
}

/** Sets the quantity of one line (clamped to the allowed range). */
export function setLineQuantity(items: CartItem[], key: string, quantity: number): CartItem[] {
  return items.map((item) =>
    lineKey(item) === key ? { ...item, quantity: clampQuantity(quantity) } : item,
  );
}

export function removeFromCart(items: CartItem[], key: string): CartItem[] {
  return items.filter((item) => lineKey(item) !== key);
}

/** Total number of shoes in the cart, for the header badge. */
export function cartCount(items: CartItem[]): number {
  return items.reduce((total, item) => total + item.quantity, 0);
}

/**
 * Subtotal in cents, using catalogue prices. Lines whose product no longer
 * exists are left out, rather than failing the whole cart.
 */
export function cartSubtotalCents(items: CartItem[], products: Product[]): number {
  return items.reduce((total, item) => {
    const product = products.find((candidate) => candidate.id === item.productId);
    return product ? total + toCents(product.price) * item.quantity : total;
  }, 0);
}
