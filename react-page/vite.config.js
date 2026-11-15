import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// base './' makes asset URLs relative, so the page also works when the
// preview serves it under a path prefix.
export default defineConfig({
  base: './',
  plugins: [react()],
  test: {
    environment: 'node',
  },
});
