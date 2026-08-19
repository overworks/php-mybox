<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The account has run out of storage quota (HTTP 507).
 */
final class InsufficientStorageException extends ApiException
{
}
