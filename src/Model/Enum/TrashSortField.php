<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * Sort key accepted by the trash listing endpoint, which supports a different
 * set of keys than the drive listings.
 */
enum TrashSortField: string
{
    case DeletedAt = 'deletedAt';
    case Name = 'name';
    case Type = 'type';
    case Location = 'location';
    case Size = 'size';
}
