# Stride: online shoe store

Stride is a small shoe store built with React, TypeScript and Vite. It has a catalogue of five
categories (Running, Casual Sneakers, Formal Leather, Sandals and Kids) with three shoes each, a shop with filters, a cart, checkout and an order confirmation.
Routes use hashes (`#/shop`, `#/product/run-sprint-lite`), so the app works under any path.

## Run it

```sh
cd shoe-shop
npm install
npm run dev        # development server, http://localhost:5173
npm test           # type check, then the Vitest suites
npm run build      # type check and production build into dist/
npm run preview    # serves the production build
```

## Project layout

| Path | What it holds |
| --- | --- |
| `src/data/products.json` | The whole catalogue: categories and products. Add products here. |
| `src/data/catalog.ts` | Typed access to the catalogue and the list of sizes (6 to 12). |
| `src/cart/` | Cart rules (`cart.ts`) and the in-memory cart context (`CartContext.tsx`). |
| `src/lib/` | Money, order totals, shop filters, hash routing, shipping validation, order building. |
| `src/payments/` | The payment interface, the mock provider and the Stripe and PayPal stubs. |
| `src/components/` | Header, footer, product cards, the SVG shoe drawing, order summary. |
| `src/pages/` | Home, shop, product, cart, checkout and confirmation pages. |
| `src/styles.css` | All styles: design tokens, responsive layout and the mobile menu. |

## Adding a product

Add an entry to `products` in `src/data/products.json`:

```json
{
  "id": "run-example",
  "name": "Example Runner",
  "categoryId": "running",
  "price": 99.0,
  "sizes": [6, 7, 8, 9, 10, 11, 12],
  "colors": [{ "name": "Black", "hex": "#1f1f24" }],
  "description": "A short description.",
  "featured": false
}
```

Use one of the existing category ids (`running`, `casual`, `formal`, `sandals`, `kids`). The shoe's
drawing follows its category.

To put a product on sale, add `"salePercent": 20` (a whole number from 1 to 99). The shop shows the old
price struck through, a Sale badge, and the sale price. The cart, the order and price sorting all use the
sale price. Leave the field out for full price. The tests check that sizes are between 6 and 12 and that every product has a
known category.

## Pricing rules

- Prices are in US dollars and are converted to cents before any arithmetic.
- A sale takes `salePercent` off the list price, rounded to the nearest cent (`src/lib/pricing.ts`).
- Shipping is free for subtotals **over** $100. Otherwise it is a flat $8. An empty cart has no shipping.
- Tax is 8% of the subtotal, rounded to the nearest cent. Shipping is not taxed.
- All of this is in `src/lib/totals.ts`.

## Payments

Checkout talks to one interface, `PaymentProvider` in `src/payments/types.ts`.

- **Mock (default).** Works like Stripe's test mode. `4242 4242 4242 4242` succeeds and
  `4000 0000 0000 0002` is declined. Any other number is refused with a message. The card number is
  checked in the browser (format, Luhn checksum, expiry date, CVC) and is never stored or sent.
  Only the last four digits stay on the order.
- **Stripe and PayPal (stubs).** These switch on only when an environment variable is set. Neither
  takes payments yet. Their `pay()` returns a clear "not connected" error, so nothing is charged by
  mistake.

Keys are read from the environment only. Copy `.env.example` to `.env.local` to set them. Real keys
must never be committed.

| Variable | Effect |
| --- | --- |
| `VITE_STRIPE_PUBLISHABLE_KEY` | Switches on the Stripe stub. |
| `VITE_PAYPAL_CLIENT_ID` | Switches on the PayPal stub. |
| `VITE_PAYMENT_PROVIDER` | `stripe` or `paypal`. Decides which to use when both are on. |

## State and storage

The cart and the last order live in React state only. Cookies and browser storage are not used, so
the app works where they are blocked. A page refresh empties the cart, and the confirmation page
only shows an order for the current session.

## Tests

`npm test` runs `tsc --noEmit` and then Vitest over `src/**/*.test.ts`. The suites cover:

- the cart: add, change quantity (with limits), remove, count and subtotal
- the order totals: shipping thresholds, tax rounding and totals
- the shop filters: category, size, price sorting and URL round-trips
- the mock payment: success, decline, card checks and provider selection
- the catalogue data, shipping validation, order building and hash routes
