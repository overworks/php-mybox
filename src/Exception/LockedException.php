<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The resource is locked and cannot be modified right now (HTTP 423).
 */
final class LockedException extends ApiException
{
}
