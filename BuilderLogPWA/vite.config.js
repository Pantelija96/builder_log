import { defineConfig, loadEnv } from 'vite';
import { fileURLToPath } from 'node:url';
import { lstatSync, realpathSync } from 'node:fs';
import { resolve } from 'node:path';
import notificationWorkerPlugin from './scripts/notification-worker.js';

const backend = fileURLToPath(new URL('../', import.meta.url));
const publicDirectory = resolve(backend, 'public');
const outputDirectory = resolve(publicDirectory, 'app');

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, fileURLToPath(new URL('.', import.meta.url)), '');
  return {
    base: '/app/',
    server: {
      proxy: {
        '/api': { target: env.PWA_DEV_API_TARGET || 'http://127.0.0.1:8000', changeOrigin: true },
      },
    },
    build: {
      outDir: outputDirectory,
      emptyOutDir: true,
    },
    plugins: [
      notificationWorkerPlugin(),
      {
        name: 'protect-laravel-public-directory',
        configResolved(config) {
          if (command !== 'build') return;
          const output = lstatSync(outputDirectory, { throwIfNoEntry: false });
          if (resolve(config.build.outDir) !== outputDirectory
            || realpathSync(publicDirectory) !== resolve(realpathSync(backend), 'public')
            || (output && (output.isSymbolicLink()
              || realpathSync(outputDirectory) !== outputDirectory))) {
            throw new Error('PWA build may only empty Laravel public/app; links and output overrides are refused.');
          }
        },
      },
    ],
  };
});
