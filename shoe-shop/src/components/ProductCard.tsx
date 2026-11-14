import { getCategory } from '../data/catalog';
import { buildHash } from '../lib/router';
import type { Product } from '../types';
import { Price, SaleBadge } from './Price';
import { ShoeArt } from './ShoeArt';

export function ProductCard({ product }: { product: Product }) {
  const category = getCategory(product.categoryId);
  const mainColor = product.colors[0]?.hex ?? '#1f1f24';
  const accent = product.colors[1]?.hex ?? '#ffffff';
  return (
    <a className="product-card" href={buildHash(['product', product.id])}>
      <div className="product-card__image">
        <ShoeArt categoryId={product.categoryId} color={mainColor} accent={accent} label={product.name} />
        <SaleBadge product={product} />
      </div>
      <div className="product-card__body">
        <p className="product-card__category">{category?.name ?? 'Shoes'}</p>
        <h3 className="product-card__name">{product.name}</h3>
        <p className="product-card__price">
          <Price product={product} />
        </p>
      </div>
    </a>
  );
}
