import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

const CONTACT_EMAIL = 'hello@fieldwerkz.com';

export default function Contact() {
    return (
        <>
            <Head title="Contact" />
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

                <main className="mx-auto max-w-3xl px-6 py-12 bg-white sm:rounded-lg sm:shadow sm:my-8 text-center">
                    <h1 className="text-3xl font-bold text-gray-900 mb-4">Contact Us</h1>
                    <p className="text-sm text-gray-600 mb-6">
                        Questions, feedback, or support requests — we'd like to hear from you.
                    </p>
                    <a
                        href={`mailto:${CONTACT_EMAIL}`}
                        className="inline-block rounded-md bg-green-600 px-6 py-3 text-sm font-semibold text-white hover:bg-green-700"
                    >
                        {CONTACT_EMAIL}
                    </a>
                </main>

                <footer className="border-t border-gray-200 py-8 text-center text-sm text-gray-500">
                    <p>&copy; {new Date().getFullYear()} FieldWerkz</p>
                    <p className="mt-2 space-x-3">
                        <Link href="/" className="hover:text-green-700 hover:underline">Home</Link>
                        <span className="text-gray-300">·</span>
                        <Link href={route('privacy-policy')} className="hover:text-green-700 hover:underline">Privacy Policy</Link>
                    </p>
                </footer>
            </div>
        </>
    );
}
