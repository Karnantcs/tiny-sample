import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import App from './App.jsx';

describe('landing page', () => {
  it('shows the Hello World heading', () => {
    render(<App />);
    const heading = screen.getByRole('heading', { level: 1, name: 'Hello World' });
    expect(heading).toBeTruthy();
  });
});
