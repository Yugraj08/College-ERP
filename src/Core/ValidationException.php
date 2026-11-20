<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class ValidationException extends RuntimeException
{
    private array $errors;

    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(array $errors, string $message = 'The given data failed validation.', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return (string)$fieldErrors[0];
            }
        }
        return null;
    }
}
