import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Create({ plannedJobs }) {
    const { currentProperty } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        description: '',
        farm_job_id: '',
        started_at: '',
        latitude: '',
        longitude: '',
    });

    const [locationStatus, setLocationStatus] = useState('getting');
    const [mode, setMode] = useState('now'); // 'now' or 'manual'

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
                () => setLocationStatus('failed'),
                { timeout: 10000, enableHighAccuracy: true }
            );
        } else {
            setLocationStatus('failed');
        }
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('work-sessions.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Start Work Session" />

            <div className="max-w-lg mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-xl font-semibold text-gray-900">Start Work</h1>
                    <div className="text-xs text-gray-500">
                        {locationStatus === 'getting' && '📍 Getting location...'}
                        {locationStatus === 'got' && '📍 Location saved'}
                        {locationStatus === 'failed' && '📍 Location unavailable'}
                    </div>
                </div>

                {currentProperty && (
                    <div className="mb-4 p-3 bg-green-50 rounded-md border border-green-200">
                        <p className="text-xs text-gray-500">Property</p>
                        <p className="font-medium text-green-800">{currentProperty.name}</p>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
                    {/* Start time mode */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <div className="flex gap-2 mb-4">
                            <button
                                type="button"
                                onClick={() => { setMode('now'); setData('started_at', ''); }}
                                className={`flex-1 py-2 rounded-lg text-sm font-medium ${mode === 'now' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                Starting Now
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('manual')}
                                className={`flex-1 py-2 rounded-lg text-sm font-medium ${mode === 'manual' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                Enter Time
                            </button>
                        </div>

                        {mode === 'manual' && (
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Start Time</label>
                                <input
                                    type="datetime-local"
                                    value={data.started_at}
                                    onChange={(e) => setData('started_at', e.target.value)}
                                    className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                />
                                {errors.started_at && <p className="mt-1 text-sm text-red-600">{errors.started_at}</p>}
                            </div>
                        )}
                    </div>

                    {/* Link to planned job */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Link to Planned Job (optional)
                        </label>
                        <select
                            value={data.farm_job_id}
                            onChange={(e) => setData('farm_job_id', e.target.value)}
                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                        >
                            <option value="">Ad-hoc work (no planned job)</option>
                            {plannedJobs.map((job) => (
                                <option key={job.id} value={job.id}>{job.name}</option>
                            ))}
                        </select>
                    </div>

                    {/* Description */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            What are you doing? (optional)
                        </label>
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Brief description..."
                            rows={3}
                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                        />
                    </div>

                    <div className="flex gap-3">
                        <Link
                            href={route('work-sessions.index')}
                            className="flex-1 py-4 border border-gray-300 text-gray-700 rounded-lg text-base text-center"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 py-4 bg-green-600 text-white rounded-lg text-base font-medium hover:bg-green-700 disabled:opacity-50"
                        >
                            Start Work
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}