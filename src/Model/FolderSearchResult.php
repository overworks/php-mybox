<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * A folder returned by `GET /v1/search/resources/folders`.
 *
 * As with {@see FileSearchResult}, MYBOX documents every field as optional.
 */
final class FolderSearchResult
{
    public function __construct(
        public readonly ?string $resourceId,
        public readonly ?string $name,
        public readonly ?string $parentId,
        public readonly ?string $path,
        public readonly ?string $parentPath,
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
            createdAt: Hydrator::nullableDateTime($data, 'createdAt'),
            modifiedAt: Hydrator::nullableDateTime($data, 'modifiedAt'),
        );
    }
}
