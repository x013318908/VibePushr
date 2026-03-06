<?php
declare(strict_types=1);

$target = $argv[1] ?? '';
if ($target === '') {
    fwrite(STDERR, "missing target path\n");
    exit(2);
}

$fp = @fopen($target, 'c+b');
if ($fp === false) {
    fwrite(STDERR, "fopen failed: {$target}\n");
    exit(3);
}

if (!@flock($fp, LOCK_EX)) {
    fwrite(STDERR, "flock failed: {$target}\n");
    @fclose($fp);
    exit(4);
}

fwrite(STDOUT, "LOCKED\n");
fflush(STDOUT);

while (true) {
    usleep(200000);
}
