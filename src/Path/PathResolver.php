<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Path;

use Minhyung\Mybox\Api\DriveApi;
use Minhyung\Mybox\Api\SearchApi;
use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Exception\NotFoundException;
use Minhyung\Mybox\Model\Enum\ResourceType;
use Minhyung\Mybox\Request\SearchFoldersOptions;

/**
 * Turns human-readable paths such as `/문서/2026/회의록.pdf` into resource ids.
 *
 * MYBOX itself is id-addressed; only the search index knows about paths. The
 * folder search endpoint accepts an exact `path`, so a folder normally
 * resolves in one request. When that misses — the index lags behind a
 * just-created folder, for instance — the resolver falls back to walking down
 * from the root with listing calls, which are cheaper on quota than search.
 *
 * Results are memoised per instance, because search is limited to 10–30 calls
 * per minute.
 */
final class PathResolver
{
    /** @var array<string, string> */
    private array $folderCache = [];

    public function __construct(
        private readonly DriveApi $drive,
        private readonly SearchApi $search,
    ) {
    }

    /**
     * Resolves a folder path to its resource id.
     *
     * @param  string $path Slash-separated, e.g. `/문서/2026`. A leading slash is optional.
     * @throws NotFoundException When no folder matches.
     */
    public function folderId(string $path): string
    {
        $segments = self::segments($path);

        if ($segments === []) {
            throw new InvalidArgumentException('The drive root has no resource id; pass a folder path.');
        }

        $key = implode('/', $segments);

        if (isset($this->folderCache[$key])) {
            return $this->folderCache[$key];
        }

        $id = $this->findFolderBySearch($segments) ?? $this->findFolderByWalking($segments);

        if ($id === null) {
            throw new NotFoundException(status: 404, errorMessage: sprintf('No folder found at path "%s".', $path));
        }

        return $this->folderCache[$key] = $id;
    }

    /**
     * Resolves a file path to its resource id.
     *
     * @throws NotFoundException When no file matches.
     */
    public function fileId(string $path): string
    {
        $segments = self::segments($path);

        if ($segments === []) {
            throw new InvalidArgumentException('A file path is required.');
        }

        $name = array_pop($segments);
        $parentId = $segments === [] ? null : $this->folderId(implode('/', $segments));

        foreach ($this->listChildren($parentId) as $child) {
            if ($child->type === ResourceType::File && $child->name === $name) {
                return $child->resourceId;
            }
        }

        throw new NotFoundException(status: 404, errorMessage: sprintf('No file found at path "%s".', $path));
    }

    /**
     * Resolves a path to either a file or a folder, whichever exists there.
     *
     * @throws NotFoundException When neither does.
     */
    public function resourceId(string $path): string
    {
        try {
            return $this->fileId($path);
        } catch (NotFoundException) {
            return $this->folderId($path);
        }
    }

    /**
     * Forgets memoised lookups, so subsequent calls see renames and moves.
     */
    public function clearCache(): void
    {
        $this->folderCache = [];
    }

    /**
     * @param list<string> $segments
     */
    private function findFolderBySearch(array $segments): ?string
    {
        // MYBOX reports folder paths with a trailing slash, e.g. "/문서/2026/".
        $wanted = '/' . implode('/', $segments) . '/';

        $page = $this->search->folders(new SearchFoldersOptions(path: $wanted));

        foreach ($page->resources as $folder) {
            if ($folder->resourceId !== null && ($folder->path === $wanted || $folder->path === rtrim($wanted, '/'))) {
                return $folder->resourceId;
            }
        }

        // Fall back to the first hit: some deployments answer an exact-path
        // query without echoing the path back verbatim.
        foreach ($page->resources as $folder) {
            if ($folder->resourceId !== null) {
                return $folder->resourceId;
            }
        }

        return null;
    }

    /**
     * @param list<string> $segments
     */
    private function findFolderByWalking(array $segments): ?string
    {
        $parentId = null;

        foreach ($segments as $segment) {
            $found = null;

            foreach ($this->listChildren($parentId) as $child) {
                if ($child->type === ResourceType::Folder && $child->name === $segment) {
                    $found = $child->resourceId;

                    break;
                }
            }

            if ($found === null) {
                return null;
            }

            $parentId = $found;
        }

        return $parentId;
    }

    /**
     * @return iterable<\Minhyung\Mybox\Model\ResourceItem>
     */
    private function listChildren(?string $parentId): iterable
    {
        return $parentId === null ? $this->drive->listRootAll() : $this->drive->listFolderAll($parentId);
    }

    /**
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $parts = [];

        foreach (explode('/', trim($path)) as $segment) {
            $segment = trim($segment);

            if ($segment !== '' && $segment !== '.') {
                $parts[] = $segment;
            }
        }

        return $parts;
    }
}
