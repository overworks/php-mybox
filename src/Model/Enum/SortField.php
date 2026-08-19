<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * Sort key accepted by the drive listing endpoints.
 *
 * Note that MYBOX always groups folders before files regardless of the key.
 */
enum SortField: string
{
    case Name = 'name';
    case CreatedAt = 'createdAt';
    case ModifiedAt = 'modifiedAt';
    case AccessedAt = 'accessedAt';
}
