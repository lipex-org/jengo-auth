<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';

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
  username: '',
  email: '',
  password: '',
  password_confirm: '',
});

const submit = () => {
  form.post('/register');
};
</script>

<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
      <h1 class="text-2xl font-bold text-white mb-2 text-center">Create Account</h1>
      <p class="text-xs text-slate-400 mb-6 text-center">Sign up to get started with your new account.</p>

      <div v-if="message" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {{ message }}
      </div>

      <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1">Username</label>
          <input
            v-model="form.username"
            type="text"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="alexdev"
            required
            autofocus
          />
          <p v-if="form.errors.username" class="text-xs text-red-400 mt-1">{{ form.errors.username }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="alex@example.com"
            required
          />
          <p v-if="form.errors.email" class="text-xs text-red-400 mt-1">{{ form.errors.email }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
          <input
            v-model="form.password"
            type="password"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="••••••••"
            required
          />
          <p v-if="form.errors.password" class="text-xs text-red-400 mt-1">{{ form.errors.password }}</p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1">Confirm Password</label>
          <input
            v-model="form.password_confirm"
            type="password"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="••••••••"
            required
          />
          <p v-if="form.errors.password_confirm" class="text-xs text-red-400 mt-1">{{ form.errors.password_confirm }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
        >
          {{ form.processing ? 'Creating account...' : 'Create Account' }}
        </button>
      </form>

      <div class="mt-6 text-center text-xs text-slate-400">
        Already have an account?
        <Link href="/login" class="text-blue-400 hover:text-blue-300 font-semibold">Log in</Link>
      </div>
    </div>
  </div>
</template>
