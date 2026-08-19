<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * Result of toggling the favourite flag on a resource.
 *
 * Both endpoints are idempotent: favouriting an already-favourited resource
 * still answers 200.
 */
final class FavoriteState
{
    public function __construct(
        public readonly string $resourceId,
        public readonly bool $isFavorite,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: Hydrator::string($data, 'resourceId'),
            isFavorite: Hydrator::bool($data, 'isFavorite'),
        );
    }
}
