<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

final readonly class RequestBodySchemaBuilder
{
    public function __construct(private ValidateRuleSchemaMapper $ruleSchemaMapper)
    {
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, string> $fields
     * @param callable(mixed, string|null): array<string, mixed> $schemaFromRule
     * @return array<string, mixed>
     */
    public function objectSchema(array $rules, bool $includeRequired, array $fields, callable $schemaFromRule): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($rules as $name => $rule) {
            $this->addObjectProperty(
                $schema,
                explode('.', $name),
                $schemaFromRule($rule, $fields[$name] ?? null),
                $includeRequired && $this->ruleSchemaMapper->isRequired($rule),
            );
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<int, string> $segments
     * @param array<string, mixed> $property
     */
    private function addObjectProperty(array &$schema, array $segments, array $property, bool $required): void
    {
        $name = array_shift($segments);

        if ($name === null || $name === '') {
            return;
        }

        if ($required) {
            $this->markRequired($schema, $name);
        }

        if ($segments === []) {
            $schema['properties'][$name] = $property;
            return;
        }

        if (!isset($schema['properties'][$name]) || !is_array($schema['properties'][$name])) {
            $schema['properties'][$name] = [
                'type' => 'object',
                'properties' => [],
            ];
        }

        if (!isset($schema['properties'][$name]['properties']) || !is_array($schema['properties'][$name]['properties'])) {
            $schema['properties'][$name]['properties'] = [];
        }

        $this->addObjectProperty($schema['properties'][$name], $segments, $property, $required);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function markRequired(array &$schema, string $name): void
    {
        if (!isset($schema['required']) || !is_array($schema['required'])) {
            $schema['required'] = [];
        }

        if (!in_array($name, $schema['required'], true)) {
            $schema['required'][] = $name;
        }
    }
}
