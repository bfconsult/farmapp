import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import MetricsManager from '@/Components/MetricsManager';
import { Head } from '@inertiajs/react';

export default function Index({ metrics }) {
    return (
        <AuthenticatedLayout title="Metrics">
            <Head title="Metrics" />

            <div className="max-w-lg mx-auto mt-2 pb-24">
                <MetricsManager metrics={metrics} />
            </div>
        </AuthenticatedLayout>
    );
}
