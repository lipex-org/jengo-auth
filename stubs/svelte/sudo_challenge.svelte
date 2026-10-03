<script lang="ts">
  import { useForm, page } from '@inertiajs/svelte';

  export let available_factors: any[] = [];
  export let message: string = '';
  export let error: string = '';

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.error || pageErrors.credentials || pageErrors.factor;
  $: activeMessage = message || flash.message || flash.success;

  let activeTab: 'password' | 'totp' | 'passkey' | 'recovery_code' = 'password';

  const form = useForm({
    factor: 'password',
    proof: '',
  });

  function selectTab(tab: 'password' | 'totp' | 'passkey' | 'recovery_code') {
    activeTab = tab;
    $form.factor = tab;
    $form.proof = '';
  }

  function submit() {
    $form.post('/auth/sudo/verify');
  }

  async function handlePasskeyAuth() {
    try {
      const res = await fetch('/auth/sudo/challenge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ factor: 'passkey' }),
      });
      const challenge = await res.json();
      if (challenge.status !== 'success' || !challenge.data?.challenge?.options) {
        alert(challenge.message || 'Passkey challenge failed.');
        return;
      }

      const options = challenge.data.challenge.options;
      options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
      if (options.allowCredentials) {
        options.allowCredentials = options.allowCredentials.map((c: any) => ({
          ...c,
          id: Uint8Array.from(atob(c.id.replace(/-/g, '+').replace(/_/g, '/')), (ch) => ch.charCodeAt(0)),
        }));
      }

      const cred = (await navigator.credentials.get({ publicKey: options })) as any;
      const proof = {
        id: cred.id,
        rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        authenticatorData: btoa(String.fromCharCode(...new Uint8Array(cred.response.authenticatorData))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        signature: btoa(String.fromCharCode(...new Uint8Array(cred.response.signature))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
      };

      const verifyRes = await fetch('/auth/sudo/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ factor: 'passkey', proof }),
      });
      const verifyData = await verifyRes.json();
      if (verifyData.status === 'success') {
        window.location.href = verifyData.data?.intended_url || '/';
      } else {
        alert(verifyData.message || 'Passkey verification failed.');
      }
    } catch (err: any) {
      alert('Passkey error: ' + err.message);
    }
  }
</script>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <div class="text-center mb-6">
      <div class="w-14 h-14 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 inline-flex items-center justify-center mb-3">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white">Confirm Access</h1>
      <p class="text-xs text-slate-400 mt-1">This is a protected area. Please verify your identity to enter Sudo mode.</p>
    </div>

    {#if activeMessage}
      <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {activeMessage}
      </div>
    {/if}

    {#if activeError}
      <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {activeError}
      </div>
    {/if}

    <div class="flex rounded-xl bg-slate-950/60 p-1 border border-slate-800 mb-4">
      <button
        type="button"
        on:click={() => selectTab('password')}
        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition {activeTab === 'password' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'}"
      >
        Password
      </button>
      <button
        type="button"
        on:click={() => selectTab('totp')}
        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition {activeTab === 'totp' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'}"
      >
        Authenticator
      </button>
      <button
        type="button"
        on:click={() => selectTab('passkey')}
        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition {activeTab === 'passkey' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'}"
      >
        Passkey
      </button>
      <button
        type="button"
        on:click={() => selectTab('recovery_code')}
        class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition {activeTab === 'recovery_code' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'}"
      >
        Recovery
      </button>
    </div>

    {#if activeTab === 'passkey'}
      <div class="space-y-4 text-center py-4">
        <p class="text-xs text-slate-400">Authenticate with Touch ID, Face ID, or your hardware security key.</p>
        <button
          type="button"
          on:click={handlePasskeyAuth}
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150"
        >
          Use Passkey / Biometrics
        </button>
      </div>
    {:else}
      <form on:submit|preventDefault={submit} class="space-y-4">
        <div>
          <label for="proof" class="block text-xs font-medium text-slate-300 mb-1">
            {activeTab === 'password' ? 'Account Password' : activeTab === 'totp' ? '6-Digit Authenticator Code' : 'Emergency Recovery Code'}
          </label>
          <input
            id="proof"
            bind:value={$form.proof}
            type={activeTab === 'password' ? 'password' : 'text'}
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none {activeTab === 'totp' ? 'text-center text-xl tracking-widest' : ''}"
            placeholder={activeTab === 'password' ? 'Enter your password' : activeTab === 'totp' ? '000 000' : 'xxxx-xxxx'}
            required
            autofocus
          />
          {#if $form.errors.proof}
            <p class="text-xs text-red-400 mt-1">{$form.errors.proof}</p>
          {/if}
        </div>

        <button
          type="submit"
          disabled={$form.processing}
          class="w-full py-3 bg-white hover:bg-slate-100 text-slate-950 font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
        >
          {$form.processing ? 'Verifying...' : 'Verify Identity'}
        </button>
      </form>
    {/if}
  </div>
</div>
