<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Response;

final readonly class ResponseDataSchema
{
    /**
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $components
     */
    public function __construct(
        public array $schema,
        public array $components = [],
    ) {
    }
}
