import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    toLocalInputValue,
    fromLocalInputValue,
    splitLocalValue,
    joinLocalValue,
    billingBlockLabel,
    timeOptionsForBlock,
    floorToBillingBlock,
} from '@/dateInput';

export default function Create({ plannedJobs, billingBlockMinutes }) {
    const { currentProperty } = usePage().props;
    const { data, setData, post, processing, errors, transform } = useForm({
        description: '',
        farm_job_id: '',
        started_date: '',
        started_time: '',
        ended_date: '',
        ended_time: '',
        latitude: '',
        longitude: '',
    });

    const [locationStatus, setLocationStatus] = useState('getting');
    const [mode, setMode] = useState('now'); // 'now' or 'manual'
    const [now, setNow] = useState(() => new Date());

    const baseTimeOptions = useMemo(() => timeOptionsForBlock(billingBlockMinutes), [billingBlockMinutes]);
    const endedTimeOptions = data.ended_time
        ? baseTimeOptions
        : [{ value: '', label: '— Still active —' }, ...baseTimeOptions];

    const switchToManual = () => {
        setMode('manual');
        // Seed the start with "now" (same value the 'now' mode was already
        // showing) so the common case - adjusting just the date or nudging
        // the time slightly - only needs a small tweak, not a fresh entry.
        const floored = splitLocalValue(toLocalInputValue(floorToBillingBlock(new Date(), billingBlockMinutes).toISOString()));
        setData((prev) => ({ ...prev, started_date: floored.date, started_time: floored.time }));
    };

    const switchToNow = () => {
        setMode('now');
        setData((prev) => ({ ...prev, started_date: '', started_time: '', ended_date: '', ended_time: '' }));
    };

    useEffect(() => {
        if (mode !== 'now') return;
        const interval = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(interval);
    }, [mode]);

    const displayedStartTime = floorToBillingBlock(now, billingBlockMinutes);

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
        transform((data) => ({
            ...data,
            started_at: fromLocalInputValue(joinLocalValue(data.started_date, data.started_time)),
            ended_at: fromLocalInputValue(joinLocalValue(data.ended_date, data.ended_time)),
        }));
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
                    {/* Start time */}
                    <div className="bg-white rounded-lg shadow p-4">
                        {mode === 'now' ? (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-gray-500 mb-1">
                                        Start Time
                                        {billingBlockMinutes && (
                                            <span className="text-gray-400"> (nearest {billingBlockLabel(billingBlockMinutes)})</span>
                                        )}
                                    </p>
                                    <p className="text-lg font-medium text-gray-900">
                                        {billingBlockMinutes
                                            ? displayedStartTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                                            : now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={switchToManual}
                                    className="text-sm text-green-600 font-medium"
                                >
                                    Change
                                </button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-gray-500">Manual times</span>
                                    <button
                                        type="button"
                                        onClick={switchToNow}
                                        className="text-sm text-green-600 font-medium"
                                    >
                                        Use current time
                                    </button>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Start Time
                                        {billingBlockMinutes && (
                                            <span className="text-gray-400 font-normal"> (in steps of {billingBlockLabel(billingBlockMinutes)})</span>
                                        )}
                                    </label>
                                    <div className="grid grid-cols-2 gap-2">
                                        <input
                                            type="date"
                                            value={data.started_date}
                                            onChange={(e) => setData('started_date', e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        />
                                        <select
                                            value={data.started_time}
                                            onChange={(e) => setData('started_time', e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        >
                                            {baseTimeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    {errors.started_at && <p className="mt-1 text-sm text-red-600">{errors.started_at}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        End Time <span className="text-gray-400 font-normal">(optional — leave as "Still active" to start an active session)</span>
                                    </label>
                                    <div className="grid grid-cols-2 gap-2">
                                        <input
                                            type="date"
                                            value={data.ended_date}
                                            onChange={(e) => setData('ended_date', e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        />
                                        <select
                                            value={data.ended_time}
                                            onChange={(e) => setData('ended_time', e.target.value)}
                                            className="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500 p-3"
                                        >
                                            {endedTimeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    {errors.ended_at && <p className="mt-1 text-sm text-red-600">{errors.ended_at}</p>}
                                </div>
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
                            {data.ended_date && data.ended_time ? 'Log Work' : 'Start Work'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}