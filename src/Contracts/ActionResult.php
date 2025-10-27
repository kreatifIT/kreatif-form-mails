<?php

namespace Kreatif\StatamicForms\Contracts;

class ActionResult
{
    public function __construct(
        public bool $success = true,
        public mixed $data = null,
        public array $errors = [],
        public ?string $message = null
    ) {
    }

    public static function success(mixed $data = null, ?string $message = null): self
    {
        return new self(success: true, data: $data, message: $message);
    }

    public static function failure(array $errors = [], ?string $message = null): self
    {
        return new self(success: false, errors: $errors, message: $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
