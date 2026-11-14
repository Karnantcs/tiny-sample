import { ProductCard } from '../components/ProductCard';
import { ALL_SIZES, categories, products } from '../data/catalog';
import { filterProducts, parseShopFilters, SORT_OPTIONS, toShopQuery, type ShopFilters, type SortOption } from '../lib/filters';
import { buildHash } from '../lib/router';
import { navigate } from '../lib/useRoute';

const SORT_LABELS: Record<SortOption, string> = {
  featured: 'Featured',
  'price-asc': 'Price: low to high',
  'price-desc': 'Price: high to low',
};

interface ShopPageProps {
  query: URLSearchParams;
}

export function ShopPage({ query }: ShopPageProps) {
  const filters = parseShopFilters(query);
  const results = filterProducts(products, filters);

  // Filters live in the URL, so a filtered shop page can be bookmarked or shared.
  function update(change: Partial<ShopFilters>) {
    const next: ShopFilters = { ...filters, ...change };
    navigate(buildHash(['shop'], toShopQuery(next)));
  }

  const activeCategory = categories.find((category) => category.id === filters.category);

  return (
    <div className="container section">
      <div className="section__heading">
        <h1 className="page-title">{activeCategory ? activeCategory.name : 'All shoes'}</h1>
        <p className="muted">
          {results.length} {results.length === 1 ? 'shoe' : 'shoes'}
        </p>
      </div>

      <div className="shop-layout">
        <aside className="filters" aria-label="Filters">
          <div className="filter-group">
            <p className="filter-group__label" id="category-label">
              Category
            </p>
            <div className="chip-row" role="group" aria-labelledby="category-label">
              <button
                type="button"
                className="chip"
                aria-pressed={filters.category === null}
                onClick={() => update({ category: null })}
              >
                All
              </button>
              {categories.map((category) => (
                <button
                  key={category.id}
                  type="button"
                  className="chip"
                  aria-pressed={filters.category === category.id}
                  onClick={() => update({ category: category.id })}
                >
                  {category.name}
                </button>
              ))}
            </div>
          </div>

          <div className="filter-group">
            <label className="filter-group__label" htmlFor="size-filter">
              Size (US)
            </label>
            <select
              id="size-filter"
              className="select"
              value={filters.size === null ? '' : String(filters.size)}
              onChange={(event) =>
                update({ size: event.target.value === '' ? null : Number(event.target.value) })
              }
            >
              <option value="">All sizes</option>
              {ALL_SIZES.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </div>

          <div className="filter-group">
            <label className="filter-group__label" htmlFor="sort-filter">
              Sort by
            </label>
            <select
              id="sort-filter"
              className="select"
              value={filters.sort}
              onChange={(event) => update({ sort: event.target.value as SortOption })}
            >
              {SORT_OPTIONS.map((option) => (
                <option key={option} value={option}>
                  {SORT_LABELS[option]}
                </option>
              ))}
            </select>
          </div>

          {(filters.category !== null || filters.size !== null || filters.sort !== 'featured') && (
            <button type="button" className="text-link" onClick={() => navigate(buildHash(['shop']))}>
              Clear filters
            </button>
          )}
        </aside>

        <div>
          {results.length === 0 ? (
            <div className="empty-state">
              <p>No shoes match these filters. Try a different size or category.</p>
              <button type="button" className="button button--ghost" onClick={() => navigate(buildHash(['shop']))}>
                Show all shoes
              </button>
            </div>
          ) : (
            <div className="product-grid">
              {results.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
