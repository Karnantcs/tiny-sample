'use strict';

const { randomBytes } = require('node:crypto');
const { validateCard } = require('./payments/mockGateway');

const CURRENCY = 'USD';
const FREE_SHIPPING_CENTS = 10000; // orders of $100.00 or more ship free
const SHIPPING_CENTS = 499;
const MAX_LINES = 20;
const MAX_QTY = 10;

/** An error the API turns into an HTTP response. `fields` maps form fields to messages. */
class ShopError extends Error {
  constructor(status, code, message, fields) {
    super(message);
    this.status = status;
    this.code = code;
    if (fields) this.fields = fields;
  }
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validateCustomer(customer) {
  const fields = {};
  const c = customer && typeof customer === 'object' ? customer : {};
  const name = String(c.name || '').trim();
  const email = String(c.email || '').trim();
  const address = String(c.address || '').trim();

  if (name.length < 2 || name.length > 100) fields.name = 'Enter your full name.';
  if (!EMAIL.test(email) || email.length > 254) fields.email = 'Enter a valid email address.';
  if (address.length < 5 || address.length > 300) fields.address = 'Enter your shipping address.';

  return { fields, value: { name, email, address } };
}

/**
 * Checks the cart lines and returns them merged by product and size.
 * Each line becomes { productId, size, qty }.
 */
function normaliseItems(items) {
  if (!Array.isArray(items) || items.length === 0) {
    throw new ShopError(400, 'empty_cart', 'Your cart is empty.');
  }
  if (items.length > MAX_LINES) {
    throw new ShopError(400, 'too_many_lines', `A cart can hold at most ${MAX_LINES} lines.`);
  }

  const merged = new Map();
  for (const raw of items) {
    const productId = String((raw && raw.id) || '');
    const size = String((raw && raw.size) || '');
    const qty = raw && raw.qty === undefined ? 1 : Number(raw && raw.qty);
    if (!productId || !size) {
      throw new ShopError(400, 'invalid_item', 'Each cart line needs a product and a size.');
    }
    if (!Number.isInteger(qty) || qty < 1 || qty > MAX_QTY) {
      throw new ShopError(400, 'invalid_quantity', `Quantity must be between 1 and ${MAX_QTY}.`);
    }
    const key = `${productId}|${size}`;
    merged.set(key, { productId, size, qty: (merged.get(key)?.qty || 0) + qty });
  }
  return [...merged.values()];
}

/**
 * Creates the order service.
 *
 * @param {{ catalog: object, gateway: { charge: Function }, now?: () => Date }} deps
 */
function createOrderService({ catalog, gateway, now = () => new Date() }) {
  const orders = new Map();

  /** Takes stock for the lines. Returns the priced lines, or throws if any line can't be filled. */
  function reserve(lines) {
    const priced = [];
    for (const line of lines) {
      const product = catalog.find(line.productId);
      if (!product) {
        throw new ShopError(400, 'unknown_product', `We don't sell product "${line.productId}".`);
      }
      const entry = product.sizes.find((s) => s.size === line.size);
      if (!entry) {
        throw new ShopError(400, 'unknown_size', `${product.name} is not sold in size ${line.size}.`);
      }
      if (entry.stock < line.qty) {
        throw new ShopError(
          409,
          'out_of_stock',
          entry.stock === 0
            ? `${product.name} in size ${line.size} is sold out.`
            : `Only ${entry.stock} of ${product.name} in size ${line.size} left.`
        );
      }
      priced.push({ product, entry, line });
    }

    // All lines are fine, so take the stock in one synchronous step.
    for (const { entry, line } of priced) entry.stock -= line.qty;

    return priced.map(({ product, line }) => ({
      productId: product.id,
      name: product.name,
      size: line.size,
      qty: line.qty,
      unitPriceCents: product.priceCents,
      lineTotalCents: product.priceCents * line.qty,
    }));
  }

  function release(lines) {
    for (const line of lines) {
      const product = catalog.find(line.productId);
      const entry = product && product.sizes.find((s) => s.size === line.size);
      if (entry) entry.stock += line.qty;
    }
  }

  /**
   * Places an order: checks the cart and the customer, reserves stock, charges the
   * card through the gateway and stores the order. Returns the order.
   */
  async function placeOrder({ items, customer, card }) {
    const { fields: customerErrors, value: buyer } = validateCustomer(customer);
    const cardInput = card && typeof card === 'object' ? card : {};
    const cardCheck = validateCard(cardInput, now());
    const fields = { ...customerErrors, ...cardCheck.errors };
    const lines = normaliseItems(items);
    if (Object.keys(fields).length > 0) {
      throw new ShopError(422, 'invalid_form', 'Please check the highlighted fields.', fields);
    }
    // Keep the card's own checks in one place: the gateway also re-checks, and turns a failure into invalid_form.

    const reserved = reserve(lines);
    const subtotalCents = reserved.reduce((sum, l) => sum + l.lineTotalCents, 0);
    const shippingCents = subtotalCents >= FREE_SHIPPING_CENTS ? 0 : SHIPPING_CENTS;
    const totalCents = subtotalCents + shippingCents;

    let result;
    try {
      result = await gateway.charge({
        amountCents: totalCents,
        currency: CURRENCY,
        card: { number: cardCheck.number, expiry: cardInput.expiry, cvc: cardInput.cvc },
        reference: 'order',
      });
    } catch (err) {
      release(lines);
      if (err && err.code === 'invalid_card') {
        throw new ShopError(422, 'invalid_form', 'Please check the highlighted fields.', err.fields);
      }
      throw new ShopError(502, 'payment_unavailable', 'The payment service is unavailable. Please try again.');
    }

    if (!result || !result.approved) {
      release(lines);
      throw new ShopError(402, (result && result.code) || 'payment_declined', (result && result.message) || 'Your payment was declined.');
    }

    const order = {
      id: `ORD-${randomBytes(4).toString('hex').toUpperCase()}`,
      status: 'paid',
      createdAt: now().toISOString(),
      customer: buyer,
      items: reserved,
      subtotalCents,
      shippingCents,
      totalCents,
      currency: CURRENCY,
      payment: {
        gateway: gateway.name || 'unknown',
        status: 'approved',
        transactionId: result.transactionId,
        brand: result.brand,
        last4: result.last4,
      },
    };
    orders.set(order.id, order);
    return order;
  }

  return {
    placeOrder,
    getOrder(id) {
      return orders.get(String(id)) || null;
    },
  };
}

module.exports = { createOrderService, ShopError, FREE_SHIPPING_CENTS, SHIPPING_CENTS };
