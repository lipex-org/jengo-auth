<?php
/**
 * Layout for Jengo Auth views.
 *
 * @var string $title
 * @var array $brand
 * @var \Jengo\Auth\Entities\User|null $user
 * @var string|null $message
 * @var string|null $error
 * @var array|null $errors
 */

$authConfig = function_exists('config') ? config('Auth') : null;
$branding = $brand ?? ($authConfig->branding ?? []);
$brandName = $branding['name'] ?? ($authConfig->brandName ?? 'Jengo');
$brandLogo = $branding['logo'] ?? ($authConfig->brandLogo ?? null);
$currentUser = $user ?? (function_exists('auth') && auth()->check() ? auth()->user() : null);

$pageTitle = isset($title) && $title !== '' ? $title . ' - ' . $brandName : $brandName;
$layoutMode = $layoutMode ?? 'auth-card'; // 'auth-card' or 'dashboard'
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 font-sans antialiased selection:bg-blue-500 selection:text-white flex flex-col justify-between">

    <?php if ($layoutMode === 'dashboard'): ?>
        <!-- Dashboard / Settings Layout Header -->
        <header class="border-b border-slate-800 bg-slate-900/40 backdrop-blur sticky top-0 z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <?php if ($brandLogo): ?>
                        <img src="<?= esc($brandLogo) ?>" alt="<?= esc($brandName) ?>" class="h-8 w-auto">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20">
                            <?= strtoupper(substr($brandName, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="font-bold text-white tracking-tight"><?= esc($brandName) ?></span>
                </div>

                <div class="flex items-center gap-3">
                    <?php if ($currentUser): ?>
                        <span class="text-xs font-medium px-3 py-1 rounded-full bg-slate-900 text-slate-300 border border-slate-800">
                            Logged in as <strong class="text-white"><?= esc($currentUser->username ?? $currentUser->email ?? 'User') ?></strong>
                        </span>
                        <form action="<?= function_exists('auth_url') ? auth_url('logout') : site_url('logout') ?>" method="POST" class="inline m-0 p-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-950/40 hover:bg-red-900/60 border border-red-800/40 px-3 py-1.5 rounded-lg transition duration-150">
                                Log Out
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Dashboard Content Area -->
        <main class="max-w-5xl mx-auto w-full px-4 sm:px-6 py-8 flex-1">
            <!-- Global Flash Messages -->
            <?php if (session()->getFlashdata('message') || session()->getFlashdata('success')): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= esc(session()->getFlashdata('message') ?? session()->getFlashdata('success')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('warning')): ?>
                <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span><?= esc(session()->getFlashdata('warning')) ?></span>
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </main>

    <?php else: ?>
        <!-- Centered Auth Card Layout (Login, Register, Forgot Password, Reset, Sudo, MFA, etc.) -->
        <main class="flex-1 flex flex-col items-center justify-center p-4 sm:p-8">
            <div class="w-full max-w-md">
                <!-- Branding Header -->
                <div class="text-center mb-8">
                    <?php if ($brandLogo): ?>
                        <img src="<?= esc($brandLogo) ?>" alt="<?= esc($brandName) ?>" class="h-12 w-auto mx-auto mb-3">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center font-bold text-xl text-white shadow-xl shadow-blue-500/20 mx-auto mb-3">
                            <?= strtoupper(substr($brandName, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <h1 class="text-2xl font-bold tracking-tight text-white"><?= esc($heading ?? $pageTitle) ?></h1>
                    <?php if (!empty($subheading)): ?>
                        <p class="text-sm text-slate-400 mt-1.5"><?= esc($subheading) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Main Auth Card Container -->
                <div class="bg-slate-900/90 backdrop-blur border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
                    <!-- Global Flash Messages -->
                    <?php if (session()->getFlashdata('message') || session()->getFlashdata('success')): ?>
                        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?= esc(session()->getFlashdata('message') ?? session()->getFlashdata('success')) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?= esc(session()->getFlashdata('error')) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('errors')): ?>
                        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm space-y-1">
                            <?php foreach (session()->getFlashdata('errors') as $err): ?>
                                <div>• <?= esc($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (session()->getFlashdata('warning')): ?>
                        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm flex items-center gap-2">
                            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span><?= esc(session()->getFlashdata('warning')) ?></span>
                        </div>
                    <?php endif; ?>

                    <?= $this->renderSection('content') ?>
                </div>

                <!-- Card Footer Slot -->
                <?php if ($this->renderSection('footer')): ?>
                    <div class="mt-6 text-center text-xs text-slate-500">
                        <?= $this->renderSection('footer') ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    <?php endif; ?>

    <!-- Global Footer -->
    <footer class="py-6 text-center text-xs text-slate-500 border-t border-slate-900">
        <p>&copy; <?= date('Y') ?> <?= esc($brandName) ?>. All rights reserved.</p>
    </footer>

</body>
</html>
