/**
 * Passwordless sign-in for the share link: email, then a 6-digit code.
 *
 * Only ever rendered on the signed-out project page. Once the code checks out the
 * server sets the portal session cookie and the browser is sent to the real project
 * URL, so there is no profile step here any more — that lives on /portal/profile.
 */

import { useState } from 'react';
import { AttentionBox, Button, Icon, TextField } from '../ds';

export function SignInPanel({ signIn, companyName }) {
    const { step, busy, error, setError, sendCode, verifyCode } = signIn;

    const [email, setEmail] = useState(signIn.email || '');
    const [otp, setOtp] = useState('');

    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                padding: 20,
                animation: 'dcPop var(--motion-expressive-short) var(--motion-timing-emphasize) both',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                <Icon name="Security" size={20} color="var(--primary-color)" />
                <h2 style={{ font: 'var(--font-h3-medium)', letterSpacing: 'var(--letter-spacing-h3-normal)' }}>
                    Sign in to propose
                </h2>
            </div>
            <p style={{ marginBottom: 16, font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                No password needed — enter your email and we'll send a 6-digit code. If you already have an account
                here, we'll recognise it.
            </p>

            {error ? (
                <div style={{ marginBottom: 12 }}>
                    <AttentionBox type="danger" onClose={() => setError('')}>
                        {error}
                    </AttentionBox>
                </div>
            ) : null}

            {step === 'email' ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <TextField
                        label="Email address"
                        placeholder="you@example.com"
                        iconName="Email"
                        type="email"
                        autoComplete="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && sendCode(email)}
                    />
                    <Button fullWidth loading={busy} onClick={() => sendCode(email)}>
                        Send code
                    </Button>
                </div>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 12,
                        animation: 'dcSlideIn var(--motion-expressive-short) var(--motion-timing-enter) both',
                    }}
                >
                    <div
                        style={{
                            padding: '8px 10px',
                            borderRadius: 'var(--border-radius-small)',
                            background: 'var(--primary-highlighted-color)',
                            font: 'var(--font-text3-normal)',
                            color: 'var(--primary-text-color)',
                            animation: 'dcPulseRing 900ms var(--motion-timing-transition) 1',
                        }}
                    >
                        Code sent to {email}
                    </div>
                    <TextField
                        label="6-digit code"
                        placeholder="000000"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        value={otp}
                        onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                        onKeyDown={(e) => e.key === 'Enter' && verifyCode(email, otp)}
                        style={{ letterSpacing: 8, fontWeight: 600 }}
                    />
                    <Button fullWidth loading={busy} onClick={() => verifyCode(email, otp)}>
                        Verify and continue
                    </Button>
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            gap: 8,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <a
                            href="#change-email"
                            onClick={(e) => {
                                e.preventDefault();
                                setOtp('');
                                setError('');
                                signIn.setStep('email');
                            }}
                        >
                            Use a different email
                        </a>
                        <a
                            href="#resend"
                            onClick={(e) => {
                                e.preventDefault();
                                setOtp('');
                                sendCode(email);
                            }}
                        >
                            Resend code
                        </a>
                    </div>
                    <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                        Codes expire after 10 minutes.
                    </div>
                </div>
            )}

            <div
                style={{
                    marginTop: 16,
                    paddingTop: 12,
                    borderTop: '1px solid var(--om-hairline)',
                    font: 'var(--font-text3-normal)',
                    color: 'var(--secondary-text-color)',
                }}
            >
                Your details are only visible to the {companyName || 'project'} team running this project.
            </div>
        </section>
    );
}
