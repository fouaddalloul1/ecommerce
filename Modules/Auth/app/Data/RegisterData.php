<?php
namespace Modules\Auth\app\Data;

class RegisterData
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public string $password
    ) {}

    public static function from(array $validated): self
    {
        return new self(
            $validated['first_name'],
            $validated['last_name'],
            $validated['email'],
            $validated['password']
        );
    }
}
