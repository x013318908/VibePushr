<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VpFunctionsTest extends TestCase
{
    private string $tmpDirRel;

    protected function setUp(): void
    {
        $this->tmpDirRel = '.vp_unit_tmp/' . uniqid('case_', true);
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
        $this->assertTrue($created);
        $this->assertSame("first\n", (string) file_get_contents($target));

        $overwritten = write_atomic($target, "second\n", 1700000001);
        $this->assertTrue($overwritten);
        $this->assertSame("second\n", (string) file_get_contents($target));
    }
}
