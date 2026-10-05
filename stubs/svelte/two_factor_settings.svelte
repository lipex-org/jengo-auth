<script lang="ts">
  import { inertia, router, page } from '@inertiajs/svelte';

  export let data: {
    enrolled_factors?: any[];
    available_factors?: any[];
  } = {};
  export let message: string = '';
  export let error: string = '';
  export let user: any = null;

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.error;
  $: activeMessage = message || flash.message || flash.success;

  let totpModal = false;
  let totpData: any = null;
  let totpCode = '';
  let recoveryModal = false;
  let recoveryCodes: string[] = [];
  let isProcessing = false;

  $: isEnrolled = (id: string) => data?.enrolled_factors?.some((f: any) => f.id === id);

  $: if (flash?.enrollment_data) {
    const factor = flash?.enrollment_factor;
    if (factor === 'totp' && flash.enrollment_data.secret) {
      totpData = flash.enrollment_data;
      totpModal = true;
    } else if (factor === 'recovery_code' && flash.enrollment_data.codes) {
      recoveryCodes = flash.enrollment_data.codes;
      recoveryModal = true;
    }
  }

  function startTotp() {
    isProcessing = true;
    router.post(
      '/two-factor/enroll/start',
      { factor: 'totp' },
      {
        preserveScroll: true,
        onFinish: () => {
          isProcessing = false;
        },
      }
    );
  }

  function confirmTotp() {
    if (!totpCode) return;
    router.post(
      '/two-factor/enroll/confirm',
      { factor: 'totp', code: totpCode },
      {
        preserveScroll: true,
        onSuccess: () => {
          totpModal = false;
          totpCode = '';
        },
      }
    );
  }

  function startPasskey() {
    const name = prompt('Name for this passkey:', 'MacBook Touch ID / YubiKey');
    if (!name) return;

    isProcessing = true;
    router.post(
      '/two-factor/enroll/start',
      { factor: 'passkey', options: { name } },
      {
        preserveScroll: true,
        onSuccess: async () => {
          const enrollmentData = flash?.enrollment_data;
          if (!enrollmentData?.options) {
            isProcessing = false;
            return;
          }

          try {
            const options = enrollmentData.options;
            options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
            options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

            const cred = (await navigator.credentials.create({ publicKey: options })) as any;
            const proof = {
              id: cred.id,
              rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
              clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
              attestationObject: btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
            };

            router.post(
              '/two-factor/enroll/confirm',
              { factor: 'passkey', proof, metadata: { name } },
              {
                preserveScroll: true,
                onFinish: () => {
                  isProcessing = false;
                },
              }
            );
          } catch (err: any) {
            isProcessing = false;
            alert(err?.message || 'Passkey registration cancelled or failed');
          }
        },
        onError: () => {
          isProcessing = false;
        },
      }
    );
  }

  function startRecoveryCodes() {
    if (!confirm('Generating new recovery codes will invalidate prior codes. Continue?')) return;
    router.post(
      '/two-factor/enroll/start',
      { factor: 'recovery_code' },
      {
        preserveScroll: true,
      }
    );
  }

  function unenroll(factor: string) {
    if (!confirm(`Are you sure you want to remove ${factor.toUpperCase()}?`)) return;
    router.post(
      '/two-factor/unenroll',
      { factor },
      {
        preserveScroll: true,
      }
    );
  }
</script>

<div class="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
  <div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b border-slate-800 pb-5">
      <div>
        <a use:inertia href="/" class="text-sm font-semibold text-blue-400 hover:text-blue-300">&larr; Back to Home</a>
        <h1 class="text-2xl font-bold text-white mt-1">Two-Factor Authentication & Sudo</h1>
        <p class="text-sm text-slate-400">Configure Multi-Factor Authentication and hardware security keys.</p>
      </div>
    </div>

    {#if activeMessage}
      <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
        {activeMessage}
      </div>
    {/if}

    {#if activeError}
      <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
        {activeError}
      </div>
    {/if}

    <div class="space-y-4">
      <!-- TOTP -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-white">Authenticator App (TOTP)</h3>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border {isEnrolled('totp') ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700'}"
            >
              {isEnrolled('totp') ? 'ENROLLED' : 'NOT CONFIGURED'}
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-1">Use Google Authenticator or 1Password for 6-digit dynamic codes.</p>
        </div>
        <div>
          {#if isEnrolled('totp')}
            <button
              on:click={() => unenroll('totp')}
              class="px-4 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40"
            >
              Remove TOTP
            </button>
          {:else}
            <button
              on:click={startTotp}
              class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-xl"
            >
              Setup Authenticator
            </button>
          {/if}
        </div>
      </div>

      <!-- Passkeys -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h3 class="font-semibold text-white">Passkeys & Security Keys (WebAuthn)</h3>
          <p class="text-xs text-slate-400 mt-1">Log in with Touch ID, Face ID, Windows Hello, or YubiKeys.</p>
        </div>
        <button
          on:click={startPasskey}
          class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white rounded-xl"
        >
          Add Passkey
        </button>
      </div>

      <!-- Recovery Codes -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-white">Emergency Recovery Codes</h3>
            <span
              class="px-2 py-0.5 rounded-full text-[10px] font-bold border {isEnrolled('recovery_code') ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700'}"
            >
              {isEnrolled('recovery_code') ? 'ACTIVE' : 'NOT GENERATED'}
            </span>
          </div>
          <p class="text-xs text-slate-400 mt-1">Single-use backup codes for emergency account access.</p>
        </div>
        <button
          on:click={startRecoveryCodes}
          class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white rounded-xl"
        >
          {isEnrolled('recovery_code') ? 'Regenerate Codes' : 'Generate Codes'}
        </button>
      </div>
    </div>

    <!-- TOTP Modal -->
    {#if totpModal && totpData}
      <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
          <h3 class="text-lg font-bold text-white">Setup Authenticator App</h3>
          {#if totpData.qr_data_uri}
            <div class="flex justify-center p-2 bg-white rounded-xl w-48 h-48 mx-auto items-center">
              <img src={totpData.qr_data_uri} alt="TOTP QR Code" class="w-44 h-44 object-contain" />
            </div>
          {/if}
          <div class="text-center font-mono font-bold text-amber-400 text-sm">{totpData.secret}</div>
          <input
            bind:value={totpCode}
            type="text"
            placeholder="123456"
            maxlength="6"
            class="w-full text-center text-xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white"
          />
          <div class="flex gap-3">
            <button
              on:click={() => (totpModal = false)}
              class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl"
            >
              Cancel
            </button>
            <button
              on:click={confirmTotp}
              class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
            >
              Confirm & Activate
            </button>
          </div>
        </div>
      </div>
    {/if}

    <!-- Recovery Codes Modal -->
    {#if recoveryModal}
      <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
          <h3 class="text-lg font-bold text-white">Emergency Recovery Codes</h3>
          <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 grid grid-cols-2 gap-2 text-center font-mono text-sm text-amber-400">
            {#each recoveryCodes as c}
              <div>{c}</div>
            {/each}
          </div>
          <button
            on:click={() => {
              recoveryModal = false;
              router.reload();
            }}
            class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
          >
            I Have Saved These Codes
          </button>
        </div>
      </div>
    {/if}
  </div>
</div>
