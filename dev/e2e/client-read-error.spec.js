const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

test.describe.configure({ mode: 'serial' });

async function isVisible(locator) {
  try {
    return await locator.isVisible();
  } catch (_) {
    return false;
  }
}

async function ensureAuthed(page) {
  await page.goto('/vp.php');

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

    let settled = 'timeout';
    await Promise.race([
      syncHeading.waitFor({ state: 'visible', timeout: 7000 }).then(() => { settled = 'sync'; }).catch(() => {}),
      page.locator('#loginError').waitFor({ state: 'visible', timeout: 7000 }).then(() => { settled = 'login_error'; }).catch(() => {}),
      page.waitForTimeout(7000),
    ]);

    if (settled === 'sync') return { ok: true };
    if (settled === 'login_error') return { ok: false, reason: 'login_failed_check_VP_E2E_PASSWORD' };
    return { ok: false, reason: 'login_timeout_after_submit' };
  }

  return { ok: false, reason: 'unknown_screen' };
}

async function setupReadBlock(page, target) {
  await page.evaluate((name) => {
    if (!window.__vpOriginalArrayBuffer) {
      window.__vpOriginalArrayBuffer = File.prototype.arrayBuffer;
      File.prototype.arrayBuffer = async function patchedArrayBuffer() {
        const cfg = window.__vpReadBlock;
        if (cfg && cfg.enabled && this.name === cfg.target) {
          throw new DOMException('simulated lock', 'NotReadableError');
        }
        return window.__vpOriginalArrayBuffer.call(this);
      };
    }

    window.__vpReadBlock = { enabled: true, target: name };

    const input = document.getElementById('folderInput');
    input.removeAttribute('webkitdirectory');
    input.removeAttribute('directory');
  }, target);
}

async function setReadBlockEnabled(page, enabled) {
  await page.evaluate((value) => {
    if (!window.__vpReadBlock) window.__vpReadBlock = { enabled: false, target: '' };
    window.__vpReadBlock.enabled = value;
  }, enabled);
}

async function setSingleFile(page, fileName, body = 'abc') {
  await page.locator('#folderInput').setInputFiles([
    { name: fileName, mimeType: 'text/plain', buffer: Buffer.from(body) },
  ]);
}

async function clearLog(page) {
  await page.evaluate(() => {
    document.getElementById('log').textContent = '';
  });
}

async function runAndExpectFail(page, buttonSelector, fileName) {
  await page.locator(buttonSelector).click();
  await expect(page.locator('#log')).toContainText(`fail: ${fileName}`, { timeout: 20000 });
}

async function runAndExpectOk(page, buttonSelector, fileName, finishedLabel) {
  await page.locator(buttonSelector).click();
  await expect(page.locator('#log')).toContainText(`ok: ${fileName}`, { timeout: 20000 });
  await expect(page.locator('#log')).toContainText(finishedLabel, { timeout: 20000 });
  await expect(page.locator('#log')).not.toContainText(`fail: ${fileName}`);
}

function startFlockLocker(targetPath) {
  const helper = path.join(__dirname, 'helpers', 'hold_flock.php');
  const child = spawn('php', [helper, targetPath], { stdio: ['ignore', 'pipe', 'pipe'] });

  return new Promise((resolve, reject) => {
    let stdout = '';
    let stderr = '';
    const timer = setTimeout(() => {
      child.kill();
      reject(new Error(`locker timeout. stderr=${stderr.trim()}`));
    }, 10000);

    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
      if (stdout.includes('LOCKED')) {
        clearTimeout(timer);
        resolve(child);
      }
    });

    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
    });

    child.on('exit', (code) => {
      if (!stdout.includes('LOCKED')) {
        clearTimeout(timer);
        reject(new Error(`locker exited before lock. code=${code}, stderr=${stderr.trim()}`));
      }
    });
  });
}

async function stopLocker(child) {
  if (!child || child.killed) return;
  child.kill();
  await new Promise((resolve) => child.once('exit', resolve));
}

