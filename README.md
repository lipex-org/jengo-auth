# Jengo Auth

A unified, high-performance **Authentication (AuthN)** and **Vima-powered Authorization (AuthZ)** engine designed specifically for the **Jengo** framework and **CodeIgniter 4**.

---

## 🚀 Key Features

- **Universal Guard**: Intelligently auto-detects `Authorization: Bearer <token>` for API/mobile requests, falling back to session cookies and encrypted Remember-Me tokens for Web/Inertia SPAs.
- **Multi-Identity Architecture**: Decoupled `users` and `user_identities` supporting Email/Password, OAuth providers, Magic links, and Passkeys without modifying user schema.
- **Full Vima AuthZ Parity**:
  - **Explicit Deny System**: Deny rules take strict precedence over grants (`UserDeny` and `UserRoleDeny`).
  - **Hierarchical RBAC**: Recursive parent-child role inheritance.
  - **Direct Grants**: Granular user-level and role-level permissions.
  - **ABAC & Model Policies**: Declarative policies matching entities and schemas.
  - **SuperAdmin Bypass**: Instant access evaluation for designated administrator roles.
  - **Fast Request Caching**: Zero duplicate database queries per request lifecycle.
  - **Immutable Audit Logging**: Built-in audit trail recording security events and permission evaluations.
- **Native Jengo Ergonomics**:
  - Declarative PHP 8 Attributes: `#[Authenticate]`, `#[Can]`, `#[Role]`, `#[Guest]`.
  - FormHandlers: `LoginFormHandler`, `RegisterFormHandler`, `ForgotPasswordFormHandler`, `ResetPasswordFormHandler`, `UpdatePasswordFormHandler`.
  - Entity Obfuscation: Full integration with `BaseEntity` and Sqids integer ID obfuscation.
  - Inertia.js SPAs: Automatic `Inertia::share('auth', ...)` user/permission state propagation.
  - Pre-built REST API: Out-of-the-box endpoints (`/api/v1/auth/*`) with Swagger/OpenAPI support.
- **Shield Migration Wizard**: Built-in CLI command (`php spark jengo:auth import:shield`) to migrate from CodeIgniter Shield.

---

## 📦 Installation

Add `jengo/auth` to your composer dependencies:

```bash
composer require jengo/auth
```

Run the interactive setup wizard and database migrations:

```bash
php spark jengo:auth setup
php spark migrate
```

---

## 🛠 Quick Start

### 1. Authentication (`auth()`)
```php
// Check authentication
if (auth()->check()) {
    $user = auth()->user(); // returns Jengo\Auth\Entities\User
    $userId = auth()->id();
}

// Attempt login
$result = auth()->attempt(['email' => $email, 'password' => $password], remember: true);
if ($result->isSuccess()) {
    // Authenticated
}

// Issue Personal Access Token for API clients
$tokenResult = auth()->createTokenFor($user, 'mobile-app', ['posts.read', 'posts.create']);
$plainText = $tokenResult->plainTextToken;
```

### 2. Authorization & Explicit Denies
```php
// Permission check
if (auth()->can('posts.publish', $post)) {
    // Authorized
}

// Authorize or throw AccessDeniedException
auth()->authorize('posts.delete', $post);

// Fluent RBAC management
auth()->user($user)->grant()->role('editor');
auth()->user($user)->grant()->permission('reports.view');

// Explicit Deny (Overrides all role grants)
auth()->user($user)->deny()->permission('posts.publish', 'User on probation');

// Undeny
auth()->user($user)->undeny()->permission('posts.publish');
```

### 3. Declarative PHP 8 Attributes
```php
namespace App\Controllers;

use Jengo\Auth\Attributes\Authenticate;
use Jengo\Auth\Attributes\Can;
use Jengo\Auth\Attributes\Role;
use Jengo\Auth\Attributes\Guest;

#[Authenticate]
class ArticleController extends BaseController
{
    #[Can('posts.create')]
    public function create() { ... }

    #[Can('update', resource: 'id')]
    public function update(int $id) { ... }

    #[Role('admin')]
    public function destroy(int $id) { ... }
}
```

---

## ⚡ CLI Commands

```bash
# Setup
php spark jengo:auth setup

# Migrate from CodeIgniter Shield
php spark jengo:auth import:shield [--dry-run]

# RBAC Management
php spark jengo:auth grant <user> <role|permission>
php spark jengo:auth deny <user> <permission|role> [reason]
php spark jengo:auth sync
php spark jengo:auth audit [--limit=20]

# Generators
php spark jengo:make policy <Name>
php spark jengo:make role <name> [--parent=parentRole] [--superadmin]
php spark jengo:make permission <name>
```

---

## 🧪 Testing

```bash
./vendor/bin/phpunit
```

---

## 📄 License

MIT License. Developed for the Jengo ecosystem.
