<?php

namespace Cvcv\ThinkOpenApi\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ApiField
{
    /**
     * @param array<string, string> $descriptions
     */
    public function __construct(public array $descriptions)
    {
    }
}
