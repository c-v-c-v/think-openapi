# Think OpenAPI

Think OpenAPI 是一个面向 ThinkPHP 8 API 项目的轻量级 OpenAPI 3.1 生成器和文档 UI 组件。

它会读取 ThinkPHP 路由、`#[ApiDoc]` Attribute、`think\Validate` 验证规则、响应 SchemaProvider 和路由中间件信息，生成标准 OpenAPI JSON 文档。同时内置 Scalar 和 Stoplight Elements 文档页面。

## 功能特性

- 从 ThinkPHP 路由定义生成 OpenAPI 3.1 JSON。
- 使用少量 PHP 8 Attribute 描述接口，避免编写大量 OpenAPI 注解。
- 复用 `think\Validate` 规则生成查询参数和 JSON 请求体 schema。
- 使用普通 PHP 类定义可复用响应 schema。
- 支持单项、列表、分页、空响应和自定义响应数据结构。
- 根据配置的中间件识别 Bearer 认证。
- 内置 Scalar、Stoplight 和原始 JSON 文档路由。
- 提供 lint 命令，检查重复路由、重复 operationId、失效 `$ref`、无效响应 provider 和空请求 schema。

## 环境要求

- PHP 8.2 或更高版本
- ThinkPHP 8

## 安装

```bash
composer require cvcv/think-openapi
```

本包通过 Composer metadata 自动注册 ThinkPHP 服务。默认配置位于包内的 `config/openapi.php`，你可以在应用配置中覆盖这些选项。

## 快速开始

给需要出现在文档中的控制器方法添加 `#[ApiDoc]`。没有 `#[ApiDoc]` 的方法会被忽略。

```php
<?php

namespace app\controller;

use app\validate\UserValidate;
use app\resource\UserResource;
use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use Cvcv\ThinkOpenApi\Attribute\ApiGroup;
use Cvcv\ThinkOpenApi\OpenApi\ResponseDataType;
use think\response\Json;

#[ApiGroup(tags: ['Users'])]
final class UserController
{
    #[ApiDoc(
        summary: '用户列表',
        validate: UserValidate::class,
        scene: 'index',
        response: UserResource::class,
        responseType: ResponseDataType::Page,
    )]
    public function index(): Json
    {
        return json();
    }

    #[ApiDoc(
        summary: '创建用户',
        tags: ['Write'],
        validate: UserValidate::class,
        scene: 'store',
        response: UserResource::class,
    )]
    public function save(): Json
    {
        return json();
    }

    #[ApiDoc(
        summary: '删除用户',
    )]
    public function delete(): \think\Response
    {
        return response('', 204);
    }
}
```

照常定义路由：

```php
<?php

use app\controller\UserController;
use think\facade\Route;

Route::get('users', [UserController::class, 'index']);
Route::post('users', [UserController::class, 'save']);
Route::delete('users/<id>', [UserController::class, 'delete']);
```

生成 OpenAPI 文件：

```bash
php think docs:generate
```

访问文档页面：

- Scalar：`/docs/api`
- Stoplight Elements：`/docs/api/stoplight`
- OpenAPI JSON：`/docs/api.json`

## Operation 扩展字段

如果需要给单个 operation 添加 OpenAPI 扩展字段，可以使用 `ApiDoc::extensions`。只会透传 `x-*` 开头的字段；如果同名字段已经由认证解析器等内部逻辑生成，则不会被覆盖。

```php
#[ApiDoc(
    summary: '用户列表',
    response: UserResource::class,
    extensions: [
        'x-code-samples' => [
            [
                'lang' => 'curl',
                'source' => 'curl /users',
            ],
        ],
    ],
)]
public function index(): Json
{
    return json();
}
```

## 参数覆盖

如果 Validate 或路由参数推导不够精确，可以使用可重复的 `#[ApiParam]` 覆盖已有参数，或追加 header、cookie 等参数。同名且 `in` 相同的参数会合并；path 参数始终保持 `required: true`，且只能覆盖路由模板中已经存在的 path 参数。

