<script lang="ts">
  import { useForm, inertia, page } from '@inertiajs/svelte';

  export let error: string = '';
  export let message: string = '';

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.credentials || pageErrors.error;
  $: activeMessage = message || flash.message || flash.success;

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  });

  function submit() {
    $form.post('/login');
  }
</script>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <h1 class="text-2xl font-bold text-white mb-2 text-center">Log In</h1>
    <p class="text-xs text-slate-400 mb-6 text-center">Welcome back! Please enter your details.</p>

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
        <label for="email" class="block text-xs font-medium text-slate-300 mb-1">Email or Username</label>
        <input
          id="email"
          bind:value={$form.email}
          type="text"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="alex@example.com"
          required
          autofocus
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

      <div class="flex items-center justify-between text-xs">
        <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
          <input
            bind:checked={$form.remember}
            type="checkbox"
            class="rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-blue-500"
          />
          Remember me
        </label>
        <a use:inertia href="/forgot-password" class="text-blue-400 hover:text-blue-300">Forgot password?</a>
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {$form.processing ? 'Signing in...' : 'Sign In'}
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-400">
      Don't have an account?
      <a use:inertia href="/register" class="text-blue-400 hover:text-blue-300 font-semibold">Sign up</a>
    </div>
  </div>
</div>
