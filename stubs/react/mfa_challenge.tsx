import React from 'react';
import { useForm, usePage, router } from '@inertiajs/react';

interface MfaChallengeProps {
  message?: string;
  error?: string;
  flash?: Record<string, string>;
}

export default function MfaChallenge({ error: initialError, message: initialMessage }: MfaChallengeProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageErrors = props.errors || {};

  const error = initialError || flash.error || pageErrors.error;
  const message = initialMessage || flash.message || flash.success;

  const { data, setData, post, processing, errors } = useForm({
    code: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/action/handle');
  };

  const handleResend = () => {
    router.post('/action/challenge', {}, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const handleCancel = () => {
    router.post('/action/cancel');
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div className="text-center mb-6">
          <div className="w-12 h-12 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 inline-flex items-center justify-center mb-3">
            <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-white">Two-Factor Authentication</h1>
          <p className="text-xs text-slate-400 mt-1">Enter the verification code sent to your email or authenticator app.</p>
        </div>

        {message && (
          <div className="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs text-center">
            {message}
          </div>
        )}

        {error && (
          <div className="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs text-center">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1 text-center">Verification Code</label>
            <input
              type="text"
              value={data.code}
              onChange={(e) => setData('code', e.target.value)}
              className="w-full text-center text-2xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="000 000"
              maxLength={7}
              required
              autoFocus
            />
            {errors.code && <p className="text-xs text-red-400 mt-1 text-center">{errors.code}</p>}
          </div>

          <button
            type="submit"
            disabled={processing}
            className="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
          >
            {processing ? 'Verifying...' : 'Verify Code'}
          </button>
        </form>

        <div className="mt-6 flex items-center justify-between text-xs">
          <button
            type="button"
            onClick={handleResend}
            className="text-blue-400 hover:text-blue-300 font-medium transition"
          >
            Resend Code
          </button>

          <button
            type="button"
            onClick={handleCancel}
            className="text-slate-400 hover:text-slate-300 transition"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}

