import React from 'react';
import { useForm, usePage, Link } from '@inertiajs/react';

interface SocialProvider {
  id: string;
  name: string;
  url: string;
}

interface RegisterProps {
  errors?: Record<string, string>;
  message?: string;
  error?: string;
  social_providers?: SocialProvider[];
  data?: {
    social_providers?: SocialProvider[];
  };
  flash?: Record<string, string>;
}

export default function Register({
  error: initialError,
  message: initialMessage,
  social_providers: propSocialProviders,
  data: propData,
}: RegisterProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageErrors = props.errors || {};
  const pageData = props.data || {};

  const socialProviders: SocialProvider[] = propSocialProviders ?? propData?.social_providers ?? pageData.social_providers ?? [];

  const error = initialError || flash.error || pageErrors.error;
  const message = initialMessage || flash.message || flash.success;

  const { data, setData, post, processing, errors } = useForm({
    username: '',
    email: '',
    password: '',
    password_confirm: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/register');
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <h1 className="text-2xl font-bold text-white mb-2 text-center">Create Account</h1>
        <p className="text-xs text-slate-400 mb-6 text-center">Sign up to get started with your new account.</p>

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
            <label className="block text-xs font-medium text-slate-300 mb-1">Username</label>
            <input
              type="text"
              value={data.username}
              onChange={(e) => setData('username', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="alexdev"
              required
              autoFocus
            />
            {errors.username && <p className="text-xs text-red-400 mt-1">{errors.username}</p>}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1">Email</label>
            <input
              type="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="alex@example.com"
              required
            />
            {errors.email && <p className="text-xs text-red-400 mt-1">{errors.email}</p>}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1">Password</label>
            <input
              type="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="••••••••"
              required
            />
            {errors.password && <p className="text-xs text-red-400 mt-1">{errors.password}</p>}
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1">Confirm Password</label>
            <input
              type="password"
              value={data.password_confirm}
              onChange={(e) => setData('password_confirm', e.target.value)}
              className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none"
              placeholder="••••••••"
              required
            />
            {errors.password_confirm && <p className="text-xs text-red-400 mt-1">{errors.password_confirm}</p>}
          </div>

          <button
            type="submit"
            disabled={processing}
            className="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
          >
            {processing ? 'Creating account...' : 'Create Account'}
          </button>
        </form>

        {socialProviders.length > 0 && (
          <div className="mt-6">
            <div className="relative flex py-2 items-center">
              <div className="flex-grow border-t border-slate-800"></div>
              <span className="flex-shrink mx-4 text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Or register with</span>
              <div className="flex-grow border-t border-slate-800"></div>
            </div>

            <div className="grid grid-cols-1 gap-2 mt-2">
              {socialProviders.map((provider) => (
                <a
                  key={provider.id}
                  href={provider.url}
                  className="w-full py-2.5 px-4 bg-slate-950 hover:bg-slate-800/80 border border-slate-800 rounded-xl text-xs font-semibold text-white flex items-center justify-center gap-2 transition duration-150"
                >
                  Register with {provider.name}
                </a>
              ))}
            </div>
          </div>
        )}

        <div className="mt-6 text-center text-xs text-slate-400">
          Already have an account?{' '}
          <Link href="/login" className="text-blue-400 hover:text-blue-300 font-semibold">
            Log in
          </Link>
        </div>
      </div>
    </div>
  );
}
