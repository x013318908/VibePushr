const { defineConfig, devices } = require('@playwright/test');

const entrypoint = (process.env.VP_ENTRYPOINT || 'vp.php').replace(/^\/+/, '') || 'vp.php';
const baseUrl = `http://127.0.0.1:8787/${entrypoint}`;
const prepareEntrypoint = entrypoint === 'vp.php'
  ? ':'
  : `cp -f ../public_html/vp.php ../public_html/${entrypoint}`;

module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://127.0.0.1:8787',
    trace: 'on-first-retry',
  },
  webServer: {
    command: `${prepareEntrypoint} && php -S 127.0.0.1:8787 -t ../public_html`,
    url: baseUrl,
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
