import { categories, products, productsInCategory } from '../data/catalog';
import { ProductCard } from '../components/ProductCard';
import { ShoeArt } from '../components/ShoeArt';
import { buildHash } from '../lib/router';

const FEATURED_LIMIT = 4;

export function HomePage() {
  const featured = products.filter((product) => product.featured).slice(0, FEATURED_LIMIT);
  return (
    <>
      <section className="hero">
        <div className="container hero__inner">
          <div className="hero__text">
            <p className="eyebrow">New season</p>
            <h1>Step into Stride</h1>
            <p className="hero__lead">
              Running, casual, formal and sandals, in sizes 6 to 12. Built to be comfortable from the
              first wear.
            </p>
            <div className="hero__actions">
              <a className="button button--primary" href={buildHash(['shop'])}>
                Shop all shoes
              </a>
              <a className="button button--ghost" href={buildHash(['shop'], new URLSearchParams({ sort: 'price-asc' }))}>
                Best prices first
              </a>
            </div>
          </div>
          <div className="hero__art" aria-hidden="true">
            <ShoeArt categoryId="running" color="#2563eb" accent="#f5f5f5" label="" />
          </div>
        </div>
      </section>

      <section className="container section">
        <div className="section__heading">
          <h2>Shop by category</h2>
        </div>
        <div className="category-grid">
          {categories.map((category) => {
            const count = productsInCategory(category.id).length;
            return (
              <a
                key={category.id}
                className="category-card"
                href={buildHash(['shop'], new URLSearchParams({ category: category.id }))}
              >
                <h3 className="category-card__name">{category.name}</h3>
                <p className="category-card__text">{category.description}</p>
                <span className="category-card__count">
                  {count} {count === 1 ? 'style' : 'styles'}
                </span>
              </a>
            );
          })}
        </div>
      </section>

      <section className="container section">
        <div className="section__heading">
          <h2>Featured shoes</h2>
          <a className="text-link" href={buildHash(['shop'])}>
            View all
          </a>
        </div>
        <div className="product-grid">
          {featured.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>
    </>
  );
}
