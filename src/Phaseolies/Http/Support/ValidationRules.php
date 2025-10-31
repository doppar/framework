<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Translation\Translator;

trait ValidationRules
{
    /**
     * @var Translator
     */
    protected Translator $translator;

    /**
     * Set the translator
     *
     * @param Translator $translator
     * @return void
     */
    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Get the translated error message for a validation rule.
     *
     * @param string $rule
     * @param string $fieldName
     * @param array $replace
     * @return string
     */
    protected function getErrorMessage(string $rule, string $fieldName, array $replace = [])
    {
        $attribute = $this->getAttributeName($fieldName);

        // Default replacements
        $replace = array_merge([
            ':attribute' => $attribute,
            'attribute' => $attribute,
        ], $replace);

        $message = $this->translator->get("validation.$rule", $replace);

        if ($message === "validation.$rule") {
            $message = $this->getDefaultErrorMessage($rule);
            foreach ($replace as $key => $value) {
                $message = str_replace(":$key", $value, $message);
            }
        }

        return $message;
    }

    /**
     * Get fallback error message
     *
     * @param string $rule
     * @return string
     */
    protected function getDefaultErrorMessage(string $rule): string
    {
        $defaultMessages = [
            'required' => 'The :attribute field is required.',
        ];

        return $defaultMessages[$rule] ?? 'Validation failed.';
    }

    /**
     * Get the displayable name of the attribute.
     *
     * @param string $fieldName
     * @return string
     */
    protected function getAttributeName(string $fieldName): string
    {
        $customName = $this->translator->get("validation.attributes.$fieldName", [], null);

        if ($customName !== "validation.attributes.$fieldName") {
            return $customName;
        }

        return $this->_removeUnderscore(ucfirst($fieldName));
    }

