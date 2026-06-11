<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use InvalidArgumentException;

final class SchemaRef
{
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

        return self::to($provider::openApiSchemaName(), $description);
    }
}
