import path from 'node:path';
import type { NextConfig } from 'next';

// The frontend is exported as static files and served by Laravel:
// `npm run build` writes them to ../public/spa, and routes/web.php serves
// public/spa/index.html at "/". The browser then calls Laravel's /api on the
// same origin. In `next dev` (port 3000) it calls http://localhost:8000 instead
// (see .env.development).
const nextConfig: NextConfig = {
  output: 'export',
  assetPrefix: process.env.NODE_ENV === 'production' ? '/spa' : undefined,
  images: { unoptimized: true },
  // The Laravel root has its own package-lock.json; this app's root is frontend/.
  outputFileTracingRoot: path.resolve(__dirname),
};

export default nextConfig;
