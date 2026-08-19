<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * One page of file search results.
 *
 * @implements Page<FileSearchResult>
 */
final class FileSearchPage implements Page
{
    /** @param list<FileSearchResult> $resources */
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
                static fn (array $item): FileSearchResult => FileSearchResult::fromArray($item),
                Hydrator::objectList($data, 'resources'),
            ),
            nextCursor: Hydrator::nullableString(Hydrator::object($data, 'responseMetaData'), 'nextCursor'),
        );
    }

    /** @return list<FileSearchResult> */
    public function items(): array
    {
        return $this->resources;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
