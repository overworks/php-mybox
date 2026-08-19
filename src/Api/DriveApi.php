<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Api;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\FavoriteState;
use Minhyung\Mybox\Model\ResourceItem;
use Minhyung\Mybox\Model\ResourceList;
use Minhyung\Mybox\Model\StorageInfo;
use Minhyung\Mybox\Pagination\CursorPaginator;
use Minhyung\Mybox\Request\ListOptions;

/**
 * Reading the drive: storage quota, folder listings, resource properties, and
 * the favourite flag.
 */
final class DriveApi extends AbstractApi
{
    /**
     * Values MYBOX accepts for the trash auto-delete interval, in days.
     * Zero turns automatic deletion off.
     */
    public const TRASH_AUTO_DELETE_DAYS = [0, 5, 15, 30, 50];

    /**
     * Quota, usage, per-category file counts, the largest uploadable file, and
     * the trash auto-delete interval.
     */
    public function storage(): StorageInfo
    {
        return StorageInfo::fromArray($this->requireBody($this->transport->get('/v1/drive/storage')));
    }

    /**
     * Sets how many days a resource stays in the trash before MYBOX deletes it.
     *
     * @param  int $days One of {@see self::TRASH_AUTO_DELETE_DAYS}; 0 disables auto-deletion.
     * @return int The interval now in effect.
     */
    public function setTrashAutoDeleteDays(int $days): int
    {
        if (!in_array($days, self::TRASH_AUTO_DELETE_DAYS, true)) {
            throw new InvalidArgumentException(sprintf(
                'trashAutoDeleteDays must be one of %s, got %d.',
                implode(', ', self::TRASH_AUTO_DELETE_DAYS),
                $days,
            ));
        }

        $body = $this->requireBody($this->transport->patch('/v1/drive/storage', ['trashAutoDeleteDays' => $days]));

        return is_int($body['trashAutoDeleteDays'] ?? null) ? $body['trashAutoDeleteDays'] : $days;
    }

    /**
     * One page of the drive root.
     */
    public function listRoot(?ListOptions $options = null): ResourceList
    {
        $options ??= new ListOptions();

        return ResourceList::fromArray(
            $this->requireBody($this->transport->get('/v1/drive/resources', $options->toQuery())),
        );
    }

    /**
     * Every resource in the drive root, paged transparently.
     *
     * @return CursorPaginator<ResourceItem>
     */
    public function listRootAll(?ListOptions $options = null): CursorPaginator
    {
        $base = $options ?? new ListOptions();

        return new CursorPaginator(fn (?string $cursor): ResourceList => $this->listRoot($base->withCursor($cursor)));
    }

    /**
     * One page of a folder's direct children.
     *
     * @param string $folderId Use a `resourceId` returned by another listing.
     */
    public function listFolder(string $folderId, ?ListOptions $options = null): ResourceList
    {
        $options ??= new ListOptions();
        $path = sprintf('/v1/drive/folders/%s/resources', rawurlencode($folderId));

        return ResourceList::fromArray($this->requireBody($this->transport->get($path, $options->toQuery())));
    }

    /**
     * Every direct child of a folder, paged transparently.
     *
     * @return CursorPaginator<ResourceItem>
     */
    public function listFolderAll(string $folderId, ?ListOptions $options = null): CursorPaginator
    {
        $base = $options ?? new ListOptions();

        return new CursorPaginator(
            fn (?string $cursor): ResourceList => $this->listFolder($folderId, $base->withCursor($cursor)),
        );
    }

    /**
     * Properties of a single file or folder.
     *
     * For folders the result also carries `fileCount` and `subFolderCount`.
     */
    public function get(string $resourceId): ResourceItem
    {
        $path = sprintf('/v1/drive/resources/%s', rawurlencode($resourceId));

        return ResourceItem::fromArray($this->requireBody($this->transport->get($path)));
    }

    /**
     * Marks a resource as a favourite. Idempotent.
     */
    public function favorite(string $resourceId): FavoriteState
    {
        $path = sprintf('/v1/drive/resources/%s/favorite', rawurlencode($resourceId));

        return FavoriteState::fromArray($this->requireBody($this->transport->post($path)));
    }

    /**
     * Clears the favourite flag. Idempotent.
     */
    public function unfavorite(string $resourceId): FavoriteState
    {
        $path = sprintf('/v1/drive/resources/%s/unfavorite', rawurlencode($resourceId));

        return FavoriteState::fromArray($this->requireBody($this->transport->post($path)));
    }
}
