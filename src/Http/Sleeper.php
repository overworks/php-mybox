<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Http;

/**
 * Indirection over sleeping so tests can exercise the retry loop without
 * spending wall-clock time.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
