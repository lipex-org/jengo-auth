# Jengo Auth

A unified authentication and authorization engine for CodeIgniter 4 and the Jengo Framework, powered by Vima. Features Universal Guard, multi-step MFA actions, scoped API tokens, and fine-grained RBAC/ABAC permissions.

Documentation: https://lipex-org.github.io/jengophp.com/packages/auth

## Installation

```bash
composer require jengo/auth
php spark jengo:auth setup
php spark migrate
```

## Quick Start

```php
// Check authentication
if (auth()->check()) {
    $user = auth()->user();
}

// Attempt login
auth()->attempt(['email' => $email, 'password' => $password], remember: true);

// Check permissions with Vima
if (can('posts.publish', $post)) {
    // Authorized
}
```

## Documentation

For full guides on Universal Guard, route publishing, Vima RBAC/ABAC workflows, response modifiers, and CLI commands, visit https://lipex-org.github.io/jengophp.com/packages/auth.

## License

Released under the MIT License.
