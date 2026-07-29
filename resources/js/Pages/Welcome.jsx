import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import SiteNotice from '@/Components/SiteNotice';

// Design handoff: design_handoff_marketing_page/README.md (2026-07-29).
// The referenced high-fidelity HTML reference wasn't included in the
// handoff zip - only the README - so layout/spacing choices below are
// built from its section-by-section description, not pixel-matched to
// an actual comp. Photo/screenshot/avatar placeholders throughout are
// exactly that: placeholders, per the README's own "Assets Needed"
// section - swap for the real assets listed there before shipping.

const steps = [
    {
        number: '01',
        title: 'Owner records the job',
        description: 'Add what needs doing and which property it’s on - takes a minute, done from anywhere.',
    },
    {
        number: '02',
        title: 'Contractor clocks in and gets on with it',
        description: 'The job’s already there waiting. Clock in, add notes and photos as they go, clock out when it’s done.',
    },
    {
        number: '03',
        title: 'Owner sees it’s done',
        description: 'No phone calls needed - the job list updates itself as the work happens.',
    },
];

const checklistItems = [
    'Keep track of the backlog - never forget a job',
    'Time and notes logged as you go - no paperwork at the end of the day',
    'Send your clients full reports with photos and time spent – no need for those phone calls.',
];

const featureCards = [
    {
        title: 'Jobs, recorded once',
        description: 'Create a job, assign it, and it’s visible to everyone who needs it - no re-typing the same thing into a text message.',
    },
    {
        title: 'Every property, mapped',
        description: 'See jobs plotted against real property boundaries, so it’s always clear what needs doing and where.',
    },
    {
        title: 'Work sessions',
        description: 'Simple time tracking that fits how field work actually happens - start it, work, stop it.',
    },
];

// Placeholder testimonials pending real customer quotes - see "Assets
// Needed" in the design handoff README.
const testimonials = [
    {
        quote: 'How many times have I looked at the same job and made a "mental note" which somehow got lost. Now I just pin it in Fieldwerkz and get someone to do it.',
        name: 'Property owner',
        role: 'Grazier - Mornington Peninsula',
    },
    {
        quote: 'I just want to get on with the job, recording time and reporting back to clients just seems like a hassle. Fieldwerkz nails all that for me.',
        name: 'Contractor',
        role: 'Farm and stock management',
    },
];

