<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

final class ValidateRuleSchemaMapper
{
    private const DEFAULT_REGEX_RULES = [
        'alpha' => '/^[A-Za-z]+$/',
        'alphaNum' => '/^[A-Za-z0-9]+$/',
        'alphaDash' => '/^[A-Za-z0-9\-\_]+$/',
        'chs' => '/^[\p{Han}]+$/u',
        'chsAlpha' => '/^[\p{Han}a-zA-Z]+$/u',
        'chsAlphaNum' => '/^[\p{Han}a-zA-Z0-9]+$/u',
        'chsDash' => '/^[\p{Han}a-zA-Z0-9\_\-]+$/u',
        'mobile' => '/^1[3-9]\d{9}$/',
        'idCard' => '/(^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$)|(^[1-9]\d{5}\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}$)/',
        'zip' => '/\d{6}/',
    ];

    /**
     * @return array<string, mixed>
     */
    public function schemaFromRule(mixed $rule, ?string $description = null): array
    {
        $parts = $this->ruleParts($rule);
        $schema = [
            'type' => $this->schemaTypeFromRuleParts($parts),
        ];

        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        foreach ($parts as $part) {
            if ($part === 'email') {
                $schema['format'] = 'email';
                continue;
            }

            if ($part === 'url') {
                $schema['format'] = 'uri';
                continue;
            }

            if ($part === 'date') {
                $schema['format'] = 'date';
                continue;
            }

            if ($part === 'ip' || str_starts_with($part, 'ip:')) {
                $schema['type'] = 'string';
                $version = $part === 'ip' ? 'ipv4' : substr($part, 3);
                $schema['format'] = $version === 'ipv6' ? 'ipv6' : 'ipv4';
                continue;
            }

            if ($this->isDateFormatRule($part)) {
                $format = substr($part, strpos($part, ':') + 1);
                $schema['format'] = $this->isDateOnlyFormat($format) ? 'date' : 'date-time';
                $this->preserveThinkPhpRule($schema, $part);
                continue;
            }

            if (isset(self::DEFAULT_REGEX_RULES[$part])) {
                $schema['pattern'] = $this->patternFromRegexPattern(self::DEFAULT_REGEX_RULES[$part]);
                continue;
            }

            if (str_starts_with($part, 'regex:')) {
                $pattern = $this->patternFromRegexRule($part);

                if ($pattern !== null) {
                    $schema['pattern'] = $pattern;
                } else {
                    $this->preserveThinkPhpRule($schema, $part);
                }

                continue;
            }

            if (str_starts_with($part, 'length:') && in_array($schema['type'], ['array', 'string'], true)) {
                $this->applyLengthRule($schema, $part);
                continue;
            }

            if (str_starts_with($part, 'max:') && in_array($schema['type'], ['array', 'string'], true)) {
                $key = $schema['type'] === 'array' ? 'maxItems' : 'maxLength';
                $schema[$key] = (int) substr($part, 4);
                continue;
            }

            if (str_starts_with($part, 'min:') && in_array($schema['type'], ['array', 'string'], true)) {
                $key = $schema['type'] === 'array' ? 'minItems' : 'minLength';
                $schema[$key] = (int) substr($part, 4);
                continue;
            }

            if (str_starts_with($part, 'multipleOf:') && $this->isNumericSchema($schema)) {
                $schema['multipleOf'] = $this->numericRuleValue($part);
                continue;
            }

            if (str_starts_with($part, 'between:')) {
                [$minimum, $maximum] = array_map('intval', explode(',', substr($part, 8), 2));
                $schema['minimum'] = $minimum;
                $schema['maximum'] = $maximum;
                continue;
            }

            if (str_starts_with($part, 'egt:') || str_starts_with($part, '>=:')) {
                $schema['minimum'] = $this->numericRuleValue($part);
                continue;
            }

            if (str_starts_with($part, 'gt:') || str_starts_with($part, '>:')) {
                $schema['exclusiveMinimum'] = $this->numericRuleValue($part);
                continue;
            }

            if (str_starts_with($part, 'elt:') || str_starts_with($part, '<=:')) {
                $schema['maximum'] = $this->numericRuleValue($part);
                continue;
            }

            if (str_starts_with($part, 'lt:') || str_starts_with($part, '<:')) {
                $schema['exclusiveMaximum'] = $this->numericRuleValue($part);
                continue;
            }

            if (str_starts_with($part, 'in:')) {
                $schema['enum'] = $this->ruleValueList($part, 3, $schema['type']);
                continue;
            }

            if (str_starts_with($part, 'notIn:')) {
                $schema['not'] = [
                    'enum' => $this->ruleValueList($part, 6, $schema['type']),
                ];
            }
        }

        return $schema;
    }

