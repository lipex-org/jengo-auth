<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';

interface Identity {
  id: number;
  type: string;
  provider: string;
  provider_name: string;
  provider_user_id: string;
  email?: string;
  name?: string;
  avatar?: string;
  last_used_at?: string;
  created_at?: string;
}

interface SocialProvider {
  id: string;
  name: string;
  url: string;
}

const props = defineProps<{
  user?: any;
  has_password?: boolean;
  identities?: Identity[];
  passkeys_count?: number;
  available_providers?: SocialProvider[];
  data?: {
    has_password?: boolean;
    identities?: Identity[];
    passkeys_count?: number;
    available_providers?: SocialProvider[];
  };
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageData = computed(() => page.props.data || {});

const currentUser = computed(() => props.user || page.props.auth?.user || page.props.user);
const hasPassword = computed(() => props.has_password ?? props.data?.has_password ?? pageData.value.has_password ?? false);
const identitiesList = computed<Identity[]>(() => props.identities ?? props.data?.identities ?? pageData.value.identities ?? []);
const providers = computed<SocialProvider[]>(() => props.available_providers ?? props.data?.available_providers ?? pageData.value.available_providers ?? []);

const unlinkedProviders = computed(() => {
  const linked = new Set(identitiesList.value.map((i) => i.provider));
  return providers.value.filter((p) => !linked.has(p.id));
});

const unlinkForm = useForm({
  identity_id: null as number | null,
});

const unlinkIdentity = (identityId: number) => {
  if (confirm('Are you sure you want to disconnect this account?')) {
    unlinkForm.identity_id = identityId;
    unlinkForm.post(`/identities/unlink/${identityId}`);
  }
};
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-800 pb-5">
        <div>
          <Link href="/" class="text-sm font-semibold text-blue-400 hover:text-blue-300 flex items-center gap-1 mb-1">
            &larr; Back to Home
          </Link>
          <h1 class="text-2xl font-bold tracking-tight text-white">Linked Accounts & Identities</h1>
          <p class="text-sm text-slate-400">Manage your connected social login providers, passwords, and sign-in identities.</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs font-medium px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
            Logged in as <strong class="text-white">{{ currentUser?.username || 'User' }}</strong>
          </span>
          <Link href="/logout" method="post" as="button" class="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-950/40 border border-red-800/40 px-3 py-1 rounded-lg">
            Logout
          </Link>
        </div>
      </div>

      <!-- Flash Messages -->
      <div v-if="flash.message || flash.success" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
        {{ flash.message || flash.success }}
      </div>

      <div v-if="flash.error" class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
        {{ flash.error }}
      </div>

      <!-- Primary Password Status -->
      <div class="p-6 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center"
            :class="hasPassword ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'"
          >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
          </div>
          <div>
            <h3 class="font-semibold text-base text-white">
              Password Authentication:
              <span :class="hasPassword ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold'">
                {{ hasPassword ? 'Configured' : 'Not Set' }}
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ hasPassword ? 'You can sign in using your email and password.' : 'No password is set. Set a password so you can sign in directly without third-party providers.' }}
            </p>
          </div>
        </div>
        <div>
          <Link
            href="/set-password"
            class="px-4 py-2 text-xs font-semibold rounded-xl transition"
            :class="hasPassword ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700' : 'bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/20'"
          >
            {{ hasPassword ? 'Change Password' : 'Set Password' }}
          </Link>
        </div>
      </div>

      <!-- Linked Social Identities -->
      <div class="space-y-4">
        <h2 class="text-lg font-bold text-white">Connected Social Accounts</h2>

        <div v-if="identitiesList.length === 0" class="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center text-slate-400 text-sm">
          No third-party social accounts currently linked.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="identity in identitiesList"
            :key="identity.id"
            class="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex items-center justify-between"
          >
            <div class="flex items-center gap-4">
              <img v-if="identity.avatar" :src="identity.avatar" alt="Avatar" class="w-10 h-10 rounded-xl object-cover border border-slate-700" />
              <div v-else class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center font-bold text-sm">
                {{ identity.provider.substring(0, 1).toUpperCase() }}
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-white text-sm">{{ identity.provider_name }}</h4>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">CONNECTED</span>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">
                  {{ identity.email || identity.name || `ID: ${identity.provider_user_id}` }}
                </p>
              </div>
            </div>

            <button
              type="button"
              @click="unlinkIdentity(identity.id)"
              class="px-3.5 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40 transition"
            >
              Disconnect
            </button>
          </div>
        </div>
      </div>

      <!-- Available Providers to Connect -->
      <div v-if="unlinkedProviders.length > 0" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-4">
        <h3 class="font-bold text-lg text-white">Link Other Accounts</h3>
        <p class="text-xs text-slate-400">Connect additional third-party accounts to log in with a single click.</p>

        <div class="flex flex-wrap gap-3 pt-2">
          <a
            v-for="provider in unlinkedProviders"
            :key="provider.id"
            :href="provider.url"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white rounded-xl border border-slate-700 flex items-center gap-2 transition"
          >
            Link {{ provider.name }}
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
