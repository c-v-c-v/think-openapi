<?php

namespace Cvcv\ThinkOpenApi;

use Cvcv\ThinkOpenApi\Command\GenerateOpenApi;
use Cvcv\ThinkOpenApi\Command\LintOpenApi;
use think\Service as ThinkService;

final class Service extends ThinkService
{
    public function boot(): void
    {
        $this->commands([GenerateOpenApi::class, LintOpenApi::class]);

        if ((bool) $this->app->config->get('openapi.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/docs.php');
        }
    }
}
