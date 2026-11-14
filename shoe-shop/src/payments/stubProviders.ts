import type { PaymentProvider } from './types';

/*
 * Stub providers. They are only switched on when their key is set in an
 * environment variable (see index.ts). Real keys never go in the code.
 * Neither stub takes payments yet: pay() returns an error that says so, so nothing
 * is charged by mistake. Wiring up the real SDK goes here later.
 */

const NOT_CONNECTED =
  'This payment method is not connected yet. Please use the test card provider for now.';

export function createStripeStub(publishableKey: string): PaymentProvider {
  return {
    id: 'stripe',
    label: 'Stripe',
    isTestMode: publishableKey.startsWith('pk_test_'),
    async pay() {
      return { ok: false, error: NOT_CONNECTED };
    },
  };
}

export function createPayPalStub(): PaymentProvider {
  return {
    id: 'paypal',
    label: 'PayPal',
    // A PayPal client ID does not show whether it is sandbox or live, so the stub
    // does not claim test mode.
    isTestMode: false,
    async pay() {
      return { ok: false, error: NOT_CONNECTED };
    },
  };
}
