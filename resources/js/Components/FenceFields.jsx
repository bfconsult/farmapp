// A fence's details (its type and wires), on the asset add and edit forms
// when the asset is of the property's fence type. Matches Asset::FENCE_TYPES.
export const FENCE_TYPES = {
    plain: 'Plain wire',
    barbed: 'Barbed wire',
    electric: 'Electric',
    netting: 'Netting',
    post_and_rail: 'Post and rail',
    other: 'Other',
};

// Whether an asset type is the one fences go in.
export const isFenceType = (type) => type?.kind === 'fence';

// The form values for an asset's fence details.
export function fenceValues(asset) {
    return { details: { fence_type: asset?.details?.fence_type ?? '', wires: asset?.details?.wires ?? '' } };
}

// "Electric, 3 wires" - a fence's details in a few words.
export function describeFence(details) {
    const parts = [];
    if (details?.fence_type) parts.push(FENCE_TYPES[details.fence_type] ?? details.fence_type);
    if (details?.wires) parts.push(`${details.wires} ${Number(details.wires) === 1 ? 'wire' : 'wires'}`);
    return parts.join(', ');
}

export default function FenceFields({ values, setValues }) {
    const details = values.details ?? {};
    const setDetail = (key, value) => setValues({ ...values, details: { ...details, [key]: value } });

    return (
        <div className="grid grid-cols-2 gap-2">
            <select
                value={details.fence_type ?? ''}
                onChange={(e) => setDetail('fence_type', e.target.value)}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">Type of fence</option>
                {Object.entries(FENCE_TYPES).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                ))}
            </select>
            <input
                type="number"
                min="1"
                max="20"
                value={details.wires ?? ''}
                onChange={(e) => setDetail('wires', e.target.value)}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
                placeholder="Wires (optional)"
            />
        </div>
    );
}
