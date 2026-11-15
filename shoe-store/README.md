# Stride & Co. shoe store

A small online shoe store. Customers browse the catalog, fill a cart, check out
and get an order confirmation. Payments go through a **mock gateway** for now,
so no real card is charged.

It is a plain Node.js app with no dependencies (`node:http`, no npm packages).

## Run it

Requires Node.js 18 or newer.

```sh
cd shoe-store
npm start            # http://localhost:8080 (set PORT to change it)
npm test             # runs the test suite
```

NinjaBots runs `npm start` for the preview (see `start` in `ninjabots.json`).

## Pages

- **Shop**: the product grid. Pick a size and press *Add to cart*. Sold-out sizes are disabled.
- **Cart** (`#cart-view`): change quantities, remove lines, see the totals. The cart is saved in this browser.
- **Checkout** (`#checkout`): shipping details and card details, then *Pay now*.
- **Confirmation** (`#confirmation`): the order number, the payment's transaction id and the last four digits of the card.

Shipping is free for orders of $100.00 or more; otherwise it is $4.99.

## API

All responses are JSON. Money is in integer cents (`priceCents`, `totalCents`).

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/health` | `{ "ok": true }` |
| GET | `/api/products` | The catalog. Sizes show `inStock`, not the stock count. |
| GET | `/api/products/:id` | One product, or 404. |
| POST | `/api/orders` | Place an order (see below). Returns `201 { order }`. |
| GET | `/api/orders/:id` | An order, or 404. |

`POST /api/orders` body:

```json
{
  "items": [{ "id": "runner-x1", "size": "9", "qty": 1 }],
  "customer": { "name": "Ada Lovelace", "email": "ada@example.com", "address": "1 Analytical Way, London" },
  "card": { "number": "4242 4242 4242 4242", "expiry": "12/30", "cvc": "123" }
}
```

Errors look like `{ "error": { "code", "message", "fields"? } }`:

| Status | Code | When |
| --- | --- | --- |
| 400 | `empty_cart`, `invalid_item`, `invalid_quantity`, `unknown_product`, `unknown_size`, `invalid_json` | The cart or body is wrong. |
| 402 | `card_declined`, `insufficient_funds` | The gateway declined the card. |
| 409 | `out_of_stock` | Not enough stock for a line. |
| 413 | `body_too_large` | The request is over 64 KB. |
| 422 | `invalid_form` | Field problems; `fields` maps each field to a message. |
| 502 | `payment_unavailable` | The gateway failed. Stock is put back. |

Prices always come from the server's catalog. The browser never sends a price.

## Mock payment gateway

`src/payments/mockGateway.js` checks cards the way a real gateway would (Luhn
check, expiry month, 3–4 digit CVC) and then answers with fixed results:

| Card number | Result |
| --- | --- |
| `4242 4242 4242 4242` | Approved |
| `4000 0000 0000 0002` | Declined (`card_declined`) |
| `4000 0000 0000 9995` | Declined (`insufficient_funds`) |
| any other number that passes Luhn | Approved |

Only the brand and last four digits are kept. The full card number and the CVC are never stored or returned.

### Replacing the mock with a real provider

The order service only calls `gateway.charge({ amountCents, currency, card, reference })`
and reads `{ approved, transactionId, brand, last4 }` or `{ approved: false, code, message }`.
To switch providers, write a class with the same `charge()` method (and a `name`) and pass it
to `createShop({ gateway })` in `server.js`. Keep card data out of the browser and logs when you do.

## Layout

```
server.js                 entry point; builds the shop and listens on $PORT
src/catalog.js            products, sizes and stock (in memory)
src/orders.js             cart checks, stock, totals and order placement
src/payments/mockGateway.js  mock gateway and card checks
src/app.js                HTTP routes, static files and error responses
public/                   storefront (index.html, styles.css, app.js)
test/                     node:test suites for payments, orders and the API
```

## Limits of this version

- Products, stock and orders live in memory. A restart resets them.
- There are no accounts, no order emails and no shipping or tax rules beyond the flat rate above.
- The order lookup (`/api/orders/:id`) has no login; the ids are random but it is still public.
