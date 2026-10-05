<script lang="ts">
  import { useForm, router, page } from '@inertiajs/svelte';

  export let data: {
    tokens?: Array<{
      id: number;
      name: string;
      abilities: string[];
      last_used_at: string | null;
      expires_at: string | null;
      created_at: string;
    }>;
  } = {};
  export let message: string = '';
  export let error: string = '';

  $: flash = $page?.props?.flash || {};
  $: pageErrors = $page?.props?.errors || {};
  $: activeError = error || flash.error || pageErrors.error;
  $: activeMessage = message || flash.message || flash.success;

  $: tokens = data?.tokens || [];
  $: newPlainTextToken = $page?.props?.token || flash.token || null;

  const form = useForm({
    name: '',
    abilities: 'dashboard:view, profile:edit',
  });

  function createToken() {
    if (!$form.name) return;

    const abilitiesArray = $form.abilities
      .split(',')
      .map((s: string) => s.trim())
      .filter(Boolean);

    $form.transform((data) => ({
      name: data.name,
      abilities: abilitiesArray.length > 0 ? abilitiesArray : ['*'],
    })).post('/tokens/create', {
      preserveScroll: true,
      onSuccess: () => {
        $form.reset('name');
      },
    });
  }

  function revokeToken(tokenId: number) {
    if (
      !confirm(
        'Are you sure you want to revoke this API token? Any application using it will lose access.'
      )
    ) {
      return;
    }

    router.delete(`/tokens/${tokenId}`, {
      preserveScroll: true,
    });
  }
</script>

<svelte:head>
  <title>Personal Access Tokens</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
  <div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between border-b border-slate-800 pb-5">
      <div>
        <h1 class="text-2xl font-bold text-white">Personal Access Tokens</h1>
        <p class="text-xs text-slate-400 mt-1">Manage API tokens that allow external access to your account.</p>
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

    <!-- Create Token Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
      <h3 class="text-lg font-bold text-white mb-1">Generate API Token</h3>
      <p class="text-xs text-slate-400 mb-6">
        Personal access tokens function like password-less HTTP Bearer authentication for scripts and API clients.
      </p>

      <!-- New Token Alert -->
      {#if newPlainTextToken}
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 space-y-2">
          <div class="font-bold text-xs flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Token Generated! Copy it now:
          </div>
          <div class="p-3 bg-slate-950 rounded-xl font-mono text-xs text-white select-all break-all border border-slate-800">
            {newPlainTextToken}
          </div>
          <p class="text-[11px] text-slate-400">
            For security reasons, this token will never be shown again.
          </p>
        </div>
      {/if}

      <form on:submit|preventDefault={createToken} class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="token-name" class="block text-xs font-semibold text-slate-300 mb-1">Token Name</label>
            <input
              id="token-name"
              bind:value={$form.name}
              type="text"
              placeholder="e.g. CI/CD Runner or Mobile App"
              required
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
            />
          </div>
          <div>
            <label for="token-abilities" class="block text-xs font-semibold text-slate-300 mb-1">Abilities / Scopes (comma separated)</label>
            <input
              id="token-abilities"
              bind:value={$form.abilities}
              type="text"
              placeholder="dashboard:view, profile:edit"
              class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={$form.processing || !$form.name}
          class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl transition duration-150 disabled:opacity-50"
        >
          {$form.processing ? 'Generating...' : 'Create API Token'}
        </button>
      </form>
    </div>

    <!-- Active Tokens List -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
      <h3 class="text-lg font-bold text-white mb-1">Active Tokens</h3>
      <p class="text-xs text-slate-400 mb-6">
        Tokens configured on your account. You can revoke access at any time.
      </p>

      {#if tokens.length === 0}
        <div class="text-center py-8 text-slate-500 text-xs">
          No personal access tokens generated yet.
        </div>
      {:else}
        <div class="divide-y divide-slate-800">
          {#each tokens as token (token.id)}
            <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-white text-sm">{token.name}</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-slate-800 text-slate-400 border border-slate-700">
                    ID: #{token.id}
                  </span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                  {#each token.abilities as ability}
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                      {ability}
                    </span>
                  {/each}
                  <span class="text-[11px] text-slate-400 ml-1">
                    Last used: {token.last_used_at || 'Never'}
                  </span>
                </div>
              </div>

              <button
                on:click={() => revokeToken(token.id)}
                class="px-3 py-1.5 text-xs font-semibold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition self-start sm:self-center"
              >
                Revoke
              </button>
            </div>
          {/each}
        </div>
      {/if}
    </div>
  </div>
</div>
