import { currentPriceCents } from './pricing';
import { computeTotals } from './totals';
import type { CartItem, Order, Product, ShippingDetails } from '../types';

/** Order numbers look like STR-LZ3K9Q-4F2A: a time part and a random part. They are not sequential. */
export function generateOrderNumber(now: number = Date.now(), random: () => number = Math.random): string {
  const timePart = now.toString(36).toUpperCase().slice(-6).padStart(6, '0');
  const randomPart = Math.floor(random() * 36 ** 4)
    .toString(36)
    .toUpperCase()
    .padStart(4, '0');
  return `STR-${timePart}-${randomPart}`;
}

export interface BuildOrderInput {
  orderNumber: string;
  items: CartItem[];
  products: Product[];
  shipping: ShippingDetails;
  transactionId: string;
  cardLast4: string;
  createdAt?: Date;
}

/**
 * Turns a paid cart into an order with prices fixed at the time of purchase.
 * Cart lines whose product no longer exists are skipped.
 */
export function buildOrder(input: BuildOrderInput): Order {
  const lines = input.items.flatMap((item) => {
    const product = input.products.find((candidate) => candidate.id === item.productId);
    if (!product) return [];
    return [
      {
        productId: product.id,
        name: product.name,
        size: item.size,
        color: item.color,
        quantity: item.quantity,
        unitPriceCents: currentPriceCents(product),
      },
    ];
  });
  const subtotalCents = lines.reduce((sum, line) => sum + line.unitPriceCents * line.quantity, 0);
  return {
    orderNumber: input.orderNumber,
    createdAt: (input.createdAt ?? new Date()).toISOString(),
    lines,
    totals: computeTotals(subtotalCents),
    shipping: input.shipping,
    transactionId: input.transactionId,
    cardLast4: input.cardLast4,
  };
}
