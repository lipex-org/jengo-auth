import React, { useState, useId } from 'react';
import { useForm, usePage, Head } from '@inertiajs/react';

interface SetPasswordProps {
  error?: string;
  message?: string;
  errors?: Record<string, string>;
  branding?: {
    name?: string;
    logo?: string;
  };
}

export default function SetPassword(props: SetPasswordProps) {
  const { flash, errors: pageErrors } = usePage<any>().props;
  const error = props.error || flash?.error || pageErrors?.error;
  const message = props.message || flash?.message || flash?.success;
  const fieldErrors = props.errors || pageErrors || {};

  const passwordId = useId();
  const confirmPasswordId = useId();

  const { data, setData, post, processing, reset } = useForm({
    password: '',
    password_confirm: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/set-password', {
      onSuccess: () => reset(),
    });
  };

  return (
    <>
      <Head title="Create Password" />

      <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
        <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
          <div className="text-center mb-6">
            <h1 className="text-2xl font-bold text-white mb-2">Create Password</h1>
            <p className="text-xs text-slate-400">
              Set a password for your account to enable traditional email & password sign-in.
            </p>
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
              <label htmlFor={passwordId} className="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">
                New Password
              </label>
              <input
                id={passwordId}
                type="password"
                required
                autoComplete="new-password"
                placeholder="••••••••"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                className="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              />
              {fieldErrors.password && <p className="text-red-400 text-xs mt-1">{fieldErrors.password}</p>}
            </div>

            <div>
              <label htmlFor={confirmPasswordId} className="block text-xs font-medium text-slate-300 uppercase tracking-wider mb-1.5">
                Confirm Password
              </label>
              <input
                id={confirmPasswordId}
                type="password"
                required
                autoComplete="new-password"
                placeholder="••••••••"
                value={data.password_confirm}
                onChange={(e) => setData('password_confirm', e.target.value)}
                className="w-full px-4 py-2.5 bg-slate-950/80 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
              />
              {fieldErrors.password_confirm && <p className="text-red-400 text-xs mt-1">{fieldErrors.password_confirm}</p>}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
            >
              {processing ? 'Saving...' : 'Set Password'}
            </button>
          </form>
        </div>
      </div>
    </>
  );
}
