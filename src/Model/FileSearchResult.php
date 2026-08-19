<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

use Minhyung\Mybox\Model\Enum\Category;

/**
 * A file returned by `GET /v1/search/resources/files`.
 *
 * The search index exposes a different projection than the drive listings: it
 * adds the human-readable `path`/`parentPath` but drops flags such as
 * `isFavorite`. MYBOX documents every field as optional, so all of them are
 * nullable here.
 */
final class FileSearchResult
{
    public function __construct(
        public readonly ?string $resourceId,
        public readonly ?string $name,
        public readonly ?string $parentId,
        public readonly ?string $path,
        public readonly ?string $parentPath,
        public readonly ?int $size,
        public readonly ?Category $category,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $modifiedAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: Hydrator::nullableString($data, 'resourceId'),
            name: Hydrator::nullableString($data, 'name'),
            parentId: Hydrator::nullableString($data, 'parentId'),
            path: Hydrator::nullableString($data, 'path'),
            parentPath: Hydrator::nullableString($data, 'parentPath'),
            size: Hydrator::nullableInt($data, 'size'),
            category: Hydrator::nullableEnum($data, 'category', Category::class),
            createdAt: Hydrator::nullableDateTime($data, 'createdAt'),
            modifiedAt: Hydrator::nullableDateTime($data, 'modifiedAt'),
        );
    }
}
