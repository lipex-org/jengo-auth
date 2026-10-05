<?php
$title = 'Set Password';
$heading = 'Create Password';
$subheading = 'Set a password for your account to enable email and password sign-in.';
$layoutMode = 'auth-card';
$this->extend('Jengo\Auth\Views\layout');
?>

<?= $this->section('content') ?>
<form action="<?= auth_url('auth.password.set') ?>" method="post" class="space-y-4">
    <?= csrf_field() ?>

    <div>
        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
            New Password
        </label>
        <input 
            type="password" 
            id="password" 
            name="password" 
            required 
            autocomplete="new-password"
            placeholder="••••••••"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
        >
        <?php if (!empty($errors['password'])): ?>
            <p class="text-xs text-red-400 mt-1"><?= esc($errors['password']) ?></p>
        <?php endif; ?>
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
            autocomplete="new-password"
            placeholder="••••••••"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
        >
        <?php if (!empty($errors['password_confirm'])): ?>
            <p class="text-xs text-red-400 mt-1"><?= esc($errors['password_confirm']) ?></p>
        <?php endif; ?>
    </div>

    <button 
        type="submit" 
        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
    >
        Save Password
    </button>
</form>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<a href="<?= site_url('/') ?>" class="font-semibold text-blue-400 hover:text-blue-300 transition">&larr; Back to Dashboard</a>
<?= $this->endSection() ?>
