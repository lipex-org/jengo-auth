import React from 'react';
import { useForm, usePage, Link } from '@inertiajs/react';

interface Identity {
  id: number;
  type: string;
  provider: string;
  provider_name: string;
  provider_user_id: string;
  email?: string;
  name?: string;
  avatar?: string;
  last_used_at?: string;
  created_at?: string;
}

interface SocialProvider {
  id: string;
  name: string;
  url: string;
}

interface IdentitiesProps {
  user?: any;
  has_password?: boolean;
  identities?: Identity[];
  passkeys_count?: number;
  available_providers?: SocialProvider[];
  data?: {
    has_password?: boolean;
    identities?: Identity[];
    passkeys_count?: number;
    available_providers?: SocialProvider[];
  };
}

export default function Identities({
  user: propUser,
  has_password: propHasPassword,
  identities: propIdentities,
  passkeys_count: propPasskeysCount,
  available_providers: propProviders,
  data: propData,
}: IdentitiesProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageData = props.data || {};

  const currentUser = propUser || props.auth?.user || props.user;
  const hasPassword = propHasPassword ?? propData?.has_password ?? pageData.has_password ?? false;
  const identitiesList: Identity[] = propIdentities ?? propData?.identities ?? pageData.identities ?? [];
  const providers: SocialProvider[] = propProviders ?? propData?.available_providers ?? pageData.available_providers ?? [];

  const linkedSet = new Set(identitiesList.map((i) => i.provider));
  const unlinkedProviders = providers.filter((p) => !linkedSet.has(p.id));

  const { post } = useForm({});

  const handleUnlink = (identityId: number) => {
    if (window.confirm('Are you sure you want to disconnect this account?')) {
      post(`/identities/unlink/${identityId}`);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
      <div className="max-w-4xl mx-auto space-y-8">
        {/* Top Navigation */}
        <div className="flex items-center justify-between border-b border-slate-800 pb-5">
          <div>
            <Link href="/" className="text-sm font-semibold text-blue-400 hover:text-blue-300 flex items-center gap-1 mb-1">
              &larr; Back to Home
            </Link>
            <h1 className="text-2xl font-bold tracking-tight text-white">Linked Accounts & Identities</h1>
            <p className="text-sm text-slate-400">Manage your connected social login providers, passwords, and sign-in identities.</p>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-xs font-medium px-3 py-1 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
              Logged in as <strong className="text-white">{currentUser?.username || 'User'}</strong>
            </span>
            <Link
              href="/logout"
              method="post"
              as="button"
              className="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-950/40 border border-red-800/40 px-3 py-1 rounded-lg"
            >
              Logout
            </Link>
          </div>
        </div>

        {/* Flash Messages */}
        {(flash.message || flash.success) && (
          <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
            {flash.message || flash.success}
          </div>
        )}

        {flash.error && (
          <div className="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
            {flash.error}
          </div>
        )}

        {/* Primary Password Identity Status */}
        <div className="p-6 rounded-2xl border bg-slate-900/60 border-slate-800 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div
              className={`w-12 h-12 rounded-xl flex items-center justify-center ${
                hasPassword
                  ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                  : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
              }`}
            >
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
              </svg>
            </div>
            <div>
              <h3 className="font-semibold text-base text-white">
                Password Authentication:{' '}
                <span className={hasPassword ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold'}>
                  {hasPassword ? 'Configured' : 'Not Set'}
                </span>
              </h3>
              <p className="text-xs text-slate-400 mt-0.5">
                {hasPassword
                  ? 'You can sign in using your email and password.'
                  : 'No password is set. Set a password so you can sign in directly without third-party providers.'}
              </p>
            </div>
          </div>
          <div>
            <Link
              href="/set-password"
              className={`px-4 py-2 text-xs font-semibold rounded-xl transition ${
                hasPassword
                  ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700'
                  : 'bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-500/20'
              }`}
            >
              {hasPassword ? 'Change Password' : 'Set Password'}
            </Link>
          </div>
        </div>

        {/* Linked Social Accounts List */}
        <div className="space-y-4">
          <h2 className="text-lg font-bold text-white">Connected Social Accounts</h2>

          {identitiesList.length === 0 ? (
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-8 text-center text-slate-400 text-sm">
              No third-party social accounts currently linked.
            </div>
          ) : (
            <div className="space-y-3">
              {identitiesList.map((identity) => (
                <div
                  key={identity.id}
                  className="bg-slate-900 border border-slate-800 rounded-2xl p-5 flex items-center justify-between"
                >
                  <div className="flex items-center gap-4">
                    {identity.avatar ? (
                      <img src={identity.avatar} alt="Avatar" className="w-10 h-10 rounded-xl object-cover border border-slate-700" />
                    ) : (
                      <div className="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center font-bold text-sm">
                        {identity.provider.substring(0, 1).toUpperCase()}
                      </div>
                    )}
                    <div>
                      <div className="flex items-center gap-2">
                        <h4 className="font-bold text-white text-sm">{identity.provider_name}</h4>
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                          CONNECTED
                        </span>
                      </div>
                      <p className="text-xs text-slate-400 mt-0.5">
                        {identity.email || identity.name || `ID: ${identity.provider_user_id}`}
                      </p>
                    </div>
                  </div>

                  <button
                    type="button"
                    onClick={() => handleUnlink(identity.id)}
                    className="px-3.5 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40 transition"
                  >
                    Disconnect
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Available Providers to Connect */}
        {unlinkedProviders.length > 0 && (
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 space-y-4">
            <h3 className="font-bold text-lg text-white">Link Other Accounts</h3>
            <p className="text-xs text-slate-400">Connect additional third-party accounts to log in with a single click.</p>

            <div className="flex flex-wrap gap-3 pt-2">
              {unlinkedProviders.map((provider) => (
                <a
                  key={provider.id}
                  href={provider.url}
                  className="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white rounded-xl border border-slate-700 flex items-center gap-2 transition"
                >
                  Link {provider.name}
                </a>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
