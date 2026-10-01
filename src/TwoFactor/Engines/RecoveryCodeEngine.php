<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Engines;

/**
 * Generates, hashes, and validates single-use emergency backup recovery codes.
 */
class RecoveryCodeEngine
{
    /**
     * Generate a list of human-readable recovery codes (e.g. "a1b2-c3d4").
     *
     * @return list<string>
     */
    public static function generate(int $count = 8, int $segmentLength = 4): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $part1 = substr(bin2hex(random_bytes(4)), 0, $segmentLength);
            $part2 = substr(bin2hex(random_bytes(4)), 0, $segmentLength);
            $codes[] = "{$part1}-{$part2}";
        }

        return $codes;
    }

    /**
     * Hash a list of plaintext recovery codes for secure database storage.
     *
     * @param list<string> $codes
     * @return list<string> Hashed codes
     */
    public static function hashCodes(array $codes): array
    {
        return array_map(
            static fn (string $code) => hash('sha256', self::normalize($code)),
            $codes
        );
    }

    /**
     * Verify and consume a recovery code against a list of stored hashed codes.
     *
     * @param string $submittedCode The code entered by user
     * @param list<string> $storedHashedCodes Reference to stored hashed codes array
     * @return bool True if valid (code is removed from array), false otherwise
     */
    public static function verifyAndConsume(string $submittedCode, array &$storedHashedCodes): bool
    {
        $normalized = self::normalize($submittedCode);
        $submittedHash = hash('sha256', $normalized);

        foreach ($storedHashedCodes as $index => $hashedCode) {
            if (hash_equals($hashedCode, $submittedHash)) {
                unset($storedHashedCodes[$index]);
                $storedHashedCodes = array_values($storedHashedCodes);
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize code input by stripping dashes, spaces, and converting to lowercase.
     */
    public static function normalize(string $code): string
    {
        return strtolower(trim(str_replace(['-', ' '], '', $code)));
    }
}
