<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * One page of folder search results.
 *
 * @implements Page<FolderSearchResult>
 */
final class FolderSearchPage implements Page
{
    /** @param list<FolderSearchResult> $resources */
    public function __construct(
        public readonly array $resources,
        public readonly ?string $nextCursor = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resources: array_map(
                static fn (array $item): FolderSearchResult => FolderSearchResult::fromArray($item),
                Hydrator::objectList($data, 'resources'),
            ),
            nextCursor: Hydrator::nullableString(Hydrator::object($data, 'responseMetaData'), 'nextCursor'),
        );
    }

    /** @return list<FolderSearchResult> */
    public function items(): array
    {
        return $this->resources;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
