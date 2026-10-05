<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, Head } from '@inertiajs/vue3';

const props = defineProps<{
  error?: string;
  message?: string;
  errors?: Record<string, string>;
  branding?: {
    name?: string;
    logo?: string;
  };
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});

const errorMessage = computed(() => props.error || flash.value.error || pageErrors.value.error);
const successMessage = computed(() => props.message || flash.value.message || flash.value.success);
const fieldErrors = computed(() => props.errors || pageErrors.value || {});

const form = useForm({
  password: '',
  password_confirm: '',
});

const submit = () => {
  form.post('/set-password', {
    onSuccess: () => form.reset(),
  });
};
</script>

<template>
  <Head title="Create Password" />

  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
      <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-white mb-2">Create Password</h1>
        <p class="text-xs text-slate-400">
          Set a password for your account to enable traditional email & password sign-in.
        </p>
      </div>

      <div v-if="successMessage" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {{ successMessage }}
      </div>

      <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">New Password</label>
          <input
            v-model="form.password"
            type="password"
            required
            autocomplete="new-password"
            placeholder="••••••••"
            class="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
          />
          <p v-if="fieldErrors.password" class="text-red-400 text-xs mt-1">{{ fieldErrors.password }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">Confirm Password</label>
          <input
            v-model="form.password_confirm"
            type="password"
            required
            autocomplete="new-password"
            placeholder="••••••••"
            class="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
          />
          <p v-if="fieldErrors.password_confirm" class="text-red-400 text-xs mt-1">{{ fieldErrors.password_confirm }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
        >
          {{ form.processing ? 'Saving...' : 'Set Password' }}
        </button>
      </form>
    </div>
  </div>
</template>
