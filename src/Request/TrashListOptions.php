<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Request;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\Enum\SortOrder;
use Minhyung\Mybox\Model\Enum\TrashSortField;

/**
 * Query options for the trash listing, which accepts its own set of sort keys
 * and defaults to `deletedAt,desc`.
 */
final class TrashListOptions
{
    public const MIN_COUNT = 1;
    public const MAX_COUNT = 1000;

    /**
     * @param int|null $count Page size, 1–1000. MYBOX defaults to 100.
     */
    public function __construct(
        public readonly ?TrashSortField $sortBy = null,
        public readonly SortOrder $sortOrder = SortOrder::Desc,
        public readonly ?int $count = null,
        public readonly ?string $cursor = null,
    ) {
        if ($count !== null && ($count < self::MIN_COUNT || $count > self::MAX_COUNT)) {
            throw new InvalidArgumentException(sprintf(
                'count must be between %d and %d, got %d.',
                self::MIN_COUNT,
                self::MAX_COUNT,
                $count,
            ));
        }
    }

    public function withCursor(?string $cursor): self
    {
        return new self($this->sortBy, $this->sortOrder, $this->count, $cursor);
    }

    /** @return array<string, scalar|null> */
    public function toQuery(): array
    {
        return [
            'sort' => $this->sortBy === null ? null : $this->sortBy->value . ',' . $this->sortOrder->value,
            'count' => $this->count,
            'cursor' => $this->cursor,
        ];
    }
}
