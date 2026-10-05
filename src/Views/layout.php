<?php
/**
 * Unified Layout for Jengo Auth views.
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
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?></title>
    
    <!-- Vite Asset Tags / Tailwind CSS Production & Dev Assets -->
    <?php if (function_exists('Jengo\Base\vite_tags')): ?>
        <?= \Jengo\Base\vite_tags() ?>
    <?php endif; ?>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 font-sans antialiased selection:bg-blue-500 selection:text-white flex flex-col justify-between">

    <!-- Header Section (Optional Slot for views that define a custom top bar / dashboard navigation) -->
    <?php if ($this->renderSection('header')): ?>
        <?= $this->renderSection('header') ?>
    <?php endif; ?>

    <!-- Main Content Container: Restricts max-width to 7xl and lets view templates control their inner width -->
    <main class="w-full max-w-7xl mx-auto flex-1 flex flex-col p-4 sm:p-8">
        <!-- Global Flash Messages -->
        <div class="w-full">
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

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm space-y-1">
                    <?php foreach (session()->getFlashdata('errors') as $err): ?>
                        <div>• <?= esc($err) ?></div>
                    <?php endforeach; ?>
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
        </div>

        <?= $this->renderSection('content') ?>

        <!-- Card Footer Slot (if defined) -->
        <?php if ($this->renderSection('footer')): ?>
            <div class="mt-6 text-center text-xs text-slate-500">
                <?= $this->renderSection('footer') ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Global Footer -->
    <footer class="py-6 text-center text-xs text-slate-500 border-t border-slate-900">
        <p>&copy; <?= date('Y') ?> <?= esc($brandName) ?>. All rights reserved.</p>
    </footer>

</body>
</html>

