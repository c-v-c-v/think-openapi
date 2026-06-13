<?php

namespace Cvcv\ThinkOpenApi\OpenApi;

use Cvcv\ThinkOpenApi\Attribute\ApiDoc;
use Cvcv\ThinkOpenApi\Attribute\ApiField;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use think\App;
use think\Validate;

final class ValidateMetadataReader
{
    public function __construct(private readonly ?App $app = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ApiDoc $doc): array
    {
        $validator = $this->validator($doc);

        if ($validator === null) {
            return [];
        }

        $rules = $this->protectedProperty($validator, 'rule');

        if (!is_array($rules)) {
            return [];
        }

        if ($doc->scene === null) {
            return $rules;
        }

        $scenes = $this->protectedProperty($validator, 'scene');
        $fields = is_array($scenes) ? ($scenes[$doc->scene] ?? []) : [];

        if (!is_array($fields)) {
            return [];
        }

        return array_intersect_key($rules, array_flip($fields));
    }

    /**
     * @return array<string, string>
     */
    public function fields(ApiDoc $doc): array
    {
        $validator = $this->validator($doc);

        if ($validator === null) {
            return [];
        }

        $property = $this->protectedPropertyReflection($validator, 'field');
        $fields = $property->getValue($validator);

        if (!is_array($fields)) {
            return [];
        }

        $fields = array_filter($fields, is_string(...));
        $apiFields = $this->apiFieldDescriptions($property);

        foreach ($apiFields as $name => $description) {
            if (isset($fields[$name])) {
                $fields[$name] = $fields[$name] === ''
                    ? $description
                    : $fields[$name] . '；' . $description;
            } else {
                $fields[$name] = $description;
            }
        }

        return $fields;
    }

    private function validator(ApiDoc $doc): ?Validate
    {
        if ($doc->validate === null || !class_exists($doc->validate)) {
            return null;
        }

        $validator = $this->makeValidator($doc->validate);

        return $validator instanceof Validate ? $validator : null;
    }

    /**
     * @param class-string $class
     */
    private function makeValidator(string $class): ?object
    {
        if ($this->app !== null) {
            try {
                return $this->app->make($class);
            } catch (Throwable) {
            }
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (Throwable) {
            return null;
        }

        if (!$reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        try {
            return $reflection->newInstance();
        } catch (Throwable) {
            return null;
        }
    }

    private function protectedProperty(object $object, string $name): mixed
    {
        $property = $this->protectedPropertyReflection($object, $name);

        return $property->getValue($object);
    }

    private function protectedPropertyReflection(object $object, string $name): ReflectionProperty
    {
        $property = new ReflectionProperty($object, $name);
        $property->setAccessible(true);

        return $property;
    }

    /**
     * @return array<string, string>
     */
    private function apiFieldDescriptions(ReflectionProperty $property): array
    {
        $attributes = $property->getAttributes(ApiField::class);

        if ($attributes === []) {
            return [];
        }

        /** @var ApiField $apiField */
        $apiField = $attributes[0]->newInstance();

        return array_filter(
            $apiField->descriptions,
            static fn (mixed $description): bool => is_string($description) && $description !== '',
        );
    }
}
