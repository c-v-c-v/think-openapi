<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use UnitEnum;
use think\App;
use think\Validate;

final class OpenApiLinter
{
    /**
     * @var list<OpenApiLintIssue>
     */
    private array $issues = [];

    public function __construct(
        private readonly App $app,
        private readonly ?RouteListProvider $routeListProvider = null,
    ) {
    }

    /**
     * @return list<OpenApiLintIssue>
     */
    public function lint(?array $openApi = null): array
    {
        $this->issues = [];
        $routes = $this->routeList();
        $documentedRoutes = $this->documentedRoutes($routes);

        $this->lintRoutes($documentedRoutes);

        if ($openApi === null) {
            try {
                $openApi = (new Generator(
                    app: $this->app,
                    routeListProvider: $this->routeListProvider,
                ))->generate();
            } catch (Throwable $exception) {
                $this->addIssue(
                    'generation',
                    'openapi',
                    sprintf('OpenAPI generation failed: %s', $exception->getMessage()),
                );

                return $this->issues;
            }
        }

        $this->lintOpenApi($openApi);
        $this->lintRegisteredEnumSchemas($openApi, $documentedRoutes);

        return $this->issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routeList(): array
    {
        return ($this->routeListProvider ?? new ThinkPhpRouteListProvider())->routes($this->app);
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @return list<array{path: string, method: string, operationId: string, doc: ApiDoc, location: string}>
     */
    private function documentedRoutes(array $routes): array
    {
        $documented = [];

        foreach ($routes as $route) {
            $endpoint = $this->endpointFromRoute($route['route'] ?? null);

            if ($endpoint === null) {
                continue;
            }

            [$class, $method] = $endpoint;

            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($class, $method);
            $doc = $this->apiDoc($reflection);

            if (!$doc instanceof ApiDoc) {
                continue;
            }

            $httpMethod = strtolower((string) ($route['method'] ?? ''));

            if (!in_array($httpMethod, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $documented[] = [
                'path' => $this->openApiPath((string) ($route['rule'] ?? '')),
                'method' => $httpMethod,
                'operationId' => $this->operationId($class, $method),
                'doc' => $doc,
                'location' => sprintf('%s::%s', $class, $method),
            ];
        }

        return $documented;
    }

    /**
     * @return array{0: class-string, 1: string}|null
     */
    private function endpointFromRoute(mixed $route): ?array
    {
        if (is_array($route) && isset($route[0], $route[1]) && is_string($route[0]) && is_string($route[1])) {
            return [$route[0], $route[1]];
        }

        if (!is_string($route) || !str_contains($route, '/')) {
            return null;
        }

        [$class, $method] = explode('/', $route, 2);

        if (class_exists($class)) {
            return [$class, $method];
        }

        if (!str_starts_with($class, $this->app->getNamespace() . '\\')) {
            return null;
        }

        return [$class, $method];
    }

    private function apiDoc(ReflectionMethod $method): ?ApiDoc
    {
        $attributes = $method->getAttributes(ApiDoc::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    private function openApiPath(string $rule): string
    {
        $path = '/' . trim($rule, '/');

        return preg_replace('/<([A-Za-z_][A-Za-z0-9_]*)>/', '{$1}', $path) ?? $path;
    }

    /**
     * @param class-string $class
     */
    private function operationId(string $class, string $method): string
    {
        $shortName = (new ReflectionClass($class))->getShortName();

        return lcfirst($shortName) . ucfirst($method);
    }

    /**
     * @param list<array{path: string, method: string, operationId: string, doc: ApiDoc, location: string}> $routes
     */
    private function lintRoutes(array $routes): void
    {
        $pathMethods = [];
        $operationIds = [];

        foreach ($routes as $route) {
            $pathMethod = $route['method'] . ' ' . $route['path'];

            if (isset($pathMethods[$pathMethod])) {
                $this->addIssue(
                    'duplicate-path-method',
                    $pathMethod,
                    sprintf('Duplicate documented route. First seen at %s, repeated at %s.', $pathMethods[$pathMethod], $route['location']),
                );
            } else {
                $pathMethods[$pathMethod] = $route['location'];
            }

            if (isset($operationIds[$route['operationId']])) {
                $this->addIssue(
                    'duplicate-operation-id',
                    $route['operationId'],
                    sprintf('Duplicate operationId. First seen at %s, repeated at %s.', $operationIds[$route['operationId']], $route['location']),
                );
            } else {
                $operationIds[$route['operationId']] = $route['location'];
            }

            $this->lintResponseProvider($route['doc'], $route['location']);
        }
    }

    private function lintResponseProvider(ApiDoc $doc, string $location): void
    {
        if ($doc->response === null) {
            return;
        }

        if (!class_exists($doc->response)) {
            $this->addIssue(
                'response-provider',
                $location,
                sprintf('Response SchemaProvider class [%s] does not exist.', $doc->response),
            );

            return;
        }

        if (!is_subclass_of($doc->response, SchemaProvider::class)) {
            $this->addIssue(
                'response-provider',
                $location,
                sprintf('Response class [%s] must implement %s.', $doc->response, SchemaProvider::class),
            );
        }
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function lintOpenApi(array $openApi): void
    {
        $this->lintRefs($openApi);
        $this->lintSpecOperationIds($openApi);
        $this->lintRequestBodyProperties($openApi);
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function lintRefs(array $openApi): void
    {
        $this->walk($openApi, '#', function (mixed $value, string $path) use ($openApi): void {
            if (!$this->isAssoc($value) || !isset($value['$ref']) || !is_string($value['$ref'])) {
                return;
            }

            $ref = $value['$ref'];

            if (!str_starts_with($ref, '#/')) {
                $this->addIssue('unresolved-ref', $path . '/$ref', sprintf('Reference [%s] is not a local JSON pointer and cannot be resolved.', $ref));
                return;
            }

            if ($this->resolveJsonPointer($openApi, $ref) === null) {
                $this->addIssue('unresolved-ref', $path . '/$ref', sprintf('Reference [%s] cannot be resolved.', $ref));
            }
        });
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function lintSpecOperationIds(array $openApi): void
    {
        $operationIds = [];
        $paths = $openApi['paths'] ?? [];

        if (!is_array($paths)) {
            return;
        }

        foreach ($paths as $path => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (!is_array($operation) || !isset($operation['operationId']) || !is_string($operation['operationId'])) {
                    continue;
                }

                $location = sprintf('#/paths/%s/%s/operationId', $this->escapeJsonPointer((string) $path), $method);

                if (isset($operationIds[$operation['operationId']])) {
                    $this->addIssue(
                        'duplicate-operation-id',
                        $location,
                        sprintf('Duplicate operationId [%s]. First seen at %s.', $operation['operationId'], $operationIds[$operation['operationId']]),
                    );
                } else {
                    $operationIds[$operation['operationId']] = $location;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $openApi
     */
    private function lintRequestBodyProperties(array $openApi): void
    {
        $paths = $openApi['paths'] ?? [];

        if (!is_array($paths)) {
            return;
        }

        foreach ($paths as $path => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (!is_array($operation)) {
                    continue;
                }

                $schema = $operation['requestBody']['content']['application/json']['schema'] ?? null;

                if (!is_array($schema)) {
                    continue;
                }

                $location = sprintf(
                    '#/paths/%s/%s/requestBody/content/application~1json/schema',
                    $this->escapeJsonPointer((string) $path),
                    $method,
                );

                $resolvedSchema = $this->schemaForLint($openApi, $schema);

                if ($resolvedSchema === null) {
                    continue;
                }

                $this->lintEmptyProperties($resolvedSchema, $location);
            }
        }
    }

    /**
     * @param array<string, mixed> $openApi
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    private function schemaForLint(array $openApi, array $schema): ?array
    {
        if (!isset($schema['$ref']) || !is_string($schema['$ref'])) {
            return $schema;
        }

        $resolved = $this->resolveJsonPointer($openApi, $schema['$ref']);

        return is_array($resolved) ? $resolved : null;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function lintEmptyProperties(array $schema, string $location): void
    {
        if (isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] === []) {
            $this->addIssue('empty-request-properties', $location . '/properties', 'Request body object schema has empty properties.');
        }

        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $this->lintEmptyProperties($value, $location . '/' . $this->escapeJsonPointer((string) $key));
            }
        }
    }

    /**
     * @param array<string, mixed> $openApi
     * @param list<array{path: string, method: string, operationId: string, doc: ApiDoc, location: string}> $routes
     */
    private function lintRegisteredEnumSchemas(array $openApi, array $routes): void
    {
        $schemas = $openApi['components']['schemas'] ?? [];

        if (!is_array($schemas)) {
            $schemas = [];
        }

        foreach ($routes as $route) {
            foreach ($this->enumRules($this->validationRules($route['doc'])) as $enum) {
                $name = EnumSchema::name($enum);

                if (!isset($schemas[$name]) || !is_array($schemas[$name]) || !array_key_exists('enum', $schemas[$name])) {
                    $this->addIssue(
                        'enum-schema',
                        $route['location'],
                        sprintf('Enum schema [%s] for [%s] is not registered in components.schemas.', $name, $enum),
                    );
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(ApiDoc $doc): array
    {
        if ($doc->validate === null || !class_exists($doc->validate)) {
            return [];
        }

        /** @var Validate $validator */
        $validator = new $doc->validate();
        $rules = $this->protectedProperty($validator, 'rule');

        if (!is_array($rules)) {
            return [];
        }

        if ($doc->scene === null) {
            return $rules;
        }

        $scenes = $this->protectedProperty($validator, 'scene');
        $fields = is_array($scenes) ? ($scenes[$doc->scene] ?? []) : [];

        if (!is_array($fields)) {
            return [];
        }

        return array_intersect_key($rules, array_flip($fields));
    }

    private function protectedProperty(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<class-string<UnitEnum>>
     */
    private function enumRules(array $rules): array
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
     * @return class-string<UnitEnum>|null
     */
    private function enumFromRule(mixed $rule): ?string
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
     * @return list<mixed>
     */
    private function rawRuleParts(mixed $rule): array
    {
        if (is_string($rule)) {
            return explode('|', $rule);
        }

        if (is_array($rule)) {
            return array_values($rule);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function resolveJsonPointer(array $document, string $ref): mixed
    {
        if (!str_starts_with($ref, '#/')) {
            return null;
        }

        $current = $document;
        $segments = explode('/', substr($ref, 2));

        foreach ($segments as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param callable(mixed, string): void $callback
     */
    private function walk(mixed $value, string $path, callable $callback): void
    {
        $callback($value, $path);

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $this->walk($child, $path . '/' . $this->escapeJsonPointer((string) $key), $callback);
        }
    }

    private function escapeJsonPointer(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }

    private function isAssoc(mixed $value): bool
    {
        return is_array($value) && array_keys($value) !== range(0, count($value) - 1);
    }

    private function addIssue(string $rule, string $location, string $message): void
    {
        $this->issues[] = new OpenApiLintIssue($rule, $location, $message);
    }
}
