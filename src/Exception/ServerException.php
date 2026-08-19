<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * MYBOX failed to handle the request (HTTP 5xx other than 507).
 *
 * 502 and 503 are retried automatically by the default retry policy; a
 * 500 surfaces immediately because retrying it is rarely productive.
 */
final class ServerException extends ApiException
{
}
