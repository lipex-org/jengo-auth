# Jengo Auth

A unified authentication and authorization engine for **CodeIgniter 4** and the **Jengo Framework**, powered by **Vima**.

---

## 🚀 Highlights

- **Universal Guard**: Auto-detects Bearer tokens for APIs and falls back to session cookies / remember-me tokens for web apps.
- **Flexible Response Modifiers**: Switch between traditional CI4 HTML views, REST API JSON, or Inertia.js SPAs via config.
- **Pluggable Notifications**: Route emails, SMS, magic links, and MFA codes directly or through background queues.
- **Smart Throttling**: Multi-signal rate limiter that protects against brute-force attacks without locking out shared IPs.
- **Vima Authorization**: Full RBAC with role hierarchies, ABAC policies, direct grants, explicit denies, and TypeScript map generation.

---

## 📦 Installation & Setup

```bash
composer require jengo/auth
php spark jengo:auth setup
php spark migrate
```

> **Note**: `jengo:auth setup` automatically publishes `Config/Auth.php`, `Config/Vima.php`, `app/Libraries/Vima/Setup.php`, and registers the auth routes in `app/Config/Routes.php`.

---

## 🛣 Route Publishing (`app/Config/Routes.php`)

Publish and customize all authentication routes with a single call:

```php
// In app/Config/Routes.php

// Basic publishing (default paths)
service('auth')->routes($routes);

// Custom prefix, paths, and filters
service('auth')->routes($routes, [
    'prefix' => 'auth', // e.g. /auth/sign-in, /auth/join
    'paths'  => [
        'login'    => 'sign-in',
        'logout'   => 'sign-out',
        'register' => 'join',
    ],
    'logoutMethod' => 'post', // 'post' (default) or 'get'
]);

// Register specific flows only
service('auth')->routes($routes, [
    'only' => ['login', 'register'], // or 'except' => ['tokens', 'magic-link']
]);

// Controller overrides & named route prefixing
service('auth')->routes($routes, [
    'as'          => 'admin.', // creates admin.login, admin.register, etc.
    'controllers' => [
        'login' => \App\Controllers\AdminLoginController::class,
    ],
]);
```

---

## 🛡 Roles, Permissions & Syncing (Vima Workflow)

### 1. Define Initial Roles & Permissions

Edit `app/Libraries/Vima/Setup.php` to define your roles, hierarchy, and permissions:

```php
namespace App\Libraries\Vima;

use Vima\Core\Config\Contracts\SetupProviderInterface;
use Vima\Core\Role\Entities\Role;
use Vima\Core\Permission\Entities\Permission;

class Setup implements SetupProviderInterface
{
    public function get(): array
    {
        return [
            'permissions' => [
                Permission::define('post.create', 'Here is a description'),
                Permission::define('post.edit', 'Here is a description'),
            ],
            'roles' => [
                Role::define('admin', 'Here is a description')
                ->withPermissions([
                    'post.create',
                    'post.edit',
                ])
            ]
        ];
    }
}
```

### 2. Sync to Database

Synchronize the definition file into your database:

```bash
php spark vima:sync
```

### 3. Generate TypeScript Mappings (Frontend / Inertia.js)

Generate type-safe PHP mappers and TypeScript mapping files for frontend auto-completion:

```bash
# Generates PHP classes in App\Mappers\Vima and TypeScript definitions in resources/js/vima/
php spark vima:maps:generate --ts
```

---

## 💻 Quick Start

### 1. Authentication

```php
// Check authentication
if (auth()->check()) {
    $user   = auth()->user(); // Jengo\Auth\Entities\User
    $userId = auth()->id();
}

// Attempt login
$result = auth()->attempt(['email' => $email, 'password' => $password], remember: true);

// Issue Personal Access Token for API / mobile clients
$token = auth()->createTokenFor($user, 'mobile-app', ['posts.read']);
echo $token->plainTextToken;
```

---

### 2. Authorization & Explicit Denies

```php
// Permission or policy check
if (can('posts.publish', $post)) {
    // Authorized
}

// Fluent role & permission management
auth()->user($user)->grant()->role('editor');
auth()->user($user)->grant()->permission('reports.view');

// Explicit Deny (strictly overrides any role grant)
auth()->user($user)->deny()->permission('posts.publish', 'Account on hold');
```

---

### 3. Declarative Attributes

```php
namespace App\Controllers;

use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\Attributes\Can;
use Jengo\Auth\Attributes\Role;

#[Authenticate]
class PostController extends BaseController
{
    #[Can('posts.create')]
    public function create() { ... }

    #[Role('admin')]
    public function delete(int $id) { ... }
}
```

---

### 4. Custom Notifications / Queues

Swap the default email sender with your own queue or provider in `Config/Auth.php`:

```php
namespace App\Notifications;

use Jengo\Auth\Contracts\NotificationSenderInterface;
use Jengo\Auth\Entities\User;

class QueuedAuthNotifier implements NotificationSenderInterface
{
    public function sendMagicLink(User $user, string $token, string $url): bool
    {
        queue('emails')->push(new SendMagicLinkJob($user->getEmail(), $url));
        return true;
    }

    public function sendPasswordReset(User $user, string $token, string $url): bool { ... }
    public function sendMfaCode(User $user, string $code): bool { ... }
    public function sendActivation(User $user, string $token, string $url): bool { ... }
    public function sendNotification(string $type, User $user, array $data = []): bool { ... }
}
```

---

### 5. Custom Guards & Drivers

Register custom authentication drivers (e.g. JWT, HMAC, API Key) via `extend()` or `Config/Auth.php`:

```php
// Register a custom guard driver
auth()->extend('jwt', fn() => new \App\Authentication\Guards\JwtGuard());

// Use it explicitly or set as default in Config/Auth.php ($defaultGuard = 'jwt')
if (auth()->guard('jwt')->check()) {
    $user = auth()->guard('jwt')->user();
}
```

---

## ⚡ CLI Commands

```bash
# Setup & Initial Configuration (Publishes Auth, Vima & Routes)
php spark jengo:auth setup

# Migrate users & credentials from CodeIgniter Shield
php spark jengo:auth import:shield [--dry-run]

# Vima Authorization & Mapping Commands (vima/codeigniter)
php spark vima:sync                         # Syncs roles & permissions from Setup.php to DB
php spark vima:maps:generate [--ts]         # Generates PHP & TypeScript mapping artifacts
php spark vima:role:create <name>           # Create a role
php spark vima:permission:create <name>     # Create a permission
php spark vima:grant <user> <role|perm>     # Grant role or permission
php spark vima:deny <user> <perm> [reason]  # Explicitly deny permission
php spark vima:make:policy <Name>           # Scaffold ABAC policy
```

---

## 🧪 Testing

```bash
./vendor/bin/phpunit
```

---

## 📄 License

MIT License. Developed for the Jengo ecosystem.
