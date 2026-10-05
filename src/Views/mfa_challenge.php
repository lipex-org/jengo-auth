<?php
$title = 'Two-Factor Verification';
$heading = 'Two-Factor Verification';
$subheading = 'Enter the 6-digit verification code sent to your registered contact.';
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
        <form action="<?= url_to('auth.action.handle') ?>" method="post" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5 text-center">
                    Security Code
                </label>
                <input 
                    type="text" 
                    id="code" 
                    name="code" 
                    maxlength="6" 
                    pattern="[0-9]{6}" 
                    required 
                    autofocus 
                    placeholder="123456"
                    class="w-full text-center text-2xl tracking-[0.5em] px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition font-mono"
                >
            </div>

            <button 
                type="submit" 
                class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
            >
                Verify & Continue
            </button>
        </form>

        <div class="flex items-center justify-between pt-2 text-xs">
            <form action="<?= url_to('auth.action.challenge') ?>" method="post" class="inline m-0 p-0">
                <?= csrf_field() ?>
                <button type="submit" class="text-blue-400 hover:text-blue-300 font-semibold cursor-pointer bg-transparent border-none p-0">
                    Resend Code
                </button>
            </form>

            <a href="<?= url_to('auth.action.cancel') ?>" class="text-slate-400 hover:text-slate-300 transition">
                Cancel
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
