import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function HubCard({ href, icon, title, subtitle }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 bg-white border border-gray-200 rounded-xl p-3.5"
        >
            <span className="w-[34px] h-[34px] rounded-[9px] bg-green-50 flex items-center justify-center flex-shrink-0">
                {icon}
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold text-gray-900">{title}</p>
                <p className="text-xs text-gray-400">{subtitle}</p>
            </div>
            <svg className="w-4 h-4 text-gray-300 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.4} strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 5l7 7-7 7" />
            </svg>
        </Link>
    );
}

export default function Index({ metricsTracked, metricsDue, checklistTemplatesCount, assetsCount, assetsOverdue, canManage }) {
    return (
        <AuthenticatedLayout title="Manage">
            <Head title="Manage" />

            <div className="max-w-lg mx-auto mt-2 space-y-2 pb-24">
                <HubCard
                    href={route('metrics.index')}
                    title="Metrics"
                    subtitle={metricsDue > 0 ? `${metricsTracked} tracked · ${metricsDue} due` : `${metricsTracked} tracked`}
                    icon={
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                            <path d="M3 17l5-5 4 3 5-6 4 4" />
                            <path d="M3 20h18" />
                        </svg>
                    }
                />

                {canManage && (
                    <HubCard
                        href={route('manage.checklists')}
                        title="Checklists"
                        subtitle={`${checklistTemplatesCount} template${checklistTemplatesCount === 1 ? '' : 's'}`}
                        icon={
                            <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 12h6M9 16h6" />
                            </svg>
                        }
                    />
                )}

                <HubCard
                    href={route('manage.assets')}
                    title="Assets"
                    subtitle={assetsOverdue > 0 ? `${assetsCount} items · ${assetsOverdue} overdue` : `${assetsCount} items`}
                    icon={
                        <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="7" cy="17" r="3" />
                            <circle cx="17" cy="17" r="3" />
                            <path d="M7 17V8h7l3 4h3v5" />
                        </svg>
                    }
                />
            </div>
        </AuthenticatedLayout>
    );
}
