import { PILL_COLORS } from '@/Utils/pillColors';

// A 16-swatch palette picker. `value` is a color key (or null/undefined for
// "no color set" - renders as an outlined empty swatch, not a selection).
export default function ColorPicker({ value, onChange }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {PILL_COLORS.map((color) => (
                <button
                    key={color.key}
                    type="button"
                    title={color.label}
                    onClick={() => onChange(value === color.key ? null : color.key)}
                    className={`h-6 w-6 rounded-full ${color.swatch} ${
                        value === color.key
                            ? 'ring-2 ring-offset-2 ring-gray-500'
                            : 'hover:ring-2 hover:ring-offset-1 hover:ring-gray-300'
                    }`}
                />
            ))}
        </div>
    );
}
