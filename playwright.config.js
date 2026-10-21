import { defineConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const temporaryDirectory = fileURLToPath(new URL('./node_modules/.cache/playwright-temp/', import.meta.url));
mkdirSync(temporaryDirectory, { recursive: true });
process.env.TEMP = temporaryDirectory;
process.env.TMP = temporaryDirectory;
process.env.TMPDIR = temporaryDirectory;

export default defineConfig({
  testDir: './tests',
  testMatch: '*.spec.js',
  timeout: 45000,
  expect: { timeout: 7000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  outputDir: process.env.PLAYWRIGHT_OUTPUT_DIR || './node_modules/.cache/playwright-results',
  reporter: process.env.PLAYWRIGHT_JSON_OUTPUT_FILE
    ? [['list'], ['json', { outputFile: process.env.PLAYWRIGHT_JSON_OUTPUT_FILE }]]
    : 'list',
  use: {
    browserName: 'chromium',
    channel: process.env.PLAYWRIGHT_CHANNEL || (process.platform === 'win32' ? 'chrome' : undefined),
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:18090',
    viewport: { width: 1280, height: 900 },
    reducedMotion: 'reduce',
    serviceWorkers: 'block',
    screenshot: 'only-on-failure',
    trace: 'off',
  },
});
