<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Api;

use Minhyung\Mybox\Model\TrashedResourceItem;
use Minhyung\Mybox\Model\TrashList;
use Minhyung\Mybox\Pagination\CursorPaginator;
use Minhyung\Mybox\Request\TrashListOptions;

/**
 * The trash: listing, restoring, and permanent deletion.
 *
 * Everything here is destructive or restorative in a way the user will notice,
 * and MYBOX applies a tighter per-minute quota to deletion (60/min) than to
 * restoration (180/min).
 */
final class TrashApi extends AbstractApi
{
    /**
     * One page of trashed resources.
     */
    public function list(?TrashListOptions $options = null): TrashList
    {
        $options ??= new TrashListOptions();

        return TrashList::fromArray($this->requireBody($this->transport->get('/v1/drive/trash', $options->toQuery())));
    }

    /**
     * Every trashed resource, paged transparently.
     *
     * @return CursorPaginator<TrashedResourceItem>
     */
    public function listAll(?TrashListOptions $options = null): CursorPaginator
    {
        $base = $options ?? new TrashListOptions();

        return new CursorPaginator(fn (?string $cursor): TrashList => $this->list($base->withCursor($cursor)));
    }

    /**
     * Puts a resource back where it was deleted from.
     *
     * @param bool $isOverwrite Replace a same-named resource at the original location.
     */
    public function restore(string $resourceId, bool $isOverwrite = false): void
    {
        $path = sprintf('/v1/drive/trash/%s/restore', rawurlencode($resourceId));

        $this->transport->post($path, ['isOverwrite' => $isOverwrite]);
    }

    /**
     * Permanently deletes one trashed resource. This cannot be undone.
     */
    public function purge(string $resourceId): void
    {
        $this->transport->delete(sprintf('/v1/drive/trash/%s', rawurlencode($resourceId)));
    }

    /**
     * Empties the trash entirely. This cannot be undone.
     */
    public function empty(): void
    {
        $this->transport->delete('/v1/drive/trash');
    }
}
