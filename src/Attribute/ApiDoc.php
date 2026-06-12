<?php

namespace Cvcv\ThinkOpenApi\Attribute;

use Cvcv\ThinkOpenApi\OpenApi\ResponseType;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiDoc
{
    /**
     * @param array<int, string> $tags
     * @param class-string|null $validate
     * @param class-string|null $response
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public ?string $summary = null,
        public array $tags = [],
        public ?string $description = null,
        public ?string $validate = null,
        public ?string $scene = null,
        public ?string $response = null,
        public ?ResponseType $responseType = null,
        public ?int $status = null,
        public bool $requestFieldsRequired = true,
        public array $extensions = [],
    ) {
    }
}
