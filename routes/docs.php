<?php

use Cvcv\ThinkOpenApi\Http\Controller\ApiDocs;
use think\facade\Route;

Route::get((string) app()->config->get('openapi.routes.scalar', 'docs/api'), [ApiDocs::class, 'scalar'])
    ->completeMatch();

Route::get((string) app()->config->get('openapi.routes.stoplight', 'docs/api/stoplight'), [ApiDocs::class, 'stoplight'])
    ->completeMatch();

Route::get((string) app()->config->get('openapi.routes.json', 'docs/api.json'), [ApiDocs::class, 'json'])
    ->completeMatch();
