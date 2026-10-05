<?php
/**
 * @var \Jengo\Auth\Entities\User|null $user
 * @var bool $has_password
 * @var array $identities
 * @var int $passkeys_count
 * @var array $available_providers
 */
$currentUser = $user ?? (auth()->check() ? auth()->user() : null);
$identitiesList = $identities ?? ($currentUser ? $currentUser->getIdentitiesSummary() : []);
$hasPassword = $has_password ?? ($currentUser ? $currentUser->hasPassword() : false);
$providers = $available_providers ?? (\Config\Services::social()->getAvailableProviders());
$title = 'Linked Accounts & Identities';
$layoutMode = 'dashboard';
$this->extend('Jengo\Auth\Views\layout');
?>

<?= $this->section('content') ?>
<div class="space-y-8">
    <!-- Top Header -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <div>
            <a href="<?= site_url('/') ?>" class="text-xs font-semibold text-blue-400 hover:text-blue-300 flex items-center gap-1 mb-1">
                &larr; Back to Home
            </a>
            <h1 class="text-2xl font-bold tracking-tight text-white">Linked Accounts & Identities</h1>
            <p class="text-xs text-slate-400 mt-0.5">Manage your connected social login providers, passwords, and sign-in identities.</p>
        </div>
    </div>

    <!-- Primary Password Identity Status -->
    <div class="p-6 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center <?= $hasPassword ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' ?>">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-base text-white">Password Authentication: <span class="<?= $hasPassword ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold' ?>"><?= $hasPassword ? 'Configured' : 'Not Set' ?></span></h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    <?= $hasPassword ? 'You can sign in using your email and password.' : 'No password is set. Set a password so you can sign in directly without third-party providers.' ?>
                </p>
            </div>
        </div>
        <div>
            <a href="<?= auth_url('auth.password.set.view') ?>" class="px-4 py-2 <?= $hasPassword ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700' : 'bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/20' ?> text-xs font-semibold rounded-xl transition">
                <?= $hasPassword ? 'Change Password' : 'Set Password' ?>
            </a>
        </div>
    </div>

    <!-- Linked Third-Party Identities List -->
    <div class="space-y-4">
        <h2 class="text-lg font-bold text-white">Connected Social Accounts</h2>

        <?php if (empty($identitiesList)): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center text-slate-400 text-sm">
                No third-party social accounts currently linked.
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($identitiesList as $identity): ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($identity['avatar'])): ?>
                                <img src="<?= esc($identity['avatar']) ?>" alt="Avatar" class="w-10 h-10 rounded-xl object-cover border border-slate-700">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center font-bold text-sm">
                                    <?= strtoupper(substr($identity['provider'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-white text-sm"><?= esc($identity['provider_name']) ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">CONNECTED</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    <?= esc($identity['email'] ?? $identity['name'] ?? ('ID: ' . $identity['provider_user_id'])) ?>
                                </p>
                            </div>
                        </div>

                        <form action="<?= auth_url('identities.unlink', $identity['id']) ?>" method="POST" onsubmit="return confirm('Are you sure you want to disconnect this account?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="identity_id" value="<?= esc($identity['id']) ?>">
                            <button type="submit" class="px-3.5 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40 transition cursor-pointer">
                                Disconnect
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Providers to Connect -->
    <?php if (!empty($providers)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-4">
            <div>
                <h3 class="font-bold text-base text-white">Link Other Accounts</h3>
                <p class="text-xs text-slate-400 mt-0.5">Connect additional third-party accounts to log in with a single click.</p>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <?php foreach ($providers as $p): ?>
                    <?php
                        $isLinked = false;
                        foreach ($identitiesList as $i) {
                            if ($i['provider'] === $p['id']) {
                                $isLinked = true;
                                break;
                            }
                        }
                    ?>
                    <?php if (!$isLinked): ?>
                        <a href="<?= esc($p['url']) ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white rounded-xl border border-slate-700 flex items-center gap-2 transition">
                            Link <?= esc($p['name']) ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
