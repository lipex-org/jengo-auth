import React, { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';

interface Factor {
  id: string;
  label: string;
  icon: string;
  description: string;
  is_enrolled: boolean;
}

interface SudoChallengeProps {
  available_factors?: Factor[];
  message?: string;
  error?: string;
  flash?: Record<string, string>;
}

export default function SudoChallenge({ available_factors = [], error: initialError, message: initialMessage }: SudoChallengeProps) {
  const { props } = usePage<any>();
  const flash = props.flash || {};
  const pageErrors = props.errors || {};

  const error = initialError || flash.error || pageErrors.error || pageErrors.credentials || pageErrors.factor;
  const message = initialMessage || flash.message || flash.success;

  const [activeTab, setActiveTab] = useState<'password' | 'totp' | 'passkey' | 'recovery_code'>('password');
  const { data, setData, post, processing, errors } = useForm({
    factor: 'password',
    proof: '',
  });

  const handleTabChange = (factor: 'password' | 'totp' | 'passkey' | 'recovery_code') => {
    setActiveTab(factor);
    setData({
      factor: factor,
      proof: '',
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/auth/sudo/verify');
  };

  const handlePasskeyAuth = async () => {
    try {
      const res = await fetch('/auth/sudo/challenge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ factor: 'passkey' }),
      });
      const challenge = await res.json();
      if (challenge.status !== 'success' || !challenge.data?.challenge?.options) {
        alert(challenge.message || 'Passkey challenge failed.');
        return;
      }

      const options = challenge.data.challenge.options;
      options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
      if (options.allowCredentials) {
        options.allowCredentials = options.allowCredentials.map((c: any) => ({
          ...c,
          id: Uint8Array.from(atob(c.id.replace(/-/g, '+').replace(/_/g, '/')), (ch) => ch.charCodeAt(0)),
        }));
      }

      const cred = (await navigator.credentials.get({ publicKey: options })) as any;
      const proof = {
        id: cred.id,
        rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        authenticatorData: btoa(String.fromCharCode(...new Uint8Array(cred.response.authenticatorData))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
        signature: btoa(String.fromCharCode(...new Uint8Array(cred.response.signature))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
      };

      const verifyRes = await fetch('/auth/sudo/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ factor: 'passkey', proof }),
      });
      const verifyData = await verifyRes.json();
      if (verifyData.status === 'success') {
        window.location.href = verifyData.data?.intended_url || '/';
      } else {
        alert(verifyData.message || 'Passkey verification failed.');
      }
    } catch (err: any) {
      alert('Passkey error: ' + err.message);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-slate-100">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div className="text-center mb-6">
          <div className="w-14 h-14 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 inline-flex items-center justify-center mb-3">
            <svg className="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-white">Confirm Access</h1>
          <p className="text-xs text-slate-400 mt-1">This is a protected area. Please verify your identity to enter Sudo mode.</p>
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

        <div className="flex rounded-xl bg-slate-950/60 p-1 border border-slate-800 mb-4">
          <button
            type="button"
            onClick={() => handleTabChange('password')}
            className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition ${
              activeTab === 'password' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'
            }`}
          >
            Password
          </button>
          <button
            type="button"
            onClick={() => handleTabChange('totp')}
            className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition ${
              activeTab === 'totp' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'
            }`}
          >
            Authenticator
          </button>
          <button
            type="button"
            onClick={() => handleTabChange('passkey')}
            className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition ${
              activeTab === 'passkey' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'
            }`}
          >
            Passkey
          </button>
          <button
            type="button"
            onClick={() => handleTabChange('recovery_code')}
            className={`flex-1 py-1.5 text-xs font-semibold rounded-lg transition ${
              activeTab === 'recovery_code' ? 'bg-slate-800 text-white shadow' : 'text-slate-400 hover:text-white'
            }`}
          >
            Recovery
          </button>
        </div>

        {activeTab === 'passkey' ? (
          <div className="space-y-4 text-center py-4">
            <p className="text-xs text-slate-400">Authenticate with Touch ID, Face ID, or your hardware security key.</p>
            <button
              type="button"
              onClick={handlePasskeyAuth}
              className="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-sm rounded-xl transition duration-150"
            >
              Use Passkey / Biometrics
            </button>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                {activeTab === 'password' && 'Account Password'}
                {activeTab === 'totp' && '6-Digit Authenticator Code'}
                {activeTab === 'recovery_code' && 'Emergency Recovery Code'}
              </label>
              <input
                type={activeTab === 'password' ? 'password' : 'text'}
                value={data.proof}
                onChange={(e) => setData('proof', e.target.value)}
                className={`w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white focus:ring-2 focus:ring-blue-500 outline-none ${
                  activeTab === 'totp' ? 'text-center text-xl tracking-widest' : ''
                }`}
                placeholder={
                  activeTab === 'password' ? 'Enter your password' : activeTab === 'totp' ? '000 000' : 'xxxx-xxxx'
                }
                required
                autoFocus
              />
              {errors.proof && <p className="text-xs text-red-400 mt-1">{errors.proof}</p>}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full py-3 bg-white hover:bg-slate-100 text-slate-950 font-semibold text-sm rounded-xl transition duration-150 disabled:opacity-50"
            >
              {processing ? 'Verifying...' : 'Verify Identity'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
