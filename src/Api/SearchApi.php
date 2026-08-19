<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Api;

use Minhyung\Mybox\Model\FileSearchPage;
use Minhyung\Mybox\Model\FileSearchResult;
use Minhyung\Mybox\Model\FolderSearchPage;
use Minhyung\Mybox\Model\FolderSearchResult;
use Minhyung\Mybox\Pagination\CursorPaginator;
use Minhyung\Mybox\Request\SearchFilesOptions;
use Minhyung\Mybox\Request\SearchFoldersOptions;

/**
 * Full-text and metadata search across the drive.
 *
 * Search is the most tightly rate-limited part of the API — 10 requests per
 * minute on the smallest plans, 30 on larger ones — so prefer a single
 * well-scoped query over paging through everything.
 */
final class SearchApi extends AbstractApi
{
    /**
     * One page of matching files.
     */
    public function files(SearchFilesOptions $options): FileSearchPage
    {
        return FileSearchPage::fromArray(
            $this->requireBody($this->transport->get('/v1/search/resources/files', $options->toQuery())),
        );
    }

    /**
     * Every matching file, paged transparently.
     *
     * @return CursorPaginator<FileSearchResult>
     */
    public function filesAll(SearchFilesOptions $options): CursorPaginator
    {
        return new CursorPaginator(
            fn (?string $cursor): FileSearchPage => $this->files($options->withCursor($cursor)),
        );
    }

    /**
     * One page of matching folders.
     */
    public function folders(SearchFoldersOptions $options): FolderSearchPage
    {
        return FolderSearchPage::fromArray(
            $this->requireBody($this->transport->get('/v1/search/resources/folders', $options->toQuery())),
        );
    }

    /**
     * Every matching folder, paged transparently.
     *
     * @return CursorPaginator<FolderSearchResult>
     */
    public function foldersAll(SearchFoldersOptions $options): CursorPaginator
    {
        return new CursorPaginator(
            fn (?string $cursor): FolderSearchPage => $this->folders($options->withCursor($cursor)),
        );
    }
}
