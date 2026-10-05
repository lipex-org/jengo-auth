<?php
$title = 'Log In';
$heading = 'Welcome back';
$subheading = 'Please enter your details to sign in.';
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
        <form action="<?= url_to('login.attempt') ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Email or Username
                </label>
                <input 
                    type="text" 
                    id="email" 
                    name="email" 
                    value="<?= old('email') ?>" 
                    required 
                    autofocus
                    placeholder="you@example.com"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                        Password
                    </label>
                    <?php if (!empty($can_reset_password)): ?>
                        <a href="<?= url_to('forgot-password') ?>" class="text-xs text-blue-400 hover:text-blue-300 transition">
                            Forgot password?
                        </a>
                    <?php endif; ?>
                </div>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember" 
                        value="1"
                        class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-blue-500 focus:ring-offset-slate-900 cursor-pointer"
                    >
                    <span class="text-xs text-slate-400">Remember me</span>
                </label>

                <?php if (!empty($allow_magic_link)): ?>
                    <a href="<?= url_to('magic-link') ?>" class="text-xs text-blue-400 hover:text-blue-300 transition">
                        Use magic link
                    </a>
                <?php endif; ?>
            </div>

            <button 
                type="submit" 
                class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
            >
                Sign In
            </button>
        </form>

        <?php if (!empty($social_providers)): ?>
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-800"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-slate-900 px-3 text-slate-500 font-medium tracking-wider">Or continue with</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2.5">
                <?php foreach ($social_providers as $provider): ?>
                    <a 
                        href="<?= esc($provider['url']) ?>" 
                        class="flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-950 hover:bg-slate-800 border border-slate-800 rounded-xl text-xs font-semibold text-slate-200 transition duration-150"
                    >
                        <span>Continue with <?= esc($provider['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Links -->
    <div class="text-center text-xs text-slate-400">
        Don't have an account? 
        <a href="<?= url_to('register') ?>" class="font-semibold text-blue-400 hover:text-blue-300 transition">Sign up</a>
    </div>
</div>
<?= $this->endSection() ?>
