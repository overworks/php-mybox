<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * One page of the trash listing (`GET /v1/drive/trash`).
 *
 * @implements Page<TrashedResourceItem>
 */
final class TrashList implements Page
{
    /** @param list<TrashedResourceItem> $resources */
    public function __construct(
        public readonly array $resources,
        public readonly int $fileCount,
        public readonly int $subFolderCount,
        public readonly ?string $nextCursor = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resources: array_map(
                static fn (array $item): TrashedResourceItem => TrashedResourceItem::fromArray($item),
                Hydrator::objectList($data, 'resources'),
            ),
            fileCount: Hydrator::int($data, 'fileCount'),
            subFolderCount: Hydrator::int($data, 'subFolderCount'),
            nextCursor: Hydrator::nullableString(Hydrator::object($data, 'responseMetaData'), 'nextCursor'),
        );
    }

    /** @return list<TrashedResourceItem> */
    public function items(): array
    {
        return $this->resources;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
