import { describe, expect, it } from 'vitest';
import type { CardDetails, PaymentRequest } from './types';
import {
  createMockProvider,
  passesLuhn,
  TEST_CARD_DECLINED,
  TEST_CARD_SUCCESS,
  validateCardInput,
} from './mockProvider';
import { selectPaymentProvider } from './index';

const NOW = new Date('2025-06-15T12:00:00Z');

function card(overrides: Partial<CardDetails> = {}): CardDetails {
  return {
    number: '4242 4242 4242 4242',
    expiry: '12/30',
    cvc: '123',
    nameOnCard: 'Alex Shopper',
    ...overrides,
  };
}

function request(overrides: Partial<CardDetails> = {}): PaymentRequest {
  return { orderNumber: 'STR-TEST-0001', amountCents: 6200, currency: 'USD', card: card(overrides) };
}

// Instant mock, so the tests do not wait for the simulated delay.
const provider = createMockProvider(0);

describe('mock payment: success and decline', () => {
  it('approves 4242 4242 4242 4242', async () => {
    const result = await provider.pay(request());
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.cardLast4).toBe('4242');
      expect(result.transactionId).toMatch(/^mock_/);
    }
  });

  it('declines 4000 0000 0000 0002 with a clear message', async () => {
    const result = await provider.pay(request({ number: '4000 0000 0000 0002' }));
    expect(result).toEqual({
      ok: false,
      error: 'Your card was declined. Please try a different card.',
    });
  });

  it('accepts the test numbers typed without spaces', async () => {
    expect((await provider.pay(request({ number: TEST_CARD_SUCCESS }))).ok).toBe(true);
    expect((await provider.pay(request({ number: TEST_CARD_DECLINED }))).ok).toBe(false);
  });

  it('gives each successful payment its own transaction id', async () => {
    const first = await provider.pay(request());
    const second = await provider.pay(request());
    expect(first.ok && second.ok && first.transactionId !== second.transactionId).toBe(true);
  });

  it('does not put the full card number in the result', async () => {
    const result = await provider.pay(request());
    expect(JSON.stringify(result)).not.toContain('4242424242424242');
    expect(JSON.stringify(result)).not.toContain('4242 4242 4242 4242');
  });

  it('refuses other card numbers that pass the checksum, without charging them', async () => {
    // 5555 5555 5555 4444 passes Luhn but is not a test card.
    const result = await provider.pay(request({ number: '5555 5555 5555 4444' }));
    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.error).toContain('4242 4242 4242 4242');
    }
  });
});

describe('mock payment: card checks in the browser', () => {
  it('rejects a card number that fails the checksum', async () => {
    const result = await provider.pay(request({ number: '4242 4242 4242 4241' }));
    expect(result).toEqual({ ok: false, error: 'Enter a valid card number.' });
  });

  it('rejects a card number that is too short', () => {
    expect(validateCardInput(card({ number: '4242 4242' }), NOW)).toBe('Enter a valid card number.');
  });

  it('rejects an expired card', () => {
    expect(validateCardInput(card({ expiry: '05/25' }), NOW)).toBe('This card has expired.');
  });

  it('accepts a card that expires in the current month', () => {
    expect(validateCardInput(card({ expiry: '06/25' }), NOW)).toBeNull();
  });

  it('rejects a malformed expiry date', () => {
    expect(validateCardInput(card({ expiry: '13/30' }), NOW)).toBe('Enter the expiry date as MM/YY.');
    expect(validateCardInput(card({ expiry: '1230' }), NOW)).toBe('Enter the expiry date as MM/YY.');
  });

  it('rejects a bad security code', () => {
    expect(validateCardInput(card({ cvc: '12' }), NOW)).toBe('Enter the 3 or 4 digit security code.');
  });

  it('rejects a missing name on the card', () => {
    expect(validateCardInput(card({ nameOnCard: ' ' }), NOW)).toBe('Enter the name on the card.');
  });

  it('accepts the valid test card', () => {
    expect(validateCardInput(card(), NOW)).toBeNull();
  });

  it('checks the Luhn checksum', () => {
    expect(passesLuhn('4242424242424242')).toBe(true);
    expect(passesLuhn('4242424242424241')).toBe(false);
  });
});

describe('payment provider selection', () => {
  it('uses the mock provider when no keys are set', () => {
    expect(selectPaymentProvider({}).id).toBe('mock');
    expect(selectPaymentProvider({ VITE_STRIPE_PUBLISHABLE_KEY: '  ' }).id).toBe('mock');
  });

  it('switches Stripe on only when its key is set', () => {
    const stripe = selectPaymentProvider({ VITE_STRIPE_PUBLISHABLE_KEY: 'pk_test_123' });
    expect(stripe.id).toBe('stripe');
    expect(stripe.isTestMode).toBe(true);
  });

  it('switches PayPal on only when its client ID is set', () => {
    expect(selectPaymentProvider({ VITE_PAYPAL_CLIENT_ID: 'client-id' }).id).toBe('paypal');
  });

  it('uses the preferred provider when both are on', () => {
    const env = { VITE_STRIPE_PUBLISHABLE_KEY: 'pk_test_1', VITE_PAYPAL_CLIENT_ID: 'abc' };
    expect(selectPaymentProvider({ ...env, VITE_PAYMENT_PROVIDER: 'paypal' }).id).toBe('paypal');
    expect(selectPaymentProvider(env).id).toBe('stripe');
  });

  it('stub providers do not take payments yet', async () => {
    const stripe = selectPaymentProvider({ VITE_STRIPE_PUBLISHABLE_KEY: 'pk_live_1' });
    const result = await stripe.pay(request());
    expect(result.ok).toBe(false);
  });
});
