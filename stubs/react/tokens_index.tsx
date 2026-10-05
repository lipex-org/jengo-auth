import React from 'react';
import { useForm, usePage, Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';

interface Token {
  id: number;
  name: string;
  abilities: string[];
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

interface TokensIndexProps {
  data?: { tokens?: Token[] };
  message?: string;
  error?: string;
}

export default function TokensIndex({
  data: dataProp,
  message: initialMessage,
  error: initialError,
}: TokensIndexProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageErrors = props.errors || {};

  const error = initialError || flash.error || pageErrors.error;
  const message = initialMessage || flash.message || flash.success;

  const tokens = dataProp?.tokens || [];

  const { data: formData, setData, post, processing, reset } = useForm({
    name: '',
    abilities: 'dashboard:view, profile:edit',
  });

  const newPlainTextToken = props.token || flash.token || null;

  const createToken = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name) return;

    const abilitiesArray = formData.abilities
      .split(',')
      .map((s: string) => s.trim())
      .filter(Boolean);

    post('/tokens/create', {
      preserveScroll: true,
      onSuccess: () => {
        reset('name');
      },
    });
  };

  const revokeToken = (tokenId: number) => {
    if (
      !confirm(
        'Are you sure you want to revoke this API token? Any application using it will lose access.'
      )
    ) {
      return;
    }

    router.delete(`/tokens/${tokenId}`, {
      preserveScroll: true,
    });
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
      <Head title="Personal Access Tokens" />

      <div className="max-w-4xl mx-auto space-y-8">
        <div className="flex items-center justify-between border-b border-slate-800 pb-5">
          <div>
            <h1 className="text-2xl font-bold text-white">Personal Access Tokens</h1>
            <p className="text-xs text-slate-400 mt-1">
              Manage API tokens that allow external access to your account.
            </p>
          </div>
        </div>

        {message && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
            {message}
          </div>
        )}

        {error && (
          <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
            {error}
          </div>
        )}

        {/* Create Token Card */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
          <h3 className="text-lg font-bold text-white mb-1">Generate API Token</h3>
          <p className="text-xs text-slate-400 mb-6">
            Personal access tokens function like password-less HTTP Bearer authentication for scripts and API clients.
          </p>

          {/* New Token Alert */}
          {newPlainTextToken && (
            <div className="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 space-y-2">
              <div className="font-bold text-xs flex items-center gap-2">
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Token Generated! Copy it now:
              </div>
              <div className="p-3 bg-slate-950 rounded-xl font-mono text-xs text-white select-all break-all border border-slate-800">
                {newPlainTextToken}
              </div>
              <p className="text-[11px] text-slate-400">
                For security reasons, this token will never be shown again.
              </p>
            </div>
          )}

          <form onSubmit={createToken} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Token Name</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="e.g. CI/CD Runner or Mobile App"
                  required
                  className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">Abilities / Scopes (comma separated)</label>
                <input
                  type="text"
                  value={formData.abilities}
                  onChange={(e) => setData('abilities', e.target.value)}
                  placeholder="dashboard:view, profile:edit"
                  className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none text-xs"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={processing || !formData.name}
              className="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs rounded-xl transition duration-150 disabled:opacity-50"
            >
              {processing ? 'Generating...' : 'Create API Token'}
            </button>
          </form>
        </div>

        {/* Active Tokens List */}
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
          <h3 className="text-lg font-bold text-white mb-1">Active Tokens</h3>
          <p className="text-xs text-slate-400 mb-6">
            Tokens configured on your account. You can revoke access at any time.
          </p>

          {tokens.length === 0 ? (
            <div className="text-center py-8 text-slate-500 text-xs">
              No personal access tokens generated yet.
            </div>
          ) : (
            <div className="divide-y divide-slate-800">
              {tokens.map((token) => (
                <div
                  key={token.id}
                  className="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                >
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-white text-sm">{token.name}</span>
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-mono bg-slate-800 text-slate-400 border border-slate-700">
                        ID: #{token.id}
                      </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5 mt-1.5">
                      {token.abilities.map((ability) => (
                        <span
                          key={ability}
                          className="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20"
                        >
                          {ability}
                        </span>
                      ))}
                      <span className="text-[11px] text-slate-400 ml-1">
                        Last used: {token.last_used_at || 'Never'}
                      </span>
                    </div>
                  </div>

                  <button
                    onClick={() => revokeToken(token.id)}
                    className="px-3 py-1.5 text-xs font-semibold text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition self-start sm:self-center"
                  >
                    Revoke
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
