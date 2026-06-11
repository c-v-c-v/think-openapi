<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use Cvcv\ThinkOpenApi\Attribute\ApiField;
use Cvcv\ThinkOpenApi\Attribute\ApiGroup;
use Cvcv\ThinkOpenApi\OpenApi\Response\ResponseSchemaFactory;
use Cvcv\ThinkOpenApi\OpenApi\Response\ResultEnvelopeSchemaFactory;
use Cvcv\ThinkOpenApi\OpenApi\Security\AuthRequirement;
use Cvcv\ThinkOpenApi\OpenApi\Security\MiddlewareAuthResolver;
use Cvcv\ThinkOpenApi\OpenApi\Security\MiddlewareSecurityInspector;
use Cvcv\ThinkOpenApi\OpenApi\Security\RouteAuthResolver;
use RuntimeException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use think\App;
use think\Validate;

final class Generator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $schemas = [];

    public function __construct(
        private readonly App $app,
        private readonly ?RouteAuthResolver $authResolver = null,
        private readonly ?RouteListProvider $routeListProvider = null,
        private readonly ?ResponseSchemaFactory $responseSchemaFactory = null,
    )
    {
    }

    public function generate(): array
    {
        $paths = [];
        $this->schemas = [];

        foreach ($this->routeList() as $route) {
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

            $path = $this->openApiPath((string) $route['rule']);
            $httpMethod = strtolower((string) $route['method']);

            if (!in_array($httpMethod, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $paths[$path][$httpMethod] = $this->operation($reflection, $httpMethod, $path, $doc, $route);
        }

        ksort($paths);

        foreach ($paths as &$methods) {
            ksort($methods);
        }
        unset($methods);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => (string) $this->app->config->get('openapi.title', 'ThinkPHP OpenAPI'),
                'version' => (string) $this->app->config->get('openapi.version', '0.1.0'),
            ],
            'servers' => $this->servers(),
            'paths' => $paths,
            'components' => $this->components(),
        ];
    }

    private function routeList(): array
    {
        return ($this->routeListProvider ?? new ThinkPhpRouteListProvider())->routes($this->app);
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

    private function openApiPath(string $rule): string
    {
        $path = '/' . trim($rule, '/');

        return preg_replace('/<([A-Za-z_][A-Za-z0-9_]*)>/', '{$1}', $path) ?? $path;
    }

    private function apiDoc(ReflectionMethod $method): ?ApiDoc
    {
        $attributes = $method->getAttributes(ApiDoc::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * @param array<string, mixed> $route
     */
    private function operation(ReflectionMethod $method, string $httpMethod, string $path, ApiDoc $doc, array $route): array
    {
        $rules = $this->validationRules($doc);
        $fields = $this->validationFields($doc);
        $parameters = $this->pathParameters($path);
        $auth = $this->auth($route);
        $comment = $this->methodComment($method);
        $class = $method->getDeclaringClass()->getName();
        $summary = $doc->summary ?: ($comment['summary'] ?: $this->operationId($class, $method->getName()));
        $description = $doc->description ?: $comment['description'];

        $this->registerEnumSchemas($this->enumRules($rules));

        if ($httpMethod === 'get') {
            $parameters = array_merge($parameters, $this->queryParameters($rules, $fields));
        }

        $operation = [
            'summary' => $summary,
            'operationId' => $this->operationId($class, $method->getName()),
            'tags' => $this->tags($class, $doc),
            'parameters' => $parameters,
            'responses' => $this->responses($doc, $httpMethod),
        ];

        if ($description !== null && $description !== '') {
            $operation['description'] = $description;
        }

        if ($auth->isRequired()) {
            $operation['security'] = $auth->security;
        }

        if ($auth->descriptionParts !== []) {
            $operation['x-auth-description'] = $auth->descriptionParts;
        }

        foreach ($auth->extensions as $name => $value) {
            $operation[$name] = $value;
        }

        if (in_array($httpMethod, ['post', 'put', 'patch'], true) && $rules !== []) {
            $operation['requestBody'] = [
                'required' => $doc->requestFieldsRequired,
                'content' => [
                    'application/json' => [
                        'schema' => $this->objectSchema($rules, $doc->requestFieldsRequired, $fields),
                    ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $route
     */
    private function auth(array $route): AuthRequirement
    {
        $middleware = $route['option']['middleware'] ?? [];

        if (!is_array($middleware)) {
            $middleware = [];
        }

        return $this->authResolver()->resolve($middleware);
    }

    private function authResolver(): RouteAuthResolver
    {
        return $this->authResolver ?? new MiddlewareAuthResolver($this->securityInspectors());
    }

    /**
     * @return list<MiddlewareSecurityInspector>
     */
    private function securityInspectors(): array
    {
        $inspectors = $this->app->config->get('openapi.security.inspectors', []);

        if (!is_array($inspectors)) {
            return [];
        }

        $resolved = [];

        foreach ($inspectors as $inspector) {
            if ($inspector instanceof MiddlewareSecurityInspector) {
                $resolved[] = $inspector;
                continue;
            }

            if (is_string($inspector) && class_exists($inspector)) {
                $instance = $this->app->make($inspector);

                if ($instance instanceof MiddlewareSecurityInspector) {
                    $resolved[] = $instance;
                }
            }
        }

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function servers(): array
    {
        $servers = $this->app->config->get('openapi.servers', [['url' => '/']]);

        return is_array($servers) && $servers !== [] ? array_values($servers) : [['url' => '/']];
    }

    /**
     * @return array{summary: string, description: string|null}
     */
    private function methodComment(ReflectionMethod $method): array
    {
        $comment = $method->getDocComment();

        if ($comment === false) {
            return ['summary' => '', 'description' => null];
        }

        $lines = preg_split('/\R/u', $comment) ?: [];
        $text = [];

        foreach ($lines as $line) {
            $line = $this->cleanDocCommentLine($line);

            if (str_starts_with(ltrim($line), '@')) {
                break;
            }

            $text[] = $line;
        }

        $text = $this->trimBlankLines($text);

        if ($text === []) {
            return ['summary' => '', 'description' => null];
        }

        $summary = trim((string) array_shift($text));
        $description = implode("\n", $this->trimBlankLines($text));

        return [
            'summary' => $summary,
            'description' => $description === '' ? null : $description,
        ];
    }

    private function cleanDocCommentLine(string $line): string
    {
        $line = rtrim($line, " \t\r\n");
        $line = ltrim($line, " \t");

        if (str_starts_with($line, '/**')) {
            $line = substr($line, 3);
        } elseif (str_starts_with($line, '/*')) {
            $line = substr($line, 2);
        }

        if (str_ends_with($line, '*/')) {
            $line = substr($line, 0, -2);
            $line = rtrim($line, " \t");
        }

        $line = ltrim($line, " \t");

        if (str_starts_with($line, '*')) {
            $line = substr($line, 1);

            if (str_starts_with($line, ' ')) {
                $line = substr($line, 1);
            }
        }

        return rtrim($this->cleanUtf8($line), " \t\r\n");
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function trimBlankLines(array $lines): array
    {
        while ($lines !== [] && trim((string) $lines[0]) === '') {
            array_shift($lines);
        }

        while ($lines !== [] && trim((string) $lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        return array_values($lines);
    }

    private function cleanUtf8(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($cleaned) ? $cleaned : '';
    }

    /**
     * @param class-string $class
     * @return array<int, string>
     */
    private function tags(string $class, ApiDoc $doc): array
    {
        $tags = [];
        $attributes = (new ReflectionClass($class))->getAttributes(ApiGroup::class);

        if ($attributes !== []) {
            $tags = $attributes[0]->newInstance()->tags;
        }

        return array_values(array_unique([...$tags, ...$doc->tags]));
    }

    /**
     * @return array<string, string>
     */
    private function validationFields(ApiDoc $doc): array
    {
        if ($doc->validate === null || !class_exists($doc->validate)) {
            return [];
        }

        /** @var Validate $validator */
        $validator = new $doc->validate();
        $property = $this->protectedPropertyReflection($validator, 'field');
        $fields = $property->getValue($validator);

        if (!is_array($fields)) {
            return [];
        }

        $fields = array_filter($fields, is_string(...));
        $apiFields = $this->apiFieldDescriptions($property);

        foreach ($apiFields as $name => $description) {
            if (isset($fields[$name])) {
                $fields[$name] = $fields[$name] === ''
                    ? $description
                    : $fields[$name] . '；' . $description;
            } else {
                $fields[$name] = $description;
            }
        }

        return $fields;
    }

    private function operationId(string $class, string $method): string
    {
        $shortName = (new ReflectionClass($class))->getShortName();

        return lcfirst($shortName) . ucfirst($method);
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
        $property = $this->protectedPropertyReflection($object, $name);

        return $property->getValue($object);
    }

    private function protectedPropertyReflection(object $object, string $name): ReflectionProperty
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * @return array<string, string>
     */
    private function apiFieldDescriptions(ReflectionProperty $property): array
    {
        $attributes = $property->getAttributes(ApiField::class);

        if ($attributes === []) {
            return [];
        }

        /** @var ApiField $apiField */
        $apiField = $attributes[0]->newInstance();

        return array_filter(
            $apiField->descriptions,
            static fn (mixed $description): bool => is_string($description) && $description !== '',
        );
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, string> $fields
     * @return array<int, array<string, mixed>>
     */
    private function queryParameters(array $rules, array $fields): array
    {
        $parameters = [];

        foreach ($rules as $name => $rule) {
            $parameter = [
                'name' => $this->queryName($name),
                'in' => 'query',
                'required' => $this->isRequired($rule),
                'schema' => $this->schemaFromRule($rule, $fields[$name] ?? null),
            ];

            if (isset($fields[$name])) {
                $parameter['description'] = $fields[$name];
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    private function queryName(string $name): string
    {
        if (!str_contains($name, '.')) {
            return $name;
        }

        $segments = explode('.', $name);
        $first = array_shift($segments);

        return $first . '[' . implode('][', $segments) . ']';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $matches);
        $parameters = [];

        foreach ($matches[1] as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => $name === 'id' ? 'integer' : 'string',
                ],
            ];
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function objectSchema(array $rules, bool $includeRequired, array $fields): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($rules as $name => $rule) {
            $this->addObjectProperty(
                $schema,
                explode('.', $name),
                $this->schemaFromRule($rule, $fields[$name] ?? null),
                $includeRequired && $this->isRequired($rule),
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

    /**
     * @return array<string, mixed>
     */
    private function schemaFromRule(mixed $rule, ?string $description = null): array
    {
        $enum = $this->enumFromRule($rule);

        if ($enum !== null) {
            return EnumSchema::reference($enum, $description);
        }

        $parts = $this->ruleParts($rule);
        $schema = [
            'type' => in_array('integer', $parts, true) ? 'integer' : 'string',
        ];

        if ($description !== null && $description !== '') {
            $schema['description'] = $description;
        }

        foreach ($parts as $part) {
            if (str_starts_with($part, 'max:') && $schema['type'] === 'string') {
                $schema['maxLength'] = (int) substr($part, 4);
                continue;
            }

            if (str_starts_with($part, 'min:') && $schema['type'] === 'string') {
                $schema['minLength'] = (int) substr($part, 4);
                continue;
            }

            if (str_starts_with($part, 'between:')) {
                [$minimum, $maximum] = array_map('intval', explode(',', substr($part, 8), 2));
                $schema['minimum'] = $minimum;
                $schema['maximum'] = $maximum;
                continue;
            }

            if (str_starts_with($part, 'in:')) {
                $schema['enum'] = explode(',', substr($part, 3));
            }
        }

        return $schema;
    }

    private function isRequired(mixed $rule): bool
    {
        return in_array('require', $this->ruleParts($rule), true);
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<class-string<\UnitEnum>>
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
     * @return class-string<\UnitEnum>|null
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
     * @return list<string>
     */
    private function ruleParts(mixed $rule): array
    {
        return array_values(array_filter(
            $this->rawRuleParts($rule),
            static fn (mixed $part): bool => is_string($part) && !enum_exists($part) && !str_starts_with($part, 'enum:'),
        ));
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
     * @return array<string, mixed>
     */
    private function responses(ApiDoc $doc, string $httpMethod): array
    {
        $status = $this->responseStatus($doc, $httpMethod);

        if ($status === 204) {
            return [
                '204' => [
                    'description' => 'No Content',
                ],
            ];
        }

        return [
            (string) $status => [
                'description' => $this->responseDescription($status),
                'content' => [
                    'application/json' => [
                        'schema' => $this->resultSchema($doc, $httpMethod),
                    ],
                ],
            ],
        ];
    }

    private function responseStatus(ApiDoc $doc, string $httpMethod): int
    {
        return $doc->status ?? match ($httpMethod) {
            'post' => 201,
            'delete' => 204,
            default => 200,
        };
    }

    private function responseDescription(int $status): string
    {
        return match ($status) {
            201 => 'Created',
            204 => 'No Content',
            default => 'OK',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resultSchema(ApiDoc $doc, string $httpMethod): array
    {
        $dataSchema = $this->dataSchema($doc, $httpMethod);
        $status = $this->responseStatus($doc, $httpMethod);

        return $this->responseSchemaFactory()->schema($dataSchema, $doc, $httpMethod, $status);
    }

    private function responseSchemaFactory(): ResponseSchemaFactory
    {
        if ($this->responseSchemaFactory instanceof ResponseSchemaFactory) {
            return $this->responseSchemaFactory;
        }

        $factory = $this->app->config->get('openapi.response_schema_factory', ResultEnvelopeSchemaFactory::class);

        if (is_string($factory) && class_exists($factory)) {
            $instance = $this->app->make($factory);

            if ($instance instanceof ResponseSchemaFactory) {
                return $instance;
            }
        }

        return new ResultEnvelopeSchemaFactory();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataSchema(ApiDoc $doc, string $httpMethod): array
    {
        $responseType = $this->responseType($doc, $httpMethod);
        $status = $this->responseStatus($doc, $httpMethod);

        if ($responseType !== ResponseDataType::None && !is_subclass_of($doc->response, SchemaProvider::class)) {
            throw new RuntimeException(sprintf(
                'Response class [%s] must implement %s.',
                $doc->response,
                SchemaProvider::class,
            ));
        }

        $schemaName = null;
        $itemSchema = null;

        if ($responseType !== ResponseDataType::None) {
            /** @var class-string<SchemaProvider> $response */
            $response = $doc->response;
            $this->registerSchemas($response::openApiSchemas());
            $schemaName = $response::openApiSchemaName();
            $itemSchema = ['$ref' => '#/components/schemas/' . $schemaName];
        }

        $dataSchema = $responseType->dataSchema(
            $schemaName,
            $itemSchema,
            $doc,
            $httpMethod,
            $status,
        );
        $this->registerSchemas($dataSchema->components);

        return $dataSchema->schema;
    }

    private function responseType(ApiDoc $doc, string $httpMethod): ResponseType
    {
        if ($this->responseStatus($doc, $httpMethod) === 204 || $doc->response === null) {
            return ResponseDataType::None;
        }

        if ($doc->responseType instanceof ResponseType) {
            return $doc->responseType;
        }

        return ResponseDataType::Item;
    }

    /**
     * @param array<string, class-string<\UnitEnum>> $enums
     */
    private function registerEnumSchemas(array $enums): void
    {
        foreach (array_unique($enums) as $enum) {
            $this->schemas[EnumSchema::name($enum)] = EnumSchema::schema($enum);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $schemas
     */
    private function registerSchemas(array $schemas): void
    {
        foreach ($schemas as $name => $schema) {
            $this->schemas[$name] = $schema;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function components(): array
    {
        return [
            'securitySchemes' => $this->authResolver()->securitySchemes(),
            'schemas' => $this->schemas,
        ];
    }
}
