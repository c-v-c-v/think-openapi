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
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function with(array $schema, array $metadata): array
    {
        foreach ($metadata as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            if (str_starts_with($name, 'x-')) {
                $schema[$name] = $value;
            }
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function format(array $schema, string $format): array
    {
        $schema['format'] = $format;

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function example(array $schema, mixed $example): array
    {
        $schema['example'] = $example;

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public static function deprecated(array $schema, bool $deprecated = true): array
    {
        $schema['deprecated'] = $deprecated;

        return $schema;
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
