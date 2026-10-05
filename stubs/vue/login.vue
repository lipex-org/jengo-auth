<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';

const props = defineProps<{
  error?: string;
  message?: string;
  allow_magic_link?: boolean;
  can_reset_password?: boolean;
  social_providers?: Array<{ id: string; name: string; url: string }>;
  data?: {
    allow_magic_link?: boolean;
    can_reset_password?: boolean;
    social_providers?: Array<{ id: string; name: string; url: string }>;
  };
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});
const pageData = computed(() => page.props.data || {});

const allowMagicLink = computed(() => props.allow_magic_link ?? props.data?.allow_magic_link ?? pageData.value.allow_magic_link ?? false);
const canResetPassword = computed(() => props.can_reset_password ?? props.data?.can_reset_password ?? pageData.value.can_reset_password ?? true);
const socialProviders = computed(() => props.social_providers ?? props.data?.social_providers ?? pageData.value.social_providers ?? []);

const error = computed(() => props.error || flash.value.error || pageErrors.value.credentials || pageErrors.value.error);
const message = computed(() => props.message || flash.value.message || flash.value.success);

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post('/login');
};
</script>

<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
      <h1 class="text-2xl font-bold text-white mb-2 text-center">Log In</h1>
      <p class="text-xs text-slate-400 mb-6 text-center">Welcome back! Please enter your details.</p>

      <div v-if="message" class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
        {{ message }}
      </div>

      <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
        {{ error }}
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium text-slate-300 mb-1">Email or Username</label>
          <input
            v-model="form.email"
            type="text"
            class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
            placeholder="alex@example.com"
            required
            autofocus
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

        <div class="flex items-center justify-between text-xs">
          <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
            <input
              v-model="form.remember"
              type="checkbox"
              class="rounded bg-slate-950 border-slate-800 text-blue-600 focus:ring-blue-500"
            />
            Remember me
          </label>
          <div class="flex items-center gap-3">
            <Link v-if="allowMagicLink" href="/magic-link" class="text-blue-400 hover:text-blue-300">Magic link</Link>
            <Link v-if="canResetPassword" href="/forgot-password" class="text-blue-400 hover:text-blue-300">Forgot password?</Link>
          </div>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
        >
          {{ form.processing ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>

      <div v-if="socialProviders.length > 0" class="mt-6">
        <div class="relative flex py-2 items-center">
          <div class="flex-grow border-t border-slate-800"></div>
          <span class="flex-shrink mx-4 text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Or continue with</span>
          <div class="flex-grow border-t border-slate-800"></div>
        </div>

        <div class="grid grid-cols-1 gap-2 mt-2">
          <a
            v-for="provider in socialProviders"
            :key="provider.id"
            :href="provider.url"
            class="w-full py-2.5 px-4 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-xl text-xs font-semibold text-white flex items-center justify-center gap-2 transition duration-150"
          >
            Sign in with {{ provider.name }}
          </a>
        </div>
      </div>

      <div class="mt-6 text-center text-xs text-slate-400">
        Don't have an account?
        <Link href="/register" class="text-blue-400 hover:text-blue-300 font-semibold">Sign up</Link>
      </div>
    </div>
  </div>
</template>
