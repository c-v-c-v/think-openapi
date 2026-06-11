<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use Cvcv\ThinkOpenApi\OpenApi\Response\ResponseDataSchema;

interface ResponseType
{
    /**
     * @param array<string, mixed>|null $itemSchema
     */
    public function dataSchema(
        ?string $itemSchemaName,
        ?array $itemSchema,
        ApiDoc $doc,
        string $httpMethod,
        int $status,
    ): ResponseDataSchema;
}
