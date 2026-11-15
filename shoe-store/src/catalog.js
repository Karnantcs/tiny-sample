'use strict';

/**
 * The product catalog. Prices are whole cents so that totals never have
 * rounding errors. Stock is kept per size; an order takes stock away.
 */

const SEED_PRODUCTS = [
  {
    id: 'runner-x1',
    name: 'Runner X1',
    category: 'Running',
    priceCents: 12900,
    description: 'Lightweight daily trainer with a responsive foam midsole and breathable mesh upper.',
    colour: 'Midnight blue',
    sizes: [
      { size: '7', stock: 4 },
      { size: '8', stock: 6 },
      { size: '9', stock: 6 },
      { size: '10', stock: 3 },
      { size: '11', stock: 2 },
    ],
  },
  {
    id: 'city-loafer',
    name: 'City Loafer',
    category: 'Formal',
    priceCents: 15900,
    description: 'Hand-stitched leather loafer with a cushioned insole for all-day office wear.',
    colour: 'Espresso brown',
    sizes: [
      { size: '8', stock: 3 },
      { size: '9', stock: 5 },
      { size: '10', stock: 5 },
      { size: '11', stock: 1 },
    ],
  },
  {
    id: 'trail-ridge',
    name: 'Trail Ridge GTX',
    category: 'Outdoor',
    priceCents: 17900,
    description: 'Waterproof hiking boot with a grippy outsole and a reinforced toe cap.',
    colour: 'Forest green',
    sizes: [
      { size: '8', stock: 2 },
      { size: '9', stock: 4 },
      { size: '10', stock: 4 },
      { size: '12', stock: 1 },
    ],
  },
  {
    id: 'street-high',
    name: 'Street High',
    category: 'Casual',
    priceCents: 8900,
    description: 'Classic high-top canvas sneaker with a vulcanised sole.',
    colour: 'Off-white',
    sizes: [
      { size: '6', stock: 5 },
      { size: '7', stock: 5 },
      { size: '8', stock: 5 },
      { size: '9', stock: 0 },
      { size: '10', stock: 4 },
    ],
  },
];

/**
 * Returns a fresh copy of the seed catalog, so that each store owns its own stock counts.
 */
function createCatalog(products = SEED_PRODUCTS) {
  const byId = new Map(products.map((p) => [p.id, JSON.parse(JSON.stringify(p))]));

  return {
    list() {
      return [...byId.values()].map(publicProduct);
    },
    get(id) {
      const product = byId.get(id);
      return product ? publicProduct(product) : null;
    },
    // Internal: the live product (with stock), used by the order service.
    find(id) {
      return byId.get(id) || null;
    },
  };
}

function publicProduct(product) {
  return {
    id: product.id,
    name: product.name,
    category: product.category,
    priceCents: product.priceCents,
    description: product.description,
    colour: product.colour,
    sizes: product.sizes.map((s) => ({ size: s.size, inStock: s.stock > 0 })),
  };
}

module.exports = { createCatalog, SEED_PRODUCTS };
