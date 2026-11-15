import { describe, it, expect } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';
import App, { GREETING } from './App.jsx';

describe('App', () => {
  it('renders the greeting heading', () => {
    const html = renderToStaticMarkup(<App />);
    expect(html).toContain(`<h1>${GREETING}</h1>`);
  });

  it('starts with zero clicks', () => {
    const html = renderToStaticMarkup(<App />);
    expect(html).toContain('Clicked 0 times');
  });
});
