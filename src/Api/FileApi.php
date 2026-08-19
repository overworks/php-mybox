<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Api;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\DownloadTicket;
use Minhyung\Mybox\Model\ResourceRef;
use Minhyung\Mybox\Model\UploadTicket;
use Minhyung\Mybox\Request\CopyOptions;
use Minhyung\Mybox\Request\UploadRequest;

/**
 * Mutating the drive: creating folders, reserving transfer URLs, and moving,
 * renaming, copying or trashing resources.
 *
 * The two transfer methods only hand back a URL — see
 * {@see \Minhyung\Mybox\Transfer\Uploader} and
 * {@see \Minhyung\Mybox\Transfer\Downloader} for moving the bytes themselves.
 */
final class FileApi extends AbstractApi
{
    /**
     * Creates a folder.
     *
     * @param string|null $parentId Parent folder; the root is used when null.
     */
    public function createFolder(string $folderName, ?string $parentId = null): ResourceRef
    {
        if (trim($folderName) === '') {
            throw new InvalidArgumentException('folderName cannot be empty.');
        }

        $body = ['folderName' => $folderName];

        if ($parentId !== null) {
            $body['parentId'] = $parentId;
        }

        return ResourceRef::fromArray($this->requireBody($this->transport->post('/v1/drive/folders', $body)));
    }

    /**
     * Reserves a URL to upload a file's bytes to.
     */
    public function createUploadUrl(UploadRequest $request): UploadTicket
    {
        return UploadTicket::fromArray(
            $this->requireBody($this->transport->post('/v1/drive/files', $request->toBody())),
        );
    }

    /**
     * Issues a single-use download URL, valid for ten minutes.
     */
    public function createDownloadUrl(string $fileId): DownloadTicket
    {
        $path = sprintf('/v1/drive/files/%s/download', rawurlencode($fileId));

        return DownloadTicket::fromArray($this->requireBody($this->transport->get($path)));
    }

    /**
     * Copies a file or folder, optionally renaming it at the destination.
     */
    public function copy(string $resourceId, ?CopyOptions $options = null): ResourceRef
    {
        $path = sprintf('/v1/drive/resources/%s/copy', rawurlencode($resourceId));
        $body = ($options ?? new CopyOptions())->toBody();

        return ResourceRef::fromArray($this->requireBody($this->transport->post($path, $body)));
    }

    /**
     * Moves a resource to the trash. Use {@see TrashApi::purge()} to erase it
     * permanently.
     */
    public function delete(string $resourceId): void
    {
        $this->transport->delete(sprintf('/v1/drive/resources/%s', rawurlencode($resourceId)));
    }

    /**
     * Relocates a resource under a different parent.
     *
     * @param bool $isOverwrite Replace a same-named resource at the destination.
     */
    public function move(string $resourceId, string $parentId, bool $isOverwrite = false): void
    {
        if (trim($parentId) === '') {
            throw new InvalidArgumentException('parentId cannot be empty when moving a resource.');
        }

        $path = sprintf('/v1/drive/resources/%s/move', rawurlencode($resourceId));

        $this->transport->post($path, ['parentId' => $parentId, 'isOverwrite' => $isOverwrite]);
    }

    /**
     * Renames a resource in place; the resource id is unchanged.
     *
     * @param  string $name New name — include the extension to keep it.
     * @return string The name now in effect.
     */
    public function rename(string $resourceId, string $name): string
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('name cannot be empty.');
        }

        $path = sprintf('/v1/drive/resources/%s/rename', rawurlencode($resourceId));
        $body = $this->requireBody($this->transport->post($path, ['name' => $name]));

        return is_string($body['name'] ?? null) ? $body['name'] : $name;
    }
}
