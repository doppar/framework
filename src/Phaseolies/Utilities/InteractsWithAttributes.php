<?php

namespace Phaseolies\Utilities;

trait InteractsWithAttributes
{
    /**
     * Accesses a private or protected property of a class using reflection.
     *
     * @param string $class The fully qualified class name.
     * @param string $attribute The property name to retrieve.
     * @return mixed The value of the specified property.
     * @throws \Exception
     */
    protected function getClassProperty(string $class, string $attribute): mixed
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->hasProperty($attribute)) {
            $property = $reflection->getProperty($attribute);
            $property->setAccessible(true);

            return $property->isStatic()
                ? $property->getValue()
                : $property->getValue(new $this->modelClass);
        }

        throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
    }

    /**
     * Checks whether a class property has a specific attribute.
     *
     * @param object|string $class The fully qualified class name.
     * @param string $attribute The property name to inspect.
     * @param string $attributeClass The attribute class to check for (e.g. CastToDate::class).
     * @return bool True if the attribute exists on the property, false otherwise.
     * @throws \Exception
     */
    protected function propertyHasAttribute(object|string $class, string $attribute, string $attributeClass): bool
    {
        $reflection = new \ReflectionClass($class);

        if (! $reflection->hasProperty($attribute)) {
            throw new \Exception("Property '{$attribute}' does not exist in class '{$class}'.");
        }

        $property = $reflection->getProperty($attribute);
        $attributes = $property->getAttributes($attributeClass);

        return !empty($attributes);
    }
}
