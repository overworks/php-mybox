<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * One page of a cursor-paginated response.
 *
 * Every MYBOX listing (drive, trash, file search, folder search) returns items
 * plus a `responseMetaData.nextCursor`, so {@see \Minhyung\Mybox\Pagination\CursorPaginator}
 * can walk any of them through this interface.
 *
 * @template-covariant T
 */
interface Page
{
    /** @return list<T> */
    public function items(): array;

    /**
     * Cursor to pass to the next call, or null when this is the last page.
     */
    public function nextCursor(): ?string;
}
