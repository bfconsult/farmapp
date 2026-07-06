import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

const ROLE_LABELS = {
    admin: 'Admin',
    manager: 'Manager',
    worker: 'Worker',
    approver: 'Approver',
};

const ROLE_COLORS = {
    admin: 'bg-purple-100 text-purple-700',
    manager: 'bg-blue-100 text-blue-700',
    worker: 'bg-green-100 text-green-700',
    approver: 'bg-yellow-100 text-yellow-700',
};

export default function Edit({ job, priorities, jobTypes, jobStatuses, properties, teamRoles }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: job.name,
        description: job.description ?? '',
        estimated_hours: job.estimated_hours ?? '',
        budget: job.budget ?? '',
        hourly_rate: job.hourly_rate ?? '',
        priority_id: job.priority_id ?? '',
        job_type_id: job.job_type_id ?? '',
        job_status_id: job.job_status_id ?? '',
        property_id: job.property_id,
        assignee_ids: job.assignees.map((user) => user.id),
        repeats: false,
        interval: 'monthly',
        starts_on: job.created_at ? job.created_at.slice(0, 10) : new Date().toISOString().slice(0, 10),
        scheduled_date: job.scheduled_date ? job.scheduled_date.slice(0, 10) : '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('jobs.update', job.id));
    };

    const toggleAssignee = (userId) => {
        setData('assignee_ids',
            data.assignee_ids.includes(userId)
                ? data.assignee_ids.filter((id) => id !== userId)
                : [...data.assignee_ids, userId]
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Edit ${job.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-semibold text-gray-900 mb-6">
                        Edit Job
                    </h1>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Scheduled Date</label>
                                <input
                                    type="date"
                                    value={data.scheduled_date}
                                    onChange={(e) => setData('scheduled_date', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.scheduled_date && <p className="mt-1 text-sm text-red-600">{errors.scheduled_date}</p>}
                            </div>

                            <div className="grid grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Estimated Hours</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        value={data.estimated_hours}
                                        onChange={(e) => setData('estimated_hours', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.estimated_hours && <p className="mt-1 text-sm text-red-600">{errors.estimated_hours}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Budget ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={data.budget}
                                        onChange={(e) => setData('budget', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.budget && <p className="mt-1 text-sm text-red-600">{errors.budget}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Hourly Rate ($)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="Worker's rate"
                                        value={data.hourly_rate}
                                        onChange={(e) => setData('hourly_rate', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    />
                                    {errors.hourly_rate && <p className="mt-1 text-sm text-red-600">{errors.hourly_rate}</p>}
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Property</label>
                                <select
                                    value={data.property_id}
                                    onChange={(e) => setData('property_id', e.target.value)}
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                >
                                    <option value="">Select a property</option>
                                    {properties.map((property) => (
                                        <option key={property.id} value={property.id}>{property.name}</option>
                                    ))}
                                </select>
                                {errors.property_id && <p className="mt-1 text-sm text-red-600">{errors.property_id}</p>}
                            </div>

                            <div className="grid grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                    <select
                                        value={data.priority_id}
                                        onChange={(e) => setData('priority_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {priorities.map((priority) => (
                                            <option key={priority.id} value={priority.id}>{priority.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <select
                                        value={data.job_type_id}
                                        onChange={(e) => setData('job_type_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {jobTypes.map((type) => (
                                            <option key={type.id} value={type.id}>{type.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        value={data.job_status_id}
                                        onChange={(e) => setData('job_status_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                    >
                                        <option value="">Select</option>
                                        {jobStatuses.map((status) => (
                                            <option key={status.id} value={status.id}>{status.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">Assigned to</label>
                                <p className="text-xs text-gray-500 mb-2">
                                    This job is visible to whoever is checked below.
                                </p>
                                <div className="space-y-1 border border-gray-200 rounded-md divide-y divide-gray-100">
                                    {teamRoles.map((role) => (
                                        <label
                                            key={role.user.id}
                                            className="flex items-center justify-between gap-2 px-3 py-2 cursor-pointer"
                                        >
                                            <span className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={data.assignee_ids.includes(role.user.id)}
                                                    onChange={() => toggleAssignee(role.user.id)}
                                                    className="rounded text-green-600 focus:ring-green-500"
                                                />
                                                <span className="text-sm text-gray-900">{role.user.name}</span>
                                            </span>
                                            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${ROLE_COLORS[role.type]}`}>
                                                {ROLE_LABELS[role.type]}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {errors.assignee_ids && <p className="mt-1 text-sm text-red-600">{errors.assignee_ids}</p>}
                            </div>

                            {!job.recurring_job_id && (
                                <div className="mb-6 border-t border-gray-200 pt-4">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input
                                            type="checkbox"
                                            checked={data.repeats}
                                            onChange={(e) => setData('repeats', e.target.checked)}
                                            className="rounded text-green-600 focus:ring-green-500"
                                        />
                                        Make this job repeat
                                    </label>

                                    {data.repeats && (
                                        <div className="grid grid-cols-2 gap-4 mt-3">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Repeats</label>
                                                <select
                                                    value={data.interval}
                                                    onChange={(e) => setData('interval', e.target.value)}
                                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                                >
                                                    <option value="daily">Daily</option>
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly">Monthly</option>
                                                    <option value="yearly">Yearly</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Starting</label>
                                                <input
                                                    type="date"
                                                    value={data.starts_on}
                                                    onChange={(e) => setData('starts_on', e.target.value)}
                                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="flex justify-end gap-4">
                                <Link
                                    href={route('jobs.show', job.id)}
                                    className="px-4 py-2 text-gray-700 hover:text-gray-900"
                                >
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
                                >
                                    Update Job
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}