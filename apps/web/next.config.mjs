import withPWAInit from 'next-pwa';
import runtimeCaching from 'next-pwa/cache.js';

const isDev =
  (globalThis?.process?.env?.NODE_ENV ?? 'production') === 'development';

const withPWA = withPWAInit({
  dest: 'public',
  register: true,
  skipWaiting: true,
  disable: isDev,
  runtimeCaching,
  buildExcludes: [/middleware-manifest\.json$/],
});

/** @type {import('next').NextConfig} */
const nextConfig = {
  transpilePackages: ['@church/ui', '@church/utils', '@church/contracts'],
};

export default withPWA(nextConfig);
