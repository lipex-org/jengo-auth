<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Access (Sudo Mode) - <?= esc(config('Auth')->branding['name'] ?? 'Jengo') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full flex items-center justify-center p-6 text-slate-100 font-sans antialiased">
    <div class="w-full max-w-md bg-slate-900/90 backdrop-blur border border-slate-800 rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 mb-3">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Confirm Access</h1>
            <p class="text-sm text-slate-400 mt-1">This is a protected area. Please verify your identity to enter Sudo mode.</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm text-center">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('warning')): ?>
            <div class="mb-4 p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm text-center">
                <?= esc(session()->getFlashdata('warning')) ?>
            </div>
        <?php endif; ?>

        <form action="<?= auth_url('auth.sudo.verify') ?>" method="POST" id="sudo-form" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="factor" id="selected-factor" value="password">

            <div id="factor-selector" class="flex rounded-xl bg-slate-950/60 p-1 border border-slate-800">
                <button type="button" onclick="selectFactor('password')" id="btn-password" class="flex-1 py-1.5 text-xs font-semibold rounded-lg bg-slate-800 text-white shadow">Password</button>
                <button type="button" onclick="selectFactor('totp')" id="btn-totp" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Authenticator</button>
                <button type="button" onclick="selectFactor('passkey')" id="btn-passkey" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Passkey</button>
                <button type="button" onclick="selectFactor('recovery_code')" id="btn-recovery_code" class="flex-1 py-1.5 text-xs font-semibold rounded-lg text-slate-400 hover:text-white">Recovery</button>
            </div>

            <!-- Password Form -->
            <div id="section-password" class="space-y-3">
                <label class="block text-xs font-medium text-slate-300">Account Password</label>
                <input type="password" name="proof" id="input-password" placeholder="Enter your current password" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- TOTP Form -->
            <div id="section-totp" class="space-y-3 hidden">
                <label class="block text-xs font-medium text-slate-300">6-Digit Authenticator Code</label>
                <input type="text" name="proof" id="input-totp" disabled placeholder="000 000" maxlength="7" class="w-full text-center tracking-widest text-lg px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Passkey Form -->
            <div id="section-passkey" class="space-y-3 hidden text-center py-3">
                <p class="text-xs text-slate-400">Authenticate with Touch ID, Face ID, or your hardware security key.</p>
                <button type="button" onclick="triggerPasskeyVerification()" class="w-full py-3 bg-blue-600 hover:bg-blue-500 font-semibold text-sm rounded-xl text-white flex items-center justify-center gap-2 shadow-lg shadow-blue-500/20">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 004.07 7.07M16 11a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Use Passkey / Biometrics
                </button>
            </div>

            <!-- Recovery Code Form -->
            <div id="section-recovery_code" class="space-y-3 hidden">
                <label class="block text-xs font-medium text-slate-300">Emergency Recovery Code</label>
                <input type="text" name="proof" id="input-recovery" disabled placeholder="xxxx-xxxx" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit" id="submit-btn" class="w-full py-3 bg-slate-100 hover:bg-white text-slate-950 font-semibold text-sm rounded-xl transition duration-150 shadow-md">
                Verify Identity
            </button>
        </form>
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

                const verifyRes = await fetch('<?= auth_url('auth.sudo.verify') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'passkey', proof: assertionProof })
                });
                const verifyData = await verifyRes.json();
                if (verifyData.status === 'success') {
                    window.location.href = verifyData.intended_url || '/';
                } else {
                    alert(verifyData.message || 'Passkey verification failed.');
                }
            } catch (err) {
                console.error(err);
                alert('Passkey error: ' + err.message);
            }
        }
    </script>
</body>
</html>
