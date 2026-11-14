import type { OrderTotals } from '../types';

/** Orders over $100 ship free. Anything at or under $100 pays the flat rate. */
export const FREE_SHIPPING_OVER_CENTS = 10_000;
export const FLAT_SHIPPING_CENTS = 800;
/** Sales tax of 8%, charged on the subtotal (not on shipping). */
export const TAX_RATE = 0.08;

/**
 * Works out shipping, tax and total from a subtotal in cents.
 * An empty basket has no shipping charge.
 */
export function computeTotals(subtotalCents: number): OrderTotals {
  if (!Number.isInteger(subtotalCents) || subtotalCents < 0) {
    throw new RangeError(`Subtotal must be a whole, non-negative number of cents: ${subtotalCents}`);
  }
  const shippingCents =
    subtotalCents === 0 || subtotalCents > FREE_SHIPPING_OVER_CENTS ? 0 : FLAT_SHIPPING_CENTS;
  const taxCents = Math.round(subtotalCents * TAX_RATE);
  return {
    subtotalCents,
    shippingCents,
    taxCents,
    totalCents: subtotalCents + shippingCents + taxCents,
  };
}
