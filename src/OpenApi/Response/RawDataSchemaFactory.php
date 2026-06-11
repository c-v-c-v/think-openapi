<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Response;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;

final class RawDataSchemaFactory implements ResponseSchemaFactory
{
    public function schema(array $dataSchema, ApiDoc $doc, string $httpMethod, int $status): array
    {
        return $dataSchema;
    }
}
