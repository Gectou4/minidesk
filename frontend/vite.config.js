import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// L'API est sur la même origine que le front (nginx), donc pas de CORS en prod.
// En dev, le serveur Vite relaie les appels à l'API vers API_TARGET.
const target = process.env.API_TARGET || 'http://localhost:8080';

export default defineConfig({
  base: '/ui/',
  plugins: [react()],
  server: {
    port: 5173,
    proxy: { '/tickets': target },
  },
});
