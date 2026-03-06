const { test, expect } = require('@playwright/test');

test('vp.php loads', async ({ page }) => {
  await page.goto('/vp.php');
  await expect(page.getByRole('heading', { level: 1, name: 'VibePushr' })).toBeVisible();
});

test('auth view or app view is visible', async ({ page }) => {
  await page.goto('/vp.php');

  const setupHeading = page.getByRole('heading', { level: 2, name: '初回セットアップ' });
  const loginHeading = page.getByRole('heading', { level: 2, name: 'ログイン' });
  const syncHeading = page.getByRole('heading', { level: 2, name: 'フォルダー同期' });

  const visibleCount = [
    await setupHeading.isVisible().catch(() => false),
    await loginHeading.isVisible().catch(() => false),
    await syncHeading.isVisible().catch(() => false),
  ].filter(Boolean).length;

  expect(visibleCount).toBeGreaterThan(0);
});
