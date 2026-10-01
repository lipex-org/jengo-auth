<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Engines;

/**
 * RFC 6238 / RFC 4226 Time-based One-Time Password (TOTP) Engine.
 */
class TotpEngine
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret key.
     */
    public static function generateSecret(int $byteLength = 20): string
    {
        $randomBytes = random_bytes($byteLength);
        return self::base32Encode($randomBytes);
    }

    /**
     * Generate the current TOTP code for a secret.
     */
    public static function generateCode(
        string $secret,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6,
        string $algo = 'sha1'
    ): string {
        $timestamp ??= time();
        $counter = (int) floor($timestamp / $period);

        $binarySecret = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);

        $hash = hash_hmac($algo, $binaryCounter, $binarySecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % (10 ** $digits);

        return str_pad((string) $value, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted TOTP code against a secret with window drift tolerance.
     */
    public static function verify(
        string $code,
        string $secret,
        int $window = 1,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6,
        string $algo = 'sha1'
    ): bool {
        $code = trim(str_replace([' ', '-'], '', $code));
        if (strlen($code) !== $digits) {
            return false;
        }

        $timestamp ??= time();

        for ($i = -$window; $i <= $window; $i++) {
            $checkTime = $timestamp + ($i * $period);
            $expected = self::generateCode($secret, $checkTime, $period, $digits, $algo);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an otpauth:// URI for QR code generation in authenticator apps.
     */
    public static function getOtpAuthUri(
        string $secret,
        string $accountName,
        string $issuer = 'Jengo',
        int $period = 30,
        int $digits = 6,
        string $algo = 'SHA1'
    ): string {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($accountName);
        $label = "{$encodedIssuer}:{$encodedAccount}";

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            $label,
            $secret,
            $encodedIssuer,
            strtoupper($algo),
            $digits,
            $period
        );
    }

    /**
     * Encode binary string to Base32.
     */
    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $base32 = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $base32 .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $base32;
    }

    /**
     * Decode Base32 string to binary.
     */
    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim(preg_replace('/[^A-Z2-7]/i', '', $base32) ?? ''));
        if ($base32 === '') {
            return '';
        }

        $binary = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos !== false) {
                $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }

        $output = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr((int) bindec($byte));
            }
        }

        return $output;
    }
}