    public function isRequired(mixed $rule): bool
    {
        return in_array('require', $this->ruleParts($rule), true);
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<class-string<\UnitEnum>>
     */
    public function enumRules(array $rules): array
    {
        $enums = [];

        foreach ($rules as $rule) {
            $enum = $this->enumFromRule($rule);

            if ($enum !== null) {
                $enums[] = $enum;
            }
        }

        return array_values(array_unique($enums));
    }

    /**
     * @return class-string<\UnitEnum>|null
     */
    public function enumFromRule(mixed $rule): ?string
    {
        foreach ($this->rawRuleParts($rule) as $part) {
            if (!is_string($part)) {
                continue;
            }

            if (enum_exists($part)) {
                return $part;
            }

            if (str_starts_with($part, 'enum:')) {
                $enum = substr($part, 5);

                if (enum_exists($enum)) {
                    return $enum;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function ruleParts(mixed $rule): array
    {
        return array_values(array_filter(
            $this->rawRuleParts($rule),
            static fn (mixed $part): bool => is_string($part) && !enum_exists($part) && !str_starts_with($part, 'enum:'),
        ));
    }

    /**
     * @return list<mixed>
     */
    public function rawRuleParts(mixed $rule): array
    {
        if (is_string($rule)) {
            return explode('|', $rule);
        }

        if (is_array($rule)) {
            $parts = [];

            foreach ($rule as $name => $part) {
                if (is_int($name)) {
                    $parts[] = $part;
                    continue;
                }

                $part = $this->normalizeNamedRulePart($name, $part);

                if ($part !== null) {
                    $parts[] = $part;
                }
            }

            return $parts;
        }

        return [];
    }

    /**
     * @param list<string> $parts
     */
    private function schemaTypeFromRuleParts(array $parts): string
    {
        foreach (['integer', 'int', 'number', 'float', 'boolean', 'bool', 'array'] as $type) {
            if (in_array($type, $parts, true)) {
                return match ($type) {
                    'int' => 'integer',
                    'float' => 'number',
                    'bool' => 'boolean',
                    default => $type,
                };
            }
        }

        return 'string';
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function applyLengthRule(array &$schema, string $part): void
    {
        $values = explode(',', substr($part, 7), 2);
        $minimumKey = $schema['type'] === 'array' ? 'minItems' : 'minLength';
        $maximumKey = $schema['type'] === 'array' ? 'maxItems' : 'maxLength';

        if (count($values) === 1 && $this->isIntegerRuleValue($values[0])) {
            $length = (int) $values[0];
            $schema[$minimumKey] = $length;
            $schema[$maximumKey] = $length;
            return;
        }

        if (count($values) === 2 && $this->isIntegerRuleValue($values[0]) && $this->isIntegerRuleValue($values[1])) {
            $schema[$minimumKey] = (int) $values[0];
            $schema[$maximumKey] = (int) $values[1];
        }
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function isNumericSchema(array $schema): bool
    {
        return in_array($schema['type'] ?? null, ['integer', 'number'], true);
    }

    private function isIntegerRuleValue(string $value): bool
    {
        return preg_match('/^\d+$/', $value) === 1;
    }

    private function isDateFormatRule(string $part): bool
    {
        return str_starts_with($part, 'dateFormat:') || str_starts_with($part, 'date_format:');
    }

    private function isDateOnlyFormat(string $format): bool
    {
        return in_array($format, ['Y-m-d', 'y-m-d'], true);
    }

    private function patternFromRegexRule(string $part): ?string
    {
        return $this->patternFromRegexPattern(trim(substr($part, 6)), false);
    }

    private function patternFromRegexPattern(string $pattern, bool $allowModifiers = true): ?string
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return null;
        }

        $delimiter = $pattern[0];

        if (
            strlen($pattern) >= 2
            && !ctype_alnum($delimiter)
            && $delimiter !== '\\'
            && !ctype_space($delimiter)
        ) {
            $lastDelimiter = strrpos($pattern, $delimiter);

            if ($lastDelimiter !== false && $lastDelimiter > 0 && !$this->isEscapedAt($pattern, $lastDelimiter)) {
                $modifiers = substr($pattern, $lastDelimiter + 1);

                if (($allowModifiers || $modifiers === '') && preg_match('/^[imsUxADSJu]*$/', $modifiers) === 1) {
                    return substr($pattern, 1, $lastDelimiter - 1);
                }
            }

            return null;
        }

        return $pattern;
    }

    private function isEscapedAt(string $value, int $position): bool
    {
        $slashes = 0;

        for ($i = $position - 1; $i >= 0 && $value[$i] === '\\'; $i--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function preserveThinkPhpRule(array &$schema, string $part): void
    {
        if (!isset($schema['x-thinkphp-rule'])) {
            $schema['x-thinkphp-rule'] = $part;
            return;
        }

        if (is_string($schema['x-thinkphp-rule'])) {
            $schema['x-thinkphp-rule'] = [$schema['x-thinkphp-rule']];
        }

        if (is_array($schema['x-thinkphp-rule']) && !in_array($part, $schema['x-thinkphp-rule'], true)) {
            $schema['x-thinkphp-rule'][] = $part;
        }
    }

    private function numericRuleValue(string $part): int|float
    {
        $value = substr($part, strpos($part, ':') + 1);

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    /**
     * @return list<int|float|bool|string>
     */
    private function ruleValueList(string $part, int $offset, string $schemaType): array
    {
        return array_map(
            fn (string $value): int|float|bool|string => $this->castRuleValue(trim($value), $schemaType),
            explode(',', substr($part, $offset)),
        );
    }

    private function castRuleValue(string $value, string $schemaType): int|float|bool|string
    {
        if ($schemaType === 'integer' && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if ($schemaType === 'number' && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if ($schemaType === 'boolean') {
            return match (strtolower($value)) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => $value,
            };
        }

        return $value;
    }

    private function normalizeNamedRulePart(string $name, mixed $value): ?string
    {
        if ($name === '') {
            return null;
        }

        if ($value === '' || $value === true) {
            return $name;
        }

        if ($value === false || $value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return $name . ':' . $this->stringifyRuleValue($value);
        }

        if (!is_array($value)) {
            return null;
        }

        $values = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                return null;
            }

            $values[] = $this->stringifyRuleValue($item);
        }

        return $values === [] ? null : $name . ':' . implode(',', $values);
    }

    private function stringifyRuleValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
