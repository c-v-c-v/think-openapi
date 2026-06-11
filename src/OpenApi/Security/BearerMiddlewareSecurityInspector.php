<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

use think\App;

final readonly class BearerMiddlewareSecurityInspector implements MiddlewareSecurityInspector
{
    public function __construct(private App $app)
    {
    }

    public function inspect(string $middleware, array $parameters, AuthState $state): void
    {
        $configured = $this->middleware();

        if ($configured === null || $middleware !== $configured) {
            return;
        }

        $state->requireSecurity([$this->schemeName() => []]);
        $state->addDescription($this->description());
    }

    public function securitySchemes(): array
    {
        if ($this->middleware() === null) {
            return [];
        }

        return [
            $this->schemeName() => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'token',
                'description' => $this->description(),
            ],
        ];
    }

    private function middleware(): ?string
    {
        $middleware = $this->app->config->get('openapi.security.bearer.middleware');

        return is_string($middleware) && $middleware !== '' ? $middleware : null;
    }

    private function schemeName(): string
    {
        return (string) $this->app->config->get('openapi.security.bearer.scheme_name', 'bearerAuth');
    }

    private function description(): string
    {
        return (string) $this->app->config->get('openapi.security.bearer.description', 'Bearer Token authentication.');
    }
}
