<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use InvalidArgumentException;

final class SchemaRef
{
    /**
     * @var array<string, class-string<SchemaProvider>>
     */
    private static array $providers = [];

    public static function to(string $schemaName, ?string $description = null): array
    {
        $schema = [
            '$ref' => '#/components/schemas/' . $schemaName,
        ];

        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param class-string<SchemaProvider> $provider
     * @return array<string, mixed>
     */
    public static function provider(string $provider, ?string $description = null): array
    {
        if (!is_subclass_of($provider, SchemaProvider::class)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] must implement %s.',
                $provider,
                SchemaProvider::class,
            ));
        }

        self::$providers[$provider::openApiSchemaName()] = $provider;

        return self::to($provider::openApiSchemaName(), $description);
    }

    /**
     * @param array<string, mixed> $schema
     * @return class-string<SchemaProvider>|null
     */
    public static function providerFrom(array $schema): ?string
    {
        $ref = $schema['$ref'] ?? null;

        if (!is_string($ref) || !str_starts_with($ref, '#/components/schemas/')) {
            return null;
        }

        $schemaName = substr($ref, strlen('#/components/schemas/'));

        return self::$providers[$schemaName] ?? null;
    }

    public static function flushProviders(): void
    {
        self::$providers = [];
    }
}
