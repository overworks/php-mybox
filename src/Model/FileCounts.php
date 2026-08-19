<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

use Minhyung\Mybox\Model\Enum\Category;

/**
 * Per-category file tallies from `GET /v1/drive/storage`.
 */
final class FileCounts
{
    public function __construct(
        public readonly int $total,
        public readonly int $image,
        public readonly int $video,
        public readonly int $audio,
        public readonly int $document,
        public readonly int $archive,
        public readonly int $executable,
        public readonly int $etc,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            total: Hydrator::int($data, 'total'),
            image: Hydrator::int($data, 'image'),
            video: Hydrator::int($data, 'video'),
            audio: Hydrator::int($data, 'audio'),
            document: Hydrator::int($data, 'document'),
            archive: Hydrator::int($data, 'archive'),
            executable: Hydrator::int($data, 'executable'),
            etc: Hydrator::int($data, 'etc'),
        );
    }

    /**
     * Count for a single category, so callers can index by enum rather than
     * by property name.
     */
    public function of(Category $category): int
    {
        return match ($category) {
            Category::Image => $this->image,
            Category::Video => $this->video,
            Category::Audio => $this->audio,
            Category::Document => $this->document,
            Category::Archive => $this->archive,
            Category::Executable => $this->executable,
            Category::Etc => $this->etc,
        };
    }
}
