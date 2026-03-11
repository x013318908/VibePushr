const { test, expect } = require('@playwright/test');
const { getEntrypointPath } = require('./helpers/entrypoint');

async function isVisible(locator) {
  try {
    return await locator.isVisible();
  } catch (_) {
    return false;
  }
}

async function ensureAuthed(page) {
  await page.goto(getEntrypointPath());

  const syncHeading = page.getByRole('heading', { level: 2, name: 'フォルダー同期' });
  if (await isVisible(syncHeading)) return { ok: true };

  const setupHeading = page.getByRole('heading', { level: 2, name: '初回セットアップ' });
  if (await isVisible(setupHeading)) {
    const password = process.env.VP_E2E_PASSWORD || 'playwright-pass-1234';
    await page.locator('#setupForm input[name="password"]').fill(password);
    await page.locator('#setupForm input[name="password_confirm"]').fill(password);
    await page.locator('#setupForm button[type="submit"]').click();
    await expect(syncHeading).toBeVisible();
    return { ok: true };
  }

  const loginHeading = page.getByRole('heading', { level: 2, name: 'ログイン' });
  if (await isVisible(loginHeading)) {
    const password = process.env.VP_E2E_PASSWORD || '';
    if (!password) return { ok: false, reason: 'login_required_set_VP_E2E_PASSWORD' };

    await page.locator('#loginForm input[name="password"]').fill(password);
    await page.locator('#loginForm button[type="submit"]').click();
    await expect(syncHeading).toBeVisible({ timeout: 10000 });
    return { ok: true };
  }

  return { ok: false, reason: 'unknown_screen' };
}

test('entrypoint loads', async ({ page }) => {
  await page.goto(getEntrypointPath());
  await expect(page.getByRole('heading', { level: 1, name: 'VibePushr' })).toBeVisible();
});

test('auth view or app view is visible', async ({ page }) => {
  await page.goto(getEntrypointPath());

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

test('renamed entrypoint supports login, sync, and dry-run', async ({ page }) => {
  const auth = await ensureAuthed(page);
  test.skip(!auth.ok, `E2E skipped: ${auth.reason || 'auth unavailable'}`);

  const fileName = `rename-check-${Date.now()}.txt`;

  await page.locator('#folderInput').setInputFiles([
    { name: fileName, mimeType: 'text/plain', buffer: Buffer.from('rename test') },
  ]);

  await page.locator('#startSync').click();
  await expect(page.locator('#log')).toContainText(`ok: ${fileName}`, { timeout: 20000 });
  await expect(page.locator('#log')).toContainText('sync finished:', { timeout: 20000 });

  await page.locator('#testSync').click();
  await expect(page.locator('#log')).toContainText(`ok: ${fileName}`, { timeout: 20000 });
  await expect(page.locator('#log')).toContainText('dry-run finished:', { timeout: 20000 });
});
