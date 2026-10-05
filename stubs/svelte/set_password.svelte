<script lang="ts">
  import { page, inertia } from '@inertiajs/svelte';

  export let error: string | undefined = undefined;
  export let message: string | undefined = undefined;
  export let errors: Record<string, string> | undefined = undefined;

  let password = '';
  let password_confirm = '';
  let processing = false;

  $: flash = ($page.props as any).flash || {};
  $: pageErrors = ($page.props as any).errors || {};
  $: errorMessage = error || flash.error || pageErrors.error;
  $: successMessage = message || flash.message || flash.success;
  $: fieldErrors = errors || pageErrors || {};

  function handleSubmit() {
    processing = true;
    (inertia as any).post('/set-password', {
      password,
      password_confirm,
    }, {
      onFinish: () => {
        processing = false;
        password = '';
        password_confirm = '';
      },
    });
  }
</script>

<svelte:head>
  <title>Create Password</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-bold text-white mb-2">Create Password</h1>
      <p class="text-xs text-slate-400">
        Set a password for your account to enable traditional email & password sign-in.
      </p>
    </div>

    {#if successMessage}
      <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {successMessage}
      </div>
    {/if}

    {#if errorMessage}
      <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {errorMessage}
      </div>
    {/if}

    <form on:submit|preventDefault={handleSubmit} class="space-y-4">
      <div>
        <label for="password" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">New Password</label>
        <input
          id="password"
          bind:value={password}
          type="password"
          required
          autocomplete="new-password"
          placeholder="••••••••"
          class="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
        />
        {#if fieldErrors.password}
          <p class="text-red-400 text-xs mt-1">{fieldErrors.password}</p>
        {/if}
      </div>

      <div>
        <label for="password_confirm" class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">Confirm Password</label>
        <input
          id="password_confirm"
          bind:value={password_confirm}
          type="password"
          required
          autocomplete="new-password"
          placeholder="••••••••"
          class="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
        />
        {#if fieldErrors.password_confirm}
          <p class="text-red-400 text-xs mt-1">{fieldErrors.password_confirm}</p>
        {/if}
      </div>

      <button
        type="submit"
        disabled={processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {processing ? 'Saving...' : 'Set Password'}
      </button>
    </form>
  </div>
</div>
