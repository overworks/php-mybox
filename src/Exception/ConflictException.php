<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * A resource with the same name already exists at the target location (HTTP 409).
 */
final class ConflictException extends ApiException
{
}
