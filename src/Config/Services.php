<?php

declare(strict_types=1);

namespace Jengo\Auth\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Auth\Support\AuthManager;
use Vima\Core\VimaManager;

class Services extends BaseService
{
    public static function auth(bool $getShared = true): AuthManager
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new AuthManager();
    }

    public static function vima(bool $getShared = true): VimaManager
    {
        return \Vima\CodeIgniter\Config\Services::vima($getShared);
    }

    public static function twoFactor(bool $getShared = true): \Jengo\Auth\TwoFactor\TwoFactorManager
    {
        if ($getShared) {
            return static::getSharedInstance('twoFactor');
        }

        $config = config('Auth');
        $customDrivers = (array) ($config->twoFactor['drivers'] ?? []);

        return new \Jengo\Auth\TwoFactor\TwoFactorManager($customDrivers);
    }

    public static function sudo(bool $getShared = true): \Jengo\Auth\Sudo\SudoManager
    {
        if ($getShared) {
            return static::getSharedInstance('sudo');
        }

        return new \Jengo\Auth\Sudo\SudoManager(static::twoFactor());
    }

    public static function social(bool $getShared = true): \Jengo\Auth\Social\SocialManager
    {
        if ($getShared) {
            return static::getSharedInstance('social');
        }

        return new \Jengo\Auth\Social\SocialManager();
    }
}

