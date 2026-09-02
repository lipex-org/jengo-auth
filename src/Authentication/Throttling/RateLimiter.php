<?php

declare(strict_types=1);

namespace Jengo\Auth\Authentication\Throttling;

use CodeIgniter\HTTP\RequestInterface;
use Config\Services;
use Jengo\Auth\Entities\User;

class RateLimiter
{
    /**
     * Resolve a unique throttle key for the request.
     * If user is authenticated, uses user ID.
     * If guest, builds a composite key combining identifier (if provided), IP address, and client fingerprint.
     */
    public function resolveKey(?RequestInterface $request = null, ?string $identifier = null, string $action = 'auth'): string
    {
        $currentUser = auth()->user();
        if ($currentUser) {
            return $this->forUser($currentUser, $action);
        }

        $req = $request ?? Services::request();
        return $this->forGuest($req, $identifier, $action);
    }

    /**
     * Generate a throttle key for an authenticated user.
     */
    public function forUser(int|string|User $user, string $action = 'auth'): string
    {
        $userId = $user instanceof User ? $user->id : (string) $user;
        return "throttle:user:{$userId}:{$action}";
    }

    /**
     * Generate a composite throttle key for a guest using multiple entropy signals:
     * - Target identifier (e.g. email/username, lowercase)
     * - Client IP address
     * - User-Agent header and Device/Client fingerprint
     */
    public function forGuest(RequestInterface $request, ?string $identifier = null, string $action = 'auth'): string
    {
        $ip = $request->getIPAddress();
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1') {
            $ip = 'localhost';
        }

        // 1. Target identifier (lowercase & trimmed to isolate rate limits per targeted account)
        $identPart = $identifier !== null && $identifier !== '' ? strtolower(trim($identifier)) : 'global';

        // 2. Client Device & Browser entropy
        $userAgent = (string) $request->getHeaderLine('User-Agent');
        $deviceId = (string) ($request->getHeaderLine('X-Device-Id') ?: $request->getHeaderLine('X-Client-Id'));
        $acceptLang = (string) $request->getHeaderLine('Accept-Language');

        $fingerprint = substr(hash('sha256', "{$userAgent}|{$deviceId}|{$acceptLang}"), 0, 16);

        return "throttle:guest:{$action}:{$identPart}:{$ip}:{$fingerprint}";
    }

    /**
     * Determine if the given key has exceeded max attempts.
     */
    public function tooManyAttempts(string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $safeKey = md5($key);
        $throttler = Services::throttler();
        return ! $throttler->check($safeKey, $maxAttempts, $decaySeconds);
    }

    /**
     * Hit the rate limiter key.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $safeKey = md5($key);
        $throttler = Services::throttler();
        $throttler->check($safeKey, 1000, $decaySeconds);
        return $throttler->getTokenTime();
    }

    /**
     * Clear the rate limiter attempts.
     */
    public function clear(string $key): void
    {
        // CodeIgniter's Throttler uses cache with time-based token decay.
        // We can explicitly remove the cached bucket.
        $safeKey = md5($key);
        $cache = Services::cache();
        $cache->delete($safeKey);
    }

    /**
     * Get remaining seconds until available.
     */
    public function availableIn(string $key): int
    {
        $safeKey = md5($key);
        return Services::throttler()->getTokenTime();
    }
}
