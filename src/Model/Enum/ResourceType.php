<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * Whether a resource is a file or a folder (the `type` field).
 */
enum ResourceType: string
{
    case File = 'file';
    case Folder = 'folder';
}
