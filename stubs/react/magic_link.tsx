import React from 'react';
import { useForm, usePage, Link } from '@inertiajs/react';

interface MagicLinkProps {
  message?: string;
  error?: string;
  flash?: Record<string, string>;
}

export default function MagicLink({ message: initialMessage, error: initialError }: MagicLinkProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageErrors = props.errors || {};

  const error = initialError || flash.error || pageErrors.error;
  const message = initialMessage || flash.message || flash.success;

  const { data, setData, post, processing, errors } = useForm({
    email: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/magic-link');
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <h1 className="text-2xl font-bold text-white mb-2 text-center">Passwordless Login</h1>
        <p className="text-xs text-slate-400 mb-6 text-center">
          Enter your email and we'll send you an instant login link.
        </p>

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
            <label className="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="alex@example.com"
              required
              autoFocus
            />
            {errors.email && <p className="text-xs text-red-400 mt-1">{errors.email}</p>}
          </div>

          <button
            type="submit"
            disabled={processing}
            className="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
          >
            {processing ? 'Sending magic link...' : 'Send Magic Link'}
          </button>
        </form>

        <div className="mt-6 text-center text-xs text-slate-400">
          Prefer using a password?{' '}
          <Link href="/login" className="text-blue-400 hover:text-blue-300 font-semibold">
            Password login
          </Link>
        </div>
      </div>
    </div>
  );
}
