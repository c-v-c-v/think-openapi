<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

interface RouteAuthResolver
{
    /**
     * @param array<int, mixed> $middleware
     */
    public function resolve(array $middleware): AuthRequirement;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function securitySchemes(): array;
}
