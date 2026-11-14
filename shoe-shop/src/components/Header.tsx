import { useEffect, useState } from 'react';
import { useCart } from '../cart/CartContext';
import type { ParsedRoute } from '../lib/router';

interface HeaderProps {
  route: ParsedRoute;
}

const NAV_LINKS = [
  { label: 'Home', href: '#/', section: '' },
  { label: 'Shop', href: '#/shop', section: 'shop' },
];

export function Header({ route }: HeaderProps) {
  const { count } = useCart();
  const [menuOpen, setMenuOpen] = useState(false);
  const current = route.segments[0] ?? '';

  // Close the mobile menu whenever the page changes.
  useEffect(() => {
    setMenuOpen(false);
  }, [route.segments.join('/'), route.query.toString()]);

  return (
    <header className="site-header">
      <div className="container site-header__inner">
        <a className="brand" href="#/" aria-label="Stride home">
          Stride
        </a>

        <button
          type="button"
          className="menu-toggle"
          aria-expanded={menuOpen}
          aria-controls="site-nav"
          aria-label={menuOpen ? 'Close menu' : 'Open menu'}
          onClick={() => setMenuOpen((open) => !open)}
        >
          <span aria-hidden="true" className="menu-toggle__bars" />
        </button>

        <nav id="site-nav" className={`site-nav${menuOpen ? ' is-open' : ''}`} aria-label="Main">
          {NAV_LINKS.map((link) => (
            <a
              key={link.label}
              href={link.href}
              className={`site-nav__link${link.section === current ? ' is-active' : ''}`}
              aria-current={link.section === current ? 'page' : undefined}
            >
              {link.label}
            </a>
          ))}
          <a
            href="#/cart"
            className={`site-nav__link site-nav__cart${current === 'cart' ? ' is-active' : ''}`}
            aria-current={current === 'cart' ? 'page' : undefined}
          >
            Cart
            <span className="cart-badge" aria-label={`${count} items in cart`}>
              {count}
            </span>
          </a>
        </nav>
      </div>
    </header>
  );
}
