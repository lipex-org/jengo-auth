<script lang="ts">
  import { useForm, inertia, page } from '@inertiajs/svelte';

  export let error: string = '';
  export let message: string = '';
  export let social_providers: Array<{ id: string; name: string; url: string }> = [];
  export let data: any = {};

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: pageData = $page?.props?.data || {};

  $: activeSocialProviders = (social_providers && social_providers.length > 0) ? social_providers : (data?.social_providers || pageData?.social_providers || []);
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

    {#if activeSocialProviders && activeSocialProviders.length > 0}
      <div class="mt-6">
        <div class="relative flex py-2 items-center">
          <div class="flex-grow border-t border-slate-800"></div>
          <span class="flex-shrink mx-4 text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Or register with</span>
          <div class="flex-grow border-t border-slate-800"></div>
        </div>

        <div class="grid grid-cols-1 gap-2 mt-2">
          {#each activeSocialProviders as provider}
            <a
              href={provider.url}
              class="w-full py-2.5 px-4 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-xl text-xs font-semibold text-white flex items-center justify-center gap-2 transition duration-150"
            >
              Register with {provider.name}
            </a>
          {/each}
        </div>
      </div>
    {/if}

    <div class="mt-6 text-center text-xs text-slate-400">
      Already have an account?
      <a use:inertia href="/login" class="text-blue-400 hover:text-blue-300 font-semibold">Log in</a>
    </div>
  </div>
</div>
