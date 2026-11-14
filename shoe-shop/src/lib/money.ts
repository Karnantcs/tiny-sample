const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

/** Converts a dollar amount to whole cents, so totals never drift by float errors. */
export function toCents(dollars: number): number {
  return Math.round(dollars * 100);
}

/** Formats cents as US dollars, e.g. 13900 -> "$139.00". */
export function formatUsd(cents: number): string {
  return usd.format(cents / 100);
}
