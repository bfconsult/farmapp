import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head } from '@inertiajs/react';

const COLUMNS = [
    { key: 'farm_jobs_count', label: 'Jobs' },
    { key: 'work_sessions_count', label: 'Work Sessions' },
    { key: 'assets_count', label: 'Assets' },
    { key: 'admin_properties_count', label: 'Properties' },
    { key: 'checklists_count', label: 'Checklists' },
    { key: 'metrics_count', label: 'Metrics' },
];

export default function Index({ users }) {
    return (
        <AuthenticatedLayout title="App Admin">
            <Head title="App Admin" />

            <div className="max-w-4xl mx-auto mt-2 space-y-4 pb-24 px-4">
                <div className="flex items-center justify-between">
                    <BackLink href={route('profile.edit')}>Profile</BackLink>
                </div>

                <div>
                    <h1 className="text-lg font-semibold text-gray-900">User Activity Report</h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Counts of what each user has added across the whole app, not just the currently-selected property.
                        "Properties" counts properties they currently hold the admin role on, as an approximation of who created it.
                    </p>
                </div>

                <div className="bg-white rounded-lg shadow overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide">User</th>
                                {COLUMNS.map((col) => (
                                    <th key={col.key} className="text-right px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wide whitespace-nowrap">
                                        {col.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-4 py-3">
                                        <p className="text-gray-900">{user.name}</p>
                                        <p className="text-xs text-gray-400">{user.email}</p>
                                    </td>
                                    {COLUMNS.map((col) => (
                                        <td key={col.key} className="text-right px-4 py-3 text-gray-700 tabular-nums">
                                            {user[col.key]}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
