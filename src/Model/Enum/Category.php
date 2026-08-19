<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model\Enum;

/**
 * File kind as classified by MYBOX.
 *
 * Doubles as the `category` search filter and as the keys of
 * {@see \Minhyung\Mybox\Model\FileCounts}.
 */
enum Category: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';
    case Executable = 'executable';
    case Etc = 'etc';
}
