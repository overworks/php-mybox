<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Model;

/**
 * The `{resourceId, name}` pair returned by the create/copy endpoints.
 */
final class ResourceRef
{
    public function __construct(
        public readonly string $resourceId,
        public readonly string $name,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceId: Hydrator::string($data, 'resourceId'),
            name: Hydrator::string($data, 'name'),
        );
    }
}
