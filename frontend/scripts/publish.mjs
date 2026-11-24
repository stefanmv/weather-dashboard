// Copies the static export (frontend/out) into Laravel's public/spa folder.
import { cpSync, existsSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const from = fileURLToPath(new URL('../out', import.meta.url));
const to = fileURLToPath(new URL('../../public/spa', import.meta.url));

if (!existsSync(from)) {
  console.error('No build output in frontend/out. Did `next build` fail?');
  process.exit(1);
}

rmSync(to, { recursive: true, force: true });
cpSync(from, to, { recursive: true });
console.log('Frontend published to public/spa');
