<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

final class ObjectSchema
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $properties = [];

    /**
     * @var list<string>
     */
    private array $required = [];

    private function __construct(
        private readonly string $name,
        private readonly ?string $description = null,
    ) {
    }

    public static function make(string $name, ?string $description = null): self
    {
        return new self($name, $description);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function required(string ...$names): self
    {
        foreach ($names as $name) {
            if ($name !== '' && !in_array($name, $this->required, true)) {
                $this->required[] = $name;
            }
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function property(string $name, array $schema, bool $required = false): self
    {
        $this->properties[$name] = $schema;

        if ($required) {
            $this->required($name);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return Schema::object($this->properties, $this->required, $this->description);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function components(): array
    {
        return [
            $this->name => $this->schema(),
        ];
    }
}
