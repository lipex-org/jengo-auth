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
}
