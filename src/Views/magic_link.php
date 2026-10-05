<?php
$title = 'Magic Link Login';
$heading = 'Passwordless Sign In';
$subheading = 'Enter your email address to receive a secure, one-click sign-in link.';
$layoutMode = 'auth-card';
$this->extend('Jengo\Auth\Views\layout');
?>

<?= $this->section('content') ?>
<form action="<?= url_to('magic-link.send') ?>" method="post" class="space-y-4">
    <?= csrf_field() ?>

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
            autofocus
            placeholder="you@example.com"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
        >
    </div>

    <button 
        type="submit" 
        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
    >
        Send Magic Link
    </button>
</form>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<a href="<?= url_to('login') ?>" class="font-semibold text-blue-400 hover:text-blue-300 transition">Back to password sign in</a>
<?= $this->endSection() ?>
