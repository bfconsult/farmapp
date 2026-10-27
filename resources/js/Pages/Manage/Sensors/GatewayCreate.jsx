import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BackLink from '@/Components/BackLink';
import { Head, useForm } from '@inertiajs/react';

export default function GatewayCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        gateway_eui: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('manage.gateways.store'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Add Gateway" />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">Add Gateway</h1>
                        <BackLink href={route('manage.sensors.index')}>Sensors</BackLink>
                    </div>

                    <div className="bg-white rounded-lg shadow p-6">
                        <form onSubmit={submit}>
                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Shed gateway"
                                    className="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Gateway EUI</label>
                                <input
                                    type="text"
                                    value={data.gateway_eui}
                                    onChange={(e) => setData('gateway_eui', e.target.value.replace(/[\s:-]/g, ''))}
                                    placeholder="16 characters, e.g. AC1F09FFFE291787"
                                    maxLength={16}
                                    className="w-full font-mono border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                />
                                {errors.gateway_eui && <p className="mt-1 text-sm text-red-600">{errors.gateway_eui}</p>}
                            </div>

                            <p className="mb-4 text-sm text-gray-500">
                                Next you'll get a key and the addresses to enter in the gateway's HTTP integration settings.
                            </p>

                            <div className="flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 bg-green-600 text-white rounded-md font-medium disabled:opacity-50"
                                >
                                    Add Gateway
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
