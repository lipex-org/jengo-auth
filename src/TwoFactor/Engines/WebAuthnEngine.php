<?php

declare(strict_types=1);

namespace Jengo\Auth\TwoFactor\Engines;

use Jengo\Auth\Entities\User;
use RuntimeException;

/**
 * Native FIDO2 / WebAuthn Passkey Engine using PHP OpenSSL.
 */
class WebAuthnEngine
{
    /**
     * Generate WebAuthn creation options for registering a new Passkey / Security Key.
     *
     * @param User $user The user registering the passkey
     * @param string $rpName Relying Party name (e.g. "Jengo App")
     * @param string $rpId Relying Party domain (e.g. "example.com" or "localhost")
     * @param list<string> $excludeCredentialIds Already registered credential IDs for this user
     * @return array<string, mixed> Options array to pass to navigator.credentials.create()
     */
    public static function generateCreationOptions(
        User $user,
        string $rpName,
        string $rpId,
        array $excludeCredentialIds = []
    ): array {
        $challenge = random_bytes(32);
        $userId = (string) ($user->id ?? $user->attributes['id'] ?? 'user_' . bin2hex(random_bytes(8)));
        $userName = (string) ($user->email ?? $user->username ?? 'user');
        $displayName = (string) ($user->name ?? $user->username ?? $userName);

        $excludeList = [];
        foreach ($excludeCredentialIds as $credId) {
            $excludeList[] = [
                'type' => 'public-key',
                'id'   => $credId,
            ];
        }

        return [
            'challenge' => self::base64UrlEncode($challenge),
            'rp' => [
                'name' => $rpName,
                'id'   => $rpId,
            ],
            'user' => [
                'id'          => self::base64UrlEncode($userId),
                'name'        => $userName,
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256 (ECDSA P-256)
                ['type' => 'public-key', 'alg' => -257], // RS256 (RSA 2048)
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification'        => 'preferred',
                'residentKey'             => 'preferred',
            ],
            'timeout'     => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $excludeList,
        ];
    }

    /**
     * Generate WebAuthn assertion options for authenticating via Passkey.
     *
     * @param list<array{id: string, transports?: list<string>}> $allowedCredentials
     * @param string $rpId
     * @return array<string, mixed> Options array to pass to navigator.credentials.get()
     */
    public static function generateRequestOptions(
        array $allowedCredentials,
        string $rpId
    ): array {
        $challenge = random_bytes(32);

        $allowList = [];
        foreach ($allowedCredentials as $cred) {
            $item = [
                'type' => 'public-key',
                'id'   => $cred['id'],
            ];
            if (!empty($cred['transports'])) {
                $item['transports'] = $cred['transports'];
            }
            $allowList[] = $item;
        }

        return [
            'challenge' => self::base64UrlEncode($challenge),
            'rpId'      => $rpId,
            'timeout'   => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allowList,
        ];
    }

    /**
     * Verify a WebAuthn assertion signature during authentication.
     *
     * @param array<string, mixed> $response Client response from navigator.credentials.get()
     * @param string $expectedChallenge Base64URL-encoded challenge stored in session
     * @param string $publicKeyPem Stored public key in PEM format
     * @param string $expectedRpId Expected Relying Party ID
     * @param int $prevCounter Previous signature counter
     * @return array{verified: bool, newCounter: int, error?: string}
     */
    public static function verifyAssertionResponse(
        array $response,
        string $expectedChallenge,
        string $publicKeyPem,
        string $expectedRpId,
        int $prevCounter = 0
    ): array {
        $clientDataJSON = self::base64UrlDecode($response['clientDataJSON'] ?? '');
        $authenticatorData = self::base64UrlDecode($response['authenticatorData'] ?? '');
        $signature = self::base64UrlDecode($response['signature'] ?? '');

        if (!$clientDataJSON || !$authenticatorData || !$signature) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Missing response payload fields.'];
        }

