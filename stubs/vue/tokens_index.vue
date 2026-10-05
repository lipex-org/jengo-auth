<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';

interface Token {
  id: number;
  name: string;
  abilities: string[];
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

const props = defineProps<{
  data?: { tokens?: Token[] };
  message?: string;
  error?: string;
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});

const error = computed(() => props.error || flash.value.error || pageErrors.value.error);
const message = computed(() => props.message || flash.value.message || flash.value.success);

const form = useForm({
  name: '',
  abilities: 'dashboard:view, profile:edit',
});

const newPlainTextToken = computed(() => {
  return page.props.token || page.props.flash?.token || null;
});

const createToken = () => {
  if (!form.name) return;

  const abilitiesArray = form.abilities.split(',').map((s: string) => s.trim()).filter(Boolean);
  form.transform((data) => ({
    name: data.name,
    abilities: abilitiesArray.length > 0 ? abilitiesArray : ['*'],
  })).post('/tokens/create', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('name');
    },
  });
};

const revokeToken = (tokenId: number) => {
  if (!confirm('Are you sure you want to revoke this API token? Any application using it will lose access.')) {
    return;
  }

  router.delete(`/tokens/${tokenId}`, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Personal Access Tokens" />

  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
      <div class="flex items-center justify-between border-b border-slate-800 pb-5">
        <div>
          <h1 class="text-2xl font-bold text-white">Personal Access Tokens</h1>
          <p class="text-xs text-slate-400 mt-1">Manage API tokens that allow external access to your account.</p>
        </div>
      </div>

      <div v-if="message" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
        {{ message }}
      </div>

      <div v-if="error" class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
        {{ error }}
      </div>

      <!-- Create Token Card -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-1">Generate API Token</h3>
        <p class="text-xs text-slate-400 mb-6">
          Personal access tokens function like password-less HTTP Bearer authentication for scripts and API clients.
        </p>

        <!-- New Token Alert -->
        <div v-if="newPlainTextToken" class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 space-y-2">
          <div class="font-bold text-xs flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Token Generated! Copy it now:
          </div>
          <div class="p-3 bg-slate-950 rounded-xl font-mono text-xs text-white select-all break-all border border-slate-800">
            {{ newPlainTextToken }}
          </div>
          <p class="text-[11px] text-slate-400">
            For security reasons, this token will never be shown again.
          </p>
        </div>

        <form @submit.prevent="createToken" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Token Name</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. CI/CD Runner or Mobile App"
                required
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-300 mb-1">Abilities / Scopes (comma separated)</label>
              <input
                v-model="form.abilities"
                type="text"
                placeholder="dashboard:view, profile:edit"
                class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="form.processing || !form.name"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl transition duration-150 disabled:opacity-50"
          >
            {{ form.processing ? 'Generating...' : 'Create API Token' }}
          </button>
        </form>
      </div>

      <!-- Active Tokens List -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-1">Active Tokens</h3>
        <p class="text-xs text-slate-400 mb-6">
          Tokens configured on your account. You can revoke access at any time.
        </p>

        <div v-if="!data?.tokens || data.tokens.length === 0" class="text-center py-8 text-slate-500 text-xs">
          No personal access tokens generated yet.
        </div>

        <div v-else class="divide-y divide-slate-800">
          <div
            v-for="token in data.tokens"
            :key="token.id"
            class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="font-bold text-white text-sm">{{ token.name }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono bg-slate-800 text-slate-400 border border-slate-700">ID: #{{ token.id }}</span>
              </div>
              <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span
                  v-for="ability in token.abilities"
                  :key="ability"
                  class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20"
                >
                  {{ ability }}
                </span>
                <span class="text-[11px] text-slate-400 ml-1">
                  Last used: {{ token.last_used_at || 'Never' }}
                </span>
              </div>
            </div>

            <button
              @click="revokeToken(token.id)"
              class="px-3 py-1.5 text-xs font-semibold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition self-start sm:self-center"
            >
              Revoke
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
