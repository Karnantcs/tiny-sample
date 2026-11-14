/// <reference types="vite/client" />

// Payment keys are read from the environment at build time. Never put real keys in the code.
interface ImportMetaEnv {
  readonly VITE_PAYMENT_PROVIDER?: string;
  readonly VITE_STRIPE_PUBLISHABLE_KEY?: string;
  readonly VITE_PAYPAL_CLIENT_ID?: string;
}
