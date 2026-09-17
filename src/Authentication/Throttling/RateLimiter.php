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
     * Generate an IP-only throttle key (prevents distributed password spraying across multiple accounts).
     */
    public function ipKey(RequestInterface $request, string $action = 'auth'): string
    {
        $ip = $request->getIPAddress();
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1' || $ip === '0.0.0.0') {
            $ip = 'localhost';
        }

        return "throttle:ip:{$action}:{$ip}";
    }

    /**
     * Generate an Account-only throttle key (prevents targeted brute forcing of a single account regardless of IP/User-Agent rotation).
     */
    public function accountKey(?string $identifier, string $action = 'auth'): string
    {
        $identPart = $identifier !== null && $identifier !== '' ? strtolower(trim($identifier)) : 'global';

        return "throttle:account:{$action}:{$identPart}";
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
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1' || $ip === '0.0.0.0') {
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
     * Dual-Bucket throttle check: checks both IP bucket and target account bucket.
     */
    public function isThrottled(RequestInterface $request, ?string $identifier, string $action = 'auth', int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $compositeKey = $this->forGuest($request, $identifier, $action);
        if ($this->tooManyAttempts($compositeKey, $maxAttempts, $decaySeconds)) {
            return true;
        }

        if ($identifier !== null && $identifier !== '') {
            $accKey = $this->accountKey($identifier, $action);
            if ($this->tooManyAttempts($accKey, $maxAttempts * 2, $decaySeconds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record failure in both composite and account buckets.
     */
    public function recordFailure(RequestInterface $request, ?string $identifier, string $action = 'auth', int $decaySeconds = 60): void
    {
        $this->hit($this->forGuest($request, $identifier, $action), $decaySeconds);

        if ($identifier !== null && $identifier !== '') {
            $this->hit($this->accountKey($identifier, $action), $decaySeconds);
        }
    }

    /**
     * Record success by clearing both composite and account buckets.
     */
    public function recordSuccess(RequestInterface $request, ?string $identifier, string $action = 'auth'): void
    {
        $this->clear($this->forGuest($request, $identifier, $action));

        if ($identifier !== null && $identifier !== '') {
            $this->clear($this->accountKey($identifier, $action));
        }
    }

    /**
     * Determine if the given key has exceeded max attempts.
     */
    public function tooManyAttempts(string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $safeKey = md5($key);
        $cache = Services::cache();
        $attempts = (int) $cache->get('throttle_hits_' . $safeKey);

        return $attempts >= $maxAttempts;
    }

    /**
     * Hit the rate limiter key.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $safeKey = md5($key);
        $cache = Services::cache();
        $hitsKey = 'throttle_hits_' . $safeKey;
        $attempts = (int) $cache->get($hitsKey);
        $attempts++;
        $cache->save($hitsKey, $attempts, $decaySeconds);

        $timerKey = 'throttle_timer_' . $safeKey;
        if (! $cache->get($timerKey)) {
            $cache->save($timerKey, time() + $decaySeconds, $decaySeconds);
        }

        return $decaySeconds;
    }

    /**
     * Clear the rate limiter attempts.
     */
    public function clear(string $key): void
    {
        $safeKey = md5($key);
        $cache = Services::cache();
        $cache->delete('throttle_hits_' . $safeKey);
        $cache->delete('throttle_timer_' . $safeKey);
        $cache->delete('throttler_' . $safeKey);
        $cache->delete($safeKey);
    }

    /**
     * Get remaining seconds until available.
     */
    public function availableIn(string $key): int
    {
        $safeKey = md5($key);
        $cache = Services::cache();
        $timerKey = 'throttle_timer_' . $safeKey;
        $expiresAt = (int) $cache->get($timerKey);
        if ($expiresAt > 0) {
            return max(1, $expiresAt - time());
        }

        return 60;
    }
}
