import type { CardDetails, PaymentProvider, PaymentRequest, PaymentResult } from './types';

export const TEST_CARD_SUCCESS = '4242424242424242';
export const TEST_CARD_DECLINED = '4000000000000002';

/** Removes spaces and dashes from a typed card number. */
export function digitsOnly(number: string): string {
  return number.replace(/[\s-]/g, '');
}

/** Luhn checksum, the same check card forms use to catch typos. */
export function passesLuhn(digits: string): boolean {
  let sum = 0;
  let double = false;
  for (let i = digits.length - 1; i >= 0; i -= 1) {
    let digit = Number(digits[i]);
    if (double) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    double = !double;
  }
  return sum % 10 === 0;
}

/**
 * Checks the card fields in the browser: number format and checksum, expiry date and CVC.
 * Returns a message for the first problem, or null if the fields look valid.
 * Nothing is stored or sent anywhere.
 */
export function validateCardInput(card: CardDetails, now: Date = new Date()): string | null {
  const digits = digitsOnly(card.number);
  if (!/^\d{13,19}$/.test(digits) || !passesLuhn(digits)) {
    return 'Enter a valid card number.';
  }

  const expiry = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(card.expiry.trim());
  if (!expiry) {
    return 'Enter the expiry date as MM/YY.';
  }
  // A card is valid until the end of its expiry month.
  const expiresAt = new Date(2000 + Number(expiry[2]), Number(expiry[1]), 1);
  if (expiresAt <= now) {
    return 'This card has expired.';
  }

  if (!/^\d{3,4}$/.test(card.cvc.trim())) {
    return 'Enter the 3 or 4 digit security code.';
  }
  if (card.nameOnCard.trim().length < 2) {
    return 'Enter the name on the card.';
  }
  return null;
}

/**
 * The default provider. It works like Stripe's test mode: 4242 4242 4242 4242 succeeds,
 * 4000 0000 0000 0002 is declined. It never stores or sends card numbers.
 */
export function createMockProvider(delayMs = 500): PaymentProvider {
  return {
    id: 'mock',
    label: 'Test card (mock provider)',
    isTestMode: true,
    async pay(request: PaymentRequest): Promise<PaymentResult> {
      const problem = validateCardInput(request.card);
      if (problem) {
        return { ok: false, error: problem };
      }

      // Pretend to wait for the bank, so the processing state is visible.
      await new Promise((resolve) => setTimeout(resolve, delayMs));

      const digits = digitsOnly(request.card.number);
      if (digits === TEST_CARD_SUCCESS) {
        return {
          ok: true,
          transactionId: `mock_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`,
          cardLast4: digits.slice(-4),
        };
      }
      if (digits === TEST_CARD_DECLINED) {
        return { ok: false, error: 'Your card was declined. Please try a different card.' };
      }
      return {
        ok: false,
        error:
          'This test card is not recognised. Use 4242 4242 4242 4242 to succeed, or 4000 0000 0000 0002 to be declined.',
      };
    },
  };
}

export const mockProvider: PaymentProvider = createMockProvider();
