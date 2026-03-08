<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VpFunctionsTest extends TestCase
{
    private string $tmpDirRel;
    private string $vpSource;

    protected function setUp(): void
    {
        $this->tmpDirRel = '.vp_unit_tmp/' . uniqid('case_', true);
        $this->vpSource = (string) file_get_contents(__DIR__ . '/../../public_html/vp.php');
    }

    protected function tearDown(): void
    {
        $base = resolve_safe_path($this->tmpDirRel, false);
        if (!is_string($base) || !is_dir($base)) {
            return;
        }

        $items = scandir($base);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $base . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        @rmdir($base);
    }

    public function testNormalizeRelpathAcceptsSafePath(): void
    {
        $this->assertSame('mailoutput/a.txt', normalize_relpath('mailoutput/a.txt'));
        $this->assertSame('mailoutput/a.txt', normalize_relpath('mailoutput\\a.txt'));
    }

    public function testNormalizeRelpathRejectsDangerousPath(): void
    {
        $this->assertNull(normalize_relpath('/etc/passwd'));
        $this->assertNull(normalize_relpath('../secret.txt'));
        $this->assertNull(normalize_relpath("a\0b"));
    }

    public function testResolveSafePathRejectsTraversal(): void
    {
        $this->assertNull(resolve_safe_path('../outside.txt', true));
    }

    public function testWriteAtomicCreatesAndOverwritesFile(): void
    {
        $relFile = $this->tmpDirRel . '/sample.txt';
        $target = resolve_safe_path($relFile, true);

        $this->assertIsString($target);
        $this->assertNotSame('', $target);

        $created = write_atomic($target, "first\n", 1700000000);
        $this->assertSame('ok', $created);
        $this->assertSame("first\n", (string) file_get_contents($target));

        $overwritten = write_atomic($target, "second\n", 1700000001);
        $this->assertSame('ok', $overwritten);
        $this->assertSame("second\n", (string) file_get_contents($target));
    }

    public function testShouldBlockForIdleReturnsFalseWhenNoLastLogin(): void
    {
        $this->assertFalse(should_block_for_idle(null, 1700000000, 30));
    }

    public function testShouldBlockForIdleReturnsTrueWhenThresholdReached(): void
    {
        $now = 1700000000;
        $days = 30;
        $last = $now - ($days * 86400);
        $this->assertTrue(should_block_for_idle($last, $now, $days));
    }

    public function testShouldBlockForIdleReturnsFalseWhenBelowThreshold(): void
    {
        $now = 1700000000;
        $days = 30;
        $last = $now - ($days * 86400) + 1;
        $this->assertFalse(should_block_for_idle($last, $now, $days));
    }

    public function testClientReadFailureTracksFailedRelpath(): void
    {
        // arrayBuffer() failure can happen client-side (e.g. locked/unreadable file).
        // In that case, catch() must retain relpath for retry.
        $this->assertStringContainsString('throw new Error(`file_read_failed:${reason}`);', $this->vpSource);
        $this->assertStringContainsString('function isClientReadError(error)', $this->vpSource);
        $this->assertStringContainsString('if (trackFailed && !failedRelpaths.includes(relpath)) {', $this->vpSource);
        $this->assertStringContainsString('failedRelpaths.push(relpath);', $this->vpSource);
    }

    public function testRetryUsesCurrentSelectionInsteadOfStaleFileObject(): void
    {
        $this->assertStringContainsString('const byPath = currentFileMap();', $this->vpSource);
        $this->assertStringContainsString('const file = byPath.get(relpath);', $this->vpSource);
        $this->assertStringContainsString('skip: ${relpath} (現在の選択に見つからないため再送不可)', $this->vpSource);
    }

    public function testDryRunPreservesRetryButtonDisabledState(): void
    {
        $this->assertStringContainsString('const previousRetryDisabled = retryFailedBtn.disabled;', $this->vpSource);
        $this->assertStringContainsString('if (!dryRun) {', $this->vpSource);
        $this->assertStringContainsString('retryFailedBtn.disabled = previousRetryDisabled;', $this->vpSource);
    }

    public function testClientReadErrorDoesShortRetryWithLatestSelection(): void
    {
        $this->assertStringContainsString('if (!isClientReadError(firstError)) {', $this->vpSource);
        $this->assertStringContainsString('await sleep(50);', $this->vpSource);
        $this->assertStringContainsString('const latest = currentFileMap().get(relpath);', $this->vpSource);
        $this->assertStringContainsString('syncResult = await sendOne(jobId, activeFile, relpath, { dryRun, force });', $this->vpSource);
    }

    public function testClientReadErrorRequiresSelectionRefreshBeforeNextRun(): void
    {
        $this->assertStringContainsString('let hasClientReadErrorSinceSelection = false;', $this->vpSource);
        $this->assertStringContainsString('async function refreshSelectionIfNeeded()', $this->vpSource);
        $this->assertStringContainsString('return true;', $this->vpSource);
        $this->assertStringContainsString("appendLog('アップロードできないファイルがありました。繰り返し失敗する場合は、フォルダーを選択し直してみてください。', true);", $this->vpSource);
        $this->assertStringContainsString('startSyncBtn.disabled = false;', $this->vpSource);
        $this->assertStringContainsString('folderInput.addEventListener(\'change\', () => {', $this->vpSource);
    }
}
