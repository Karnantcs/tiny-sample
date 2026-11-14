import { OrderSummary } from '../components/OrderSummary';
import { Price } from '../components/Price';
import { ShoeArt } from '../components/ShoeArt';
import { cartSubtotalCents, lineKey, MAX_QUANTITY, MIN_QUANTITY } from '../cart/cart';
import { useCart } from '../cart/CartContext';
import { getCategory, products } from '../data/catalog';
import { formatUsd } from '../lib/money';
import { buildHash } from '../lib/router';
import { currentPriceCents } from '../lib/pricing';
import { computeTotals } from '../lib/totals';

export function CartPage() {
  const { items, count, setQuantity, remove } = useCart();

  // Lines for products that no longer exist are not shown.
  const lines = items.flatMap((item) => {
    const product = products.find((candidate) => candidate.id === item.productId);
    return product ? [{ item, product, key: lineKey(item) }] : [];
  });

  if (lines.length === 0) {
    return (
      <div className="container section">
        <h1 className="page-title">Your cart</h1>
        <div className="empty-state">
          <p>Your cart is empty.</p>
          <a className="button button--primary" href={buildHash(['shop'])}>
            Start shopping
          </a>
        </div>
      </div>
    );
  }

  const totals = computeTotals(cartSubtotalCents(items, products));

  return (
    <div className="container section">
      <h1 className="page-title">Your cart</h1>
      <div className="cart-layout">
        <ul className="cart-list" aria-label="Items in your cart">
          {lines.map(({ item, product, key }) => {
            const category = getCategory(product.categoryId);
            const colorHex = product.colors.find((option) => option.name === item.color)?.hex ?? '#1f1f24';
            return (
              <li key={key} className="cart-line">
                <div className="cart-line__image">
                  <ShoeArt categoryId={product.categoryId} color={colorHex} label="" />
                </div>
                <div className="cart-line__info">
                  <a className="cart-line__name" href={buildHash(['product', product.id])}>
                    {product.name}
                  </a>
                  <p className="muted">
                    {category?.name} · {item.color} · Size {item.size}
                  </p>
                  <p className="cart-line__price">
                    <Price product={product} /> each
                  </p>
                </div>
                <div className="cart-line__controls">
                  <div className="quantity" role="group" aria-label={`Quantity for ${product.name}`}>
                    <button
                      type="button"
                      aria-label="Decrease quantity"
                      disabled={item.quantity <= MIN_QUANTITY}
                      onClick={() => setQuantity(key, item.quantity - 1)}
                    >
                      −
                    </button>
                    <span aria-live="polite">{item.quantity}</span>
                    <button
                      type="button"
                      aria-label="Increase quantity"
                      disabled={item.quantity >= MAX_QUANTITY}
                      onClick={() => setQuantity(key, item.quantity + 1)}
                    >
                      +
                    </button>
                  </div>
                  <p className="cart-line__total">{formatUsd(currentPriceCents(product) * item.quantity)}</p>
                  <button type="button" className="text-link text-link--danger" onClick={() => remove(key)}>
                    Remove
                  </button>
                </div>
              </li>
            );
          })}
        </ul>

        <aside className="card-panel" aria-label="Order summary">
          <h2 className="panel-title">Order summary</h2>
          <OrderSummary totals={totals} itemCount={count} />
          <a className="button button--primary button--block" href={buildHash(['checkout'])}>
            Checkout
          </a>
          <a className="text-link text-link--center" href={buildHash(['shop'])}>
            Continue shopping
          </a>
        </aside>
      </div>
    </div>
  );
}
