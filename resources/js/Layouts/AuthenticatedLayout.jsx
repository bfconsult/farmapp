import { Link, router, usePage } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function AuthenticatedLayout({ title, children }) {
    const { auth, properties, currentProperty, currentUserRole, flash } = usePage().props;
    const canViewReports = currentUserRole === 'admin' || currentUserRole === 'manager';

    const selectProperty = (e) => {
        router.post(route('property.select'), { property_id: e.target.value });
    };

    return (
        <div className="min-h-screen bg-gray-100 pb-16">
            {/* Top bar */}
            <nav className="bg-white border-b border-gray-200 fixed top-0 left-0 right-0 z-10">
                <div className="flex items-center justify-between px-4 h-14">
                    {/* Left: logo + page title */}
                    <div className="flex items-center gap-2">
                        <ApplicationLogo className="h-6 w-6" />
                        {title && (
                            <span className="text-gray-900 font-semibold text-base">{title}</span>
                        )}
                    </div>

                    {/* Right: property selector */}
                    {currentProperty ? (
                    <div className="relative flex items-center">
                        <select
                            onChange={selectProperty}
                            value={currentProperty?.id ?? ''}
                            style={{ 
                                appearance: 'none',
                                WebkitAppearance: 'none',
                                MozAppearance: 'none',
                                background: 'transparent',
                                border: 'none',
                                outline: 'none',
                                padding: '0',
                                paddingRight: '16px',
                                fontSize: '14px',
                                color: '#15803d',
                                fontWeight: '500',
                                cursor: 'pointer',
                            }}
                        >
                            {properties.map((property) => (
                                <option key={property.id} value={property.id}>
                                    {property.name}
                                </option>
                            ))}
                        </select>
                        <span className="pointer-events-none absolute right-0 text-green-700 text-xs">▾</span>
                    </div>
                   ) : (
                    properties.length > 0 ? (
                        <div className="relative flex items-center">
                            <select
                                onChange={selectProperty}
                                value=""
                                style={{
                                    appearance: 'none',
                                    WebkitAppearance: 'none',
                                    MozAppearance: 'none',
                                    background: 'transparent',
                                    border: 'none',
                                    outline: 'none',
                                    padding: '0',
                                    paddingRight: '16px',
                                    fontSize: '14px',
                                    color: '#6b7280',
                                    cursor: 'pointer',
                                }}
                            >
                                <option value="">Select Property</option>
                                {properties.map((property) => (
                                    <option key={property.id} value={property.id}>
                                        {property.name}
                                    </option>
                                ))}
                            </select>
                            <span className="pointer-events-none absolute right-0 text-gray-400 text-xs">▾</span>
                        </div>
                    ) : (
                        <Link
                            href={route('properties.create')}
                            className="text-sm text-green-600"
                        >
                            Add Property
                        </Link>
                    )
                )}
                </div>
            </nav>

            {/* Main content */}
            <main className="pt-14 px-4 pb-4">
                {flash?.error && (
                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        {flash.error}
                    </div>
                )}
                {flash?.success && (
                    <div className="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                        {flash.success}
                    </div>
                )}
                {children}
            </main>

            {/* Floating Add Job button — only where adding a job actually makes sense */}
            {currentProperty && (route().current('jobs.*') || route().current('map')) && (
                <Link
                    href={route('jobs.create')}
                    className="fixed bottom-20 right-4 z-20 bg-green-600 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg text-2xl hover:bg-green-700"
                >
                    +
                </Link>
            )}

            {/* Bottom navigation */}
            <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-10">
                <div className="flex justify-around items-center h-16">
                    <Link
                        href={route('work-sessions.index')}
                        className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${route().current('work-sessions.*') ? 'text-green-600' : 'text-gray-500'}`}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Work
                    </Link>

                    <Link
                        href={route('jobs.index')}
                        className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${route().current('jobs.*') ? 'text-green-600' : 'text-gray-500'}`}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Jobs
                    </Link>

                    <Link
                        href={route('map')}
                        className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${route().current('map') ? 'text-green-600' : 'text-gray-500'}`}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-9.293A1 1 0 014.5 9h15a1 1 0 01.894 1.707L15 20M9 20h6M12 9V4" />
                        </svg>
                        Map
                    </Link>

                    {canViewReports && (
                        <Link
                            href={route('reports.index')}
                            className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${route().current('reports.*') ? 'text-green-600' : 'text-gray-500'}`}
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14m0 0h2a2 2 0 002-2" />
                            </svg>
                            Reports
                        </Link>
                    )}

                    <Link
                        href={route('profile.edit')}
                        className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${route().current('profile.*') ? 'text-green-600' : 'text-gray-500'}`}
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Profile
                    </Link>
                </div>
            </nav>
        </div>
    );
}