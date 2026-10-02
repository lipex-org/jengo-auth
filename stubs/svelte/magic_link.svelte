<script lang="ts">
  import { useForm, inertia } from '@inertiajs/svelte';

  export let message: string = '';
  export let error: string = '';

  const form = useForm({
    email: '',
  });

  function submit() {
    $form.post('/magic-link');
  }
</script>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
  <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
    <h1 class="text-2xl font-bold text-white mb-2 text-center">Passwordless Login</h1>
    <p class="text-xs text-slate-400 mb-6 text-center">Enter your email and we'll send you an instant login link.</p>

    {#if message}
      <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {message}
      </div>
    {/if}

    {#if error}
      <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {error}
      </div>
    {/if}

    <form on:submit|preventDefault={submit} class="space-y-4">
      <div>
        <label for="email" class="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
        <input
          id="email"
          bind:value={$form.email}
          type="email"
          class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
          placeholder="alex@example.com"
          required
          autofocus
        />
        {#if $form.errors.email}
          <p class="text-xs text-red-400 mt-1">{$form.errors.email}</p>
        {/if}
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
      >
        {$form.processing ? 'Sending magic link...' : 'Send Magic Link'}
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-400">
      Prefer password?
      <a use:inertia href="/login" class="text-blue-400 hover:text-blue-300 font-semibold">Password login</a>
    </div>
  </div>
</div>
