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
            'exists_in' => 'The selected :attribute is invalid.',
            'alpha'       => 'The :attribute may only contain letters.',
            'alpha_num'   => 'The :attribute may only contain letters and numbers.',
            'alpha_dash'  => 'The :attribute may only contain letters, numbers, dashes, and underscores.',
            'numeric'     => 'The :attribute must be a number.',
            'url'         => 'The :attribute must be a valid URL.',
            'ip'          => 'The :attribute must be a valid IP address.',
            'ipv4'        => 'The :attribute must be a valid IPv4 address.',
            'ipv6'        => 'The :attribute must be a valid IPv6 address.',
            'json'        => 'The :attribute must be a valid JSON string.',
            'boolean'     => 'The :attribute field must be true or false.',
            'confirmed'   => 'The :attribute confirmation does not match.',
            'different'   => 'The :attribute and :other must be different.',
            'in'          => 'The selected :attribute is invalid. Allowed: :values.',
            'not_in'      => 'The selected :attribute is invalid.',
            'regex'       => 'The :attribute format is invalid.',
            'phone'       => 'The :attribute must be a valid phone number.',
            'digits'      => 'The :attribute must be exactly :digits digits.',
            'min_digits'  => 'The :attribute must have at least :min digits.',
            'max_digits'  => 'The :attribute must not have more than :max digits.',
            'size'        => 'The :attribute must be exactly :size characters.',
            'starts_with' => 'The :attribute must start with :value.',
            'ends_with'   => 'The :attribute must end with :value.',
            'uuid'        => 'The :attribute must be a valid UUID.',
            'date_format' => 'The :attribute does not match the format :format.',
            'uppercase'   => 'The :attribute must be uppercase.',
            'lowercase'   => 'The :attribute must be lowercase.',
            'slug'        => 'The :attribute must be a valid slug (lowercase letters, numbers, and hyphens).',
            'string'      => 'The :attribute must be a string.',
            'array'       => 'The :attribute must be an array.',
            'timezone'    => 'The :attribute must be a valid timezone.',
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

                case 'exists_in':
                    if (!$this->isRecordExists($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('exists_in', $fieldName);
                    }
                    break;

                case 'alpha':
                    if (!$this->isAlpha($input, $fieldName)) {
                        return $this->getErrorMessage('alpha', $fieldName);
                    }
                    break;

                case 'alpha_num':
                    if (!$this->isAlphaNum($input, $fieldName)) {
                        return $this->getErrorMessage('alpha_num', $fieldName);
                    }
                    break;

                case 'alpha_dash':
                    if (!$this->isAlphaDash($input, $fieldName)) {
                        return $this->getErrorMessage('alpha_dash', $fieldName);
                    }
                    break;

                case 'numeric':
                    if (!$this->isNumeric($input, $fieldName)) {
                        return $this->getErrorMessage('numeric', $fieldName);
                    }
                    break;

                case 'url':
                    if (!$this->isUrl($input, $fieldName)) {
                        return $this->getErrorMessage('url', $fieldName);
                    }
                    break;

                case 'ip':
                    if (!$this->isIp($input, $fieldName)) {
                        return $this->getErrorMessage('ip', $fieldName);
                    }
                    break;

                case 'ipv4':
                    if (!$this->isIpv4($input, $fieldName)) {
                        return $this->getErrorMessage('ipv4', $fieldName);
                    }
                    break;

                case 'ipv6':
                    if (!$this->isIpv6($input, $fieldName)) {
                        return $this->getErrorMessage('ipv6', $fieldName);
                    }
                    break;

                case 'json':
                    if (!$this->isJsonable($input, $fieldName)) {
                        return $this->getErrorMessage('json', $fieldName);
                    }
                    break;

                case 'boolean':
                    if (!$this->isBoolean($input, $fieldName)) {
                        return $this->getErrorMessage('boolean', $fieldName);
                    }
                    break;

                case 'confirmed':
                    if (!$this->isConfirmed($input, $fieldName)) {
                        return $this->getErrorMessage('confirmed', $fieldName);
                    }
                    break;

                case 'different':
                    if (!$this->isDifferent($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('different', $fieldName, [
                            ':other' => $this->getAttributeName($ruleValue),
                            'other' => $this->getAttributeName($ruleValue),
                        ]);
                    }
                    break;

                case 'in':
                    if (!$this->isInList($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('in', $fieldName, [
                            ':values' => $ruleValue,
                            'values' => $ruleValue,
                        ]);
                    }
                    break;

                case 'not_in':
                    if (!$this->isNotInList($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('not_in', $fieldName, [
                            ':values' => $ruleValue,
                            'values' => $ruleValue,
                        ]);
                    }
                    break;

                case 'regex':
                    if (!$this->isRegexMatch($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('regex', $fieldName);
                    }
                    break;

                case 'phone':
                    if (!$this->isPhone($input, $fieldName)) {
                        return $this->getErrorMessage('phone', $fieldName);
                    }
                    break;

                case 'digits':
                    if (!$this->isExactDigits($input, $fieldName, (int) $ruleValue)) {
                        return $this->getErrorMessage('digits', $fieldName, [
                            ':digits' => $ruleValue,
                            'digits' => $ruleValue,
                        ]);
                    }
                    break;

                case 'min_digits':
                    if (!$this->isMinDigits($input, $fieldName, (int) $ruleValue)) {
                        return $this->getErrorMessage('min_digits', $fieldName, [
                            ':min' => $ruleValue,
                            'min' => $ruleValue,
                        ]);
                    }
                    break;

                case 'max_digits':
                    if (!$this->isMaxDigits($input, $fieldName, (int) $ruleValue)) {
                        return $this->getErrorMessage('max_digits', $fieldName, [
                            ':max' => $ruleValue,
                            'max' => $ruleValue,
                        ]);
                    }
                    break;

                case 'size':
                    if (!$this->isExactSize($input, $fieldName, (int) $ruleValue)) {
                        return $this->getErrorMessage('size', $fieldName, [
                            ':size' => $ruleValue,
                            'size' => $ruleValue,
                        ]);
                    }
                    break;

                case 'starts_with':
                    if (!$this->isStartsWith($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('starts_with', $fieldName, [
                            ':value' => $ruleValue,
                            'value' => $ruleValue,
                        ]);
                    }
                    break;

                case 'ends_with':
                    if (!$this->isEndsWith($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('ends_with', $fieldName, [
                            ':value' => $ruleValue,
                            'value' => $ruleValue,
                        ]);
                    }
                    break;

                case 'uuid':
                    if (!$this->isUuid($input, $fieldName)) {
                        return $this->getErrorMessage('uuid', $fieldName);
                    }
                    break;

                case 'date_format':
                    if (!$this->isDateFormat($input, $fieldName, $ruleValue)) {
                        return $this->getErrorMessage('date_format', $fieldName, [
                            ':format' => $ruleValue,
                            'format' => $ruleValue,
                        ]);
                    }
                    break;

                case 'uppercase':
                    if (!$this->isUppercase($input, $fieldName)) {
                        return $this->getErrorMessage('uppercase', $fieldName);
                    }
                    break;

                case 'lowercase':
                    if (!$this->isLowercase($input, $fieldName)) {
                        return $this->getErrorMessage('lowercase', $fieldName);
                    }
                    break;

                case 'slug':
                    if (!$this->isSlug($input, $fieldName)) {
                        return $this->getErrorMessage('slug', $fieldName);
                    }
                    break;

                case 'string':
                    if (!$this->isString($input, $fieldName)) {
                        return $this->getErrorMessage('string', $fieldName);
                    }
                    break;

                case 'array':
                    if (!$this->isArray($input, $fieldName)) {
                        return $this->getErrorMessage('array', $fieldName);
                    }
                    break;

                case 'timezone':
                    if (!$this->isTimezone($input, $fieldName)) {
                        return $this->getErrorMessage('timezone', $fieldName);
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
     * Check if a record exists in the database
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isRecordExists(array $input, string $fieldName, string $ruleValue): bool
    {
        $value = $input[$fieldName] ?? '';

        if (empty($value)) {
            return false;
        }

        $parts = explode(',', $ruleValue);
        $tableName = trim($parts[0]);
        $columnName = trim($parts[1] ?? 'id');

        return $this->checkRecordExists($tableName, $columnName, $value);
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
     * Check if the field value contains only alphabetic characters.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isAlpha(array $input, string $fieldName): bool
    {
        return (bool) preg_match('/^[a-zA-Z]+$/', $input[$fieldName] ?? '');
    }

    /**
     * Check if the field value contains only alphanumeric characters.
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isAlphaNum(array $input, string $fieldName): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]+$/', $input[$fieldName] ?? '');
    }

    /**
     * Check if the field value contains only letters, numbers, dashes, and underscores
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isAlphaDash(array $input, string $fieldName): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', $input[$fieldName] ?? '');
    }

    /**
     * Check if the field value is numeric
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isNumeric(array $input, string $fieldName): bool
    {
        return is_numeric($input[$fieldName] ?? '');
    }

    /**
     * Check if the field value is a valid URL
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isUrl(array $input, string $fieldName): bool
    {
        return filter_var($input[$fieldName] ?? '', FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if the field value is a valid IP address (v4 or v6)
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isIp(array $input, string $fieldName): bool
    {
        return filter_var($input[$fieldName] ?? '', FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if the field value is a valid IPv4 address
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isIpv4(array $input, string $fieldName): bool
    {
        return filter_var($input[$fieldName] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Check if the field value is a valid IPv6 address
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isIpv6(array $input, string $fieldName): bool
    {
        return filter_var($input[$fieldName] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Check if the field value is a valid JSON string
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isJsonable(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        if (!is_string($value) || $value === '') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if the field value is a boolean-like value (true, false, 0, 1, "0", "1", "true", "false")
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isBoolean(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? null;

        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
    }

    /**
     * Check if the field value matches the {field}_confirmation counterpart
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isConfirmed(array $input, string $fieldName): bool
    {
        return ($input[$fieldName] ?? '') === ($input[$fieldName . '_confirmation'] ?? '');
    }

    /**
     * Check if the field value is different from another field's value
     *
     * @param array $input
     * @param string $fieldName
     * @param string $otherField
     * @return bool
     */
    protected function isDifferent(array $input, string $fieldName, string $otherField): bool
    {
        return ($input[$fieldName] ?? '') !== ($input[$otherField] ?? '');
    }

    /**
     * Check if the field value is one of the allowed values
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isInList(array $input, string $fieldName, string $ruleValue): bool
    {
        $list = array_map('trim', explode(',', $ruleValue));

        return in_array($input[$fieldName] ?? '', $list, true);
    }

    /**
     * Check if the field value is not in the given list
     *
     * @param array $input
     * @param string $fieldName
     * @param string $ruleValue
     * @return bool
     */
    protected function isNotInList(array $input, string $fieldName, string $ruleValue): bool
    {
        $list = array_map('trim', explode(',', $ruleValue));

        return !in_array($input[$fieldName] ?? '', $list, true);
    }

    /**
     * Check if the field value matches a regular expression pattern
     *
     * @param array $input
     * @param string $fieldName
     * @param string $pattern
     * @return bool
     */
    protected function isRegexMatch(array $input, string $fieldName, string $pattern): bool
    {
        $value = $input[$fieldName] ?? '';

        return (bool) @preg_match($pattern, $value);
    }

    /**
     * Check if the field value is a valid phone number
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isPhone(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return (bool) preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $value);
    }

    /**
     * Check if the field value is a numeric string with exactly N digits
     *
     * @param array $input
     * @param string $fieldName
     * @param int $digits
     * @return bool
     */
    protected function isExactDigits(array $input, string $fieldName, int $digits): bool
    {
        $value = (string)($input[$fieldName] ?? '');

        return (bool) preg_match('/^\d{' . $digits . '}$/', $value);
    }

    /**
     * Check if the field value is a numeric string with at least N digits
     *
     * @param array $input
     * @param string $fieldName
     * @param int $min
     * @return bool
     */
    protected function isMinDigits(array $input, string $fieldName, int $min): bool
    {
        $value = (string)($input[$fieldName] ?? '');

        return (bool) preg_match('/^\d+$/', $value) && strlen($value) >= $min;
    }

    /**
     * Check if the field value is a numeric string with at most N digits
     *
     * @param array $input
     * @param string $fieldName
     * @param int $max
     * @return bool
     */
    protected function isMaxDigits(array $input, string $fieldName, int $max): bool
    {
        $value = (string)($input[$fieldName] ?? '');

        return (bool) preg_match('/^\d+$/', $value) && strlen($value) <= $max;
    }

    /**
     * Check if the field value has exactly N characters (or equals N for numeric values)
     *
     * @param array $input
     * @param string $fieldName
     * @param int $size
     * 
     * @return bool
     */
    protected function isExactSize(array $input, string $fieldName, int $size): bool
    {
        $value = $input[$fieldName] ?? '';

        if (is_numeric($value)) {
            return (float)$value === (float)$size;
        }

        return strlen($value) === $size;
    }

    /**
     * Check if the field value starts with a given string
     *
     * @param array $input
     * @param string $fieldName
     * @param string $prefix
     * @return bool
     */
    protected function isStartsWith(array $input, string $fieldName, string $prefix): bool
    {
        return str_starts_with($input[$fieldName] ?? '', $prefix);
    }

    /**
     * Check if the field value ends with a given string
     *
     * @param array $input
     * @param string $fieldName
     * @param string $suffix
     * @return bool
     */
    protected function isEndsWith(array $input, string $fieldName, string $suffix): bool
    {
        return str_ends_with($input[$fieldName] ?? '', $suffix);
    }

    /**
     * Check if the field value is a valid UUID (v1–v5)
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isUuid(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        );
    }

    /**
     * Check if the field value matches a specific date format
     *
     * @param array $input
     * @param string $fieldName
     * @param string $format
     * @return bool
     */
    protected function isDateFormat(array $input, string $fieldName, string $format): bool
    {
        $value = $input[$fieldName] ?? '';

        $date = \DateTime::createFromFormat($format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /**
     * Check if the field value is entirely uppercase
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isUppercase(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return $value !== '' && $value === strtoupper($value) && preg_match('/[A-Z]/', $value);
    }

    /**
     * Check if the field value is entirely lowercase
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isLowercase(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return $value !== '' && $value === strtolower($value) && preg_match('/[a-z]/', $value);
    }

    /**
     * Check if the field value is a valid URL slug
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isSlug(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    /**
     * Check if the field value is a string type
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isString(array $input, string $fieldName): bool
    {
        return is_string($input[$fieldName] ?? null);
    }

    /**
     * Check if the field value is an array type
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isArray(array $input, string $fieldName): bool
    {
        return is_array($input[$fieldName] ?? null);
    }

    /**
     * Check if the field value is a valid IANA timezone identifier
     *
     * @param array $input
     * @param string $fieldName
     * @return bool
     */
    protected function isTimezone(array $input, string $fieldName): bool
    {
        $value = $input[$fieldName] ?? '';

        return in_array($value, \DateTimeZone::listIdentifiers(), true);
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
