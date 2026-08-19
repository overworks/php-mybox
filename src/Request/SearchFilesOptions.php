<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Request;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Model\Enum\DateField;

/**
 * Query options for `GET /v1/search/resources/files`.
 *
 * MYBOX requires at least one of keyword, category, or a date bound; asking
 * for everything is not a supported query. That rule is enforced here so the
 * call fails before it consumes one of the tight search quota slots
 * (10–30 requests per minute depending on plan).
 */
final class SearchFilesOptions
{
    public const MIN_COUNT = 20;
    public const MAX_COUNT = 200;

    /**
     * @param string|null $q          Keyword. Whitespace-separated terms and the file extension
     *                                are combined with AND, e.g. `1월 회의록 pdf`.
     * @param string|null $parentPath Restrict the search to this folder and its descendants.
     * @param int|null    $count      Page size, 20–200. MYBOX defaults to 20.
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?Category $category = null,
        public readonly ?\DateTimeInterface $startDate = null,
        public readonly ?\DateTimeInterface $endDate = null,
        public readonly ?DateField $dateField = null,
        public readonly ?string $parentPath = null,
        public readonly ?int $count = null,
        public readonly ?string $cursor = null,
    ) {
        if (($q === null || trim($q) === '') && $category === null && $startDate === null && $endDate === null) {
            throw new InvalidArgumentException(
                'A file search needs at least one of: q, category, startDate, or endDate.',
            );
        }

        if ($count !== null && ($count < self::MIN_COUNT || $count > self::MAX_COUNT)) {
            throw new InvalidArgumentException(sprintf(
                'count must be between %d and %d, got %d.',
                self::MIN_COUNT,
                self::MAX_COUNT,
                $count,
            ));
        }

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw new InvalidArgumentException('startDate must not be later than endDate.');
        }
    }

    public function withCursor(?string $cursor): self
    {
        return new self(
            $this->q,
            $this->category,
            $this->startDate,
            $this->endDate,
            $this->dateField,
            $this->parentPath,
            $this->count,
            $cursor,
        );
    }

    /** @return array<string, scalar|null> */
    public function toQuery(): array
    {
        return [
            'q' => $this->q,
            'category' => $this->category?->value,
            'startDate' => $this->startDate?->format(\DateTimeInterface::ATOM),
            'endDate' => $this->endDate?->format(\DateTimeInterface::ATOM),
            'dateField' => $this->dateField?->value,
            'parentPath' => $this->parentPath,
            'count' => $this->count,
            'cursor' => $this->cursor,
        ];
    }
}
