<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use Cvcv\ThinkOpenApi\OpenApi\Response\ResponseDataSchema;

enum ResponseDataType: string implements ResponseType
{
    case Item = 'item';
    case List = 'list';
    case Page = 'page';
    case None = 'none';

    public function dataSchema(
        ?string $itemSchemaName,
        ?array $itemSchema,
        ApiDoc $doc,
        string $httpMethod,
        int $status,
    ): ResponseDataSchema {
        if ($this === self::None || $itemSchemaName === null || $itemSchema === null) {
            return new ResponseDataSchema(['type' => 'null']);
        }

        return match ($this) {
            self::Item => new ResponseDataSchema($itemSchema),
            self::List => new ResponseDataSchema([
                'type' => 'array',
                'items' => $itemSchema,
            ]),
            self::Page => $this->pageSchema($itemSchemaName, $itemSchema),
            self::None => new ResponseDataSchema(['type' => 'null']),
        };
    }

    /**
     * @param array<string, mixed> $itemSchema
     */
    private function pageSchema(string $itemSchemaName, array $itemSchema): ResponseDataSchema
    {
        $pageSchemaName = $itemSchemaName . 'Page';

        return new ResponseDataSchema(
            ['$ref' => '#/components/schemas/' . $pageSchemaName],
            [
                'PageMeta' => [
                    'type' => 'object',
                    'required' => ['current_page', 'per_page', 'total', 'last_page'],
                    'properties' => [
                        'current_page' => ['type' => 'integer', 'description' => '当前页码'],
                        'per_page' => ['type' => 'integer', 'description' => '每页数量'],
                        'total' => ['type' => 'integer', 'description' => '总记录数'],
                        'last_page' => ['type' => 'integer', 'description' => '最后一页页码'],
                    ],
                ],
                $pageSchemaName => [
                    'type' => 'object',
                    'required' => ['list', 'meta'],
                    'properties' => [
                        'list' => [
                            'type' => 'array',
                            'items' => $itemSchema,
                        ],
                        'meta' => ['$ref' => '#/components/schemas/PageMeta'],
                    ],
                ],
            ],
        );
    }
}
