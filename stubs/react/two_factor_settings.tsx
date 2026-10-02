import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';

interface Factor {
  id: string;
  label: string;
  icon: string;
  description: string;
  is_enrolled: boolean;
}

interface TwoFactorSettingsProps {
  enrolled_factors?: Factor[];
  available_factors?: Factor[];
  user?: any;
}

export default function TwoFactorSettings({
  enrolled_factors = [],
  available_factors = [],
  user,
}: TwoFactorSettingsProps) {
  const [totpModal, setTotpModal] = useState(false);
  const [totpData, setTotpData] = useState<{ secret: string; qr_data_uri?: string; qr_uri?: string } | null>(null);
  const [totpCode, setTotpCode] = useState('');
  const [recoveryModal, setRecoveryModal] = useState(false);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);

  const isEnrolled = (id: string) => enrolled_factors.some((f) => f.id === id);

  const startTotp = async () => {
    const res = await fetch('/user/two-factor/enroll/start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor: 'totp' }),
    });
    const json = await res.json();
    if (json.status === 'success') {
      setTotpData(json.data.data);
      setTotpModal(true);
    } else {
      alert(json.message || 'Failed to initialize TOTP');
    }
  };

  const confirmTotp = async () => {
    const res = await fetch('/user/two-factor/enroll/confirm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor: 'totp', code: totpCode }),
    });
    const json = await res.json();
    if (json.status === 'success') {
      setTotpModal(false);
      router.reload();
    } else {
      alert(json.message || 'Invalid code');
    }
  };

  const startPasskey = async () => {
    const name = prompt('Name for this passkey:', 'MacBook Touch ID / YubiKey');
    if (!name) return;

    const res = await fetch('/user/two-factor/enroll/start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor: 'passkey', options: { name } }),
    });
    const json = await res.json();
    if (json.status !== 'success' || !json.data?.data?.options) {
      alert(json.message || 'Failed to start passkey');
      return;
    }

    const options = json.data.data.options;
    options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
    options.user.id = Uint8Array.from(atob(options.user.id.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

    const cred = (await navigator.credentials.create({ publicKey: options })) as any;
    const proof = {
      id: cred.id,
      rawId: btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
      clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(cred.response.clientDataJSON))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
      attestationObject: btoa(String.fromCharCode(...new Uint8Array(cred.response.attestationObject))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
    };

    const confirmRes = await fetch('/user/two-factor/enroll/confirm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor: 'passkey', proof, metadata: { name } }),
    });
    const confirmJson = await confirmRes.json();
    if (confirmJson.status === 'success') {
      alert('Passkey registered successfully!');
      router.reload();
    } else {
      alert(confirmJson.message || 'Passkey enrollment failed');
    }
  };

  const startRecoveryCodes = async () => {
    if (!confirm('Generating new recovery codes will invalidate prior codes. Continue?')) return;
    const res = await fetch('/user/two-factor/enroll/start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor: 'recovery_code' }),
    });
    const json = await res.json();
    if (json.status === 'success' && json.data?.data?.codes) {
      setRecoveryCodes(json.data.data.codes);
      setRecoveryModal(true);
    }
  };

  const unenroll = async (factor: string) => {
    if (!confirm(`Are you sure you want to remove ${factor.toUpperCase()}?`)) return;
    const res = await fetch('/user/two-factor/unenroll', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ factor }),
    });
    const json = await res.json();
    if (json.status === 'success') {
      router.reload();
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 p-6 sm:p-12">
      <div className="max-w-4xl mx-auto space-y-8">
        <div className="flex items-center justify-between border-b border-slate-800 pb-5">
          <div>
            <Link href="/dashboard" className="text-sm font-semibold text-blue-400 hover:text-blue-300">
              &larr; Back to Dashboard
            </Link>
            <h1 className="text-2xl font-bold text-white mt-1">Two-Factor Authentication & Sudo</h1>
            <p className="text-sm text-slate-400">Configure Multi-Factor Authentication and hardware security keys.</p>
          </div>
        </div>

        <div className="space-y-4">
          {/* TOTP */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-semibold text-white">Authenticator App (TOTP)</h3>
                <span
                  className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${
                    isEnrolled('totp')
                      ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
                      : 'bg-slate-800 text-slate-400 border-slate-700'
                  }`}
                >
                  {isEnrolled('totp') ? 'ENROLLED' : 'NOT CONFIGURED'}
                </span>
              </div>
              <p className="text-xs text-slate-400 mt-1">Use Google Authenticator or 1Password for 6-digit dynamic codes.</p>
            </div>
            <div>
              {isEnrolled('totp') ? (
                <button
                  onClick={() => unenroll('totp')}
                  className="px-4 py-2 bg-red-950/40 hover:bg-red-900/60 text-xs font-semibold text-red-400 rounded-xl border border-red-800/40"
                >
                  Remove TOTP
                </button>
              ) : (
                <button
                  onClick={startTotp}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-xs font-semibold text-white rounded-xl"
                >
                  Setup Authenticator
                </button>
              )}
            </div>
          </div>

          {/* Passkeys */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <h3 className="font-semibold text-white">Passkeys & Security Keys (WebAuthn)</h3>
              <p className="text-xs text-slate-400 mt-1">Log in with Touch ID, Face ID, Windows Hello, or YubiKeys.</p>
            </div>
            <button
              onClick={startPasskey}
              className="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-xs font-semibold text-white rounded-xl"
            >
              Add Passkey
            </button>
          </div>

          {/* Recovery Codes */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-semibold text-white">Emergency Recovery Codes</h3>
                <span
                  className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${
                    isEnrolled('recovery_code')
                      ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
                      : 'bg-slate-800 text-slate-400 border-slate-700'
                  }`}
                >
                  {isEnrolled('recovery_code') ? 'ACTIVE' : 'NOT GENERATED'}
                </span>
              </div>
              <p className="text-xs text-slate-400 mt-1">Single-use backup codes for emergency account access.</p>
            </div>
            <button
              onClick={startRecoveryCodes}
              className="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-xs font-semibold text-white rounded-xl"
            >
              {isEnrolled('recovery_code') ? 'Regenerate Codes' : 'Generate Codes'}
            </button>
          </div>
        </div>

        {/* TOTP Modal */}
        {totpModal && totpData && (
          <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
              <h3 className="text-lg font-bold text-white">Setup Authenticator App</h3>
              {totpData.qr_data_uri && (
                <div className="flex justify-center p-2 bg-white rounded-xl w-48 h-48 mx-auto items-center">
                  <img src={totpData.qr_data_uri} alt="TOTP QR Code" className="w-44 h-44 object-contain" />
                </div>
              )}
              <div className="text-center font-mono font-bold text-amber-400 text-sm">{totpData.secret}</div>
              <input
                type="text"
                value={totpCode}
                onChange={(e) => setTotpCode(e.target.value)}
                placeholder="123456"
                maxLength={6}
                className="w-full text-center text-xl tracking-widest px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white"
              />
              <div className="flex gap-3">
                <button
                  onClick={() => setTotpModal(false)}
                  className="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl"
                >
                  Cancel
                </button>
                <button
                  onClick={confirmTotp}
                  className="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
                >
                  Confirm & Activate
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Recovery Codes Modal */}
        {recoveryModal && (
          <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
              <h3 className="text-lg font-bold text-white">Emergency Recovery Codes</h3>
              <div className="bg-slate-950 p-4 rounded-xl border border-slate-800 grid grid-cols-2 gap-2 text-center font-mono text-sm text-amber-400">
                {recoveryCodes.map((c, i) => (
                  <div key={i}>{c}</div>
                ))}
              </div>
              <button
                onClick={() => {
                  setRecoveryModal(false);
                  router.reload();
                }}
                className="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl"
              >
                I Have Saved These Codes
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
