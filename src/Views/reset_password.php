<?php
$title = 'Reset Password';
$heading = 'Set new password';
$subheading = 'Your new password must be different from previously used passwords.';
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
        <form action="<?= url_to('reset-password.attempt') ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token ?? old('token')) ?>">

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    New Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autofocus
                    placeholder="••••••••"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <div>
                <label for="password_confirm" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                    Confirm New Password
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
                Update Password
            </button>
        </form>
    </div>

    <!-- Footer Links -->
    <div class="text-center text-xs text-slate-400">
        <a href="<?= url_to('login') ?>" class="font-semibold text-blue-400 hover:text-blue-300 transition">Back to sign in</a>
    </div>
</div>
<?= $this->endSection() ?>
