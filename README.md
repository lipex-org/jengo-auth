<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/docs/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Auth</h1>

<p align="center">
  <strong>A unified authentication and authorization engine for CodeIgniter 4 and the Jengo Framework, powered by Vima. Features Universal Guard, multi-step MFA actions, scoped API tokens, and fine-grained RBAC/ABAC permissions.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/auth"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/auth/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/auth/issues"><strong>Issues</strong></a>
</p>

---

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
