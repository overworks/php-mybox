<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * Which timestamp `startDate`/`endDate` filter against when searching.
 */
enum DateField: string
{
    case Created = 'created';
    case Modified = 'modified';
}
