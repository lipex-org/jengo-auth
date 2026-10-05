<?php
$title = 'Two-Factor Verification';
$heading = 'Two-Factor Verification';
$subheading = 'Enter the 6-digit verification code sent to your registered contact.';
$layoutMode = 'auth-card';
$this->extend('Jengo\Auth\Views\layout');
?>

<?= $this->section('content') ?>
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
<?= $this->endSection() ?>
