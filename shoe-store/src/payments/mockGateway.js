'use strict';

/**
 * A mock payment gateway. It does not talk to any bank or payment provider.
 * It checks card details the way a real gateway would, and it answers with
 * fixed results for well-known test card numbers:
 *
 *   4242 4242 4242 4242  approved
 *   4000 0000 0000 0002  declined (card_declined)
 *   4000 0000 0000 9995  declined (insufficient_funds)
 *
 * Any other number that passes the Luhn check is approved. Card numbers are
 * never stored: the gateway returns only the last four digits and the brand.
 *
 * To use a real provider, implement an object with the same charge() method
 * and pass it to createApp() in server.js.
 */

const { randomBytes } = require('node:crypto');

const DECLINE_CARDS = new Map([
  ['4000000000000002', { code: 'card_declined', message: 'Your card was declined.' }],
  ['4000000000009995', { code: 'insufficient_funds', message: 'Your card has insufficient funds.' }],
]);

/** Returns the card brand from the number's leading digits (a simple guess for display only). */
function detectBrand(number) {
  if (/^4/.test(number)) return 'visa';
  if (/^5[1-5]/.test(number) || /^2(2[2-9]|[3-6]\d|7[01]|720)/.test(number)) return 'mastercard';
  if (/^3[47]/.test(number)) return 'amex';
  return 'card';
}

/** Luhn checksum over a string of digits. */
function passesLuhn(digits) {
  let sum = 0;
  let double = false;
  for (let i = digits.length - 1; i >= 0; i -= 1) {
    let d = Number(digits[i]);
    if (double) {
      d *= 2;
      if (d > 9) d -= 9;
    }
    sum += d;
    double = !double;
  }
  return sum % 10 === 0;
}

/**
 * Checks card details. Returns { ok, errors } where errors maps each field to a message.
 * `now` can be passed in for tests.
 */
function validateCard(card, now = new Date()) {
  const errors = {};
  const number = String((card && card.number) || '').replace(/[\s-]/g, '');
  if (!/^\d{12,19}$/.test(number) || !passesLuhn(number)) {
    errors.number = 'Enter a valid card number.';
  }

  const expiry = String((card && card.expiry) || '').trim();
  const match = /^(\d{1,2})\s*\/\s*(\d{2}|\d{4})$/.exec(expiry);
  if (!match) {
    errors.expiry = 'Enter the expiry as MM/YY.';
  } else {
    const month = Number(match[1]);
    let year = Number(match[2]);
    if (year < 100) year += 2000;
    if (month < 1 || month > 12) {
      errors.expiry = 'Enter a valid expiry month.';
    } else {
      // A card is valid until the end of its expiry month.
      const endOfMonth = new Date(Date.UTC(year, month, 1));
      if (endOfMonth <= now) {
        errors.expiry = 'This card has expired.';
      }
    }
  }

  const cvc = String((card && card.cvc) || '').trim();
  if (!/^\d{3,4}$/.test(cvc)) {
    errors.cvc = 'Enter the 3 or 4 digit security code.';
  }

  return { ok: Object.keys(errors).length === 0, errors, number };
}

/**
 * Creates the mock gateway. Each charge() returns a promise of either
 * { approved: true, transactionId, brand, last4 } or
 * { approved: false, code, message }.
 */
function createMockGateway() {
  return {
    name: 'mock',

    async charge({ amountCents, currency = 'USD', card, reference }) {
      if (!Number.isInteger(amountCents) || amountCents <= 0) {
        throw new TypeError('amountCents must be a positive integer');
      }
      const check = validateCard(card);
      if (!check.ok) {
        const error = new Error('Invalid card details');
        error.code = 'invalid_card';
        error.fields = check.errors;
        throw error;
      }

      const transactionId = `mock_${randomBytes(9).toString('hex')}`;
      const declined = DECLINE_CARDS.get(check.number);
      if (declined) {
        return { approved: false, transactionId, code: declined.code, message: declined.message };
      }

      return {
        approved: true,
        transactionId,
        brand: detectBrand(check.number),
        last4: check.number.slice(-4),
        amountCents,
        currency,
        reference,
      };
    },
  };
}

module.exports = { createMockGateway, validateCard, passesLuhn, detectBrand };
