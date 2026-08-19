<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Model\Enum\ResourceType;

/**
 * A file or folder as returned by the drive listing and detail endpoints.
 *
 * `fileCount` and `subFolderCount` are only populated when a *folder* is
 * fetched through `GET /v1/drive/resources/{resourceId}`; listings and files
 * leave them null.
 */
final class ResourceItem
{
    public function __construct(
        public readonly string $resourceId,
        public readonly string $name,
        public readonly string $parentId,
        public readonly ResourceType $type,
        public readonly int $size,
        public readonly bool $isFavorite,
        public readonly bool $isHidden,
        public readonly string $lastModifiedBy,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $modifiedAt,
        public readonly \DateTimeImmutable $accessedAt,
        public readonly ?Category $category = null,
        public readonly ?int $fileCount = null,
        public readonly ?int $subFolderCount = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: Hydrator::string($data, 'resourceId'),
            name: Hydrator::string($data, 'name'),
            parentId: Hydrator::string($data, 'parentId'),
            type: Hydrator::enum($data, 'type', ResourceType::class),
            size: Hydrator::int($data, 'size'),
            isFavorite: Hydrator::bool($data, 'isFavorite'),
            isHidden: Hydrator::bool($data, 'isHidden'),
            lastModifiedBy: Hydrator::string($data, 'lastModifiedBy'),
            createdAt: Hydrator::dateTime($data, 'createdAt'),
            modifiedAt: Hydrator::dateTime($data, 'modifiedAt'),
            accessedAt: Hydrator::dateTime($data, 'accessedAt'),
            category: Hydrator::nullableEnum($data, 'category', Category::class),
            fileCount: Hydrator::nullableInt($data, 'fileCount'),
            subFolderCount: Hydrator::nullableInt($data, 'subFolderCount'),
        );
    }

    public function isFolder(): bool
    {
        return $this->type === ResourceType::Folder;
    }

    public function isFile(): bool
    {
        return $this->type === ResourceType::File;
    }
}
