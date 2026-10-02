<?php
/**
 * @var \Jengo\Auth\Entities\User|null $user
 */
$currentUser = $user ?? (auth()->check() ? auth()->user() : null);
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication & Sudo - Jengo</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 font-sans antialiased p-6 sm:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
        <!-- Top Navigation -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-5">
            <div>
                <a href="<?= site_url('dashboard') ?>" class="text-sm font-semibold text-blue-400 hover:text-blue-300 flex items-center gap-1 mb-1">
                    &larr; Back to Dashboard
                </a>
                <h1 class="text-2xl font-bold tracking-tight text-white">Security & Step-Up Auth (Sudo)</h1>
                <p class="text-sm text-slate-400">Configure Multi-Factor Authentication and test GitHub-style Sudo verification.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                    Logged in as <strong class="text-white"><?= esc($currentUser->username ?? 'User') ?></strong>
                </span>
                <form action="<?= auth_url('logout') ?>" method="POST" class="inline m-0 p-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-950/40 border border-red-800/40 px-3 py-1 rounded-lg">Logout</button>
                </form>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (session()->getFlashdata('message') || session()->getFlashdata('success')): ?>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
                <?= esc(session()->getFlashdata('message') ?? session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <!-- Sudo Mode Status Banner -->
        <?php $isSudo = sudo()->check(); ?>
        <div class="p-6 rounded-2xl border <?= $isSudo ? 'bg-emerald-950/20 border-emerald-800/40' : 'bg-slate-900/60 border-slate-800' ?> flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center <?= $isSudo ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-400' ?>">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-base text-white">Sudo Mode Status: <span class="<?= $isSudo ? 'text-emerald-400 font-bold' : 'text-slate-400' ?>"><?= $isSudo ? 'Active (Unlocked)' : 'Locked' ?></span></h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?php if ($isSudo): ?>
                            Active grace period expires in <?= sudo()->secondsRemaining() ?> seconds (at <?= date('H:i:s', sudo()->expiresAt()) ?>).
                        <?php else: ?>
                            Privileged operations require confirmation with your password, TOTP, or Passkey.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isSudo): ?>
                    <form action="<?= auth_url('auth.sudo.exit') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-xl border border-slate-700 transition">
                            Exit Sudo Mode
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= auth_url('auth.sudo') ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-xl shadow-lg shadow-blue-500/20 transition">
                        Enter Sudo Mode &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Testing Section: Protected Sudo Endpoints -->
        <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-6">
            <h2 class="text-lg font-bold text-white mb-2">Test Sudo Protected Endpoints</h2>
            <p class="text-xs text-slate-400 mb-4">Click below to test route actions protected with the <code class="text-blue-400 bg-slate-950 px-1.5 py-0.5 rounded">#[Sudo]</code> attribute or Sudo Filter.</p>
            <div class="flex flex-wrap gap-3">
                <a href="<?= site_url('sudo/test-settings') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 rounded-xl border border-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Test Protected Action (2-hour Grace)
                </a>
                <a href="<?= site_url('sudo/test-sensitive') ?>" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 rounded-xl border border-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Test Force Fresh Action (Always Prompts)
                </a>
            </div>
        </div>

        <!-- Factor Enrollment List -->
        <div class="space-y-4">
            <h2 class="text-lg font-bold text-white">Two-Factor Authentication Methods</h2>

            <!-- 1. TOTP Authenticator App -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-white">Authenticator App (TOTP)</h3>
                            <?php if ($currentUser && two_factor()->driver('totp')->isEnrolled($currentUser)): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">ENROLLED</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">NOT CONFIGURED</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Use Google Authenticator, 1Password, or Authy to generate dynamic 6-digit rolling codes.</p>
                    </div>
                </div>
                <div>
                    <?php if ($currentUser && two_factor()->driver('totp')->isEnrolled($currentUser)): ?>
                        <button onclick="unenrollFactor('totp')" class="px-4 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40 transition">
                            Remove TOTP
                        </button>
                    <?php else: ?>
                        <button onclick="startTotpEnrollment()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-xl shadow-lg shadow-blue-500/20 transition">
                            Setup Authenticator
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Passkeys / FIDO2 / WebAuthn -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-white">Passkeys & Security Keys (WebAuthn)</h3>
                            <?php $passkeysCount = $currentUser ? count($currentUser->passkeys()) : 0; ?>
                            <?php if ($passkeysCount > 0): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"><?= $passkeysCount ?> REGISTERED</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">NOT CONFIGURED</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Authenticate seamlessly with Touch ID, Face ID, Windows Hello, or hardware security keys (YubiKey).</p>
                    </div>
                </div>
                <div>
                    <button onclick="startPasskeyEnrollment()" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white rounded-xl shadow-lg shadow-purple-500/20 transition">
                        Add Passkey
                    </button>
                </div>
            </div>

            <!-- 3. Emergency Recovery Codes -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-white">Emergency Recovery Codes</h3>
                            <?php if ($currentUser && two_factor()->driver('recovery_code')->isEnrolled($currentUser)): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">ACTIVE</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">NOT GENERATED</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Single-use backup codes to access your account if you lose access to your primary 2FA devices.</p>
                    </div>
                </div>
                <div>
                    <button onclick="startRecoveryCodeEnrollment()" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white rounded-xl shadow-lg shadow-amber-500/20 transition">
                        <?= ($currentUser && two_factor()->driver('recovery_code')->isEnrolled($currentUser)) ? 'Regenerate Codes' : 'Generate Codes' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for TOTP Enrollment -->
    <div id="totp-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-lg font-bold text-white">Setup Authenticator App</h3>
            <p class="text-xs text-slate-400">Scan the QR code or enter the secret key below into your authenticator app.</p>
            
            <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 text-center space-y-3">
                <div id="totp-qr-container" class="flex justify-center p-2 bg-white rounded-xl w-48 h-48 mx-auto items-center">
                    <img id="totp-qr-image" src="" alt="TOTP QR Code" class="w-44 h-44 object-contain">
                </div>
                <div class="text-xs text-slate-500 uppercase tracking-widest font-bold">Secret Key</div>
                <div id="totp-secret-text" class="text-base font-mono font-bold text-amber-400 select-all"></div>
                <div class="text-[11px] text-slate-500 break-all" id="totp-uri-text"></div>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-medium text-slate-300">Enter 6-Digit Code to Confirm</label>
                <input type="text" id="totp-confirm-code" maxlength="6" placeholder="123456" class="w-full text-center text-xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal('totp-modal')" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl">Cancel</button>
                <button type="button" onclick="confirmTotpEnrollment()" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl">Confirm & Activate</button>
            </div>
        </div>
    </div>

    <!-- Modal for Recovery Codes Display -->
    <div id="recovery-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-lg font-bold text-white">Your Emergency Recovery Codes</h3>
            <p class="text-xs text-slate-400">Save these backup codes in a safe place. Each code can only be used once.</p>
            
            <div id="recovery-codes-list" class="bg-slate-950 p-4 rounded-xl border border-slate-800 grid grid-cols-2 gap-2 text-center font-mono text-sm text-amber-400 select-all">
            </div>

            <button type="button" onclick="closeModal('recovery-modal'); window.location.reload();" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl">
                I Have Saved These Codes
            </button>
        </div>
    </div>

    <script>
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        async function startTotpEnrollment() {
            try {
                const res = await fetch('<?= auth_url('two-factor.enroll.start') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'totp' })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('totp-secret-text').innerText = data.data.secret;
                    document.getElementById('totp-uri-text').innerText = data.data.qr_uri || data.data.otpauth_uri;
                    if (data.data.qr_data_uri) {
                        document.getElementById('totp-qr-image').src = data.data.qr_data_uri;
                    }
                    document.getElementById('totp-modal').classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to initialize TOTP.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        async function confirmTotpEnrollment() {
            const code = document.getElementById('totp-confirm-code').value.trim();
            if (!code) {
                alert('Please enter the 6-digit code.');
                return;
            }

            try {
                const res = await fetch('<?= auth_url('two-factor.enroll.confirm') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'totp', code: code })
                });

                window.location.reload();

                if (data.status === 'success') {
                    alert('Authenticator successfully enrolled!');
                    window.location.reload();
                } else {
                    alert(data.message || 'Invalid confirmation code.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        async function startPasskeyEnrollment() {
            const name = prompt('Enter a name for this Passkey (e.g. MacBook Touch ID, YubiKey):', 'My Security Key');
            if (!name) return;

            try {
                const res = await fetch('<?= auth_url('two-factor.enroll.start') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'passkey', options: { name: name } })
                });
                const data = await res.json();
                if (data.status !== 'success' || !data.data || !data.data.options) {
                    alert(data.message || 'Failed to start Passkey registration.');
                    return;
                }

                const options = data.data.options;
                options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
                options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));

                const credential = await navigator.credentials.create({ publicKey: options });
                const attestationProof = {
                    id: credential.id,
                    rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                    clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                    attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
                };

                const confirmRes = await fetch('<?= auth_url('two-factor.enroll.confirm') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'passkey', proof: attestationProof, metadata: { name: name } })
                });
                const confirmData = await confirmRes.json();
                if (confirmData.status === 'success') {
                    alert('Passkey successfully registered!');
                    window.location.reload();
                } else {
                    alert(confirmData.message || 'Passkey enrollment verification failed.');
                }
            } catch (err) {
                alert('Passkey error: ' + err.message);
            }
        }

        async function startRecoveryCodeEnrollment() {
            if (!confirm('Generating new recovery codes will invalidate any previous backup codes. Continue?')) {
                return;
            }

            try {
                const res = await fetch('<?= auth_url('two-factor.enroll.start') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: 'recovery_code' })
                });
                const data = await res.json();
                if (data.status === 'success' && data.data.codes) {
                    const list = document.getElementById('recovery-codes-list');
                    list.innerHTML = data.data.codes.map(c => `<div>${c}</div>`).join('');
                    document.getElementById('recovery-modal').classList.remove('hidden');
                } else {
                    alert(data.message || 'Failed to generate recovery codes.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }

        async function unenrollFactor(factor) {
            if (!confirm(`Are you sure you want to remove ${factor.toUpperCase()}?`)) return;

            try {
                const res = await fetch('<?= auth_url('two-factor.unenroll') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ factor: factor })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert('Method removed.');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to remove.');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        }
    </script>
</body>
</html>