```php
use Cvcv\ThinkOpenApi\Attribute\ApiParam;

#[ApiParam(
    name: 'page[number]',
    in: 'query',
    description: '页码',
    schema: ['type' => 'integer', 'minimum' => 1],
    example: 1,
)]
#[ApiParam(
    name: 'X-Request-ID',
    in: 'header',
    description: '请求 ID',
    schema: ['type' => 'string'],
)]
public function index(): Json
{
    return json();
}
```

## 响应覆盖

如果需要补充错误响应或覆盖默认响应描述，可以使用可重复的 `#[ApiResponse]`。同状态码响应会浅合并，不存在的状态码会追加；扩展字段只透传 `x-*`。`status` 可以是 HTTP 状态码或 `default`；响应会始终包含 `description`，`204` 响应会忽略 `content`。

```php
use Cvcv\ThinkOpenApi\Attribute\ApiResponse;

#[ApiResponse(
    status: 400,
    description: '验证失败',
    content: [
        'application/json' => [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                ],
            ],
        ],
    ],
)]
public function save(): Json
{
    return json();
}
```

## Validate 规则

当设置了 `ApiDoc::validate` 时，生成器会读取验证器中的 protected `$rule`、`$field` 和 `$scene` 属性。

对于 `GET` 接口，验证规则会转换为 query 参数。点号字段会转换为方括号参数名，例如 `page.number` 会变成 `page[number]`。

对于 `POST`、`PUT` 和 `PATCH` 接口，验证规则会转换为请求体 schema。默认使用 `application/json`；如果规则包含 `file`、`image`、`fileExt`、`fileMime` 或 `fileSize`，会使用 `multipart/form-data`。点号字段会转换为嵌套对象属性。

```php
<?php

namespace app\validate;

use app\enum\UserStatus;
use think\Validate;

final class UserValidate extends Validate
{
    protected $rule = [
        'filter.name' => 'string|max:255',
        'page.number' => 'integer|between:1,100000',
        'profile.name' => 'require|string|max:255',
        'profile.email' => 'require|string|max:255',
        'status' => 'require|string|enum:' . UserStatus::class,
        'enabled' => 'boolean',
        'tags' => 'array',
    ];

    protected $field = [
        'filter.name' => '名称筛选',
        'page.number' => '页码',
        'profile.name' => '用户名称',
        'profile.email' => '邮箱地址',
        'status' => '用户状态',
        'enabled' => '是否启用',
        'tags' => '标签列表',
    ];

    protected $scene = [
        'index' => ['filter.name', 'page.number'],
        'store' => ['profile.name', 'profile.email', 'status', 'enabled', 'tags'],
    ];
}
```

当前支持的 schema 推导规则包括：

- `require` -> `required`
- `integer` / `int` -> `type: integer`
- `number` / `float` -> `type: number`
- `boolean` / `bool` -> `type: boolean`
- `array` -> `type: array`
- `email` -> `format: email`
- `url` -> `format: uri`
- `date` -> `format: date`
- `dateFormat:Y-m-d` / `date_format:Y-m-d H:i:s` -> `format: date` 或 `format: date-time`，并保留 `x-thinkphp-rule`
- `ip` / `ip:ipv4` / `ip:ipv6` -> `format: ipv4` / `format: ipv6`
- 字符串上的 `max:n` / `min:n` / `length:n` / `length:min,max` -> `maxLength` / `minLength`
- 数组上的 `max:n` / `min:n` / `length:n` / `length:min,max` -> `maxItems` / `minItems`
- `between:min,max` -> `minimum` / `maximum`
- `egt:n`、`>=:n`、`gt:n`、`>:n`、`elt:n`、`<=:n`、`lt:n`、`<:n`
- `multipleOf:n` -> `multipleOf`
- `in:a,b,c` -> 按字段类型生成内联 enum 值
- `notIn:a,b,c` -> `not.enum`
- `file` / `image` / `fileExt` / `fileMime` / `fileSize` -> `multipart/form-data` 文件字段，schema 为 `type: string`、`format: binary`，ThinkPHP 文件约束保留在 `x-thinkphp-rule`
- `regex:/.../` -> `pattern`；带修饰符或无法安全转换时保留 `x-thinkphp-rule`
- `alpha`、`alphaNum`、`alphaDash`、`chs`、`chsAlpha`、`chsAlphaNum`、`chsDash`、`mobile`、`idCard`、`zip` -> 基于 ThinkPHP 内置正则生成 `pattern`
- `['max' => 10]`、`['in' => ['a', 'b']]` 等关联数组规则会按 ThinkPHP 规则名归一化后推导
- PHP enum 或 `enum:EnumClass` -> 可复用 enum 组件 schema

