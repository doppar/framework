<?php

namespace Phaseolies\Validation;

use Phaseolies\Translation\Translator;

final class MessageResolver
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    /**
     * Resolves a constraint violation into a translated message.
     *
     * @param ConstraintViolation $violation
     * @return string
     */
    public function resolve(ConstraintViolation $violation): string
    {
        $attribute = $this->translator->get(
            "validation.attributes.{$violation->property}",
            [],
            null,
        );
        $attributeKey = "validation.attributes.{$violation->property}";

        if ($attribute === $attributeKey) {
            $attribute = ucwords(str_replace('_', ' ', $violation->property));
        }

        $key = "validation.{$violation->messageKey}";
        $replacements = array_merge([
            ':attribute' => $attribute,
            'attribute' => $attribute,
        ], $violation->parameters);
        $message = $this->translator->get($key, $replacements);

        if ($message !== $key) {
            return $message;
        }

        $fallbacks = [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'int' => 'The :attribute must be an integer.',
            'between' => 'The :attribute must be between :min and :max.',
            'min.string' => 'The :attribute must be at least :min characters.',
            'max.string' => 'The :attribute may not be greater than :max characters.',
        ];

        return strtr(
            $fallbacks[$violation->messageKey] ?? 'Validation failed.',
            $replacements,
        );
    }
}
