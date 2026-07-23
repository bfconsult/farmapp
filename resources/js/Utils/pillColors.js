// Fixed 16-color palette for job status/priority/type pills. Keys must match
// App\Http\Controllers\SettingsController::PILL_COLORS exactly.
//
// Written as full literal class strings (not built from a template like
// `bg-${key}-100`) so Tailwind's build-time class scanner can see and keep
// every one of them - a dynamically-interpolated class name here would be
// silently purged from the production CSS.
export const PILL_COLORS = [
    { key: 'red', label: 'Red', swatch: 'bg-red-500', badge: 'bg-red-100 text-red-800' },
    { key: 'orange', label: 'Orange', swatch: 'bg-orange-500', badge: 'bg-orange-100 text-orange-800' },
    { key: 'amber', label: 'Amber', swatch: 'bg-amber-500', badge: 'bg-amber-100 text-amber-800' },
    { key: 'yellow', label: 'Yellow', swatch: 'bg-yellow-500', badge: 'bg-yellow-100 text-yellow-800' },
    { key: 'lime', label: 'Lime', swatch: 'bg-lime-500', badge: 'bg-lime-100 text-lime-800' },
    { key: 'green', label: 'Green', swatch: 'bg-green-500', badge: 'bg-green-100 text-green-800' },
    { key: 'emerald', label: 'Emerald', swatch: 'bg-emerald-500', badge: 'bg-emerald-100 text-emerald-800' },
    { key: 'teal', label: 'Teal', swatch: 'bg-teal-500', badge: 'bg-teal-100 text-teal-800' },
    { key: 'cyan', label: 'Cyan', swatch: 'bg-cyan-500', badge: 'bg-cyan-100 text-cyan-800' },
    { key: 'sky', label: 'Sky', swatch: 'bg-sky-500', badge: 'bg-sky-100 text-sky-800' },
    { key: 'blue', label: 'Blue', swatch: 'bg-blue-500', badge: 'bg-blue-100 text-blue-800' },
    { key: 'indigo', label: 'Indigo', swatch: 'bg-indigo-500', badge: 'bg-indigo-100 text-indigo-800' },
    { key: 'violet', label: 'Violet', swatch: 'bg-violet-500', badge: 'bg-violet-100 text-violet-800' },
    { key: 'purple', label: 'Purple', swatch: 'bg-purple-500', badge: 'bg-purple-100 text-purple-800' },
    { key: 'pink', label: 'Pink', swatch: 'bg-pink-500', badge: 'bg-pink-100 text-pink-800' },
    { key: 'rose', label: 'Rose', swatch: 'bg-rose-500', badge: 'bg-rose-100 text-rose-800' },
];

const DEFAULT_BADGE = 'bg-gray-100 text-gray-600';

// Falls back to a flat gray pill for records that haven't picked a color yet.
export function pillBadgeClass(colorKey) {
    return PILL_COLORS.find((c) => c.key === colorKey)?.badge ?? DEFAULT_BADGE;
}
