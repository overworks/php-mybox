<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * The request was well-formed but semantically rejected (HTTP 422).
 */
final class UnprocessableEntityException extends ApiException
{
}
