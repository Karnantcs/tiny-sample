'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { ShopError } = require('./orders');

const MAX_BODY_BYTES = 64 * 1024;

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.ico': 'image/x-icon',
};

const SECURITY_HEADERS = {
  'X-Content-Type-Options': 'nosniff',
  'Referrer-Policy': 'same-origin',
  'Content-Security-Policy': "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'",
};

/** Reads and parses a JSON request body. Rejects bodies over MAX_BODY_BYTES and bad JSON with a ShopError. */
function readJson(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    let tooLarge = false;
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > MAX_BODY_BYTES) {
        tooLarge = true;
        return;
      }
      chunks.push(chunk);
    });
    req.on('error', reject);
    req.on('end', () => {
      if (tooLarge) {
        reject(new ShopError(413, 'body_too_large', 'The request is too large.'));
        return;
      }
      try {
        const text = Buffer.concat(chunks).toString('utf8');
        resolve(text ? JSON.parse(text) : {});
      } catch {
        reject(new ShopError(400, 'invalid_json', 'The request body is not valid JSON.'));
      }
    });
  });
}

function sendJson(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    ...SECURITY_HEADERS,
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(payload),
    'Cache-Control': 'no-store',
  });
  res.end(payload);
}

function sendError(res, err) {
  if (err instanceof ShopError) {
    const error = { code: err.code, message: err.message };
    if (err.fields) error.fields = err.fields;
    sendJson(res, err.status, { error });
    return;
  }
  console.error(err);
  sendJson(res, 500, { error: { code: 'internal_error', message: 'Something went wrong. Please try again.' } });
}

function serveStatic(res, publicDir, pathname) {
  const relative = pathname === '/' ? 'index.html' : pathname.replace(/^\/+/, '');
  const file = path.resolve(publicDir, relative);
  // Only files inside public/ are served.
  if (!file.startsWith(publicDir + path.sep)) {
    sendJson(res, 404, { error: { code: 'not_found', message: 'Not found.' } });
    return;
  }
  fs.readFile(file, (err, data) => {
    if (err) {
      sendJson(res, 404, { error: { code: 'not_found', message: 'Not found.' } });
      return;
    }
    res.writeHead(200, {
      ...SECURITY_HEADERS,
      'Content-Type': MIME[path.extname(file)] || 'application/octet-stream',
      'Content-Length': data.length,
    });
    res.end(data);
  });
}

/**
 * Creates the request handler.
 *
 * @param {{ catalog: object, orders: object, publicDir: string }} deps
 */
function createApp({ catalog, orders, publicDir }) {
  const root = path.resolve(publicDir);

  return async function handle(req, res) {
    try {
      const url = new URL(req.url || '/', 'http://localhost');
      const pathname = decodeURIComponent(url.pathname);
      const method = req.method || 'GET';

      if (pathname.startsWith('/api/')) {
        await handleApi(req, res, method, pathname, { catalog, orders });
        return;
      }

      if (method !== 'GET' && method !== 'HEAD') {
        sendJson(res, 405, { error: { code: 'method_not_allowed', message: 'Method not allowed.' } });
        return;
      }
      serveStatic(res, root, pathname);
    } catch (err) {
      sendError(res, err);
    }
  };
}

async function handleApi(req, res, method, pathname, { catalog, orders }) {
  if (pathname === '/api/health' && method === 'GET') {
    sendJson(res, 200, { ok: true });
    return;
  }

  if (pathname === '/api/products' && method === 'GET') {
    sendJson(res, 200, { products: catalog.list() });
    return;
  }

  const productMatch = /^\/api\/products\/([^/]+)$/.exec(pathname);
  if (productMatch && method === 'GET') {
    const product = catalog.get(productMatch[1]);
    if (!product) {
      sendJson(res, 404, { error: { code: 'not_found', message: 'No such product.' } });
      return;
    }
    sendJson(res, 200, { product });
    return;
  }

  if (pathname === '/api/orders') {
    if (method !== 'POST') {
      sendJson(res, 405, { error: { code: 'method_not_allowed', message: 'Use POST to place an order.' } });
      return;
    }
    const parsed = await readJson(req);
    const body = parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    const order = await orders.placeOrder({ items: body.items, customer: body.customer, card: body.card });
    sendJson(res, 201, { order });
    return;
  }

  const orderMatch = /^\/api\/orders\/([^/]+)$/.exec(pathname);
  if (orderMatch && method === 'GET') {
    const order = orders.getOrder(orderMatch[1]);
    if (!order) {
      sendJson(res, 404, { error: { code: 'not_found', message: 'No such order.' } });
      return;
    }
    sendJson(res, 200, { order });
    return;
  }

  sendJson(res, 404, { error: { code: 'not_found', message: 'No such API route.' } });
}

module.exports = { createApp };
