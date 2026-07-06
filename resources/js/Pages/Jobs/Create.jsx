import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const todayIso = () => new Date().toISOString().slice(0, 10);

export default function Create({ priorities, jobTypes, jobStatuses, currentProperty }) {
    const defaultStatus = jobStatuses.find((status) => status.is_default);

    const { data, setData, post, transform, processing, errors } = useForm({
        name: '',
        description: '',
        estimated_hours: '',
        budget: '',
        hourly_rate: '',
        priority_id: '',
        job_type_id: '',
        job_status_id: defaultStatus ? String(defaultStatus.id) : '',
        latitude: '',
        longitude: '',
        repeats: false,
        interval: 'monthly',
        starts_on: todayIso(),
    });

    const [locationStatus, setLocationStatus] = useState('getting');
    const [showOptional, setShowOptional] = useState(false);

    useEffect(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    setData(data => ({
                        ...data,
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                    }));
                    setLocationStatus('got');
                },
                () => {
                    setLocationStatus('failed');
                },
                { timeout: 10000, enableHighAccuracy: true }
            );
        } else {
            setLocationStatus('failed');
        }
    }, []);

    const submitWithIntent = (intent) => {
        transform((data) => ({ ...data, intent }));
        post(route('jobs.store'));
    };

    const submit = (e) => {
        e.preventDefault();
        submitWithIntent(data.repeats ? 'plan' : 'camera');
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Job" />

            <div className="max-w-lg mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-xl font-semibold text-gray-900">Add Job</h1>
                    <div className="text-xs text-gray-500">
                        {locationStatus === 'getting' && '📍 Getting location...'}
                        {locationStatus === 'got' && '📍 Location saved'}
                        {locationStatus === 'failed' && '📍 Location unavailable'}
                    </div>
                </div>

                <div className="mb-4 p-3 bg-green-50 rounded-md border border-green-200">
                    <p className="text-xs text-gray-500">Property</p>
                    <p className="font-medium text-green-800">{currentProperty.name}</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <input
                            type="text"
                            placeholder="Job name *"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                            className="w-full text-lg border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-4"
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowOptional(!showOptional)}
                        className="text-sm text-green-600 hover:text-green-800"
                    >
                        {showOptional ? '▲ Hide details' : '▼ Add details (optional)'}
                    </button>

                    {showOptional && (
                        <div className="space-y-4">
                            <textarea
                                placeholder="Description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                            />

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Priority</label>
                                    <select
                                        value={data.priority_id}
                                        onChange={(e) => setData('priority_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {priorities.map((priority) => (
                                            <option key={priority.id} value={priority.id}>{priority.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Type</label>
                                    <select
                                        value={data.job_type_id}
                                        onChange={(e) => setData('job_type_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {jobTypes.map((type) => (
                                            <option key={type.id} value={type.id}>{type.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Status</label>
                                    <select
                                        value={data.job_status_id}
                                        onChange={(e) => setData('job_status_id', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="">Select</option>
                                        {jobStatuses.map((status) => (
                                            <option key={status.id} value={status.id}>{status.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Est. Hours</label>
                                    <input
                                        type="number"
                                        step="0.5"
                                        placeholder="0"
                                        value={data.estimated_hours}
                                        onChange={(e) => setData('estimated_hours', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Budget ($)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        value={data.budget}
                                        onChange={(e) => setData('budget', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">
                                        Hourly Rate ($) <span className="text-gray-400">optional</span>
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="Uses worker's rate"
                                        value={data.hourly_rate}
                                        onChange={(e) => setData('hourly_rate', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="border-t border-gray-200 pt-4">
                        <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input
                                type="checkbox"
                                checked={data.repeats}
                                onChange={(e) => setData('repeats', e.target.checked)}
                                className="rounded"
                            />
                            Make this job repeat
                        </label>

                        {data.repeats && (
                            <div className="grid grid-cols-2 gap-3 mt-3">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Repeats</label>
                                    <select
                                        value={data.interval}
                                        onChange={(e) => setData('interval', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Starting</label>
                                    <input
                                        type="date"
                                        value={data.starts_on}
                                        onChange={(e) => setData('starts_on', e.target.value)}
                                        className="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-2 pt-2">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="flex-1 flex items-center justify-center py-3 border border-gray-300 text-gray-700 rounded-lg text-sm"
                        >
                            Cancel
                        </button>
                        {data.repeats ? (
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex-1 flex items-center justify-center py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                            >
                                Create Repeating Job
                            </button>
                        ) : (
                            <>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 flex flex-col items-center justify-center gap-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                                >
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Report
                                </button>
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() => submitWithIntent('plan')}
                                    className="flex-1 flex flex-col items-center justify-center gap-1 py-3 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50"
                                >
                                    <svg className="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Plan
                                </button>
                            </>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}