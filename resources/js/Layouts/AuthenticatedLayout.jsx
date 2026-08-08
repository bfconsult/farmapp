import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';

export default function AuthenticatedLayout({ title, children }) {
    const { auth, properties, currentProperty, currentUserRole, hasIncompleteMetrics, flash } = usePage().props;
    const canViewReports = currentUserRole === 'admin' || currentUserRole === 'manager' || currentUserRole === 'approver';
    const canViewMetrics = currentUserRole === 'admin' || currentUserRole === 'manager' || currentUserRole === 'worker' || currentUserRole === 'approver';
    // Same as Profile/Edit's old "can add another property" rule: always
    // available with no current property to fall back to, otherwise
    // admin-only.
    const canAddProperty = !currentProperty || currentUserRole === 'admin';

    const [showPropertyMenu, setShowPropertyMenu] = useState(false);
    const [showChangeList, setShowChangeList] = useState(false);
    const propertyMenuRef = useRef(null);

    useEffect(() => {
        if (!showPropertyMenu) return undefined;

        const closeOnOutsideClick = (e) => {
            if (propertyMenuRef.current && !propertyMenuRef.current.contains(e.target)) {
                setShowPropertyMenu(false);
                setShowChangeList(false);
            }
        };
        document.addEventListener('mousedown', closeOnOutsideClick);
        return () => document.removeEventListener('mousedown', closeOnOutsideClick);
    }, [showPropertyMenu]);

    const togglePropertyMenu = () => {
        setShowChangeList(false);
        setShowPropertyMenu((v) => !v);
    };

    const selectProperty = (propertyId) => {
        setShowPropertyMenu(false);
        setShowChangeList(false);
        router.post(route('property.select'), { property_id: propertyId });
    };

    const addProperty = () => {
        setShowPropertyMenu(false);
        setShowChangeList(false);
        router.post(route('properties.store'));
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

                    {/* Right: property picker. With only one property there's
                        nothing to switch to, so the name is a plain link
                        straight to its settings page. Otherwise it opens a
                        small menu (Settings / Change) instead, since "go to
                        settings" and "switch property" are both plausible
                        things to want from tapping the name and a single
                        link/arrow split was easy to hit by mistake. */}
                    {properties.length === 0 ? (
                        <button onClick={addProperty} className="text-sm text-green-600">
                            Add Property
                        </button>
                    ) : properties.length === 1 && currentProperty ? (
                        <Link
                            href={route('properties.show', currentProperty.id)}
                            className="text-sm font-medium text-green-700"
                        >
                            {currentProperty.name}
                        </Link>
                    ) : (
                        <div className="relative flex items-center" ref={propertyMenuRef}>
                            <button
                                type="button"
                                onClick={togglePropertyMenu}
                                aria-expanded={showPropertyMenu}
                                className={`text-sm font-medium ${currentProperty ? 'text-green-700' : 'text-gray-500'}`}
                            >
                                {currentProperty ? currentProperty.name : 'Select Property'}
                                <span className="ml-1 text-xs">▾</span>
                            </button>

                            {showPropertyMenu && (
                                <div className="absolute right-0 top-full mt-1 w-52 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-20">
                                    {currentProperty && (
                                        <Link
                                            href={route('properties.show', currentProperty.id)}
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                        >
                                            Settings
                                        </Link>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setShowChangeList((v) => !v)}
                                        aria-expanded={showChangeList}
                                        className="flex w-full items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                    >
                                        Change
                                        <span className="text-xs">{showChangeList ? '▴' : '▾'}</span>
                                    </button>
                                    {showChangeList && (
                                        <div className="pb-1">
                                            {properties
                                                .filter((property) => property.id !== currentProperty?.id)
                                                .map((property) => (
                                                    <button
                                                        key={property.id}
                                                        type="button"
                                                        onClick={() => selectProperty(property.id)}
                                                        className="block w-full py-2 pl-8 pr-4 text-left text-sm text-gray-700 hover:bg-gray-50"
                                                    >
                                                        {property.name}
                                                    </button>
                                                ))}
                                            {canAddProperty && (
                                                <button
                                                    type="button"
                                                    onClick={addProperty}
                                                    className="block w-full py-2 pl-8 pr-4 text-left text-sm text-green-600"
                                                >
                                                    + Add Property
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
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

            {/* Floating Add Job button — Jobs itself now has its own dashed
                "+ Add Job" button at the top of the list instead; the Map
                page has no such list to put one in, so it keeps the FAB. */}
            {currentProperty && route().current('map') && (
                <Link
                    href={route('jobs.create')}
                    className="fixed bottom-20 right-4 z-[1100] bg-green-600 text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg text-2xl hover:bg-green-700"
                >
                    +
                </Link>
            )}

            {/* Bottom navigation. z-[1100] (not the usual z-10) because
                Leaflet's own .leaflet-top/.leaflet-bottom control panes are
                z-index:1000 and, since .leaflet-container never sets its own
                z-index, that 1000 competes directly against this nav rather
                than staying scoped inside the map - on a page where content
                above an embedded map (e.g. Map.jsx's "no boundary"/"no
                jobs" banners) pushes the map down far enough, Leaflet's
                controls would otherwise paint over this nav and steal its
                clicks. */}
            <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-[1100]">
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

                    {canViewMetrics && (
                        <Link
                            href={route('manage.index')}
                            className={`flex flex-col items-center text-xs gap-1 px-4 py-2 ${
                                route().current('manage.*') || route().current('metrics.*') || route().current('metric-measurements.*') || route().current('checklist-templates.*') || route().current('checklists.*') || route().current('checklist-items.*')
                                    ? 'text-green-600'
                                    : 'text-gray-500'
                            }`}
                        >
                            <span className="relative">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6" />
                                </svg>
                                {hasIncompleteMetrics && (
                                    <span className="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full" />
                                )}
                            </span>
                            Manage
                        </Link>
                    )}

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
                        Account
                    </Link>
                </div>
            </nav>
        </div>
    );
}