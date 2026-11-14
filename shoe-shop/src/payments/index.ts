import { mockProvider } from './mockProvider';
import { createPayPalStub, createStripeStub } from './stubProviders';
import type { PaymentProvider } from './types';

export type { PaymentProvider, PaymentRequest, PaymentResult, CardDetails } from './types';

/** The environment variables the payment setup reads. Keys come from the environment only. */
export interface PaymentEnv {
  VITE_PAYMENT_PROVIDER?: string;
  VITE_STRIPE_PUBLISHABLE_KEY?: string;
  VITE_PAYPAL_CLIENT_ID?: string;
}

/**
 * Picks the provider. Stripe is on when its key is set and PayPal when its client ID is set.
 * With no keys, the mock provider is used. If both are on, VITE_PAYMENT_PROVIDER decides,
 * and otherwise Stripe is used.
 */
export function selectPaymentProvider(env: PaymentEnv): PaymentProvider {
  const stripeKey = env.VITE_STRIPE_PUBLISHABLE_KEY?.trim() ?? '';
  const paypalId = env.VITE_PAYPAL_CLIENT_ID?.trim() ?? '';
  const preferred = (env.VITE_PAYMENT_PROVIDER ?? '').trim().toLowerCase();

  const stripeOn = stripeKey.length > 0;
  const paypalOn = paypalId.length > 0;

  if (preferred === 'paypal' && paypalOn) return createPayPalStub();
  if (preferred === 'stripe' && stripeOn) return createStripeStub(stripeKey);
  if (stripeOn) return createStripeStub(stripeKey);
  if (paypalOn) return createPayPalStub();
  return mockProvider;
}

/** The provider the app uses, chosen from Vite's import.meta.env at build time. */
export const paymentProvider: PaymentProvider = selectPaymentProvider(import.meta.env);
