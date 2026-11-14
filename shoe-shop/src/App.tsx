import { useEffect } from 'react';
import { Footer } from './components/Footer';
import { Header } from './components/Header';
import { useRoute } from './lib/useRoute';
import { CartPage } from './pages/CartPage';
import { CheckoutPage } from './pages/CheckoutPage';
import { ConfirmationPage } from './pages/ConfirmationPage';
import { HomePage } from './pages/HomePage';
import { ProductPage } from './pages/ProductPage';
import { ShopPage } from './pages/ShopPage';
import { buildHash } from './lib/router';

export default function App() {
  const route = useRoute();
  const [page, detail] = route.segments;

  // Start each page at the top, except when only the shop filters changed.
  const pageKey = route.segments.join('/');
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'auto' });
  }, [pageKey]);

  let content;
  switch (page) {
    case undefined:
      content = <HomePage />;
      break;
    case 'shop':
      content = <ShopPage query={route.query} />;
      break;
    case 'product':
      content = <ProductPage productId={detail} />;
      break;
    case 'cart':
      content = <CartPage />;
      break;
    case 'checkout':
      content = <CheckoutPage />;
      break;
    case 'order':
      content = <ConfirmationPage orderNumber={detail} />;
      break;
    default:
      content = (
        <div className="container section">
          <div className="empty-state">
            <h1>Page not found</h1>
            <a className="button button--primary" href={buildHash([])}>
              Back to the home page
            </a>
          </div>
        </div>
      );
  }

  return (
    <div className="app">
      <a className="skip-link" href="#main">
        Skip to content
      </a>
      <Header route={route} />
      <main id="main" className="main">
        {content}
      </main>
      <Footer />
    </div>
  );
}
