<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The personal access token is missing, malformed, or expired (HTTP 401).
 */
final class UnauthorizedException extends ApiException
{
}
