<?php

/**
 * Drives the SDK's own upload and download helpers against the live service,
 * proving the transfer layer works through the public API rather than through
 * hand-rolled requests.
 *
 * Usage: MYBOX_PAT=mbx_pat_xxx php tools/verify-sdk.php
 */

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\MyboxClient;

require __DIR__ . '/../vendor/autoload.php';

const SANDBOX = '__php_mybox_sdk_probe__';

$token = getenv('MYBOX_PAT');

if (!is_string($token) || trim($token) === '') {
    fwrite(STDERR, "Set MYBOX_PAT first.\n");

    exit(1);
}

$factory = new HttpFactory();
$mybox = new MyboxClient(
    new ClientConfig($token),
    new GuzzleClient(['http_errors' => false, 'timeout' => 180]),
    $factory,
    $factory,
);

$checks = 0;
$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $checks, $failures;

    ++$checks;

    if (!$ok) {
        ++$failures;
    }

    printf("  [%s] %-48s %s\n", $ok ? ' ok ' : 'FAIL', $label, $detail);
}

$folderId = $mybox->files()->createFolder(SANDBOX)->resourceId;

try {
    echo "=== Uploader ===\n";

    $small = 'php-mybox sdk verification ' . bin2hex(random_bytes(8));
    $result = $mybox->upload()->fromString($small, 'from-string.txt', parentId: $folderId);
    check('fromString', $result->resourceId !== '', $result->resourceId);
    check('  reported size matches', $result->fileSize === strlen($small), sprintf('%d bytes', $result->fileSize));

    $tmp = tempnam(sys_get_temp_dir(), 'mybox') ?: '/tmp/mybox-verify';
    $bytes = random_bytes(1_500_000);
    file_put_contents($tmp, $bytes);
    $fromFile = $mybox->upload()->fromFile($tmp, parentId: $folderId, fileName: 'from-file.bin');
    check('fromFile (1.5 MB)', $fromFile->fileSize === strlen($bytes), sprintf('%d bytes', $fromFile->fileSize));

    $korean = '한글 내용 테스트';
    $unicode = $mybox->upload()->fromString($korean, '한글 파일명.txt', parentId: $folderId);
    check('unicode file name and content', $unicode->name === '한글 파일명.txt', $unicode->name);

    echo "\n=== Downloader ===\n";

    check('contents() round trip', $mybox->download()->contents($result->resourceId) === $small);
    check('unicode round trip', $mybox->download()->contents($unicode->resourceId) === $korean);

    $out = tempnam(sys_get_temp_dir(), 'mybox') ?: '/tmp/mybox-out';
    $written = $mybox->download()->toFile($fromFile->resourceId, $out);
    check('toFile() byte count', $written === strlen($bytes), sprintf('%d bytes', $written));
    check('toFile() content identical', file_get_contents($out) === $bytes);

    echo "\n=== Overwrite ===\n";
    $replacement = 'replaced ' . bin2hex(random_bytes(4));
    $mybox->upload()->fromString($replacement, 'from-string.txt', parentId: $folderId, isOverwrite: true);
    $listed = [];

    foreach ($mybox->drive()->listFolderAll($folderId) as $item) {
        $listed[$item->name] = $item->resourceId;
    }

    check('overwrite kept one file', count(array_filter(array_keys($listed), static fn (string $n): bool => $n === 'from-string.txt')) === 1);
    check('overwritten content served', $mybox->download()->contents($listed['from-string.txt']) === $replacement);

    echo "\n=== Paths, search and listing ===\n";
    check('folder path resolves', $mybox->paths()->folderId('/' . SANDBOX) === $folderId);
    check('file path resolves', $mybox->paths()->fileId('/' . SANDBOX . '/from-file.bin') === $fromFile->resourceId);

    $detail = $mybox->drive()->get($folderId);
    check('folder detail reports counts', $detail->fileCount === 3, sprintf('fileCount=%s', var_export($detail->fileCount, true)));

    echo "\n=== Storage accounting ===\n";
    $storage = $mybox->drive()->storage();
    check('usedBytes advanced', $storage->usedBytes > 0, sprintf('%d bytes used', $storage->usedBytes));

    unlink($tmp);
    unlink($out);
} finally {
    echo "\nCleanup: ";

    try {
        $mybox->files()->delete($folderId);
        $mybox->trash()->purge($folderId);
        echo "sandbox removed.\n";
    } catch (Throwable $e) {
        echo 'FAILED — remove "', SANDBOX, "\" by hand: ", $e->getMessage(), "\n";
    }
}

printf("\n%d checks, %d failures\n", $checks, $failures);

exit($failures === 0 ? 0 : 1);
