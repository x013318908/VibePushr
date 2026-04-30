<?php

declare(strict_types=1);

define('VP_UNIT_TEST_MODE', true);
require __DIR__ . '/../../public_html/vp.php';

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function assertTrue(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            return;
        }

        $this->failed++;
        fwrite(STDERR, "[FAIL] {$message}\n");
    }

    public function assertSame($expected, $actual, string $message): void
    {
        $this->assertTrue($expected === $actual, $message . " (expected=" . var_export($expected, true) . ", actual=" . var_export($actual, true) . ")");
    }

    public function summary(): int
    {
        fwrite(STDOUT, "[RESULT] passed={$this->passed}, failed={$this->failed}\n");
        return $this->failed === 0 ? 0 : 1;
    }
}

$t = new TestRunner();

// normalize_relpath
$t->assertSame('a/b/c.txt', normalize_relpath('a/b/c.txt'), 'normalize keeps simple path');
$t->assertSame('a/b/c.txt', normalize_relpath('a\\b\\c.txt'), 'normalize converts backslash');
$t->assertSame(null, normalize_relpath('/etc/passwd'), 'normalize rejects absolute path');
$t->assertSame(null, normalize_relpath('../secret.txt'), 'normalize rejects traversal');
$t->assertSame(null, normalize_relpath("a\0b"), 'normalize rejects null byte');

// resolve_safe_path
$safe = resolve_safe_path('mailoutput/unit-test.txt', true);
$t->assertTrue(is_string($safe) && $safe !== '', 'resolve_safe_path returns path for safe relpath');
$t->assertSame(null, resolve_safe_path('../outside.txt', true), 'resolve_safe_path rejects traversal path');

// write_atomic create + overwrite
$tmpDirRel = 'mailoutput/.vp_unit_tmp';
$tmpFileRel = $tmpDirRel . '/sample.txt';
$tmpFile = resolve_safe_path($tmpFileRel, true);
$t->assertTrue(is_string($tmpFile) && $tmpFile !== '', 'temporary test file path resolved');

if (is_string($tmpFile)) {
    $created = write_atomic($tmpFile, "first\n", 1700000000);
    $t->assertSame('ok', $created, 'write_atomic creates file');
    $t->assertSame("first\n", (string) @file_get_contents($tmpFile), 'write_atomic stores first content');

    $overwritten = write_atomic($tmpFile, "second\n", 1700000001);
    $t->assertSame('ok', $overwritten, 'write_atomic overwrites existing file');
    $t->assertSame("second\n", (string) @file_get_contents($tmpFile), 'write_atomic stores overwritten content');
}

// cleanup
if (is_string($tmpFile) && is_file($tmpFile)) {
    @unlink($tmpFile);
}
$tmpDir = resolve_safe_path($tmpDirRel, false);
if (is_string($tmpDir) && is_dir($tmpDir)) {
    @rmdir($tmpDir);
}

exit($t->summary());

