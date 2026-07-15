import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

const features = [
    {
        title: 'Job Tracking',
        description:
            'Create, assign, and track farm jobs from start to finish, with priorities and statuses that fit how your team works.',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            />
        ),
    },
    {
        title: 'Property Mapping',
        description:
            'See jobs plotted against your property boundaries on an interactive map, so you always know what needs doing and where.',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 20l-5.447-9.293A1 1 0 014.5 9h15a1 1 0 01.894 1.707L15 20M9 20h6M12 9V4"
            />
        ),
    },
    {
        title: 'Work Sessions',
        description:
            'Clock in on a job from the field, capture photos as you go, and clock out when it is done. Simple time tracking that keeps up with the work.',
        icon: (
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
            />
        ),
    },
];

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Fieldwerkz" />
            <div className="min-h-screen bg-gray-100">
                <header className="border-b border-gray-200 bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2">
                            <ApplicationLogo className="h-8 w-8" />
                            <span className="text-lg font-black tracking-tight text-gray-800">
                                Farm<span className="text-[#1A5C38]">Task</span>
                            </span>
                        </div>

                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('jobs.index')}
                                    className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                >
                                    Go to Jobs
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="text-sm font-medium text-gray-700 hover:text-green-700"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto max-w-6xl px-6 py-20 text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                            Run your farm's day-to-day from one place
                        </h1>
                        <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
                            Fieldwerkz helps you plan jobs, track work across your
                            properties, and see everything happening on the
                            map — built for teams working out in the field.
                        </p>
                        {!auth.user && (
                            <div className="mt-8 flex justify-center gap-4">
                                <Link
                                    href={route('register')}
                                    className="rounded-md bg-green-600 px-6 py-3 text-sm font-semibold text-white hover:bg-green-700"
                                >
                                    Get Started
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-md border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                                >
                                    Log in
                                </Link>
                            </div>
                        )}
                    </section>

                    <section className="border-t border-gray-200 bg-white">
                        <div className="mx-auto max-w-6xl px-6 py-16">
                            <div className="grid gap-8 sm:grid-cols-3">
                                {features.map((feature) => (
                                    <div key={feature.title} className="text-center sm:text-left">
                                        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-700 sm:mx-0">
                                            <svg
                                                className="h-6 w-6"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                {feature.icon}
                                            </svg>
                                        </div>
                                        <h2 className="mt-4 text-lg font-semibold text-gray-900">
                                            {feature.title}
                                        </h2>
                                        <p className="mt-2 text-sm leading-relaxed text-gray-600">
                                            {feature.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-t border-gray-200 py-8 text-center text-sm text-gray-500">
                    &copy; {new Date().getFullYear()} Fieldwerkz
                </footer>
            </div>
        </>
    );
}
