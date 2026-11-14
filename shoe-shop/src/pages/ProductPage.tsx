import { useState } from 'react';
import { ShoeArt } from '../components/ShoeArt';
import { useCart } from '../cart/CartContext';
import { Price, SaleBadge } from '../components/Price';
import { getCategory, getProduct } from '../data/catalog';
import { buildHash } from '../lib/router';
import type { Product } from '../types';

interface ProductPageProps {
  productId: string | undefined;
}

export function ProductPage({ productId }: ProductPageProps) {
  const product = productId ? getProduct(productId) : undefined;
  if (!product) {
    return (
      <div className="container section">
        <div className="empty-state">
          <h1>We couldn't find that shoe</h1>
          <a className="button button--ghost" href={buildHash(['shop'])}>
            Back to the shop
          </a>
        </div>
      </div>
    );
  }
  return <ProductDetail product={product} />;
}

function ProductDetail({ product }: { product: Product }) {
  const { add } = useCart();
  const [size, setSize] = useState<number | null>(null);
  const [color, setColor] = useState<string | null>(null);
  const [errors, setErrors] = useState<{ size?: string; color?: string }>({});
  const [added, setAdded] = useState(false);

  const category = getCategory(product.categoryId);
  const selectedColor = product.colors.find((candidate) => candidate.name === color);
  const artColor = selectedColor?.hex ?? product.colors[0]?.hex ?? '#1f1f24';
  const artAccent = product.colors.find((candidate) => candidate.name !== color)?.hex ?? '#ffffff';

  function handleAdd() {
    const nextErrors: { size?: string; color?: string } = {};
    if (size === null) nextErrors.size = 'Choose a size.';
    if (color === null) nextErrors.color = 'Choose a colour.';
    setErrors(nextErrors);
    if (size === null || color === null) {
      setAdded(false);
      return;
    }
    add({ productId: product.id, size, color });
    setAdded(true);
  }

  return (
    <div className="container section">
      <nav className="breadcrumb" aria-label="Breadcrumb">
        <a href={buildHash(['shop'])}>Shop</a>
        {category && (
          <>
            <span aria-hidden="true"> / </span>
            <a href={buildHash(['shop'], new URLSearchParams({ category: category.id }))}>{category.name}</a>
          </>
        )}
      </nav>

      <div className="product-detail">
        <div className="product-detail__image">
          <ShoeArt categoryId={product.categoryId} color={artColor} accent={artAccent} label={`${product.name} in ${selectedColor?.name ?? product.colors[0]?.name ?? 'default colour'}`} />
        </div>

        <div className="product-detail__info">
          <p className="product-card__category">{category?.name}</p>
          <h1 className="page-title">{product.name}</h1>
          <p className="product-detail__price">
            <Price product={product} />
          </p>
          <SaleBadge product={product} />
          <p>{product.description}</p>

          <fieldset className="option-group">
            <legend>
              Colour{selectedColor ? `: ${selectedColor.name}` : ''}
            </legend>
            <div className="chip-row" role="radiogroup" aria-label="Colour">
              {product.colors.map((option) => (
                <button
                  key={option.name}
                  type="button"
                  role="radio"
                  aria-checked={color === option.name}
                  className={`swatch${color === option.name ? ' is-selected' : ''}`}
                  onClick={() => {
                    setColor(option.name);
                    setErrors((current) => ({ ...current, color: undefined }));
                  }}
                >
                  <span className="swatch__dot" style={{ background: option.hex }} aria-hidden="true" />
                  {option.name}
                </button>
              ))}
            </div>
            {errors.color && <p className="field-error" role="alert">{errors.color}</p>}
          </fieldset>

          <fieldset className="option-group">
            <legend>Size (US){size !== null ? `: ${size}` : ''}</legend>
            <div className="size-grid" role="radiogroup" aria-label="Size">
              {product.sizes.map((option) => (
                <button
                  key={option}
                  type="button"
                  role="radio"
                  aria-checked={size === option}
                  className={`size-button${size === option ? ' is-selected' : ''}`}
                  onClick={() => {
                    setSize(option);
                    setErrors((current) => ({ ...current, size: undefined }));
                  }}
                >
                  {option}
                </button>
              ))}
            </div>
            {errors.size && <p className="field-error" role="alert">{errors.size}</p>}
          </fieldset>

          <div className="product-detail__actions">
            <button type="button" className="button button--primary" onClick={handleAdd}>
              Add to cart
            </button>
            {added && (
              <p className="notice" role="status">
                Added to your cart.{' '}
                <a href={buildHash(['cart'])}>View cart</a>
              </p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
