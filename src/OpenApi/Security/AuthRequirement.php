<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

final readonly class AuthRequirement
{
    /**
     * @param array<int, array<string, array<int, string>>> $security
     * @param array<int, string> $descriptionParts
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public array $security = [],
        public array $descriptionParts = [],
        public array $extensions = [],
    ) {
    }

    public function isRequired(): bool
    {
        return $this->security !== [];
    }
}
