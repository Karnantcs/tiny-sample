export interface CardDetails {
  /** Card number as typed, spaces allowed. Used only in the browser. */
  number: string;
  /** Expiry as MM/YY. */
  expiry: string;
  cvc: string;
  nameOnCard: string;
}

export interface PaymentRequest {
  orderNumber: string;
  amountCents: number;
  currency: 'USD';
  card: CardDetails;
}

export type PaymentResult =
  | { ok: true; transactionId: string; cardLast4: string }
  | { ok: false; error: string };

/** One interface for every payment provider. The checkout page only talks to this. */
export interface PaymentProvider {
  id: 'mock' | 'stripe' | 'paypal';
  label: string;
  /** True when no real money moves (the mock provider, or a Stripe test key). */
  isTestMode: boolean;
  pay(request: PaymentRequest): Promise<PaymentResult>;
}
