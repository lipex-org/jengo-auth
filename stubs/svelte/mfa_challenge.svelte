<script lang="ts">
  import { useForm, page, router } from '@inertiajs/svelte';

  export let error: string = '';
  export let message: string = '';

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.error;
  $: activeMessage = message || flash.message || flash.success;

  const form = useForm({
    code: '',
  });

  function submit() {
    $form.post('/auth/action/handle');
  }

  function resend() {
    router.post('/auth/action/challenge', {}, {
      preserveScroll: true,
      preserveState: true,
    });
  }

  function cancel() {
    router.post('/auth/action/cancel');
  }
</script>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <div class="text-center mb-6">
      <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 inline-flex items-center justify-center mb-3">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white">Two-Factor Authentication</h1>
      <p class="text-xs text-slate-400 mt-1">Enter the 6-digit verification code from your authenticator app.</p>
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

    <form on:submit|preventDefault={submit} class="space-y-4">
      <div>
        <label for="code" class="block text-xs font-medium text-slate-300 mb-1 text-center">Verification Code</label>
        <input
          id="code"
          bind:value={$form.code}
          type="text"
          class="w-full text-center text-2xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="000 000"
          maxLength={7}
          required
          autofocus
        />
        {#if $form.errors.code}
          <p class="text-xs text-red-400 mt-1 text-center">{$form.errors.code}</p>
        {/if}
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {$form.processing ? 'Verifying...' : 'Verify Code'}
      </button>
    </form>

    <div class="mt-6 flex items-center justify-between text-xs">
      <button
        type="button"
        on:click={resend}
        class="text-blue-400 hover:text-blue-300 font-medium transition"
      >
        Resend Code
      </button>

      <button
        type="button"
        on:click={cancel}
        class="text-slate-400 hover:text-slate-300 transition"
      >
        Cancel
      </button>
    </div>
  </div>
</div>
