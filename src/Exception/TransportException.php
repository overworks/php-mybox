<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Exception;

/**
 * Thrown when the request never produced a usable HTTP response: connection
 * failures, DNS errors, timeouts, or a response body that is not valid JSON.
 */
final class TransportException extends \RuntimeException implements MyboxException
{
}
