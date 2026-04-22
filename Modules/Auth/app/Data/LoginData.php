<?php
namespace Modules\Auth\app\Data;

class LoginData
{
    public function __construct(public string $email, public string $password) {}

    public static function from(array $validated): self
    {
        return new self($validated['email'], $validated['password']);
    }
}
