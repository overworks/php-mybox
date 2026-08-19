<?php

/**
 * Removes any probe sandbox left behind in the account.
 *
 * An upload cut mid-flight leaves the resource locked (HTTP 423) until the
 * server gives up on it, which blocks deletion for a while. This retries until
 * the lock clears.
 *
 * Usage: MYBOX_PAT=mbx_pat_xxx php tools/cleanup-sandbox.php
 */

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\MyboxClient;

require __DIR__ . '/../vendor/autoload.php';

$sandboxNames = ['__php_mybox_sdk_probe__', '__php_mybox_sdk_test__'];

$token = getenv('MYBOX_PAT');

if (!is_string($token) || trim($token) === '') {
    fwrite(STDERR, "Set MYBOX_PAT first.\n");

    exit(1);
}

$factory = new HttpFactory();
$mybox = new MyboxClient(
    new ClientConfig($token, retryPolicy: RetryPolicy::none()),
    new GuzzleClient(['http_errors' => false, 'timeout' => 60]),
    $factory,
    $factory,
);

$deadline = time() + 600;
$pending = [];

echo "Scanning the drive root...\n";

foreach ($mybox->drive()->listRootAll() as $item) {
    if (in_array($item->name, $sandboxNames, true)) {
        printf("  found %s (%s)\n", $item->name, $item->resourceId);
        $pending[$item->resourceId] = $item->name;
    }
}

echo "Scanning the trash...\n";

foreach ($mybox->trash()->listAll() as $item) {
    if (in_array($item->name, $sandboxNames, true)) {
        printf("  found in trash: %s (%s)\n", $item->name, $item->resourceId);
        $pending[$item->resourceId] = $item->name;
    }
}

if ($pending === []) {
    echo "\nNothing to clean up — the account is already clear.\n";

    exit(0);
}

echo "\nRemoving (retrying while anything is still locked):\n";

while ($pending !== [] && time() < $deadline) {
    foreach ($pending as $id => $name) {
        try {
            try {
                $mybox->files()->delete($id);
            } catch (ApiException $e) {
                if ($e->status !== 404) {
                    throw $e;
                }
            }

            $mybox->trash()->purge($id);
            printf("  removed %s\n", $name);
            unset($pending[$id]);
        } catch (ApiException $e) {
            printf("  %s: HTTP %d %s — retrying\n", $name, $e->status, (string) $e->errorMessage);
        }
    }

    if ($pending !== []) {
        sleep(15);
    }
}

if ($pending === []) {
    echo "\nAccount is clean.\n";

    exit(0);
}

echo "\nStill present after 10 minutes:\n";

foreach ($pending as $id => $name) {
    printf("  %s (%s) — remove it from the MYBOX web UI\n", $name, $id);
}

exit(1);
