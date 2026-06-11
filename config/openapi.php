<?php

use Cvcv\ThinkOpenApi\OpenApi\Response\ResultEnvelopeSchemaFactory;
use Cvcv\ThinkOpenApi\OpenApi\Security\BearerMiddlewareSecurityInspector;

return [
    'title' => 'ThinkPHP OpenAPI',
    'version' => '0.1.0',
    'servers' => [
        ['url' => '/'],
    ],
    'json_url' => '/docs/api.json',
    'spec_path' => 'runtime/docs/openapi.json',
    'production_envs' => ['prod', 'production'],
    'regenerate_on_request' => filter_var(env('DOCS_REGENERATE_ON_REQUEST', false), FILTER_VALIDATE_BOOLEAN),
    'routes' => [
        'enabled' => true,
        'scalar' => 'docs/api',
        'stoplight' => 'docs/api/stoplight',
        'json' => 'docs/api.json',
    ],
    'views_path' => null,
    'response_schema_factory' => ResultEnvelopeSchemaFactory::class,
    'security' => [
        'inspectors' => [
            BearerMiddlewareSecurityInspector::class,
        ],
        'bearer' => [
            'middleware' => null,
            'scheme_name' => 'bearerAuth',
            'description' => 'Bearer Token authentication.',
        ],
    ],
];
