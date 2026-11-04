import assert from 'node:assert/strict';

const origin = process.argv[2] || 'http://127.0.0.1:8000';
const development = process.argv.includes('--dev');
async function expect(path, status) {
  const response = await fetch(new URL(path, origin), {
    redirect: 'follow', headers: path.startsWith('/api/') ? { Accept: 'application/json' } : {},
  });
  assert.equal(response.status, status, path);
  // Consume each PHP-server response before opening the next connection.
  return new Response(await response.arrayBuffer(), { status: response.status, headers: response.headers });
}
const html = await (await expect('/app/', 200)).text();
assert.ok(html.includes('id="root"'));
if (!development) {
  for (const path of ['/app', '/app/technical-fallback/path']) {
    assert.equal(await (await expect(path, 200)).text(), html);
  }
  for (const path of ['/app/assets/missing.js', '/app/sw.js', '/app-other']) await expect(path, 404);
}
assert.deepEqual(await (await expect('/api/ping', 200)).json(), { message: 'API works!' });
await expect('/api/v1/me', 401);
await expect('/', 200);
const manifest = await (await expect('/app/manifest.webmanifest', 200)).json();
assert.equal(new URL(manifest.start_url, origin + '/app/manifest.webmanifest').pathname, '/app/');
const worker = await expect('/app/firebase-messaging-sw.js', 200);
assert.ok(/javascript/.test(worker.headers.get('content-type')), 'Worker must be served as JavaScript.');
assert.ok((await worker.text()).includes('builderlog-2ce47'));
const resources = [...html.matchAll(/(?:src|href)="([^"]+)"/g)].map(match => match[1]);
for (const icon of manifest.icons) resources.push(new URL(icon.src, origin + '/app/manifest.webmanifest').pathname);
for (const path of new Set(resources)) {
  const response = await expect(path, 200);
  if (!path.endsWith('.html')) assert.ok(!response.headers.get('content-type')?.includes('text/html'), path);
  if (path.endsWith('.css')) {
    for (const [, url] of (await response.text()).matchAll(/url\(["']?(\/app\/[^\s)'";]+)["']?\)/g)) await expect(url, 200);
  }
}
console.log(`PASS: ${development ? 'Vite dev /app/ and API proxy' : 'Laravel /app/ and scoped fallback'}, API, manifest and ${new Set(resources).size} entry resources at ${origin}.`);
