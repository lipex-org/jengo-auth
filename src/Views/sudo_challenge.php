<?php
$title = 'Confirm Access (Sudo Mode)';
$heading = 'Confirm Access';
$subheading = 'This is a protected area. Please verify your identity to enter Sudo mode.';
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
        <form action="<?= auth_url('auth.sudo.verify') ?>" method="POST" id="sudo-form" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="factor" id="selected-factor" value="password">
            <?php if ($redirectUrl = (request()->getGet('redirect') ?? session()->get(\Jengo\Auth\Sudo\SudoManager::SESSION_INTENDED))): ?>
                <input type="hidden" name="redirect" id="redirect-input" value="<?= esc($redirectUrl) ?>">
            <?php endif; ?>

            <div id="factor-selector" class="flex rounded-xl bg-slate-950/80 p-1 border border-slate-800">
                <button type="button" onclick="selectFactor('password')" id="btn-password" class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 text-white shadow">Password</button>
                <button type="button" onclick="selectFactor('totp')" id="btn-totp" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Authenticator</button>
                <button type="button" onclick="selectFactor('passkey')" id="btn-passkey" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Passkey</button>
                <button type="button" onclick="selectFactor('recovery_code')" id="btn-recovery_code" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Recovery</button>
            </div>

            <!-- Password Form -->
            <div id="section-password" class="space-y-2">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Account Password</label>
                <input 
                    type="password" 
                    name="proof" 
                    id="input-password" 
                    placeholder="Enter your current password" 
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm"
                >
            </div>

            <!-- TOTP Form -->
            <div id="section-totp" class="space-y-2 hidden">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider text-center">6-Digit Authenticator Code</label>
                <input 
                    type="text" 
                    name="proof" 
                    id="input-totp" 
                    disabled 
                    placeholder="000 000" 
                    maxlength="7" 
                    class="w-full text-center tracking-widest text-lg px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition font-mono"
                >
            </div>

            <!-- Passkey Form -->
            <div id="section-passkey" class="space-y-3 hidden text-center py-2">
                <p class="text-xs text-slate-400">Authenticate with Touch ID, Face ID, or your hardware security key.</p>
                <button 
                    type="button" 
                    onclick="triggerPasskeyVerification()" 
                    class="w-full py-3 bg-blue-600 hover:bg-blue-500 font-semibold text-sm rounded-xl text-white flex items-center justify-center gap-2 shadow-lg shadow-blue-500/20 transition cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004.07 7.07M16 11a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Use Passkey / Biometrics
                </button>
            </div>

            <!-- Recovery Code Form -->
            <div id="section-recovery_code" class="space-y-2 hidden">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Emergency Recovery Code</label>
                <input 
                    type="text" 
                    name="proof" 
                    id="input-recovery" 
                    disabled 
                    placeholder="xxxx-xxxx" 
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-600 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition text-sm font-mono"
                >
            </div>

            <button 
                type="submit" 
                id="submit-btn" 
                class="w-full py-2.5 px-4 bg-slate-100 hover:bg-white text-slate-950 font-semibold text-sm rounded-xl transition duration-150 shadow-md cursor-pointer"
            >
                Verify Identity
            </button>
        </form>
    </div>
</div>

<script>
    function selectFactor(factor) {
        document.getElementById('selected-factor').value = factor;
        ['password', 'totp', 'passkey', 'recovery_code'].forEach(f => {
            const section = document.getElementById('section-' + f);
            const btn = document.getElementById('btn-' + f);
            const input = section ? section.querySelector('input[name="proof"]') : null;
            if (section && btn) {
                if (f === factor) {
                    section.classList.remove('hidden');
                    btn.className = "flex-1 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 text-white shadow";
                    if (input) input.removeAttribute('disabled');
                } else {
                    section.classList.add('hidden');
                    btn.className = "flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white";
                    if (input) input.setAttribute('disabled', 'disabled');
                }
            }
        });
        document.getElementById('submit-btn').style.display = (factor === 'passkey') ? 'none' : 'block';
    }

    async function triggerPasskeyVerification() {
        try {
            const challengeRes = await fetch('<?= auth_url('auth.sudo.challenge') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ factor: 'passkey' })
            });
            const challengeData = await challengeRes.json();
            if (challengeData.status !== 'success' || !challengeData.challenge || !challengeData.challenge.options) {
                alert(challengeData.message || 'Passkey challenge failed.');
                return;
            }

            const options = challengeData.challenge.options;
            options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(c => ({
                    ...c,
                    id: Uint8Array.from(atob(c.id.replace(/-/g, '+').replace(/_/g, '/')), ch => ch.charCodeAt(0))
                }));
            }

            const credential = await navigator.credentials.get({ publicKey: options });
            const assertionProof = {
                id: credential.id,
                rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                authenticatorData: btoa(String.fromCharCode(...new Uint8Array(credential.response.authenticatorData))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                signature: btoa(String.fromCharCode(...new Uint8Array(credential.response.signature))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
            };

            const redirectInput = document.getElementById('redirect-input');
            const verifyRes = await fetch('<?= auth_url('auth.sudo.verify') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ 
                    factor: 'passkey', 
                    proof: assertionProof,
                    redirect: redirectInput ? redirectInput.value : undefined
                })
            });
            const verifyData = await verifyRes.json();
            if (verifyData.status === 'success') {
                window.location.href = verifyData.intended_url || (redirectInput ? redirectInput.value : '/');
            } else {
                alert(verifyData.message || 'Passkey verification failed.');
            }
        } catch (err) {
            console.error(err);
            alert('Passkey error: ' + err.message);
        }
    }
</script>
<?= $this->endSection() ?>