        // 1. Verify ClientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if (!is_array($clientData)) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Invalid clientDataJSON.'];
        }

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Unexpected challenge type.'];
        }

        if (!hash_equals($expectedChallenge, $clientData['challenge'] ?? '')) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Challenge mismatch.'];
        }

        // 2. Verify Authenticator Data
        if (strlen($authenticatorData) < 37) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Malformed authenticator data.'];
        }

        $rpIdHash = substr($authenticatorData, 0, 32);
        $flags = ord($authenticatorData[32]);
        $counter = unpack('N', substr($authenticatorData, 33, 4))[1];

        $expectedRpIdHash = hash('sha256', $expectedRpId, true);
        if (!hash_equals($expectedRpIdHash, $rpIdHash)) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'RP ID hash mismatch.'];
        }

        // Check User Present (UP) flag (bit 0)
        if (($flags & 0x01) !== 0x01) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'User was not present.'];
        }

        // 3. Counter check (detect clone / replay attacks if counter was incremented)
        if ($counter > 0 && $counter <= $prevCounter) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Potential authenticator cloning detected.'];
        }

        // 4. Verify Cryptographic Signature
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData = $authenticatorData . $clientDataHash;

        $isValid = openssl_verify($signedData, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($isValid !== 1) {
            return ['verified' => false, 'newCounter' => $prevCounter, 'error' => 'Cryptographic signature verification failed.'];
        }

        return [
            'verified'   => true,
            'newCounter' => $counter,
        ];
    }

    /**
     * Parse raw registration response and extract credential ID and Public Key PEM.
     *
     * @param array<string, mixed> $response Client response from navigator.credentials.create()
     * @param string $expectedChallenge Stored challenge
     * @param string $expectedRpId Expected RP domain
     * @return array{credentialId: string, publicKeyPem: string, aaguid: string, transports: list<string>}
     */
    public static function parseRegistrationResponse(
        array $response,
        string $expectedChallenge,
        string $expectedRpId
    ): array {
        $credentialId = $response['id'] ?? '';
        $clientDataJSON = self::base64UrlDecode($response['clientDataJSON'] ?? '');
        $attestationObject = self::base64UrlDecode($response['attestationObject'] ?? '');

        if (!$clientDataJSON || !$attestationObject || !$credentialId) {
            throw new RuntimeException('Missing required WebAuthn registration parameters.');
        }

        $clientData = json_decode($clientDataJSON, true);
        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new RuntimeException('Invalid WebAuthn creation client data.');
        }

        if (!hash_equals($expectedChallenge, $clientData['challenge'] ?? '')) {
            throw new RuntimeException('WebAuthn challenge mismatch.');
        }

        // Parse CBOR attestation object
        $authData = self::extractAuthDataFromAttestation($attestationObject);
        if (strlen($authData) < 55) {
            throw new RuntimeException('Malformed authData in attestation.');
        }

        $rpIdHash = substr($authData, 0, 32);
        $expectedRpIdHash = hash('sha256', $expectedRpId, true);
        if (!hash_equals($expectedRpIdHash, $rpIdHash)) {
            throw new RuntimeException('RP ID mismatch during registration.');
        }

        $aaguid = bin2hex(substr($authData, 37, 16));
        $credIdLen = unpack('n', substr($authData, 53, 2))[1];
        $rawCredId = substr($authData, 55, $credIdLen);
        $coseKeyBytes = substr($authData, 55 + $credIdLen);

        $publicKeyPem = self::coseToPem($coseKeyBytes);

        $transports = $response['transports'] ?? ['internal', 'hybrid'];

        return [
            'credentialId' => $credentialId,
            'publicKeyPem' => $publicKeyPem,
            'aaguid'       => $aaguid,
            'transports'   => is_array($transports) ? $transports : [],
        ];
    }

    /**
     * Extract authData bytes from CBOR attestation structure.
     */
    private static function extractAuthDataFromAttestation(string $cborBytes): string
    {
        // Simple search for "authData" string key in CBOR
        $authDataKey = 'authData';
        $pos = strpos($cborBytes, $authDataKey);
        if ($pos === false) {
            throw new RuntimeException('authData not found in attestation object.');
        }

        $offset = $pos + strlen($authDataKey);
        $head = ord($cborBytes[$offset]);

        // Major type 2 (byte string)
        if (($head & 0xE0) === 0x40) {
            $lenInfo = $head & 0x1F;
            if ($lenInfo < 24) {
                return substr($cborBytes, $offset + 1, $lenInfo);
            }
            if ($lenInfo === 24) {
                $len = ord($cborBytes[$offset + 1]);
                return substr($cborBytes, $offset + 2, $len);
            }
            if ($lenInfo === 25) {
                $len = unpack('n', substr($cborBytes, $offset + 1, 2))[1];
                return substr($cborBytes, $offset + 3, $len);
            }
            if ($lenInfo === 26) {
                $len = unpack('N', substr($cborBytes, $offset + 1, 4))[1];
                return substr($cborBytes, $offset + 5, $len);
            }
        }

        return substr($cborBytes, $offset + 1);
    }

    /**
     * Convert COSE Key bytes to OpenSSL PEM format.
     */
    public static function coseToPem(string $coseBytes): string
    {
        // Check if already PEM
        if (str_contains($coseBytes, '-----BEGIN')) {
            return $coseBytes;
        }

        // Basic EC P-256 (COSE Alg -7) key extraction
        // Public key X, Y coordinates: 32 bytes each
        // An uncompressed EC point is: 0x04 || X (32) || Y (32) = 65 bytes
        // ASN.1 EC SubjectPublicKeyInfo template:
        $xPos = strpos($coseBytes, chr(0x20)); // -2: x coordinate
        $yPos = strpos($coseBytes, chr(0x21)); // -3: y coordinate

        // Fallback: extract last 64 or 65 bytes if standard COSE map
        $rawPoint = substr($coseBytes, -64);
        if (strlen($rawPoint) === 64) {
            $uncompressedPoint = "\x04" . $rawPoint;
            // ASN.1 DER header for P-256 (secp256r1)
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uncompressedPoint;
            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        }

        // Fallback wrap raw bytes in PEM
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($coseBytes), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
