<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

interface Factor {
  id: string;
  label: string;
  icon: string;
  description: string;
  is_enrolled: boolean;
}

const props = defineProps<{
  data?: {
    enrolled_factors?: Factor[];
    available_factors?: Factor[];
  };
  message?: string;
  error?: string;
  user?: any;
}>();

const page = usePage<any>();
const flash = computed(() => page.props.flash || {});
const pageErrors = computed(() => page.props.errors || {});

const error = computed(() => props.error || flash.value.error || pageErrors.value.error);
const message = computed(() => props.message || flash.value.message || flash.value.success);

const totpModal = ref(false);
const totpData = ref<{ secret: string; qr_data_uri?: string; qr_uri?: string } | null>(null);
const totpCode = ref('');
const recoveryModal = ref(false);
const recoveryCodes = ref<string[]>([]);
const isProcessing = ref(false);

const isEnrolled = (id: string) => props.data?.enrolled_factors?.some((f) => f.id === id);

// Watch for flash enrollment data received via standard Inertia redirects
watch(
  () => flash.value.enrollment_data,
  (enrollmentData) => {
    if (!enrollmentData) return;
    const factor = flash.value.enrollment_factor;

    if (factor === 'totp' && enrollmentData.secret) {
      totpData.value = enrollmentData;
      totpModal.value = true;
    } else if (factor === 'recovery_code' && enrollmentData.codes) {
      recoveryCodes.value = enrollmentData.codes;
      recoveryModal.value = true;
    }
  },
  { immediate: true, deep: true }
);

const startTotp = () => {
  isProcessing.value = true;
  router.post(
    '/two-factor/enroll/start',
    { factor: 'totp' },
    {
      preserveScroll: true,
      onFinish: () => {
        isProcessing.value = false;
      },
    }
  );
};

const confirmTotp = () => {
  if (!totpCode.value) return;
  router.post(
    '/two-factor/enroll/confirm',
    { factor: 'totp', code: totpCode.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        totpModal.value = false;
        totpCode.value = '';
      },
    }
  );
};

const startPasskey = () => {
  const name = prompt('Name for this passkey:', 'MacBook Touch ID / YubiKey');
  if (!name) return;

  isProcessing.value = true;
  router.post(
    '/two-factor/enroll/start',
    { factor: 'passkey', options: { name } },
    {
      preserveScroll: true,
      onSuccess: async () => {
        const enrollmentData = flash.value.enrollment_data;
        if (!enrollmentData?.options) {
          isProcessing.value = false;
          return;
        }

        try {
          const options = enrollmentData.options;
          options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
          options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

          const cred = (await navigator.credentials.create({ publicKey: options })) as any;
          const proof = {
            id: cred.id,
            rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
            attestationObject: btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
          };

          router.post(
            '/two-factor/enroll/confirm',
            { factor: 'passkey', proof, metadata: { name } },
            {
              preserveScroll: true,
              onFinish: () => {
                isProcessing.value = false;
              },
            }
          );
        } catch (err: any) {
          isProcessing.value = false;
          alert(err?.message || 'Passkey registration cancelled or failed');
        }
      },
      onError: () => {
        isProcessing.value = false;
      },
    }
  );
};

const startRecoveryCodes = () => {
  if (!confirm('Generating new recovery codes will invalidate prior codes. Continue?')) return;
  router.post(
    '/two-factor/enroll/start',
    { factor: 'recovery_code' },
    {
      preserveScroll: true,
    }
  );
};

const unenroll = (factor: string) => {
  if (!confirm(`Are you sure you want to remove ${factor.toUpperCase()}?`)) return;
  router.post(
    '/two-factor/unenroll',
    { factor },
    {
      preserveScroll: true,
    }
  );
};
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
      <div class="flex items-center justify-between border-b border-slate-800 pb-5">
        <div>
          <Link href="/" class="text-sm font-semibold text-blue-400 hover:text-blue-300">&larr; Back to Home</Link>
          <h1 class="text-2xl font-bold text-white mt-1">Two-Factor Authentication & Sudo</h1>
          <p class="text-sm text-slate-400">Configure Multi-Factor Authentication and hardware security keys.</p>
        </div>
      </div>

      <div v-if="message" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
        {{ message }}
      </div>

      <div v-if="error" class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
        {{ error }}
      </div>

      <div class="space-y-4">
        <!-- TOTP -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-white">Authenticator App (TOTP)</h3>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                  isEnrolled('totp') ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700'
                ]"
              >
                {{ isEnrolled('totp') ? 'ENROLLED' : 'NOT CONFIGURED' }}
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Use Google Authenticator or 1Password for 6-digit dynamic codes.</p>
          </div>
          <div>
            <button
              v-if="isEnrolled('totp')"
              @click="unenroll('totp')"
              class="px-4 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40"
            >
              Remove TOTP
            </button>
            <button
              v-else
              @click="startTotp"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-xl"
            >
              Setup Authenticator
            </button>
          </div>
        </div>

        <!-- Passkeys -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h3 class="font-semibold text-white">Passkeys & Security Keys (WebAuthn)</h3>
            <p class="text-xs text-slate-400 mt-1">Log in with Touch ID, Face ID, Windows Hello, or YubiKeys.</p>
          </div>
          <button
            @click="startPasskey"
            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white rounded-xl"
          >
            Add Passkey
          </button>
        </div>

        <!-- Recovery Codes -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-semibold text-white">Emergency Recovery Codes</h3>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                  isEnrolled('recovery_code') ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 'bg-slate-800 text-slate-400 border-slate-700'
                ]"
              >
                {{ isEnrolled('recovery_code') ? 'ACTIVE' : 'NOT GENERATED' }}
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Single-use backup codes for emergency account access.</p>
          </div>
          <button
            @click="startRecoveryCodes"
            class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white rounded-xl"
          >
            {{ isEnrolled('recovery_code') ? 'Regenerate Codes' : 'Generate Codes' }}
          </button>
        </div>
      </div>

      <!-- TOTP Modal -->
      <div v-if="totpModal && totpData" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
          <h3 class="text-lg font-bold text-white">Setup Authenticator App</h3>
          <div v-if="totpData.qr_data_uri" class="flex justify-center p-2 bg-white rounded-xl w-48 h-48 mx-auto items-center">
            <img :src="totpData.qr_data_uri" alt="TOTP QR Code" class="w-44 h-44 object-contain" />
          </div>
          <div class="text-center font-mono font-bold text-amber-400 text-sm">{{ totpData.secret }}</div>
          <input
            v-model="totpCode"
            type="text"
            placeholder="123456"
            maxlength="6"
            class="w-full text-center text-xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white"
          />
          <div class="flex gap-3">
            <button
              @click="totpModal = false"
              class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl"
            >
              Cancel
            </button>
            <button
              @click="confirmTotp"
              class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
            >
              Confirm & Activate
            </button>
          </div>
        </div>
      </div>

      <!-- Recovery Codes Modal -->
      <div v-if="recoveryModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
          <h3 class="text-lg font-bold text-white">Emergency Recovery Codes</h3>
          <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 grid grid-cols-2 gap-2 text-center font-mono text-sm text-amber-400">
            <div v-for="(c, i) in recoveryCodes" :key="i">{{ c }}</div>
          </div>
          <button
            @click="recoveryModal = false; router.reload();"
            class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
          >
            I Have Saved These Codes
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
