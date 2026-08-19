<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Request;

/**
 * Body for `POST /v1/drive/resources/{resourceId}/copy`.
 *
 * Omitting `$name` keeps the original name; when supplying one, include the
 * extension or the copy loses it.
 */
final class CopyOptions
{
    /**
     * @param string|null $parentId    Destination folder; the root is used when null.
     * @param string|null $name        Name for the copy, extension included.
     * @param bool|null   $isOverwrite Replace a same-named resource at the destination.
     */
    public function __construct(
        public readonly ?string $parentId = null,
        public readonly ?string $name = null,
        public readonly ?bool $isOverwrite = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toBody(): array
    {
        $body = [];

        if ($this->parentId !== null) {
            $body['parentId'] = $this->parentId;
        }

        if ($this->name !== null) {
            $body['name'] = $this->name;
        }

        if ($this->isOverwrite !== null) {
            $body['isOverwrite'] = $this->isOverwrite;
        }

        return $body;
    }
}
