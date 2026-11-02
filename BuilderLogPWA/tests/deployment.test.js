import assert from 'node:assert/strict';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { resolveConfig } from 'vite';

const root = fileURLToPath(new URL('../', import.meta.url));
const configFile = fileURLToPath(new URL('../vite.config.js', import.meta.url));
const output = fileURLToPath(new URL('../../public/app', import.meta.url));

test('production output is exclusively Laravel public/app', async () => {
  const config = await resolveConfig({ root, configFile, logLevel: 'silent' }, 'build');
  assert.equal(config.base, '/app/');
  assert.equal(config.build.outDir, output);
  assert.equal(config.build.emptyOutDir, true);
});

test('configuration refuses public/ and other output overrides before any build runs', async () => {
  for (const target of ['../../public', '../../', '../../../']) {
    const outDir = fileURLToPath(new URL(target, import.meta.url));
    await assert.rejects(resolveConfig({ root, configFile, logLevel: 'silent', build: { outDir } }, 'build'),
      /PWA build may only empty Laravel public\/app/);
  }
});
