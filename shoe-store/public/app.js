'use strict';

(function () {
  const CART_KEY = 'stride-cart';
  const MAX_QTY = 10;
  // Mirrors src/orders.js. The server computes the real totals; these only show them before payment.
  const FREE_SHIPPING_CENTS = 10000;
  const SHIPPING_CENTS = 499;

  const money = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
  const fmt = (cents) => money.format(cents / 100);

  let products = [];
  let lastOrder = null;

  const $ = (id) => document.getElementById(id);

  function el(tag, props, children) {
    const node = document.createElement(tag);
    if (props) {
      for (const [key, value] of Object.entries(props)) {
        // null means "leave this attribute off", e.g. disabled: null for an enabled button.
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') node.className = value;
        else if (key === 'text') node.textContent = value;
        else if (key === 'style') node.setAttribute('style', value);
        else node.setAttribute(key, value);
      }
    }
    for (const child of children || []) node.append(child);
    return node;
  }

  // ---- Cart (kept in this browser) ----

  function loadCart() {
    try {
      const data = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
      return Array.isArray(data) ? data.filter((l) => l && typeof l.id === 'string' && typeof l.size === 'string' && Number.isInteger(l.qty)) : [];
    } catch {
      return [];
    }
  }

  let cart = loadCart();

  function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCount();
  }

  function productById(id) {
    return products.find((p) => p.id === id) || null;
  }

  // Drops lines for products or sizes that no longer exist.
  function tidyCart() {
    const before = cart.length;
    cart = cart.filter((line) => {
      const product = productById(line.id);
      return product && product.sizes.some((s) => s.size === line.size);
    });
    if (cart.length !== before) saveCart();
  }

  function cartCount() {
    return cart.reduce((sum, l) => sum + l.qty, 0);
  }

  function updateCount() {
    $('cart-count').textContent = String(cartCount());
  }

  function addToCart(id, size) {
    const existing = cart.find((l) => l.id === id && l.size === size);
    if (existing) {
      existing.qty = Math.min(MAX_QTY, existing.qty + 1);
    } else {
      cart.push({ id, size, qty: 1 });
    }
    saveCart();
  }

  function setQty(id, size, qty) {
    const line = cart.find((l) => l.id === id && l.size === size);
    if (!line) return;
    line.qty = Math.max(1, Math.min(MAX_QTY, qty));
    saveCart();
  }

  function removeLine(id, size) {
    cart = cart.filter((l) => !(l.id === id && l.size === size));
    saveCart();
  }

  function totals() {
    let subtotal = 0;
    for (const line of cart) {
      const product = productById(line.id);
      if (product) subtotal += product.priceCents * line.qty;
    }
    const shipping = subtotal === 0 || subtotal >= FREE_SHIPPING_CENTS ? 0 : SHIPPING_CENTS;
    return { subtotal, shipping, total: subtotal + shipping };
  }

  // ---- Catalog ----

  function swatchColour(id) {
    let hash = 0;
    for (const ch of id) hash = (hash * 31 + ch.charCodeAt(0)) % 360;
    return `hsl(${hash} 45% 38%)`;
  }

  function renderCatalog() {
    const grid = $('product-grid');
    grid.replaceChildren();
    for (const product of products) {
      const select = el('select', { name: 'size', 'aria-label': `Size for ${product.name}` });
      const inStock = product.sizes.filter((s) => s.inStock);
      for (const s of product.sizes) {
        select.append(el('option', { value: s.size, disabled: s.inStock ? null : '', text: s.inStock ? `EU ${s.size}` : `EU ${s.size} (sold out)` }));
      }
      if (inStock.length > 0) {
        select.value = inStock[0].size;
      }

      const form = el('form', { 'data-id': product.id }, [
        select,
        el('button', { type: 'submit', class: 'button', disabled: inStock.length ? null : '', text: inStock.length ? 'Add to cart' : 'Sold out' }),
      ]);

      grid.append(
        el('article', { class: 'card' }, [
          el('div', { class: 'swatch', style: `background:${swatchColour(product.id)}`, text: product.colour }),
          el('div', { class: 'card-body' }, [
            el('span', { class: 'category', text: product.category }),
            el('h3', { text: product.name }),
            el('span', { class: 'price', text: fmt(product.priceCents) }),
            el('p', { class: 'description', text: product.description }),
            form,
          ]),
        ])
      );
    }
    $('catalog-status').textContent = products.length ? '' : 'No shoes are available right now.';
  }

  // ---- Cart and summary views ----

  function renderCart() {
    tidyCart();
    const list = $('cart-lines');
    list.replaceChildren();
    const empty = cart.length === 0;
    $('cart-empty').hidden = !empty;
    $('cart-totals').hidden = empty;
    $('cart-hint').hidden = empty;
    $('go-checkout').hidden = empty;

    for (const line of cart) {
      const product = productById(line.id);
      const qtyInput = el('input', { type: 'number', min: '1', max: String(MAX_QTY), value: String(line.qty), 'aria-label': `Quantity of ${product.name}` });
      qtyInput.addEventListener('change', () => {
        setQty(line.id, line.size, Number(qtyInput.value) || 1);
        renderCart();
      });
      const remove = el('button', { type: 'button', class: 'link-button', text: 'Remove' });
      remove.addEventListener('click', () => {
        removeLine(line.id, line.size);
        renderCart();
      });

      list.append(
        el('li', null, [
          el('div', null, [
            el('strong', { text: product.name }),
            el('div', { class: 'hint', text: `EU ${line.size} · ${fmt(product.priceCents)} each` }),
          ]),
          el('div', { class: 'line-controls' }, [qtyInput, el('span', { text: fmt(product.priceCents * line.qty) }), remove]),
        ])
      );
    }

    const t = totals();
    $('cart-subtotal').textContent = fmt(t.subtotal);
    $('cart-shipping').textContent = t.shipping === 0 ? 'Free' : fmt(t.shipping);
    $('cart-total').textContent = fmt(t.total);
  }

  function renderSummary() {
    const list = $('summary-lines');
    list.replaceChildren();
    for (const line of cart) {
      const product = productById(line.id);
      list.append(el('li', null, [el('span', { text: `${product.name} · EU ${line.size} × ${line.qty}` }), el('span', { text: fmt(product.priceCents * line.qty) })]));
    }
    const t = totals();
    $('summary-subtotal').textContent = fmt(t.subtotal);
    $('summary-shipping').textContent = t.shipping === 0 ? 'Free' : fmt(t.shipping);
    $('summary-total').textContent = fmt(t.total);
  }

  function renderConfirmation(order) {
    const details = $('order-details');
    details.replaceChildren();
    const rows = [
      ['Order', order.id],
      ['Name', order.customer.name],
      ['Email', order.customer.email],
      ['Ship to', order.customer.address],
      ['Card', `${order.payment.brand} ending ${order.payment.last4}`],
      ['Transaction', order.payment.transactionId],
      ['Total paid', fmt(order.totalCents)],
    ];
    for (const [label, value] of rows) {
      details.append(el('dt', { text: label }), el('dd', { text: value }));
    }
    const lines = $('order-lines');
    lines.replaceChildren();
    for (const item of order.items) {
      lines.append(el('li', null, [el('span', { text: `${item.name} · EU ${item.size} × ${item.qty}` }), el('span', { text: fmt(item.lineTotalCents) })]));
    }
  }

  // ---- Checkout ----

  function clearErrors() {
    for (const node of document.querySelectorAll('[data-error]')) node.textContent = '';
    $('checkout-error').hidden = true;
    $('checkout-error').textContent = '';
  }

  function showErrors(fields, message) {
    for (const [field, text] of Object.entries(fields || {})) {
      const node = document.querySelector(`[data-error="${field}"]`);
      if (node) node.textContent = text;
    }
    if (message) {
      $('checkout-error').textContent = message;
      $('checkout-error').hidden = false;
    }
  }

  async function submitOrder(event) {
    event.preventDefault();
    clearErrors();
    const form = $('checkout-form');
    const data = new FormData(form);
    const payload = {
      items: cart.map((l) => ({ id: l.id, size: l.size, qty: l.qty })),
      customer: {
        name: String(data.get('name') || ''),
        email: String(data.get('email') || ''),
        address: String(data.get('address') || ''),
      },
      card: {
        number: String(data.get('number') || ''),
        expiry: String(data.get('expiry') || ''),
        cvc: String(data.get('cvc') || ''),
      },
    };

    const button = $('pay-button');
    button.disabled = true;
    button.textContent = 'Processing…';
    try {
      const response = await fetch('api/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
      });
      const body = await response.json().catch(() => ({}));

      if (response.ok && body.order) {
        lastOrder = body.order;
        cart = [];
        saveCart();
        form.reset();
        location.hash = '#confirmation';
        return;
      }

      const error = body.error || {};
      showErrors(error.fields, error.message || 'Your payment could not be completed. Please try again.');
      if (error.code === 'out_of_stock' || error.code === 'unknown_product' || error.code === 'unknown_size') {
        await loadProducts();
        tidyCart();
        renderCatalog();
      }
    } catch {
      showErrors(null, 'We could not reach the store. Check your connection and try again.');
    } finally {
      button.disabled = false;
      button.textContent = 'Pay now';
    }
  }

  // ---- Views ----

  const VIEWS = ['shop', 'cart-view', 'checkout', 'confirmation'];

  function show(name) {
    for (const view of VIEWS) {
      $(view).hidden = view !== name;
    }
  }

  function route() {
    const name = location.hash.replace(/^#/, '') || 'shop';
    if (name === 'checkout') {
      if (cart.length === 0) {
        location.hash = '#cart-view';
        return;
      }
      renderSummary();
      clearErrors();
      show('checkout');
    } else if (name === 'cart-view') {
      renderCart();
      show('cart-view');
    } else if (name === 'confirmation') {
      if (!lastOrder) {
        location.hash = '#shop';
        return;
      }
      renderConfirmation(lastOrder);
      show('confirmation');
    } else {
      show('shop');
    }
    window.scrollTo(0, 0);
  }

  async function loadProducts() {
    try {
      const response = await fetch('api/products', { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const body = await response.json();
      products = Array.isArray(body.products) ? body.products : [];
    } catch {
      products = [];
      $('catalog-status').textContent = 'We could not load the catalog. Please refresh the page.';
    }
  }

  async function init() {
    $('product-grid').addEventListener('submit', (event) => {
      const form = event.target.closest('form[data-id]');
      if (!form) return;
      event.preventDefault();
      const size = form.querySelector('select').value;
      const product = productById(form.dataset.id);
      addToCart(form.dataset.id, size);
      $('catalog-status').textContent = `Added ${product.name} (EU ${size}) to your cart.`;
      updateCount();
    });
    $('checkout-form').addEventListener('submit', submitOrder);
    window.addEventListener('hashchange', route);

    updateCount();
    await loadProducts();
    renderCatalog();
    tidyCart();
    updateCount();
    route();
  }

  init();
})();
