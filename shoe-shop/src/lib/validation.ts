import type { ShippingDetails } from '../types';

export type ShippingErrors = Partial<Record<keyof ShippingDetails, string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const POSTAL_CODE_PATTERN = /^[A-Za-z0-9][A-Za-z0-9 -]{2,9}$/;

/**
 * Checks the shipping form. Returns an error message per field,
 * or an empty object when everything is valid. Values are trimmed first.
 */
export function validateShipping(details: ShippingDetails): ShippingErrors {
  const value = (field: keyof ShippingDetails) => details[field].trim();
  const errors: ShippingErrors = {};

  if (value('fullName').length < 2) {
    errors.fullName = 'Enter your full name.';
  }
  if (!EMAIL_PATTERN.test(value('email'))) {
    errors.email = 'Enter a valid email address, such as name@example.com.';
  }
  if (value('address').length < 5) {
    errors.address = 'Enter your street address.';
  }
  if (value('city').length < 2) {
    errors.city = 'Enter your city.';
  }
  if (value('state').length < 2) {
    errors.state = 'Enter your state or region.';
  }
  if (!POSTAL_CODE_PATTERN.test(value('postalCode'))) {
    errors.postalCode = 'Enter a valid postal or ZIP code.';
  }
  return errors;
}