function CheckIcon() {
    return (
        <svg className="h-6 w-6 flex-shrink-0 text-[#1a5c38]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    );
}

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="FieldWerkz">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Inter:wght@400;500;600;700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-[#f6f1e7] font-body">
                <header className="border-b border-[#e4dbc8]">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-3">
                            <ApplicationLogo className="h-9 w-9 rounded-lg" />
                            <span className="font-display text-lg font-extrabold tracking-tight text-[#22271f]">
                                Field<span className="font-black">Werkz</span>
                            </span>
                        </div>

                        <nav className="flex items-center gap-8">
                            <a href="#how-it-works" className="hidden text-sm font-medium text-[#5b5b4d] hover:text-[#22271f] sm:inline">
                                How it works
                            </a>
                            <a href="#features" className="hidden text-sm font-medium text-[#5b5b4d] hover:text-[#22271f] sm:inline">
                                Features
                            </a>
                            {!auth.user && (
                                <Link href={route('login')} className="text-sm font-medium text-[#5b5b4d] hover:text-[#22271f]">
                                    Log in
                                </Link>
                            )}
                            <Link
                                href={route(auth.user ? 'jobs.index' : 'register')}
                                className="rounded-full bg-[#c1622d] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#a8531f]"
                            >
                                {auth.user ? 'Go to Jobs' : 'Get Started'}
                            </Link>
                        </nav>
                    </div>
                </header>

                <SiteNotice />

                <main>
                    <section className="relative overflow-hidden bg-[#22271f]">
                        <img
                            src="/images/marketing/hero-photo.jpg"
                            alt=""
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 bg-gradient-to-r from-[#22271f] via-[#22271f]/90 to-[#1a5c38]/40" />
                        <div className="relative mx-auto max-w-6xl px-6 py-24 sm:py-32">
                            <p className="font-display text-sm font-bold uppercase tracking-widest text-[#e7c9a0]">
                                For contractors and property owners
                            </p>
                            <h1 className="mt-4 max-w-2xl font-display text-4xl font-black leading-tight text-white sm:text-5xl">
                                The easiest way to track jobs and keep everyone in the loop.
                            </h1>
                            <p className="mt-6 max-w-xl text-lg text-[#b9bcae]">
                                FieldWerkz gives property owners a simple way to hand off work, and
                                contractors a simple way to get it done - one job list and one time
                                record, shared across every property.
                            </p>
                            <div className="mt-10 flex flex-wrap gap-4">
                                <Link
                                    href={route('register')}
                                    className="rounded-full bg-[#c1622d] px-7 py-3.5 text-base font-semibold text-white hover:bg-[#a8531f]"
                                >
                                    Get Started Free
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-full border border-white/30 px-7 py-3.5 text-base font-semibold text-white hover:bg-white/10"
                                >
                                    Log in
                                </Link>
                            </div>
                        </div>
                    </section>

                    {/* How it works */}
                    <section id="how-it-works" className="py-24">
                        <div className="mx-auto max-w-6xl px-6">
                            <h2 className="font-display text-3xl font-extrabold text-[#22271f] sm:text-4xl">
                                How it works
                            </h2>
                            <div className="mt-14 grid gap-12 sm:grid-cols-3">
                                {steps.map((step) => (
                                    <div key={step.number}>
                                        <span className="font-display text-6xl font-black text-[#d8c9a8]">{step.number}</span>
                                        <h3 className="mt-4 font-display text-xl font-bold text-[#22271f]">{step.title}</h3>
                                        <p className="mt-2 text-[#5b5b4d]">{step.description}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* In the field */}
                    <section className="bg-white py-24">
                        <div className="mx-auto grid max-w-6xl gap-16 px-6 sm:grid-cols-2 sm:items-center">
                            <div>
                                <h2 className="font-display text-3xl font-extrabold text-[#22271f] sm:text-4xl">
                                    Built for work in the field
                                </h2>
                                <p className="mt-4 text-lg text-[#5b5b4d]">
                                    No office, no desk, no problem - FieldWerkz is built to be used
                                    standing in a paddock with one hand.
                                </p>
                                <ul className="mt-8 space-y-4">
                                    {checklistItems.map((item) => (
                                        <li key={item} className="flex items-start gap-3">
                                            <CheckIcon />
                                            <span className="text-[#22271f]">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="flex justify-center">
                                <div className="aspect-[9/19] w-64 overflow-hidden rounded-[2.5rem] border-8 border-[#22271f] bg-[#f6f1e7] shadow-xl">
                                    <img
                                        src="/images/marketing/app-screenshot.png"
                                        alt="An auto-tracked work session on FieldWerkz, showing the GPS trail across several named paddocks"
                                        className="h-full w-full object-cover object-top"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Features */}
                    <section id="features" className="bg-[#22271f] py-24">
                        <div className="mx-auto max-w-6xl px-6">
                            <h2 className="font-display text-3xl font-extrabold text-white sm:text-4xl">
                                Everything in one place
                            </h2>
                            <div className="mt-14 grid gap-6 sm:grid-cols-3">
                                {featureCards.map((card) => (
                                    <div key={card.title} className="rounded-2xl border border-[#3a4132] bg-[#2c3226] p-8">
                                        <h3 className="font-display text-xl font-bold text-white">{card.title}</h3>
                                        <p className="mt-3 text-[#b9bcae]">{card.description}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Testimonials */}
                    <section className="py-24">
                        <div className="mx-auto max-w-6xl px-6">
                            <h2 className="font-display text-3xl font-extrabold text-[#22271f] sm:text-4xl">
                                What people are saying
                            </h2>
                            <div className="mt-14 grid gap-8 sm:grid-cols-2">
                                {testimonials.map((t) => (
                                    <div key={t.name} className="rounded-2xl border border-[#e4dbc8] bg-white p-8">
                                        <p className="text-lg text-[#22271f]">&ldquo;{t.quote}&rdquo;</p>
                                        <div className="mt-6 flex items-center gap-3">
                                            {/* Avatar placeholder - swap for a real portrait photo */}
                                            <div className="h-10 w-10 rounded-full bg-[#e4dbc8]" />
                                            <div>
                                                <p className="font-semibold text-[#22271f]">{t.name}</p>
                                                <p className="text-sm text-[#8a8a78]">{t.role}</p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* CTA banner - headline updated from the handoff draft
                        ("Ready to stop chasing updates?") per the README's own
                        tone-of-voice rule: avoid surveillance/chasing language. */}
                    <section className="pb-24">
                        <div className="mx-auto max-w-6xl px-6">
                            <div className="rounded-3xl bg-[#1a5c38] px-8 py-16 text-center sm:px-16">
                                <h2 className="font-display text-3xl font-extrabold text-white sm:text-4xl">
                                    Ready to keep everyone in the loop?
                                </h2>
                                <p className="mx-auto mt-4 max-w-xl text-lg text-white/80">
                                    Get set up in a few minutes - free to start, no credit card needed.
                                </p>
                                <Link
                                    href={route('register')}
                                    className="mt-8 inline-block rounded-full bg-[#c1622d] px-7 py-3.5 text-base font-semibold text-white hover:bg-[#a8531f]"
                                >
                                    Get Started Free
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-[#e4dbc8] py-8 text-center text-sm text-[#8a8a78]">
                    <p>&copy; {new Date().getFullYear()} FieldWerkz</p>
                    <p className="mt-2 space-x-3">
                        <Link href={route('privacy-policy')} className="hover:text-[#1a5c38] hover:underline">Privacy Policy</Link>
                        <span className="text-[#d8c9a8]">·</span>
                        <Link href={route('contact')} className="hover:text-[#1a5c38] hover:underline">Contact</Link>
                    </p>
                </footer>
            </div>
        </>
    );
}
