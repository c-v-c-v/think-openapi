<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use think\App;

interface RouteListProvider
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function routes(App $app): array;
}