## 响应 Schema

响应类必须实现 `SchemaProvider`。

```php
<?php

namespace app\resource;

use Cvcv\ThinkOpenApi\OpenApi\ObjectSchema;
use Cvcv\ThinkOpenApi\OpenApi\Schema;
use Cvcv\ThinkOpenApi\OpenApi\SchemaProvider;

final class UserResource implements SchemaProvider
{
    public static function openApiSchemaName(): string
    {
        return 'User';
    }

    public static function openApiSchemas(): array
    {
        return ObjectSchema::make(self::openApiSchemaName())
            ->property('id', Schema::integer('用户 ID'), required: true)
            ->property('name', Schema::string('用户名称'), required: true)
            ->property('email', Schema::format(Schema::string('邮箱地址'), 'email'), required: true)
            ->components();
    }
}
```

常用 schema 元数据可以用 helper 叠加：

```php
Schema::example(Schema::string('用户名称'), 'Alice');
Schema::deprecated(Schema::string('旧字段'));
Schema::with(Schema::string(), ['x-displayName' => '状态']);
```

标准字段使用专用 helper；`Schema::with()` 只透传 OpenAPI 扩展字段，也就是 `x-*`。

使用 `ResponseDataType` 描述 `data` 字段结构：

- `ResponseDataType::Item`：单个对象
- `ResponseDataType::List`：对象数组
- `ResponseDataType::Page`：`{ list, meta }` 分页对象
- `ResponseDataType::None`：无响应数据

如果设置了 `response` 但没有设置 `responseType`，默认使用单项响应数据。

默认响应会被包装为：

```json
{
  "code": 200,
  "data": {},
  "msg": "OK"
}
```

如果希望直接返回原始 data schema，可以配置：

```php
use Cvcv\ThinkOpenApi\OpenApi\Response\RawDataSchemaFactory;

return [
    'response_schema_factory' => RawDataSchemaFactory::class,
];
```

你也可以实现 `Cvcv\ThinkOpenApi\OpenApi\Response\ResponseSchemaFactory`，定义自己的响应包装结构。

## 认证

内置的 bearer inspector 可以在路由包含指定中间件时，将接口标记为需要认证。

```php
return [
    'security' => [
        'bearer' => [
            'middleware' => app\middleware\AuthToken::class,
            'scheme_name' => 'bearerAuth',
            'description' => 'Bearer Token 认证。',
        ],
    ],
];
```

如果需要更复杂的中间件识别逻辑，可以实现 `MiddlewareSecurityInspector`，并注册到 `openapi.security.inspectors`。

```php
<?php

namespace app\openapi;

use Cvcv\ThinkOpenApi\OpenApi\Security\AuthState;
use Cvcv\ThinkOpenApi\OpenApi\Security\MiddlewareSecurityInspector;

final class AdminSecurityInspector implements MiddlewareSecurityInspector
{
    public function inspect(string $middleware, array $parameters, AuthState $state): void
    {
        if ($middleware !== \app\middleware\AdminAuth::class) {
            return;
        }

        $state->requireSecurity(['adminBearer' => []]);
        $state->addDescription('需要管理员 Bearer Token。');
    }

    public function securitySchemes(): array
    {
        return [
            'adminBearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'token',
                'description' => '管理员 Bearer Token。',
            ],
        ];
    }
}
```

```php
return [
    'security' => [
        'inspectors' => [
            \Cvcv\ThinkOpenApi\OpenApi\Security\BearerMiddlewareSecurityInspector::class,
            \app\openapi\AdminSecurityInspector::class,
        ],
    ],
];
```

