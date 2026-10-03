<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
  error?: string;
  message?: string;
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});

const error = computed(() => props.error || flash.value.error || pageErrors.value.error);
const message = computed(() => props.message || flash.value.message || flash.value.success);

const form = useForm({
  code: '',
});

const submit = () => {
  form.post('/auth/action/handle');
};
</script>

<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
      <div class="text-center mb-6">
        <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 inline-flex items-center justify-center mb-3">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white">Two-Factor Authentication</h1>
        <p class="text-xs text-slate-400 mt-1">Enter the 6-digit verification code from your authenticator app.</p>
      </div>

      <div v-if="message" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {{ message }}
      </div>

      <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1 text-center">Verification Code</label>
          <input
            v-model="form.code"
            type="text"
            class="w-full text-center text-2xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="000 000"
            maxlength="7"
            required
            autofocus
          />
          <p v-if="form.errors.code" class="text-xs text-red-400 mt-1 text-center">{{ form.errors.code }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
        >
          {{ form.processing ? 'Verifying...' : 'Verify Code' }}
        </button>
      </form>
    </div>
  </div>
</template>
