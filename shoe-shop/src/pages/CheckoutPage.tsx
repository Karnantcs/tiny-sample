import { useState, type FormEvent } from 'react';
import { OrderSummary } from '../components/OrderSummary';
import { cartSubtotalCents } from '../cart/cart';
import { useCart } from '../cart/CartContext';
import { products } from '../data/catalog';
import { paymentProvider } from '../payments';
import type { CardDetails } from '../payments';
import { buildOrder, generateOrderNumber } from '../lib/order';
import { formatUsd } from '../lib/money';
import { buildHash } from '../lib/router';
import { navigate } from '../lib/useRoute';
import { validateShipping, type ShippingErrors } from '../lib/validation';
import { computeTotals } from '../lib/totals';
import type { ShippingDetails } from '../types';

type Step = 'shipping' | 'payment';

const EMPTY_SHIPPING: ShippingDetails = {
  fullName: '',
  email: '',
  address: '',
  city: '',
  state: '',
  postalCode: '',
};

const EMPTY_CARD: CardDetails = { number: '', expiry: '', cvc: '', nameOnCard: '' };

/** Groups card digits in fours, e.g. "4242424242424242" -> "4242 4242 4242 4242". */
export function formatCardNumber(value: string): string {
  return value
    .replace(/\D/g, '')
    .slice(0, 19)
    .replace(/(\d{4})(?=\d)/g, '$1 ');
}

/** Adds the slash to an expiry date as the user types, e.g. "1229" -> "12/29". */
export function formatExpiry(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 4);
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
}

