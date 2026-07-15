import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import HelpTip from '@/Components/HelpTip';
import { Head, Link, router, usePage } from '@inertiajs/react';

const STATUS_LABELS = {
    draft: 'Draft',
    finalised: 'Finalised',
    approved: 'Approved',
};

const STATUS_COLORS = {
    draft: 'bg-gray-100 text-gray-600',
    finalised: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
};

function sessionLabel(session) {
    if (session.farm_job) return session.farm_job.name;
    return session.source === 'auto_tracked' ? 'Auto-tracked visit' : 'Ad-hoc work';
}

export default function Index({ sessions, activeSession }) {
    const { currentProperty } = usePage().props;

    const stop = () => {
        router.post(route('work-sessions.stop', activeSession.id));
    };

    const formatTime = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (datetime) => {
        if (!datetime) return '—';
        return new Date(datetime).toLocaleDateString();
    };

    return (
        <AuthenticatedLayout title="Jobs">
            <Head title="Work" />

           
            <div className="max-w-lg mx-auto mt-2">
 

                {/* Active session banner */}
                {activeSession && (
                    <div className="bg-green-600 rounded-lg p-4 text-white mb-4">
                        <p className="text-sm font-medium mb-1">⏱ Session in progress</p>
                        <p className="text-base font-semibold mb-3">
                            {sessionLabel(activeSession)}
                        </p>
                        <p className="text-sm mb-3 opacity-90">
                            Started at {formatTime(activeSession.started_at)}
                        </p>
                        <div className="flex gap-2">
                            <button
                                onClick={stop}
                                className="flex-1 py-2 bg-white text-green-600 rounded-lg font-medium text-sm"
                            >
                                Stop Work
                            </button>
                            <Link
                                href={route('work-sessions.show', activeSession.id)}
                                className="flex-1 py-2 border border-white text-white rounded-lg font-medium text-sm text-center"
                            >
                                View
                            </Link>
                        </div>
                    </div>
                )}

                {/* Start new session button */}
                {!activeSession && (
                    <Link
                        href={route('work-sessions.create')}
                        className="block w-full py-4 bg-green-600 text-white rounded-lg text-base font-medium text-center mb-4"
                    >
                        Start Work Session
                    </Link>
                )}

                {/* Finalise & share / export */}
                <div className="flex items-center justify-end gap-3 mb-3">
                    <div className="flex items-center gap-1 px-3 py-2 bg-white rounded-lg shadow">
                        <Link
                            href={route('work-sessions.finalise-and-share')}
                            className="text-sm font-medium text-green-600"
                        >
                            Finalise & Share →
                        </Link>
                        <HelpTip messageKey="work-sessions.finalise-and-share" />
                    </div>
                    <Link
                        href={route('work-sessions.export')}
                        className="px-3 py-2 bg-white rounded-lg shadow text-sm font-medium text-green-600"
                    >
                        Export →
                    </Link>
                </div>

                {/* Sessions list */}
                {sessions.length === 0 ? (
                    <div className="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                        No work sessions yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {sessions.map((session) => (
                            <Link
                                key={session.id}
                                href={route('work-sessions.show', session.id)}
                                className="block bg-white rounded-lg shadow p-4 hover:bg-gray-50"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-medium text-gray-900">
                                            {sessionLabel(session)}
                                        </p>
                                        <p className="text-sm text-gray-500 mt-1">
                                            {formatDate(session.started_at)} · {formatTime(session.started_at)} — {formatTime(session.ended_at)}
                                        </p>
                                        {session.description && (
                                            <p className="text-sm text-gray-500 mt-1 line-clamp-1">
                                                {session.description}
                                            </p>
                                        )}
                                    </div>
                                    <div className="text-right flex-shrink-0 ml-2">
                                        {session.duration_in_hours && (
                                            <p className="text-sm font-medium text-gray-900">
                                                {session.duration_in_hours}h
                                            </p>
                                        )}
                                        {session.billing_amount && (
                                            <p className="text-sm text-green-700">
                                                ${session.billing_amount}
                                            </p>
                                        )}
                                        {!session.ended_at ? (
                                            <span className="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                                Active
                                            </span>
                                        ) : (
                                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_COLORS[session.status]}`}>
                                                {STATUS_LABELS[session.status]}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}