<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

final readonly class MiddlewareAuthResolver implements RouteAuthResolver
{
    /**
     * @param array<int, MiddlewareSecurityInspector> $inspectors
     */
    public function __construct(private array $inspectors)
    {
    }

    public static function defaults(): self
    {
        return new self([]);
    }

    public function resolve(array $middleware): AuthRequirement
    {
        $state = new AuthState();

        foreach ($middleware as $item) {
            $normalized = $this->normalize($item);

            if ($normalized === null) {
                continue;
            }

            [$class, $parameters] = $normalized;

            foreach ($this->inspectors as $inspector) {
                $inspector->inspect($class, $parameters, $state);
            }
        }

        return $state->requirement();
    }

    public function securitySchemes(): array
    {
        $schemes = [];

        foreach ($this->inspectors as $inspector) {
            $schemes = [...$schemes, ...$inspector->securitySchemes()];
        }

        return $schemes;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}|null
     */
    private function normalize(mixed $middleware): ?array
    {
        if (is_string($middleware)) {
            return [$middleware, []];
        }

        if (!is_array($middleware) || !isset($middleware[0]) || !is_string($middleware[0])) {
            return null;
        }

        $parameters = $middleware[1] ?? [];

        if (!is_array($parameters)) {
            $parameters = [$parameters];
        }

        return [$middleware[0], array_values($parameters)];
    }
}
