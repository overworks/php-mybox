<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Http;

/**
 * Default {@see Sleeper}, backed by `usleep()`.
 */
final class SystemSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int) round($seconds * 1_000_000));
        }
    }
}
