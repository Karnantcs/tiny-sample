'use strict';

const http = require('node:http');
const path = require('node:path');
const { createCatalog } = require('./src/catalog');
const { createMockGateway } = require('./src/payments/mockGateway');
const { createOrderService } = require('./src/orders');
const { createApp } = require('./src/app');

/**
 * Builds the shop with its default parts. Tests can pass their own catalog or gateway.
 */
function createShop({ catalog = createCatalog(), gateway = createMockGateway() } = {}) {
  const orders = createOrderService({ catalog, gateway });
  return createApp({ catalog, orders, publicDir: path.join(__dirname, 'public') });
}

if (require.main === module) {
  const port = Number(process.env.PORT) || 8080;
  const server = http.createServer(createShop());
  server.listen(port, '0.0.0.0', () => {
    console.log(`shoe-store: serving the shop on port ${port} (payments: mock gateway)`);
  });
}

module.exports = { createShop };
