import { formatUsd } from '../lib/money';
import { FREE_SHIPPING_OVER_CENTS } from '../lib/totals';
import type { OrderTotals } from '../types';

interface OrderSummaryProps {
  totals: OrderTotals;
  itemCount: number;
}

/** The subtotal, shipping, tax and total lines. Used on the cart, checkout and confirmation pages. */
export function OrderSummary({ totals, itemCount }: OrderSummaryProps) {
  const freeShipping = totals.shippingCents === 0 && totals.subtotalCents > 0;
  return (
    <dl className="totals">
      <div className="totals__row">
        <dt>Subtotal ({itemCount} {itemCount === 1 ? 'item' : 'items'})</dt>
        <dd>{formatUsd(totals.subtotalCents)}</dd>
      </div>
      <div className="totals__row">
        <dt>Shipping</dt>
        <dd>{freeShipping ? 'Free' : formatUsd(totals.shippingCents)}</dd>
      </div>
      <div className="totals__row">
        <dt>Tax (8%)</dt>
        <dd>{formatUsd(totals.taxCents)}</dd>
      </div>
      <div className="totals__row totals__row--total">
        <dt>Total</dt>
        <dd>{formatUsd(totals.totalCents)}</dd>
      </div>
      {totals.subtotalCents > 0 && totals.subtotalCents <= FREE_SHIPPING_OVER_CENTS && (
        <p className="totals__hint">
          Add {formatUsd(FREE_SHIPPING_OVER_CENTS + 1 - totals.subtotalCents)} more for free shipping.
        </p>
      )}
    </dl>
  );
}
