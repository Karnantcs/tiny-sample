import { describe, expect, it } from 'vitest';
import { computeTotals, FLAT_SHIPPING_CENTS, FREE_SHIPPING_OVER_CENTS, TAX_RATE } from './totals';

describe('order totals: shipping', () => {
  it('charges $8 shipping for a subtotal under $100', () => {
    const totals = computeTotals(50_00);
    expect(totals.shippingCents).toBe(800);
    expect(FLAT_SHIPPING_CENTS).toBe(800);
  });

  it('charges $8 shipping at exactly $100, because free shipping is for orders over $100', () => {
    expect(computeTotals(100_00).shippingCents).toBe(800);
  });

  it('ships free just above $100', () => {
    expect(computeTotals(100_01).shippingCents).toBe(0);
    expect(FREE_SHIPPING_OVER_CENTS).toBe(10_000);
  });

  it('has no shipping charge on an empty basket', () => {
    expect(computeTotals(0)).toEqual({ subtotalCents: 0, shippingCents: 0, taxCents: 0, totalCents: 0 });
  });
});

describe('order totals: tax and total', () => {
  it('charges 8% tax on the subtotal', () => {
    expect(TAX_RATE).toBe(0.08);
    expect(computeTotals(50_00).taxCents).toBe(400);
  });

  it('rounds tax to the nearest cent', () => {
    // 8% of $10.01 is 80.08 cents, which rounds to 80.
    expect(computeTotals(10_01).taxCents).toBe(80);
    // 8% of $10.06 is 80.48 cents, which rounds to 80.
    expect(computeTotals(10_06).taxCents).toBe(80);
    // 8% of $10.07 is 80.56 cents, which rounds to 81.
    expect(computeTotals(10_07).taxCents).toBe(81);
  });

  it('does not charge tax on shipping', () => {
    const totals = computeTotals(50_00);
    expect(totals.taxCents).toBe(Math.round(50_00 * 0.08));
  });

  it('adds subtotal, shipping and tax into the total', () => {
    // $50.00 subtotal: $8.00 shipping, $4.00 tax, $62.00 total.
    expect(computeTotals(50_00)).toEqual({
      subtotalCents: 5000,
      shippingCents: 800,
      taxCents: 400,
      totalCents: 6200,
    });
  });

  it('gives a free-shipping order with tax and no shipping line', () => {
    // $150.00 subtotal: free shipping, $12.00 tax, $162.00 total.
    expect(computeTotals(150_00)).toEqual({
      subtotalCents: 15000,
      shippingCents: 0,
      taxCents: 1200,
      totalCents: 16200,
    });
  });
});

describe('order totals: input checks', () => {
  it('rejects negative amounts', () => {
    expect(() => computeTotals(-1)).toThrow(RangeError);
  });

  it('rejects fractional cents', () => {
    expect(() => computeTotals(10.5)).toThrow(RangeError);
  });

  it('rejects values that are not numbers', () => {
    expect(() => computeTotals(Number.NaN)).toThrow(RangeError);
  });
});
