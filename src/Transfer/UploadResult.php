<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Model\UploadTicket;

/**
 * The stored file, as reported by the storage host once the bytes are in.
 *
 * A successful upload answers `{"resourceId": …, "name": …, "fileSize": …}`.
 * The raw and decoded bodies are kept alongside the parsed fields because that
 * response is not part of the published documentation and may grow.
 */
final class UploadResult
{
    /** @param array<string, mixed>|null $decodedBody */
    public function __construct(
        public readonly string $resourceId,
        public readonly string $name,
        public readonly int $fileSize,
        public readonly UploadTicket $ticket,
        public readonly int $status,
        public readonly string $rawBody,
        public readonly ?array $decodedBody = null,
        public readonly int $bytesSent = 0,
    ) {
    }

    /**
     * Whether this upload continued an earlier, interrupted one.
     */
    public function wasResumed(): bool
    {
        return $this->ticket->offset > 0;
    }
}
