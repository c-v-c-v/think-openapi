<?php

namespace Cvcv\ThinkOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiResponse
{
    /**
     * @param array<string, mixed>|null $content
     * @param array<string, mixed>|null $headers
     * @param array<string, mixed>|null $links
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public int|string $status,
        public ?string $description = null,
        public ?array $content = null,
        public ?array $headers = null,
        public ?array $links = null,
        public array $extensions = [],
    ) {
    }
}
