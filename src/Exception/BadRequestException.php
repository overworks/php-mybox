<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The request was malformed or violated a parameter constraint (HTTP 400).
 */
final class BadRequestException extends ApiException
{
}
