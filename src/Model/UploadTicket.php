<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * A short-lived destination for file bytes, from `POST /v1/drive/files`.
 *
 * `offset` is non-zero only when the ticket was requested with `resume: true`
 * and MYBOX already holds part of the file; send the remaining bytes starting
 * at that position.
 */
final class UploadTicket
{
    public function __construct(
        public readonly string $uploadUrl,
        public readonly int $offset = 0,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uploadUrl: Hydrator::string($data, 'uploadUrl'),
            offset: Hydrator::nullableInt($data, 'offset') ?? 0,
        );
    }

    /**
     * Whether MYBOX reported partial content already stored for this file.
     */
    public function isResumed(): bool
    {
        return $this->offset > 0;
    }
}
