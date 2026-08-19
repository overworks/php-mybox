<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Request;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\Enum\DateField;

/**
 * Query options for `GET /v1/search/resources/folders`.
 *
 * At least one of keyword, exact path, or a date bound is required. Note that
 * `path` is exclusive: when set, MYBOX ignores every other criterion and
 * returns just that folder, which is what makes it a cheap way to turn a path
 * into a resource id.
 */
final class SearchFoldersOptions
{
    public const MIN_COUNT = 20;
    public const MAX_COUNT = 200;

    /**
     * @param string|null $q          Keyword; whitespace-separated terms are combined with AND.
     * @param string|null $path       Exact folder path. Overrides all other criteria.
     * @param string|null $parentPath Restrict the search to this folder and its descendants.
     * @param int|null    $count      Page size, 20–200. MYBOX defaults to 20.
     */
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $path = null,
        public readonly ?\DateTimeInterface $startDate = null,
        public readonly ?\DateTimeInterface $endDate = null,
        public readonly ?DateField $dateField = null,
        public readonly ?string $parentPath = null,
        public readonly ?int $count = null,
        public readonly ?string $cursor = null,
    ) {
        $hasKeyword = $q !== null && trim($q) !== '';
        $hasPath = $path !== null && trim($path) !== '';

        if (!$hasKeyword && !$hasPath && $startDate === null && $endDate === null) {
            throw new InvalidArgumentException(
                'A folder search needs at least one of: q, path, startDate, or endDate.',
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
            $this->path,
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
            'path' => $this->path,
            'startDate' => $this->startDate?->format(\DateTimeInterface::ATOM),
            'endDate' => $this->endDate?->format(\DateTimeInterface::ATOM),
            'dateField' => $this->dateField?->value,
            'parentPath' => $this->parentPath,
            'count' => $this->count,
            'cursor' => $this->cursor,
        ];
    }
}
