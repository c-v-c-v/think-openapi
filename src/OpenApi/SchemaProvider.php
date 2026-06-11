<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

interface SchemaProvider
{
    public static function openApiSchemaName(): string;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function openApiSchemas(): array;
}
