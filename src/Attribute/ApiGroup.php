<?php

namespace Cvcv\ThinkOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ApiGroup
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        public array $tags,
        public ?string $description = null,
    ) {
    }
}
