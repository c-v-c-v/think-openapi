<?php

namespace Cvcv\ThinkOpenApi\Http;

use think\App;

final readonly class DocsAccess
{
    public function __construct(private App $app)
    {
    }

    public function allowed(): bool
    {
        return !in_array($this->environment(), $this->productionEnvironments(), true);
    }

    private function environment(): string
    {
        return strtolower((string) $this->app->config->get('app.env', 'dev'));
    }

    /**
     * @return list<string>
     */
    private function productionEnvironments(): array
    {
        $environments = $this->app->config->get('openapi.production_envs', ['prod', 'production']);

        if (!is_array($environments)) {
            return ['prod', 'production'];
        }

        return array_values(array_map(
            static fn (mixed $environment): string => strtolower((string) $environment),
            $environments
        ));
    }
}
