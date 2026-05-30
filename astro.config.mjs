// @ts-check
import { defineConfig } from 'astro/config';
import tailwindcss from '@tailwindcss/vite';
import markdoc from '@astrojs/markdoc';

// https://astro.build/config
export default defineConfig({
  site: 'https://taufikoji.github.io',
  base: '/astro',
  integrations: [markdoc()],
  output: 'static',
  vite: {
    plugins: [tailwindcss()],
  },
});