import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import App from './App.jsx';

describe('landing page', () => {
  it('shows the Welcome Home heading', () => {
    render(<App />);
    const heading = screen.getByRole('heading', { level: 1, name: 'Welcome Home' });
    expect(heading).toBeTruthy();
  });
});