    /**
     * Validate a field based on the given rule.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $rule
     * @param mixed $ruleValue
     * @return string|null
     */
    protected function sanitizeUserRequest(
        array $input,
        string $fieldName,
        string $rule,
        mixed $ruleValue = null
    ): ?string {
        $this->setTranslator(app('translator'));

        if ($this->isFileField($fieldName)) {
            return $this->validateFile($fieldName, $rule, $ruleValue);
        }

        if ($rule === 'required') {
            if ($this->isEmptyFieldRequired($input, $fieldName)) {
                return $this->getErrorMessage('required', $fieldName);
            }
        }

        if ($rule === 'null' && $this->isNullable($input, $fieldName)) {
            return null;
        }

        if ($this->isNullable($input, $fieldName) && $rule !== 'null') {
            return null;
        } else {
            switch ($rule) {
                case 'required':
                    if ($this->isEmptyFieldRequired($input, $fieldName)) {
                        return $this->getErrorMessage('required', $fieldName);
                    }
                    break;

                case 'email':
                    if (!$this->isEmailValid($input, $fieldName)) {
                        return $this->getErrorMessage('email', $fieldName);
                    }
                    break;

                case 'min':
                    if ($this->isLessThanMin($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('min.string', $fieldName, [
                            ':min' => $ruleValue,
                            'min' => $ruleValue
                        ]);
                    }
                    break;

                case 'max':
                    if ($this->isMoreThanMax($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('max.string', $fieldName, [
                            ':max' => $ruleValue,
                            'max' => $ruleValue
                        ]);
                    }
                    break;

                case 'unique':
                    if ($this->isRecordUnique($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('unique', $fieldName);
                    }
                    break;
                case 'date':
                    if (!$this->isDateValid($input, $fieldName)) {
                        return $this->getErrorMessage('date', $fieldName);
                    }
                    break;

                case 'gte':
                    if (!$this->isDateGreaterThanOrEqual($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('gte', $fieldName, [
                            ':date' => $ruleValue,
                            'date' => $ruleValue
                        ]);
                    }
                    break;

                case 'lte':
                    if (!$this->isDateLessThanOrEqual($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('lte', $fieldName, [
                            ':date' => $ruleValue,
                            'date' => $ruleValue
                        ]);
                    }
                    break;

                case 'gt':
                    if (!$this->isDateGreaterThan($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('gt', $fieldName, [
                            ':date' => $ruleValue,
                            'date' => $ruleValue
                        ]);
                    }
                    break;

                case 'lt':
                    if (!$this->isDateLessThan($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('lt', $fieldName, [
                            ':date' => $ruleValue,
                            'date' => $ruleValue
                        ]);
                    }
                    break;
                case 'int':
                    if (!$this->isInteger($input, $fieldName)) {
                        return $this->getErrorMessage('int', $fieldName);
                    }
                    break;

                case 'float':
                    if (!$this->isFloat($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('float', $fieldName, [
                            ':decimal' => $ruleValue,
                            'decimal' => $ruleValue
                        ]);
                    }
                    break;

                case 'between':
                    if (!$this->isBetween($input, $fieldName, $ruleValue)) {
                        $range = explode(',', $ruleValue);
                        return $this->getErrorMessage('between', $fieldName, [
                            ':min' => $range[0],
                            'min' => $range[0],
                            ':max' => $range[1],
                            'max' => $range[1],
                        ]);
                    }
                    break;

                case 'same_as':
                    if (!$this->isSameAs($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('same_as', $fieldName, [
                            ':other' => $this->getAttributeName($ruleValue),
                            'other' => $this->getAttributeName($ruleValue)
                        ]);
                    }
                    break;
            }
        }

        return null;
    }

    /**
     * Check if a field value matches another field's value.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $otherField
     * @return bool
     */
    protected function isSameAs(array $input, string $fieldName, string $otherField): bool
    {
        $value = trim($input[$fieldName] ?? '');
        $otherValue = trim($input[$otherField] ?? '');

        return $value === $otherValue;
    }

    /**
     * Check if the field value is an integer.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isInteger(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Check if the field value is a float with the specified decimal places.
     *
     * @param array $input
     * @param string $fieldName
     * @param int $decimalPlaces
     * @return bool
     */
    protected function isFloat(array $input, string $fieldName, int $decimalPlaces): bool
    {
        $value = $input[$fieldName] ?? '';

        if (!is_numeric($value)) {
            return false;
        }

        // Check if the number of decimal places matches the rule
        $decimalPart = explode('.', $value)[1] ?? '';

        return strlen($decimalPart) <= $decimalPlaces;
    }

    /**
     * Check if the field value is between the given range.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isBetween(array $input, string $fieldName, string $ruleValue): bool
    {
        $value = $input[$fieldName] ?? '';

        if (!is_numeric($value)) {
            return false;
        }

        $range = explode(',', $ruleValue);
        $min = (float)$range[0];
        $max = (float)$range[1];

        return $value >= $min && $value <= $max;
    }

    /**
     * Check if the field value is nullable (null or empty).
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isNullable(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return $value === null || $value === '';
    }

    /**
     * Check if the field is a valid date.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isDateValid(array $input, string $fieldName): bool
    {
        $date = $input[$fieldName] ?? '';
        return strtotime($date) !== false;
    }

    /**
     * Check if the field value is greater than or equal to the given date.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isDateGreaterThanOrEqual(array $input, string $fieldName, string $ruleValue): bool
    {
        $date = $input[$fieldName] ?? '';
        $compareDate = $ruleValue === 'today' ? date('Y-m-d') : $ruleValue;
        return strtotime($date) >= strtotime($compareDate);
    }

    /**
     * Check if the field value is less than or equal to the given date.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isDateLessThanOrEqual(array $input, string $fieldName, string $ruleValue): bool
    {
        $date = $input[$fieldName] ?? '';
        $compareDate = $ruleValue === 'today' ? date('Y-m-d') : $ruleValue;
        return strtotime($date) <= strtotime($compareDate);
    }

    /**
     * Check if the field value is greater than the given date.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isDateGreaterThan(array $input, string $fieldName, string $ruleValue): bool
    {
        $date = $input[$fieldName] ?? '';

        $compareDate = $ruleValue === 'today' ? date('Y-m-d') : $ruleValue;

        return strtotime($date) > strtotime($compareDate);
    }

    /**
     * Check if the field value is less than the given date.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isDateLessThan(array $input, string $fieldName, string $ruleValue): bool
    {
        $date = $input[$fieldName] ?? '';
        $compareDate = $ruleValue === 'today' ? date('Y-m-d') : $ruleValue;
        return strtotime($date) < strtotime($compareDate);
    }

    /**
     * Check if the field is a file field.
     *
     * @param string $fieldName
     * @return bool
     */
    protected function isFileField(string $fieldName): bool
    {
        return isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Validate a file field based on the given rule.
     *
     * @param string $fieldName
     * @param string $rule
     * @param mixed $ruleValue
     * @return string|null
     */
    protected function validateFile(string $fieldName, string $rule, mixed $ruleValue = null): ?string
    {
        $file = $_FILES[$fieldName];

        switch ($rule) {
            case 'required':
                if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                    return $this->getErrorMessage('file.required', $fieldName);
                }
                break;

            case 'image':
                if (!@getimagesize($file['tmp_name'])) {
                    return $this->getErrorMessage('file.image', $fieldName);
                }
                break;

            case 'mimes':
                $allowedTypes = explode(',', $ruleValue);
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedTypes)) {
                    return $this->getErrorMessage('file.mimes', $fieldName, [
                        ':values' => $ruleValue,
                        'values' => $ruleValue
                    ]);
                }
                break;

            case 'dimensions':
                $dimensions = $this->parseDimensionsRule($ruleValue);
                if ($dimensions) {
                    [$width, $height] = getimagesize($file['tmp_name']);

                    if (isset($dimensions['min_width']) && $width < $dimensions['min_width']) {
                        return $this->getErrorMessage('file.dimensions.min_width', $fieldName, [
                            ':min_width' => $dimensions['min_width'],
                            'min_width' => $dimensions['min_width']
                        ]);
                    }

                    if (isset($dimensions['min_height']) && $height < $dimensions['min_height']) {
                        return $this->getErrorMessage('file.dimensions.min_height', $fieldName, [
                            ':min_height' => $dimensions['min_height'],
                            'min_height' => $dimensions['min_height'],
                        ]);
                    }

                    if (isset($dimensions['max_width']) && $width > $dimensions['max_width']) {
                        return $this->getErrorMessage('file.dimensions.max_width', $fieldName, [
                            ':max_width' => $dimensions['max_width'],
                            'max_width' => $dimensions['max_width']
                        ]);
                    }

                    if (isset($dimensions['max_height']) && $height > $dimensions['max_height']) {
                        return $this->getErrorMessage('file.dimensions.max_height', $fieldName, [
                            ':max_height' => $dimensions['max_height'],
                            'max_height' => $dimensions['max_height']
                        ]);
                    }
                }
                break;

            case 'max':
                $maxSize = $this->parseSizeRule($ruleValue);
                if ($file['size'] > $maxSize) {
                    return $this->getErrorMessage('file.max', $fieldName, [
                        ':max' => $this->formatBytes($maxSize),
                        'max' => $this->formatBytes($maxSize)
                    ]);
                }
                break;
        }

        return null;
    }

    /**
     * Parse the dimensions rule value.
     *
     * @param string $ruleValue
     * @return array<string, int>|null The parsed dimensions or null if invalid.
     */
    protected function parseDimensionsRule(string $ruleValue): ?array
    {
        $dimensions = [];
        $parts = explode(',', $ruleValue);

        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part);
                $dimensions[trim($key)] = (int)trim($value);
            }
        }

        return !empty($dimensions) ? $dimensions : null;
    }

    /**
     * Parse the size rule value.
     *
     * @param string $ruleValue
     * @return int
     */
    protected function parseSizeRule(string $ruleValue): int
    {
        $unit = strtoupper(substr($ruleValue, -1));
        $size = (int)substr($ruleValue, 0, -1);

        switch ($unit) {
            case 'K': // Kilobytes
                return $size * 1024;
            case 'M': // Megabytes
                return $size * 1024 * 1024;
            case 'G': // Gigabytes
                return $size * 1024 * 1024 * 1024;
            default: // Bytes
                return (int)$ruleValue;
        }
    }

    /**
     * Format bytes into a human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * Check if a required field is empty.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isEmptyFieldRequired(array $input, string $fieldName): bool
    {
        return !isset($input[$fieldName]) || $input[$fieldName] === '';
    }

    /**
     * Check if a field value is less than the minimum length.
     *
     * @param array $input
     * @param string $fieldName
     * @param int $value
     * @return bool
     */
    protected function isLessThanMin(array $input, string $fieldName, int $value): bool
    {
        return strlen($input[$fieldName]) < $value;
    }

    /**
     * Check if a field value exceeds the maximum length.
     *
     * @param array $input
     * @param string $fieldName
     * @param int $value
     * @return bool
     */
    protected function isMoreThanMax(array $input, string $fieldName, int $value): bool
    {
        return strlen($input[$fieldName]) > $value;
    }

    /**
     * Check duplicate records exists or not
     *
     * @param mixed $tableName
     * @param mixed $fieldName
     * @param mixed $fieldValue
     * @return bool
     */
    public function checkRecordExists($tableName, $fieldName, $fieldValue): bool
    {
        try {
            return (bool) db()->bucket($tableName)
                ->where($fieldName, $fieldValue)
                ->exists();
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a record is unique.
     *
     * @param array $input
     * @param string $fieldName
     * @param string $value
     * @return bool
     */
    protected function isRecordUnique(array $input, string $fieldName, string $value): bool
    {
        return $this->checkRecordExists($value, $fieldName, $input[$fieldName]);
    }

    /**
     * Validate if the email is valid.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isEmailValid(array $input, string $fieldName): bool
    {
        $email = $input[$fieldName] ?? '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Remove underscores from a string and capitalize words.
     *
     * @param string $string
     * @return string
     */
    protected function _removeUnderscore(string $string): string
    {
        return str_replace("_", " ", $string);
    }

    /**
     * Remove the suffix from a rule string.
     *
     * @param string $string
     * @return string
     */
    protected function _removeRuleSuffix(string $string): string
    {
        return explode(":", $string)[0];
    }

    /**
     * Get the suffix from a rule string.
     *
     * @param string $string
     * @return string|null
     */
    protected function _getRuleSuffix(string $string): ?string
    {
        $arr = explode(":", $string);

        return $arr[1] ?? null;
    }
}
