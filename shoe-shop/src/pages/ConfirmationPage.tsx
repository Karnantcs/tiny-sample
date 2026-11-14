import { OrderSummary } from '../components/OrderSummary';
import { useCart } from '../cart/CartContext';
import { formatUsd } from '../lib/money';
import { buildHash } from '../lib/router';

interface ConfirmationPageProps {
  orderNumber: string | undefined;
}

export function ConfirmationPage({ orderNumber }: ConfirmationPageProps) {
  const { lastOrder } = useCart();

  // Orders are kept in memory only, so a page refresh loses them.
  if (!lastOrder || lastOrder.orderNumber !== orderNumber) {
    return (
      <div className="container section">
        <div className="empty-state">
          <h1>We couldn't find that order</h1>
          <p>
            Orders are kept only for this browser session. If you refreshed the page, the order details are
            no longer shown here.
          </p>
          <a className="button button--primary" href={buildHash(['shop'])}>
            Continue shopping
          </a>
        </div>
      </div>
    );
  }

  const order = lastOrder;
  const itemCount = order.lines.reduce((sum, line) => sum + line.quantity, 0);

  return (
    <div className="container section">
      <div className="confirmation">
        <p className="eyebrow">Thank you, {order.shipping.fullName.split(' ')[0]}</p>
        <h1 className="page-title">Your order is confirmed</h1>
        <p className="lead">
          Order number <strong data-testid="order-number">{order.orderNumber}</strong>. We've sent the receipt to{' '}
          {order.shipping.email}.
        </p>
      </div>

      <div className="checkout-layout">
        <section className="card-panel" aria-labelledby="order-items">
          <h2 className="panel-title" id="order-items">
            Items
          </h2>
          <ul className="summary-lines">
            {order.lines.map((line) => (
              <li key={`${line.productId}|${line.size}|${line.color}`}>
                <span>
                  {line.name} · {line.color} · Size {line.size} × {line.quantity}
                </span>
                <span>{formatUsd(line.unitPriceCents * line.quantity)}</span>
              </li>
            ))}
          </ul>
          <OrderSummary totals={order.totals} itemCount={itemCount} />
        </section>

        <section className="card-panel" aria-labelledby="order-delivery">
          <h2 className="panel-title" id="order-delivery">
            Delivery and payment
          </h2>
          <p>
            {order.shipping.fullName}
            <br />
            {order.shipping.address}
            <br />
            {order.shipping.city}, {order.shipping.state} {order.shipping.postalCode}
          </p>
          <p className="muted">
            Paid with a card ending in {order.cardLast4} (reference {order.transactionId}).
          </p>
          <a className="button button--primary button--block" href={buildHash(['shop'])}>
            Keep shopping
          </a>
        </section>
      </div>
    </div>
  );
}
