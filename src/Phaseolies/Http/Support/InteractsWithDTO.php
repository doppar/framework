<?php

namespace Phaseolies\Http\Support;

trait InteractsWithDTO
{
    /**
     * Binds the current data to the given object's properties.
     *
     * @param object $object The target object to bind data to
     * @param bool $strict Whether to enforce property existence checks (default: true)
     * @return object The modified object with bound data
     */
    public function bindTo(object $object, bool $strict = true): object
    {
        $data = $this->all();

        foreach ($data as $key => $value) {
            if ($strict && !property_exists($object, $key)) {
                continue;
            }

            $this->setDynamicProperty($object, $key, $value, $strict);
        }

        return $object;
    }

    /**
     * Dynamically sets a property on an object with type checking and nested object support.
     *
     * @param object $object The target object
     * @param string $key The property name to set
     * @param mixed $value The value to assign
     * @param bool $strict Whether to enforce type checking
     * @return void
     */
    protected function setDynamicProperty(object $object, string $key, $value, bool $strict): void
    {
        try {
            $reflection = new \ReflectionProperty($object, $key);
            $type = $reflection->getType();

            // Handle nested objects
            if ($type && !$type->isBuiltin()) {
                $this->handleNestedObject($object, $key, $value, $type->getName(), $strict);
                return;
            }

            // Handle arrays that should be converted to objects
            if (is_array($value) && $this->isPotentialNestedObject($object, $key)) {
                $this->handleNestedObject($object, $key, $value, $this->guessClassName($object, $key), $strict);
                return;
            }

            // Regular assignment
            $object->{$key} = $value;
        } catch (\ReflectionException $e) {
            if (!$strict) {
                $object->{$key} = $value;
            }
        }
    }

    /**
     * Handles conversion and binding of nested objects.
     *
     * @param object $object The parent object
     * @param string $key The property name
     * @param mixed $value The value to convert to a nested object
     * @param string $className The target class name for the nested object
     * @param bool $strict Whether to enforce strict mode
     * @throws \RuntimeException When the target class doesn't exist
     */
    protected function handleNestedObject(object $object, string $key, $value, string $className, bool $strict): void
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Class {$className} does not exist");
        }

        $nestedObject = new $className();

        if (is_string($value) && json_validate($value)) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            foreach ($value as $nestedKey => $nestedValue) {
                $this->setDynamicProperty($nestedObject, $nestedKey, $nestedValue, $strict);
            }
        }

        $object->{$key} = $nestedObject;
    }

    /**
     * Determines if a property might represent a nested object.
     *
     * @param object $object The object to check
     * @param string $key The property name to check
     * @return bool True if the property might be a nested object
     */
    protected function isPotentialNestedObject(object $object, string $key): bool
    {
        // Check if property exists and is null or not initialized
        try {
            $reflection = new \ReflectionProperty($object, $key);
            return true;
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Attempts to guess the class name for a potential nested object.
     *
     * Uses common naming conventions to determine possible class names
     * for nested DTO objects based on property name and namespace context.
     *
     * @param object $object The parent object
     * @param string $key The property name
     * @return string The guessed class name (falls back to stdClass)
     */
    protected function guessClassName(object $object, string $key): string
    {
        // Try common naming patterns for nested DTOs
        $class = get_class($object);
        $namespace = substr($class, 0, strrpos($class, '\\'));

        $guesses = [
            $namespace . '\\' . ucfirst($key),
            $namespace . '\\' . ucfirst($key) . 'DTO',
            $namespace . '\\' . str_replace('_', '', ucwords($key, '_')) . 'DTO',
            $namespace . '\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'DTO'
        ];

        foreach ($guesses as $guess) {
            if (class_exists($guess)) {
                return $guess;
            }
        }

        // Fallback to generic object
        return \stdClass::class;
    }
}
