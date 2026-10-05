<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, router, Head } from '@inertiajs/vue3';

const props = defineProps<{
  error?: string;
  message?: string;
  version?: string;
  title?: string;
  user?: any;
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});

const error = computed(() => props.error || flash.value.error || pageErrors.value.error);
const message = computed(() => props.message || flash.value.message || flash.value.success);

const form = useForm({
  accept_terms: '1',
});

const submit = () => {
  form.post('/auth/action/handle');
};

const cancel = () => {
  router.post('/auth/action/cancel');
};
</script>

<template>
  <Head title="Terms of Service Acceptance" />

  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
    <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
      <div class="flex items-center justify-between mb-4">
        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
          Version {{ version || '2026.1' }}
        </span>
        <span class="text-xs text-slate-400">Post-Auth Action</span>
      </div>

      <h1 class="text-2xl font-bold text-white mb-2">{{ title || 'Terms of Service Update' }}</h1>
      <p class="text-xs text-slate-400 mb-6">
        Hello <span class="text-slate-200 font-semibold">{{ user?.username || page.props.auth?.user?.username || 'there' }}</span>, please review and accept our updated policy to complete your sign-in.
      </p>

      <div v-if="message" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {{ message }}
      </div>

      <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {{ error }}
      </div>

      <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 text-xs text-slate-300 leading-relaxed max-h-48 overflow-y-auto mb-6 space-y-3 scrollbar-thin">
        <p class="font-medium text-white">1. Introduction & Acceptance of Terms</p>
        <p>
          By accessing and using this Jengo application, you agree to comply with and be bound by all terms, policies, and conditions outlined in this agreement. If you do not accept these terms, you will be signed out immediately.
        </p>
        <p class="font-medium text-white">2. Multi-Factor & Cryptographic Token Security</p>
        <p>
          You agree to maintain the security of your Passkeys, TOTP secrets, and Personal Access Tokens. All cryptographic operations and session step-ups are protected by hardware keys and strict rate-limiting policies.
        </p>
        <p class="font-medium text-white">3. Sudo Privileges & Elevated Zones</p>
        <p>
          Privileged operations (such as token rotation and security audit inspection) require step-up re-authentication within the configured grace period.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-3">
        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50 flex items-center justify-center gap-2"
        >
          <svg v-if="!form.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ form.processing ? 'Submitting...' : 'I Accept the Terms of Service' }}
        </button>

        <button
          type="button"
          @click="cancel"
          class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-sm rounded-xl transition duration-150"
        >
          Decline & Sign Out
        </button>
      </form>
    </div>
  </div>
</template>
