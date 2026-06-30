import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Create({ priorities, jobTypes, jobStatuses, currentProperty }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        estimated_hours: '',
        budget: '',
        priority_id: '',
        job_type_id: '',
        job_status_id: '',
        latitude: '',
        longitude: '',
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

    const submit = (e) => {
        e.preventDefault();
        post(route('jobs.store'));
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
                        </div>
                    )}

                    <div className="flex gap-3 pt-2">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="flex-1 py-4 border border-gray-300 text-gray-700 rounded-lg text-base"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 py-4 bg-green-600 text-white rounded-lg text-base font-medium hover:bg-green-700 disabled:opacity-50"
                        >
                            Save Job
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}