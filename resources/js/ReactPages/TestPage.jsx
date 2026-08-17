import { useState } from 'react';
import { Head } from '@inertiajs/react';

// Monday.com-vibe status colours, pulled from Redesign/proposal design tokens
// (Redesign/proposal/_ds/.../tokens/colors.css) so this test page previews
// the same palette the redesign is aiming for.
const statusPills = [
    { label: 'Done', color: '#00c875' },
    { label: 'Working on it', color: '#fdab3d' },
    { label: 'Stuck', color: '#df2f4a' },
];

export default function TestPage({ message }) {
    const [count, setCount] = useState(0);

    return (
        <>
            <Head title="React Test Page" />

            <div className="min-h-screen bg-[#f6f7fb] font-sans text-[#323338]">
                <header className="flex items-center justify-between border-b border-[#d0d4e4] bg-white px-8 py-4">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[#1a73e8] font-semibold text-white">
                            R
                        </span>
                        <h1 className="text-lg font-semibold">React Test Page</h1>
                    </div>

                    {/* Plain <a>, not Inertia's <Link> — this hops back into the Vue
                        app, so it needs a full page load, not an SPA navigation. */}
                    <a
                        href="/dashboard"
                        className="text-sm font-medium text-[#1a73e8] hover:text-[#1560c4]"
                    >
                        ← Back to Vue dashboard
                    </a>
                </header>

                <main className="mx-auto max-w-3xl space-y-8 px-8 py-10">
                    <section className="rounded-lg border border-[#d0d4e4] bg-white p-6 shadow-sm">
                        <h2 className="mb-2 text-base font-semibold">It&apos;s alive</h2>
                        <p className="text-sm text-[#676879]">
                            This page is rendered entirely by React 19 + Inertia, running side by side with
                            the existing Vue 3 pages in this app. {message}
                        </p>
                    </section>

                    <section className="rounded-lg border border-[#d0d4e4] bg-white p-6 shadow-sm">
                        <h2 className="mb-4 text-base font-semibold">React state works</h2>
                        <div className="flex items-center gap-4">
                            <button
                                type="button"
                                onClick={() => setCount((c) => c - 1)}
                                className="h-9 w-9 rounded-md border border-[#d0d4e4] text-lg leading-none hover:bg-[#f0f6ff]"
                            >
                                −
                            </button>
                            <span className="w-10 text-center text-2xl font-semibold tabular-nums">
                                {count}
                            </span>
                            <button
                                type="button"
                                onClick={() => setCount((c) => c + 1)}
                                className="h-9 w-9 rounded-md border border-[#d0d4e4] text-lg leading-none hover:bg-[#f0f6ff]"
                            >
                                +
                            </button>
                        </div>
                    </section>

                    <section className="rounded-lg border border-[#d0d4e4] bg-white p-6 shadow-sm">
                        <h2 className="mb-4 text-base font-semibold">Monday-vibe status pills</h2>
                        <div className="flex gap-2">
                            {statusPills.map((pill) => (
                                <span
                                    key={pill.label}
                                    className="rounded px-3 py-1 text-sm font-medium text-white"
                                    style={{ backgroundColor: pill.color }}
                                >
                                    {pill.label}
                                </span>
                            ))}
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
