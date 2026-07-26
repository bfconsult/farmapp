import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetricsManager from '@/Components/MetricsManager';
import BackLink from '@/Components/BackLink';
import { Head } from '@inertiajs/react';

export default function Index({ metrics }) {
    return (
        <AuthenticatedLayout title="Metrics">
            <Head title="Metrics" />

            <div className="max-w-lg mx-auto mt-2 space-y-4 pb-24">
                <div className="flex items-center justify-between">
                    <BackLink href={route('manage.index')}>Manage</BackLink>
                </div>

                <h1 className="text-lg font-semibold text-gray-900">Metrics</h1>

                <MetricsManager metrics={metrics} />
            </div>
        </AuthenticatedLayout>
    );
}
