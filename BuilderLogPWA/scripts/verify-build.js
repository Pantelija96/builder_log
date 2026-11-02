import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const output = fileURLToPath(new URL('../../public/app/', import.meta.url));
const html = readFileSync(join(output, 'index.html'), 'utf8');
const origin = 'https://example.invalid';
const manifestUrl = origin + '/app/manifest.webmanifest';
const manifest = JSON.parse(readFileSync(join(output, 'manifest.webmanifest'), 'utf8'));
assert.equal(new URL(manifest.start_url, manifestUrl).pathname, '/app/');
assert.equal(new URL(manifest.scope, manifestUrl).pathname, '/app/');
for (const icon of manifest.icons) {
  const url = new URL(icon.src, manifestUrl);
  assert.ok(url.pathname.startsWith('/app/'));
  assert.ok(existsSync(join(output, url.pathname.slice('/app/'.length))));
}
for (const [, url] of html.matchAll(/(?:src|href)="([^"]+)"/g)) {
  assert.ok(url.startsWith('/app/'), `Unexpected built HTML asset: ${url}`);
  assert.ok(existsSync(join(output, url.slice('/app/'.length))), `Missing build asset: ${url}`);
}
for (const file of readdirSync(join(output, 'assets')).filter(name => /\.(js|css)$/.test(name))) {
  const content = readFileSync(join(output, 'assets', file), 'utf8');
  assert.ok(!/["'`(]\/assets\//.test(content), `Unprefixed asset in ${file}`);
}
assert.ok(!existsSync(join(output, 'sw.js')), 'No service worker belongs to this integration.');
assert.ok(!html.includes('/src/'));
console.log('PASS: /app/ HTML, bundled assets, manifest and icons; no service worker.');
