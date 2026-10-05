<?php
$title = 'Register';
$heading = 'Create an account';
$subheading = 'Enter your details below to get started.';
$authConfig = function_exists('config') ? config('Auth') : null;
$branding = $brand ?? ($authConfig->branding ?? []);
$brandName = $branding['name'] ?? ($authConfig->brandName ?? 'Jengo');
$brandLogo = $branding['logo'] ?? ($authConfig->brandLogo ?? null);

$this->extend('Jengo\Auth\Views\layout');
?>

<?= $this->section('content') ?>
<div class="w-full max-w-md mx-auto my-auto space-y-6">
    <!-- Header / Brand -->
    <div class="text-center space-y-2">
        <?php if (!empty($brandLogo)): ?>
            <img src="<?= esc($brandLogo) ?>" alt="Logo" class="mx-auto h-12 w-auto mb-4">
        <?php else: ?>
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-600/10 border border-blue-500/20 text-blue-500 font-bold text-xl mb-2">
                <?= substr(esc($brandName), 0, 1) ?>
            </div>
        <?php endif; ?>
        <h1 class="text-2xl font-bold tracking-tight text-white"><?= esc($heading) ?></h1>
        <?php if (!empty($subheading)): ?>
            <p class="text-xs text-slate-400"><?= esc($subheading) ?></p>
        <?php endif; ?>
    </div>

    <!-- Auth Card -->
    <div class="bg-slate-900/90 backdrop-blur border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6">
        <form action="<?= url_to('register.attempt') ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Username
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= old('username') ?>" 
                    required 
                    autofocus
                    placeholder="johndoe"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Email Address
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= old('email') ?>" 
                    required 
                    placeholder="you@example.com"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div>
                <label for="password_confirm" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Confirm Password
                </label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    required
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <button 
                type="submit" 
                class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
            >
                Create Account
            </button>
        </form>

        <?php if (!empty($social_providers)): ?>
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-slate-900 px-3 text-slate-500 font-medium tracking-wider">Or sign up with</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2.5">
                <?php foreach ($social_providers as $provider): ?>
                    <a 
                        href="<?= esc($provider['url']) ?>" 
                        class="flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 rounded-xl text-xs font-semibold text-slate-200 transition duration-150"
                    >
                        <span>Sign up with <?= esc($provider['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Links -->
    <div class="text-center text-xs text-slate-400">
        Already have an account? 
        <a href="<?= url_to('login') ?>" class="font-semibold text-blue-400 hover:text-blue-300 transition">Sign in</a>
    </div>
</div>
<?= $this->endSection() ?>
