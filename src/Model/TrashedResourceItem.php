<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Model\Enum\ResourceType;

/**
 * A file or folder sitting in the trash.
 *
 * Compared with {@see ResourceItem} this carries `deletedAt` and omits the
 * favourite/hidden flags, which MYBOX does not report for trashed resources.
 */
final class TrashedResourceItem
{
    public function __construct(
        public readonly string $resourceId,
        public readonly string $name,
        public readonly string $parentId,
        public readonly ResourceType $type,
        public readonly int $size,
        public readonly string $lastModifiedBy,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $modifiedAt,
        public readonly \DateTimeImmutable $accessedAt,
        public readonly \DateTimeImmutable $deletedAt,
        public readonly ?Category $category = null,
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
            lastModifiedBy: Hydrator::string($data, 'lastModifiedBy'),
            createdAt: Hydrator::dateTime($data, 'createdAt'),
            modifiedAt: Hydrator::dateTime($data, 'modifiedAt'),
            accessedAt: Hydrator::dateTime($data, 'accessedAt'),
            deletedAt: Hydrator::dateTime($data, 'deletedAt'),
            category: Hydrator::nullableEnum($data, 'category', Category::class),
        );
    }

    public function isFolder(): bool
    {
        return $this->type === ResourceType::Folder;
    }
}
