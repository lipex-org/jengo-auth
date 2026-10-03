<script lang="ts">
  import { useForm, inertia, page } from '@inertiajs/svelte';

  export let error: string = '';
  export let message: string = '';

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.error;
  $: activeMessage = message || flash.message || flash.success;

  const form = useForm({
    username: '',
    email: '',
    password: '',
    password_confirm: '',
  });

  function submit() {
    $form.post('/register');
  }
</script>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <h1 class="text-2xl font-bold text-white mb-2 text-center">Create Account</h1>
    <p class="text-xs text-slate-400 mb-6 text-center">Sign up to get started with your new account.</p>

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
        <label for="username" class="block text-xs font-medium text-slate-300 mb-1">Username</label>
        <input
          id="username"
          bind:value={$form.username}
          type="text"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="alexdev"
          required
          autofocus
        />
        {#if $form.errors.username}
          <p class="text-xs text-red-400 mt-1">{$form.errors.username}</p>
        {/if}
      </div>

      <div>
        <label for="email" class="block text-xs font-medium text-slate-300 mb-1">Email</label>
        <input
          id="email"
          bind:value={$form.email}
          type="email"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="alex@example.com"
          required
        />
        {#if $form.errors.email}
          <p class="text-xs text-red-400 mt-1">{$form.errors.email}</p>
        {/if}
      </div>

      <div>
        <label for="password" class="block text-xs font-medium text-slate-300 mb-1">Password</label>
        <input
          id="password"
          bind:value={$form.password}
          type="password"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="••••••••"
          required
        />
        {#if $form.errors.password}
          <p class="text-xs text-red-400 mt-1">{$form.errors.password}</p>
        {/if}
      </div>

      <div>
        <label for="password_confirm" class="block text-xs font-medium text-slate-300 mb-1">Confirm Password</label>
        <input
          id="password_confirm"
          bind:value={$form.password_confirm}
          type="password"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="••••••••"
          required
        />
        {#if $form.errors.password_confirm}
          <p class="text-xs text-red-400 mt-1">{$form.errors.password_confirm}</p>
        {/if}
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {$form.processing ? 'Creating account...' : 'Create Account'}
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-400">
      Already have an account?
      <a use:inertia href="/login" class="text-blue-400 hover:text-blue-300 font-semibold">Log in</a>
    </div>
  </div>
</div>
