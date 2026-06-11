<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

interface MiddlewareSecurityInspector
{
    /**
     * @param array<int, mixed> $parameters
     */
    public function inspect(string $middleware, array $parameters, AuthState $state): void;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function securitySchemes(): array;
}
