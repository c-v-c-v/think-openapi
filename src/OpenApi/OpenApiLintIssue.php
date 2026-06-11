<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

final readonly class OpenApiLintIssue
{
    public function __construct(
        public string $rule,
        public string $location,
        public string $message,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s: %s', $this->rule, $this->location, $this->message);
    }
}
