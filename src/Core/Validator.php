<?php
declare(strict_types=1);

namespace App\Core;

use DateTime;

class Validator
{
    private array $data;
    private array $rules;
    private array $customMessages;
    private array $errors = [];
    private array $validatedData = [];
    private bool $hasRun = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<string>> $rules
     * @param array<string, string> $customMessages
     */
    public function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<string>> $rules
     * @param array<string, string> $customMessages
     */
    public static function make(array $data, array $rules, array $customMessages = []): self
    {
        return new self($data, $rules, $customMessages);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<string>> $rules
     * @param array<string, string> $customMessages
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $customMessages = []): array
    {
        $validator = new self($data, $rules, $customMessages);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }
        return $validator->validated();
    }

    public function passes(): bool
    {
        if ($this->hasRun) {
            return empty($this->errors);
        }

        $this->hasRun = true;
        $this->errors = [];
        $this->validatedData = [];

        foreach ($this->rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $this->data[$field] ?? null;

            $isRequired = in_array('required', $ruleList, true);
            $isNullable = in_array('nullable', $ruleList, true);

            if (($value === null || $value === '') && !$isRequired) {
                if ($isNullable) {
                    $this->validatedData[$field] = null;
                }
                continue;
            }

            foreach ($ruleList as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                $ruleName = $rule;
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule, 2);
                }

                $this->validateRule($field, $value, $ruleName, $ruleParam);
            }

            if (!isset($this->errors[$field])) {
                $this->validatedData[$field] = $value;
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        $this->passes();
        return $this->errors;
    }

    public function first(?string $field = null): ?string
    {
        $this->passes();
        if ($field !== null) {
            return $this->errors[$field][0] ?? null;
        }

        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->passes();
        return $this->validatedData;
    }

    private function validateRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        $humanField = ucwords(str_replace(['_', '-'], ' ', $field));

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, $rule, "{$humanField} is required.");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $rule, "{$humanField} must be a valid email address.");
                }
                break;

            case 'phone':
                $cleanPhone = preg_replace('/[^0-9]/', '', (string)$value);
                if (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '91')) {
                    $cleanPhone = substr($cleanPhone, 2);
                }
                if (!empty($value) && !preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
                    $this->addError($field, $rule, "{$humanField} must be a valid 10-digit mobile number starting with 6-9.");
                }
                break;

            case 'min':
                $min = (int)$param;
                if (is_numeric($value)) {
                    if ((float)$value < $min) {
                        $this->addError($field, $rule, "{$humanField} must be at least {$min}.");
                    }
                } elseif (is_string($value) && mb_strlen($value) < $min) {
                    $this->addError($field, $rule, "{$humanField} must have at least {$min} characters.");
                } elseif (is_array($value) && count($value) < $min) {
                    $this->addError($field, $rule, "{$humanField} must contain at least {$min} items.");
                }
                break;

            case 'max':
                $max = (int)$param;
                if (is_numeric($value)) {
                    if ((float)$value > $max) {
                        $this->addError($field, $rule, "{$humanField} must not exceed {$max}.");
                    }
                } elseif (is_string($value) && mb_strlen($value) > $max) {
                    $this->addError($field, $rule, "{$humanField} must not exceed {$max} characters.");
                } elseif (is_array($value) && count($value) > $max) {
                    $this->addError($field, $rule, "{$humanField} must not contain more than {$max} items.");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, $rule, "{$humanField} must be a valid number.");
                }
                break;

            case 'integer':
                if (!empty($value) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $rule, "{$humanField} must be an integer.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string)$param);
                if (!empty($value) && !in_array((string)$value, $allowed, true)) {
                    $this->addError($field, $rule, "{$humanField} must be one of: " . implode(', ', $allowed) . ".");
                }
                break;

            case 'date':
                if (!empty($value)) {
                    $d = DateTime::createFromFormat('Y-m-d', (string)$value);
                    if (!$d || $d->format('Y-m-d') !== (string)$value) {
                        $this->addError($field, $rule, "{$humanField} must be a valid date in YYYY-MM-DD format.");
                    }
                }
                break;

            case 'regex':
                if (!empty($value) && !preg_match((string)$param, (string)$value)) {
                    $this->addError($field, $rule, "{$humanField} format is invalid.");
                }
                break;

            case 'confirmed':
                $confirmationField = $field . '_confirmation';
                $confirmationValue = $this->data[$confirmationField] ?? null;
                if ($value !== $confirmationValue) {
                    $this->addError($field, $rule, "{$humanField} confirmation does not match.");
                }
                break;
        }
    }

    private function addError(string $field, string $rule, string $defaultMessage): void
    {
        $customKey = "{$field}.{$rule}";
        $message = $this->customMessages[$customKey]
            ?? $this->customMessages[$field]
            ?? $defaultMessage;

        $this->errors[$field][] = $message;
    }
}
