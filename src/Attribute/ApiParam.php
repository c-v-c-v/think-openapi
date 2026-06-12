<?php

namespace Cvcv\ThinkOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class ApiParam
{
    /**
     * @param array<string, mixed>|null $schema
     * @param array<string, mixed>|null $examples
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public string $name,
        public string $in = 'query',
        public ?string $description = null,
        public ?bool $required = null,
        public ?array $schema = null,
        public mixed $example = null,
        public ?array $examples = null,
        public ?bool $deprecated = null,
        public array $extensions = [],
    ) {
    }
}
