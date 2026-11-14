import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// base './' keeps asset URLs relative, because the app is served under a path of its own.
// Routes use hashes (#/shop), so the server never needs rewrite rules.
export default defineConfig({
  plugins: [react()],
  base: './',
  server: {
    host: true,
    allowedHosts: true,
  },
  preview: {
    host: true,
    allowedHosts: true,
  },
  test: {
    environment: 'node',
    include: ['src/**/*.test.ts'],
  },
});
