import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    // Proxy API requests to Laravel backend during development
    // This avoids CORS issues and simplifies cookie handling
    proxy: {
      '/sanctum': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
        // Configure cookie handling
        cookieDomainRewrite: {
          '*': '',
        },
        // Preserve cookies from backend
        onProxyRes: (proxyRes, req, res) => {
          if (proxyRes.headers['set-cookie']) {
            // Remove domain attribute from cookies to allow them to work on localhost
            proxyRes.headers['set-cookie'] = proxyRes.headers['set-cookie'].map(cookie => {
              return cookie
                .split(';')
                .filter(part => !part.trim().toLowerCase().startsWith('domain='))
                .join(';')
                .replace(/;.\s*$/, '');
            });
          }
        },
      },
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
        // Configure cookie handling
        cookieDomainRewrite: {
          '*': '',
        },
        // Preserve cookies from backend
        onProxyRes: (proxyRes, req, res) => {
          if (proxyRes.headers['set-cookie']) {
            // Remove domain attribute from cookies to allow them to work on localhost
            proxyRes.headers['set-cookie'] = proxyRes.headers['set-cookie'].map(cookie => {
              return cookie
                .split(';')
                .filter(part => !part.trim().toLowerCase().startsWith('domain='))
                .join(';')
                .replace(/;.\s*$/, '');
            });
          }
        },
      },
    }
  }
})
