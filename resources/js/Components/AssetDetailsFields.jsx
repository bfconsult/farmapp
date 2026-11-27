// The details an asset records for its type's kind, on the asset add and
// edit forms: a fence's type and wires, a tank's capacity, a trough's size
// and capacity, vegetation's type. Matches Asset::DETAILS,
// Asset::FENCE_TYPES, Asset::TROUGH_SIZES and Asset::VEGETATION_TYPES.
export const FENCE_TYPES = {
    plain: 'Plain wire',
    barbed: 'Barbed wire',
    electric: 'Electric',
    netting: 'Netting',
    post_and_rail: 'Post and rail',
    other: 'Other',
};

// Standard stock trough sizes, each with about how many litres it holds -
// picking one fills in the capacity, which can still be changed.
export const TROUGH_SIZES = {
    round_1200: { label: 'Round, 1.2 m across', litres: 450 },
    round_1500: { label: 'Round, 1.5 m across', litres: 700 },
    round_1800: { label: 'Round, 1.8 m across', litres: 1100 },
    round_2400: { label: 'Round, 2.4 m across', litres: 2000 },
    rect_1200: { label: 'Rectangular, 1.2 m long', litres: 200 },
    rect_2400: { label: 'Rectangular, 2.4 m long', litres: 400 },
    rect_3000: { label: 'Rectangular, 3 m long', litres: 500 },
    rect_3600: { label: 'Rectangular, 3.6 m long', litres: 600 },
    other: { label: 'Other size', litres: null },
};

export const VEGETATION_TYPES = {
    tree: 'Trees',
    shrubs: 'Shrubs',
    undergrowth: 'Undergrowth',
};

// The kind of the asset type with this id, from the property's types.
export const kindOfType = (assetTypes, typeId) =>
    assetTypes.find((type) => String(type.id) === String(typeId))?.kind ?? null;

// The form values for an asset's details - every kind's, as the server
// keeps only those of the type it ends up with.
export function detailValues(asset) {
    const details = asset?.details ?? {};
    return {
        details: {
            fence_type: details.fence_type ?? '',
            wires: details.wires ?? '',
            capacity: details.capacity ?? '',
            trough_size: details.trough_size ?? '',
            vegetation_type: details.vegetation_type ?? '',
        },
    };
}

export const formatLitres = (litres) => `${Math.round(Number(litres)).toLocaleString()} L`;

// An asset's details in a few words: "Electric, 3 wires", "20,000 L", "Shrubs".
export function describeDetails(kind, details) {
    if (!details) return '';
    if (kind === 'fence') {
        const parts = [];
        if (details.fence_type) parts.push(FENCE_TYPES[details.fence_type] ?? details.fence_type);
        if (details.wires) parts.push(`${details.wires} ${Number(details.wires) === 1 ? 'wire' : 'wires'}`);
        return parts.join(', ');
    }
    if (kind === 'tank') return details.capacity ? formatLitres(details.capacity) : '';
    if (kind === 'trough') {
        const size = TROUGH_SIZES[details.trough_size];
        return [size && details.trough_size !== 'other' ? size.label : null, details.capacity ? formatLitres(details.capacity) : null].filter(Boolean).join(', ');
    }
    if (kind === 'vegetation') return VEGETATION_TYPES[details.vegetation_type] ?? '';
    return '';
}

const field = 'w-full border-gray-300 rounded-lg p-2 text-sm';

export default function AssetDetailsFields({ kind, values, setValues }) {
    const details = values.details ?? {};
    const setDetail = (key, value) => setValues({ ...values, details: { ...details, [key]: value } });

    if (kind === 'fence') {
        return (
            <div className="grid grid-cols-2 gap-2">
                <select value={details.fence_type ?? ''} onChange={(e) => setDetail('fence_type', e.target.value)} className={field}>
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
                    className={field}
                    placeholder="Wires (optional)"
                />
            </div>
        );
    }

    if (kind === 'tank') {
        return (
            <div>
                <div className="flex items-center gap-2">
                    <input
                        type="number"
                        min="0"
                        step="any"
                        value={details.capacity ?? ''}
                        onChange={(e) => setDetail('capacity', e.target.value)}
                        className={field}
                        placeholder="Capacity (optional)"
                    />
                    <span className="text-sm text-gray-500">L</span>
                </div>
                <p className="mt-1 text-xs text-gray-500">Litres when full. A level sensor fitted to it shows how many are in it.</p>
            </div>
        );
    }

    if (kind === 'trough') {
        // Picking a standard size fills in its capacity.
        const pickSize = (key) => setValues({
            ...values,
            details: { ...details, trough_size: key, capacity: TROUGH_SIZES[key]?.litres ?? details.capacity },
        });
        return (
            <div>
                <div className="grid grid-cols-2 gap-2">
                    <select value={details.trough_size ?? ''} onChange={(e) => pickSize(e.target.value)} className={field}>
                        <option value="">Size</option>
                        {Object.entries(TROUGH_SIZES).map(([key, size]) => (
                            <option key={key} value={key}>{size.label}</option>
                        ))}
                    </select>
                    <div className="flex items-center gap-2">
                        <input
                            type="number"
                            min="0"
                            step="any"
                            value={details.capacity ?? ''}
                            onChange={(e) => setDetail('capacity', e.target.value)}
                            className={field}
                            placeholder="Capacity"
                        />
                        <span className="text-sm text-gray-500">L</span>
                    </div>
                </div>
                <p className="mt-1 text-xs text-gray-500">A standard size fills in about how much it holds - change it if yours differs.</p>
            </div>
        );
    }

    if (kind === 'vegetation') {
        return (
            <div>
                <select value={details.vegetation_type ?? ''} onChange={(e) => setDetail('vegetation_type', e.target.value)} className={field}>
                    <option value="">Trees, shrubs or undergrowth?</option>
                    {Object.entries(VEGETATION_TYPES).map(([key, label]) => (
                        <option key={key} value={key}>{label}</option>
                    ))}
                </select>
                <p className="mt-1 text-xs text-gray-500">Locate it by a point for one tree or clump, a shape for an area, or a line for a row.</p>
            </div>
        );
    }

    return null;
}
