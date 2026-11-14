import { ALL_SIZES } from '../data/catalog';
import { currentPriceCents } from './pricing';
import type { Product } from '../types';

export const SORT_OPTIONS = ['featured', 'price-asc', 'price-desc'] as const;
export type SortOption = (typeof SORT_OPTIONS)[number];

export interface ShopFilters {
  /** A category id, or null for every category. */
  category: string | null;
  /** A size from ALL_SIZES, or null for every size. */
  size: number | null;
  sort: SortOption;
}

export const DEFAULT_FILTERS: ShopFilters = { category: null, size: null, sort: 'featured' };

/**
 * Keeps the products that match the category and size, then sorts them.
 * Never changes the array it is given. Sorting by price is stable, so ties keep catalogue order.
 */
export function filterProducts(products: Product[], filters: ShopFilters): Product[] {
  const matching = products.filter(
    (product) =>
      (filters.category === null || product.categoryId === filters.category) &&
      (filters.size === null || product.sizes.includes(filters.size)),
  );
  if (filters.sort === 'price-asc') {
    return [...matching].sort((a, b) => currentPriceCents(a) - currentPriceCents(b));
  }
  if (filters.sort === 'price-desc') {
    return [...matching].sort((a, b) => currentPriceCents(b) - currentPriceCents(a));
  }
  return matching;
}

/**
 * Reads filters from a URL query. Unknown or invalid values fall back to "no filter",
 * so a hand-edited link never breaks the shop page.
 */
export function parseShopFilters(query: URLSearchParams): ShopFilters {
  const category = query.get('category')?.trim() || null;

  const sizeText = query.get('size');
  const sizeNumber = sizeText === null ? NaN : Number(sizeText);
  const size = ALL_SIZES.includes(sizeNumber) ? sizeNumber : null;

  const sortText = query.get('sort');
  const sort = SORT_OPTIONS.includes(sortText as SortOption) ? (sortText as SortOption) : 'featured';

  return { category, size, sort };
}

/** The inverse of parseShopFilters: only non-default values go into the query. */
export function toShopQuery(filters: ShopFilters): URLSearchParams {
  const query = new URLSearchParams();
  if (filters.category) query.set('category', filters.category);
  if (filters.size !== null) query.set('size', String(filters.size));
  if (filters.sort !== 'featured') query.set('sort', filters.sort);
  return query;
}
