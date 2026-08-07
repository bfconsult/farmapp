import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

function Section({ title, children }) {
    return (
        <section className="mb-8">
            <h2 className="text-lg font-semibold text-gray-900 mb-2">{title}</h2>
            <div className="text-sm leading-relaxed text-gray-700 space-y-3">{children}</div>
        </section>
    );
}

// Named generically (not "PrivacyPolicy") - Brave's built-in cookie-consent-
// notice blocking (and similar filter lists) blocks resources whose path
// matches common privacy/consent page names, which silently prevented this
// chunk from ever loading. The rendered content/title can still say
// "Privacy Policy" - only the file/component name needed to change.
export default function Legal() {
    return (
        <>
            <Head title="Privacy Policy" />
            <div className="min-h-screen bg-gray-100">
                <header className="border-b border-gray-200 bg-white">
                    <div className="mx-auto flex max-w-3xl items-center gap-2 px-6 py-4">
                        <Link href="/" className="flex items-center gap-2">
                            <ApplicationLogo className="h-8 w-8" />
                            <span className="text-lg font-black tracking-tight text-gray-800">
                                Field<span className="text-[#1A5C38]">Werkz</span>
                            </span>
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-3xl px-6 py-12 bg-white sm:rounded-lg sm:shadow sm:my-8">
                    <h1 className="text-3xl font-bold text-gray-900 mb-1">Privacy Policy</h1>
                    <p className="text-sm text-gray-400 mb-8">Last updated: 7 August 2026</p>

                    <Section title="Who we are">
                        <p>
                            FieldWerkz ("we", "us", "our") is a job and work-tracking tool for farms and
                            properties. This policy explains what information we collect through the FieldWerkz
                            web app and mobile companion app, and how we use it.
                        </p>
                    </Section>

                    <Section title="Information we collect">
                        <p><strong>Account information</strong> — your name, email address, and password (stored as a secure hash, never in plain text).</p>
                        <p><strong>Property and job data</strong> — the properties, jobs, work sessions, checklists, metrics, maintenance items, assets, and notes that you or your team create.</p>
                        <p><strong>Location data</strong> — GPS coordinates recorded when you start or stop a work session, when you or your team set a location for a job, asset, or note, and — if you turn on automatic tracking in the FieldWerkz mobile app — in the background while the app isn't open, so it can detect when you arrive at or leave a property.</p>
                        <p><strong>Photos</strong> — images you choose to upload against a job, checklist item, work session, or note.</p>
                        <p><strong>Team information</strong> — invitations you send, and the roles assigned to people on your property's team.</p>
                    </Section>

                    <Section title="Cookies">
                        <p>
                            FieldWerkz only uses cookies that are strictly necessary for the site to work: a
                            session cookie that keeps you logged in, and a CSRF cookie that protects your account
                            from cross-site request forgery. We do not use advertising cookies, third-party
                            analytics/tracking cookies, or sell any cookie data.
                        </p>
                    </Section>

                    <Section title="How we use your information">
                        <p>We use the information above to:</p>
                        <ul className="list-disc list-inside space-y-1">
                            <li>Operate the core features of FieldWerkz — tracking jobs, work sessions, and where work happens.</li>
                            <li>Let members of your property's team see and collaborate on shared jobs, checklists, and notes.</li>
                            <li>Keep your account secure (e.g. detecting suspicious activity).</li>
                            <li>Send you service-related communications, such as team invitations.</li>
                        </ul>
                    </Section>

                    <Section title="Location data specifically">
                        <p>
                            Because FieldWerkz is built for field work, location data is a core part of the
                            product. It's recorded when you or a teammate explicitly place a pin for a job, asset,
                            or note, and during any work session, whether logged manually in the web app or
                            started automatically by the mobile app.
                        </p>
                        <p>
                            <strong>Automatic background tracking (mobile app only).</strong> If you turn on
                            tracking in the FieldWerkz mobile app, it monitors your location — including while the
                            app is closed or your phone is locked — to automatically detect when you enter or
                            leave a property boundary your team has set up, and to record a periodic location
                            trail while you're on-site. This is what lets a work session start and stop without
                            you having to open the app. You can turn tracking off at any time from the app's Home
                            screen, and restrict it to only run during set hours from Settings → Schedule.
                            Automatic tracking only monitors properties belonging to teams you're a member of — it
                            does not track your location anywhere else.
                        </p>
                        <p>
                            Recorded locations are visible to your property's team, according to their role, and
                            are not shared outside your property or used for advertising.
                        </p>
                    </Section>

                    <Section title="Sharing your information">
                        <p>
                            We don't sell your information or use it for advertising. We share it only with:
                        </p>
                        <ul className="list-disc list-inside space-y-1">
                            <li>Other members of your property's team, based on their assigned role.</li>
                            <li>Service providers who help us run FieldWerkz — currently our hosting/storage provider (AWS) and our transactional email provider (Resend), used only to deliver the service, not for marketing.</li>
                        </ul>
                    </Section>

                    <Section title="Data retention">
                        <p>
                            We keep your information for as long as your account or property remains active on
                            FieldWerkz. You can ask us to delete your account and associated data at any time by
                            contacting us — see below.
                        </p>
                    </Section>

                    <Section title="Your rights">
                        <p>
                            You can ask us to access, correct, or delete the personal information we hold about
                            you by contacting us using the details on our{' '}
                            <Link href={route('contact')} className="text-green-700 hover:underline">Contact page</Link>.
                        </p>
                    </Section>

                    <Section title="Children's privacy">
                        <p>
                            FieldWerkz is a workplace tool and isn't directed at children. We don't knowingly
                            collect information from children.
                        </p>
                    </Section>

                    <Section title="Changes to this policy">
                        <p>
                            If we change what we collect or how we use it, we'll update this page and change the
                            "Last updated" date above.
                        </p>
                    </Section>

                    <Section title="Contact us">
                        <p>
                            Questions about this policy or your data? Get in touch via our{' '}
                            <Link href={route('contact')} className="text-green-700 hover:underline">Contact page</Link>.
                        </p>
                    </Section>
                </main>

                <footer className="border-t border-gray-200 py-8 text-center text-sm text-gray-500">
                    <p>&copy; {new Date().getFullYear()} FieldWerkz</p>
                    <p className="mt-2 space-x-3">
                        <Link href="/" className="hover:text-green-700 hover:underline">Home</Link>
                        <span className="text-gray-300">·</span>
                        <Link href={route('contact')} className="hover:text-green-700 hover:underline">Contact</Link>
                    </p>
                </footer>
            </div>
        </>
    );
}
