import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import checker from 'vite-plugin-checker';
import tsconfigPaths from 'vite-tsconfig-paths';
import { resolve } from 'path';

// https://vitejs.dev/config/
export default defineConfig({
  base: '/build/',
  plugins: [
    react(),
    checker({
      typescript: true,
    }),
    tsconfigPaths(),
  ],
  // Specify the entry point
  build: {
    outDir: resolve(__dirname, 'public', 'build'),
    copyPublicDir: false,
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'assets/index.html'),
      },
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]',
      },
    },
  },
  // Allow the dev server to find the files
  server: {
    port: 3000,
    strictPort: true,
    origin: 'http://localhost:3000',
  },
  // Set up aliases for cleaner imports
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/js'),
      '@styles': resolve(__dirname, 'assets/styles'),
      '@images': resolve(__dirname, 'assets/images'),
    },
  },
});
