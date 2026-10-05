<?php
/**
 * @var \Jengo\Auth\Entities\User|null $user
 * @var array<\Jengo\Auth\Entities\UserToken> $tokens
 * @var string|null $newPlainTextToken
 */
$currentUser = $user ?? (auth()->check() ? auth()->user() : null);
$tokenList = $tokens ?? ($currentUser ? auth()->getUserTokenModel()->where('user_id', $currentUser->id)->findAll() : []);
$title = 'Personal Access Tokens';
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
            <h1 class="text-2xl font-bold tracking-tight text-white">Personal Access Tokens</h1>
            <p class="text-xs text-slate-400 mt-0.5">Generate cryptographic Bearer tokens with granular abilities for external tools and cURL scripts.</p>
        </div>
    </div>

    <?php if (session()->getFlashdata('token')): ?>
        <div class="p-5 rounded-2xl bg-emerald-950/20 border border-emerald-800/40 text-emerald-400 space-y-2">
            <div class="font-bold text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                New Personal Access Token Generated!
            </div>
            <div class="p-3 bg-slate-950 rounded-xl font-mono text-xs text-white select-all break-all border border-slate-800">
                <?= esc(session()->getFlashdata('token')) ?>
            </div>
            <p class="text-xs text-slate-400">
                Be sure to copy your new token now. You will not be able to see it again!
            </p>
        </div>
    <?php endif; ?>

    <!-- Create Token Form -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        <div>
            <h3 class="font-bold text-base text-white">Generate New Token</h3>
            <p class="text-xs text-slate-400 mt-1">Tokens you generate will inherit your user permissions subject to assigned scopes.</p>
        </div>

        <form action="<?= auth_url('tokens.create') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Token Name</label>
                    <input
                        name="name"
                        type="text"
                        placeholder="e.g. GitHub Action or CLI Worker"
                        required
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Abilities / Scopes (comma separated)</label>
                    <input
                        name="abilities"
                        type="text"
                        placeholder="dashboard:view, profile:edit"
                        value="dashboard:view, profile:edit"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                    />
                </div>
            </div>

            <button
                type="submit"
                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-blue-500/20 transition duration-150 cursor-pointer"
            >
                Create Personal Access Token
            </button>
        </form>
    </div>

    <!-- Active Tokens List -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        <div>
            <h3 class="font-bold text-base text-white">Active Tokens</h3>
            <p class="text-xs text-slate-400 mt-1">These tokens are currently valid for authenticating requests.</p>
        </div>

        <?php if (empty($tokenList)): ?>
            <div class="text-center py-10 text-slate-500 text-sm">
                No personal access tokens created yet.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-800">
                <?php foreach ($tokenList as $token): ?>
                    <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-white text-sm"><?= esc($token->name) ?></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-slate-800 text-slate-400 border border-slate-700">#<?= esc($token->id) ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                <?php foreach ((array) $token->abilities as $ability): ?>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        <?= esc($ability) ?>
                                    </span>
                                <?php endforeach; ?>
                                <span class="text-xs text-slate-500 ml-2">
                                    Last used: <?= esc($token->last_used_at ?? 'Never') ?>
                                </span>
                            </div>
                        </div>

                        <form action="<?= auth_url('tokens.revoke', $token->id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to revoke this token?');">
                            <?= csrf_field() ?>
                            <button
                                type="submit"
                                class="px-3 py-1.5 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40 transition cursor-pointer"
                            >
                                Revoke
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