## 配置

默认配置：

```php
return [
    'title' => 'ThinkPHP OpenAPI',
    'version' => '0.1.0',
    'servers' => [
        ['url' => '/'],
    ],
    'json_url' => '/docs/api.json',
    'spec_path' => 'runtime/docs/openapi.json',
    'production_envs' => ['prod', 'production'],
    'regenerate_on_request' => false,
    'routes' => [
        'enabled' => true,
        'scalar' => 'docs/api',
        'stoplight' => 'docs/api/stoplight',
        'json' => 'docs/api.json',
    ],
    'ui' => [
        'scalar' => [
            'script_url' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
        ],
        'stoplight' => [
            'script_url' => 'https://unpkg.com/@stoplight/elements/web-components.min.js',
            'styles_url' => 'https://unpkg.com/@stoplight/elements/styles.min.css',
        ],
    ],
];
```

当 `app.env` 命中 `production_envs` 时，文档路由会返回 404。生产环境建议主动生成并发布 JSON 文件，不建议在每次请求时重新生成。

## 离线文档 UI

默认文档页面通过 CDN 加载 Scalar 和 Stoplight Elements。内网或离线环境可以只覆盖资源 URL，不需要复制或改写内置模板：

```php
return [
    'ui' => [
        'scalar' => [
            'script_url' => '/assets/openapi/scalar.js',
        ],
        'stoplight' => [
            'script_url' => '/assets/openapi/stoplight-elements.js',
            'styles_url' => '/assets/openapi/stoplight-elements.css',
        ],
    ],
];
```

如果需要完全自定义 HTML，仍然可以通过 `views_path` 指向自己的 `scalar.html` 和 `stoplight.html`。内置模板会对配置值进行 HTML 转义。

## 命令

生成 OpenAPI 文档到配置的 `spec_path`：

```bash
php think docs:generate
```

生成到自定义路径：

```bash
php think docs:generate --output=runtime/docs/openapi.json
```

检查生成文档和路由元数据：

```bash
php think docs:lint
```

`docs:lint` 适合放入 CI；发现问题时会返回非零退出码。

## 生成流程

1. 通过 route list provider 加载 ThinkPHP 路由。
2. 只处理指向已有控制器方法的路由 action。
3. 只生成带有 `#[ApiDoc]` 的方法。
4. 将 `<id>` 风格的 ThinkPHP 路由变量转换为 `{id}` OpenAPI path 参数。
5. 当 `ApiDoc` 没有提供摘要和描述时，方法 PHPDoc 可作为后备来源。
6. 根据 Validate 规则生成请求参数或请求体 schema。
7. 将响应 `SchemaProvider` 注册到 `components.schemas`。
8. 通过 middleware security inspector 添加 operation `security` 和 `components.securitySchemes`。

## 路由支持边界

生成器只处理可以解析到控制器方法的路由 action：

- `[Controller::class, 'method']`
- `Controller/method` 字符串，其中 `Controller` 是已存在的完整类名，或位于当前应用命名空间下的控制器类名

只有方法存在、HTTP 方法是 `GET`、`POST`、`PUT`、`PATCH` 或 `DELETE`，并且方法带有 `#[ApiDoc]` 时，才会生成 OpenAPI operation。闭包路由、无法解析到类方法的路由、未加 `#[ApiDoc]` 的方法，以及当前不支持的 HTTP 方法会被跳过。

`docs:lint` 会对可疑跳过项给出提示：带 `#[ApiDoc]` 但使用不支持 HTTP 方法的路由会报告 `unsupported-route-method`；看起来像控制器 action 但无法解析到现有方法的路由会报告 `unsupported-route-action`。普通闭包路由不会被视为 lint 问题。

## 开发

安装依赖：

```bash
composer install
```

运行测试：

```bash
composer test
```

运行单个测试文件：

```bash
vendor/bin/phpunit -c phpunit.xml tests/Unit/OpenApi/GeneratorTest.php
```