export function CheckoutPage() {
  const { items, clear, placeOrder } = useCart();
  const [step, setStep] = useState<Step>('shipping');
  const [shipping, setShipping] = useState<ShippingDetails>(EMPTY_SHIPPING);
  const [shippingErrors, setShippingErrors] = useState<ShippingErrors>({});
  const [card, setCard] = useState<CardDetails>(EMPTY_CARD);
  const [cardError, setCardError] = useState<string | null>(null);
  const [paying, setPaying] = useState(false);

  const subtotalCents = cartSubtotalCents(items, products);
  const totals = computeTotals(subtotalCents);
  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

  if (items.length === 0 && !paying) {
    return (
      <div className="container section">
        <h1 className="page-title">Checkout</h1>
        <div className="empty-state">
          <p>Your cart is empty, so there is nothing to check out.</p>
          <a className="button button--primary" href={buildHash(['shop'])}>
            Go to the shop
          </a>
        </div>
      </div>
    );
  }

  function updateShipping(field: keyof ShippingDetails, value: string) {
    setShipping((current) => ({ ...current, [field]: value }));
    setShippingErrors((current) => ({ ...current, [field]: undefined }));
  }

  function handleShippingSubmit(event: FormEvent) {
    event.preventDefault();
    const errors = validateShipping(shipping);
    setShippingErrors(errors);
    if (Object.keys(errors).length === 0) {
      setStep('payment');
    }
  }

  async function handlePayment(event: FormEvent) {
    event.preventDefault();
    setCardError(null);
    setPaying(true);
    const orderNumber = generateOrderNumber();
    const result = await paymentProvider.pay({
      orderNumber,
      amountCents: totals.totalCents,
      currency: 'USD',
      card,
    });
    if (!result.ok) {
      setPaying(false);
      setCardError(result.error);
      return;
    }
    const order = buildOrder({
      orderNumber,
      items,
      products,
      shipping: {
        ...shipping,
        fullName: shipping.fullName.trim(),
        email: shipping.email.trim(),
        address: shipping.address.trim(),
        city: shipping.city.trim(),
        state: shipping.state.trim(),
        postalCode: shipping.postalCode.trim(),
      },
      transactionId: result.transactionId,
      cardLast4: result.cardLast4,
    });
    // Card details are dropped here. Only the last four digits stay on the order.
    setCard(EMPTY_CARD);
    placeOrder(order);
    clear();
    navigate(buildHash(['order', orderNumber]));
  }

  return (
    <div className="container section">
      <h1 className="page-title">Checkout</h1>
      <div className="checkout-layout">
        <div>
          <ol className="steps" aria-label="Checkout steps">
            <li className={step === 'shipping' ? 'is-current' : 'is-done'}>1. Shipping</li>
            <li className={step === 'payment' ? 'is-current' : ''}>2. Payment</li>
          </ol>

          {step === 'shipping' && (
            <form className="form card-panel" onSubmit={handleShippingSubmit} noValidate>
              <h2 className="panel-title">Shipping details</h2>
              <TextField label="Full name" name="fullName" autoComplete="name" value={shipping.fullName} error={shippingErrors.fullName} onChange={(v) => updateShipping('fullName', v)} />
              <TextField label="Email" name="email" type="email" autoComplete="email" value={shipping.email} error={shippingErrors.email} onChange={(v) => updateShipping('email', v)} />
              <TextField label="Street address" name="address" autoComplete="street-address" value={shipping.address} error={shippingErrors.address} onChange={(v) => updateShipping('address', v)} />
              <div className="form__row">
                <TextField label="City" name="city" autoComplete="address-level2" value={shipping.city} error={shippingErrors.city} onChange={(v) => updateShipping('city', v)} />
                <TextField label="State" name="state" autoComplete="address-level1" value={shipping.state} error={shippingErrors.state} onChange={(v) => updateShipping('state', v)} />
                <TextField label="ZIP / postal code" name="postalCode" autoComplete="postal-code" value={shipping.postalCode} error={shippingErrors.postalCode} onChange={(v) => updateShipping('postalCode', v)} />
              </div>
              <button type="submit" className="button button--primary">
                Continue to payment
              </button>
            </form>
          )}

          {step === 'payment' && (
            <form className="form card-panel" onSubmit={handlePayment} noValidate>
              <h2 className="panel-title">Payment</h2>
              {paymentProvider.isTestMode && (
                <p className="notice notice--info">
                  Test mode: no real money moves. Use <strong>4242 4242 4242 4242</strong> to succeed, or{' '}
                  <strong>4000 0000 0000 0002</strong> to be declined. Any future expiry date and any 3-digit code work.
                </p>
              )}
              <TextField
                label="Card number"
                name="cardNumber"
                inputMode="numeric"
                autoComplete="cc-number"
                placeholder="1234 1234 1234 1234"
                value={card.number}
                onChange={(v) => setCard((current) => ({ ...current, number: formatCardNumber(v) }))}
              />
              <div className="form__row">
                <TextField
                  label="Expiry (MM/YY)"
                  name="expiry"
                  inputMode="numeric"
                  autoComplete="cc-exp"
                  placeholder="MM/YY"
                  value={card.expiry}
                  onChange={(v) => setCard((current) => ({ ...current, expiry: formatExpiry(v) }))}
                />
                <TextField
                  label="CVC"
                  name="cvc"
                  inputMode="numeric"
                  autoComplete="cc-csc"
                  placeholder="123"
                  value={card.cvc}
                  onChange={(v) => setCard((current) => ({ ...current, cvc: v.replace(/\D/g, '').slice(0, 4) }))}
                />
              </div>
              <TextField
                label="Name on card"
                name="nameOnCard"
                autoComplete="cc-name"
                value={card.nameOnCard}
                onChange={(v) => setCard((current) => ({ ...current, nameOnCard: v }))}
              />
              {cardError && (
                <p className="notice notice--error" role="alert">
                  {cardError}
                </p>
              )}
              <div className="form__actions">
                <button type="button" className="button button--ghost" disabled={paying} onClick={() => setStep('shipping')}>
                  Back
                </button>
                <button type="submit" className="button button--primary" disabled={paying}>
                  {paying ? 'Processing payment…' : `Pay ${formatUsd(totals.totalCents)}`}
                </button>
              </div>
              <p className="muted small">
                Card details are checked in your browser and are not stored by Stride.
              </p>
            </form>
          )}
        </div>

        <aside className="card-panel" aria-label="Order summary">
          <h2 className="panel-title">Order summary</h2>
          <ul className="summary-lines">
            {items.map((item) => {
              const product = products.find((candidate) => candidate.id === item.productId);
              if (!product) return null;
              return (
                <li key={`${item.productId}|${item.size}|${item.color}`}>
                  <span>
                    {product.name} · {item.color} · {item.size} × {item.quantity}
                  </span>
                </li>
              );
            })}
          </ul>
          <OrderSummary totals={totals} itemCount={itemCount} />
        </aside>
      </div>
    </div>
  );
}

interface TextFieldProps {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  type?: string;
  autoComplete?: string;
  inputMode?: 'numeric' | 'text' | 'email';
  placeholder?: string;
}

function TextField({ label, name, value, onChange, error, type = 'text', autoComplete, inputMode, placeholder }: TextFieldProps) {
  const id = `field-${name}`;
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        name={name}
        type={type}
        value={value}
        autoComplete={autoComplete}
        inputMode={inputMode}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && (
        <p className="field-error" id={`${id}-error`}>
          {error}
        </p>
      )}
    </div>
  );
}
