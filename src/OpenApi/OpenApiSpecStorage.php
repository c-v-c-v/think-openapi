<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use think\App;

final readonly class OpenApiSpecStorage
{
    public function __construct(private App $app)
    {
    }

    public function path(?string $path = null): string
    {
        $path = $path ?: (string) $this->app->config->get('openapi.spec_path', 'runtime/docs/openapi.json');

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->app->getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, ltrim($path, '\\/'));
    }

    public function exists(?string $path = null): bool
    {
        return is_file($this->path($path));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(?string $path = null): ?array
    {
        $target = $this->path($path);

        if (!is_file($target)) {
            return null;
        }

        $content = file_get_contents($target);
        $data = is_string($content) ? json_decode($content, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $openApi
     */
    public function write(array $openApi, ?string $path = null): ?string
    {
        $target = $this->path($path);
        $directory = dirname($target);

        if (file_exists($directory) && !is_dir($directory)) {
            return null;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $json = json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return null;
        }

        if (file_put_contents($target, $json . PHP_EOL) === false) {
            return null;
        }

        return $target;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
