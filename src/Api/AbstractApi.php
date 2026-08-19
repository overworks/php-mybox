<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Api;

use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Http\Transport;

/**
 * Shared plumbing for the endpoint groups.
 */
abstract class AbstractApi
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    /**
     * Asserts that an endpoint documented to return a payload actually did.
     *
     * @param  array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    protected function requireBody(?array $body): array
    {
        if ($body === null) {
            throw new TransportException('MYBOX returned an empty body where a JSON object was expected.');
        }

        return $body;
    }
}
