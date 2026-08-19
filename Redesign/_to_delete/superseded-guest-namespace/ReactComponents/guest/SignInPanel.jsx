/**
 * Passwordless sign-in for the guest sidebar: email -> 6-digit code -> name and phone.
 *
 * The three steps are driven by `session.step` from useGuestSession, so a cached
 * session that still needs a profile lands straight on the profile step.
 */

import { useEffect, useState } from 'react';
import { AttentionBox, Button, Icon, TextField } from '../ds';

export function SignInPanel({ session, companyName }) {
    const { step, busy, error, sendCode, verifyCode, saveProfile, setError, user } = session;

    const [email, setEmail] = useState(user.email || '');
    const [otp, setOtp] = useState('');
    const [name, setName] = useState(user.name || '');
    const [phone, setPhone] = useState(user.phone || '');

    // Once the server tells us who they are, seed the profile fields.
    useEffect(() => {
        if (user.email) setEmail(user.email);
        if (user.name) setName(user.name);
        if (user.phone) setPhone(user.phone);
    }, [user.email, user.name, user.phone]);

    const heading = step === 'profile' ? 'A couple of details' : 'Sign in to propose';
    const intro =
        step === 'profile'
            ? 'These go on every proposal you send, so the team knows who to reply to.'
            : "No password needed — enter your email and we'll send a 6-digit code.";

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
                    {heading}
                </h2>
            </div>
            <p
                style={{
                    marginBottom: 16,
                    font: 'var(--font-text2-normal)',
                    color: 'var(--secondary-text-color)',
                }}
            >
                {intro}
            </p>

            {error ? (
                <div style={{ marginBottom: 12 }}>
                    <AttentionBox type="danger" onClose={() => setError('')}>
                        {error}
                    </AttentionBox>
                </div>
            ) : null}

            {step === 'loading' ? (
                <div style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                    Checking your session…
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
            ) : null}

            {step === 'otp' ? (
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
                                session.setStep('email');
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
            ) : null}

            {step === 'profile' ? (
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
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            font: 'var(--font-text3-medium)',
                            color: 'var(--positive-color)',
                        }}
                    >
                        <Icon name="Check" size={14} color="var(--positive-color)" />
                        <span>Email verified</span>
                    </div>
                    <TextField
                        label="Full name"
                        placeholder="Your name"
                        iconName="Person"
                        autoComplete="name"
                        required
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <TextField
                        label="Phone number"
                        placeholder="+61 4xx xxx xxx"
                        iconName="Mobile"
                        autoComplete="tel"
                        required
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && saveProfile({ name, phone })}
                    />
                    <Button fullWidth loading={busy} onClick={() => saveProfile({ name, phone })}>
                        Save and continue
                    </Button>
                </div>
            ) : null}

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
