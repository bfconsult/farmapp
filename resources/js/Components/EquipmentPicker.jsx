import { Link } from '@inertiajs/react';

/**
 * "Equipment used" checklist for a work session (WorkSession::assets()) -
 * any number of the property's assets, e.g. the tractor + flail mower.
 * Maintaining an asset is recorded on the job instead, not here.
 *
 * `prefilled` shows a hint when the selection was filled in from an earlier
 * session on the same job (see jobEquipment / prefillEquipment()).
 */
export default function EquipmentPicker({ assets, value, onChange, prefilled = false, error }) {
    const toggle = (assetId) => {
        onChange(value.includes(assetId) ? value.filter((id) => id !== assetId) : [...value, assetId]);
    };

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                Equipment used <span className="text-gray-400 font-normal">optional</span>
            </label>
            {assets.length > 0 ? (
                <>
                    <div className="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-60 overflow-y-auto">
                        {assets.map((asset) => (
                            <label key={asset.id} className="flex items-center gap-2 px-3 py-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={value.includes(asset.id)}
                                    onChange={() => toggle(asset.id)}
                                    className="rounded text-green-600 focus:ring-green-500"
                                />
                                <span className="text-sm text-gray-900">{asset.name}</span>
                            </label>
                        ))}
                    </div>
                    {prefilled && (
                        <p className="mt-1 text-xs text-gray-500">Filled in from the last session on this job - change as needed.</p>
                    )}
                </>
            ) : (
                <p className="text-sm text-gray-500">
                    Once you set up assets for this property, you'll be able to record the equipment used here.{' '}
                    <Link href={route('manage.assets')} className="text-green-600 font-medium">
                        Add an asset
                    </Link>
                </p>
            )}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}

/**
 * The equipment to pre-fill when a job is picked, or null to leave the
 * current selection alone - only while the person hasn't chosen any
 * equipment themselves, so a deliberate selection is never overwritten.
 */
export function prefillEquipment(jobEquipment, jobId, touched) {
    if (touched) return null;
    return jobEquipment?.[jobId] ?? [];
}
