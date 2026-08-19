<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The token is valid but the account may not perform this operation (HTTP 403).
 */
final class ForbiddenException extends ApiException
{
}
