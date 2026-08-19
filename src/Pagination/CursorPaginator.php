<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Pagination;

use Minhyung\Mybox\Model\Page;

/**
 * Walks a cursor-paginated MYBOX listing.
 *
 * Pages are fetched lazily, one request at a time, as iteration advances — so
 * a `foreach` over a large folder never materialises the whole listing in
 * memory, and breaking out early stops making requests.
 *
 * ```php
 * foreach ($mybox->drive()->listFolderAll($id) as $item) {
 *     echo $item->name, PHP_EOL;
 * }
 * ```
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class CursorPaginator implements \IteratorAggregate
{
    /**
     * @param \Closure(string|null): Page<T> $fetch Retrieves the page at the given cursor;
     *                                              null means the first page.
     */
    public function __construct(private readonly \Closure $fetch)
    {
    }

    /**
     * Iterates page objects rather than individual items, for callers that
     * need the per-page metadata (counts, cursors).
     *
     * @return \Generator<int, Page<T>>
     */
    public function pages(): \Generator
    {
        $cursor = null;
        $seen = [];

        do {
            $page = ($this->fetch)($cursor);

            yield $page;

            $cursor = $page->nextCursor();

            // A server that keeps handing back a cursor it already gave us
            // would loop forever; stop instead of hammering the quota.
            if ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    break;
                }

                $seen[$cursor] = true;
            }
        } while ($cursor !== null && $cursor !== '');
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->pages() as $page) {
            foreach ($page->items() as $item) {
                yield $item;
            }
        }
    }

    /**
     * Eagerly collects every item across every page.
     *
     * Convenient for small listings; prefer iterating for large ones.
     *
     * @return list<T>
     */
    public function all(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * Collects at most `$limit` items, stopping as soon as it has them so no
     * further pages are requested.
     *
     * @return list<T>
     */
    public function take(int $limit): array
    {
        $items = [];

        if ($limit <= 0) {
            return $items;
        }

        foreach ($this as $item) {
            $items[] = $item;

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }
}
