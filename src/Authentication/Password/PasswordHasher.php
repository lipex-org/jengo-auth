<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Password;

class PasswordHasher
{
    protected string $algorithm;
    protected array $options;

    public function __construct(?string $algorithm = null, array $options = [])
    {
        if ($algorithm === null) {
            $this->algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        } else {
            $this->algorithm = $algorithm;
        }

        $this->options = $options;
    }

    /**
     * Hash a plain password string.
     */
    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm, $this->options);
    }

    /**
     * Verify password matches hash.
     */
    public function verify(string $password, string $hash): bool
    {
        if ($hash === '' || $password === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Check if hash needs rehashing with new algorithm/cost.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }
}
