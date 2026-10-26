import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ device, models, prefill }) {
    const isNew = !device;

    const { data, setData, post, put, processing, errors } = useForm({
        name: device?.name ?? prefill?.name ?? '',
        model: device?.model ?? models[0]?.value ?? '',
        dev_eui: device?.dev_eui ?? prefill?.dev_eui ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isNew) {
            post(route('manage.sensors.store'));
        } else {
            put(route('manage.sensors.update', device.id));
        }
    };

    const destroy = () => {
        if (confirm(`Remove "${device.name}"? Its readings are deleted too.`)) {
            router.delete(route('manage.sensors.destroy', device.id));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isNew ? 'Add Sensor' : `Edit ${device.name}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {isNew ? 'Add Sensor' : 'Edit Sensor'}
                        </h1>
                        <BackLink href={isNew ? route('manage.sensors.index') : route('manage.sensors.show', device.id)}>
                            {isNew ? 'Sensors' : device.name}
                        </BackLink>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Front gate"
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            {isNew ? (
                                <>
                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Sensor type</label>
                                        <select
                                            value={data.model}
                                            onChange={(e) => setData('model', e.target.value)}
                                            className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                        >
                                            {models.map((model) => (
                                                <option key={model.value} value={model.value}>{model.label}</option>
                                            ))}
                                        </select>
                                        {errors.model && <p className="mt-1 text-sm text-red-600">{errors.model}</p>}
                                    </div>

                                    <div className="mb-4">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">DevEUI</label>
                                        <input
                                            type="text"
                                            value={data.dev_eui}
                                            onChange={(e) => setData('dev_eui', e.target.value.replace(/[\s:-]/g, ''))}
                                            placeholder="16 characters, e.g. A84041210862A0CE"
                                            maxLength={16}
                                            className="w-full font-mono border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                        />
                                        {errors.dev_eui && <p className="mt-1 text-sm text-red-600">{errors.dev_eui}</p>}
                                    </div>
                                </>
                            ) : (
                                <p className="mb-4 text-sm text-gray-500">
                                    {device.model_label} · <span className="font-mono">{device.dev_eui}</span>
                                    <br />
                                    To change the type or DevEUI, remove the sensor and add it again. Its stored uplinks are decoded again when you do.
                                </p>
                            )}

                            <div className="flex items-center justify-between">
                                {!isNew ? (
                                    <button type="button" onClick={destroy} className="text-sm text-red-600">
                                        Remove sensor
                                    </button>
                                ) : <span />}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md font-medium disabled:opacity-50"
                                >
                                    {isNew ? 'Add Sensor' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
