<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * One page of a drive listing (`GET /v1/drive/resources` or
 * `GET /v1/drive/folders/{folderId}/resources`).
 *
 * The two counts describe the *whole* folder, not this page, and cover only
 * direct children — resources nested deeper are not included.
 *
 * @implements Page<ResourceItem>
 */
final class ResourceList implements Page
{
    /** @param list<ResourceItem> $resources */
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
                static fn (array $item): ResourceItem => ResourceItem::fromArray($item),
                Hydrator::objectList($data, 'resources'),
            ),
            fileCount: Hydrator::int($data, 'fileCount'),
            subFolderCount: Hydrator::int($data, 'subFolderCount'),
            nextCursor: Hydrator::nullableString(Hydrator::object($data, 'responseMetaData'), 'nextCursor'),
        );
    }

    /** @return list<ResourceItem> */
    public function items(): array
    {
        return $this->resources;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
