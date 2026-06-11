<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Response;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;

interface ResponseSchemaFactory
{
    /**
     * @param array<string, mixed> $dataSchema
     * @return array<string, mixed>
     */
    public function schema(array $dataSchema, ApiDoc $doc, string $httpMethod, int $status): array;
}
