<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * Sort direction half of a `sort=field,order` parameter.
 */
enum SortOrder: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
