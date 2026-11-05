<?php

namespace Phaseolies\Validation;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

final class DtoValidator
{
    public function __construct(
        private readonly MessageResolver $messages,
    ) {
    }

    /**
     * Returns validation errors for a DTO payload.
     *
     * @param object $dto
     * @param array<string, mixed> $payload
     * @return array<string, list<string>>
     */
    public function errors(object $dto, array $payload): array
    {
        $errors = [];
        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties() as $property) {
            $constraints = $property->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ($constraints === []) {
                continue;
            }

            $value = $payload[$property->getName()] ?? null;
            foreach ($constraints as $attribute) {
                $violation = $attribute->newInstance()->validate($value, $property->getName(), $payload);
                if ($violation === null) {
                    continue;
                }

                $errors[$violation->property][] = $this->messages->resolve($violation);
            }
        }

        return $errors;
    }

    /**
     * Normalizes built-in DTO property values in a payload.
     *
     * @param object $dto
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function normalize(object $dto, array $payload): array
    {
        $normalized = $payload;
        $reflection = new ReflectionClass($dto);

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!array_key_exists($name, $normalized)) {
                continue;
            }

            $normalized[$name] = $this->normalizeValue($property, $normalized[$name]);
        }

        return $normalized;
    }

    /**
     * Normalizes a value according to a reflected property type.
     *
     * @param ReflectionProperty $property
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin() || $value === null) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }
}
