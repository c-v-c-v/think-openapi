<?php

namespace Cvcv\ThinkOpenApi\OpenApi\Security;

final class AuthState
{
    /**
     * @var array<int, array<string, array<int, string>>>
     */
    private array $security = [];

    /**
     * @var array<int, string>
     */
    private array $descriptionParts = [];

    /**
     * @var array<string, mixed>
     */
    private array $extensions = [];

    /**
     * @param array<string, array<int, string>> $security
     */
    public function requireSecurity(array $security): void
    {
        if (!in_array($security, $this->security, true)) {
            $this->security[] = $security;
        }
    }

    /**
     * @param array<int, array<string, array<int, string>>> $security
     */
    public function replaceSecurity(array $security): void
    {
        $this->security = [];

        foreach ($security as $item) {
            if (!in_array($item, $this->security, true)) {
                $this->security[] = $item;
            }
        }
    }

    public function addDescription(string $description): void
    {
        if (!in_array($description, $this->descriptionParts, true)) {
            $this->descriptionParts[] = $description;
        }
    }

    public function addExtension(string $name, mixed $value): void
    {
        $this->extensions[$name] = $value;
    }

    public function requirement(): AuthRequirement
    {
        return new AuthRequirement($this->security, $this->descriptionParts, $this->extensions);
    }
}
