import { formatUsd } from '../lib/money';
import { currentPriceCents, isOnSale, listPriceCents } from '../lib/pricing';
import type { Product } from '../types';

/**
 * Shows what a shopper pays for one shoe. A product on sale also shows its
 * old price, struck through, so the saving is visible.
 */
export function Price({ product, className }: { product: Product; className?: string }) {
  if (!isOnSale(product)) {
    return <span className={className}>{formatUsd(listPriceCents(product))}</span>;
  }
  return (
    <span className={className}>
      <s className="price-was">
        <span className="visually-hidden">Was </span>
        {formatUsd(listPriceCents(product))}
      </s>{' '}
      <span className="price-sale">
        <span className="visually-hidden">Now </span>
        {formatUsd(currentPriceCents(product))}
      </span>
    </span>
  );
}

/** A small "Sale" label for products with a discount. Renders nothing when the product is not on sale. */
export function SaleBadge({ product }: { product: Product }) {
  if (!isOnSale(product)) return null;
  return <span className="sale-badge">Sale</span>;
}
