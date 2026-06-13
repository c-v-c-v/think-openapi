<?php

namespace Cvcv\ThinkOpenApi\Http\Controller;

use Cvcv\ThinkOpenApi\Http\DocsAccess;
use Cvcv\ThinkOpenApi\OpenApi\Generator;
use Cvcv\ThinkOpenApi\OpenApi\OpenApiSpecStorage;
use think\App;
use think\Response;
use think\response\Json;

final readonly class ApiDocs
{
    public function __construct(
        private App $app,
        private DocsAccess $access,
        private OpenApiSpecStorage $storage,
        private Generator $generator,
    ) {
    }

    public function scalar(): Response
    {
        if (!$this->access->allowed()) {
            return response('', 404);
        }

        return $this->render('scalar', [
            'title' => $this->title(),
            'specUrl' => $this->specUrl(),
            'scalarScriptUrl' => $this->scalarScriptUrl(),
        ]);
    }

    public function stoplight(): Response
    {
        if (!$this->access->allowed()) {
            return response('', 404);
        }

        return $this->render('stoplight', [
            'title' => $this->title(),
            'specUrl' => $this->specUrl(),
            'stoplightScriptUrl' => $this->stoplightScriptUrl(),
            'stoplightStylesUrl' => $this->stoplightStylesUrl(),
        ]);
    }

    public function json(): Json|Response
    {
        if (!$this->access->allowed()) {
            return response('', 404);
        }

        $data = $this->regenerateOnRequest()
            ? $this->regenerateSpec()
            : $this->storage->read();

        if ($data === null) {
            return response('', 404);
        }

        return json(
            $data,
            200,
            [],
            ['json_encode_param' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]
        );
    }

    private function title(): string
    {
        return (string) $this->app->config->get('openapi.title', 'ThinkPHP OpenAPI');
    }

    private function specUrl(): string
    {
        return (string) $this->app->config->get('openapi.json_url', '/docs/api.json');
    }

    private function scalarScriptUrl(): string
    {
        return (string) $this->app->config->get(
            'openapi.ui.scalar.script_url',
            'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
        );
    }

    private function stoplightScriptUrl(): string
    {
        return (string) $this->app->config->get(
            'openapi.ui.stoplight.script_url',
            'https://unpkg.com/@stoplight/elements/web-components.min.js',
        );
    }

    private function stoplightStylesUrl(): string
    {
        return (string) $this->app->config->get(
            'openapi.ui.stoplight.styles_url',
            'https://unpkg.com/@stoplight/elements/styles.min.css',
        );
    }

    private function regenerateOnRequest(): bool
    {
        return filter_var(
            $this->app->config->get('openapi.regenerate_on_request', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function regenerateSpec(): ?array
    {
        $data = $this->generator->generate();

        return $this->storage->write($data) === null ? null : $data;
    }

    /**
     * @param array<string, string> $variables
     */
    private function render(string $view, array $variables): Response
    {
        $path = $this->viewPath($view);

        if (!is_file($path)) {
            return response('', 404);
        }

        $html = (string) file_get_contents($path);

        foreach ($variables as $key => $value) {
            $html = str_replace('{$' . $key . '|htmlentities}', htmlentities($value, ENT_QUOTES, 'UTF-8'), $html);
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function viewPath(string $view): string
    {
        $configured = $this->app->config->get('openapi.views_path');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '\\/') . DIRECTORY_SEPARATOR . $view . '.html';
        }

        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $view . '.html';
    }
}
