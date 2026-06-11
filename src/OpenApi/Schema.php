<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

final class Schema
{
    /**
     * @param array<string, mixed> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    public static function object(array $properties = [], array $required = [], ?string $description = null): array
    {
        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return self::describe($schema, $description);
    }

    /**
     * @param array<string, mixed> $items
     * @return array<string, mixed>
     */
    public static function arrayOf(array $items, ?string $description = null): array
    {
        return self::describe([
            'type' => 'array',
            'items' => $items,
        ], $description);
    }

    /**
     * @param list<array<string, mixed>> $schemas
     * @return array<string, mixed>
     */
    public static function oneOf(array $schemas, ?string $description = null): array
    {
        return self::describe([
            'oneOf' => $schemas,
        ], $description);
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function nullable(array $schema, ?string $description = null): array
    {
        return self::oneOf([$schema, self::null()], $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function string(?string $description = null): array
    {
        return self::type('string', $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function integer(?string $description = null): array
    {
        return self::type('integer', $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function number(?string $description = null): array
    {
        return self::type('number', $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function boolean(?string $description = null): array
    {
        return self::type('boolean', $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function null(): array
    {
        return ['type' => 'null'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function type(string $type, ?string $description): array
    {
        return self::describe(['type' => $type], $description);
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function describe(array $schema, ?string $description): array
    {
        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }
}
