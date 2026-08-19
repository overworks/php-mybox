<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * A single-use download URL from `GET /v1/drive/files/{fileId}/download`.
 *
 * MYBOX documents the URL as valid for one download within ten minutes; it
 * carries its own credentials, so do not attach the personal access token when
 * fetching it.
 */
final class DownloadTicket
{
    public function __construct(
        public readonly string $downloadUrl,
        public readonly int $expiresIn,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            downloadUrl: Hydrator::string($data, 'downloadUrl'),
            expiresIn: Hydrator::nullableInt($data, 'expiresIn') ?? 0,
        );
    }
}
