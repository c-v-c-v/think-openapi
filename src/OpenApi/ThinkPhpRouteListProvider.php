<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use DirectoryIterator;
use think\App;
use think\event\RouteLoaded;

final class ThinkPhpRouteListProvider implements RouteListProvider
{
    public function routes(App $app): array
    {
        $app->route->clear();
        $app->route->lazy(false);

        $routePath = $app->getRootPath() . 'route' . DIRECTORY_SEPARATOR;
        if (is_dir($routePath)) {
            $this->scanRoute($app, $routePath, $routePath, (bool) $app->route->config('route_auto_group'));
        }

        $app->event->trigger(RouteLoaded::class);

        return $app->route->getRuleList();
    }

    private function scanRoute(App $app, string $path, string $root, bool $autoGroup): void
    {
        $iterator = new DirectoryIterator($path);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            if ($fileinfo->isFile() && $fileinfo->getExtension() === 'php') {
                $groupName = str_replace('\\', '/', substr_replace($fileinfo->getPath(), '', 0, strlen($root)));

                if ($groupName !== '') {
                    $app->route->group($groupName, function () use ($fileinfo): void {
                        include $fileinfo->getRealPath();
                    });
                    continue;
                }

                include $fileinfo->getRealPath();
                continue;
            }

            if ($autoGroup && $fileinfo->isDir()) {
                $this->scanRoute($app, $fileinfo->getPathname(), $root, $autoGroup);
            }
        }
    }
}
