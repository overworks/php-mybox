<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * Marker interface implemented by every exception this SDK throws.
 *
 * Catching this single interface is enough to isolate all MYBOX failures
 * from the rest of your application's exceptions.
 */
interface MyboxException extends \Throwable
{
}
