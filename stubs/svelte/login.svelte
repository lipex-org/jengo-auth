<script lang="ts">
  import { useForm, inertia, page } from '@inertiajs/svelte';

  export let error: string = '';
  export let message: string = '';
  export let allow_magic_link: boolean = false;
  export let can_reset_password: boolean = true;
  export let social_providers: Array<{ id: string; name: string; url: string }> = [];
  export let data: any = {};

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: pageData = $page?.props?.data || {};

  $: activeAllowMagicLink = allow_magic_link || data?.allow_magic_link || pageData?.allow_magic_link || false;
  $: activeCanResetPassword = can_reset_password ?? data?.can_reset_password ?? pageData?.can_reset_password ?? true;
  $: activeSocialProviders = (social_providers && social_providers.length > 0) ? social_providers : (data?.social_providers || pageData?.social_providers || []);

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
        <div class="flex items-center gap-3">
          {#if activeAllowMagicLink}
            <a use:inertia href="/magic-link" class="text-blue-400 hover:text-blue-300">Magic link</a>
          {/if}
          {#if activeCanResetPassword}
            <a use:inertia href="/forgot-password" class="text-blue-400 hover:text-blue-300">Forgot password?</a>
          {/if}
        </div>
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {$form.processing ? 'Signing in...' : 'Sign In'}
      </button>
    </form>

    {#if activeSocialProviders && activeSocialProviders.length > 0}
      <div class="mt-6">
        <div class="relative flex py-2 items-center">
          <div class="flex-grow border-t border-slate-800"></div>
          <span class="flex-shrink mx-4 text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Or continue with</span>
          <div class="flex-grow border-t border-slate-800"></div>
        </div>

        <div class="grid grid-cols-1 gap-2 mt-2">
          {#each activeSocialProviders as provider}
            <a
              href={provider.url}
              class="w-full py-2.5 px-4 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-xl text-xs font-semibold text-white flex items-center justify-center gap-2 transition duration-150"
            >
              Sign in with {provider.name}
            </a>
          {/each}
        </div>
      </div>
    {/if}

    <div class="mt-6 text-center text-xs text-slate-400">
      Don't have an account?
      <a use:inertia href="/register" class="text-blue-400 hover:text-blue-300 font-semibold">Sign up</a>
    </div>
  </div>
</div>
