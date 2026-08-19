<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * Thrown when arguments are rejected locally, before any HTTP request is made.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements MyboxException
{
}
