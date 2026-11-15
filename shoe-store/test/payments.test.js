'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { createMockGateway, validateCard, passesLuhn, detectBrand } = require('../src/payments/mockGateway');

const NOW = new Date('2026-06-15T12:00:00Z');
const goodCard = { number: '4242 4242 4242 4242', expiry: '12/30', cvc: '123' };

test('passesLuhn accepts a valid number and rejects a broken one', () => {
  assert.equal(passesLuhn('4242424242424242'), true);
  assert.equal(passesLuhn('4242424242424241'), false);
});

test('validateCard accepts a well-formed card with a future expiry', () => {
  const result = validateCard(goodCard, NOW);
  assert.equal(result.ok, true);
  assert.deepEqual(result.errors, {});
  assert.equal(result.number, '4242424242424242');
});

test('validateCard rejects a bad number, expiry and CVC', () => {
  const result = validateCard({ number: '4242 4242 4242 4241', expiry: '13/30', cvc: '12' }, NOW);
  assert.equal(result.ok, false);
  assert.ok(result.errors.number);
  assert.ok(result.errors.expiry);
  assert.ok(result.errors.cvc);
});

test('validateCard treats a card as valid until the end of its expiry month', () => {
  assert.equal(validateCard({ ...goodCard, expiry: '06/26' }, NOW).ok, true);
  assert.equal(validateCard({ ...goodCard, expiry: '05/26' }, NOW).errors.expiry, 'This card has expired.');
});

test('validateCard accepts a four-digit year and rejects a missing card', () => {
  assert.equal(validateCard({ ...goodCard, expiry: '12/2030' }, NOW).ok, true);
  const missing = validateCard(undefined, NOW);
  assert.equal(missing.ok, false);
  assert.ok(missing.errors.number);
});

test('detectBrand guesses the brand for display only', () => {
  assert.equal(detectBrand('4242424242424242'), 'visa');
  assert.equal(detectBrand('5555555555554444'), 'mastercard');
  assert.equal(detectBrand('378282246310005'), 'amex');
  assert.equal(detectBrand('9999999999999999'), 'card');
});

test('the mock gateway approves a normal test card and returns only the last four digits', async () => {
  const gateway = createMockGateway();
  const result = await gateway.charge({ amountCents: 13399, card: goodCard, reference: 'order' });

  assert.equal(result.approved, true);
  assert.match(result.transactionId, /^mock_[0-9a-f]{18}$/);
  assert.equal(result.last4, '4242');
  assert.equal(result.brand, 'visa');
  assert.equal(JSON.stringify(result).includes('4242424242424242'), false);
});

test('the mock gateway declines the decline test cards', async () => {
  const gateway = createMockGateway();

  const declined = await gateway.charge({ amountCents: 100, card: { number: '4000000000000002', expiry: '12/30', cvc: '123' } });
  assert.equal(declined.approved, false);
  assert.equal(declined.code, 'card_declined');

  const funds = await gateway.charge({ amountCents: 100, card: { number: '4000 0000 0000 9995', expiry: '12/30', cvc: '123' } });
  assert.equal(funds.approved, false);
  assert.equal(funds.code, 'insufficient_funds');
});

test('the mock gateway rejects invalid card details and bad amounts', async () => {
  const gateway = createMockGateway();

  await assert.rejects(
    gateway.charge({ amountCents: 100, card: { number: '1234', expiry: '12/30', cvc: '123' } }),
    (err) => err.code === 'invalid_card' && typeof err.fields.number === 'string'
  );
  await assert.rejects(gateway.charge({ amountCents: 0, card: goodCard }), TypeError);
  await assert.rejects(gateway.charge({ amountCents: 12.5, card: goodCard }), TypeError);
});
