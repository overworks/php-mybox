<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * Account-wide storage state from `GET /v1/drive/storage`.
 *
 * Both `quotaBytes` and `usedBytes` include capacity shared with other
 * accounts (나눠쓰기) and, for the quota, capacity allotted to mail.
 */
final class StorageInfo
{
    public function __construct(
        public readonly int $quotaBytes,
        public readonly int $usedBytes,
        public readonly int $maxFileBytes,
        public readonly int $trashAutoDeleteDays,
        public readonly FileCounts $fileCounts,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            quotaBytes: Hydrator::int($data, 'quotaBytes'),
            usedBytes: Hydrator::int($data, 'usedBytes'),
            maxFileBytes: Hydrator::int($data, 'maxFileBytes'),
            trashAutoDeleteDays: Hydrator::int($data, 'trashAutoDeleteDays'),
            fileCounts: FileCounts::fromArray(Hydrator::object($data, 'fileCounts')),
        );
    }

    public function freeBytes(): int
    {
        return max(0, $this->quotaBytes - $this->usedBytes);
    }

    /**
     * Fraction of the quota in use, between 0.0 and 1.0.
     */
    public function usedRatio(): float
    {
        return $this->quotaBytes > 0 ? $this->usedBytes / $this->quotaBytes : 0.0;
    }
}
