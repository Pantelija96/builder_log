import { build } from 'vite';
import { fileURLToPath } from 'node:url';

const entry = fileURLToPath(new URL('../src/com/s2d2/radulovic/notifications/firebase-messaging-sw.js', import.meta.url));
const filename = 'firebase-messaging-sw.js';

// Bundle the modular Firebase SDK into a classic worker for installed iOS.
export default function notificationWorkerPlugin() {
  let base;
  let pending;
  const bundle = () => pending ??= build({
    configFile: false,
    logLevel: 'error',
    define: { 'process.env.NODE_ENV': JSON.stringify('production') },
    build: {
      write: false,
      target: 'es2020',
      minify: true,
      lib: { entry, name: 'BuilderLogNotifications', formats: ['iife'], fileName: () => filename },
    },
  }).then(result => {
    const chunks = (Array.isArray(result) ? result : [result]).flatMap(result => result.output);
    if (chunks.length !== 1 || chunks[0].type !== 'chunk') throw new Error('Notification worker must be one self-contained script.');
    return chunks[0].code;
  }).catch(error => { pending = undefined; throw error; });

  return {
    name: 'builderlog-notification-worker',
    configResolved(config) { base = config.base; },
    async generateBundle() {
      this.emitFile({ type: 'asset', fileName: filename, source: await bundle() });
    },
    configureServer(server) {
      const invalidate = () => { pending = undefined; };
      server.watcher.on('change', invalidate);
      server.httpServer?.once('close', () => server.watcher.off('change', invalidate));
      server.middlewares.use(async (req, res, next) => {
        if (req.url?.split('?')[0] !== base + filename) return next();
        try {
          const source = await bundle();
          res.setHeader('Content-Type', 'application/javascript');
          res.setHeader('Cache-Control', 'no-cache');
          res.end(source);
        } catch (error) { next(error); }
      });
    },
  };
}
