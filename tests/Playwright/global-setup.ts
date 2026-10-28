import { spawnSync } from 'node:child_process';

const PROJECT_ROOT = new URL('../../', import.meta.url).pathname;

/**
 * The shop limits sign-ups and guest accounts per address and per caller, and every run
 * signs up from the same address: a second run within the hour would be refused. Empty the
 * rate limiter pool of the shop the specs drive before they start.
 */
export default function globalSetup(): void {
  const result = spawnSync(
    'ddev',
    ['exec', 'php', 'bin/console', 'cache:pool:clear', 'cache.rate_limiter', '--env=dev'],
    { cwd: PROJECT_ROOT, encoding: 'utf8' },
  );

  if (0 !== result.status) {
    throw new Error(`Could not empty the rate limiter pool: ${result.stderr || result.stdout}`);
  }
}
