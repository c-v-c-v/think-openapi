<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Response;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;

final class ResultEnvelopeSchemaFactory implements ResponseSchemaFactory
{
    public function schema(array $dataSchema, ApiDoc $doc, string $httpMethod, int $status): array
    {
        return [
            'type' => 'object',
            'required' => ['code', 'data', 'msg'],
            'properties' => [
                'code' => ['type' => 'integer', 'description' => '业务状态码', 'example' => 200],
                'data' => ['description' => '响应数据', ...$dataSchema],
                'msg' => ['type' => 'string', 'description' => '响应消息', 'example' => 'OK'],
            ],
        ];
    }
}
