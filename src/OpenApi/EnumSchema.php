<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use UnitEnum;

final class EnumSchema
{
    /**
     * @param class-string<UnitEnum> $enum
     */
    public static function name(string $enum): string
    {
        return (new ReflectionClass($enum))->getShortName();
    }

    /**
     * @param class-string<UnitEnum> $enum
     * @return list<int|string>
     */
    public static function values(string $enum): array
    {
        self::ensureEnum($enum);

        return array_values(array_map(
            static fn (UnitEnum $case): int|string => $case instanceof BackedEnum ? $case->value : $case->name,
            $enum::cases(),
        ));
    }

    /**
     * @param class-string<UnitEnum> $enum
     * @return array<string, mixed>
     */
    public static function schema(string $enum, ?string $description = null): array
    {
        $values = self::values($enum);

        $schema = [
            'type' => self::type($values),
            'enum' => $values,
        ];

        $descriptions = self::descriptions($enum);

        if ($descriptions !== []) {
            $schema['x-enumDescriptions'] = $descriptions;
        }

        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param class-string<UnitEnum> $enum
     * @return list<string>
     */
    private static function descriptions(string $enum): array
    {
        self::ensureEnum($enum);

        $descriptions = [];

        foreach ($enum::cases() as $case) {
            if (!method_exists($case, 'label')) {
                return [];
            }

            $descriptions[] = (string) $case->label();
        }

        return $descriptions;
    }

    /**
     * @param class-string<UnitEnum> $enum
     * @return array<string, mixed>
     */
    public static function reference(string $enum, ?string $description = null): array
    {
        self::ensureEnum($enum);

        $schema = [
            '$ref' => '#/components/schemas/' . self::name($enum),
        ];

        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param list<int|string> $values
     */
    private static function type(array $values): string
    {
        foreach ($values as $value) {
            return is_int($value) ? 'integer' : 'string';
        }

        return 'string';
    }

    private static function ensureEnum(string $enum): void
    {
        if (!enum_exists($enum)) {
            throw new InvalidArgumentException(sprintf('[%s] is not an enum.', $enum));
        }
    }
}