test('recovers on retry after transient client-side unreadable file error', async ({ page }) => {
  const auth = await ensureAuthed(page);
  test.skip(!auth.ok, `E2E skipped: ${auth.reason || 'auth unavailable'}`);

  const stamp = Date.now();
  const transientName = `locked-${stamp}.txt`;
  const stableName = `normal-${stamp}.txt`;

  await setupReadBlock(page, transientName);
  await page.locator('#folderInput').setInputFiles([
    { name: transientName, mimeType: 'text/plain', buffer: Buffer.from('transient') },
    { name: stableName, mimeType: 'text/plain', buffer: Buffer.from('normal') },
  ]);

  await runAndExpectFail(page, '#startSync', transientName);
  await expect(page.locator('#retryFailed')).toBeEnabled();

  await setReadBlockEnabled(page, false);
  await page.locator('#retryFailed').click();
  await expect(page.locator('#log')).toContainText(`ok: ${transientName}`, { timeout: 20000 });
  await expect(page.locator('#log')).toContainText('sync finished:', { timeout: 20000 });
});

test('after unblocking, start sync again should not keep failing with same file_read_failed', async ({ page }) => {
  const auth = await ensureAuthed(page);
  test.skip(!auth.ok, `E2E skipped: ${auth.reason || 'auth unavailable'}`);

  const transientName = `start-again-${Date.now()}.txt`;
  await setupReadBlock(page, transientName);
  await setSingleFile(page, transientName);

  await runAndExpectFail(page, '#startSync', transientName);
  await setReadBlockEnabled(page, false);
  await clearLog(page);
  await setSingleFile(page, transientName);
  await runAndExpectOk(page, '#startSync', transientName, 'sync finished:');
});

test('after unblocking, test sync again should not keep failing with same file_read_failed', async ({ page }) => {
  const auth = await ensureAuthed(page);
  test.skip(!auth.ok, `E2E skipped: ${auth.reason || 'auth unavailable'}`);

  const transientName = `dryrun-again-${Date.now()}.txt`;
  await setupReadBlock(page, transientName);
  await setSingleFile(page, transientName);

  await runAndExpectFail(page, '#testSync', transientName);
  await setReadBlockEnabled(page, false);
  await clearLog(page);
  await setSingleFile(page, transientName);
  await runAndExpectOk(page, '#testSync', transientName, 'dry-run finished:');
});

test('fopen/flock lock then unlock: start sync should fail first and succeed after unlock', async ({ page }) => {
  const auth = await ensureAuthed(page);
  test.skip(!auth.ok, `E2E skipped: ${auth.reason || 'auth unavailable'}`);

  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'vp-flock-'));
  const fileName = `flock-${Date.now()}.txt`;
  const targetPath = path.join(tmpDir, fileName);
  fs.writeFileSync(targetPath, 'locked by flock test', 'utf8');

  let locker;
  try {
    locker = await startFlockLocker(targetPath);

    await page.evaluate(() => {
      const input = document.getElementById('folderInput');
      input.removeAttribute('webkitdirectory');
      input.removeAttribute('directory');
    });
    await page.locator('#folderInput').setInputFiles(targetPath);

    await page.locator('#startSync').click();
    await expect(page.locator('#log')).toContainText('sync finished:', { timeout: 20000 });
    const firstLog = (await page.locator('#log').textContent()) || '';
    if (!firstLog.includes(`fail: ${fileName}`)) {
      test.skip(true, 'Environment did not reproduce read failure with advisory flock lock.');
    }
  } finally {
    await stopLocker(locker);
  }

  await clearLog(page);
  await page.locator('#folderInput').setInputFiles([]);
  await page.locator('#folderInput').setInputFiles(targetPath);
  await page.locator('#startSync').click();
  await expect(page.locator('#log')).toContainText(`ok: ${fileName}`, { timeout: 20000 });
  await expect(page.locator('#log')).toContainText('sync finished:', { timeout: 20000 });
});
