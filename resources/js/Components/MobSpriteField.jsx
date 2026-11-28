import { LIVESTOCK_SPRITES } from '@/Utils/livestockSprites';

// How a mob's animals are drawn in the farm view, on the mob's add and edit
// forms (the Livestock list and the mob's own page). Each animal can still
// be changed on its own page.
export default function MobSpriteField({ values, setValues }) {
    return (
        <div>
            <label className="block text-xs text-gray-500 mb-1">Shown in the farm view as</label>
            <select
                value={values.sprite ?? ''}
                onChange={(e) => setValues({ ...values, sprite: e.target.value })}
                className="w-full border-gray-300 rounded-lg p-2 text-sm"
            >
                <option value="">Picked by livestock type</option>
                {Object.entries(LIVESTOCK_SPRITES).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                ))}
            </select>
            <p className="mt-1 text-xs text-gray-500">Each animal can be changed on its own page.</p>
        </div>
    );
}
